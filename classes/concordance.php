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
 * Class for concordance persistence.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_concordance;

defined('MOODLE_INTERNAL') || die();

use \core\persistent;
use \moodle_url;

/**
 * Class for loading/storing concordance from the DB.
 *
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class concordance extends persistent {

    /** Table name for concordance persistency */
    const TABLE = 'concordance';

    /** Concordance phase : Setup. */
    const CONCORDANCE_PHASE_SETUP = 1;

    /** Concordance phase : Panelists are answering. */
    const CONCORDANCE_PHASE_PANELISTS = 2;

    /** Concordance phase : Preparation for students. */
    const CONCORDANCE_PHASE_STUDENTS = 3;

    /** Concordance task status : To do. */
    const CONCORDANCE_TASKSTATUS_TODO = 'todo';

    /** Concordance task status : Done. */
    const CONCORDANCE_TASKSTATUS_DONE = 'done';

    /** Concordance task status : Failed. */
    const CONCORDANCE_TASKSTATUS_FAILED = 'fail';

    /** Concordance task status : Info. */
    const CONCORDANCE_TASKSTATUS_INFO = 'info';

    /** @var stdClass $cm The course module. */
    protected $cm = null;

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'course' => array(
                'type' => PARAM_INT,
            ),
            'cmorigin' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'coursegenerated' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'cmgenerated' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'name' => array(
                'type' => PARAM_TEXT
            ),
            'descriptionpanelist' => array(
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED
            ),
            'descriptionpanelistformat' => array(
                'type' => PARAM_INT
            ),
            'descriptionstudent' => array(
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED
            ),
            'descriptionstudentformat' => array(
                'type' => PARAM_INT
            ),
            'activephase' => array(
                'choices' => array(
                    self::CONCORDANCE_PHASE_SETUP,
                    self::CONCORDANCE_PHASE_PANELISTS,
                    self::CONCORDANCE_PHASE_STUDENTS,
                ),
                'type' => PARAM_INT,
                'default' => self::CONCORDANCE_PHASE_SETUP,
            ),
        );
    }

    /**
     * Get the status for the settings task (if all fields are filled, the task is done, otherwise it is 'to do').
     *
     * @return string
     */
    public function get_status_settings() {
        if (empty($this->get('name')) || empty($this->get('descriptionpanelist')) || empty($this->get('descriptionstudent'))) {
            if ($this->get('activephase') == self::CONCORDANCE_PHASE_SETUP) {
                return self::CONCORDANCE_TASKSTATUS_TODO;
            } else {
                return self::CONCORDANCE_TASKSTATUS_FAILED;
            }
        } else {
            return self::CONCORDANCE_TASKSTATUS_DONE;
        }
    }

    /**
     * Get the status for the panelists task (if all fields are filled, the task is done, otherwise it is 'to do').
     *
     * @return string
     */
    public function get_status_panelists() {
        if (\mod_concordance\panelist::count_records_for_concordance($this->get('id')) < 1) {
            if ($this->get('activephase') == self::CONCORDANCE_PHASE_SETUP) {
                return self::CONCORDANCE_TASKSTATUS_TODO;
            } else {
                return self::CONCORDANCE_TASKSTATUS_FAILED;
            }
        } else {
            return self::CONCORDANCE_TASKSTATUS_DONE;
        }
    }

    /**
     * Returns the course module for this concordance instance.
     *
     * @return stdClass
     */
    public function get_cm() {
        if (is_null($this->cm)) {
            $this->cm = get_coursemodule_from_instance('concordance', $this->get('id'), $this->get('course'), true, MUST_EXIST);
        }
        return $this->cm;
    }

    /**
     * Returns the moodle_url of this concordance's view page.
     *
     * @return moodle_url of this concordance's view page
     */
    public function view_url() {
        return new moodle_url('/mod/concordance/view.php', array('id' => $this->get_cm()->id));
    }

    /**
     * Returns the moodle_url of this concordance's switch phase page.
     *
     * @param int $phase The internal phase code
     * @return moodle_url of the script to change the current phase to $phasecode
     */
    public function switchphase_url($phase) {
        $phase = clean_param($phase, PARAM_INT);
        return new moodle_url('/mod/concordance/switchphase.php', array('cmid' => $this->get_cm()->id, 'phase' => $phase));
    }

    /**
     * Returns the moodle_url of the mod_edit form for this concordance.
     *
     * @return moodle_url of the mod_edit form
     */
    public function updatemod_url() {
        return new moodle_url('/course/modedit.php', array('update' => $this->get_cm()->id, 'return' => 1));
    }

    /**
     * Returns the moodle_url of the panelists management page.
     *
     * @return moodle_url of the panelists management page
     */
    public function panelists_url() {
        return new moodle_url('/mod/concordance/panelists.php', ['cmid' => $this->get_cm()->id]);
    }

    /**
     * Hook to execute after a delete.
     *
     * @param bool $result Whether or not the delete was successful.
     * @return void
     */
    protected function after_delete($result) {
        if ($result) {
            $panelists = \mod_concordance\panelist::get_records(['concordance' => $this->get('id')]);
            foreach ($panelists as $panelist) {
                $panelist->delete();
            }
        }
    }
}