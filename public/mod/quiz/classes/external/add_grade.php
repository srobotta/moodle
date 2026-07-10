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

namespace mod_quiz\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core\url as moodle_url;
use core\output\actions\popup_action;
use core\output\html_writer;
use mod_quiz\quiz_attempt;

require_once $CFG->dirroot . '/mod/quiz/locallib.php';

/**
 * Web service method to set a grade for a single question response of a student.
 * The user must have the 'mod/quiz:grade' capability for the quiz.
 *
 * @package   mod_quiz
 * @copyright 2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_grade extends external_api {

    /**
     * Declare the method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attempt' => new external_value(PARAM_INT, 'The attempt id.'),
            'slot' => new external_value(PARAM_INT, 'The slot id.'),
            'mark' => new external_value(PARAM_LOCALISEDFLOAT, 'The mark for this question.'),
        ]);
    }

    /**
     * Add new grade items to this quiz.
     *
     * New items are added in order, at the end of the sort order.
     *
     * @param int $attempt the id of the attempt of a quiz to add the grade.
     * @param int $slot the slot id of the quiz.
     * @param string $mark the grade that the student should receive.
     * @return array
     */
    public static function execute(int $attempt, int $slot, string $mark): array {
        global $DB;

        [
            'attempt' => $attempt,
            'slot' => $slot,
            'mark' => $mark,
        ] = self::validate_parameters(self::execute_parameters(), [
            'attempt' => $attempt,
            'slot' => $slot,
            'mark' => $mark,
        ]);

        $attemptobj = quiz_create_attempt_handling_errors($attempt);
        $attemptobj->preload_all_attempt_step_users();

        // Can only grade finished attempts.
        if (!$attemptobj->is_finished()) {
            throw new \moodle_exception('attemptclosed', 'quiz');
        }


        $qa = $attemptobj->get_question_attempt($slot);
        $maxmark = $qa->get_max_mark();

        // Validate that the mark is within the allowed range. The web service receives the mark directly as a
        // parameter, so we cannot rely on question_engine::is_manual_grade_in_range() which reads it from $_POST.
        if ($mark < $qa->get_min_fraction() * $maxmark || $mark > $qa->get_max_fraction() * $maxmark) {
            throw new \moodle_exception('savemanualgradingfailed', 'quiz');
        }

        // Build the submitted data in the same shape the manual grading form (comment.php) would post it, so the
        // question engine picks up the grade. The comment is intentionally left empty, but its draft file area
        // itemid must still be supplied: the manualgraded behaviour only treats this as a grading action (via
        // process_comment()) when the comment field is present. Without it the action is treated as a response
        // save, which is discarded because the attempt is already finished, and the mark is never stored.
        $prefix = $qa->get_field_prefix();
        $postdata = [
            'slots' => $slot,
            $prefix . ':sequencecheck' => $qa->get_sequence_check_count(),
            $prefix . '-comment' => '',
            $prefix . '-comment:itemid' => file_get_unused_draft_itemid(),
            $prefix . '-commentformat' => FORMAT_HTML,
            $prefix . '-mark' => $mark,
            $prefix . '-maxmark' => $maxmark,
        ];

        $attemptobj->process_submitted_actions(time(), false, $postdata);

        // Log this action.
        $params = [
            'objectid' => $attemptobj->get_question_attempt($slot)->get_question_id(),
            'courseid' => $attemptobj->get_courseid(),
            'context' => $attemptobj->get_quizobj()->get_context(),
            'other' => [
                'quizid' => $attemptobj->get_quizid(),
                'attemptid' => $attemptobj->get_attemptid(),
                'slot' => $slot
            ]
        ];
        $event = \mod_quiz\event\question_manually_graded::create($params);
        $event->trigger();
        return [
            'html' => self::render_result($mark, $slot, $attemptobj),
            'state' => (string)$attemptobj->get_question_state($slot),
        ];
    }

    /**
     * Render html for the table cell to replace the input field.
     * @param string $data
     * @param int $slot
     * @param quiz_attempt $attempt
     * @return string
     */
    protected static function render_result(string $data, int $slot, quiz_attempt $attempt) {
        global $CFG, $OUTPUT, $PAGE;

        $contextid = $attempt->get_quizobj()->get_context();
        $context = \context::instance_by_id($contextid);
        $PAGE->set_context($context);

        $baseurl = '/mod/quiz/reviewquestion.php';
        $reviewparams = ['attempt' => $attempt->get_attempt()->id, 'slot' => $slot];

        // Flag icon, only if the question is flagged in this attempt.
        $flag = '';
        if ($attempt->is_question_flagged($slot)) {
            $flag = $OUTPUT->pix_icon(
                'i/flagged',
                get_string('flagged', 'question'),
                'moodle',
                ['class' => 'questionflag']
            );
        }

        // The grade has just been processed on the live attempt, so we can read the state and fraction from it
        // directly instead of reloading the step data as the report does.
        $state = $attempt->get_question_state($slot);

        // Feedback icon (green tick, red cross, etc.) for the graded state.
        $feedbackimg = '';
        if ($state->is_finished() && $state != \question_state::$needsgrading) {
            $feedbackimg = self::icon_for_fraction($attempt->get_question_mark($slot));
        }

        // Wrap the mark in a span carrying the state class so it is styled correctly.
        $link = \html_writer::tag('span', $feedbackimg . \html_writer::tag('span',
                $data, ['class' => $state->get_state_class(true)]) . $flag, ['class' => 'que']);

        $url = new moodle_url($baseurl, $reviewparams);
        $output = $OUTPUT->action_link(
            $url,
            $link,
            new popup_action(
                'click',
                $url,
                'reviewquestion',
                ['height' => 450, 'width' => 650]
            ),
            ['title' => get_string('reviewresponse', 'quiz')]
        );

        if (!empty($CFG->enableplagiarism)) {
            require_once($CFG->libdir . '/plagiarismlib.php');
            $contextid = $attempt->get_quizobj()->get_context();
            $output .= plagiarism_get_links([
                'context' => $contextid,
                'component' => $attempt->get_question_attempt($slot)->get_question()->qtype,
                'cmid' => $context->instanceid,
                'area' => $attempt->get_attempt()->usageid,
                'itemid' => $slot,
                'userid' => $attempt->get_attempt()->userid,
            ]);
        }
        return $output;
    }

    /**
     * Return an appropriate icon (green tick, red cross, etc.) for a grade.
     *
     * @param float $fraction grade on a scale 0..1.
     * @return string html fragment.
     */
    protected static function icon_for_fraction($fraction) {
        global $OUTPUT;

        $feedbackclass = \question_state::manually_graded_state_for_fraction($fraction)->get_feedback_class();
        return $OUTPUT->pix_icon(
            'i/grade_' . $feedbackclass,
            get_string($feedbackclass, 'question'),
            'moodle',
            ['class' => 'icon']
        );
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered mark', VALUE_REQUIRED),
            'state' => new external_value(PARAM_ALPHAEXT, 'Question state', VALUE_REQUIRED),
        ]);
    }
}
