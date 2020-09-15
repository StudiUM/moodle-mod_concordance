@mod @mod_concordance @javascript
Feature: Contact panelists page
  As a teacher
  In order to do a learning by concordance activity in my course
  I need to be able to contact the panelists

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
    # Create a basic concordance activity.
    And I add a "Learning by concordance management" to section "1" and I fill the form with:
      | Name                          | TestConcordance                   |
      | Description for the panelists | The description for the panelists |
      | Description for the students  | The description for the students  |
    And I add a "Quiz" to section "1" and I fill the form with:
      | Name          | Test quiz name        |
      | Description   | Test quiz description |
      | Availability  | Hide from students    |

  Scenario: There is no panelist and no quiz selected yet
    Given I follow "TestConcordance"
    When I follow "Contact panelists"
    Then I should see "It is not possible to contact the panelists because the quiz for panelists is not selected."
    And I should see "No panelist have been created yet."
    And "Send a message" "button" should not be visible

  Scenario: There is no panelist yet, but a quiz is selected
    Given I follow "TestConcordance"
    And I follow "Select the quiz for panelists"
    And I set the field "Quiz" to "Test quiz name"
    And I click on "Save changes" "button"
    And I follow "TestConcordance"
    When I follow "Contact panelists"
    Then I should see "No panelist have been created yet."
    And I should not see "It is not possible to contact the panelists because the quiz for panelists is not selected."
    And "Send a message" "button" should not be visible

  Scenario: There is no quiz selected yet, but at least a panelist is created
    Given I follow "TestConcordance"
    And I follow "Manage panelists"
    And I click on "Add new panelist" "button"
    And I set the following fields to these values:
      | First name     | Rebecca              |
      | Surname        | Armenta              |
      | Email address  | rebeccaa@example.com |
    And I click on "Save changes" "button"
    And I follow "TestConcordance"
    When I follow "Contact panelists"
    Then I should see "It is not possible to contact the panelists because the quiz for panelists is not selected."
    And I should not see "No panelist have been created yet."
    And "Send a message" "button" should not be visible
    And I should see "Armenta" in the "Rebecca" "table_row"

  Scenario: Contact the panelists
    Given I follow "TestConcordance"
    And I follow "Select the quiz for panelists"
    And I set the field "Quiz" to "Test quiz name"
    And I click on "Save changes" "button"
    And I follow "TestConcordance"
    And I follow "Manage panelists"
    And I click on "Add new panelist" "button"
    And I set the following fields to these values:
      | First name     | Rebecca              |
      | Surname        | Armenta              |
      | Email address  | rebeccaa@example.com |
    And I click on "Save changes" "button"
    And I click on "Add new panelist" "button"
    And I set the following fields to these values:
      | First name     | Donald              |
      | Surname        | Fletcher            |
      | Email address  | donaldf@example.com |
    And I click on "Save changes" "button"
    And I follow "TestConcordance"
    When I follow "Contact panelists"
    Then I should not see "It is not possible to contact the panelists because the quiz for panelists is not selected."
    And I should not see "No panelist have been created yet."
    And "Send a message" "button" should be visible
    And the "Send a message" "button" should be disabled
    # Send a message to one panelist.
    And I click on "panelists" "checkbox" in the "Rebecca" "table_row"
    And the "Send a message" "button" should be enabled
    And I click on "Send a message" "button"
    And I wait until the page is ready
    And "Send message to 1 person" "dialogue" should be visible
    And I click on "Send message to 1 person" "button"
    And I wait until the page is ready
    And I should see "Message sent to 1 person"
    # Send a message to all panelists.
    And I click on "All" "link"
    And I click on "Send a message" "button"
    And I wait until the page is ready
    And "Send message to 2 people" "dialogue" should be visible
    And I click on "Send message to 2 people" "button"
    And I wait until the page is ready
    And I should see "Message sent to 2 people"
    And "Rebecca" row "Nb. emails sent" column of "generaltable" table should contain "2"
    And "Donald" row "Nb. emails sent" column of "generaltable" table should contain "1"
