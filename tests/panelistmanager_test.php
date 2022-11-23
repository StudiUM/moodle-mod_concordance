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
 * Unit tests for mod_concordance panelistmanager.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_concordance;


/**
 * Unit tests for mod_concordance panelistmanager
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_concordance\panlistmanager
 */
class panelistmanager_test extends \advanced_testcase {

    /**
     * Setup.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test panelistcreated/panelistdeleted.
     * @return void
     */
    public function test_panelist_created_deleted() {
        global $DB, $PAGE, $CFG;
        // Test panelist created.
        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create the activity.
        $concordance = $this->getDataGenerator()->create_module('concordance', array('course' => $course->id));
        $panelistid = $this->createpanelist($concordance->id);
        $panelist = new \mod_concordance\panelist($panelistid);
        $this->assertNull($panelist->get('userid'));
        \mod_concordance\panelistmanager::panelistcreated($panelist);
        // Check that new moodle user is created.
        $this->assertNotNull($panelist->get('userid'));
        $user = $DB->get_record('user', ['id' => $panelist->get('userid')]);
        $this->assertEquals('Panelist-' . $panelist->get('id'), $user->firstname);
        $this->assertEquals('Panelist-' . $panelist->get('id'), $user->lastname);
        $this->assertEquals(1, $user->confirmed);
        // Check if user is enrolled as student in the course.
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'student'));
        $context = \context_course::instance($concordance->coursegenerated);
        $this->assertTrue(user_has_role_assignment($panelist->get('userid'), $roleid, $context->id));

        // Test panelist deleted.
        $DB->delete_records('concordance_panelist', ['id' => $panelistid]);
        \mod_concordance\panelistmanager::panelistdeleted($panelist->get('userid'));
        $userdeleted = $DB->get_record('user', ['id' => $panelist->get('userid')]);
        $this->assertEquals(1, $userdeleted->deleted);
        $this->assertFalse(user_has_role_assignment($panelist->get('userid'), $roleid, $context->id));
    }

    /**
     * Create panelist.
     * @param int $concordanceid
     * @return int panelistid
     */
    private function createpanelist($concordanceid) {
        global $DB;
        $panelist = new \stdClass();
        $panelist->concordance = $concordanceid;
        $panelist->firstname = 'Smith';
        $panelist->lastname = 'Smith';
        $panelist->email = 'smith@example.com';
        $panelist->bibliography = 'bibliography';
        return $DB->insert_record('concordance_panelist', $panelist);
    }
}
