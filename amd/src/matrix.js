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
 * A javascript module to add resize behaviour to matrix inputs.
 *
 * @package    qtype_stateful
 * @copyright  2020 Matti Harjula
 * @copyright  2020 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/event'], function($, coreevent) {

    var r = {
        /* Essenttially react to the small + and - buttons and
         * hides or displays rows and updates things.
         * If the illconceived reverse numbering is in use
         * updates them as well.
         */
        registerRowMod: function(name, prefix, min, max, reversenumberedleft, reversenumberedright) {
            $('#' + $.escapeSelector(prefix + name + '__addrow')).click(function() {
                var current = $('#' + $.escapeSelector(prefix + name + '__rows'));
                var cv = parseInt(current.val(), 10);
                if (cv < max) {
                    // If we grow we need to enable the shrink.
                    $('#' + $.escapeSelector(prefix + name + '__remrow')).prop('disabled', false);

                    var table = $('#' + $.escapeSelector(prefix + name + '__0_0')).closest('table');

                    // Up the size.
                    cv = cv + 1;
                    current.val('' + cv);
                    current.trigger('change');
                    current.trigger('input');
                    // If we grow we need to check if we can grow more.
                    // If not then disable the option.
                    if (cv == max) {
                        $('#' + $.escapeSelector(prefix + name + '__addrow')).prop('disabled', true);
                    }

                    // Show the new relevant bits.
                    $('tr.row' + (cv - 1), table).show();

                    // The labels...
                    if (reversenumberedleft) {
                        $('th.lnum:visible', table).each(function(i) {
                            $(this).html((cv - i) + '.');
                        });
                    }
                    if (reversenumberedright) {
                        $('th.rnum:visible', table).each(function(i) {
                            $(this).html((cv - i) + '.');
                        });
                    }
                }
                return false;
            });
            $('#' + $.escapeSelector(prefix + name + '__remrow')).click(function() {
                var current = $('#' + $.escapeSelector(prefix + name + '__rows'));
                var cv = parseInt(current.val(), 10);
                if (cv > min) {
                    // If we shrink we need to enable the grow.
                    $('#' + $.escapeSelector(prefix + name + '__addrow')).prop('disabled', false);

                    var table = $('#' + $.escapeSelector(prefix + name + '__0_0')).closest('table');

                    // Cut the size.
                    cv = cv - 1;
                    current.val('' + cv);
                    current.trigger('change');
                    current.trigger('input');
                    // If we shring we need to check if we can shrink more.
                    // If not then disable the option.
                    if (cv == min) {
                        $('#' + $.escapeSelector(prefix + name + '__remrow')).prop('disabled', true);
                    }

                    // Hide the old bit.
                    $('tr.row' + cv, table).hide();

                    // The labels...
                    if (reversenumberedleft) {
                        $('th.lnum:visible', table).each(function(i) {
                            $(this).html((cv - i) + '.');
                        });
                    }
                    if (reversenumberedright) {
                        $('th.rnum:visible', table).each(function(i) {
                            $(this).html((cv - i) + '.');
                        });
                    }
                }
                return false;
            });
        },
        registerColMod: function(name, prefix, min, max, reversenumberedtop, reversenumberedbottom) {

            $('#' + $.escapeSelector(prefix + name + '__addcol')).click(function() {
                var current = $('#' + $.escapeSelector(prefix + name + '__cols'));
                var cv = parseInt(current.val(), 10);
                if (cv < max) {
                    // If we grow we need to enable the shrink.
                    $('#' + $.escapeSelector(prefix + name + '__remcol')).prop('disabled', false);

                    var table = $('#' + $.escapeSelector(prefix + name + '__0_0')).closest('table');

                    // Up the size.
                    cv = cv + 1;
                    current.val('' + cv);
                    current.trigger('change');
                    current.trigger('input');
                    // If we grow we need to check if we can grow more.
                    // If not then disable the option.
                    if (cv == max) {
                        $('#' + $.escapeSelector(prefix + name + '__addcol')).prop('disabled', true);
                    }

                    // Show the new relevant bits.
                    $('td.col' + (cv - 1), table).show();
                    $('th.col' + (cv - 1), table).show();

                    // The labels...
                    if (reversenumberedtop) {
                        $('th.tnum:visible', table).each(function(i) {
                            $(this).html((cv - i) + '.');
                        });
                    }
                    if (reversenumberedbottom) {
                        $('th.bnum:visible', table).each(function(i) {
                            $(this).html((cv - i) + '.');
                        });
                    }
                }
                return false;
            });
            $('#' + $.escapeSelector(prefix + name + '__remcol')).click(function() {
                var current = $('#' + $.escapeSelector(prefix + name + '__cols'));
                var cv = parseInt(current.val(), 10);
                if (cv > min) {
                    // If we shrink we need to enable the grow.
                    $('#' + $.escapeSelector(prefix + name + '__addcol')).prop('disabled', false);

                    var table = $('#' + $.escapeSelector(prefix + name + '__0_0')).closest('table');

                    // Cut the size.
                    cv = cv - 1;
                    current.val('' + cv);
                    current.trigger('change');
                    current.trigger('input');
                    // If we shring we need to check if we can shrink more.
                    // If not then disable the option.
                    if (cv == min) {
                        $('#' + $.escapeSelector(prefix + name + '__remcol')).prop('disabled', true);
                    }

                    // Hide the old bit.
                    $('td.col' + cv, table).hide();
                    $('th.col' + cv, table).hide();

                    // The labels...
                    if (reversenumberedtop) {
                        $('th.tnum:visible', table).each(function(i) {
                            $(this).html((cv - i) + '.');
                        });
                    }
                    if (reversenumberedbottom) {
                        $('th.bnum:visible', table).each(function(i) {
                            $(this).html((cv - i) + '.');
                        });
                    }
                }
                return false;
            });
        }
    };

    return r;
});