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
        $courseinfo = get_fast_modinfo($this->course);
        $this->assertCount(2, $courseinfo->instances['quiz']);
        $this->assertEquals($this->course->id, $this->concordancepersistent->get('course'));
        $this->assertEquals($quiz1->cmid, $this->concordancepersistent->get('cmorigin'));
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

        // Add quiz to the course.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz1 = $quizgenerator->create_instance(array('course' => $this->course->id, 'name' => 'First quiz', 'visible' => false));

        // Select a quiz for the Concordance activity and duplicate it for panelists.
        $this->concordancepersistent->set('cmorigin', $quiz1->cmid);
        quizmanager::duplicatequizforpanelists($this->concordancepersistent, false);

        // Duplicate quiz for students.
        $cmid = quizmanager::duplicatequizforstudents($this->concordancepersistent, $formdata);
        $this->assertNotNull($cmid);

        $courseinfo = get_fast_modinfo($this->course);
        $cm = get_coursemodule_from_id('quiz', $cmid);
        $context = \context_module::instance($cm->id);
        $this->assertTrue(array_key_exists($cm->instance, $courseinfo->instances['quiz']));
        $quiz = $DB->get_record('quiz', array('id' => $cm->instance), '*', MUST_EXIST);
        $this->assertEquals('-', $quiz->browsersecurity);
        $this->assertEquals(0, $cm->visible);
        $this->assertEquals('description student', trim(strip_tags($quiz->intro)));
        // Check that file was copied.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_quiz', 'intro', 0, "itemid, filepath, filename", false);
        $this->assertCount(1, $files);
        $file = array_values($files)[0];
        $this->assertEquals('fakeimage2.png', $file->get_filename());
    }
}
