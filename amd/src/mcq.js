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
 * A javascript module to add additional behaviour to MCQ inputs.
 *
 * @package    qtype_stateful
 * @copyright  2019 Matti Harjula
 * @copyright  2019 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/event'], function($, coreevent) {

    var r = {
        declareDropdown: function(fieldid, rawOptions) {
            // Rawoptions is a list of value-label-pairs, it is passed through AMD-apis
            // to avoid various Moodle filters breaking it.
            // Changes the HTML dropdown to slightly better.
            // The jQuery UI selectmenu is one option but Moodle does not like it.
            // So lets build our own.
            var oldDropDown = $('#' + $.escapeSelector(fieldid));

            // We will have a inline-block div as the selected value display and button.
            var button = document.createElement('div');
            button.className = 'stateful-mcq-select';

            // Selected label.
            var selected = document.createElement('div');
            selected.className = 'stateful-mcq-select-selected-label';
            button.appendChild(selected);

            // The carret at the end.
            var carret = document.createElement('div');
            carret.className = 'stateful-mcq-select-carret';
            carret.innerHTML = '▾';
            button.appendChild(carret);

            // The options exist in a table that will be absolutely positioned
            // under the button when the button is clicked, otherwise it will be
            // hidden.
            var table = document.createElement('table');
            table.className = 'stateful-mcq-select-table';

            // OnClick for the options.
            var selectOption = function() {
                // When an option gets selected.
                var jqthis = $(this);
                if (jqthis.data('value') !== oldDropDown.val()) {
                    oldDropDown.val(jqthis.data('value'));
                    oldDropDown.trigger('change');
                    oldDropDown.trigger('input');
                    // Unset the selected one in the table.
                    $('td', this.parentNode.parentNode).removeClass('stateful-mcq-select-table-selected');
                    $(this).addClass('stateful-mcq-select-table-selected');

                    // Hide the table and update the label.
                    selected.innerHTML = jqthis.data('orig-label');
                    coreevent.notifyFilterContentUpdated(selected);
                }
                $(this.parentNode.parentNode).hide();
            };

            // CSS:hover might be an option for this.
            var jqbutton = $(button);
            jqbutton.hover(function() {
                // On entter.
                $(table).show();
            }, function() {
                // On leave.
                $(table).hide();
            });

            // We could get the values of the options from the <option> elements
            // but the labels are a problem.
            var hasLabel = false;
            for (var i = 0; i < rawOptions.length; i++) {
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                var opt = rawOptions[i];
                td.innerHTML = opt.label;
                var jqtd = $(td);
                jqtd.data('value', opt.value);
                // Hold onto this.
                jqtd.data('orig-label', opt.label);
                jqtd.click(selectOption);
                if (oldDropDown.val() === opt.value) {
                    td.className = 'stateful-mcq-select-table-selected';
                    selected.innerHTML = opt.label;
                    hasLabel = true;
                }

                tr.appendChild(td);
                table.appendChild(tr);
            }
            if (!hasLabel) {
                // Nothing selected, display the first one.
                selected.innerHTML = rawOptions[0].label;
            }

            table.style.display = 'none';
            button.appendChild(table);

            // Replace the default dropdown with our new one.
            oldDropDown.hide();
            oldDropDown.after(button);
            // Render the MathJax just in case we have any.
            coreevent.notifyFilterContentUpdated(button);
        },

        deselectRadioSetup: function(fieldid) {
            // React to changes in input value.
            $('input[name=' + $.escapeSelector(fieldid) + ']').change(function() {
                if ($(this).val() === '%_unselect') {
                    $('#' + $.escapeSelector(fieldid) + '__unselect__row').hide();
                    $(this).prop('checked', false);
                } else {
                    $('#' + $.escapeSelector(fieldid) + '__unselect__row').show();
                }
            });

            // Handle initialisation when the value has been selected earlier.
            if ($('input[name=' + $.escapeSelector(fieldid) + ']').filter(':checked').length > 0) {
                $('#' + $.escapeSelector(fieldid) + '__unselect__row').show();
            }
        }
    };

    return r;
});