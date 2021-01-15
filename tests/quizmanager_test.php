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
 * Unit tests for mod_concordance quizmanager.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');

use \mod_concordance\quizmanager;
use \mod_concordance\concordance;


/**
 * Unit tests for mod_concordance quizmanager
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Lévesque <marie-eve.levesque.8@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizmanager_testcase extends advanced_testcase {

    /** @var concordance Concordance persistent object. */
    protected $concordancepersistent = null;

    /** @var concordstdClass course record. */
    protected $course = null;

    /**
     * Setup.
     */
    public function setUp() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course.
        $this->course = $this->getDataGenerator()->create_course();

        // Create and enrol the teacher.
        $teacher = $this->getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($teacher->id,  $this->course->id, $teacherrole->id);

        $this->setUser($teacher);

        // Create the concordance activity.
        $concordance = $this->getDataGenerator()->create_module('concordance', array('course' => $this->course->id,
            'descriptionpanelist' => '', 'descriptionstudent' => ''));
        $concordancem = get_coursemodule_from_instance('concordance', $concordance->id, $this->course->id, true, MUST_EXIST);
        $context = \context_module::instance($concordancem->id);
        $filerecord1 = array(
            'contextid' => $context->id,
            'component' => 'mod_concordance',
            'filearea'  => 'descriptionpanelist',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'fakeimage1.png',
        );
        $filerecord2 = array(
            'contextid' => $context->id,
            'component' => 'mod_concordance',
            'filearea'  => 'descriptionstudent',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'fakeimage2.png',
        );
        $fs = get_file_storage();
        $fs->create_file_from_string($filerecord1, 'img contents');
        $concordance->descriptionpanelist = '<p>description panelist</p> <img src="@@PLUGINFILE@@/fakeimage1.png">';
        $fs->create_file_from_string($filerecord2, 'img contents');
        $concordance->descriptionstudent = '<p>description student</p> <img src="@@PLUGINFILE@@/fakeimage2.png">';
        $concordance->descriptionstudentformat = FORMAT_HTML;
        $DB->update_record('concordance', $concordance);
        $this->concordancepersistent   = new concordance($concordance->id);
    }

    /**
     * Test duplicatequizforpanelists.
     * @return void
     */
    public function test_duplicatequizforpanelists() {
        global $DB;
        // Duplicate the quiz, when there are no quiz yet.
        quizmanager::duplicatequizforpanelists($this->concordancepersistent, false);
        $this->assertNull($this->concordancepersistent->get('cmgenerated'));

        // Add 2 quizzes to the course.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz1 = $quizgenerator->create_instance(array('course' => $this->course->id, 'name' => 'First quiz', 'visible' => false));
        $quiz2 = $quizgenerator->create_instance(array('course' => $this->course->id, 'name' => 'Second quiz', 'visible' => false));

        // Select a quiz for the Concordance activity and duplicate it for panelists.
        $this->concordancepersistent->set('cmorigin', $quiz1->cmid);
        quizmanager::duplicatequizforpanelists($this->concordancepersistent, false);
        // Check the original course and quiz.
        $cm = get_coursemodule_from_id('', $this->concordancepersistent->get('cmorigin'), 0, true, MUST_EXIST);
        $quiz1record = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);
        $courseinfo = get_fast_modinfo($this->course);
        $this->assertCount(2, $courseinfo->instances['quiz']);
        $this->assertEquals($this->course->id, $this->concordancepersistent->get('course'));
        $this->assertEquals($quiz1->cmid, $this->concordancepersistent->get('cmorigin'));
        $this->assertEquals($quiz1record->grade, 0);
        // Check the duplicated course and quiz.
        $courseinfo = get_fast_modinfo($this->concordancepersistent->get('coursegenerated'));
        $this->assertCount(1, $courseinfo->instances['quiz']);
        $this->assertNotEquals($this->concordancepersistent->get('course'), $this->concordancepersistent->get('coursegenerated'));
        $quiztocheck1 = array_values($courseinfo->instances['quiz'])[0];
        $this->assertNotEquals($this->concordancepersistent->get('cmorigin'), $quiztocheck1->id);
        $this->assertEquals($this->concordancepersistent->get('cmgenerated'), $quiztocheck1->id);
        $this->assertEquals('First quiz', $quiztocheck1->name);
        $this->assertEquals(1, $quiztocheck1->visible);
        $quiztocheck1details = $DB->get_record('quiz', array('id' => $quiztocheck1->instance), '*', MUST_EXIST);
        $this->assertEquals('securewindow', $quiztocheck1details->browsersecurity);
        $this->assertEquals('description panelist', trim(strip_tags($quiztocheck1details->intro)));
        $this->assertEquals(1, $quiztocheck1details->attempts);
        // Check that file was copied.
        $contextcm = \context_module::instance($quiztocheck1->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextcm->id, 'mod_quiz', 'intro', 0, "itemid, filepath, filename", false);
        $this->assertCount(1, $files);
        $file = array_values($files)[0];
        $this->assertEquals('fakeimage1.png', $file->get_filename());

        // Change the quiz of the Concordance activity.
        $this->concordancepersistent->set('cmorigin', $quiz2->cmid);
        quizmanager::duplicatequizforpanelists($this->concordancepersistent, false);
        // Check the original course and quiz.
        $courseinfo = get_fast_modinfo($this->course);
        $this->assertCount(2, $courseinfo->instances['quiz']);
        $this->assertEquals($this->course->id, $this->concordancepersistent->get('course'));
        $this->assertEquals($quiz2->cmid, $this->concordancepersistent->get('cmorigin'));
        // Check the duplicated course and quiz.
        $courseinfo = get_fast_modinfo($this->concordancepersistent->get('coursegenerated'));
        $this->assertCount(1, $courseinfo->instances['quiz']);
        $this->assertNotEquals($this->concordancepersistent->get('course'), $this->concordancepersistent->get('coursegenerated'));
        $quiztocheck2 = array_values($courseinfo->instances['quiz'])[0];
        $this->assertNotEquals($this->concordancepersistent->get('cmorigin'), $quiztocheck2->id);
        $this->assertEquals($this->concordancepersistent->get('cmgenerated'), $quiztocheck2->id);
        $this->assertNotEquals($quiztocheck1->id, $quiztocheck2->id);
        $this->assertEquals('Second quiz', $quiztocheck2->name);
        $quiztocheck2details = $DB->get_record('quiz', array('id' => $quiztocheck2->instance), '*', MUST_EXIST);
        $this->assertEquals('securewindow', $quiztocheck2details->browsersecurity);
    }

    /**
     * Test duplicatequizforstudents.
     * @return void
     */
    public function test_duplicatequizforstudents() {
        global $DB;

        // Duplicate the quiz for students, when there are no panelist quiz yet.
        $formdata = new \stdClass();
        $cmid = quizmanager::duplicatequizforstudents($this->concordancepersistent, $formdata);
        $this->assertNull($cmid);
        $dg = $this->getDataGenerator();

        // Add quiz to the course.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz1 = $quizgenerator->create_instance(array('course' => $this->course->id, 'name' => 'First quiz', 'visible' => false));

        // Questions.
        $questgen = $dg->get_plugin_generator('core_question');
        $quizcat = $questgen->create_question_category(array('contextid' => \context_course::instance($this->course->id)->id));
        $question1 = $questgen->create_question('numerical', null, ['category' => $quizcat->id]);
        $questgen->update_question($question1);
        $question2 = $questgen->create_question('numerical', null, ['category' => $quizcat->id]);
        quiz_add_quiz_question($question1->id, $quiz1);
        quiz_add_quiz_question($question2->id, $quiz1);
        // Check that the questions are in the category that we created.
        $this->assertEquals($quizcat->id, $question1->category);
        $this->assertEquals($quizcat->id, $question2->category);

        // Select a quiz for the Concordance activity and duplicate it for panelists.
        $this->concordancepersistent->set('cmorigin', $quiz1->cmid);
        quizmanager::duplicatequizforpanelists($this->concordancepersistent, false);
        $cmpanelist = get_coursemodule_from_id('', $this->concordancepersistent->get('cmgenerated'), 0, true, MUST_EXIST);
        $quizpanelist = $DB->get_record('quiz', array('id' => $cmpanelist->instance), '*', MUST_EXIST);
        $coursepanelist = $DB->get_record('course',
                array('id' => $this->concordancepersistent->get('coursegenerated')), '*', MUST_EXIST);
        $quizobj = new \quiz($quizpanelist, $cmpanelist, $coursepanelist);
        $quizobj->preload_questions();
        $quizobj->load_questions();
        $questions = array_values($quizobj->get_questions());
        $this->assertCount(2, $questions);
        $slot1 = $questions[0]->slot;
        $slot2 = $questions[1]->slot;

        // Duplicate quiz for students and 2 questions - summative quiz.
        $formdata->questionstoinclude = ["$slot1" => 1, "$slot2" => 1];
        $formdata->quiztype = quizmanager::CONCORDANCE_QUIZTYPE_SUMMATIVE_WITHFEEDBACK;
        $cmid = quizmanager::duplicatequizforstudents($this->concordancepersistent, $formdata);
        $this->assertNotNull($cmid);

        $courseinfo = get_fast_modinfo($this->course);
        $cm = get_coursemodule_from_id('quiz', $cmid);
        $context = \context_module::instance($cm->id);
        $this->assertTrue(array_key_exists($cm->instance, $courseinfo->instances['quiz']));
        $quiz = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);
        $quizobj = new \quiz($quiz, $cm, $this->course);
        $quizobj->preload_questions();
        $quizobj->load_questions();
        $questions = $quizobj->get_questions();
        $this->assertEquals('-', $quiz->browsersecurity);
        $this->assertEquals(0, $cm->visible);
        $this->assertEquals('description student', trim(strip_tags($quiz->intro)));
        $this->assertCount(2, $questions);
        // Check that all questions are in a new category, that is a child of the category we created.
        foreach ($questions as $question) {
            $this->assertNotEquals($quizcat->id, $question->category);
            $this->assertEquals($quizcat->id, $question->categoryobject->parent);
            $firstchildcategory = $question->category;
        }
        $quizconfig = get_config('quiz');
        $this->assertEquals(quiz_format_grade($quiz, $quizconfig->maximumgrade), quiz_format_grade($quiz, $quiz->grade));
        $this->assertEquals('deferredfeedback', $quiz->preferredbehaviour);
        // Check that file was copied.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_quiz', 'intro', 0, "itemid, filepath, filename", false);
        $this->assertCount(1, $files);
        $file = array_values($files)[0];
        $this->assertEquals('fakeimage2.png', $file->get_filename());

        // Duplicate quiz for students and 1 question - formative test.
        $quizobj = new \quiz($quizpanelist, $cmpanelist, $coursepanelist);
        $quizobj->preload_questions();
        $quizobj->load_questions();
        $questions = array_values($quizobj->get_questions());
        $this->assertCount(2, $questions);
        $slot1 = $questions[0]->slot;
        $formdata->questionstoinclude = ["$slot1" => 1];
        $formdata->quiztype = quizmanager::CONCORDANCE_QUIZTYPE_FORMATIVE;
        $cmid = quizmanager::duplicatequizforstudents($this->concordancepersistent, $formdata);
        $this->assertNotNull($cmid);
        $courseinfo = get_fast_modinfo($this->course);
        $cm = get_coursemodule_from_id('quiz', $cmid);
        $quiz = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);
        $quizobj = new \quiz($quiz, $cm, $this->course);
        $quizobj->preload_questions();
        $quizobj->load_questions();
        $questions = $quizobj->get_questions();
        $question = current($questions);
        $this->assertCount(1, $questions);
        $this->assertEquals($question1->name, $question->name);
        $this->assertEquals(quiz_format_grade($quiz, 0), quiz_format_grade($quiz, $quiz->grade));
        $this->assertEquals('immediatefeedback', $quiz->preferredbehaviour);
        // Check that the question is in a new category, that is a child of the category we created
        // (and not the same as the other quiz we generated).
        $this->assertNotEquals($quizcat->id, $question->category);
        $this->assertEquals($quizcat->id, $question->categoryobject->parent);
        $this->assertNotEquals($firstchildcategory, $question->category);
    }

    /**
     * Test getusersattemptedquiz.
     * @return void
     */
    public function test_getusersattemptedquiz() {
        global $DB;
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $quizgen = $dg->get_plugin_generator('mod_quiz');
        $course = $dg->create_course();
        $u1 = $dg->create_user();
        $u2 = $dg->create_user();
        $u3 = $dg->create_user();
        $u4 = $dg->create_user();
        $u5 = $dg->create_user();
        $role = $DB->get_record('role', ['shortname' => 'student']);

        $dg->enrol_user($u1->id, $course->id, $role->id);
        $dg->enrol_user($u2->id, $course->id, $role->id);
        $dg->enrol_user($u3->id, $course->id, $role->id);
        $dg->enrol_user($u4->id, $course->id, $role->id);
        $dg->enrol_user($u5->id, $course->id, $role->id);

        $quiz1 = $quizgen->create_instance(['course' => $course->id, 'sumgrades' => 2]);

        // Questions.
        $questgen = $dg->get_plugin_generator('core_question');
        $quizcat = $questgen->create_question_category();
        $question = $questgen->create_question('numerical', null, ['category' => $quizcat->id]);
        quiz_add_quiz_question($question->id, $quiz1);

        $quizobj1a = quiz::create($quiz1->id, $u1->id);
        $quizobj1b = quiz::create($quiz1->id, $u2->id);
        $quizobj1c = quiz::create($quiz1->id, $u3->id);
        $quizobj1d = quiz::create($quiz1->id, $u4->id);

        // Set attempts.
        $quba1a = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj1a->get_context());
        $quba1a->set_preferred_behaviour($quizobj1a->get_quiz()->preferredbehaviour);
        $quba1b = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj1b->get_context());
        $quba1b->set_preferred_behaviour($quizobj1b->get_quiz()->preferredbehaviour);
        $quba1c = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj1c->get_context());
        $quba1c->set_preferred_behaviour($quizobj1c->get_quiz()->preferredbehaviour);
        $quba1d = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj1d->get_context());
        $quba1d->set_preferred_behaviour($quizobj1d->get_quiz()->preferredbehaviour);

        $timenow = time();

        // User 1 passes quiz 1.
        $attempt = quiz_create_attempt($quizobj1a, 1, false, $timenow, false, $u1->id);
        quiz_start_new_attempt($quizobj1a, $quba1a, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj1a, $quba1a, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);
        $attemptobj->process_finish($timenow, false);

        // User 2 goes overdue in quiz 1.
        $attempt = quiz_create_attempt($quizobj1b, 1, false, $timenow, false, $u2->id);
        quiz_start_new_attempt($quizobj1b, $quba1b, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj1b, $quba1b, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_going_overdue($timenow, true);

        // User 3 does not finish quiz 1.
        $attempt = quiz_create_attempt($quizobj1c, 1, false, $timenow, false, $u3->id);
        quiz_start_new_attempt($quizobj1c, $quba1c, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj1c, $quba1c, $attempt);

        // User 4 abandons the quiz 1.
        $attempt = quiz_create_attempt($quizobj1d, 1, false, $timenow, false, $u4->id);
        quiz_start_new_attempt($quizobj1d, $quba1d, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj1d, $quba1d, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_abandon($timenow, true);

        // Check for users in quiz1.
        $concordance = new \mod_concordance\concordance();
        $concordance->set('coursegenerated', $course->id);
        $cm = get_coursemodule_from_instance('quiz', $quiz1->id, $course->id, false, MUST_EXIST);
        $concordance->set('cmgenerated', $cm->id);
        $users = \mod_concordance\quizmanager::getusersattemptedquiz($concordance);
        // User5 did not start quiz.
        $this->assertCount(4, $users);

        // User1.
        $user1 = array_shift($users);
        $this->assertEquals(quiz_attempt::FINISHED, $user1->state);
        $this->assertEquals($u1->id, $user1->userid);

        // User2.
        $user2 = array_shift($users);
        $this->assertEquals(quiz_attempt::OVERDUE, $user2->state);
        $this->assertEquals($u2->id, $user2->userid);

        // User3.
        $user3 = array_shift($users);
        $this->assertEquals(quiz_attempt::IN_PROGRESS, $user3->state);
        $this->assertEquals($u3->id, $user3->userid);

        // User4.
        $user4 = array_shift($users);
        $this->assertEquals(quiz_attempt::ABANDONED, $user4->state);
        $this->assertEquals($u4->id, $user4->userid);

        // Also check the has_attempted_quiz function, for each panelist.
        $panelistinfo = new \stdClass();
        $panelistinfo->userid = $u1->id;
        $panelist = new \mod_concordance\panelist(0, $panelistinfo);
        $this->assertTrue($panelist->has_attempted_quiz($quiz1->id));

        $panelistinfo = new \stdClass();
        $panelistinfo->userid = $u2->id;
        $panelist = new \mod_concordance\panelist(0, $panelistinfo);
        $this->assertTrue($panelist->has_attempted_quiz($quiz1->id));

        $panelistinfo = new \stdClass();
        $panelistinfo->userid = $u3->id;
        $panelist = new \mod_concordance\panelist(0, $panelistinfo);
        $this->assertTrue($panelist->has_attempted_quiz($quiz1->id));

        $panelistinfo = new \stdClass();
        $panelistinfo->userid = $u4->id;
        $panelist = new \mod_concordance\panelist(0, $panelistinfo);
        $this->assertTrue($panelist->has_attempted_quiz($quiz1->id));

        $panelistinfo = new \stdClass();
        $panelistinfo->userid = $u5->id;
        $panelist = new \mod_concordance\panelist(0, $panelistinfo);
        $this->assertFalse($panelist->has_attempted_quiz($quiz1->id));
    }
}
