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
 * This is called at the beginning of the uninstallation process.
 *
 * @package   mod_concordance
 * @copyright 2020 Université de Montréal
 * @author    Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * This is called at the beginning of the uninstallation process to give the module
 * a chance to clean-up its hacks, bits etc. where possible.
 *
 * @return bool true if success
 */
function xmldb_concordance_uninstall() {
    global $DB;
    // Delete users associated to panelists.
    $sql = "id IN (SELECT userid
                     FROM {concordance_panelist}
                    WHERE 1)";
    $users = $DB->get_records_select('user', $sql, []);
    foreach ($users as $user) {
        delete_user($user);
    }
    // Delete the courses generated.
    $courses = $DB->get_records_select('concordance', "coursegenerated IS NOT NULL", [], null, 'coursegenerated');
    foreach ($courses as $key => $value) {
        delete_course($key, false);
    }
    if (count($courses > 0)) {
        fix_course_sortorder();
    }
    return true;
}
