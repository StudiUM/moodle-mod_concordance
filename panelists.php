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
 * This page lets users to manage panelists.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);            // Course module.
$action = optional_param('action', null, PARAM_RAW);
$panelistid = optional_param('panelistid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('concordance', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$concordance = $DB->get_record('concordance', array('id' => $cm->instance), '*', MUST_EXIST);
$concordancepersistent = new \mod_concordance\concordance($concordance->id);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/concordance:addinstance', $context);

$url = new moodle_url('/mod/concordance/panelists.php', array('cmid' => $cm->id));
$PAGE->set_url($url);
$panelistsmanagementstring = get_string('panelistmanagement', 'mod_concordance');
$PAGE->set_title($panelistsmanagementstring);
$PAGE->set_activity_record($concordance);
$PAGE->navbar->add($panelistsmanagementstring, $url);

$output = $PAGE->get_renderer('mod_concordance');
echo $output->header();
if (!empty($panelistid) && $action === 'delete') {
    $panelist = new \mod_concordance\panelist($panelistid);
    $panelist->delete();
    echo $OUTPUT->notification(get_string('panelistdeleted', 'mod_concordance'),
                    \core\output\notification::NOTIFY_SUCCESS);
}
echo $output->heading($panelistsmanagementstring);
$page = new \mod_concordance\output\manage_panelists_page($cm->id, $concordance->id);
echo $output->render($page);
echo $output->footer();
