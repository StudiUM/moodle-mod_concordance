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
 * Class containing data for managepanelists page
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_concordance\output;

use renderable;
use templatable;
use renderer_base;
use single_button;
use stdClass;
use moodle_url;

/**
 * Class containing data for managepanelists page
 *
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_panelists_page implements renderable, templatable {

    /** @var int The course module id. */
    protected $cmid;

    /** @var \mod_concordance\panelist[] $panelists array of panelists. */
    protected $panelists = [];

    /**
     * Construct this renderable.
     * @param int $cmid
     * @param int $concordanceid
     */
    public function __construct($cmid, $concordanceid) {
        $this->cmid = $cmid;
        $this->panelists = \mod_concordance\panelist::get_records(['concordance' => $concordanceid]);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output Renderer base.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->cmid = $this->cmid;
        $relateds = [
            'context' => \context_module::instance($this->cmid),
        ];
        $data->panelists = [];
        foreach ($this->panelists as $panelist) {
            $paramsurledit = ['cmid' => $this->cmid, 'id' => $panelist->get('id')];
            $paramsurldelete = ['cmid' => $this->cmid,
                'action' => 'delete', 'panelistid' => $panelist->get('id')];
            $deletebutton = new single_button(
                new moodle_url('/mod/concordance/panelists.php', $paramsurldelete),
                    get_string('delete'), 'get'
            );
            $deletebutton->add_confirm_action(get_string('deletepanelistconfirm', 'mod_concordance'));

            $editbutton = new single_button(
                new moodle_url('/mod/concordance/editpanelist.php', $paramsurledit),
                get_string('edit'), 'get'
            );
            $buttons = new \stdClass();
            $buttons->editbutton = $output->render($editbutton);
            $buttons->deletebutton = $output->render($deletebutton);
            $relateds['buttons'] = $buttons;
            $exporter = new \mod_concordance\external\panelist_exporter($panelist, $relateds);
            $data->panelists[] = $exporter->export($output);
        }
        $button = new single_button(
           new moodle_url('/mod/concordance/editpanelist.php', ['cmid' => $this->cmid]),
           get_string('addnewpanelist', 'mod_concordance'),
           'get'
        );
        $data->addbutton = $output->render($button);
        return $data;
    }
}
