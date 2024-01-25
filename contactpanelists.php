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
 * Contact panelists page.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);            // Course module.

$panelistid = optional_param('panelistid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('concordance', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$concordance = $DB->get_record('concordance', ['id' => $cm->instance], '*', MUST_EXIST);
$concordancepersistent = new \mod_concordance\concordance($concordance->id);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/concordance:addinstance', $context);

$url = new moodle_url('/mod/concordance/contactpanelists.php', ['cmid' => $cm->id]);
$PAGE->set_url($url);
$contactpanelistsstring = get_string('task_contactpanelists', 'mod_concordance');
$PAGE->set_title($contactpanelistsstring);
$PAGE->set_activity_record($concordance);
$PAGE->navbar->add($contactpanelistsstring, $url);

$output = $PAGE->get_renderer('mod_concordance');
echo $output->header();

echo $output->heading($contactpanelistsstring);
$page = new \mod_concordance\output\contact_panelists_page($cm->id, $concordancepersistent);
echo $output->render($page);
echo $output->footer();
