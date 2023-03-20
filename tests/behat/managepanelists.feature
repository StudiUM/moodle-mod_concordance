@mod @mod_concordance @javascript
Feature: Manage panelists page
  As a teacher
  In order to do a learning by concordance activity in my course
  I need to be able to manage the panelists

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
      | Name                          | Test Concordance for Manage Panelists   |
      | Description for the panelists | The description for the panelists       |
      | Description for the students  | The description for the students        |

  Scenario: Manage the panelists
    Given I am on the "Test Concordance for Manage Panelists" "concordance activity" page logged in as teacher1
    When I follow "Manage panelists"
    Then I should see "No panelist have been created yet."
    # Add panelists.
    And I click on "Add new panelist" "button"
    And I set the following fields to these values:
      | First name     | Rebecca              |
      | Last name      | Armenta              |
      | Email address  | rebeccaa@example.com |
    And I click on "Save changes" "button"
    And I should see "Panelist created"
    And I click on "Add new panelist" "button"
    And I set the following fields to these values:
      | First name     | Donald              |
      | Last name      | Fletcher            |
      | Email address  | donaldf@example.com |
    And I click on "Save changes" "button"
    And I should see "Panelist created"
    # Edit a panelist (Donald).
    And I click on "//table/tbody/tr[contains(.,'Donald')]//button[.='Edit']" "xpath_element"
    And the field "First name" matches value "Donald"
    And the field "Last name" matches value "Fletcher"
    And the field "Email address" matches value "donaldf@example.com"
    And I set the following fields to these values:
      | First name     | Pablo              |
      | Last name      | Menendez           |
      | Email address  | pablom@example.com |
    And I click on "Save changes" "button"
    And I should see "Panelist updated"

    # Edit a panelist (Rebecca) - make an error in the form, then cancel.
    And I click on "//table/tbody/tr[contains(.,'Rebecca')]//button[.='Edit']" "xpath_element"
    And the field "First name" matches value "Rebecca"
    And I set the following fields to these values:
      | First name     | Pablo              |
      | Last name      | Menendez           |
      | Email address  | pablom             |
    And I click on "Save changes" "button"
    And I should see "Data submitted is invalid"
    And I click on "Cancel" "button"
    And I should see "Panelist management"

    # Check both panelists have the good information (modification saved for the first case only).
    And "Pablo" row "Last name" column of "generaltable" table should contain "Menendez"
    And "Pablo" row "Email address" column of "generaltable" table should contain "pablom@example.com"
    And "Rebecca" row "Last name" column of "generaltable" table should contain "Armenta"
    And "Rebecca" row "Email address" column of "generaltable" table should contain "rebeccaa@example.com"
    And I should not see "Donald"

    # Check in the panelists course the number of students.
    And I log out
    And I log in as "admin"
    And there should be "2" panelists in the panelists course for concordance "Test Concordance for Manage Panelists"
    # Delete a panelist.
    And I am on the "Test Concordance for Manage Panelists" "concordance activity" page logged in as teacher1
    And I follow "Manage panelists"
    And I click on "//table/tbody/tr[contains(.,'Rebecca')]//button[.='Delete']" "xpath_element"
    And I click on "Yes" "button" in the "Confirmation" "dialogue"
    And I should see "Panelist successfully deleted"
    And I should see "Pablo"
    And I should not see "Rebecca"

    # Check in the panelists course the number of students.
    And I log out
    And I log in as "admin"
    And there should be "1" panelists in the panelists course for concordance "Test Concordance for Manage Panelists"
