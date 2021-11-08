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
 * Class for panelist management.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_concordance;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/lib.php');

/**
 * Class for panelist management.
 *
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panelistmanager {

    /**
     * Steps to do when a panelist is created:
     * Create Moodle user.
     * Enrol user in panelist course.
     *
     * @param panelist $panelist Panelist persistence object
     */
    public static function panelistcreated($panelist) {
        global $DB;

        if (!$panelist->get('userid')) {
            $concordance = new \mod_concordance\concordance($panelist->get('concordance'));
            if ($panelistcourse = $concordance->get('coursegenerated')) {
                // Create user Moodle.
                $user = new \stdClass();
                $user->username = uniqid('concordance-panelist-');
                $user->firstname = 'Panelist-' . $panelist->get('id');
                $user->lastname = $user->firstname;
                $user->email = $user->username;
                $user->confirmed = 1;
                $userid = user_create_user($user, false, false);
                // Enrol user in panelist course.
                $plugin = enrol_get_plugin('manual');
                $roleid = get_config('mod_concordance', 'panelistsrole');
                $instances = $DB->get_records('enrol',
                        array('courseid' => $panelistcourse, 'enrol' => 'manual'));
                $instance = reset($instances);
                $plugin->enrol_user($instance, $userid, $roleid);
                // Set userid in panelist.
                $panelist->set('userid', $userid);
                $panelist->update();
            }
        }
    }

    /**
     * Delete Moodle user when panelist deleted.
     *
     * @param int $moodleuserid Moodle user id
     */
    public static function panelistdeleted($moodleuserid) {
        global $DB;

        if ($moodleuserid) {
            $user = $DB->get_record('user', array('id' => $moodleuserid));
            user_delete_user($user);
        }
    }
}
