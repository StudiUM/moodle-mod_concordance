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
 * Class for panelist persistence.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_concordance;

use \core\persistent;

/**
 * Class for loading/storing panelist from the DB.
 *
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panelist extends persistent {

    /** Table name for panelist persistency */
    const TABLE = 'concordance_panelist';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'concordance' => array(
                'type' => PARAM_INT
            ),
            'userid' => array(
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'firstname' => array(
                'type' => PARAM_TEXT
            ),
            'lastname' => array(
                'type' => PARAM_TEXT
            ),
            'email' => array(
                'type' => PARAM_EMAIL
            ),
            'nbemailsent' => array(
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'bibliography' => array(
                'type' => PARAM_RAW
            ),
            'bibliographyformat' => array(
                'type' => PARAM_INT
            ),
        );
    }

    /**
     * Count the number of panelists for a concordance.
     *
     * @param  int $concordanceid The concordance ID
     * @return int
     */
    public static function count_records_for_concordance($concordanceid) {
        $filters = array('concordance' => $concordanceid);
        return self::count_records($filters);
    }

    /**
     * Count the number of panelists contacted for a concordance.
     *
     * @param  int $concordanceid The concordance ID
     * @return int
     */
    public static function count_panelistscontacted_for_concordance($concordanceid) {
        $params['concordance'] = $concordanceid;
        return self::count_records_select("nbemailsent >= 1 AND concordance = :concordance", $params);
    }

    /**
     * Hook to execute after a create.
     *
     * @return void
     */
    protected function after_create() {
        \mod_concordance\panelistmanager::panelistcreated($this);
    }

    /**
     * Hook to execute after a delete.
     *
     * @param bool $result Whether or not the delete was successful.
     * @return void
     */
    protected function after_delete($result) {
        if ($result) {
            \mod_concordance\panelistmanager::panelistdeleted($this->get('userid'));
        }
    }

    /**
     * Get user key.
     *
     * @return string
     */
    public function get_user_key() {
        if ($this->get('userid')) {
            return get_user_key('concordancepanelist', $this->get('userid'));
        }
        return null;
    }

    /**
     * Get user.
     *
     * @return stdClass user
     */
    public function get_user() {
        global $DB;
        if ($this->get('userid')) {
            return $DB->get_record('user', array('id' => $this->get('userid')));
        }
        return null;
    }

    /**
     * Get url of quiz access page.
     *
     * @return moodle_url The quiz access page.
     */
    public function get_quizaccess_url() {
        return new \moodle_url('/mod/concordance/quizaccess.php', array('key' => $this->get_user_key()));
    }

    /**
     * Return true if this panelist attempted this quiz, false otherwise.
     *
     * @param int $quizid The quiz id to check.
     * @return boolean
     */
    public function has_attempted_quiz($quizid) {
        global $DB;
        return $DB->record_exists('quiz_attempts', array('quiz' => $quizid, 'userid' => $this->get('userid')));
    }

    /**
     * Get panelists by ids.
     *
     * @param array $ids Array of ids
     * @param int $concordanceid concordance id
     * @return panelist[] Return array of panelists.
     */
    public static function get_concordancepanelists_by_ids($ids, $concordanceid) {
        global $DB;
        $panelists = [];
        if (!empty($ids)) {
            list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $params['concordance'] = $concordanceid;
            $panelists = self::get_records_select("id $insql AND concordance = :concordance", $params);
        }
        return $panelists;
    }
}
