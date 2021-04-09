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
 * Class for exporting panelist data.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_concordance\external;
defined('MOODLE_INTERNAL') || die();

/**
 * Class for exporting panelist data.
 *
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class panelist_exporter extends \core\external\persistent_exporter {

    /**
     * Constructor.
     *
     * @param \core\persistent $persistent
     * @param array $related
     */
    public function __construct($persistent, $related = array()) {
        parent::__construct($persistent, $related);

        $this->data->bibliography = file_rewrite_pluginfile_urls(
            $this->persistent->get('bibliography'),
            'pluginfile.php',
            $related['context']->id,
            'mod_concordance',
            'bibliography',
            $this->persistent->get('id')
        );
        $this->data->nbemailsent = (empty($this->persistent->get('nbemailsent'))) ? 0 :
                $this->persistent->get('nbemailsent');
    }

    /**
     * Returns the specific class the persistent should be an instance of.
     *
     * @return string
     */
    protected static function define_class() {
        return 'mod_concordance\\panelist';
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related() {
        return [
            'context' => 'context',
            'buttons' => '\\stdClass'
        ];
    }

    /**
     * Returns the definition of other concordance properties.
     *
     * @return array
     */
    public static function define_other_properties() {
        return array(
            'editbutton' => array(
                'type' => PARAM_RAW,
            ),
            'deletebutton' => array(
                'type' => PARAM_RAW
            ),
        );
    }

    /**
     * Returns other panelist properties.
     *
     * @param  renderer_base $output
     * @return array
     */
    protected function get_other_values(\renderer_base $output) {
        $otherproperties = ['editbutton' => '', 'deletebutton' => ''];
        if (isset($this->related['buttons']->editbutton)) {
            $otherproperties['editbutton'] = $this->related['buttons']->editbutton;
        }
        if (isset($this->related['buttons']->deletebutton)) {
            $otherproperties['deletebutton'] = $this->related['buttons']->deletebutton;
        }

        return $otherproperties;
    }
}
