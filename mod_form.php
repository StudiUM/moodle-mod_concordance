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
 * Concordance configuration form.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Concordance configuration form
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_concordance_mod_form extends moodleform_mod {

    /**
     * Define form elements.
     */
    public function definition() {
        global $CFG;
        $mform = $this->_form;

        // Name.
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        // Description for panelist.
        $mform->addElement('editor', 'descriptionpanelisteditor', get_string('descriptionpanelist', 'mod_concordance'), null,
                            concordance_get_editor_options($this->context));
        $mform->setType('descriptionpanelisteditor', PARAM_RAW);

        // Description for student.
        $mform->addElement('editor', 'descriptionstudenteditor', get_string('descriptionstudent', 'mod_concordance'), null,
                            concordance_get_editor_options($this->context));
        $mform->setType('descriptionstudenteditor', PARAM_RAW);

        $this->standard_coursemodule_elements();
        // Freeze availability.
        $mform->hardFreeze('visible');
        $this->add_action_buttons();
    }

    /**
     * Prepares the form before data are set.
     *
     * Additional wysiwyg editor are prepared here.
     *
     * @param array $data to be set
     */
    public function data_preprocessing(&$data) {
        if ($this->current->instance) {
            // Prepare the added editor elements.
            // Descriptionpanelist.
            $draftitemid = file_get_submitted_draft_itemid('descriptionpanelist');
            $data['descriptionpanelisteditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_concordance', 'descriptionpanelist', 0,
                                concordance_get_editor_options($this->context),
                                $data['descriptionpanelist']);
            $data['descriptionpanelisteditor']['format'] = $data['descriptionpanelistformat'];
            $data['descriptionpanelisteditor']['itemid'] = $draftitemid;

            // Descriptionstudent.
            $draftitemid = file_get_submitted_draft_itemid('descriptionstudent');
            $data['descriptionstudenteditor']['text'] = file_prepare_draft_area($draftitemid, $this->context->id,
                                'mod_concordance', 'descriptionstudent', 0,
                                concordance_get_editor_options($this->context),
                                $data['descriptionstudent']);
            $data['descriptionstudenteditor']['format'] = $data['descriptionstudentformat'];
            $data['descriptionstudenteditor']['itemid'] = $draftitemid;
        } else {
            // Descriptionpanelist.
            // Adding a new concordance instance.
            $draftitemid = file_get_submitted_draft_itemid('descriptionpanelist');
            // No context yet, itemid not used.
            file_prepare_draft_area($draftitemid, null, 'mod_concordance', 'descriptionpanelist', false);
            $data['descriptionpanelisteditor']['text'] = '';
            $data['descriptionpanelisteditor']['format'] = editors_get_preferred_format();
            $data['descriptionpanelisteditor']['itemid'] = $draftitemid;

            // Descriptionstudent.
            // Adding a new concordance instance.
            $draftitemid = file_get_submitted_draft_itemid('descriptionstudent');
            // No context yet, itemid not used.
            file_prepare_draft_area($draftitemid, null, 'mod_concordance', 'descriptionstudent', false);
            $data['descriptionstudenteditor']['text'] = '';
            $data['descriptionstudenteditor']['format'] = editors_get_preferred_format();
            $data['descriptionstudenteditor']['itemid'] = $draftitemid;
        }
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
        // Set concordance availibility hidden to student.
        $defaultvalues['visible'] = MoodleQuickForm_modvisible::HIDE;
        parent::set_data($defaultvalues);
    }
}
