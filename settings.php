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
 * Concordance settings.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_settings_coursecat_select('mod_concordance/categorypanelcourses',
        get_string('categorypanelcourses', 'mod_concordance'), get_string('configcategorypanelcourses', 'mod_concordance'), 1));

    $studentroles = [];
    $roles = role_fix_names(get_all_roles(), null, ROLENAME_ORIGINALANDSHORT);
    foreach ($roles as $role) {
        if ($role->archetype == 'student') {
            $defaultstudentroleid = isset($defaultstudentroleid) ? $defaultstudentroleid : $role->id;
            $studentroles[$role->id] = $role->localname;
        }
    }
    if (empty($studentroles)) {
        $studentroles[0] = new lang_string('none');
        $defaultstudentroleid = 0;
    }

    $settings->add(new admin_setting_configselect('mod_concordance/panelistsrole', get_string('panelistsrole', 'mod_concordance'),
        get_string('configpanelistsrole', 'mod_concordance'), $defaultstudentroleid, $studentroles));
    $systemroles = [];
    $allowedsystemroleids = get_roles_for_contextlevels(CONTEXT_SYSTEM);
    $systemroles[0] = get_string('nosystemrole', 'mod_concordance');
    foreach ($allowedsystemroleids as $allowedsystemroleid) {
        $systemroles[$allowedsystemroleid] = $roles[$allowedsystemroleid]->localname;
    }
    $settings->add(new admin_setting_configselect('mod_concordance/panelistssystemrole',
        get_string('panelistssystemrole', 'mod_concordance'),
        get_string('configpanelistssystemrole', 'mod_concordance'), 0, $systemroles));
}
