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

namespace core_contentbank\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use tool_brickfield\local\areas\mod_choice\option;

/**
 * This is the external method for searching contexts in categories and courses where the user has access to.
 *
 * @package    core_contentbank
 * @copyright  2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_contexts extends external_api {
    /**
     * search_contexts parameters.
     *
     * @since  Moodle 5.3
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'term' => new external_value(PARAM_RAW, 'The search term', VALUE_REQUIRED),
                'contextid' => new external_value(PARAM_INT, 'The context id currently in use', VALUE_OPTIONAL),
            ]
        );
    }

    /**
     * Search for contexts based on a search term and that are accessible by the current user.
     *
     * @since  Moodle 5.3
     * @param  string $term The search term.
     * @param  int $contextid The context id currently in use.
     * @return array Id of the new content; false and the warning, otherwise.
     */
    public static function execute(string $term, int $contextid = 0): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'term' => $term,
            'contextid' => $contextid,
        ]);
        $params['term'] = clean_param($params['term'], PARAM_TEXT);
        // Context must be set because of the format string function to display
        // properly the course/category names in the search results.
        $params['contextid'] = clean_param($params['contextid'], PARAM_INT) ?? 0;
        if ($params['contextid']) {
            $PAGE->set_context(\context::instance_by_id($params['contextid']));
        } else {
            $PAGE->set_context(\context_system::instance());
        }

        $response = [
            'category' => [
                'contextid' => [],
                'name' => [],
            ],
            'course' => [
                'contextid' => [],
                'name' => [],
            ],
        ];

        if ($params['term'] !== '') {
            $contextfields = 'ctxid, ctxpath, ctxdepth, ctxlevel, ctxinstance, ctxlocked';
            [$categories, $courses] = get_user_capability_contexts(
                'moodle/contentbank:access',
                true,
                null,
                true,
                "fullname, {$contextfields}",
                "name, {$contextfields}",
                'fullname',
                'name',
                30,
                $params['term']
            );

            if ($categories) {
                foreach ($categories as $category) {
                    $response['category']['contextid'][] = $category->ctxid;
                    $response['category']['name'][] = format_string($category->name);
                }
            }

            if ($courses) {
                global $SITE;
                foreach ($courses as $course) {
                    if ($course->id == $SITE->id) {
                        continue;
                    }
                    $response['course']['contextid'][] = $course->ctxid;
                    $response['course']['name'][] = format_string($course->fullname);
                }
            }
        }

        return $response;
    }

    /**
     * contexts_search return.
     *
     * @since  Moodle 5.3
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'category' => new external_single_structure([
                'contextid' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'The context ID of the categories'),
                ),
                'name' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'The name of the categories'),
                ),
            ], 'The list of matching categories where the user has access to.'),
            'course' => new external_single_structure([
                'contextid' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'The context ID of the courses'),
                ),
                'name' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'The name of the courses'),
                ),
            ], 'The list of matching courses where the user has access to.'),
        ]);
    }
}
