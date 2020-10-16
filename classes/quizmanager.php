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
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
require_once($CFG->dirroot . '/lib/adminlib.php');
require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
require_once($CFG->dirroot . '/mod/quiz/accessmanager.php');

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
     * @return obj The new quiz object from the DB.
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

            $concordancem = get_coursemodule_from_instance('concordance',
                    $concordance->get('id'), $concordance->get('course'), true, MUST_EXIST);
            $context = \context_module::instance($concordancem->id);
            $newcm = self::duplicate_module($course, $cm);
            set_coursemodule_visible($newcm->id, 1);
            $concordance->set('cmgenerated', $newcm->id);

            $quiz = $DB->get_record('quiz', array('id' => $newcm->instance), '*', MUST_EXIST);
            $quiz->browsersecurity = 'securewindow';

            // The panelists should see no feedback.
            $quiz->preferredbehaviour = 'deferredfeedback';
            foreach (\mod_quiz_admin_review_setting::fields() as $field => $name) {
                $quiz->{'review'.$field} = 0;
            }

            // Move files if exists.
            $newcontext = \context_module::instance($newcm->id);
            $newfilerecord = array('contextid' => $newcontext->id, 'component' => 'mod_quiz', 'filearea' => 'intro', 'itemid' => 0);
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($context->id, 'mod_concordance', 'descriptionpanelist', 0)) {
                foreach ($files as $file) {
                    $draftfile = $fs->create_file_from_storedfile($newfilerecord, $file);
                }
            }
            $quiz->intro = is_null($concordance->get('descriptionpanelist')) ? '' : $concordance->get('descriptionpanelist');
            $DB->update_record('quiz', $quiz);

            return $quiz;
        }
    }

    /**
     * Duplicate the panelist quiz so it can be used by the students.
     *
     * @param concordance $concordance Concordance persistence object.
     * @param object $formdata The data submitted by the form.
     * @return int the new cm id generated
     */
    public static function duplicatequizforstudents($concordance, $formdata) {
        global $DB;
        $hasquestions = isset($formdata->questionstoinclude) && !empty($formdata->questionstoinclude);
        // Duplicate the panelist quiz in the students course and make it hidden.
        if (!is_null($concordance->get('cmgenerated')) && $hasquestions) {
            $cm = get_coursemodule_from_id('', $concordance->get('cmgenerated'), 0, true, MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $concordance->get('course')), '*', MUST_EXIST);

            $concordancem = get_coursemodule_from_instance('concordance',
                    $concordance->get('id'), $concordance->get('course'), true, MUST_EXIST);
            $context = \context_module::instance($concordancem->id);
            // Before duplicate, update stamp and version fields for questions.
            $quizpanelist = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);
            $coursepanelist = $DB->get_record('course', array('id' => $concordance->get('coursegenerated')), '*', MUST_EXIST);
            self::updatestampandversionquestions($quizpanelist, $cm, $coursepanelist);
            $newcm = self::duplicate_module($course, $cm, $concordance->get('coursegenerated'));
            set_coursemodule_visible($newcm->id, 0);

            $quiz = $DB->get_record('quiz', array('id' => $newcm->instance), '*', MUST_EXIST);
            // Move files if exists.
            $newcontext = \context_module::instance($newcm->id);
            $newfilerecord = array('contextid' => $newcontext->id, 'component' => 'mod_quiz', 'filearea' => 'intro', 'itemid' => 0);
            $fs = get_file_storage();
            // Delete any file related to intro filearea.
            $fs->delete_area_files($newcontext->id, 'mod_quiz', 'intro');
            if ($files = $fs->get_area_files($context->id, 'mod_concordance', 'descriptionstudent', 0)) {
                foreach ($files as $file) {
                    $draftfile = $fs->create_file_from_storedfile($newfilerecord, $file);
                }
            }
            $quiz->intro = $concordance->get('descriptionstudent');
            $quiz->browsersecurity = '-';

            // The students should see the feedbacks immediatly.
            $quiz->preferredbehaviour = 'immediatefeedback';
            foreach (\mod_quiz_admin_review_setting::fields() as $field => $name) {
                $default = \mod_quiz_admin_review_setting::all_on();
                $quiz->{'review'.$field} = $default;
            }

            $DB->update_record('quiz', $quiz);

            // Move to the right section.
            $section = $DB->get_record('course_sections',
                    array('course' => $concordance->get('course'), 'section' => $concordancem->sectionnum));
            moveto_module($newcm, $section);
            $quizobj = new \quiz($quiz, $newcm, $course);
            $quizobj->preload_questions();
            $quizobj->load_questions();
            $questions = $quizobj->get_questions();
            $structure = $quizobj->get_structure();
            $removed = false;
            foreach ($questions as $question) {
                if (!key_exists($question->slot, $formdata->questionstoinclude)) {
                    // Remove question.
                    if (!$slot = $DB->get_record('quiz_slots', array('quizid' => $quiz->id, 'id' => $question->slotid))) {
                        throw new moodle_exception('Bad slot ID '.$question->slotid);
                    }
                    $structure->remove_slot($slot->slot);
                    $removed = true;
                }
            }
            if ($removed) {
                quiz_delete_previews($quiz);
                quiz_update_sumgrades($quiz);
            }

            // Compile the answers for panelists and questions included.
            self::compileanswers($cm, $quizpanelist, $coursepanelist, $quizobj, $formdata);
            return $newcm->id;
        }
        return null;
    }

    /**
     * Api to duplicate a module. This was copied from duplicate_module in course/lib.php.
     * This copy is necessary because the core function does not allow to duplicate in a different course.
     * The differences with the original function have a "Concordance modification : " comment just above them.
     *
     * @param object $course course object.
     * @param object $cm course module object to be duplicated.
     * @param int    $courseidavoidcap the course on which we want to avoid the capability check.
     *
     * @throws Exception
     * @throws coding_exception
     * @throws moodle_exception
     * @throws restore_controller_exception
     *
     * @return cm_info|null cminfo object if we sucessfully duplicated the mod and found the new cm.
     */
    private static function duplicate_module($course, $cm, $courseidavoidcap = null) {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/filelib.php');

        // Concordance modification : Temporarily enrol teacher in panelist course to avoid capability errors.
        if (!$courseidavoidcap) {
            $courseidavoidcap = $course->id;
        }
        $enrolplugin = enrol_get_plugin('manual');
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'), MUST_EXIST);
        $contextcoursecap = context_course::instance($courseidavoidcap);
        $isenrolled = user_has_role_assignment($USER->id, $roleid, $contextcoursecap->id);
        if (!$isenrolled) {
            $instances = $DB->get_records('enrol',
                    array('courseid' => $courseidavoidcap, 'enrol' => 'manual'));
            $enrolinstance = reset($instances);
            $enrolplugin->enrol_user($enrolinstance, $USER->id, $roleid, 0, 0, null, false);
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

    /**
     * Compile the answers from the panelists in the feedback for each choice of answer.
     *
     * @param stdClass $quizanswered The course module for the quiz answered by the panelists.
     * @param stdClass $quizpanelist The quiz object from the DB (for the panelist).
     * @param stdClass $coursepanelist The course object from the DB (for the panelist quiz).
     * @param Quiz $newquiz The new quiz (for the students).
     * @param array $formdata An array of data submitted by the 'Generate student quiz' form.
     */
    private static function compileanswers($quizanswered, $quizpanelist, $coursepanelist, $newquiz, $formdata) {
        global $DB;

        $params = array();
        $conditions = ' AND state = :state';
        $params['quizid'] = $quizanswered->instance;
        $params['state'] = \quiz_attempt::FINISHED;

        $attempts = $DB->get_records_select('quiz_attempts',
            "quiz = :quizid " . $conditions,
            $params, 'quiz, userid, timestart DESC');

        $combinedanswers = array();
        $previoususerid = -1;
        foreach ($attempts as $attempt) {
            $panelist = panelist::get_record(array('userid' => $attempt->userid));
            // Consider this attempt only if it is the last one for this panelist and this panelist has to be included.
            if ($attempt->userid != $previoususerid && !empty($panelist)
                    && isset($formdata->paneliststoinclude[$panelist->get('id')])) {
                $previoususerid = $attempt->userid;
                $quizattempt = new \quiz_attempt($attempt, $quizpanelist, $quizanswered, $coursepanelist);
                $slots = $quizattempt->get_slots();
                foreach ($slots as $slot) {
                    if (!key_exists($slot, $formdata->questionstoinclude)) {
                        continue;
                    }
                    $questionattempt = $quizattempt->get_question_attempt($slot);
                    $question = $questionattempt->get_question();
                    if ($question instanceof \qtype_tcs_question) {
                        $qtdata = $questionattempt->get_last_qt_data();
                        if (isset($qtdata['answer'])) {
                            $qtchoiceorder = $qtdata['answer'];

                            if (!isset($combinedanswers[$slot])) {
                                $combinedanswers[$slot] = array();
                            }
                            if (!isset($combinedanswers[$slot][$qtchoiceorder])) {
                                $combinedanswers[$slot][$qtchoiceorder] = array('nbexperts' => 0, 'feedback' => '');
                            }

                            $combinedanswers[$slot][$qtchoiceorder]['nbexperts']++;

                            $panelistname = $panelist->get('firstname').' '.$panelist->get('lastname');
                            $namebl = \html_writer::tag('strong', $panelistname.'&nbsp;:');
                            $combinedanswers[$slot][$qtchoiceorder]['feedback'] .= \html_writer::tag('p', $namebl);
                            $combinedanswers[$slot][$qtchoiceorder]['feedback'] .= \html_writer::tag('p',
                                $qtdata['answerfeedback']);
                        }
                    }
                }

            }
        }

        // Save the combined feedbacks and number of experts in the new quiz questions.
        $questions = $newquiz->get_questions();
        foreach ($questions as $question) {
            if (!key_exists($question->slot, $formdata->questionstoinclude)) {
                continue;
            }
            $answerorder = 0; // Order of answers begin at 0.
            foreach ($question->options->answers as $answer) {
                if (isset($combinedanswers[$question->slot][$answerorder])) {
                    $answer->fraction = $combinedanswers[$question->slot][$answerorder]['nbexperts'];
                    $answer->feedback = $combinedanswers[$question->slot][$answerorder]['feedback'];
                } else {
                    // Empty this answer.
                    $answer->fraction = 0;
                    $answer->feedback = '';
                }
                $DB->update_record('question_answers', $answer);
                $answerorder++;
            }
            // Important to notify that the question was edited or the changes will not be visible.
            \question_bank::notify_question_edited($question->id);
        }
    }

    /**
     * Get users who have attempted the quiz.
     *
     * @param concordance $concordance Concordance persistence object.
     * @return array Array of users id.
     */
    public static function getusersattemptedquiz($concordance) {
        global $DB;
        if (empty($concordance->get('cmgenerated'))) {
            return array();
        }
        $cm = get_coursemodule_from_id('', $concordance->get('cmgenerated'), 0, true, MUST_EXIST);
        $quiz = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);
        $params['quizid'] = $quiz->id;
        $query = "SELECT t1.userid AS userid, t1.state as state
                    FROM {quiz_attempts} t1
                   WHERE t1.timestart = (SELECT MAX(t2.timestart)
                                     FROM {quiz_attempts} t2
                                     WHERE t2.userid = t1.userid)
                         AND t1.quiz = :quizid";
        return $DB->get_records_sql($query, $params);
    }

    /**
     * Get structure from panelist quiz.
     *
     * @param concordance $concordance Concordance persistence object.
     * @return \mod_quiz\structure Quiz structure.
     */
    public static function getquizstructure($concordance) {
        global $DB;
        if (empty($concordance->get('cmgenerated'))) {
            return array();
        }
        $coursegeneratedid = $concordance->get('coursegenerated');
        $course = $DB->get_record('course', array('id' => $coursegeneratedid), '*', MUST_EXIST);
        $cm = get_coursemodule_from_id('', $concordance->get('cmgenerated'), 0, true, MUST_EXIST);
        $quizobjet = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);
        $quiz = new \quiz($quizobjet, $cm, $course);
        return $quiz->get_structure();
    }

    /**
     * Update version and stamp fields for quiz questions.
     *
     * @param stdClass $quizobjet Quiz
     * @param stdClass $cm   Course module
     * @param stdClass $course Course
     */
    private static function updatestampandversionquestions($quizobjet, $cm, $course) {
        global $DB;
        $quiz = new \quiz($quizobjet, $cm, $course);
        $quiz->preload_questions();
        $quiz->load_questions();
        $questions = $quiz->get_questions();
        foreach ($questions as $question) {
            $q = $DB->get_record('question', array('id' => $question->id), '*', MUST_EXIST);
            $q->stamp = make_unique_id_code();
            $q->version = make_unique_id_code();
            $q->timemodified = time();
            $DB->update_record('question', $q);
        }
    }

}