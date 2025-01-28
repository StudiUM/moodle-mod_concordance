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

namespace mod_concordance;

use context_module;
use context_course;
use qformat_xml;
use moodle_exception;
use core_question\local\bank\question_edit_contexts;
use qbank_importquestions\form\question_import_form;
use Exception;
use mod_quiz\quiz_settings;
use stdClass;


/**
 * Class to duplicate the questions in in q new quiz
 *
 * @package    mod_concordance
 * @copyright  2024 Université de Montréal
 * @author     Gurvan Giboire <gurvan.giboire@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questions_import_export_handler {

    /** @var string  Filename to export temporary the questions of a quiz */
    protected string $filename;

    /**
     * Constructor
     *
     * @param quiz_settings $quizsettings The quiz settings where to duplicate the questions
     * @param array $questions Questions to duplicate
     */
    public function __construct(
        /** @var quiz_settings */
        protected quiz_settings $quizsettings,
        /** @var array */
        protected array $questions,

    ) {
        $this->filename = sprintf("/tmp/concordance_questions_%d.xml", time());
    }

    /**
     * Export all the questions in a file as XML
     *
     * @return void
     */
    private function export_questions_as_xml_in_file(): void {
        $exporter = new qformat_xml();
        try {
            $file = fopen($this->filename, "w");
            fwrite($file, "<quiz>");
            foreach ($this->questions as $key => $question) {
                fwrite($file, $exporter->writequestion($question));
                // Give new slot in order to match with the new one !
                $question->slot = $key + 1;
            }
            fwrite($file, "<quiz>");
            fclose($file);
        } catch (Exception $e) {
            throw new moodle_exception("Unable to export the question in xml file");
        }
    }

    /**
     * Remove the temporary export file
     *
     * @return void
     */
    private function remove_export_file(): void {
        unlink($this->filename);
    }

    /**
     * Import questions from the xml file
     *
     * @return void
     */
    public function duplicate_questions_in_quiz(): void {
        global $DB, $CFG;
        $this->export_questions_as_xml_in_file();

        require_once($CFG->dirroot . '/question/editlib.php');
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/lib/questionlib.php');

        $quiz = $this->quizsettings->get_quiz();
        $course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);
        $coursemodule = get_coursemodule_from_instance('quiz', $quiz->id);
        $quizcontext = context_module::instance($coursemodule->id, MUST_EXIST);
        $contexts = new question_edit_contexts($quizcontext);

        // Use existing questions category for quiz or create the defaults.
        $category = question_make_default_categories($contexts->all());

        $formatfile = $CFG->dirroot .  '/question/format/xml/format.php';
        if (!is_readable($formatfile)) {
            throw new moodle_exception('formatnotfound', 'question', '', 'xml');
        }

        require_once($formatfile);

        $qformat = new qformat_xml();
        $qformat->displayprogress = false;

        $qformat->setCategory($this->create_new_category());
        $qformat->setContexts([$quizcontext]);
        $qformat->setCourse($course);
        $qformat->setFilename($this->filename);
        $qformat->setRealfilename($this->filename);
        $qformat->setMatchgrades('nearest');
        $qformat->setStoponerror(true);

        if (!$qformat->importpreprocess()) {
            throw new moodle_exception('question_import');
        }

        if (!$qformat->importprocess($category)) {
            throw new moodle_exception('question_import');
        }

        if (!$qformat->importpostprocess()) {
            throw new moodle_exception('question_import');
        }

        $addonpage = 1;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        foreach ($qformat->questionids as $addquestion) {
            quiz_require_question_use($addquestion);
            quiz_add_quiz_question($addquestion, $quiz, $addonpage);
            quiz_delete_previews($quiz);
        }

        $this->remove_export_file();
        $this->update_pages();
    }

    /**
     * Create a new category specific for those questions
     *
     * @return stdClass
     */
    private function create_new_category(): stdClass {
        global $DB;
        $coursecontext = context_course::instance($this->quizsettings->get_courseid());
        $newcategory = new stdClass();
        $newcategory->parent = question_get_default_category($coursecontext->id)->id;
        $newcategory->contextid = $coursecontext->id;
        $newcategory->name = $this->build_category_name();
        $date = userdate(time(), get_string('strftimedatetime', 'langconfig'));
        $newcategory->info = get_string('questionscategoryinfo', 'mod_concordance', $date);
        $newcategory->sortorder = 999;
        $newcategory->stamp = make_unique_id_code();
        $newcategory->id = $DB->insert_record('question_categories', $newcategory);
        return $newcategory;
    }

    /**
     * Build the category name
     *
     * @return string
     */
    private function build_category_name(): string {
        $maxlen = strlen(get_string('questionscategoryname', 'mod_concordance', ''));
        $quizname = shorten_text($this->quizsettings->get_quiz_name(), 255 - $maxlen);
        return get_string('questionscategoryname', 'mod_concordance', $quizname);
    }

    /**
     * Update the page of the question based on the panelist questions
     *
     * @return void
     */
    private function update_pages(): void {
        global $DB;
        $this->quizsettings->preload_questions();
        $this->quizsettings->load_questions();
        foreach ($this->quizsettings->get_questions() as $question) {
            $keyoriginquestion = array_search($question->slot, array_column($this->questions, 'slot'));
            if ($keyoriginquestion === false) {
                continue;
            }
            $originquestion = $this->questions[$keyoriginquestion];
            if ($originquestion->page != $question->page) {
                $DB->update_record(
                    'quiz_slots',
                    ['id' => $question->slotid, "page" => $originquestion->page]
                );
            }
        }
    }
}
