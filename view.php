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
 * Concordance module main user interface
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once("$CFG->dirroot/repository/lib.php");

// Course module ID.
$id = optional_param('id', 0, PARAM_INT);
// Concordance instance id.
$s  = optional_param('sy', 0, PARAM_INT);

// Two ways to specify the module.
if ($s) {
    $concordance = $DB->get_record('concordance', array('id' => $s), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('concordance', $concordance->id, $concordance->course, true, MUST_EXIST);

} else {
    $cm = get_coursemodule_from_id('concordance', $id, 0, true, MUST_EXIST);
    $concordance = $DB->get_record('concordance', array('id' => $cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/concordance:view', $context);

$params = array(
    'context' => $context,
    'objectid' => $concordance->id
);
$event = \mod_concordance\event\course_module_viewed::create($params);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('concordance', $concordance);
$event->trigger();

$PAGE->set_url('/mod/concordance/view.php', array('id' => $cm->id));

$PAGE->set_title($course->shortname . ': '. $concordance->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($concordance);

$output = $PAGE->get_renderer('mod_concordance');

echo $output->header();

echo $output->heading(format_string($concordance->name), 2);

echo $output->footer();