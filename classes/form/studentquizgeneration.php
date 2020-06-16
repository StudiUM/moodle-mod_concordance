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
        $mform = $this->_form;

        // Panelists.
        $mform->addElement('header', 'paneliststoincludeheader',
                get_string('paneliststoinclude', 'mod_concordance'));
        $group = array();
        foreach ($this->_customdata['panelists'] as $panelist) {
            $label = $panelist->get('firstname').' '.$panelist->get('lastname');

            $group[] = $mform->createElement('checkbox', 'paneliststoinclude['. $panelist->get("id").']', '', $label);
        }
        $mform->addGroup($group, 'paneliststoinclude', '', null, false);

        // Disable short forms.
        $mform->setDisableShortforms();
        $this->add_action_buttons(false, get_string('generate', 'mod_concordance'));

    }
}
