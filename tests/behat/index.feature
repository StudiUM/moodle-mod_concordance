@mod @mod_concordance @javascript
Feature: Index page
  As a teacher or as a student
  I need to see a list of concordance activities in my course

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com  |
      | student1 | Student1  | Student1 | student1@example.com  |
    And the following "courses" exist:
      | fullname  | shortname |
      | Course1   | c1        |
      | Course2   | c2        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | c1     | editingteacher |
      | student1 | c1     | student        |
      | teacher1 | c2     | editingteacher |
      | student1 | c2     | student        |
    And the following "activities" exist:
      | activity    | course | section   | idnumber     | name                             | visible | descriptionpanelist      | descriptionstudent     |
      | concordance | c1     | Section A | concordance1 | First concordance in section A   | 1       | Description panelists 1  | Description students 1 |
      | concordance | c1     | Section A | concordance2 | Second concordance in section A  | 0       | Description panelists 2  | Description students 2 |
      | concordance | c1     | Section B | concordance3 | Concordance in section B         | 1       | Description panelists 3  | Description students 3 |
      | concordance | c2     | Section A | concordance4 | Concordance in course 2          | 1       | Description panelists 4  | Description students 4 |
    And the following "activities" exist:
      | activity    | course | section   | idnumber     | name                             | visible |
      | quiz        | c1     | Section A | quiz1        | Quiz                             | 1       |

  Scenario: Check the list of concordance instances
    Given I log in as "teacher1"
    When I am on "Course1" course homepage with editing mode on
    And I add the "Activities" block
    And I click on "Learning by concordance management" "link" in the "Activities" "block"
    Then I should see "First concordance in section A"
    And I should see "Second concordance in section A"
    And I should see "Concordance in section B"
    And I should not see "Concordance in course 2"
    And I should not see "Quiz"
    And I follow "First concordance in section A"
    And I should see "First concordance in section A" in the "//h2" "xpath_element"
    And I log out
    And I log in as "student1"
    And I am on "Course1" course homepage
    And I click on "Learning by concordance management" "link" in the "Activities" "block"
    And I should see "First concordance in section A"
    And I should not see "Second concordance in section A"
    And I should see "Concordance in section B"
    And I should not see "Concordance in course 2"
    And I should not see "Quiz"
