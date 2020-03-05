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

defined('MOODLE_INTERNAL') || die;

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
            'url' => $concordance->updatemod_url()->out(false), 'statusname' => 'task'.$status, 'statusclass' => $status);
        $phasesetup['tasks'][] = array('name' => get_string('task_quizselection', 'mod_concordance'), 'url' => '',
            'statusname' => 'tasktodo', 'statusclass' => 'todo');
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
        $phasepanelists['tasks'][] = array('name' => get_string('task_contactpanelists', 'mod_concordance'), 'url' => '',
            'statusname' => 'taskfail', 'statusclass' => 'fail');

        $phasestudents = array();
        $phasestudents['name'] = get_string('phase_students', 'mod_concordance');
        $phasestudents['switchphase'] = $concordance->switchphase_url(concordance::CONCORDANCE_PHASE_STUDENTS)->out(false);
        $phasestudents['switchnext'] = null;
        $phasestudents['isactive'] = ($concordance->get('activephase') == concordance::CONCORDANCE_PHASE_STUDENTS) ? true : false;
        $phasestudents['islast'] = true;
        $phasestudents['tasks'] = array();
        $phasestudents['tasks'][] = array('name' => get_string('task_generatequiz', 'mod_concordance'), 'url' => '',
            'statusname' => 'taskfail', 'statusclass' => 'fail');

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
}
