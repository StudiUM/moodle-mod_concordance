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

defined('MOODLE_INTERNAL') || die();

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
                'type' => PARAM_INT
            ),
            'firstname' => array(
                'type' => PARAM_TEXT
            ),
            'lastname' => array(
                'type' => PARAM_TEXT
            ),
            'email' => array(
                'type' => PARAM_TEXT
            ),
            'nbemailsent' => array(
                'type' => PARAM_INT
            ),
            'bibliography' => array(
                'type' => PARAM_RAW
            ),
            'bibliographyformat' => array(
                'type' => PARAM_INT
            ),
        );
    }
}