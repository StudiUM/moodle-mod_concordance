<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Step definitions for Concordance.
 *
 * @package    mod_concordance
 * @category   test
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @copyright  2020 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use mod_concordance\concordance;
use mod_concordance\panelist;

/**
 * Step definitions for Concordance.
 *
 * @package    mod_concordance
 * @category   test
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @copyright  2020 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_concordance extends behat_base {
    /**
     * Log in as a concordance panelist, the same way they will do with the link
     * they receive by email.
     *
     * @Given I log in as concordance panelist :email
     * @param string $email
     */
    public function i_log_in_as_concordance_panelist($email) {
        // Get the token and generate the link to access.
        $panelist = panelist::get_record(['email' => $email]);
        $key = $panelist->get_user_key();

        $url = new moodle_url('/mod/concordance/quizaccess.php', array('key' => $key));
        $this->getSession()->visit($this->locate_path($url->out_as_local_url()));
    }

    /**
     * Panelists can't log out the regular way : they just have to close the window.
     * We can't do that in behat, so we do it another way : go to the login page
     * and then there is a confirmation button to logout.
     *
     * @Given I force log out
     */
    public function i_force_log_out() {
        $loginurl = new moodle_url('/login/index.php');
        // Visit login page.
        $this->getSession()->visit($this->locate_path($loginurl->out_as_local_url()));
        $this->execute('behat_forms::press_button', get_string('logout'));
    }

    /**
     * Check that the comment made by the panelist appears for the specified answer choice, for the specified question.
     *
     * @Given I should see :comment for panelist :panelist for answer :answer of question :questionnb
     * @param string $comment The comment entered by the panelist.
     * @param string $panelist The panelist name.
     * @param string $answer The answer the panelist chose.
     * @param int $questionnb The question number.
     */
    public function i_should_see_for_panelist_for_answer_of_question($comment, $panelist, $answer, $questionnb) {
        $xpath = "(//div[contains(@class,'tcs')])[$questionnb]//div[contains(@class,'specificfeedback')]/p[contains(.,'$answer')]"
            . "/following::div[1]/p[contains(.,'$panelist')]/following::p[1]";
        $this->execute("behat_general::assert_element_contains_text",
            array($comment, $xpath, "xpath_element")
        );
    }

    /**
     * Check that no panelists made comments for the specified answer choice, for the specified question.
     *
     * @Given I should see no comments for answer :answer of question :questionnb
     * @param string $answer The answer the panelist chose.
     * @param int $questionnb The question number.
     */
    public function i_should_see_no_comments_for_answer_of_question($answer, $questionnb) {
        $xpath = "(//div[contains(@class,'tcs')])[$questionnb]//div[contains(@class,'specificfeedback')]/p[contains(.,'$answer')]";
        $this->execute("behat_general::should_not_exist", array($xpath, "xpath_element"));
    }

    /**
     * Check the number of panelists who chose the specified answer choice, for the specified question.
     *
     * @Given I should see that :nbpanelists panelists have answered :answer for question :questionnb
     * @param string $nbpanelists The number of panelists who chose this answer.
     * @param string $answer The answer the panelist chose.
     * @param int $questionnb The question number.
     */
    public function i_should_see_that_panelists_have_answered_for_question($nbpanelists, $answer, $questionnb) {
        $xpath = "(//div[contains(@class,'tcs')])[$questionnb]//div[contains(@class,'formulation')]//div[contains(@class,'answer')]"
            . "//label[.='$answer']/following::span[not(contains(@class,'gauge'))][2]";
        $this->execute("behat_general::assert_element_contains_text",
            array($nbpanelists, $xpath, "xpath_element")
        );
    }

    /**
     * Check the number of panelists who appear as students in the temporary course for panelists.
     *
     * @Given there should be :nb panelists in the panelists course for concordance :concordance
     * @param int $nb The number of panelists there should be.
     * @param string $concordance The name of the concordance activity.
     */
    public function there_should_be_panelists_in_the_panelists_course_for_concordance($nb, $concordance) {
        $concordance = concordance::get_record(['name' => $concordance]);
        $course = get_course($concordance->get('coursegenerated'));

        $this->execute("behat_navigation::i_am_on_course_homepage", array($course->fullname));
        $this->execute("behat_general::click_link", array('Participants'));
        $this->execute("behat_general::i_should_see_occurrences_of_in_element", array($nb, "Student", "generaltable", "table"));
    }
}
