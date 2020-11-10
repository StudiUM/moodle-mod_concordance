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
 * Generate quiz for students.
 *
 * @module     mod_concordance/studentquizgeneration
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'],
    function($) {

        var SELECTORS = {
            PANELISTSSELECTEDCHECKBOXES: "input[name^='paneliststoinclude']:checked",
            PANELISTSCHECKBOXES: "input[name^='paneliststoinclude']",
            SUBMITBUTTON: "input[name='submitbutton']"
        };

        return /** @alias module:mod_concordance/studentquizgeneration */ {
            // Public variables and functions.

            /**
             * Initialise.
             *
             * @method init
             */
            'init': function() {
                $(SELECTORS.SUBMITBUTTON).prop('disabled', true);
                $(SELECTORS.PANELISTSCHECKBOXES).on('change', function() {
                    // Enable/disable submitbutton.
                    if ($(SELECTORS.PANELISTSSELECTEDCHECKBOXES).length > 0) {
                        $(SELECTORS.SUBMITBUTTON).prop('disabled', false);
                    } else {
                        $(SELECTORS.SUBMITBUTTON).prop('disabled', true);
                    }
                });
            }
        };
    });
