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
 * Renderer class for mod_concordance
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_concordance\output;

use plugin_renderer_base;
use mod_concordance\concordance;

/**
 * Renderer class for mod_concordance plugin.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Defer to template to render the wizard.
     *
     * @param concordance $concordance Concordance instance we want to show the wizard of.
     * @return string html for the page
     */
    public function render_wizard($concordance) {
        $data = new \stdClass();

        $phasesetup = array();
        $phasesetup['name'] = get_string('phase_setup', 'mod_concordance');
        $phasesetup['switchphase'] = $concordance->switchphase_url(concordance::CONCORDANCE_PHASE_SETUP)->out(false);
        $phasesetup['switchnext'] = $concordance->switchphase_url(concordance::CONCORDANCE_PHASE_PANELISTS)->out(false);
        $phasesetup['isactive'] = ($concordance->get('activephase') == concordance::CONCORDANCE_PHASE_SETUP) ? true : false;
        $phasesetup['islast'] = false;
        $phasesetup['tasks'] = array();
        $status = $concordance->get_status_settings();
        $phasesetup['tasks'][] = array('name' => get_string('task_editsettings', 'mod_concordance'),
            'url' => $concordance->updatemod_url()->out(false),
            'statusname' => get_string('task' . $status, 'mod_concordance'), 'statusclass' => $status);
        if ($status === concordance::CONCORDANCE_TASKSTATUS_FAILED) {
            $statusname = get_string('task' . concordance::CONCORDANCE_TASKSTATUS_INFO, 'mod_concordance');
            $statusinfo = concordance::CONCORDANCE_TASKSTATUS_INFO;
            if (empty($concordance->get('descriptionpanelist'))) {
                $phasesetup['tasks'][] = [
                    'name' => get_string('unfilledfield', 'mod_concordance', get_string('descriptionpanelist', 'mod_concordance')),
                    'url' => '',
                    'statusname' => $statusname, 'statusclass' => $statusinfo];
            }
            if (empty($concordance->get('descriptionstudent'))) {
                $phasesetup['tasks'][] = [
                    'name' => get_string('unfilledfield', 'mod_concordance', get_string('descriptionstudent', 'mod_concordance')),
                    'url' => '',
                    'statusname' => $statusname, 'statusclass' => $statusinfo];
            }
        }
        // Quiz selection.
        $dataquiz = null;
        if (!empty($concordance->get_cmidorigin())) {
            $page = new \mod_concordance\output\select_quiz_page($concordance->get_cmidorigin());
            $dataquiz = $page->export_for_template($this);
        }
        $status = $concordance->get_status_selectionquiz($dataquiz);
        $phasesetup['tasks'][] = array('name' => get_string('task_quizselection', 'mod_concordance'),
            'url' => $concordance->selectquiz_url()->out(false),
            'statusname' => get_string('task' . $status, 'mod_concordance'), 'statusclass' => $status);
        if ($dataquiz) {
            $statusinfo = concordance::CONCORDANCE_TASKSTATUS_INFO;
            $statusname = get_string('task' . $statusinfo, 'mod_concordance');
            if ($dataquiz->hasconcordancetype === false) {
                $phasesetup['tasks'][] = [
                    'name' => get_string('quizdoesnotcontainconcordancequestion', 'mod_concordance'),
                    'url' => '',
                    'statusname' => $statusname, 'statusclass' => $statusinfo];
            }
            if ($dataquiz->hasothertype === true) {
                $phasesetup['tasks'][] = [
                    'name' => get_string('quizcontainsotherquestion', 'mod_concordance'),
                    'url' => '',
                    'statusname' => $statusname, 'statusclass' => $statusinfo];
            }
            if ($dataquiz->visible == true) {
                $phasesetup['tasks'][] = [
                    'name' => get_string('quizvisibletostudent', 'mod_concordance'),
                    'url' => '',
                    'statusname' => $statusname, 'statusclass' => $statusinfo];
            }
        }
        $status = $concordance->get_status_panelists();
        $phasesetup['tasks'][] = array('name' => get_string('task_panelistsmanagement', 'mod_concordance'),
            'url' => $concordance->panelists_url()->out(false), 'statusname' => 'tasktodo', 'statusclass' => $status);

        $phasepanelists = array();
        $phasepanelists['name'] = get_string('phase_panelists', 'mod_concordance');
        $phasepanelists['switchphase'] = $concordance->switchphase_url(concordance::CONCORDANCE_PHASE_PANELISTS)->out(false);
        $phasepanelists['switchnext'] = $concordance->switchphase_url(concordance::CONCORDANCE_PHASE_STUDENTS)->out(false);
        $phasepanelists['isactive'] = ($concordance->get('activephase') == concordance::CONCORDANCE_PHASE_PANELISTS) ? true : false;
        $phasepanelists['islast'] = false;
        $phasepanelists['tasks'] = array();
        $status = $concordance->get_status_contactpanelists();
        $phasepanelists['tasks'][] = array('name' => get_string('task_contactpanelists', 'mod_concordance'),
            'url' => $concordance->contact_panelists_url()->out(false),
            'statusname' => get_string('task' . $status, 'mod_concordance'), 'statusclass' => $status);
        $nbcontacted = \mod_concordance\panelist::count_panelistscontacted_for_concordance($concordance->get('id'));
        $nbtotal = \mod_concordance\panelist::count_records_for_concordance($concordance->get('id'));
        if ($nbcontacted > 0 && ($nbtotal > $nbcontacted) ) {
            $statusinfo = concordance::CONCORDANCE_TASKSTATUS_INFO;
            $statusname = get_string('task' . $statusinfo, 'mod_concordance');
            $phasepanelists['tasks'][] = [
                'name' => get_string('atleastpanelistnotcontacted', 'mod_concordance'),
                'url' => '',
                'statusname' => $statusname, 'statusclass' => $statusinfo];
        }

        $phasestudents = array();
        $phasestudents['name'] = get_string('phase_students', 'mod_concordance');
        $phasestudents['switchphase'] = $concordance->switchphase_url(concordance::CONCORDANCE_PHASE_STUDENTS)->out(false);
        $phasestudents['switchnext'] = null;
        $phasestudents['isactive'] = ($concordance->get('activephase') == concordance::CONCORDANCE_PHASE_STUDENTS) ? true : false;
        $phasestudents['islast'] = true;
        $phasestudents['tasks'] = array();
        $statusinfo = concordance::CONCORDANCE_TASKSTATUS_INFO;
        $status = $concordance->get_status_generatestudentquiz();
        $phasestudents['tasks'][] = array('name' => get_string('task_generatequiz', 'mod_concordance'),
            'url' => $concordance->generate_studentquiz_url()->out(false),
            'statusname' => get_string('task' . $status, 'mod_concordance'), 'statusclass' => $status);
        if ($status == concordance::CONCORDANCE_TASKSTATUS_FAILED) {
            $nbpanelists = \mod_concordance\panelist::count_records_for_concordance($concordance->get('id'));
            $cmpanelistgenerated = get_coursemodule_from_id('quiz', $concordance->get('cmgenerated'));
            $statusinfo = concordance::CONCORDANCE_TASKSTATUS_INFO;
            $statusname = get_string('task' . $statusinfo, 'mod_concordance');
            if ($nbpanelists == 0) {
                $phasestudents['tasks'][] = [
                    'name' => get_string('nopanelists', 'mod_concordance'),
                    'url' => '',
                    'statusname' => $statusname, 'statusclass' => $statusinfo];
            }
            if (!$cmpanelistgenerated) {
                $phasestudents['tasks'][] = [
                    'name' => get_string('panelistsquiznotfound', 'mod_concordance'),
                    'url' => '',
                    'statusname' => $statusname, 'statusclass' => $statusinfo];
            }
        }

        $data->phases = array($phasesetup, $phasepanelists, $phasestudents);

        return parent::render_from_template('mod_concordance/wizard', $data);
    }

    /**
     * Defer to template.
     *
     * @param manage_panelists_page $page
     *
     * @return string html for the page
     */
    public function render_manage_panelists_page(manage_panelists_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('mod_concordance/manage_panelists_page', $data);
    }

    /**
     * Defer to template.
     *
     * @param contact_panelists_page $page
     *
     * @return string html for the page
     */
    public function render_contact_panelists_page(contact_panelists_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('mod_concordance/contact_panelists_page', $data);
    }

    /**
     * Defer to template.
     *
     * @param select_quiz_page $page
     *
     * @return string html for the page
     */
    public function render_select_quiz_page(select_quiz_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('mod_concordance/select_quiz_page', $data);
    }
}
