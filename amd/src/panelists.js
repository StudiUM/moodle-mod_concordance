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
 * Send emails for panelist page.
 *
 * @module     mod_concordance/panelists
 * @package    mod_concordance
 * @copyright  2020 Université de Montréal
 * @author     Issam Taboubi <issam.taboubi@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/templates', 'core/notification', 'core/ajax'],
    function ($, Str, ModalFactory, ModalEvents, Templates, Notification, Ajax) {

        var SELECTORS = {
            SENDACTIONBUTTON: "#showpanelistemailpopup",
            PANELISTSSELECTEDCHECKBOXES: "input[name='panelists']:checked",
            PANELISTSCHECKBOXES: "input[name='panelists']",
            CHECKALLLINK: "#checkall",
            CHECKNONELINK: "#checknone"
        };

        /**
         * Constructor
         *
         * @param {Object} options Object containing options.
         */
        var Panelists = function (options) {

            this.cmid = options.cmid;

            this.attachEventListeners();
        };

        // Class variables and functions.

        /**
         * @var {Modal} modal
         * @private
         */
        Panelists.prototype.modal = null;

        /**
         * @var {int} Course module id
         * @private
         */
        Panelists.prototype.cmid = -1;

        /**
         * Private method
         *
         * @method attachEventListeners
         * @private
         */
        Panelists.prototype.attachEventListeners = function () {
            $(SELECTORS.SENDACTIONBUTTON).on('click', function (e) {
                e.preventDefault();

                var ids = [];
                $(SELECTORS.PANELISTSSELECTEDCHECKBOXES).each(function (index, ele) {
                    var id = $(ele).val();
                    ids.push(id);
                });

                this.showSendEmail(ids).fail(Notification.exception);
            }.bind(this));

            $(SELECTORS.CHECKALLLINK).on('click', function (e) {
                e.preventDefault();
                $(SELECTORS.PANELISTSCHECKBOXES).prop('checked', true);
                $(SELECTORS.SENDACTIONBUTTON).prop('disabled', false);
            });

            $(SELECTORS.CHECKNONELINK).on('click', function (e) {
                e.preventDefault();
                $(SELECTORS.PANELISTSCHECKBOXES).prop('checked', false);
                $(SELECTORS.SENDACTIONBUTTON).prop('disabled', true);
            });

            $(SELECTORS.PANELISTSCHECKBOXES).on('change', function () {
                // Enable/disable buttonsend.
                if ($(SELECTORS.PANELISTSSELECTEDCHECKBOXES).length > 0) {
                    $(SELECTORS.SENDACTIONBUTTON).prop('disabled', false);
                } else {
                    $(SELECTORS.SENDACTIONBUTTON).prop('disabled', true);
                }
            });
        };

        /**
         * Show the send email popup.
         *
         * @method showSendEmail
         * @private
         * @param {int[]} users
         * @return {Promise}
         */
        Panelists.prototype.showSendEmail = function (users) {

            if (users.length == 0) {
                // Nothing to do.
                return $.Deferred().resolve().promise();
            }
            var titlePromise = null;
            if (users.length == 1) {
                titlePromise = Str.get_string('sendbulkmessagesingle', 'core_message');
            } else {
                titlePromise = Str.get_string('sendbulkmessage', 'core_message', users.length);
            }

            return $.when(
                    ModalFactory.create({
                        type: ModalFactory.types.SAVE_CANCEL,
                        body: Templates.render('mod_concordance/send_bulk_email', {})
                    }),
                    titlePromise
                    ).then(function (modal, title) {
                        // Keep a reference to the modal.
                        this.modal = modal;

                        this.modal.setTitle(title);
                        this.modal.setSaveButtonText(title);

                        this.modal.getRoot().on(ModalEvents.hidden, function () {
                            $(SELECTORS.SENDACTIONBUTTON).focus();
                            this.modal.getRoot().remove();
                        }.bind(this));

                        this.modal.getRoot().on(ModalEvents.save, this.submitSendEmail.bind(this, users));
                        var self = this;
                        this.modal.getRoot().on('change keyup', '#subject-bulk-email, #body-bulk-email', function () {
                            var messageText = self.modal.getRoot().find('form textarea').val();
                            var subject = self.modal.getRoot().find('form input').val();
                            messageText = messageText.trim();
                            subject = subject.trim(subject);
                            if (messageText.length === 0 || subject.length === 0) {
                                self.modal.getRoot().find('button[data-action="save"]').prop('disabled', true);
                            } else {
                                self.modal.getRoot().find('button[data-action="save"]').prop('disabled', false);
                            }
                        });

                        this.modal.show();

                        return this.modal;
                    }.bind(this));
        };

        /**
         * Send a message to these users.
         *
         * @method submitSendEmail
         * @private
         * @param {int[]} users
         * @param {Event} e Form submission event.
         * @return {Promise}
         */
        Panelists.prototype.submitSendEmail = function (users) {

            var messageText = this.modal.getRoot().find('form textarea').val();
            var subject = this.modal.getRoot().find('form input').val();

            return Ajax.call([{
                methodname: 'mod_concordance_send_message',
                args: {users: users, message: messageText, subject: subject, displaynotification: true}
                }])[0].then(function (result) {
                    if (result) {
                        window.location.reload(true);
                    }
                }).catch(Notification.exception);
        };

        return /** @alias module:mod_concordance/panelists */ {
            // Public variables and functions.

            /**
             * Initialise.
             *
             * @method init
             * @param {Object} options - List of options.
             * @return {Panelists}
             */
            'init': function (options) {
                return new Panelists(options);
            }
        };
    });
