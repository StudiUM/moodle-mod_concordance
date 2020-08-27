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
 * Prints the list of all concordance activities in the course.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

unset($id);

require_course_login($course, true);
$PAGE->set_pagelayout('incourse');

// Get all required strings.
$strconcordances        = get_string('modulenameplural', 'mod_concordance');
$strconcordance         = get_string('modulename', 'mod_concordance');
$strname         = get_string('name');
$strphase        = get_string('phase', 'mod_concordance');
$strlastmodified = get_string('lastmodified');

$PAGE->set_url('/mod/concordance/index.php', array('id' => $course->id));
$PAGE->set_title($course->shortname.': '.$strconcordances);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strconcordances);
echo $OUTPUT->header();

\mod_concordance\event\course_module_instance_list_viewed::create_from_course($course)->trigger();

// Get all the appropriate data.
if (!$concordances = get_all_instances_in_course('concordance', $course)) {
    notice(get_string('thereareno', 'moodle', $strconcordances), "$CFG->wwwroot/course/view.php?id=$course->id");
    die;
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head  = array ($strsectionname, $strname, $strphase);
    $table->align = array ('center', 'left', 'left');
} else {
    $table->head  = array ($strlastmodified, $strname, $strphase);
    $table->align = array ('left', 'left', 'left');
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($concordances as $concordance) {
    $cm = $modinfo->get_cm($concordance->coursemodule);

    if ($usesections) {
        $printsection = get_section_name($course, $concordance->section);
    } else {
        $printsection = html_writer::tag('span', userdate($concordance->timemodified), array('class' => 'smallinfo'));
    }

    $class = $concordance->visible ? null : array('class' => 'dimmed'); // Hidden modules are dimmed.

    $concordancepersistent = new \mod_concordance\concordance($concordance->id);
    switch ($concordancepersistent->get('activephase')) {
        case \mod_concordance\concordance::CONCORDANCE_PHASE_SETUP:
            $phase = get_string('phase_setup', 'mod_concordance');
        break;
        case \mod_concordance\concordance::CONCORDANCE_PHASE_PANELISTS:
            $phase = get_string('phase_panelists', 'mod_concordance');
        break;
        case \mod_concordance\concordance::CONCORDANCE_PHASE_STUDENTS:
            $phase = get_string('phase_students', 'mod_concordance');
        break;
        default:
            $phase = '';
        break;
    }

    $table->data[] = array (
        $printsection,
        html_writer::link(new moodle_url('view.php', array('id' => $cm->id)), format_string($concordance->name), $class),
        $phase);
}

echo html_writer::table($table);

echo $OUTPUT->footer();
