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
 * Auto-login using token and redirect to quiz page.
 *
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot.'/mod/quiz/locallib.php');

$keytoken = required_param('key', PARAM_ALPHANUMEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_pagelayout('secure');
$urlquizaccess = new moodle_url('/mod/concordance/quizaccess.php', ['key' => $keytoken]);
$PAGE->set_url($urlquizaccess);
$output = $PAGE->get_renderer('mod_quiz');

$key = validate_user_key($keytoken, 'concordancepanelist', null);
$user = core_user::get_user($key->userid, '*', MUST_EXIST);
$panelist = \mod_concordance\panelist::get_record(['userid' => $user->id]);
if (!$panelist) {
    throw new moodle_exception('Can not find panelist');
}
$concordance = \mod_concordance\concordance::get_record(['id' => $panelist->get('concordance')]);
if (isloggedin() and !$confirm) {
    if ($USER->id === $user->id) {
        $cm = get_coursemodule_from_id('quiz', $concordance->get('cmgenerated'));
        $context = context_module::instance($cm->id);
        $quizobj = quiz::create($cm->instance, $USER->id);
        $quiz = $quizobj->get_quiz();
        if ($panelist->has_attempted_quiz($quiz->id)) {
            $quizurl = new moodle_url('/mod/quiz/view.php', ['id' => $concordance->get('cmgenerated'), 'sesskey' => sesskey()]);
        } else {
            $quizurl = new moodle_url('/mod/quiz/startattempt.php', ['cmid' => $concordance->get('cmgenerated'),
                'sesskey' => sesskey()]);
        }
        $button = new single_button($quizurl, get_string('attemptquiznow', 'quiz'));
        $info = $output->view_information($quiz, $cm, $context, []);
        echo $OUTPUT->header();
        echo $output->render_from_template('mod_concordance/accesspanelistquiz',
                ['body' => $info, 'footer' => $output->render($button)]);
        echo $OUTPUT->footer();
        return;
    }
    echo $OUTPUT->header();
    $url = new moodle_url('/mod/concordance/quizaccess.php', ['key' => $keytoken, 'confirm' => 1]);
    echo $OUTPUT->confirm(get_string('alreadyloggedin', 'error', fullname($USER)), $url, $CFG->wwwroot);
    echo $OUTPUT->footer();
} else {
    core_user::require_active_user($user, true, true);
    // Do the user log-in.
    if (!$user = get_complete_user_data('id', $user->id)) {
        throw new moodle_exception('cannotfinduser', '', '', $user->id);
    }

    complete_user_login($user);
    \core\session\manager::apply_concurrent_login_limit($user->id, session_id());
    require_login(null, false);

    redirect($urlquizaccess);
}
