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
 * This file contains the form add/edit a panelist.
 *
 * @package   mod_concordance
 * @copyright 2020 Université de Montréal
 * @author    Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_concordance\form;
defined('MOODLE_INTERNAL') || die();

use core\form\persistent;

/**
 * Panelist form.
 *
 * @package   mod_concordance
 * @copyright 2020 Université de Montréal
 * @author    Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panelist extends persistent {

    /** @var mod_concordance\panelist persistent class for form */
    protected static $persistentclass = 'mod_concordance\\panelist';

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'concordance');
        $mform->setType('concordance', PARAM_INT);
        $mform->setConstant('concordance', $this->_customdata['concordanceid']);

        $mform->addElement('header', 'generalhdr', get_string('general'));

        $attributes = 'maxlength="100" size="30"';
        // Firstname.
        $mform->addElement('text', 'firstname', get_string('firstname'), $attributes);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', null, 'required', null, 'client');

        // Lastname.
        $mform->addElement('text', 'lastname', get_string('lastname'), $attributes);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', null, 'required', null, 'client');

        // Email.
        $mform->addElement('text', 'email', get_string('email'), $attributes);
        $mform->addRule('email', null, 'required', null, 'client');
        $mform->setType('email', PARAM_RAW_TRIMMED);

        // Bibliography.
        $mform->addElement('editor', 'bibliography', get_string('bibliography', 'mod_concordance'), null,
                            concordance_get_editor_options($this->_customdata['context']));
        $mform->setType('bibliography', PARAM_RAW);

        // Disable short forms.
        $mform->setDisableShortforms();
        $this->add_action_buttons(true, get_string('savechanges'));

    }

    /**
     * Prepares the form before data are set.
     *
     * Additional wysiwyg editor are prepared here.
     *
     * @param array $data to be set
     */
    public function data_preprocessing(&$data) {
        $draftitemid = file_get_submitted_draft_itemid('bibliography');
        $text = file_prepare_draft_area($draftitemid, $this->_customdata['context']->id,
                        'mod_concordance', 'bibliography', $data['id'],
                        concordance_get_editor_options($this->_customdata['context']),
                        $data['bibliography']['text']);
        $data['bibliography']['text'] = $text;
        $data['bibliography']['itemid'] = $draftitemid;
    }

    /**
     * Load in existing data as form defaults. Usually new entry defaults are stored directly in
     * form definition (new entry form); this function is used to load in data where values
     * already exist and data is being edited (edit entry form).
     *
     * @param mixed $defaultvalues object or array of default values
     */
    public function set_data($defaultvalues) {
        if (is_object($defaultvalues)) {
            $defaultvalues = (array)$defaultvalues;
        }

        $this->data_preprocessing($defaultvalues);
        parent::set_data($defaultvalues);
    }

    /**
     * Define extra validation mechanims.
     *
     * @param  stdClass $data Data to validate.
     * @param  array $files Array of files.
     * @param  array $errors Currently reported errors.
     * @return array of additional errors, or overridden errors.
     */
    protected function extra_validation($data, $files, array &$errors) {
        global $DB;
        if (empty($this->_customdata['id'])) {
            // If there are other user(s) that already have the same email, show an error.
            $select = $DB->sql_equal('email', ':email', false) . ' AND concordance = :concordance';
            $params = array(
                'email' => $data->email,
                'concordance' => $data->concordance
            );
            if ($DB->record_exists_select('concordance_panelist', $select, $params)) {
                $errors["email"] = get_string('emailexists');
            }
        }

        return $errors;
    }
}
