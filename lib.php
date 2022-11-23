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
 * Mandatory public API of concordance module
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * List of features supported in Concordance module
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function concordance_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_OTHER;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return false;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;

        default:
            return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 *
 * @param array $data the data submitted from the reset course.
 * @return array status array
 */
function concordance_reset_userdata($data) {
    return array();
}

/**
 * Add concordance instance.
 *
 * @param object $data
 * @param object $mform
 * @return int new concordance instance id
 */
function concordance_add_instance($data, $mform) {
    global $DB;

    $cmid = $data->coursemodule;
    $context = context_module::instance($cmid);
    // Prevent teacher to change concordance visibility.
    $id = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
    assign_capability('moodle/course:activityvisibility', CAP_PROHIBIT, $id, $context->id, true);
    $id = $DB->get_field('role', 'id', array('shortname' => 'associateeditingteacher'));
    assign_capability('moodle/course:activityvisibility', CAP_PROHIBIT, $id, $context->id, true);

    if ($draftitemid = $data->descriptionpanelisteditor['itemid']) {
        $data->descriptionpanelist = file_save_draft_area_files($draftitemid, $context->id, 'mod_concordance',
                'descriptionpanelist', 0, concordance_get_editor_options($context), $data->descriptionpanelisteditor['text']);
        $data->descriptionpanelistformat = $data->descriptionpanelisteditor['format'];
    }

    if ($draftitemid = $data->descriptionstudenteditor['itemid']) {
        $data->descriptionstudent = file_save_draft_area_files($draftitemid, $context->id, 'mod_concordance', 'descriptionstudent',
                0, concordance_get_editor_options($context), $data->descriptionstudenteditor['text']);
        $data->descriptionstudentformat = $data->descriptionstudenteditor['format'];
    }

    $data->id = $DB->insert_record('concordance', $data);
    // Create course for panelists.
    $data->coursegenerated = generate_course_for_panelists($data);
    $DB->update_record('concordance', $data);

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $data->id, array('id' => $cmid));

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'concordance', $data->id, $completiontimeexpected);

    return $data->id;
}

/**
 * Generate course for panelists.
 *
 * @param object $data
 * @return int course id
 */
function generate_course_for_panelists($data) {
    global $DB;
    $categoryid = get_config('mod_concordance', 'categorypanelcourses');
    $course = new \stdClass();
    $course->category = $categoryid;
    $course->visible = 1;
    $identifier = $data->course . '-' . $data->id;
    $i = 1;
    $shortname = $identifier;
    while ($found = $DB->record_exists('course', array('shortname' => $shortname))) {
        $shortname = $identifier . '(' . $i . ')';
        $i++;
    }
    $course->shortname = $shortname;
    $course->fullname = $shortname;

    $course = create_course($course);
    return intval($course->id);
}

/**
 * Update concordance instance.
 *
 * @param object $data
 * @param object $mform
 * @return bool true
 */
function concordance_update_instance($data, $mform) {
    global $CFG, $DB;

    $cmid        = $data->coursemodule;

    $data->timemodified = time();
    $data->id           = $data->instance;

    $context = context_module::instance($cmid);
    if ($draftitemid = $data->descriptionpanelisteditor['itemid']) {
        $data->descriptionpanelist = file_save_draft_area_files($draftitemid, $context->id, 'mod_concordance',
                'descriptionpanelist', 0, concordance_get_editor_options($context), $data->descriptionpanelisteditor['text']);
        $data->descriptionpanelistformat = $data->descriptionpanelisteditor['format'];
    }

    if ($draftitemid = $data->descriptionstudenteditor['itemid']) {
        $data->descriptionstudent = file_save_draft_area_files($draftitemid, $context->id, 'mod_concordance', 'descriptionstudent',
                0, concordance_get_editor_options($context), $data->descriptionstudenteditor['text']);
        $data->descriptionstudentformat = $data->descriptionstudenteditor['format'];
    }

    $DB->update_record('concordance', $data);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'concordance', $data->id, $completiontimeexpected);

    return true;
}

/**
 * Delete concordance instance.
 *
 * @param int $id
 * @return bool true
 */
function concordance_delete_instance($id) {
    global $DB;

    if (!$concordance = $DB->get_record('concordance', array('id' => $id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('concordance', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'concordance', $concordance->id, null);

    $concordancepersistence = new \mod_concordance\concordance($id);
    $concordancepersistence->delete();
    // Delete course for panelists.
    if ($concordancepersistence->get('coursegenerated')) {
        delete_course($concordance->coursegenerated, false);
        // Update course count in categories.
        fix_course_sortorder();
    }

    return true;
}

/**
 * Given a coursemodule object, this function returns the extra
 * information needed to print this activity in various places.
 *
 * @param cm_info $cm
 * @return cached_cm_info info
 */
function concordance_get_coursemodule_info($cm) {
    global $DB;
    if (!($concordance = $DB->get_record('concordance', array('id' => $cm->instance),
            'id, name'))) {
        return null;
    }
    $cminfo = new cached_cm_info();
    $cminfo->name = $concordance->name;
    $cminfo->customdata = null;
    return $cminfo;
}

/**
 * Sets dynamic information about a course module
 *
 * This function is called from cm_info when displaying the module
 * mod_concordance can be displayed inline on course page and therefore have no course link
 *
 * @param cm_info $cm
 */
function concordance_cm_info_dynamic(cm_info $cm) {
    if ($cm->customdata) {
        // The field 'customdata' is not empty IF AND ONLY IF we display contens inline.
        $cm->set_no_view_link();
    }
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $concordance     concordance object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 */
function concordance_view($concordance, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $concordance->id
    );

    $event = \mod_concordance\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('concordance', $concordance);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Returns the option for the editor to use in the activity parameters form.
 * @param context $context
 * @return array
 */
function concordance_get_editor_options($context) {
    global $CFG;
    return array('subdirs' => 1, 'maxbytes' => $CFG->maxbytes, 'maxfiles' => -1, 'changeformat' => 1, 'context' => $context,
        'noclean' => 1, 'trusttext' => 0);
}

/**
 * Serve the files.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function mod_concordance_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    // Only serve the file if the user can access the course and course module.
    require_login($course, false, $cm);

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_concordance/$filearea/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
