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
 * Concordance external API.
 *
 * @package    mod_concordance
 * @category   external
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_concordance;

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_value;

/**
 * Concordance external functions.
 *
 * @package    mod_concordance
 * @category   external
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {

    /**
     * Returns description of send_message() parameters.
     *
     * @return \external_function_parameters
     */
    public static function send_message_parameters() {
        return new external_function_parameters([
            'users' => new external_multiple_structure(
                new external_value(PARAM_INT, 'The users ids', VALUE_REQUIRED)
            ),
            'message' => new external_value(
                PARAM_RAW,
                'Text of the message',
                VALUE_REQUIRED
            ),
            'subject' => new external_value(
                PARAM_RAW,
                'Subject of the message',
                VALUE_REQUIRED
            ),
            'cmid' => new external_value(
                PARAM_INT,
                'The course module id',
                VALUE_REQUIRED
            ),
            'displaynotification' => new external_value(
                PARAM_BOOL,
                'True if we want to display notification',
                VALUE_DEFAULT,
                false
            )
        ]);
    }

    /**
     * Send message to panelists.
     *
     * @param array  $users The users ids
     * @param string $message Text of the message
     * @param string $subject Subject of the message
     * @param int    $cmid The course module id
     * @param boolean $displaynotification If we want to display notification
     * @return boolean
     */
    public static function send_message($users, $message, $subject, $cmid, $displaynotification = false) {
        global $USER, $PAGE;
        $params = self::validate_parameters(self::send_message_parameters(), array(
            'users' => $users,
            'message' => $message,
            'subject' => $subject,
            'cmid' => $cmid,
            'displaynotification' => $displaynotification
        ));

        $cm         = get_coursemodule_from_id('concordance', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $PAGE->set_context($context);
        require_capability('mod/concordance:addinstance', $context);
        $panelists = \mod_concordance\panelist::get_concordancepanelists_by_ids($params['users'], $cm->instance);
        $sender = $USER;
        $countuser = 0;
        $replyto = $USER->email;
        $replytoname = fullname($USER);
        foreach ($panelists as $panelist) {
            $recipient = $panelist->get_user();
            $recipient->firstname = $panelist->get('firstname');
            $recipient->lastname = $panelist->get('lastname');
            $recipient->email = $panelist->get('email');
            $data = ['text' => $message];
            $data['url'] = $panelist->get_quizaccess_url()->out(true);
            $renderer = $PAGE->get_renderer('core');
            $messagehtml = nl2br($renderer->render_from_template('mod_concordance/email_panelist', $data));
            $messagetext = html_to_text($messagehtml);
            if (email_to_user($recipient, $sender, $subject, $messagetext, $messagehtml, '', '', true, $replyto, $replytoname)) {
                // Trigger event for email sent.
                $event = \mod_concordance\event\email_sent::create(array(
                    'context' => $context,
                    'userid' => $USER->id,
                    'relateduserid' => $panelist->get('id')
                ));
                $event->trigger();
                $nbsent = $panelist->get('nbemailsent') + 1;
                $panelist->set('nbemailsent', $nbsent);
                $panelist->update();
                $countuser++;
            }
        }

        if ($displaynotification && $countuser) {
            if ($countuser === 1) {
                $notificationmessage = get_string('sendbulkmessagesentsingle', 'core_message');
            } else {
                $notificationmessage = get_string('sendbulkmessagesent', 'core_message', $countuser);
            }
            \core\notification::add($notificationmessage, \core\output\notification::NOTIFY_SUCCESS);
        }

        return true;
    }

    /**
     * Returns description of send_message() result value.
     *
     * @return \external_description
     */
    public static function send_message_returns() {
        return new external_value(PARAM_BOOL, 'True if sending was successful');
    }
}
