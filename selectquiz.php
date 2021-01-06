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
 * Select quiz page.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$cmid       = required_param('cmid', PARAM_INT);  // Course module.

$cm         = get_coursemodule_from_id('concordance', $cmid, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$concordance   = $DB->get_record('concordance', array('id' => $cm->instance), '*', MUST_EXIST);
$concordancepersistent   = new \mod_concordance\concordance($concordance->id);

$context = context_module::instance($cm->id);
require_login($course, false, $cm);
require_capability('mod/concordance:addinstance', $context);

$title = get_string('quizselection', 'mod_concordance');
$url = new moodle_url("/mod/concordance/selectquiz.php", [
    'cmid' => $cmid
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

if (!$cms = get_coursemodules_in_course('quiz', $course->id)) {
    $strplural = get_string('modulenameplural', 'quiz');
    echo $OUTPUT->notification(get_string('thereareno', 'moodle', $strplural));
} else {
    $count = \mod_concordance\panelist::count_panelistscontacted_for_concordance($concordancepersistent->get('id'));
    if ($count > 0) {
        echo $OUTPUT->notification(get_string('selectquiz_panelistcontacted', 'mod_concordance'));
        $cm = get_coursemodule_from_id('', $concordancepersistent->get('cmorigin'), 0, true, IGNORE_MISSING);
        if (!empty($cm)) {
            echo '<p>'.get_string('pluginname', 'quiz').' : '.$cm->name.'</p>';
        }
    } else {
        $quizlist = \mod_concordance\concordance::quizlist($course);
        $form = new \mod_concordance\form\quizselection($url->out(false),
                array('quizlist' => $quizlist, 'context' => $context));

        $form->set_data(['cmorigin' => $concordancepersistent->get('cmorigin')]);
        $data = $form->get_submitted_data();
        if ($data) {
            if (isset($data->cmorigin)) {
                $contextcmorigin = context_module::instance($data->cmorigin);
                require_capability('mod/quiz:manage', $contextcmorigin);
                $concordancepersistent->set('cmorigin', $data->cmorigin);
            } else {
                $concordancepersistent->set('cmorigin', null);
            }
            \mod_concordance\quizmanager::duplicatequizforpanelists($concordancepersistent);
            $concordancepersistent->update();
        }
        $notification = $OUTPUT->notification(get_string('shouldnotmodifyquiz', 'mod_concordance'),
                \core\output\notification::NOTIFY_INFO);
        $classhidden = (empty($concordancepersistent->get('cmorigin'))) ? 'hidden' : '';
        $notificationtemplate = $OUTPUT->render_from_template('mod_concordance/selectquiznotification',
                ['html' => $notification, 'classhidden' => $classhidden]);
        echo $notificationtemplate;
        $form->display();
    }
    if ($cmid = $concordancepersistent->get('cmorigin')) {
        $contextcmorigin = context_module::instance($cmid);
        require_capability('mod/quiz:manage', $contextcmorigin);
        $page = new \mod_concordance\output\select_quiz_page($cmid);
        echo $output->render($page);
    }
}

echo $output->footer();
