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
 * Edit concordance module instance
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('concordance', $id, 0, true, MUST_EXIST);
$context = context_module::instance($cm->id, MUST_EXIST);
$concordance = $DB->get_record('concordance', ['id' => $cm->instance], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, false, $cm);

$PAGE->set_url('/mod/concordance/edit.php', ['cmid' => $cm->id]);
$PAGE->set_title($course->shortname . ': ' . $concordance->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($concordance);

$data = new stdClass();
$data->id = $cm->id;

$mform = new \mod_concordance\form\edit_form(null, ['data' => $data]);

if ($mform->is_cancelled()) {
    redirect($redirecturl);

} else if ($formdata = $mform->get_data()) {
    // Ici traiter le formulaire.

    $params = [
        'context' => $context,
        'objectid' => $concordance->id,
    ];
    $event = \mod_concordance\event\concordance_updated::create($params);
    $event->add_record_snapshot('concordance', $concordance);
    $event->trigger();

    redirect($redirecturl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($concordance->name));

$mform->display();

echo $OUTPUT->footer();
