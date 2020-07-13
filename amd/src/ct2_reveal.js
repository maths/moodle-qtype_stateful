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
 * A javascript module to handle the reveal block
 *
 * @package    qtype_stateful
 * @copyright  2020 Matti Harjula
 * @copyright  2020 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'qtype_stateful/ajaxvalidation', 'qtype_stateful/stack_util'], function($, input2ajax, sutil) {
    "use strict";

    var toCheck = [];
    var registered = false;

    function check() {
        for (var i = 0; i < toCheck.length; i++) {
            var element = document.getElementById(toCheck[i]);
            var el = $('#' + toCheck[i]);
            var ival = '';
            if (sutil.is_radio(element, el.attr('data-input'))) {
                var rads = $('input[name="'
                             + $.escapeSelector(sutil.find_input_name(element, el.attr('data-input')))
                             + '"][type=radio]');
                rads = rads.filter(':checked');
                if (rads.length > 0) {
                    ival = rads.val();
                }
            } else {
                ival = $('#' + $.escapeSelector(sutil.find_input_id(element, el.attr('data-input')))).val();
            }
            if (ival === el.attr('data-val')) {
                el.show();
            } else {
                el.hide();
            }
        }
    }

    return {
        init: function(id) {
            var element = document.getElementById(id);
            if (element) {
                toCheck[toCheck.length] = id;
            } else {
                return;
            }
            var el = $(element);

            var ival = '';
            if (sutil.is_radio(element, el.attr('data-input'))) {
                var rads = $('input[name="'
                             + $.escapeSelector(sutil.find_input_name(element, el.attr('data-input')))
                             + '"][type=radio]');
                rads = rads.filter(':checked');
                if (rads.length > 0) {
                    ival = rads.val();
                }
            } else {
                ival = $('#' + $.escapeSelector(sutil.find_input_id(element, el.attr('data-input')))).val();
            }

            var value = el.attr('data-val');

            // If we start with the value display the default hidden block.
            if (ival === value) {
                el.show();
            }

            if (!registered) {
                input2ajax.registerListener(check);
                registered = true;
            }
        }
    };
});
