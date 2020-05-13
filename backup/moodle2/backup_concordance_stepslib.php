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
 * Define all the backup steps that will be used by the backup_concordance_activity_task
 *
 * @package   mod_concordance
 * @category  backup
 * @copyright 2020 Université de Montréal
 * @author    Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define all the backup steps that will be used by the backup_concordance_activity_task
 *
 * @package   mod_concordance
 * @category  backup
 * @copyright 2020 Université de Montréal
 * @author    Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_concordance_activity_structure_step extends backup_activity_structure_step {
    /**
     * Function that will return the structure to be processed by this restore_step.
     * Must return one array of @restore_path_element elements
     */
    protected function define_structure() {
        // Define each element separated.
        $concordance = new backup_nested_element('concordance', array('id'), array(
            'name', 'course', 'timemodified', 'descriptionpanelist', 'descriptionpanelistformat', 'descriptionstudent',
            'descriptionstudentformat', 'activephase'));

        // EVOSTDM-2091 ou ultérieur : TODO traiter 'cmorigin' et 'cmgenerated'.

        $panelists = new backup_nested_element('panelists');

        $panelist = new backup_nested_element('panelist', array('id'), array(
            'firstname', 'lastname', 'email', 'nbemailsent', 'bibliography', 'bibliographyformat', 'timemodified'));

        // Build the tree.
        $concordance->add_child($panelists);
        $panelists->add_child($panelist);

        // Define sources.
        $concordance->set_source_table('concordance', array('id' => backup::VAR_ACTIVITYID));
        $panelist->set_source_table('concordance_panelist', array('concordance' => backup::VAR_PARENTID));

        // Define file annotations.
        $panelist->annotate_files('mod_concordance', 'descriptionpanelist', null); // This file area does not have an itemid.
        $panelist->annotate_files('mod_concordance', 'descriptionstudent', null); // This file area does not have an itemid.
        $panelist->annotate_files('mod_concordance', 'bibliography', null); // This file area does not have an itemid.

        // Return the root element, wrapped into standard activity structure.
        return $this->prepare_activity_structure($concordance);
    }
}
