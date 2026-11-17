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

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/lib/adminlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use stdClass;
use moodle_exception;
use backup_controller;
use restore_controller;
use backup;
use cm_info;
use context_module;
use context_course;
use mod_quiz\quiz_settings;
use mod_quiz\quiz_attempt;
use mod_quiz\admin\review_setting;
use question_definition;
use mod_quiz\structure;
use progress_bar;

require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * Class for quiz management.
 *
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Lévesque <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_manager {
    /**
     * Value for formative quiz types.
     *
     * @var int
     */
    const CONCORDANCE_QUIZTYPE_FORMATIVE = 1;

    /**
     * Value for summative with feedback quiz types.
     *
     * @var int
     */
    const CONCORDANCE_QUIZTYPE_SUMMATIVE_WITHFEEDBACK = 2;

    /**
     * Value for summative without feedback quiz types.
     *
     * @var int
     */
    const CONCORDANCE_QUIZTYPE_SUMMATIVE_WITHOUTFEEDBACK = 3;

    /**
     * Form data provided when duplicating a quiz for the students
     *
     * @var stdClass
     */
    private ?stdClass $formdata = null;

    /**
     * Progress bar
     *
     * @var progress_bar
     */
    private $progressbar;

    /**
     * Constructor
     *
     * @param concordance $concordance Concordance object
     */
    public function __construct(
        /** @var concordance */
        protected concordance $concordance
    ) {
    }

    /**
     * Duplicate the origin quiz so it can be used by the panelists.
     *
     * @param bool $async True to delete the old quiz async, false otherwise (usually false only for test purpose).
     * @return object $quiz The new quiz object from the DB.
     */
    public function duplicate_quiz_for_panelists($async = true) {
        global $DB;

        // If a quiz for panelists was already generated, delete it.
        if ($this->concordance->is_not_null('cmgenerated')) {
            course_delete_module($this->concordance->get('cmgenerated'), $async);
            $this->concordance->set('cmgenerated', null);
        }

        // If an origin quiz was selected, duplicate it in the panelists' course, make it visible and save it as 'cmgenerated'.
        if ($this->concordance->is_not_null('cmorigin')) {
            if (!PHPUNIT_TEST) {
                $this->update_progress_bar(10);
            }
            $cm = get_coursemodule_from_id('', $this->concordance->get('cmorigin'), 0, true, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $this->concordance->get('coursegenerated')], '*', MUST_EXIST);
            $context = \context_module::instance($this->get_concordance_module()->id);

            $newcm = $this->duplicate_module_for_panelists($course, $cm);
            set_coursemodule_visible($newcm->id, 1);
            $this->concordance->set('cmgenerated', $newcm->id);

            // Remove the selected quiz from the gradebook.
            $originquiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
            $originquiz->instance = $originquiz->id;
            $quizobject = new quiz_settings($originquiz, $cm, $course);
            $quizcalculator = $quizobject->get_grade_calculator();
            $quizcalculator->update_quiz_maximum_grade(0);
            $quizcalculator->recompute_all_final_grades();
            quiz_update_grades($originquiz, 0, true);
            // Change some params in the quiz.
            $quiz = $DB->get_record('quiz', ['id' => $newcm->instance], '*', MUST_EXIST);
            $quiz->browsersecurity = 'securewindow';
            $quiz->attempts = 1;

            // The panelists should see no feedback, but can review their attempts.
            $quiz->preferredbehaviour = 'deferredfeedback';
            foreach (review_setting::fields() as $field => $name) {
                if ($field == 'attempt') {
                    $quiz->{'review' . $field} = review_setting::all_on();
                } else {
                    $quiz->{'review' . $field} = 0;
                }
            }
            // Move files if exists.
            $newcontext = \context_module::instance($newcm->id);
            $newfilerecord = ['contextid' => $newcontext->id, 'component' => 'mod_quiz', 'filearea' => 'intro', 'itemid' => 0];
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($context->id, 'mod_concordance', 'descriptionpanelist', 0)) {
                foreach ($files as $file) {
                    $fs->create_file_from_storedfile($newfilerecord, $file);
                }
            }
            $quiz->intro = $this->concordance->is_not_null('descriptionpanelist')
                ? $this->concordance->get('descriptionpanelist')
                : '';
            $DB->update_record('quiz', $quiz);
            if (!PHPUNIT_TEST) {
                $this->update_progress_bar(100);
            }
            return $quiz;
        }
    }

    /**
     * Duplicate the panelist quiz so it can be used by the students.
     *
     * @param object $formdata The data submitted by the form.
     * @return int the new cm id generated
     */
    public function duplicate_quiz_for_students(object $formdata) {
        global $DB;
        $this->set_form_data($formdata);
        // Duplicate the panelist quiz in the students course and make it hidden.
        if ($this->concordance->is_not_null('cmgenerated') && $this->form_has_questions()) {
            if (!PHPUNIT_TEST) {
                $this->update_progress_bar(25);
            }
            $cm = get_coursemodule_from_id('', $this->concordance->get('cmgenerated'), 0, true, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $this->concordance->get('course')], '*', MUST_EXIST);
            $concordancem = $this->get_concordance_module();
            $context = context_module::instance($concordancem->id);

            $newcm = $this->duplicate_module_for_students($course);
            if (!PHPUNIT_TEST) {
                $this->update_progress_bar(50);
            }

            $quiz = $DB->get_record('quiz', ['id' => $newcm->instance], '*', MUST_EXIST);
            // Move files if exists.
            $newcontext = context_module::instance($newcm->id);
            $newfilerecord = ['contextid' => $newcontext->id, 'component' => 'mod_quiz', 'filearea' => 'intro', 'itemid' => 0];
            $fs = get_file_storage();
            // Delete any file related to intro filearea.
            $fs->delete_area_files($newcontext->id, 'mod_quiz', 'intro');
            if ($files = $fs->get_area_files($context->id, 'mod_concordance', 'descriptionstudent', 0)) {
                foreach ($files as $file) {
                    $fs->create_file_from_storedfile($newfilerecord, $file);
                }
            }
            $quiz->intro = $this->concordance->get('descriptionstudent');
            $quiz->browsersecurity = '-';

            // Feedback options, depending on the type of quiz.
            if ($this->is_summative()) {
                // Show deferred feedback.
                $quiz->preferredbehaviour = 'deferredfeedback';
                foreach (review_setting::fields() as $field => $name) {
                    if ($this->formdata->quiztype == self::CONCORDANCE_QUIZTYPE_SUMMATIVE_WITHFEEDBACK) {
                        // Not during the attempt, but all other options ON.
                        $default = review_setting::IMMEDIATELY_AFTER
                            | review_setting::LATER_WHILE_OPEN
                            | review_setting::AFTER_CLOSE;
                    } else {
                        // No feedback at all.
                        $default = 0;
                    }
                    $quiz->{'review' . $field} = $default;
                }
            } else {
                // Show immediate feedback during the attempt.
                $quiz->preferredbehaviour = 'immediatefeedback';
                foreach (review_setting::fields() as $field => $name) {
                    $default = review_setting::all_on();
                    $quiz->{'review' . $field} = $default;
                }
            }

            // Include bibliographies in introduction.
            if ($this->include_biography()) {
                $biographies = '';
                foreach ($this->formdata->paneliststoinclude as $id) {
                    $panelist = panelist::get_record(['id' => $id]);
                    if ($panelist && $panelist->get('bibliography')) {
                        $biographies .= \html_writer::empty_tag('br');
                        $biographies .= \html_writer::tag('h4', $panelist->get('firstname') . ' ' . $panelist->get('lastname'));
                        $biographies .= $panelist->get('bibliography');
                        if ($files = $fs->get_area_files($context->id, 'mod_concordance', 'bibliography', $id)) {
                            foreach ($files as $file) {
                                $fs->create_file_from_storedfile($newfilerecord, $file);
                            }
                        }
                    }
                }
                if (!empty($biographies)) {
                    $bibhtml = \html_writer::tag('h3', get_string('bibliographiespanelists', 'mod_concordance')) . $biographies;
                    $quiz->intro .= $bibhtml;
                }
            }
            if (!PHPUNIT_TEST) {
                $this->update_progress_bar(75);
            }

            // Set quiz name.
            if (isset($this->formdata->name) && !empty($this->formdata->name)) {
                $quiz->name = $this->formdata->name;
            }

            $DB->update_record('quiz', $quiz);

            // Gradebook : if summative quiz, add the student quiz to the gradebook ; otherwise (formative quiz) remove it.
            $quizconfig = get_config('quiz');
            $quiz->instance = $quiz->id;
            $quizobj = new quiz_settings($quiz, $newcm, $course);
            $quizcalculator = $quizobj->get_grade_calculator();
            if ($this->is_summative()) {
                $quizcalculator->update_quiz_maximum_grade($quizconfig->maximumgrade);
            } else {
                $quizcalculator->update_quiz_maximum_grade(0);
            }
            $quizcalculator->recompute_all_final_grades();
            quiz_update_grades($quiz, 0, true);

            $this->move_under_the_concordance_module($newcm, $concordancem->sectionnum);
            // Compile the answers for panelists and questions included.
            $this->compile_answers($cm, $quizobj);
            if (!PHPUNIT_TEST) {
                $this->update_progress_bar(100);
            }
            return $newcm->id;
        }
        return null;
    }

    /**
     * Move the new module under the concordance module.
     *
     * @param [type] $newcm
     * @param [type] $sectionnum
     * @return void
     */
    private function move_under_the_concordance_module($newcm, $sectionnum): void {
        global $DB;
        // Move the quiz to the right section.
        $section = $DB->get_record(
            'course_sections',
            ['course' => $this->concordance->get('course'), 'section' => $sectionnum]
        );
        moveto_module($newcm, $section);
    }

    /**
     * Generate the student quiz
     * Duplicate the quiz from the original quiz
     * Then duplicate the questions from the panelist quiz
     *
     * @param stdClass $course
     * @return cm_info|null
     */
    private function duplicate_module_for_students($course): ?cm_info {
        $originmodule = get_coursemodule_from_id('', $this->concordance->get('cmorigin'), 0, true, MUST_EXIST);
        // Create the module for the student based on the original module!
        $newcm = duplicate_module($course, $originmodule, null, false);
        set_coursemodule_visible($newcm->id, 0);

        $this->duplicate_questions_from_panelist_course(
            $this->get_quiz_settings($course->id, $newcm->id),
            $this->get_quiz_settings($this->concordance->get('coursegenerated'), $this->concordance->get('cmgenerated'))
        );
        return $newcm;
    }

    /**
     * Verify if summative is requested based on the form provided
     *
     * @return bool
     */
    private function is_summative(): bool {
        return isset($this->formdata->quiztype)
            && ($this->formdata->quiztype == self::CONCORDANCE_QUIZTYPE_SUMMATIVE_WITHOUTFEEDBACK
                || $this->formdata->quiztype == self::CONCORDANCE_QUIZTYPE_SUMMATIVE_WITHFEEDBACK);
    }

    /**
     * Get the concordance module
     *
     * @return stdClass
     */
    private function get_concordance_module(): stdClass {
        return get_coursemodule_from_instance(
            'concordance',
            $this->concordance->get('id'),
            $this->concordance->get('course'),
            true,
            MUST_EXIST
        );
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
    private function duplicate_module_for_panelists($course, $cm) {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->libdir . '/filelib.php');

        // Concordance modification : Temporarily enrol teacher in panelist course to avoid capability errors.
        $courseidavoidcap = $course->id;
        $enrolplugin = enrol_get_plugin('manual');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $contextcoursecap = context_course::instance($courseidavoidcap);
        $cmcontext = context_module::instance($cm->id);
        $isenrolled = user_has_role_assignment($USER->id, $roleid, $contextcoursecap->id);
        if (!$isenrolled) {
            $instances = $DB->get_records(
                'enrol',
                ['courseid' => $courseidavoidcap, 'enrol' => 'manual']
            );
            $enrolinstance = reset($instances);
            $enrolplugin->enrol_user($enrolinstance, $USER->id, $roleid, 0, 0, null, false);
        }

        $a = new stdClass();
        $a->modtype = get_string('modulename', $cm->modname);
        $a->modname = format_string($cm->name);

        if (!plugin_supports('mod', $cm->modname, FEATURE_BACKUP_MOODLE2)) {
            throw new moodle_exception('duplicatenosupport', 'error', '', $a);
        }
        if (!PHPUNIT_TEST) {
            $this->update_progress_bar(20);
        }
        // Backup the activity.

        $bc = new backup_controller(
            backup::TYPE_1ACTIVITY,
            $cm->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );

        $backupid = $bc->get_backupid();
        $backupbasepath = $bc->get_plan()->get_basepath();

        $bc->execute_plan();

        $bc->destroy();
        if (!PHPUNIT_TEST) {
            $this->update_progress_bar(30);
        }
        // Restore the backup immediately.
        $rc = new restore_controller(
            $backupid,
            $course->id,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id,
            backup::TARGET_CURRENT_ADDING
        );

        // Make sure that the restore_general_groups setting is always enabled when duplicating an activity.
        $plan = $rc->get_plan();
        $groupsetting = $plan->get_setting('groups');
        if (!PHPUNIT_TEST) {
            $this->update_progress_bar(40);
        }
        if (empty($groupsetting->get_value())) {
            $groupsetting->set_value(true);
        }

        if (!$rc->execute_precheck()) {
            $precheckresults = $rc->get_precheck_results();
            if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                if (empty($CFG->keeptempdirectoriesonbackup)) {
                    fulldelete($backupbasepath);
                }
            }
        }
        if (!PHPUNIT_TEST) {
            $this->update_progress_bar(50);
        }
        $rc->execute_plan();
        if (!PHPUNIT_TEST) {
            $this->update_progress_bar(60);
        }

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
        if (!PHPUNIT_TEST) {
            $this->update_progress_bar(70);
        }

        if (empty($CFG->keeptempdirectoriesonbackup)) {
            fulldelete($backupbasepath);
        }
        if (!PHPUNIT_TEST) {
            $this->update_progress_bar(80);
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
        if (!PHPUNIT_TEST) {
            $this->update_progress_bar(90);
        }

        return isset($newcm) ? $newcm : null;
    }

    /**
     * Compile the answers from the panelists in the feedback for each choice of answer.
     *
     * @param stdClass $quizanswered The course module for the quiz answered by the panelists.
     * @param quiz_settings $newquiz The new quiz (for the students).
     */
    private function compile_answers($quizanswered, quiz_settings $newquiz) {
        global $DB;
        $quizpanelist = $DB->get_record('quiz', ['id' => $quizanswered->instance], '*', MUST_EXIST);
        $coursepanelist = $DB->get_record('course', ['id' => $quizanswered->course], '*', MUST_EXIST);
        $params = [];
        $params['quizid'] = $quizanswered->instance;

        $attempts = $DB->get_records_select(
            'quiz_attempts',
            "quiz = :quizid ",
            $params,
            'quiz, userid, timestart DESC'
        );

        $combinedanswers = [];
        $previoususerid = -1;
        $drawings = [];
        $generalfeedbacks = [];
        foreach ($attempts as $attempt) {
            $panelist = panelist::get_record(['userid' => $attempt->userid]);
            // Consider this attempt only if it is the last one for this panelist and this panelist has to be included.
            if (
                $attempt->userid != $previoususerid && !empty($panelist)
                && in_array((int)$panelist->get('id'), $this->formdata->paneliststoinclude)
            ) {
                $previoususerid = $attempt->userid;
                $quizattempt = new quiz_attempt($attempt, $quizpanelist, $quizanswered, $coursepanelist);
                $slots = $quizattempt->get_slots();
                foreach ($slots as $slot) {
                    if (!$this->question_should_be_included($slot)) {
                        continue;
                    }
                    $questionattempt = $quizattempt->get_question_attempt($slot);
                    $question = $questionattempt->get_question(false);
                    if ($this->is_tcs_question($question)) {
                        $qtdata = $questionattempt->get_last_qt_data();
                        if ($this->is_tcs_perception_question($question)) {
                            $answer = $this->get_perception_combined_answers($qtdata, $slot, $panelist, $combinedanswers);
                            // Get drawing panelist.
                            $files = $question->get_image_for_files();
                            $drawings = $this->get_perception_combined_drawings($qtdata, $slot, $panelist, $drawings, $files);
                            // Get general feedbacks.
                            if (isset($qtdata['generalcomment'])) {
                                if (!isset($combinedanswers[$slot])) {
                                    $generalfeedbacks[$slot] = [];
                                }
                                $panelistname = $panelist->get('firstname') . ' ' . $panelist->get('lastname');
                                if (!empty($panelistname) && !empty($qtdata['generalcomment'])) {
                                    $panelistname .= '&nbsp;:';
                                }
                                $namebl = \html_writer::tag('strong', $panelistname);
                                if (!isset($generalfeedbacks[$slot]['generalcomment'])) {
                                    $generalfeedbacks[$slot]['generalcomment'] = '';
                                }
                                $generalfeedbacks[$slot]['generalcomment'] .= $namebl;
                                $generalfeedbacks[$slot]['generalcomment'] .= $qtdata['generalcomment'];
                                $generalfeedbacks[$slot]['generalcomment'] .= '<hr>';
                            }
                        } else {
                            $answer = $this->get_tcs_combined_answers($qtdata, $slot, $panelist, $combinedanswers);
                        }
                        if (!empty($answer)) {
                            $combinedanswers = $answer;
                        }
                    }
                }
            }
        }

        // Save the combined feedbacks and number of experts in the new quiz questions.
        $questions = $this->get_questions_from_quiz($newquiz);
        foreach ($questions as $question) {
            $q = \question_bank::make_question($question);
            if (!$this->is_tcs_question($q)) {
                continue;
            }
            if (isset($question->options->showoutsidefieldcompetence)) {
                $tcstype = $question->qtype;
                $tablequestionoption = 'qtype_' . $tcstype . '_options';
                $qorecord = $DB->get_record($tablequestionoption, ['id' => $question->options->id], '*', MUST_EXIST);
                $qorecord->showoutsidefieldcompetence = 0;
                $DB->update_record($tablequestionoption, $qorecord);
            }
            if (isset($question->options->answers)) {
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
            if ($this->is_tcs_perception_question($q)) {
                $slotmapped = $this->formdata->questionstoinclude[$question->slot];
                foreach ($drawings[$slotmapped] as $key => $drawing) {
                    $drawingobject = new \stdClass();
                    $drawingobject->questionid = $question->id;
                    $drawingobject->answer = $this->build_full_svg($drawing);
                    $drawingobject->image = $key;
                    $drawingobject->othervalues = json_encode($drawing['othervalues']);
                    $this->insert_or_update_perception_answer($drawingobject);
                }
                // Update general feedback.
                if (isset($generalfeedbacks[$question->slot]['generalcomment'])) {
                    $data = (object) [
                        'id' => $question->id,
                        'generalfeedback' => $generalfeedbacks[$question->slot]['generalcomment'],
                    ];
                    $DB->update_record('question', $data, true);
                }
            }
        }
    }

    /**
     * Get combined answers for tcs and tcsjudgment questions
     *
     * @param array $qtdata
     * @param number $slot
     * @param object $panelist
     * @param array $combinedanswers
     * @return array
     */
    protected function get_tcs_combined_answers($qtdata, $slot, $panelist, $combinedanswers): array {
        if (!empty($qtdata['outsidefieldcompetence']) && intval($qtdata['outsidefieldcompetence']) === 1) {
            return [];
        }
        if (isset($qtdata['answer'])) {
            $qtchoiceorder = $qtdata['answer'];

            if (!isset($combinedanswers[$slot])) {
                $combinedanswers[$slot] = [];
            }
            if (!isset($combinedanswers[$slot][$qtchoiceorder])) {
                $combinedanswers[$slot][$qtchoiceorder] = ['nbexperts' => 0, 'feedback' => ''];
            }

            $combinedanswers[$slot][$qtchoiceorder]['nbexperts']++;

            $panelistname = $panelist->get('firstname') . ' ' . $panelist->get('lastname');
            if (!empty($panelistname) && !empty($qtdata['answerfeedback'])) {
                $panelistname .= '&nbsp;:';
            }
            $namebl = \html_writer::tag('strong', $panelistname);
            $combinedanswers[$slot][$qtchoiceorder]['feedback'] .= \html_writer::tag('p', $namebl);
            if (!empty($qtdata['answerfeedback'])) {
                $combinedanswers[$slot][$qtchoiceorder]['feedback'] .= \html_writer::tag(
                    'p',
                    $qtdata['answerfeedback']
                );
            }
            return $combinedanswers;
        }
        return [];
    }

    /**
     * Get combined answers for tcs perception questions
     *
     * @param array $qtdata
     * @param number $slot
     * @param object $panelist
     * @param array $combinedanswers
     * @return array
     */
    protected function get_perception_combined_answers($qtdata, $slot, $panelist, $combinedanswers): array {
        if (isset($qtdata['answermultiplechoice'])) {
            $qtchoiceorder = $qtdata['answermultiplechoice'];

            if (!isset($combinedanswers[$slot])) {
                $combinedanswers[$slot] = [];
            }
            if (!isset($combinedanswers[$slot][$qtchoiceorder])) {
                $combinedanswers[$slot][$qtchoiceorder] = ['nbexperts' => 0, 'feedback' => ''];
            }

            $combinedanswers[$slot][$qtchoiceorder]['nbexperts']++;

            $panelistname = $panelist->get('firstname') . ' ' . $panelist->get('lastname');
            if (!empty($panelistname) && !empty($qtdata['answerfeedback'])) {
                $panelistname .= '&nbsp;:';
            }
            $namebl = \html_writer::tag('strong', $panelistname);
            $combinedanswers[$slot][$qtchoiceorder]['feedback'] .= \html_writer::tag('p', $namebl);
            if (!empty($qtdata['answerfeedback'])) {
                $combinedanswers[$slot][$qtchoiceorder]['feedback'] .= \html_writer::tag(
                    'p',
                    $qtdata['answerfeedback']
                );
            }
            return $combinedanswers;
        }
        return [];
    }

    /**
     * Get combined drawings for tcs perception questions
     *
     * @param array $qtdata
     * @param int $slot
     * @param object $panelist
     * @param array $combineddrawings
     * @param array $files
     * @return array
     */
    protected function get_perception_combined_drawings(
        array $qtdata,
        int $slot,
        object $panelist,
        array $combineddrawings,
        array $files
    ): array {
        $nbfiles = count($files);

        if ($nbfiles !== 0) {
            $i = 0;
            foreach ($files as $file) {
                if (!isset($combineddrawings[$slot])) {
                    $combineddrawings[$slot] = [];
                }

                // Get image width and height.
                $imageparts = explode(";base64,", $file[1]);
                $imagebase64 = $imageparts[1];
                $image = base64_decode($imagebase64);
                $source = imagecreatefromstring($image);
                $width = imagesx($source);
                $height = imagesy($source);
                imagedestroy($source);

                if (!isset($combineddrawings[$slot][$i])) {
                    $combineddrawings[$slot][$i] = [
                        'answer' => '',
                        'imagefeedback' => '',
                        'othervalues' => [],
                        'image' => $i,
                        'width' => $width,
                        'height' => $height,
                    ];
                }
                $panelistname = $panelist->get('firstname') . ' ' . $panelist->get('lastname');
                $uniqid = md5($panelist->get('firstname') . $panelist->get('lastname') . $panelist->get('id'));

                if (isset($qtdata['answer' . $i])) {
                    $svgcontent = $qtdata['answer' . $i];
                } else {
                    $svgcontent = '';
                }
                $svgcontent = preg_replace('/<svg[^>]*>/', '', $svgcontent);
                $svgcontent = str_replace('</svg>', '', $svgcontent);
                $svgcontent = str_replace(
                    'id="paths"',
                    'id="' . $uniqid . '" class="panelistdrawing ' . $uniqid . '"',
                    $svgcontent
                );
                $svgcontent = preg_replace(
                    '/<title class="grouptitle">([^<]+)<\/title>/',
                    '<title class="grouptitle">' . $panelistname . '</title>',
                    $svgcontent
                );

                // Add class to identify panelist in line.
                $svgcontent = preg_replace('/<line/', '<line class="' . $uniqid . '"', $svgcontent);

                // Add class to identify panelist in text.
                $svgcontent = preg_replace('/<text/', '<text class="' . $uniqid . '"', $svgcontent);

                // Add class to identify panelist in path.
                $svgcontent = preg_replace('/<path/', '<path class="' . $uniqid . '"', $svgcontent);
                // Add class to identify panelist in ellipse.
                $svgcontent = preg_replace('/<ellipse/', '<ellipse class="' . $uniqid . '"', $svgcontent);
                if (isset($qtdata['answer' . $i])) {
                    $combineddrawings[$slot][$i]['answer'] .= $svgcontent;
                }
                $panelistname = $panelist->get('firstname') . ' ' . $panelist->get('lastname');
                $uniqid = md5($panelist->get('firstname') . $panelist->get('lastname') . $panelist->get('id'));
                $combineddrawings[$slot][$i]['othervalues'][] =
                    [
                        'panelist' => $panelistname,
                        'id' => $uniqid,
                        'imagefeedback' => isset($qtdata['imagefeedback' . $i]) ? $qtdata['imagefeedback' . $i] : '',
                    ];
                $i++;
            }
            return $combineddrawings;
        }
        return [];
    }

    /**
     * Get users who have attempted the quiz.
     *
     * @return array Array of users id.
     */
    public function get_users_attempted_quiz() {
        global $DB;
        if (empty($this->concordance->get('cmgenerated'))) {
            return [];
        }
        $cm = get_coursemodule_from_id('', $this->concordance->get('cmgenerated'), 0, true, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
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
     * @return structure|array Quiz structure.
     */
    public function get_quiz_structure(): structure|array {
        if (empty($this->concordance->get('cmgenerated'))) {
            return [];
        }
        $quizsettings = $this->get_quiz_settings(
            $this->concordance->get('coursegenerated'),
            $this->concordance->get('cmgenerated')
        );
        return $quizsettings->get_structure();
    }

    /**
     * Unlink the questions generated when the quiz module was duplicated
     * Then duplicate the question from the panelist quiz.
     *
     * @param quiz_settings $newquiz
     * @param quiz_settings $panelistquiz
     * @return void
     */
    private function duplicate_questions_from_panelist_course(
        quiz_settings $newquiz,
        quiz_settings $panelistquiz
    ): void {
        // Remove all the questions linked to this quiz!
        // The student quiz is a copy of the origin quiz which may contain more than one section.
        // structure::remove_slot() refuses to delete the only slot of a section while several sections
        // remain, so we first remove the extra section headings before deleting the slots one by one.
        $this->remove_extra_sections($newquiz);
        $quizstructure = $newquiz->get_structure();
        while ($quizstructure->has_questions()) {
            $quizstructure->remove_slot(1);
        }

        $allquestions = $this->get_questions_from_quiz($panelistquiz);
        $questionspanelistquiz = array_values(
            array_filter(
                $allquestions,
                fn($q) => $this->question_should_be_included($q->slot)
            )
        );
        // Must be computed before the import, which renumbers the slot of the questions.
        $sectionsplan = $this->build_sections_plan($panelistquiz, $allquestions);
        $questionhandler = new questions_import_export_handler($newquiz, $questionspanelistquiz);
        $questionhandler->duplicate_questions_in_quiz();
        $this->restore_sections($newquiz, $sectionsplan);
    }

    /**
     * Describe the sections of the panelist quiz to recreate in the student quiz.
     *
     * A section is kept only if at least one of its questions is included. It starts on the page
     * of its first included question (pages are preserved when the questions are duplicated).
     *
     * @param quiz_settings $panelistquiz The panelist quiz.
     * @param array $questions All the questions of the panelist quiz.
     * @return array List of ['heading' => string, 'shuffle' => bool, 'page' => int], ordered by page.
     * @author     Gurvan Giboire <gurvan.giboire@umontreal.ca>
     * @copyright  2026 Université de Montréal
     */
    private function build_sections_plan(quiz_settings $panelistquiz, array $questions): array {
        $structure = $panelistquiz->get_structure();
        $plan = [];
        foreach ($structure->get_sections() as $section) {
            foreach ($structure->get_slots_in_section($section->id) as $slot) {
                $included = $this->question_should_be_included((int)$slot);
                $question = array_values(array_filter($questions, fn($q) => $q->slot == $slot));
                if ($included && !empty($question)) {
                    $plan[] = [
                        'heading' => (string)$section->heading,
                        'shuffle' => !empty($section->shufflequestions),
                        'page' => (int)$question[0]->page,
                    ];
                    break;
                }
            }
        }
        return $plan;
    }

    /**
     * Recreate in the student quiz the sections of the panelist quiz.
     *
     * The first section always exists in the student quiz, so it is only renamed. The others are added.
     *
     * @param quiz_settings $newquiz The student quiz.
     * @param array $plan The sections to recreate, see {@see build_sections_plan()}.
     * @return void
     * @author     Gurvan Giboire <gurvan.giboire@umontreal.ca>
     * @copyright  2026 Université de Montréal
     */
    private function restore_sections(quiz_settings $newquiz, array $plan): void {
        foreach ($plan as $index => $sectiondata) {
            $structure = $newquiz->get_structure();
            if ($index === 0) {
                $sections = $structure->get_sections();
                $sectionid = reset($sections)->id;
                $structure->set_section_heading($sectionid, $sectiondata['heading']);
            } else {
                $sectionid = $structure->add_section_heading($sectiondata['page'], $sectiondata['heading']);
            }
            $structure->set_section_shuffle($sectionid, $sectiondata['shuffle']);
        }
    }

    /**
     * Remove every section heading of the quiz except the first one.
     *
     * When the origin quiz contains more than one section, {@see structure::remove_slot()} throws a
     * coding_exception when asked to delete the only remaining slot of a section. Removing the extra
     * sections beforehand guarantees a single section is left so the slots can then be deleted safely.
     *
     * A fresh structure is fetched after each deletion because {@see structure::remove_section_heading()}
     * only deletes the database record and does not refresh the in-memory section list.
     *
     * @param quiz_settings $quiz The quiz whose extra sections must be removed.
     * @return void
     * @author     Gurvan Giboire <gurvan.giboire@umontreal.ca>
     * @copyright  2026 Université de Montréal
     */
    private function remove_extra_sections(quiz_settings $quiz): void {
        $structure = $quiz->get_structure();
        while ($structure->get_section_count() > 1) {
            $removed = false;
            foreach ($structure->get_sections() as $section) {
                // The first section (firstslot == 1) cannot be removed.
                if ($section->firstslot != 1) {
                    $structure->remove_section_heading($section->id);
                    $removed = true;
                    break;
                }
            }
            // Safety guard: stop if no removable section was found to avoid an infinite loop.
            if (!$removed) {
                break;
            }
            // Re-fetch a fresh structure since remove_section_heading() does not refresh the in-memory state.
            $structure = $quiz->get_structure();
        }
    }

    /**
     * Get quiz settings
     *
     * @param int $courseid The course id.
     * @param int $moduleid The course module id.
     * @return quiz_settings Quiz structure.
     */
    private function get_quiz_settings(int $courseid, int $moduleid): quiz_settings {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_id('', $moduleid, 0, true, MUST_EXIST);
        $quizobjet = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        return new quiz_settings($quizobjet, $cm, $course);
    }

    /**
     * Get all the questions of a quiz
     *
     * @param quiz_settings $quiz
     * @return array
     */
    private function get_questions_from_quiz(quiz_settings $quiz): array {
        if ($quiz->has_questions()) {
            $quiz->load_questions();
            return $quiz->get_questions();
        }
        return [];
    }

    /**
     * Check if it is a tcs or tcs perception question type
     *
     * @param question_definition $question
     * @return bool
     */
    private function is_tcs_question(question_definition $question): bool {
        $qtcs = "qtype_tcs_question";
        $qtcsperception = "qtype_tcsperception_question";
        return  $question instanceof $qtcs
            || $question instanceof $qtcsperception;
    }

    /**
     * Check if it is tcs perception question type
     *
     * @param question_definition $question
     * @return bool
     */
    private function is_tcs_perception_question(question_definition $question): bool {
        $qtcsperception = "qtype_tcsperception_question";
        return $question instanceof $qtcsperception;
    }

    /**
     * Verify if a question should be included based on the data provided in the form
     *
     * @param int $slot
     * @return bool
     */
    private function question_should_be_included(int $slot): bool {
        return in_array((int)$slot, $this->formdata->questionstoinclude);
    }

    /**
     * Form data setter
     *
     * @param stdClass $form
     * @return void
     */
    private function set_form_data(stdClass $form): void {
        // Create a an array based on the question to include with the new slot as key and with the slot of the panelist as value !
        $form->questionstoinclude = isset($form->questionstoinclude)
            ? array_combine(range(1, count($form->questionstoinclude)), array_keys($form->questionstoinclude)) : [];
        $form->paneliststoinclude = isset($form->paneliststoinclude) ? array_keys($form->paneliststoinclude) : [];
        $this->formdata = $form;
    }

    /**
     * Verify if the form contains selected questions
     *
     * @return bool
     */
    private function form_has_questions(): bool {
        return !empty($this->formdata->questionstoinclude);
    }

    /**
     * Build a clean svg
     *
     * @param array $drawing
     * @return string
     */
    private function build_full_svg(array $drawing): string {
        $width = $drawing['width'];
        $height = $drawing['height'];
        $answer = $drawing['answer'];
        return <<<XML
        <svg xmlns="http://www.w3.org/2000/svg" width="$width" height="$height">
            <g id="paths">$answer</g>
        </svg>
        XML;
    }

    /**
     * Insert or update a perception answer based on the existing data
     *
     * @param stdClass $answer
     * @return void
     */
    private static function insert_or_update_perception_answer(stdClass $answer): void {
        global $DB;
        $existinganswer = $DB->get_record(
            'qtype_tcsperception_answers',
            [
                'questionid' => $answer->questionid,
                'image' => $answer->image,
            ]
        );
        $method = 'insert_record';
        if ($existinganswer) {
            $answer->id = $existinganswer->id;
            $answer->timemodified = time();
            $method = 'update_record';
        }

        $DB->{$method}('qtype_tcsperception_answers', $answer);
    }

    /**
     * Check if it should include the biography
     *
     * @return bool
     */
    private function include_biography(): bool {
        return !empty($this->formdata->includebibliography)
            && !empty($this->formdata->paneliststoinclude);
    }

    /**
     * Update the progress bar to inform the progression to the user
     *
     * @param int $percent
     * @return void
     */
    private function update_progress_bar(int $percent): void {
        if (PHPUNIT_TEST) {
            return;
        }
        if (!$this->progressbar) {
            $this->progressbar = new progress_bar();
            $this->progressbar->create();
        }
        $this->progressbar->update_full($percent, get_string('progress_bar_message', 'mod_concordance'));
    }
}
