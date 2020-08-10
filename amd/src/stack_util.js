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
 * A javascript module with some basic utilities for dealing with
 * STACK/Stateful.
 *
 * @package    qtype_stateful
 * @copyright  2020 Matti Harjula
 * @copyright  2020 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    "use strict";

    var r = {
        /**
         * Finds an input with a given name from within the question
         * that contains the relatedelement.
         * @param  {element} any element within a question
         * @param  {string} name    name of the input
         * @return {string}         the id of the input-element
         */
        find_input_id: function(relatedelement, name) {
            var tmp = relatedelement;
            while ((tmp = tmp.parentElement) &&
                   !(tmp.classList.contains("formulation") &&
                     tmp.parentElement.classList.contains("content"))) {}
            var tmp2 = tmp.querySelector('input[id$="_' + name + '"]');
            if(tmp2) {
                return tmp2.id;
            }
            tmp = tmp.querySelector('select[id$="_' + name + '"]');
            return tmp.id;
        },
        /**
         * Same but finds the name, note that radio buttons might need this.
         */
        find_input_name: function(relatedelement, name) {
            var tmp = relatedelement;
            while ((tmp = tmp.parentElement) &&
                   !(tmp.classList.contains("formulation") &&
                     tmp.parentElement.classList.contains("content"))) {}
            var tmp2 = tmp.querySelector('input[name$="_' + name + '"]');
            if(tmp2) {
                return tmp2.name;
            }
            tmp = tmp.querySelector('select[name$="_' + name + '"]');
            return tmp.name;
        },
        is_radio: function(relatedelement, name) {
            var tmp = relatedelement;
            while ((tmp = tmp.parentElement) &&
                   !(tmp.classList.contains("formulation") &&
                     tmp.parentElement.classList.contains("content"))) {}
            var tmp2 = tmp.querySelector('input[id$="_' + name + '"]');
            if (tmp2) {
                return false;
            }
            tmp2 = tmp.querySelector('input[name$="_' + name + '"][type=radio]');
            if (tmp2) {
                return true;
            }

            return false;
        },
        is_select: function(relatedelement, name) {
            var tmp = relatedelement;
            while ((tmp = tmp.parentElement) &&
                   !(tmp.classList.contains("formulation") &&
                     tmp.parentElement.classList.contains("content"))) {}
            var tmp2 = tmp.querySelector('select[id$="_' + name + '"]');
            if (tmp2) {
                return true;
            }
            tmp2 = tmp.querySelector('select[name$="_' + name + '"]');
            if (tmp2) {
                return true;
            }
            return false;
        }
    };

    return r;
});