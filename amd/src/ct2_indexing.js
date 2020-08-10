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
 * A javascript module to handle dynamic numbering.
 *
 * @package    qtype_stateful
 * @copyright  2020 Matti Harjula
 * @copyright  2020 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'qtype_stateful/ajaxvalidation'], function($, input2ajax) {
    "use strict";

    var update = function() {
        // For each indexing look for the spans.
        var idxs = $('div.stack_ct2_indexing');
        var updateSets = [];
        for (var i = 0; i < idxs.length; i++) {
            var idx = idxs[i];
            // attr vs. data as we need the raw form.
            var style = $(idx).attr('data-style');
            var start = $(idx).data('start');
            var name  = $(idx).attr('data-name');

            var items = [];
            if (name === undefined) {
                items = $('span.stack_ct2_index:not([name])', idx);
            } else {
                items = $('span.stack_ct2_index[name="' + $.escapeSelector(name) + '"]', idx);
            }
            // Evaluating that :visible. needs to be done before we start writing values.
            updateSets[updateSets.length] = [style, start, items.filter(':visible')];
        }

        var handled = [];
        // Reverse order is a must.
        for (var j = updateSets.length - 1; j >=0; j--) {
            var us = updateSets[j];
            var i = us[1];
            for (var k = 0; k < us[2].length; k++) {
                var item = us[2][k];
                if (handled.indexOf($(item).attr('data-c')) > -1) {
                    // If some deepper level has alredy dealt with it.
                    continue;
                } else {
                    handled[handled.length] = $(item).attr('data-c');
                }

                switch (us[0]) {
                    case '00':
                        item.innerHTML = (i + '').padStart(2, '0');
                        break;
                    case '000':
                        item.innerHTML = (i + '').padStart(3, '0');
                        break;
                    case '0000':
                        item.innerHTML = (i + '').padStart(4, '0');
                        break;
                    case '1':
                        item.innerHTML = i;
                        break;
                    case '1.':
                        item.innerHTML = i + '.';
                        break;
                    case '?':
                        item.innerHTML = '?';
                        break;
                    case ' ':
                        item.innerHTML = '';
                        break;
                    case 'I':
                        // We only go to four digit numbers and this is not
                        // the best ever algorithmn. Behaviour with negative
                        // values is not sensible.
                        var digit1 = i % 10;
                        var digit2 = (i-digit1)/10 % 10;
                        var digit3 = ((i-digit1)/10 - digit2)/10 % 10;
                        var digit4 = (((i-digit1)/10 - digit2)/10 -digit3)/10 % 10;
                        var r = '';
                        r += ['','M','MM','MMM','M&#773;V','&#773;V','&#773;VM','&#773;VMM','&#773;VMMM','M&#773;X'][digit4];
                        r += ['','C','CC','CCC','CD','D','DC','DCC','DCCC','CM'][digit3];
                        r += ['','X','XX','XXX','XL','L','LX','LXX','LXXX','XC'][digit2];
                        r += ['','I','II','III','IV','V','VI','VII','VIII','IX'][digit1];

                        item.innerHTML = r;
                        break;
                    default:
                        item.innerHTML = i;
                }
                i = i + 1;
            }
        }
    };

    var r = {
        init: function() {
            // Make sure we do the updates every time some input changes.
            input2ajax.registerListener(update);
            // Also update on init.
            update();
        }
    };

    return r;
});