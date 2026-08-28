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

namespace mod_concordance;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use mod_concordance\external;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * External testcase.
 *
 * @package    mod_concordance
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @copyright  2020 Université de Montréal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(external::class)]
#[RunTestsInSeparateProcesses]
final class external_test extends \externallib_advanced_testcase {
    /**
     * Test send message external.
     */
    #[RunInSeparateProcess]
    public function test_send_message(): void {
        $this->resetAfterTest(true);
        // Create a course.
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Create the activity.
        $concordance = $this->getDataGenerator()->create_module('concordance', ['course' => $course->id]);
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
        $result = external::send_message(
            [$panelist1->get('id'), $panelist2->get('id')],
            $message,
            $subject,
            $concordance->cmid,
            false
        );
        $result = (object) \external_api::clean_returnvalue(external::send_message_returns(), $result);
        $this->assertTrue($result->scalar);

        // Get our messages.
        $this->assertSame(2, $sink->count());
        $result = $sink->get_messages();
        $this->assertCount(2, $result);
        $sink->close();
        // Get our event.
        $events = $sinkevents->get_events();
        $this->assertCount(2, $events);
        // Messages and events are not guaranteed to be returned in a particular order, so match them by panelist.
        $resultbyemail = [];
        foreach ($result as $sentmessage) {
            $resultbyemail[$sentmessage->to] = $sentmessage;
        }
        $eventsbypanelistid = [];
        foreach ($events as $event) {
            $eventsbypanelistid[$event->other['panelistid']] = $event;
        }

        foreach ([$panelist1, $panelist2] as $panelist) {
            $event = $eventsbypanelistid[$panelist->get('id')];
            $this->assertInstanceOf('\mod_concordance\event\email_sent', $event);
            $this->assertNull($event->relateduserid);
            $this->assertEquals($teacher->id, $event->userid);
            $this->assertEquals($context->id, $event->contextid);
            $panelistfullname = trim($panelist->get('firstname') . ' ' . $panelist->get('lastname'));
            $eventdesc = "Email sent for contacting panelists from the user with id '$teacher->id' " .
                   "to the panelist '$panelistfullname' with id '" . $panelist->get('id') . "'";
            $this->assertStringContainsString($eventdesc, $event->get_description());

            $panelistmessage = $resultbyemail[$panelist->get('email')];
            $this->assertSame($subject, $panelistmessage->subject);
            $this->assertStringContainsString($message, trim($panelistmessage->body));
            $this->assertStringContainsString(
                $panelist->get_quizaccess_url()->out(true),
                quoted_printable_decode($panelistmessage->body)
            );
        }
        // Load panelists.
        $panelist1->read();
        $panelist2->read();
        $this->assertEquals(1, $panelist1->get('nbemailsent'));
        $this->assertEquals(1, $panelist2->get('nbemailsent'));
    }
}
