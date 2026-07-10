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

use core_question_generator;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use required_capability_exception;

/**
 * Test for the add_grade external service.
 *
 * @package   mod_quiz
 * @category  external
 * @copyright 2026 Stephan Robotta <stephan.robotta@bfh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\external\add_grade
 */
final class add_grade_test extends \core_external\tests\externallib_testcase {
    /**
     * Quiz settings.
     * @var quiz_settings
     */
    protected quiz_settings $quizobj;

    /**
     * Attempt of student1.
     * @var \stdClass
     */
    protected \stdClass $attempt1;

    /**
     * Attempt of student 2
     * @var \stdClass.
     */
    protected \stdClass $attempt2;

    /**
     * Student 1.
     * @var \stdClass
     */
    protected \stdClass $student1;

    /**
     * Student 2.
     * @var \stdClass
     */
    protected \stdClass $student2;

    /**
     * Teacher of the two students.
     * @var \stdClass
     */
    protected \stdClass $teacher;

    /**
     * Test permissions for webservice.
     */
    public function test_add_grade_service_checks_permissions(): void {
        $this->create_quiz_with_two_attempts();

        $unprivilegeduser = $this->getDataGenerator()->create_user();
        $this->setUser($unprivilegeduser);

        $this->expectException(required_capability_exception::class);
        add_grade::execute($this->attempt1->id, 1, '2.5');
    }

    /**
     * Test error when attempt is not yet closed.
     */
    public function test_add_grade_service_attemptopen(): void {
        $this->create_quiz_with_two_attempts(true);
        $this->setUser($this->teacher);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('attemptclosed', 'mod_quiz'));
        add_grade::execute($this->attempt1->id, 1, '2.5');
    }

    /**
     * Test error when an ivalid mark has been submitted.
     */
    public function test_add_grade_service_mark_invalid(): void {
        $this->create_quiz_with_two_attempts();
        $this->setUser($this->teacher);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('savemanualgradingfailed', 'mod_quiz'));
        add_grade::execute($this->attempt1->id, 1, '22.5');
    }

    /**
     * Test a working example.
     */
    public function test_add_grade_service_works(): void {
        $this->create_quiz_with_two_attempts();

        $this->setUser($this->teacher);

        add_grade::execute($this->attempt1->id, 1, '2.5');
        add_grade::execute($this->attempt1->id, 2, '6');
        add_grade::execute($this->attempt2->id, 1, '8');
        add_grade::execute($this->attempt2->id, 2, '8');

        // Read the recomputed overall quiz grade for each student.
        $quiz = $this->quizobj->get_quiz();
        $grade1 = quiz_get_best_grade($quiz, $this->student1->id);
        $grade2 = quiz_get_best_grade($quiz, $this->student2->id);
        $this->assertEquals('4.25', $grade1);
        $this->assertEquals('8.00', $grade2);
    }

    /**
     * Create a quiz of two essay questions, add two attempts from two students.
     * @param bool $leaveattemptopen
     */
    protected function create_quiz_with_two_attempts(bool $leaveattemptopen = false): void {
        global $SITE;
        $this->resetAfterTest();

        // Generate a course.
        $course = $this->getDataGenerator()->create_course();
        // Generate 3 users.
        $this->student1 = $this->getDataGenerator()->create_user();
        $this->student2 = $this->getDataGenerator()->create_user();
        $this->teacher = $this->getDataGenerator()->create_user();
        // Enrol users on the course with the appropriate roles.
        $this->getDataGenerator()->enrol_user($this->student1->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($this->student2->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $course->id, 'editingteacher');

        // Make a quiz.
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'grade' => 10,
        ]);

        // Create two question.
        /** @var core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $essay1 = $questiongenerator->create_question('essay', null, ['category' => $cat->id, 'defaultmark' => 12]);
        $essay2 = $questiongenerator->create_question('essay', null, ['category' => $cat->id, 'defaultmark' => 8]);

        // Add the two essay questions to the quiz.
        quiz_add_quiz_question($essay1->id, $quiz, 0);
        quiz_add_quiz_question($essay2->id, $quiz, 0);

        // Add two attempts, one for each student.
        $this->quizobj = quiz_settings::create($quiz->id);
        // Recompute the quiz sumgrades from the actual question maxmarks (12 + 8 = 20),
        // so the final grades are scaled correctly against the quiz maximum grade of 10.
        $this->quizobj->get_grade_calculator()->recompute_quiz_sumgrades();
        $starttime = time();
        foreach ([$this->student1, $this->student2] as $i => $student) {
            // Each attempt needs its own question usage, otherwise the slot numbers get confused.
            $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $this->quizobj->get_context());
            $quba->set_preferred_behaviour($quiz->preferredbehaviour);
            // Start the attempt.
            $attempt = quiz_create_attempt($this->quizobj, 1, false, $starttime, false, $student->id);
            quiz_start_new_attempt($this->quizobj, $quba, $attempt, 1, $starttime);
            quiz_attempt_save_started($this->quizobj, $quba, $attempt, $starttime);
            $this->{'attempt' . ($i + 1)} = $attempt;
            if ($leaveattemptopen) {
                return;
            }
            // Finish the attempt.
            $attemptobj = quiz_attempt::create($attempt->id);
            $attemptobj->process_submit($starttime, false);
            $attemptobj->process_grade_submission($starttime);
        }
    }
}
