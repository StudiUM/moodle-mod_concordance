@mod @mod_concordance @javascript
Feature: Students quiz generation
  As a teacher
  In order to do a learning by concordance activity in my course
  I need to be able to generate the quiz for students

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com  |
      | student1 | Student1  | Student1 | student1@example.com  |
    And the following "courses" exist:
      | fullname  | shortname |
      | Course1   | c1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | c1     | editingteacher |
      | student1 | c1     | student        |
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
      | Question name     | Q1                          |
      | Question text     | First question              |
      | Option            | The option is ABC           |
      | New information   | The new information is DEF  |
    And I add a "Concordance of reasoning" question to the "Test quiz name" quiz with:
      | Question name     | Q2                          |
      | Question text     | Second question             |
      | Option            | The option is GHI           |

  Scenario: There is no panelist and no quiz selected yet
    Given I follow "TestConcordance"
    When I follow "Generate the quiz for students"
    Then I should see "No panelist have been created yet."
    And "Generate" "button" should not be visible

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
    When I follow "Generate the quiz for students"
    Then I should see "No quiz found for panelists"
    And I should not see "No panelist have been created yet."
    And "Generate" "button" should not be visible

  @_switch_window
  Scenario: Test the options on the "Generate the quiz for students" page
    # Select the quiz for panelists.
    Given I follow "TestConcordance"
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
    And I click on "Add new panelist" "button"
    And I set the following fields to these values:
      | First name     | Donald                    |
      | Surname        | Fletcher                  |
      | Email address  | donaldf@example.com       |
      | Biography      | <p>Fletcher biography</p> |
    And I click on "Save changes" "button"
    And I should see "Panelist created"
    And I click on "Add new panelist" "button"
    And I set the following fields to these values:
      | First name     | Pablo              |
      | Surname        | Menendez           |
      | Email address  | pablom@example.com |
    And I click on "Save changes" "button"
    And I should see "Panelist created"
    And I click on "Add new panelist" "button"
    And I set the following fields to these values:
      | First name     | Stepanie              |
      | Surname        | Grant                 |
      | Email address  | stepanieg@example.com |
    And I click on "Save changes" "button"
    And I should see "Panelist created"
    And I log out
    # No need to contact panelists here, other test will cover it.
    # Complete the test as panelists.
    # Panelist 1 completes the quiz.
    And I log in as concordance panelist "rebeccaa@example.com"
    And I wait until the page is ready
    And I should see "The description for the panelists"
    # Two screens with Attempt quiz now buttons.
    And I click on "Attempt quiz now" "button"
    And I click on "Attempt quiz now" "button"
    And I switch to the last window
    And I should see "First question"
    And I click on "Weakened" "radio" in the "First question" "question"
    And I set the field with xpath "(//div[@class='answerfeedback'])[1]/textarea" to "Comments from Rebecca for Q1"
    And I should see "Second question"
    And I click on "Weakened" "radio" in the "Second question" "question"
    And I set the field with xpath "(//div[@class='answerfeedback'])[2]/textarea" to "Comments from Rebecca for Q2"
    And I click on "Finish attempt ..." "button"
    And I click on "Submit all and finish" "button"
    And I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    # Check that Rebecca can't attempt again, but can review her attempt.
    And I log in as concordance panelist "rebeccaa@example.com"
    And I wait until the page is ready
    And I click on "Attempt quiz now" "button"
    And I should see "Summary of your previous attempts"
    And I should see "No more attempts are allowed"
    And I click on "Review" "button"
    And I switch to "quizpopup" window
    And I should see "Comments from Rebecca for Q1"
    # Panelist 2 completes the quiz.
    And I log in as concordance panelist "donaldf@example.com"
    And I click on "Continue" "button"
    And I click on "Attempt quiz now" "button"
    And I click on "Attempt quiz now" "button"
    And I switch to the last window
    And I should see "First question"
    And I click on "Unchanged" "radio" in the "First question" "question"
    And I set the field with xpath "(//div[@class='answerfeedback'])[1]/textarea" to "Comments from Donald for Q1"
    And I should see "Second question"
    And I click on "Weakened" "radio" in the "Second question" "question"
    And I set the field with xpath "(//div[@class='answerfeedback'])[2]/textarea" to "Comments from Donald for Q2"
    And I click on "Finish attempt ..." "button"
    And I click on "Submit all and finish" "button"
    And I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    # Panelist 3 begins the quiz but does not complete it.
    And I log in as concordance panelist "pablom@example.com"
    And I click on "Continue" "button"
    And I click on "Attempt quiz now" "button"
    And I click on "Attempt quiz now" "button"
    And I switch to the last window
    And I should see "First question"
    And I set the field "Unchanged" to "1"
    And I set the field "Comments" to "Comments from Pablo"
    And I click on "Finish attempt ..." "button"
    # Panelist 4 does not even start the quiz.

    # We generate some quizzes with different options.
    # General checks for the Generate quiz page.
    And I force log out
    And I log in as "teacher1"
    And I am on "Course1" course homepage
    And I follow "TestConcordance"
    When I follow "Generate the quiz for students"
    Then the "Generate" "button" should be disabled
    And I should see "Finished" in the "Rebecca Armenta" "table_row"
    And I should see "Finished" in the "Donald Fletcher" "table_row"
    And I should see "In progress" in the "Pablo Menendez" "table_row"
    And I should see "Not completed" in the "Stepanie Grant" "table_row"
    And the "Rebecca Armenta" "checkbox" should be enabled
    And the "Donald Fletcher" "checkbox" should be enabled
    And the "Pablo Menendez" "checkbox" should be enabled
    And the "Stepanie Grant" "checkbox" should be disabled
    # Generate the quiz for students (Quiz 1).
    And I click on "Rebecca Armenta" "checkbox"
    And I set the field "quiztype" to "Formative"
    And I set the field "Include panelists' biographies in the introduction" to "Yes"
    And the "Generate" "button" should be enabled
    And I click on "Generate" "button"
    And I should see "A new quiz has been generated"
    And I click on "Go to the generated quiz" "link"
    And I should see "Armenta biography"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Name          | Student quiz 1      |
      | Availability  | Show on course page |
    And I click on "Save and return to course" "button"
    # Generate the quiz for students (Quiz 2).
    And I follow "TestConcordance"
    And I follow "Generate the quiz for students"
    And I click on "Donald Fletcher" "checkbox"
    And I set the field "quiztype" to "Formative"
    And I set the field "Include panelists' biographies in the introduction" to "Yes"
    And I click on "Generate" "button"
    And I click on "Go to the generated quiz" "link"
    And I should see "Fletcher biography"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Name          | Student quiz 2      |
      | Availability  | Show on course page |
    And I click on "Save and return to course" "button"
    # Generate the quiz for students (Quiz 3).
    And I follow "TestConcordance"
    And I follow "Generate the quiz for students"
    And I click on "Rebecca Armenta" "checkbox"
    And I click on "Donald Fletcher" "checkbox"
    And I set the field "quiztype" to "Formative"
    And I set the field "Include panelists' biographies in the introduction" to "No"
    And I click on "Generate" "button"
    And I click on "Go to the generated quiz" "link"
    And I should not see "Fletcher biograph"
    And I should not see "Armenta biograph"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Name          | Student quiz 3      |
      | Availability  | Show on course page |
    And I click on "Save and return to course" "button"
    # Check the gradebook.
    And I navigate to "Gradebook setup" in current page administration
    And I should not see "Student quiz 1"
    And I should not see "Student quiz 2"
    And I should not see "Student quiz 3"
    And I should not see "Test quiz name"
    And I log out

    # Student logs in and see generated quizzes only.
    And I log in as "student1"
    And I am on "Course1" course homepage
    And I should see "Student quiz 1"
    And I should see "Student quiz 2"
    And I should see "Student quiz 3"
    And I should not see "Test quiz name"
    # Student answer the quiz and see feedbacks correctly.
    # Quiz 1 answered by Rebecca only.
    And I follow "Student quiz 1"
    And I should see "The description for the students"
    And I click on "Attempt quiz now" "button"
    And I click on "Weakened" "radio" in the "First question" "question"
    And I click on "Check" "button" in the "First question" "question"
    And I should see "The most popular answer is: Weakened" in the "First question" "question"
    And I should see "Comments from Rebecca for Q1" for panelist "Rebecca Armenta" for answer "Weakened" of question "1"
    And I should see no comments for answer "Unchanged" of question "1"
    And I should see that "0" panelists have answered "Unchanged" for question "1"
    And I should see that "1" panelists have answered "Weakened" for question "1"
    And I click on "Weakened" "radio" in the "Second question" "question"
    And I click on "Check" "button" in the "Second question" "question"
    And I should see "The most popular answer is: Weakened" in the "Second question" "question"
    And I should see "Comments from Rebecca for Q2" for panelist "Rebecca Armenta" for answer "Weakened" of question "2"
    And I should see no comments for answer "Unchanged" of question "2"
    And I should see that "0" panelists have answered "Unchanged" for question "2"
    And I should see that "1" panelists have answered "Weakened" for question "2"
    # Quiz 2 answered by Donald only.
    And I am on "Course1" course homepage
    And I follow "Student quiz 2"
    And I click on "Attempt quiz now" "button"
    And I click on "Weakened" "radio" in the "First question" "question"
    And I click on "Check" "button" in the "First question" "question"
    And I should see "The most popular answer is: Unchanged" in the "First question" "question"
    And I should see "Comments from Donald for Q1" for panelist "Donald Fletcher" for answer "Unchanged" of question "1"
    And I should see no comments for answer "Weakened" of question "1"
    And I should see that "1" panelists have answered "Unchanged" for question "1"
    And I should see that "0" panelists have answered "Weakened" for question "1"
    And I click on "Weakened" "radio" in the "Second question" "question"
    And I click on "Check" "button" in the "Second question" "question"
    And I should see "The most popular answer is: Weakened" in the "Second question" "question"
    And I should see "Comments from Donald for Q2" for panelist "Donald Fletcher" for answer "Weakened" of question "2"
    And I should see no comments for answer "Unchanged" of question "1"
    And I should see that "0" panelists have answered "Unchanged" for question "2"
    And I should see that "1" panelists have answered "Weakened" for question "2"
    # Quiz 3 answered by Rebecca and Donald.
    And I am on "Course1" course homepage
    And I follow "Student quiz 3"
    And I click on "Attempt quiz now" "button"
    And I click on "Weakened" "radio" in the "First question" "question"
    And I click on "Check" "button" in the "First question" "question"
    And I should see "The most popular answer is: Weakened, Unchanged" in the "First question" "question"
    And I should see "Comments from Rebecca for Q1" for panelist "Rebecca Armenta" for answer "Weakened" of question "1"
    And I should see "Comments from Donald for Q1" for panelist "Donald Fletcher" for answer "Unchanged" of question "1"
    And I should not see "Comments from Pablo"
    And I should see that "1" panelists have answered "Unchanged" for question "1"
    And I should see that "1" panelists have answered "Weakened" for question "1"
    And I click on "Weakened" "radio" in the "Second question" "question"
    And I click on "Check" "button" in the "Second question" "question"
    And I should see "The most popular answer is: Weakened" in the "Second question" "question"
    And I should see "Comments from Rebecca for Q2" for panelist "Rebecca Armenta" for answer "Weakened" of question "2"
    And I should see "Comments from Donald for Q2" for panelist "Donald Fletcher" for answer "Weakened" of question "2"
    And I should see that "0" panelists have answered "Unchanged" for question "2"
    And I should see that "2" panelists have answered "Weakened" for question "2"
