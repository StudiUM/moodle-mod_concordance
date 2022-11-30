@mod @mod_concordance
Feature:
  As a panelist i can access a quiz with concordance questions
  but i can't change information of the created account and not access toother courses

  Background:
    Given the following "roles" exist:
      | shortname | name    | archetype |
      | panelist   | Panelist |     |
    And the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com  |
      | student1 | Student1  | Student1 | student1@example.com  |
    And the following "permission overrides" exist:
      | capability                          | permission    | role    | contextlevel | reference |
      | moodle/my:manageblocks              | Prohibit      | panelist | System       |           |
      | moodle/site:sendmessage             | Prohibit      | panelist | System       |           |
      | moodle/user:changeownpassword       | Prohibit      | panelist | System       |           |
      | moodle/user:editownmessageprofile   | Prohibit      | panelist | System       |           |
      | moodle/user:editownprofile          | Prohibit      | panelist | System       |           |
      | moodle/user:manageownfiles          | Prohibit      | panelist | System       |           |
      | moodle/user:editprofile             | Prohibit      | panelist | System       |           |
      | moodle/badges:manageownbadges       | Prohibit      | panelist | System       |           |
      | tool/dataprivacy:requestdelete      | Prohibit      | panelist | System       |           |
      | moodle/webservice:createtoken       | Prohibit      | panelist | System       |           |
    And the following config values are set as admin:
      | config | value | plugin |
      | contactdataprotectionofficer | 1 | tool_dataprivacy |
    And the following "courses" exist:
      | fullname  | shortname |
      | Course1   | c1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | c1     | editingteacher |
      | student1 | c1     | student        |
    And I log in as "admin"
    And I navigate to "Plugins >  Activity modules >  Concordance" in site administration
    And I select "Panelist (panelist)" from the "System role for the panelists" singleselect
    And I click on "Save changes" "button"
    And I log out
    And I log in as "teacher1"
    And I am on "Course1" course homepage with editing mode on
    # Create a basic concordance activity.
    And I add a "Learning by concordance management" to section "1" and I fill the form with:
      | Name                          | TestConcordance                   |
      | Description for the panelists | The description for the panelists |
      | Description for the students  | The description for the students  |
    # Create a quiz with one concordance question.
    And I add a "Quiz" to section "1" and I fill the form with:
      | Name          | Test quiz name        |
      | Description   | Test quiz description |
      | Availability  | Hide from students    |
    And I add a "Concordance of reasoning" question to the "Test quiz name" quiz with:
      | Question name                                                       | Q1                          |
      | Display the "This question is outside my field of competence" field | No                          |
      | Question text                                                       | First question              |
      | Option                                                              | The option is ABC           |
      | New information                                                     | The new information is DEF  |
    # Select the quiz for panelists.
    And I follow "TestConcordance"
    And I follow "Select the quiz for panelists"
    And I set the field "Quiz" to "Test quiz name"
    And I click on "Save changes" "button"
    # Add panelists.
    And I follow "TestConcordance"
    And I follow "Manage panelists"
    And I click on "Add new panelist" "button"
    And I set the following fields to these values:
      | First name     | Rebecca                  |
      | Surname        | Armenta                  |
      | Email address  | rebeccaa@example.com     |
      | Biography      | <p>Armenta biography</p> |
    And I click on "Save changes" "button"
    And I should see "Panelist created"
    Then I log out

  @javascript
  Scenario: Test what a Panelist can see
    Given I log in as concordance panelist "rebeccaa@example.com"
    And I wait until the page is ready
    And I should see "The description for the panelists"
    And I click on "Attempt quiz now" "button"
    When I follow "preferences" in the user menu
    # moodle/user:editownprofile
    Then I should not see "Edit profile"
    # moodle/user:changeownpassword
    And I should not see "Change password"
    # moodle/user:editownmessageprofil
    And I should not see "Message preferences"
    And I should not see "Calendar preferences"
    # moodle/webservice:createtoken
    And I should not see "Security keys"
    # moodle/badges:manageownbadges
    And I should not see "Manage badges"
    When I follow "Profile" in the user menu
    # moodle/user:manageownfiles
    And I should not see "Private files"
        # tool/dataprivacy:requestdelete
    And I should not see "Delete my account"
    # moodle/site:sendmessage
    When I open messaging
    And I click on "//*[@id='view-overview-favourites-toggle']/following-sibling::div/div[2]/a" "xpath_element"
    And I set the field with xpath "//textarea[@data-region='send-message-txt']" to "My message"
    And I click on "//button[@aria-label='Send message']" "xpath_element"
    Then I should see "cannot send a message to conversation"
    # moodle/my:manageblocks
    When I follow "Dashboard" in the user menu
    Then I should not see "Customise this page"




