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
 * Class for quiz management.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_concordance;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/lib.php');

use \stdClass;
use \moodle_exception;
use \backup_controller;
use \restore_controller;
use \backup;
use \context_module;
use \context_course;

/**
 * Class for quiz management.
 *
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Lévesque <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizmanager {
    /**
     * Duplicate the origin quiz so it can be used by the panelists.
     *
     * @param concordance $concordance Concordance persistence object.
     * @param boolean $async True to delete the old quiz async, false otherwise (usually false only for test purpose).
     */
    public static function duplicatequizforpanelists($concordance, $async = true) {
        global $DB;
        // If a quiz for panelists was already generated, delete it.
        if (!is_null($concordance->get('cmgenerated'))) {
            course_delete_module($concordance->get('cmgenerated'), $async);
            $concordance->set('cmgenerated', null);
        }

        // If an origin quiz was selected, duplicate it in the panelists' course, make it visible and save it as 'cmgenerated'.
        if (!is_null($concordance->get('cmorigin'))) {
            $cm = get_coursemodule_from_id('', $concordance->get('cmorigin'), 0, true, MUST_EXIST);
            $coursegeneratedid = $concordance->get('coursegenerated');
            $course = $DB->get_record('course', array('id' => $coursegeneratedid), '*', MUST_EXIST);

            $newcm = self::duplicate_module($course, $cm);
            set_coursemodule_visible($newcm->id, 1);
            $concordance->set('cmgenerated', $newcm->id);

            $quiz = $DB->get_record('quiz', array('id' => $newcm->instance), '*', MUST_EXIST);
            $quiz->browsersecurity = 'securewindow';
            $DB->update_record('quiz', $quiz);
        }
    }

    /**
     * Api to duplicate a module. This was copied from duplicate_module in course/lib.php.
     * This copy is necessary because the core function does not allow to duplicate in a different course.
     * The differences with the original function have a "Concordance modification : " comment just above them.
     *
     * @param object $course course object.
     * @param object $cm course module object to be duplicated.
     *
     * @throws Exception
     * @throws coding_exception
     * @throws moodle_exception
     * @throws restore_controller_exception
     *
     * @return cm_info|null cminfo object if we sucessfully duplicated the mod and found the new cm.
     */
    private static function duplicate_module($course, $cm) {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/filelib.php');

        // Concordance modification : Temporarily enrol teacher in panelist course to avoid capability errors.
        $enrolplugin = enrol_get_plugin('manual');
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'), MUST_EXIST);
        $context = context_course::instance($course->id);
        $isenrolled = user_has_role_assignment($USER->id, $roleid, $context->id);
        if (!$isenrolled) {
            $instances = $DB->get_records('enrol',
                    array('courseid' => $course->id, 'enrol' => 'manual'));
            $enrolinstance = reset($instances);
            $enrolplugin->enrol_user($enrolinstance, $USER->id, $roleid);
        }

        $a          = new stdClass();
        $a->modtype = get_string('modulename', $cm->modname);
        $a->modname = format_string($cm->name);

        if (!plugin_supports('mod', $cm->modname, FEATURE_BACKUP_MOODLE2)) {
            throw new moodle_exception('duplicatenosupport', 'error', '', $a);
        }

        // Backup the activity.

        $bc = new backup_controller(backup::TYPE_1ACTIVITY, $cm->id, backup::FORMAT_MOODLE,
                backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id);

        $backupid       = $bc->get_backupid();
        $backupbasepath = $bc->get_plan()->get_basepath();

        $bc->execute_plan();

        $bc->destroy();

        // Restore the backup immediately.
        $rc = new restore_controller($backupid, $course->id,
                backup::INTERACTIVE_NO, backup::MODE_IMPORT, $USER->id, backup::TARGET_CURRENT_ADDING);

        // Make sure that the restore_general_groups setting is always enabled when duplicating an activity.
        $plan = $rc->get_plan();
        $groupsetting = $plan->get_setting('groups');
        if (empty($groupsetting->get_value())) {
            $groupsetting->set_value(true);
        }

        $cmcontext = context_module::instance($cm->id);
        if (!$rc->execute_precheck()) {
            $precheckresults = $rc->get_precheck_results();
            if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                if (empty($CFG->keeptempdirectoriesonbackup)) {
                    fulldelete($backupbasepath);
                }
            }
        }

        $rc->execute_plan();

        // Now a bit hacky part follows - we try to get the cmid of the newly
        // restored copy of the module.
        $newcmid = null;
        $tasks = $rc->get_plan()->get_tasks();
        foreach ($tasks as $task) {
            if (is_subclass_of($task, 'restore_activity_task')) {
                if ($task->get_old_contextid() == $cmcontext->id) {
                    $newcmid = $task->get_moduleid();
                    break;
                }
            }
        }

        $rc->destroy();

        if (empty($CFG->keeptempdirectoriesonbackup)) {
            fulldelete($backupbasepath);
        }

        // If we know the cmid of the new course module, let us move it
        // right below the original one. otherwise it will stay at the
        // end of the section.
        if ($newcmid) {
            // Proceed with activity renaming before everything else. We don't use APIs here to avoid
            // triggering a lot of create/update duplicated events.
            // Concordance modification : $course->id instead of $cm->course.
            $newcm = get_coursemodule_from_id($cm->modname, $newcmid, $course->id);

            // Concordance modification : Code removed (add '(copy)' to the duplicate and move module in the section).

            // Update calendar events with the duplicated module.
            // The following line is to be removed in MDL-58906.
            course_module_update_calendar_events($newcm->modname, null, $newcm);

            // Trigger course module created event. We can trigger the event only if we know the newcmid.
            // Concordance modification : $newcm->course instead of $cm->course.
            $newcm = get_fast_modinfo($newcm->course)->get_cm($newcmid);
            $event = \core\event\course_module_created::create_from_cm($newcm);
            $event->trigger();
        }

        // Concordance modification : Unenrol temporary teacher in panelist course.
        if (!$isenrolled) {
            $enrolplugin->unenrol_user($enrolinstance, $USER->id);
        }

        return isset($newcm) ? $newcm : null;
    }
}