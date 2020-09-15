@mod @mod_concordance @javascript
Feature: Quiz selection
  As a teacher
  In order to do a learning by concordance activity in my course
  I need to be able to select a quiz for the panelists

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
    # Create a basic concordance activity.
    And the following "activities" exist:
      | activity    | course | idnumber     | name             | descriptionpanelist    | descriptionstudent    |
      | concordance | c1     | concordance1 | TestConcordance  | Description panelists  | Description students  |
    # Create different quizzes
    And the following "activities" exist:
      | activity    | course | idnumber     | name                                    | visible |
      | quiz        | c1     | quiz1        | Quiz hidden with TCS                    | 0       |
      | quiz        | c1     | quiz1        | Quiz visible with TCS and other         | 1       |
      | quiz        | c1     | quiz1        | Quiz hidden without TCS but with other  | 0       |
      | quiz        | c1     | quiz1        | Quiz visible without TCS                | 1       |
    # Create questions.
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | c1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype       | name        | template    |
      | Test questions   | tcs         | TCS-001     | reasoning   |
      | Test questions   | multichoice | MC-001      | two_of_four |
    # Add the questions to some of the quizzes.
    And concordance quiz "Quiz hidden with TCS" contains the following questions:
      | question       | page |
      | TCS-001        | 1    |
    And concordance quiz "Quiz visible with TCS and other" contains the following questions:
      | question       | page |
      | TCS-001        | 1    |
      | MC-001         | 1    |
    And concordance quiz "Quiz hidden without TCS but with other" contains the following questions:
      | question       | page |
      | MC-001         | 1    |

  Scenario: Select quizzes with different parameters, to test validations.
    Given I log in as "teacher1"
    And I am on "Course1" course homepage with editing mode on
    When I follow "TestConcordance"
    Then the status of concordance task "Select the quiz for panelists" should be "todo"
    And I follow "Select the quiz for panelists"
    And the "cmorigin" select box should contain "Quiz hidden with TCS (hidden)"
    And the "cmorigin" select box should contain "Quiz visible with TCS and other"
    And the "cmorigin" select box should contain "Quiz hidden without TCS but with other (hidden)"
    And the "cmorigin" select box should contain "Quiz visible without TCS"
    # Select and check the 'Quiz hidden with TCS (hidden)' quiz.
    And I set the field "cmorigin" to "Quiz hidden with TCS (hidden)"
    And I press "Save changes"
    And I should see "The quiz contains Concordance questions"
    And I should not see "The quiz does not contain Concordance questions"
    And I should not see "questions of a type other than Concordance"
    And I should not see "currently visible to students"
    And I follow "TestConcordance"
    And the status of concordance task "Select the quiz for panelists" should be "done"
    And I should not see "The quiz does not contain Concordance questions"
    And I should not see "questions of a type other than Concordance"
    And I should not see "currently visible to students"
    # Select and check the 'Quiz visible with TCS and other' quiz.
    And I follow "Select the quiz for panelists"
    And I set the field "cmorigin" to "Quiz visible with TCS and other"
    And I press "Save changes"
    And I should see "The quiz contains Concordance questions"
    And I should not see "The quiz does not contain Concordance questions"
    And I should see "questions of a type other than Concordance"
    And I should see "currently visible to students"
    And I follow "TestConcordance"
    And the status of concordance task "Select the quiz for panelists" should be "fail"
    And I should not see "The quiz does not contain Concordance questions"
    And there should be an info after concordance task "Select the quiz for panelists" saying "questions of a type other than Concordance"
    And there should be an info after concordance task "Select the quiz for panelists" saying "currently visible to students"
    # Select and check the 'Quiz hidden without TCS but with other' quiz.
    And I follow "Select the quiz for panelists"
    And I set the field "cmorigin" to "Quiz hidden without TCS but with other (hidden)"
    And I press "Save changes"
    And I should not see "The quiz contains Concordance questions"
    And I should see "The quiz does not contain Concordance questions"
    And I should see "questions of a type other than Concordance"
    And I should not see "currently visible to students"
    And I follow "TestConcordance"
    And the status of concordance task "Select the quiz for panelists" should be "fail"
    And there should be an info after concordance task "Select the quiz for panelists" saying "The quiz does not contain Concordance questions"
    And there should be an info after concordance task "Select the quiz for panelists" saying "questions of a type other than Concordance"
    And I should not see "currently visible to students"
    # Select and check the 'Quiz visible without TCS' quiz.
    And I follow "Select the quiz for panelists"
    And I set the field "cmorigin" to "Quiz visible without TCS"
    And I press "Save changes"
    And I should not see "The quiz contains Concordance questions"
    And I should see "The quiz does not contain Concordance questions"
    And I should not see "questions of a type other than Concordance"
    And I should see "currently visible to students"
    And I follow "TestConcordance"
    And the status of concordance task "Select the quiz for panelists" should be "fail"
    And there should be an info after concordance task "Select the quiz for panelists" saying "The quiz does not contain Concordance questions"
    And I should not see "questions of a type other than Concordance"
    And there should be an info after concordance task "Select the quiz for panelists" saying "currently visible to students"

  Scenario: Try to select a quiz if panelists have already been contacted.
    Given I log in as "teacher1"
    And I am on "Course1" course homepage with editing mode on
    When I follow "TestConcordance"
    And I follow "Select the quiz for panelists"
    And I set the field "cmorigin" to "Quiz hidden with TCS (hidden)"
    And I press "Save changes"
    Then the "cmorigin" "field" should be enabled
    And I should not see "at least one panelist was already contacted"
    # Add a panelist.
    And I follow "TestConcordance"
    And I follow "Manage panelists"
    And I click on "Add new panelist" "button"
    And I set the following fields to these values:
      | First name     | Rebecca              |
      | Surname        | Armenta              |
      | Email address  | rebeccaa@example.com |
    And I click on "Save changes" "button"
    # Contact the panelist.
    And I follow "TestConcordance"
    And I follow "Contact panelists"
    And I click on "panelists" "checkbox" in the "Rebecca" "table_row"
    And I click on "Send a message" "button"
    And I click on "Send message to 1 person" "button"
    And I wait until the page is ready
    And I should see "Message sent to 1 person"
    # Check the 'select quiz' page and info in wizard.
    And I follow "TestConcordance"
    And I follow "Select the quiz for panelists"
    And "cmorigin" "field" should not exist
    And I should see "at least one panelist was already contacted"
    # Delete the panelist, and check the 'select quiz' page and info in wizard.
    And I follow "TestConcordance"
    And I follow "Manage panelists"
    And I click on "//table/tbody/tr[contains(.,'Rebecca')]//button[.='Delete']" "xpath_element"
    And I click on "Yes" "button" in the "Confirmation" "dialogue"
    And I should see "Panelist successfully deleted"
    And I follow "TestConcordance"
    And I follow "Select the quiz for panelists"
    And the "cmorigin" "field" should be enabled
    And I should not see "at least one panelist was already contacted"