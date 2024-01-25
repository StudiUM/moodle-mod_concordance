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
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/yui'],
    function($, Y) {

        var SELECTORS = {
            PANELISTSSELECTEDCHECKBOXES: "input[name^='paneliststoinclude']:checked",
            PANELISTSCHECKBOXES: "input[name^='paneliststoinclude']",
            QUESTIONSSELECTEDCHECKBOXES: "input[name^='questionstoinclude']:checked",
            QUESTIONSCHECKBOXES: "input[name^='questionstoinclude']",
            SUBMITBUTTON: "input[name='submitbutton']",
            SECTIONSELECTOR: ".concordancequestionsection",
            FORMSELECTOR: "#generatestudentquizform",
            QUIZNAME: "#generatestudentquizform input[name='name']",
            QUESTIONSINCLUDEERROR: "#questionstoincludeerror",
            PANELISTSINCLUDEERROR: "#paneliststoincludeerror"
        };

        var validateform = function() {
            if ($(SELECTORS.PANELISTSSELECTEDCHECKBOXES).length > 0 &&
                    $(SELECTORS.QUESTIONSSELECTEDCHECKBOXES).length > 0 &&
                    $(SELECTORS.QUIZNAME).val() !== '') {
                $(SELECTORS.SUBMITBUTTON).prop('disabled', false);
            } else {
                $(SELECTORS.SUBMITBUTTON).prop('disabled', true);
            }
            if ($(SELECTORS.PANELISTSSELECTEDCHECKBOXES).length === 0) {
                $(SELECTORS.PANELISTSINCLUDEERROR).show();
            } else {
                $(SELECTORS.PANELISTSINCLUDEERROR).hide();
            }
            if ($(SELECTORS.QUESTIONSSELECTEDCHECKBOXES).length === 0) {
                $(SELECTORS.QUESTIONSINCLUDEERROR).show();
            } else {
                $(SELECTORS.QUESTIONSINCLUDEERROR).hide();
            }
        };

        var checkquestionremove = function() {
            var valid = true;
            $(SELECTORS.SECTIONSELECTOR).each(function() {
                if ($(this).find(SELECTORS.QUESTIONSSELECTEDCHECKBOXES).length === 0) {
                    valid = false;
                    return false;
                }
            });

            if (valid === false) {
                Y.use('moodle-core-notification-alert', function() {
                    var alert = new M.core.alert({
                        title: M.util.get_string('cannotremoveslots', 'mod_quiz'),
                        message: M.util.get_string('cannotremoveallsectionslots', 'mod_concordance')
                    });

                    alert.show();
                });
                return false;
            }
            return true;
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
                $(SELECTORS.PANELISTSCHECKBOXES + ',' + SELECTORS.QUESTIONSCHECKBOXES).on('change', function() {
                    // Enable/disable submitbutton.
                    validateform();
                });
                $(SELECTORS.QUIZNAME).on('change', function() {
                    // Enable/disable submitbutton.
                    validateform();
                });
                validateform();
                // Check questions removal on submit.
                $(SELECTORS.FORMSELECTOR).on("submit", function() {
                    return checkquestionremove();
                });
            }
        };
    });
