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
define([], function() {
    var aliasmap = {};

    var r = {

        registerButton: function(fieldname, value, alias) {

            if (alias !== '' && alias !== null) {
                aliasmap[fieldname] = alias;
            }

            var button = document.querySelector('button[id$="' + CSS.escape(fieldname + '__button') + '"]');

            // Find the matching submit.
            var iter = button;
            while (iter && !iter.classList.contains('formulation')) {
                iter = iter.parentElement;
            }
            var submit = false;
            if (iter && iter.classList.contains('formulation')) {
                // iter now represents the borders of the question containing
                // this button.
                // In Moodle inputs that are behaviour variables use `-` as a separator
                // for the name and usage id.
                submit = iter.querySelector('.im-controls *[id$="-submit"][type=submit]');
            }

            // Only do this if it is even possible.
            if (submit) {
                button.addEventListener('click', () => {
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
                        var f = document.getElementById(field);
                        if (f) {
                            f.value = value;
                            f.dispatchEvent(new Event('change'));
                        }
                    });

                    submit.click();
                    return false;
                });
            } else {
                console.log("Cannot have buttons tied to submit buttons if no submit buttons are present. Check question behaviour related settings.");
            }
        }
    };

    return r;
});