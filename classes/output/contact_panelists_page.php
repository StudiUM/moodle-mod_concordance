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
 * Class containing data for contactpanelists page
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_concordance\output;
defined('MOODLE_INTERNAL') || die();


use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Class containing data for contactpanelists page
 *
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contact_panelists_page implements renderable, templatable {

    /** @var int The course module id. */
    protected $cmid;

    /** @var \mod_concordance\panelist[] $panelists array of panelists. */
    protected $panelists = [];

    /** @var \mod_concordance\concordance The Concordance persistence object. */
    protected $concordance;

    /**
     * Construct this renderable.
     * @param int $cmid
     * @param Concordance $concordance Concordance persistence object.
     */
    public function __construct($cmid, $concordance) {
        $this->cmid = $cmid;
        $this->concordance = $concordance;
        $this->panelists = \mod_concordance\panelist::get_records(['concordance' => $concordance->get('id')]);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output Renderer base.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
        $data = new stdClass();
        $data->cmid = $this->cmid;
        $relateds = [
            'context' => \context_module::instance($this->cmid),
            'buttons' => new stdClass()
        ];
        $data->panelists = [];
        $data->haspanelists = (count($this->panelists) > 0) ? true : false;
        $usersattemptedquiz = \mod_concordance\quizmanager::getusersattemptedquiz($this->concordance);
        foreach ($this->panelists as $panelist) {
            $exporter = new \mod_concordance\external\panelist_exporter($panelist, $relateds);
            $exporteddata = $exporter->export($output);
            $exporteddata->quizstate = null;
            $exporteddata->quizstateclass = 'badge-info';

            if (key_exists($exporteddata->userid, $usersattemptedquiz)) {
                $state = $usersattemptedquiz[$panelist->get('userid')]->state;
                if (!empty($state)) {
                    $exporteddata->quizstate = get_string('state' . $state, 'mod_quiz');
                    if ($state == \quiz_attempt::FINISHED) {
                        $exporteddata->quizstateclass = 'badge-success';
                    }
                    if ($state == \quiz_attempt::IN_PROGRESS) {
                        $exporteddata->quizstateclass = 'badge-warning';
                    }
                } else {
                    $exporteddata->quizstate = get_string('notcompleted', 'mod_concordance');
                }
            } else {
                $exporteddata->quizstate = get_string('notcompleted', 'mod_concordance');
            }
            $data->panelists[] = $exporteddata;
        }
        $data->isquizselected = !empty($this->concordance->get('cmorigin'));
        $data->noquizselectedwarning = (object)array(
            'message' => get_string('noquizselected_cantcontact', 'mod_concordance'),
            'closebutton' => false
        );
        return $data;
    }
}
