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
 * Generate student's quiz page.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);  // Course module.

$cm = get_coursemodule_from_id('concordance', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$concordance = $DB->get_record('concordance', ['id' => $cm->instance], '*', MUST_EXIST);
$concordancepersistent = new \mod_concordance\concordance($concordance->id);

$context = context_module::instance($cm->id);
require_login($course, false, $cm);
require_capability('mod/concordance:addinstance', $context);

$title = get_string('task_generatequiz', 'mod_concordance');
$url = new moodle_url("/mod/concordance/generatestudentquiz.php", [
    'cmid' => $cmid,
]);
$PAGE->navigation->override_active_url($url);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($context->get_context_name());
$PAGE->navbar->add($title, $url);

$output = $PAGE->get_renderer('mod_concordance');
echo $output->header();
echo $output->heading($title);
$panelists = \mod_concordance\panelist::get_records(['concordance' => $concordancepersistent->get('id')]);
$cmgenerated = $concordancepersistent->get('cmgenerated');
$coursegenerated = $concordancepersistent->get('coursegenerated');
if (count($panelists) == 0) {
    echo $OUTPUT->notification(get_string('nopanelists', 'mod_concordance'));
} else if (!$cm = get_coursemodule_from_id('quiz', $cmgenerated, $coursegenerated)) {
    echo $OUTPUT->notification(get_string('panelistsquiznotfound', 'mod_concordance'));
} else {
    $attempts = \mod_concordance\quizmanager::getusersattemptedquiz($concordancepersistent);
    $structure = \mod_concordance\quizmanager::getquizstructure($concordancepersistent);
    $form = new \mod_concordance\form\studentquizgeneration($url->out(false),
            ['panelists' => $panelists, 'context' => $context, 'attempts' => $attempts, 'structure' => $structure],
            'post', '', ['id' => 'generatestudentquizform']);

    $data = $form->get_submitted_data();
    if ($data) {
        $cmgenerated = \mod_concordance\quizmanager::duplicatequizforstudents($concordancepersistent, $data);
        if ($cmgenerated) {
            $message = get_string('studentquizgenerated', 'mod_concordance');
            $newcmurl = new moodle_url("/mod/quiz/view.php", ['id' => $cmgenerated]);
            $link = html_writer::link($newcmurl, get_string('gotogeneratedquiz', 'mod_concordance'));
            echo $OUTPUT->notification($message . ' ' . $link, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
    $form->display();
}

echo $output->footer();
