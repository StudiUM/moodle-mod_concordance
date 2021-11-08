@mod @mod_concordance @javascript
Feature: Wizard
  As a teacher
  In order to do a learning by concordance activity in my course
  I need to use the wizard to manage my activity

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
    And I log in as "teacher1"
    And I am on "Course1" course homepage with editing mode on
    And I add a "Quiz" to section "1" and I fill the form with:
      | Name          | Test quiz             |
      | Description   | Test quiz description |
      | Availability  | Hide from students    |
    And I add a "Concordance of reasoning" question to the "Test quiz" quiz with:
      | Question name     | Q1                          |
      | Question text     | First question              |
      | Option            | The option is ABC           |

  Scenario: Test the wizard phases and tasks status.
    # Create a basic concordance activity.
    Given I am on "Course1" course homepage
    When I add a "Learning by concordance management" to section "1"
    Then "//div[contains(text(), 'Hide from students')]/preceding-sibling::div[1][contains(.,'Availability')]" "xpath_element" should be visible
    And I set the field "Name" to "TestConcordance"
    And I press "Save and display"
    And the status of concordance task "Edit settings" should be "todo"
    And the status of concordance task "Select the quiz for panelists" should be "todo"
    And the status of concordance task "Manage panelists" should be "todo"
    And the status of concordance task "Contact panelists" should be "todo"
    And the status of concordance task "Generate the quiz for students" should be "todo"
    And I click on "Switch to the next phase" "link"
    And the status of concordance task "Edit settings" should be "fail"
    And I should see "The field 'Description for the panelists' is not filled"
    And I should see "The field 'Description for the students' is not filled"
    And the status of concordance task "Select the quiz for panelists" should be "fail"
    And the status of concordance task "Manage panelists" should be "fail"
    And the status of concordance task "Contact panelists" should be "fail"
    And the status of concordance task "Generate the quiz for students" should be "todo"
    And I click on "Switch to the next phase" "link"
    And the status of concordance task "Edit settings" should be "fail"
    And I should see "The field 'Description for the panelists' is not filled"
    And I should see "The field 'Description for the students' is not filled"
    And the status of concordance task "Select the quiz for panelists" should be "fail"
    And the status of concordance task "Manage panelists" should be "fail"
    And the status of concordance task "Contact panelists" should be "fail"
    And the status of concordance task "Generate the quiz for students" should be "fail"
    And there should be an info after concordance task "Generate the quiz for students" saying "No panelist have been created"
    And there should be an info after concordance task "Generate the quiz for students" saying "No quiz found"
    # Edit settings
    And I click on "Edit settings" "link" in the ".concordance-wizard" "css_element"
    And I set the following fields to these values:
      | Description for the panelists | Test desc1 |
      | Description for the students  | Test desc2 |
    And I press "Save and display"
    And the status of concordance task "Edit settings" should be "done"
    # Select the quiz.
    And I am on the "TestConcordance" "concordance activity" page
    And I follow "Select the quiz for panelists"
    And I should not see "You should not change the content of the currently selected quiz since these changes will not be reflected in the quiz for panelists."
    And I set the field "cmorigin" to "Test quiz (hidden)"
    And I should see "You should not change the content of the currently selected quiz since these changes will not be reflected in the quiz for panelists."
    And I press "Save changes"
    And I should see "You should not change the content of the currently selected quiz since these changes will not be reflected in the quiz for panelists."
    And I am on the "TestConcordance" "concordance activity" page
    And the status of concordance task "Select the quiz for panelists" should be "done"
    And I should not see "No test found"
    # More advanced tests for status and info are made in selectquiz.feature.
    # Manage panelists.
    And I follow "Manage panelists"
    And I click on "Add new panelist" "button"
    And I set the following fields to these values:
      | First name     | Rebecca              |
      | Surname        | Armenta              |
      | Email address  | rebeccaa@example.com |
    And I click on "Save changes" "button"
    And I should see "Panelist created"
    And I am on the "TestConcordance" "concordance activity" page
    And the status of concordance task "Manage panelists" should be "done"
    And I should not see "No panelist have been created"
    And the status of concordance task "Generate the quiz for students" should be "info"
    # Contact panelists.
    And I follow "Contact panelists"
    And I click on "panelists" "checkbox" in the "Rebecca" "table_row"
    And I click on "Send a message" "button"
    And I click on "Send message to 1 person" "button"
    And I wait until the page is ready
    And I should see "Message sent to 1 person"
    And I am on the "TestConcordance" "concordance activity" page
    And the status of concordance task "Contact panelists" should be "done"

  Scenario: Test the wizard navigation.
    # Create a basic concordance activity.
    Given I am on "Course1" course homepage
    When I add a "Learning by concordance management" to section "1" and I fill the form with:
      | Name                          | TestConcordance                   |
    And I am on the "TestConcordance" "concordance activity" page
    Then the concordance wizard active phase should be "Setup"
    And I click on "Switch to the next phase" "link"
    And the concordance wizard active phase should be "Answers by panelists"
    And I click on "Switch to the next phase" "link"
    And the concordance wizard active phase should be "Generation for students"
    And I switch the concordance phase to "Setup"
    And the concordance wizard active phase should be "Setup"
    And I switch the concordance phase to "Generation for students"
    And the concordance wizard active phase should be "Generation for students"
    And I switch the concordance phase to "Answers by panelists"
    And the concordance wizard active phase should be "Answers by panelists"
