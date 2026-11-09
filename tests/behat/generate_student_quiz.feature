@mod @mod_concordance @javascript
Feature: Student quiz generation from a quiz with several sections
  As a teacher
  In order to give the panelists' quiz structure to my students
  I need the sections of the panelists' quiz to be restored in the student quiz

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com  |
    And the following "courses" exist:
      | fullname  | shortname |
      | Course1   | c1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | c1     | editingteacher |
    And the following "activities" exist:
      | activity    | course | idnumber     | name            | descriptionpanelist   | descriptionstudent   |
      | concordance | c1     | concordance1 | TestConcordance | Description panelists | Description students |
    And the following "activities" exist:
      | activity | course | idnumber | name        | visible |
      | quiz     | c1     | quiz1    | Origin quiz | 0       |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | c1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype | name  | template  |
      | Test questions   | tcs   | TCS-A | reasoning |
      | Test questions   | tcs   | TCS-B | reasoning |
      | Test questions   | tcs   | TCS-C | reasoning |
    # Three questions, one per page, split in two sections: A (slot 1) and B (slots 2 and 3).
    And concordance quiz "Origin quiz" contains the following questions:
      | question | page |
      | TCS-A    | 1    |
      | TCS-B    | 2    |
      | TCS-C    | 3    |
    And quiz "Origin quiz" contains the following sections:
      | heading   | firstslot | shuffle |
      | Section A | 1         | 0       |
      | Section B | 2         | 0       |
    And I log in as "teacher1"
    And I am on the "TestConcordance" "concordance activity" page
    And I follow "Select the quiz for panelists"
    And I set the field "Quiz" to "Origin quiz (hidden)"
    And I click on "Save changes" "button"
    And I am on the "TestConcordance" "concordance activity" page
    And I follow "Manage panelists"
    And I click on "Add new panelist" "button"
    And I set the following fields to these values:
      | First name    | Rebecca              |
      | Last name     | Armenta              |
      | Email address | rebeccaa@example.com |
    And I click on "Save changes" "button"
    And I should see "Panelist created"
    And panelist "rebeccaa@example.com" has completed the panelists quiz of concordance "TestConcordance"

  Scenario: Generate a student quiz from a quiz with two sections
    Given I am on the "TestConcordance" "concordance activity" page
    And I follow "Generate the quiz for students"
    And I click on "Rebecca Armenta" "checkbox"
    And I set the field "name" to "Student quiz"
    When I click on "Generate" "button"
    Then I should see "A new quiz has been generated"
    And I should not see "You cannot remove the last slot in a section"
    And I am on the "Student quiz" "mod_quiz > Edit" page
    And I should see "Section A"
    And I should see "Section B"
    And I should see "TCS-A" on quiz page "1"
    And I should see "TCS-B" on quiz page "2"
    And I should see "TCS-C" on quiz page "3"

  Scenario: Generate a student quiz when only the first question of the second section is excluded
    Given I am on the "TestConcordance" "concordance activity" page
    And I follow "Generate the quiz for students"
    And I click on "Rebecca Armenta" "checkbox"
    And I set the field "name" to "Student quiz"
    And I click on "TCS-B" "checkbox"
    When I click on "Generate" "button"
    Then I should see "A new quiz has been generated"
    And I should not see "You cannot remove the last slot in a section"
    And I am on the "Student quiz" "mod_quiz > Edit" page
    And I should see "Section A"
    And I should see "Section B"
    And I should see "TCS-A" on quiz page "1"
    And I should not see "TCS-B"
    And I should see "TCS-C"

  Scenario: The form refuses to exclude every question of a section
    Given I am on the "TestConcordance" "concordance activity" page
    And I follow "Generate the quiz for students"
    And I click on "Rebecca Armenta" "checkbox"
    And I click on "TCS-B" "checkbox"
    And I click on "TCS-C" "checkbox"
    When I click on "Generate" "button"
    Then I should see "It is not allowed to remove all questions under a section heading." in the "Cannot remove questions" "dialogue"
    And I should not see "A new quiz has been generated"
