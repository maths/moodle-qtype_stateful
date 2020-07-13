// This file is part of Stateful
//
// Stateful is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stateful is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stateful.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A javascript module to handle AJAX validation of inputs.
 *
 * @package    qtype_stateful
 * @copyright  2019 Matti Harjula
 * @copyright  2019 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/event'], function($, ajax, coreevent) {
    "use strict";

    var StatefulInput2 = {
        typingDelay: 1000, /* ms */

        timeoutHandles: {}, /* The debounce handles for multiple questions */

        activeFields: {}, /* inputs to collect when doing validation calls,
                             note can track multiple questions */
        listeners: [] /* Other things requiring notifications of changed inputs. */
    };

    var r = {
        register: function(fields, prefix) {
            if (!(prefix in StatefulInput2.activeFields)) {
                StatefulInput2.activeFields[prefix] = {};
            }

            // Remember what fields have been registered for this prefix.
            for (var i = 0; i < fields.length; i++) {
                StatefulInput2.activeFields[prefix][fields[i]] = true;
            }

            if (!(prefix in StatefulInput2.timeoutHandles)) {
                StatefulInput2.timeoutHandles[prefix] = null;
            }

            var details = $('#' + $.escapeSelector(prefix) + '__tech_details');
            var qaid = details.data('qaid');
            var seqn = details.data('seqn');

            // For each new input registered add debounced change listener
            // that sends every input of this prefix to be validated and replaces
            // all the validation boxes with the results. also sets the _val inputs.

            var upload = function() {
                // Collect values of all tracked inputs.
                var data = {};
                Object.keys(StatefulInput2.activeFields[prefix]).forEach(function(key) {
                    var field = $('#' + $.escapeSelector(prefix + key));
                    if (field.length === 1) {
                        // Singular element e.g. textarea or some input
                        // other than radio.
                        if (field.first().prop('type') === 'checkbox') {
                            if (field.first().prop('checked')) {
                                data[key] = 'true';
                            }
                        } else {
                            data[key] = field.val();
                        }
                    } else {
                        // Something else i.e. radio where we
                        // have multiple with the same name.
                        field = $('input[name=' + $.escapeSelector(prefix + key) + ']:checked');
                        if (field.length === 1) {
                            data[key] = field.first().val();
                        }
                    }
                });
                // As we are dealing with PARAM_RAW and there is no PARAM_JSON.
                data = JSON.stringify(data);

                // Push the data up and be happy.
                ajax.call([{
                    methodname: 'qtype_stateful_validate_input',
                    args: {qaid: qaid, seqn: seqn, data:data},
                    done: function(response) {
                        // Parse.
                        var val_update = JSON.parse(response.val_update);
                        var vbox_update = JSON.parse(response.vbox_update);

                        // Assign new values to _val fields.
                        Object.keys(val_update).forEach(function(key) {
                            var field = $('#' + $.escapeSelector(prefix + key));
                            // TODO: only update if actually changed.
                            field.val(val_update[key]);
                            field.trigger('change');
                            field.trigger('input');
                        });
                        Object.keys(vbox_update).forEach(function(key) {
                            var vbox = $('#' + $.escapeSelector(prefix + 'vbox_' + key));
                            // TODO: only update if actually changed.
                            vbox.html(vbox_update[key]);
                            if (vbox_update[key] === '') {
                                vbox.hide();
                            } else {
                                vbox.show();
                            }
                            coreevent.notifyFilterContentUpdated(vbox);
                        });

                        // Let our listeners know that something may have changed.
                        // Again, but we need to deal with validation message related
                        // stuff as well.
                        for (var i = 0; i < StatefulInput2.listeners.length; i++) {
                            StatefulInput2.listeners[i]();
                        }
                    }
                }]);
            };

            for (var i = 0; i < fields.length; i++) {
                var field = $('#' + $.escapeSelector(prefix + fields[i]));
                field.on('input', function() {
                    // Let our listeners know that something may have changed.
                    for (var i = 0; i < StatefulInput2.listeners.length; i++) {
                        StatefulInput2.listeners[i]();
                    }

                    if (StatefulInput2.timeoutHandles[prefix] !== null) {
                        clearTimeout(StatefulInput2.timeoutHandles[prefix]);
                        StatefulInput2.timeoutHandles[prefix] = null;
                    }
                    StatefulInput2.timeoutHandles[prefix] = setTimeout(upload, StatefulInput2.typingDelay);
                });

                $('input[type=radio][name="' + $.escapeSelector(prefix + fields[i]) + '"]').on('click', function() {
                    // Let our listeners know that something may have changed.
                    for (var i = 0; i < StatefulInput2.listeners.length; i++) {
                        StatefulInput2.listeners[i]();
                    }

                    if (StatefulInput2.timeoutHandles[prefix] !== null) {
                        clearTimeout(StatefulInput2.timeoutHandles[prefix]);
                        StatefulInput2.timeoutHandles[prefix] = null;
                    }
                    StatefulInput2.timeoutHandles[prefix] = setTimeout(upload, StatefulInput2.typingDelay);
                });

                $('select[name="' + $.escapeSelector(prefix + fields[i]) + '"]').on('change', function() {
                    // Let our listeners know that something may have changed.
                    for (var i = 0; i < StatefulInput2.listeners.length; i++) {
                        StatefulInput2.listeners[i]();
                    }

                    if (StatefulInput2.timeoutHandles[prefix] !== null) {
                        clearTimeout(StatefulInput2.timeoutHandles[prefix]);
                        StatefulInput2.timeoutHandles[prefix] = null;
                    }
                    StatefulInput2.timeoutHandles[prefix] = setTimeout(upload, StatefulInput2.typingDelay);
                });
            }
        },

        registerListener: function(listener) {
            StatefulInput2.listeners[StatefulInput2.listeners.length] = listener;
        }
    };

    return r;
});