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
 * Class containing data for select quiz page page
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
use stdClass;
use mod_quiz\quiz_settings;

/**
 * Class containing data for select quiz page.
 *
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class select_quiz_page implements renderable, templatable {

    /** @var int The course module id. */
    protected $cmid;

    /**
     * Construct this renderable.
     * @param int $cmid
     */
    public function __construct($cmid) {
        $this->cmid = $cmid;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output Renderer base.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $USER, $CFG;
        require_once($CFG->dirroot.'/mod/quiz/locallib.php');

        $data = new stdClass();
        $data->hasconcordancetype = false;
        $data->hasothertype = false;

        $cmorigin = get_coursemodule_from_id('quiz', $this->cmid);
        $quizobj = quiz_settings::create($cmorigin->instance, $USER->id);
        // Fully load all the questions in this quiz.
        $quizobj->preload_questions();
        $quizobj->load_questions();
        $questions = $quizobj->get_questions();
        $concordancetypefound = false;
        $othertypefound = false;
        foreach ($questions as $question) {
            if (\question_bank::make_question($question) instanceof \qtype_tcs_question) {
                $data->hasconcordancetype = true;
                $concordancetypefound = true;
            } else {
                $data->hasothertype = true;
                $othertypefound = true;
            }

            if ($othertypefound && $concordancetypefound) {
                break;
            }
        }

        $data->visible = $cmorigin->visible;
        return $data;
    }
}
