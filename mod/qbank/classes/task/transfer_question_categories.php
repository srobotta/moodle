<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_qbank\task;

use context_system;
use core\context;
use core\log\Debug;
use core\task\adhoc_task;
use core\task\manager;
use core_course_category;
use core_question\local\bank\question_bank_helper;
use stdClass;

/**
 * /**
 * This script transfers question categories at CONTEXT_SITE, CONTEXT_COURSE, & CONTEXT_COURSECAT to a new qbank instance
 * context.
 *
 * Firstly, it finds any question categories where questions are not being used and deletes them, including questions.
 *
 * Then for any remaining, if it is at course level context, it creates a mod_qbank instance taking the course name
 * and moves the category there including subcategories, files and tags.
 *
 * If the original question category context was at system context, then it creates a mod_qbank instance on the site course i.e.
 * front page and moves the category & sub categories there, along with its files and tags.
 *
 * If the original question category context was a course category context, then it creates a course in that category,
 * taking the category name. Then it creates a mod_qbank instance in that course and moves the category & sub categories
 * there, along with files and tags belonging to those categories.
 *
 * @package    mod_qbank
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transfer_question_categories extends adhoc_task {

    /**
     * Run the install task.
     *
     * @return void
     */
    public function execute() {

        global $DB, $CFG;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->libdir . '/questionlib.php');

        $this->fix_wrong_parents();

        $recordset = $DB->get_recordset('question_categories', ['parent' => 0]);
        Debug::get_instance()->log('Start handling question categories.');

        foreach ($recordset as $oldtopcategory) {

            Debug::get_instance()->log('Start transfering old top category {1}', $oldtopcategory->id);

            // There are cases where the contextid is 0, we cannot handle these categories automatically.
            if ($oldtopcategory->contextid == 0) {
                Debug::get_instance()->log('Category {1} has no contextid, skip it.', $oldtopcategory->id);
                continue;
            }
            if (!$oldcontext = context::instance_by_id($oldtopcategory->contextid, IGNORE_MISSING)) {
                // That context does not exist anymore, we will treat these as if they were at site context level.
                $oldcontext = context_system::instance();
                Debug::get_instance()->log('Category {1} has old contextid {2}, use system context.', $oldtopcategory->id, $oldtopcategory->contextid);
            }

            $trans = $DB->start_delegated_transaction();

            // Remove any unused questions if they are marked as deleted.
            // Also, if a category contained questions which were all unusable then delete it as well.
            $subcategories = $DB->get_records_select('question_categories',
                'parent <> 0 AND contextid = :contextid',
                ['contextid' => $oldtopcategory->contextid]
            );
            // This gives us categories in parent -> child order so array_reverse it,
            // because we should process stale categories from the bottom up.
            $subcategories = array_reverse(\sort_categories_by_tree($subcategories, $oldtopcategory->id));
            foreach ($subcategories as $subcategory) {
                \qbank_managecategories\helper::question_remove_stale_questions_from_category($subcategory->id);
                if ($this->question_category_is_empty($subcategory->id)) {
                    question_category_delete_safe($subcategory);
                }
            }

            // If the top category no longer has any subcategories, because they only contained stale questions,
            // delete the top category and stop here without creating a new qbank.
            if (!$DB->record_exists('question_categories', ['parent' => $oldtopcategory->id])) {
                $DB->delete_records('question_categories', ['id' => $oldtopcategory->id]);
                $trans->allow_commit();
                continue;
            }

            // We don't want to transfer any categories at valid contexts i.e. quiz modules.
            if ($oldcontext->contextlevel === CONTEXT_MODULE) {
                $trans->allow_commit();
                continue;
            }

            // Category is in use so let's process it. Firstly, a course and mod instance is needed.
            switch ($oldcontext->contextlevel) {
                case CONTEXT_SYSTEM:
                    $course = get_site();
                    $bankname = question_bank_helper::get_bank_name_string('systembank', 'question');
                    break;
                case CONTEXT_COURSECAT:
                    $coursecategory = core_course_category::get($oldcontext->instanceid);
                    $courseshortname = "{$coursecategory->name}-{$coursecategory->id}";
                    $course = $this->create_course($coursecategory, $courseshortname);
                    $bankname = question_bank_helper::get_bank_name_string('sharedbank', 'mod_qbank', $coursecategory->name);
                    break;
                case CONTEXT_COURSE:
                    $course = get_course($oldcontext->instanceid);
                    $bankname = question_bank_helper::get_bank_name_string('sharedbank', 'mod_qbank', $course->shortname);
                    break;
                default:
                    // This shouldn't be possible, so we can't really transfer it.
                    // We should commit any pre-transfer category cleanup though.
                    Debug::get_instance()->log('Warning, we are not able to handle context level {1}, Cleanup.', $oldcontext->contextlevel);
                    $trans->allow_commit();
                    continue 2;
            }

            if (!$newmod = question_bank_helper::get_default_open_instance_system_type($course)) {
                $newmod = question_bank_helper::create_default_open_instance($course, $bankname, question_bank_helper::TYPE_SYSTEM);
                Debug::get_instance()->log('Created qbank {1} in course {2}', $bankname, $course->id);
            }

            // We have our new mod instance, now move all the subcategories of the old 'top' category to this new context.
            Debug::get_instance()->log('Start moving all subcategories of {1} to context {2}.', $oldtopcategory->id, $newmod->context);
            $this->move_question_category($oldtopcategory, $newmod->context);
            Debug::get_instance()->log('Finished moving all subcategories of {1} to context {2}.', $oldtopcategory->id, $newmod->context);

            // Job done, lets delete the old 'top' category.
            Debug::get_instance()->log('Start deleting old top category {1}.', $oldtopcategory->id);
            $DB->delete_records('question_categories', ['id' => $oldtopcategory->id]);
            Debug::get_instance()->log('Finish deleting old top category {1}.', $oldtopcategory->id);
            Debug::get_instance()->log('Commit all db changes for old top category {1}.', $oldtopcategory->id);
            $trans->allow_commit();
            Debug::get_instance()->log('Done commit for changes on old top category {1}.', $oldtopcategory->id);
        }

        $recordset->close();
        Debug::get_instance()->log('Finish handling question categories.');
    }

    /**
     * Wrapper for \create_course.
     *
     * @param core_course_category $coursecategory
     * @param string $shortname
     * @return stdClass
     */
    protected function create_course(\core_course_category $coursecategory, string $shortname): stdClass {
        $data = (object) [
            'enablecompletion' => 0,
            'fullname' => get_string('coursecategory', 'mod_qbank', $coursecategory->name),
            'shortname' => $shortname,
            'category' => $coursecategory->id,
        ];
        return \create_course($data);
    }

    /**
     * Create a new 'Top' category in our new context and move the old categories descendents beneath it.
     *
     * @param stdClass $oldtopcategory The old 'Top' category that we are moving.
     * @param \context $newcontext The context we are moving our category to.
     * @return void
     */
    protected function move_question_category(stdClass $oldtopcategory, \context $newcontext): void {
        global $DB;

        $newtopcategory = question_get_top_category($newcontext->id, true);

        Debug::get_instance()->log('Start moving question category {1} from context {2} to context {3}', $oldtopcategory->id, $oldtopcategory->contextid, $newcontext->id);
        // This function moves subcategories, so we have to start at the top.
        question_move_category_to_context($oldtopcategory->id, $oldtopcategory->contextid, $newcontext->id);

        Debug::get_instance()->log('Finished moving question category {1} from context {2} to context {3}', $oldtopcategory->id, $oldtopcategory->contextid, $newcontext->id);
        // Move the parent from the old top category to the new one.
        $DB->set_field('question_categories', 'parent', $newtopcategory->id, ['parent' => $oldtopcategory->id]);
        Debug::get_instance()->log('Change old parent {2} to new parent {1}', $newtopcategory->id, $oldtopcategory->id);
    }

    /**
     * Fix wrong parents.
     *
     * In former course copies sometimes a question category got the parent from the
     * source course that is being copied. This function searches all category relations
     * where B is a child category of A and where the context id of B differs the
     * context id of A.
     * Those identified child question categories are traversed, the parent of this
     * particular chhild category is fetched and the context id is read. Then the child
     * category get the conext id of its parent assigned.
     *
     * @return void
     * @throws \dml_exception
     */
    protected function fix_wrong_parents(): void {
        global $DB;

        $res = $this->get_question_categories_diff_context();
        Debug::get_instance()->log('Found {1} category pairs with different context.', count($res));
        foreach ($res as $row) {
            $newparent = $DB->get_field(
                'question_categories',
                'id',
                ['contextid' => $row->childcontextid, 'parent' => 0]
            );
            if ((int)$newparent > 0) {
                Debug::get_instance()->log('Update category {1}, set new parent {2}.', $row->cid, $newparent);
                $DB->update_record('question_categories', ['id' => $row->cid, 'parent' => $newparent]);
                continue;
            }
            // We have no parent for the current context of the child category. Therefore, we have to
            // change the context id to the same as it's current parent has.
            $cat = $DB->get_record('question_categories', ['id' => $row->cid]);
            $newcontext = $DB->get_field('question_categories', 'contextid', ['id' => $cat->parent]);
            Debug::get_instance()->log('Update category {1}, set new context {2} from parent {3}.', $row->cid, $newcontext, $cat->parent);
            $DB->update_record('question_categories', ['id' => $row->cid, 'contextid' => $newcontext]);
        }
    }

    /**
     * Get all question categories where the context id of the parent differs from the context id of the child.
     * Returns an array with stdClasses with the properties cid and childcontextid.
     *
     * @return array
     * @throws \dml_exception
     */
    protected function get_question_categories_diff_context(): array {
        global $DB;

        $sql = '
                SELECT c.id AS cid, c.contextid AS childcontextid
                  FROM {question_categories} c
            INNER JOIN {question_categories} p ON p.id = c.parent
                 WHERE p.contextid <> c.contextid
        ';
        return $DB->get_records_sql($sql);
    }

    /**
     * Recursively check if a question category or its children contain any questions.
     *
     * @param int $categoryid The parent category to check from.
     * @return bool True if neither the category nor its children contain any questions.
     * @throws \dml_exception
     */
    protected function question_category_is_empty(int $categoryid): bool {
        global $DB;

        if ($DB->record_exists('question_bank_entries', ['questioncategoryid' => $categoryid])) {
            return false;
        }
        // If this category is empty, recursively check child categories.
        $childcategoryids = $DB->get_fieldset('question_categories', 'id', ['parent' => $categoryid]);
        foreach ($childcategoryids as $childcategoryid) {
            if (!$this->question_category_is_empty($childcategoryid)) {
                // If we found questions in a child, we don't want to check any other children.
                return false;
            }
        }
        return true;
    }
}
