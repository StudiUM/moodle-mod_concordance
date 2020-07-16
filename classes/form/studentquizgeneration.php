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
 * This file contains the form to generate a student quiz.
 *
 * @package   mod_concordance
 * @copyright 2020 Université de Montréal
 * @author    Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_concordance\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Student quiz generation form.
 *
 * @package   mod_concordance
 * @copyright 2020 Université de Montréal
 * @author    Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class studentquizgeneration extends \moodleform {

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {
        global $PAGE;
        $mform = $this->_form;

        // Panelists.
        $mform->addElement('header', 'paneliststoincludeheader',
                get_string('paneliststoinclude', 'mod_concordance'));
        $statelabel = get_string('quizstate', 'mod_concordance');
        $mform->addElement('html',
                "<table id='paneliststoincludetable'><thead><tr><th></th><th>$statelabel</th></tr></thead>");
        $mform->addElement('html', '<tbody>');
        foreach ($this->_customdata['panelists'] as $panelist) {
            $label = $panelist->get('firstname').' '.$panelist->get('lastname');
            $mform->addElement('html', '<tr><td>');
            $mform->addElement('checkbox', 'paneliststoinclude['. $panelist->get("id").']', '', $label);
            $quizstate = '';
            $quizstateclass = '';
            if (key_exists($panelist->get("userid"), $this->_customdata['attempts'])) {
                $state = $this->_customdata['attempts'][$panelist->get('userid')]->state;
                if (!empty($state)) {
                    $quizstate = get_string('state' . $state, 'mod_quiz');
                    if ($state == \quiz_attempt::FINISHED) {
                        $quizstateclass = 'label-success';
                    }
                    if ($state == \quiz_attempt::IN_PROGRESS) {
                        $quizstateclass = 'label-warning';
                        $mform->freeze(['paneliststoinclude['. $panelist->get("id").']']);
                    }
                } else {
                    $quizstate = get_string('notcompleted', 'mod_concordance');
                    $mform->freeze(['paneliststoinclude['. $panelist->get("id").']']);
                }
            } else {
                $quizstate = get_string('notcompleted', 'mod_concordance');
                $mform->freeze(['paneliststoinclude['. $panelist->get("id").']']);
            }

            $mform->addElement('html', '</td>');
            $mform->addElement('html', "<td><span class='mt-1 label $quizstateclass'>$quizstate</span></td></tr>");
        }
        $mform->addElement('html', '</tbody></table>');

        // Disable short forms.
        $mform->setDisableShortforms();
        $this->add_action_buttons(false, get_string('generate', 'mod_concordance'));

        $PAGE->requires->js_call_amd('mod_concordance/studentquizgeneration', 'init', []);
    }
}
