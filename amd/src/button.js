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
 * A javascript module to setup button type inputs.
 *
 * @package    qtype_stateful
 * @copyright  2019 Matti Harjula
 * @copyright  2019 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(["jquery"], function($) {
    var aliasmap = {};

    var r = {

        registerButton: function(fieldname, value, alias) {

            if (alias !== '' && alias !== null) {
                aliasmap[fieldname] = alias;
            }

            $('#' + $.escapeSelector(fieldname) + '__button').click(function() {
                // Find the parent div.
                var parent = $('#' + $.escapeSelector(fieldname) + '__button').closest('div.formulation');
                // Then the submit button.
                var submit = $('.im-controls > input[type=submit]', parent);

                // Before submit ensure that the value is placed.
                var fieldstoset = {};
                var i = fieldname;
                while (i in fieldstoset == false) {
                    fieldstoset[i] = true;
                    if (aliasmap[i] != undefined) {
                        i = aliasmap[i];
                    } else {
                        break;
                    }
                }

                Object.keys(fieldstoset).forEach(function(field) {
                    var f = $('#' + $.escapeSelector(field));
                    if (f) {
                        // Suppose the input is not present in the page...
                        f.attr('value', value);
                        f.trigger('change');
                    }
                });

                submit.trigger('click');
                return false;
            });
        }
    };

    return r;
});