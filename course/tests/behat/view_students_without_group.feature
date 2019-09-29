@core @core_course @javascript
Feature: Viewing course participants, who are not members of any group
  As a teacher
  I should be able to see course participants, who are not members of any group, their grades reports and course completion information

  Background:
    Given the following "courses" exist:
      | fullname | shortname | groupmode |
      | Course 1 | C1        | 2         |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher  | Test      | Teacher  | teacher@example.com |
      | user1    | Test      | User1    | user1@example.com |
      | user2    | Test      | User2    | user2@example.com |
      | user3    | Test      | User3    | user3@example.com |
      | user4    | Test      | User4    | user4@example.com |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
      | user1   | C1     | student        |
      | user2   | C1     | student        |
      | user3   | C1     | student        |
      | user4   | C1     | student        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group 1 | C1     | G1       |
      | Group 2 | C1     | G2       |
    And the following "group members" exist:
      | user        | group |
      | user1       | G1    |
      | user2       | G1    |
      | user3       | G2    |
    When I log in as "teacher"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add a "Survey" to section "0" and I fill the form with:
      | Name        | survey1                 |
      | Survey type | Critical incidents      |
      | Description | Test survey description |
      | groupmode   | 2                       |
    And I log out
    When I log in as "user1"
    And I am on "Course 1" course homepage
    And I follow "survey1"
    And I set the field "At what moment in class were you most engaged as a learner?" to "Text1"
    And I set the field "At what moment in class were you most distanced as a learner?" to "Text1"
    And I set the field "What action from anyone in the forums did you find most affirming or helpful?" to "Text1"
    And I set the field "What action from anyone in the forums did you find most puzzling or confusing?" to "Text1"
    And I click on "Submit" "button"
    And I log out
    When I log in as "user4"
    And I am on "Course 1" course homepage
    And I follow "survey1"
    And I set the field "At what moment in class were you most engaged as a learner?" to "Text4"
    And I set the field "At what moment in class were you most distanced as a learner?" to "Text4"
    And I set the field "What action from anyone in the forums did you find most affirming or helpful?" to "Text4"
    And I set the field "What action from anyone in the forums did you find most puzzling or confusing?" to "Text4"
    And I click on "Submit" "button"
    And I log out

  Scenario: As a teacher in the survey I can see all group members and also students not in any group.
    When I log in as "teacher"
    And I am on "Course 1" course homepage
    And I click on "//span[contains(text(), 'survey1')]/parent::a" "xpath_element"
    # Click the "Response reports" tab in the Survey
    And I click on "//a[contains(text(), 'Response reports')]" "xpath_element"
    And I set the field "Visible groups" to "Participants not in a group"
    And I should not see "Test User1"
    And I should not see "Test User2"
    And I should not see "Test User3"
    And I should see "Test User4"
    And I set the field "" to "Participants"
    And I should not see "Test User1"
    And I should not see "Test User2"
    And I should not see "Test User3"
    And I should see "Test User4"
    And I set the field "Visible groups" to "Group 1"
    And I should see "Test User1"
    And I should not see "Test User2"
    And I should not see "Test User3"
    And I should not see "Test User4"
    And I log out

  Scenario: As a teacher in the gradebook I can see all group members and students not in any group.
    When I log in as "teacher"
    And I am on "Course 1" course homepage
    And I navigate to "View > Single view" in the course gradebook
    And I click on "Users" "link"
    And I click on "Group 1" in the "group" search widget
    And I click on "//div[contains(text(), 'Select a user')]" "xpath_element"
    And I should see "Test User1"
    And I should see "Test User2"
    And I should not see "Test User3"
    And I should not see "Test User4"
    And I click on "Grade items" "link"
    And I click on "Course total" in the "grade" search widget
    And I should see "Test User1"
    And I should see "Test User2"
    And I should not see "Test User3"
    And I should not see "Test User4"
    And I click on "Group 2" in the "group" search widget
    And I should not see "Test User1"
    And I should not see "Test User2"
    And I should see "Test User3"
    And I should not see "Test User4"
    And I click on "Participants not in a group" in the "group" search widget
    And I should not see "Test User1"
    And I should not see "Test User2"
    And I should not see "Test User3"
    And I should see "Test User4"
    And I log out
