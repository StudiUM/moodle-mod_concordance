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
 * This page lets users to add/edit panelist.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id         = optional_param('id', 0, PARAM_INT);
$cmid       = required_param('cmid', PARAM_INT);  // Course module.

$cm         = get_coursemodule_from_id('concordance', $cmid, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$concordance   = $DB->get_record('concordance', array('id' => $cm->instance), '*', MUST_EXIST);
$concordancepersistent   = new \mod_concordance\concordance($concordance->id);

$panelist = null;
if (!empty($id)) {
    $panelist = new \mod_concordance\panelist($id);
}
$context = context_module::instance($cm->id);
require_login($course, false, $cm);
require_capability('mod/concordance:addinstance', $context);

$title = get_string('panelistmanagement', 'mod_concordance');
$url = new moodle_url("/mod/concordance/editpanelist.php", [
    'id' => $id,
    'cmid' => $cmid
]);
$panelistsurl = new moodle_url('/mod/concordance/panelists.php', array('cmid' => $cmid));
$PAGE->navigation->override_active_url($panelistsurl);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading($context->get_context_name());
$PAGE->navbar->add($title, $panelistsurl);

if (empty($id)) {
    $subtitle = get_string('addnewpanelist', 'mod_concordance');
    $PAGE->navbar->add($subtitle, $url);
} else {
    $subtitle = get_string('editpanelist', 'mod_concordance');
    $PAGE->navbar->add($subtitle, $url);
}
$form = new \mod_concordance\form\panelist($url->out(false),
        array('persistent' => $panelist, 'context' => $context, 'concordanceid' => $concordance->id, 'id' => $id));
if ($form->is_cancelled()) {
    redirect($panelistsurl);
}

$data = $form->get_data();
if ($data) {
    $draftitemid = file_get_submitted_draft_itemid('bibliography');
    if (empty($data->id)) {
        $panelistpersistent = new \mod_concordance\panelist(0, $data);
        $panelistpersistent = $panelistpersistent->create();
        if ($draftitemid) {
            $bibliography = file_save_draft_area_files($draftitemid,
                    $context->id,
                    'mod_concordance',
                    'bibliography',
                    $panelistpersistent->get('id'),
                    concordance_get_editor_options($context),
                    $data->bibliography);
            $panelistpersistent->set('bibliography', $bibliography);
            $panelistpersistent->update();
        }
        $returnurl = new moodle_url('/mod/concordance/panelists.php', [
            'cmid' => $cmid
        ]);
        $returnmsg = get_string('panelistcreated', 'mod_concordance');
    } else {
        $panelistpersistent = new \mod_concordance\panelist($data->id);
        $panelistpersistent->from_record($data);
        if ($draftitemid) {
            $bibliography = file_save_draft_area_files($draftitemid,
                    $context->id,
                    'mod_concordance',
                    'bibliography',
                    $panelistpersistent->get('id'),
                    concordance_get_editor_options($context),
                    $data->bibliography);
            $panelistpersistent->set('bibliography', $bibliography);
        }

        $panelistpersistent->update();
        $returnmsg = get_string('panelistupdated', 'mod_concordance');
    }
    redirect($panelistsurl, $returnmsg, null, \core\output\notification::NOTIFY_SUCCESS);
}

$output = $PAGE->get_renderer('mod_concordance');
echo $output->header();
echo $output->heading($title);
echo $output->heading($subtitle, 3);

$form->display();

echo $output->footer();
