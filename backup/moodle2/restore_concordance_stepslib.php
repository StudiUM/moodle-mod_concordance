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
 * Structure step to restore one concordance activity.
 *
 * @package   mod_concordance
 * @category  backup
 * @copyright 2020 Université de Montréal
 * @author    Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_concordance\panelist;
use mod_concordance\panelistmanager;

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one concordance activity.
 *
 * @package   mod_concordance
 * @category  backup
 * @copyright 2020 Université de Montréal
 * @author    Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_concordance_activity_structure_step extends restore_activity_structure_step {

    /**
     * Function that will return the structure to be processed by this restore_step.
     * Must return one array of @restore_path_element elements
     */
    protected function define_structure() {
        $paths = array();
        $paths[] = new restore_path_element('concordance', '/activity/concordance');
        $paths[] = new restore_path_element('concordance_panelist', '/activity/concordance/panelists/panelist');

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process concordance
     *
     * @param stdClass $data
     */
    protected function process_concordance($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the concordance record.
        $newitemid = $DB->insert_record('concordance', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);

        $data->id = $newitemid;
        $data->coursegenerated = generate_course_for_panelists($data);
        $DB->update_record('concordance', $data);

        // Prevent teacher to change concordance visibility.
        $context = context_module::instance($this->task->get_moduleid());
        $id = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
        assign_capability('moodle/course:activityvisibility', CAP_PROHIBIT, $id, $context->id, true);
        $id = $DB->get_field('role', 'id', array('shortname' => 'associateeditingteacher'));
        assign_capability('moodle/course:activityvisibility', CAP_PROHIBIT, $id, $context->id, true);

        // TODO EVOSTDM-2175 : traiter 'cmorigin' et 'cmgenerated'.
    }

    /**
     * Process syllabus concordance panelist.
     *
     * @param stdClass $data
     */
    protected function process_concordance_panelist($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->concordance = $this->get_new_parentid('concordance');
        $data->nbemailsent = 0;

        $newitemid = $DB->insert_record('concordance_panelist', $data);
        $panelist = new panelist($newitemid);
        panelistmanager::panelistcreated($panelist);

        // Mapping is needed for add_related_files.
        $this->set_mapping('concordance_panelist', $oldid, $newitemid, true);
    }

    /**
     * After execute function.
     */
    protected function after_execute() {
        // Add syllabus related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_concordance', 'descriptionpanelist', null);
        $this->add_related_files('mod_concordance', 'descriptionstudent', null);
        $this->add_related_files('mod_concordance', 'bibliography', 'concordance_panelist');
    }
}
