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
 * External tests.
 *
 * @package    mod_concordance
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2020 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use mod_concordance\external;

/**
 * External testcase.
 *
 * @package    mod_concordance
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2020 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_concordance_external_testcase extends externallib_advanced_testcase {

    /**
     * Test send message external.
     */
    public function test_send_message() {
        $this->resetAfterTest(true);
        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Create the activity.
        $concordance = $this->getDataGenerator()->create_module('concordance', array('course' => $course->id));
        $context = \context_module::instance($concordance->cmid);
        // Create 2 panelists.
        $record = new \stdClass();
        $record->concordance = $concordance->id;
        $record->firstname = 'Smith';
        $record->lastname = 'Smith';
        $record->email = 'smith@example.com';
        $record->bibliography = 'bibliography';
        $record->bibliographyformat = FORMAT_HTML;

        $panelist1 = new \mod_concordance\panelist(0, $record);
        $panelist1->create();

        $record = new \stdClass();
        $record->concordance = $concordance->id;
        $record->firstname = 'John';
        $record->lastname = 'John';
        $record->email = 'john@example.com';
        $record->bibliography = 'bibliography';
        $record->bibliographyformat = FORMAT_HTML;

        $panelist2 = new \mod_concordance\panelist(0, $record);
        $panelist2->create();

        // Test sending message.
        $message = "Body text";
        $subject = "subject";
        $this->setUser($teacher);
        $sink = $this->redirectEmails();
        $sinkevents = $this->redirectEvents();
        $result = external::send_message([$panelist1->get('id'), $panelist2->get('id')],
                $message, $subject, $concordance->cmid, false);
        $result = (object) external_api::clean_returnvalue(external::send_message_returns(), $result);
        $this->assertTrue($result->scalar);

        // Get our messages.
        $this->assertSame(2, $sink->count());
        $result = $sink->get_messages();
        $this->assertCount(2, $result);
        $sink->close();
        // Get our event.
        $events = $sinkevents->get_events();
        $this->assertCount(2, $events);
        $eventpan2 = $events[0];
        $eventpan1 = $events[1];

        // Check the events data.
        $this->assertInstanceOf('\mod_concordance\event\email_sent', $eventpan2);
        $this->assertNull($eventpan2->relateduserid);
        $this->assertEquals($teacher->id, $eventpan2->userid);
        $this->assertEquals($context->id, $eventpan2->contextid);
        $panleist2fullname = trim($panelist2->get('firstname') . ' ' . $panelist2->get('lastname'));
        $eventdescpan2 = "Email sent for contacting panelists from the user with id '$teacher->id' " .
               "to the panelist '$panleist2fullname' with id '" . $panelist2->get('id') . "'";
        $this->assertStringContainsString($eventdescpan2, $eventpan2->get_description());

        $this->assertInstanceOf('\mod_concordance\event\email_sent', $eventpan1);
        $this->assertNull($eventpan1->relateduserid);
        $this->assertEquals($teacher->id, $eventpan1->userid);
        $this->assertEquals($context->id, $eventpan1->contextid);
        $panleist1fullname = trim($panelist1->get('firstname') . ' ' . $panelist1->get('lastname'));
        $eventdescpan1 = "Email sent for contacting panelists from the user with id '$teacher->id' " .
               "to the panelist '$panleist1fullname' with id '" . $panelist1->get('id') . "'";
        $this->assertStringContainsString($eventdescpan1, $eventpan1->get_description());

        $this->assertSame($panelist2->get('email'), $result[0]->to);
        $this->assertSame($subject, $result[0]->subject);
        $this->assertStringContainsString($message, trim($result[0]->body));
        $this->assertStringContainsString($panelist2->get_quizaccess_url()->out(true), quoted_printable_decode($result[0]->body));

        $this->assertSame($panelist1->get('email'), $result[1]->to);
        $this->assertSame($subject, $result[1]->subject);
        $this->assertStringContainsString($message, trim($result[1]->body));
        $this->assertStringContainsString($panelist1->get_quizaccess_url()->out(true), quoted_printable_decode($result[1]->body));
        // Load panelists.
        $panelist1->read();
        $panelist2->read();
        $this->assertEquals(1, $panelist1->get('nbemailsent'));
        $this->assertEquals(1, $panelist2->get('nbemailsent'));
    }
}
