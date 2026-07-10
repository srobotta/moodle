@mod @mod_quiz
Feature: Teachers can quickly add a grade for a question directly in the Grades report
  As a teacher
  In order to grade responses that need manual grading with less effort
  I must be able to enter a mark inline in the report, without opening the commenting dialogue.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity   | name    | intro              | course | idnumber | grade |
      | quiz       | Quiz 1  | Quiz 1 description | C1     | quiz1    | 20    |
    And the following "question categories" exist:
      | contextlevel    | reference | name           |
      | Activity module | quiz1     | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype | name | questiontext    | defaultmark |
      | Test questions   | essay | TF1  | First question  | 12          |
      | Test questions   | essay | TF2  | Second question | 8           |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
      | TF2      | 2    |
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response                        |
      | 1    | This is student 1's 1st answer. |
      | 2    | This is student 1's 2nd answer. |
    And user "student2" has attempted "Quiz 1" with responses:
      | slot | response                        |
      | 1    | This is student 2's 1st answer. |
      | 2    | This is student 2's 2nd answer. |

  @javascript
  Scenario: Grade a question inline and see the cell and the average summary update
    When I am on the "Quiz 1" "mod_quiz > Grades report" page logged in as "teacher1"
    # Both attempts of the essay question need manual grading and offer the inline grading link.
    Then I should see "Requires grading" in the "Student 1" "table_row"
    And I should see "Requires grading" in the "Student 2" "table_row"
    # Every ungraded question in each student's row offers an "Add grade" link.
    And "Student 1" row "Q. 1/12.00" column of "attempts" table should contain "Add grade"
    And "Student 1" row "Q. 2/8.00" column of "attempts" table should contain "Add grade"
    And "Student 2" row "Q. 1/12.00" column of "attempts" table should contain "Add grade"
    And "Student 2" row "Q. 2/8.00" column of "attempts" table should contain "Add grade"

    # Grade the first student's first question inline: click its link, type the mark and confirm with Enter.
    And I click on ".grade-now[data-slot='1']" "css_element" in the "Student 1" "table_row"
    And I type "6"
    And I press the enter key
    # The cell is updated with the new mark and the grading link is gone.
    And "Student 1" row "Q. 1/12.00" column of "attempts" table should contain "6.00"
    And "Student 1" row "Q. 1/12.00" column of "attempts" table should not contain "Requires grading"
    # The graded question no longer offers the grading link, but the second question still does.
    And "Student 1" row "Q. 1/12.00" column of "attempts" table should not contain "Add grade"
    And "Student 1" row "Q. 2/8.00" column of "attempts" table should contain "Requires grading"
    And "Student 1" row "Q. 2/8.00" column of "attempts" table should contain "Add grade"
    # The average summary cell for the first question includes the one graded response.
    And "Overall average" row "Q. 1/12.00" column of "attempts" table should contain "6.00"
    And "Overall average" row "Q. 1/12.00" column of "attempts" table should contain "(1)"
    # Grade the second student's first question inline as well.
    And I click on ".grade-now[data-slot='1']" "css_element" in the "Student 2" "table_row"
    And I type "12"
    And I press the enter key
    And "Student 2" row "Q. 1/12.00" column of "attempts" table should contain "12.00"
    And "Student 2" row "Q. 1/12.00" column of "attempts" table should not contain "Requires grading"
    And "Student 2" row "Q. 1/12.00" column of "attempts" table should not contain "Add grade"
    And "Student 2" row "Q. 2/8.00" column of "attempts" table should contain "Requires grading"
    And "Student 2" row "Q. 2/8.00" column of "attempts" table should contain "Add grade"
    # The first question's average cell now reflects both graded responses: (6.00 + 12.00) / 2 = 9.00.
    And "Overall average" row "Q. 1/12.00" column of "attempts" table should contain "9.00"
    And "Overall average" row "Q. 1/12.00" column of "attempts" table should contain "(2)"
    # Grade the second student's second question inline as well.
    And I click on ".grade-now[data-slot='2']" "css_element" in the "Student 2" "table_row"
    And I type "4"
    And I press the enter key
    # Reload the page so that a chart appears
    And I reload the page
    # Grade the first student's second question inline as well.
    And I click on ".grade-now[data-slot='2']" "css_element" in the "Student 1" "table_row"
    And I type "8"
    And I press the enter key
    # Because of the reload there is a chart, that gets a notice when a grade is added directly
    And I should see "Grades have been changed, please reload the page to get an up-to-date chart."

  @javascript
  Scenario: Cancelling the inline grading with Escape keeps the response ungraded
    When I am on the "Quiz 1" "mod_quiz > Grades report" page logged in as "teacher1"
    And I click on ".grade-now[data-slot='1']" "css_element" in the "Student 1" "table_row"
    And I type "10"
    # Pressing Escape restores the "Add grade" link without saving anything.
    And I press the escape key
    Then "Student 1" row "Q. 1/12.00" column of "attempts" table should contain "Add grade"
    And "Student 1" row "Q. 1/12.00" column of "attempts" table should contain "Requires grading"
    And "Student 1" row "Q. 1/12.00" column of "attempts" table should not contain "10.00"

  @javascript
  Scenario: Grading with a mark outside the valid range shows an error
    When I am on the "Quiz 1" "mod_quiz > Grades report" page logged in as "teacher1"
    And I click on ".grade-now[data-slot='1']" "css_element" in the "Student 1" "table_row"
    # The maximum mark for this question is 12, so 25 is out of range.
    And I type "25"
    And I press the enter key
    # The grade is rejected and the error is shown in a modal dialogue.
    Then I should see "Modification not saved. Please check the message below and try again."
