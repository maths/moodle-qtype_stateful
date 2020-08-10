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
 * A javascript module to add the behaviour to order inputs.
 *
 * @package    qtype_stateful
 * @copyright  2020 Matti Harjula
 * @copyright  2020 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'qtype_stateful/ajaxvalidation'], function($, input2ajax) {

    function placeBlanks(listelement, template, vertical, indent) {
        // clean first.
        $('.stateful-order-list-blank', listelement).remove();
        var beforeFixed = {};
        var currentcount = 0;
        var childs = $(listelement).children('div');
        for(var i = 0; i < childs.length; i++) {
            var element = childs[i];
            var el = $(element);
            if (el.hasClass('stateful-order-list-fixed-item')) {
                beforeFixed[el.attr('data-token')] = currentcount;
                currentcount = 0;
                if (indent) {
                    if (!(template.length == 0 || (template[0][0] === 'fixed' && template[0][1] === el.attr('data-token')))) {
                        el.before('<div class="stateful-order-list-blank" data-token="' + el.attr('data-token') + '">?</div>');
                    }
                } else {
                    if (!(template.length == 0 || (template[0][0] === 'fixed' && template[0][1][0] === el.attr('data-token')))) {
                        el.before('<div class="stateful-order-list-blank" data-token="' + el.attr('data-token') + '">?</div>');
                    }
                }
            } else {
                currentcount++;
                el.before('<div class="stateful-order-list-blank" data-token="' + el.attr('data-token') + '">?</div>');
            }
        }
        if ($(listelement).hasClass('stateful-order-list')) {
            beforeFixed['%%list%%'] = currentcount;
        } else {
            beforeFixed['%%shuffle%%'] = currentcount;
        }
        // and after every one if we have no template fixing the end.
        if (template.length == 0 || template[template.length-1][0] !== 'fixed') {
            if ($(listelement).hasClass('stateful-order-list')) {
                $(listelement).append('<div class="stateful-order-list-blank" data-token="%%list%%">?</div>');
            } else {
                $(listelement).append('<div class="stateful-order-list-blank" data-token="%%shuffle%%">?</div>');
            }
        }

        // If the template has some limits we might make some blanks visible even when no drag is happening.
        var expected = {};
        currentcount = 0;
        for (var i = 0; i < template.length; i++) {
            var item = template[i];
            if (item[0] === 'fixed') {
                if (indent) {
                    expected[item[1][0]] = currentcount;
                } else {
                    expected[item[1]] = currentcount;
                }
                currentcount = 0;
            } else if (item[0] === 'empty') {
                currentcount += item[1];
            } else {
                currentcount++;
            }
        }
        if ($(listelement).hasClass('stateful-order-list')) {
            expected['%%list%%'] = currentcount;
        } else {
            expected['%%shuffle%%'] = currentcount;
        }
        for (var key in expected) {
            if (expected[key] > beforeFixed[key]) {
                var el = $(listelement).children('.stateful-order-list-blank')
                                       .filter('[data-token="' + $.escapeSelector(key) + '"]');
                if (vertical) {
                    el.css('display', 'block');
                } else {
                    el.css('display', 'inline-block');
                }
            }
        }
    }

    function activateBlanks(listelement, dragged, template, vertical, indent) {
        // Figure out the max counts for fixed intervals.
        var expected = {};
        var currentcount = 0;
        for (var i = 0; i < template.length; i++) {
            var item = template[i];
            if (item[0] === 'fixed') {
                if (indent) {
                    expected[item[1][0]] = currentcount;
                } else {
                    expected[item[1]] = currentcount;
                }
                currentcount = 0;
            } else if (item[0] === 'empty') {
                if (item.length > 2) {
                    currentcount += item[2];
                } else {
                    currentcount += item[1];
                }
            } else {
                currentcount ++;
            }
        }
        if (template.length === 0) {
            currentcount = 999;
        }
        if ($(listelement).hasClass('stateful-order-list')) {
            expected['%%list%%'] = currentcount;
        } else {
            expected['%%shuffle%%'] = currentcount;
        }

        var beforeFixed = {};
        var current = [];
        var last = null;
        var childs = $(listelement).children('div');
        for(var i = 0; i < childs.length; i++) {
            var element = childs[i];
            var el = $(element);
            if (el.hasClass('stateful-order-list-blank')) {
                if (last !== dragged && el.attr('data-token') !== dragged) {
                    current[current.length] = el;
                }
            }
            if (el.hasClass('stateful-order-list-fixed-item')) {
                beforeFixed[el.attr('data-token')] = current;
                current = [];
            }
            if (el.hasClass('stateful-order-list-item')) {
                last = el.attr('data-token');
            }
        }
        if ($(listelement).hasClass('stateful-order-list')) {
            beforeFixed['%%list%%'] = current;
        } else {
            beforeFixed['%%shuffle%%'] = current;
        }
        for (var key in expected) {
            if (expected[key] >= beforeFixed[key].length) {
                for (var j = 0; j < beforeFixed[key].length; j++) {
                    el = beforeFixed[key][j];
                    if (vertical) {
                        el.css('display', 'block');
                    } else {
                        el.css('display', 'inline-block');
                    }
                }
            }
        }
    }

    function doMove(targetblank, moved, inputname, indent) {
        var theToken = $('div.stateful-order-list-item[data-token="'
                         + $.escapeSelector(moved) + '"]', targetblank.parentElement.parentElement.parentElement);
        theToken.detach();
        $(targetblank).before(theToken[0]);
        var target = $(targetblank).attr('data-token');

        var input = $(document.getElementById(inputname));
        var current = JSON.parse(input.val());
        if (indent) {
            current = current.filter((x) => x[0] !== moved);
        } else {
            current = current.filter((x) => x !== moved);
        }
        if (target === '%%list%%') {
            if (indent) {
                current[current.length] = [moved, theToken.data('indent')];
            } else {
                current[current.length] = moved;
            }
        } else if (!indent && current.indexOf(target) >= 0) {
            current.splice(current.indexOf(target), 0, moved);
        } else if (indent) {
            var replacement = [];
            for (var i of current) {
                if (i[0] === target) {
                    replacement[replacement.length] = [moved, theToken.data('indent')];
                }
                replacement[replacement.length] = i;
            }
            current = replacement;
        }
        input.val(JSON.stringify(current));

        var sb = $(document.getElementById(inputname + '__sb'));
        if (sb.length > 0) {
            current = JSON.parse(sb.val());
            if (indent) {
                current = current.filter((x) => x[0] !== moved);
            } else {
                current = current.filter((x) => x !== moved);
            }
            if (target === '%%shuffle%%') {
                if (indent) {
                    current[current.length] = [moved, theToken.data('indent')];
                } else {
                    current[current.length] = moved;
                }
            } else if (!indent && current.indexOf(target) >= 0) {
                current.splice(current.indexOf(target), 0, moved);
            } else if (indent) {
                var replacement = [];
                for (var i of current) {
                    if (i[0] === target) {
                        replacement[replacement.length] = [moved, theToken.data('indent')];
                    }
                    replacement[replacement.length] = i;
                }
                current = replacement;
            }
            sb.val(JSON.stringify(current));
        }
        input.trigger('input');
        input.trigger('change');
    }

    function updateIndent(element, mod, maxindent, indent, inputname) {
        var newindent = element.data('indent') + mod;
        if (newindent < 0 || newindent > maxindent) {
            return;
        }
        var internal = '';
        if (newindent > 0) {
            internal = ('&nbsp;'.repeat(indent)).repeat(newindent);
        }
        element.data('indent', newindent);
        element.find('.stateful-order-indent').html(internal);

        // Update the inputs.
        var input = $(document.getElementById(inputname));
        var current = JSON.parse(input.val());
        var change = false;
        current = current.map((x) => {
            if (x[0] === element.attr('data-token')) {
                x[1] = newindent;
                change = true;
            }
            return x;
        });
        if (change) {
            input.val(JSON.stringify(current));
            input.trigger('input');
            input.trigger('change');
        }
        change = false;
        input = $(document.getElementById(inputname + '__sb'));
        current = JSON.parse(input.val());
        current = current.map((x) => {
            if (x[0] === element.attr('data-token')) {
                x[1] = newindent;
                change = true;
            }
            return x;
        });
        if (change) {
            input.val(JSON.stringify(current));
            input.trigger('input');
            input.trigger('change');
        }
    }

    var r = {
        inPlaceInit: function(inputname, readonly, vertical) {
            if (!readonly) {
                var container = $(document.getElementById(inputname).parentElement);
                var listcontainer = $('.stateful-order-list', container);
                // Add some blanks.
                placeBlanks(listcontainer, [], vertical, false);
                // Make the items draggable.
                container.find('.stateful-order-list-item').attr('draggable', 'true');
                container.find('.stateful-order-list-item').on('dragstart', (event) => {
                    // Activate blanks.
                    event.originalEvent.dataTransfer.setData('text/plain', $(event.target).attr('data-token'));
                    event.originalEvent.dataTransfer.effectAllowed = "move";
                    setTimeout((x) => {
                        // We just need to make the blanks appear after the item gets lifted for drag.
                        activateBlanks(listcontainer, $(event.target).attr('data-token'), [], vertical, false);

                        container.find('.stateful-order-list-blank').on('drop', (event) => {
                            event.preventDefault();
                            doMove(event.target, event.originalEvent.dataTransfer.getData('text/plain'), inputname, false);
                        });

                        container.find('.stateful-order-list-blank').on('dragover', (event) => {
                            event.preventDefault();
                            event.originalEvent.dataTransfer.dropEffect = "move";
                        });
                    }, 20);
                });

                container.find('.stateful-order-list-item').on('dragend', (event) => {
                    // Clear visibility.
                    placeBlanks(listcontainer, [], vertical, false);
                });
            }

            // Check that all possible numbering logic have been updated.
            setTimeout((x) => {input2ajax.executeListeners();}, 20);
        },

        fillInInit: function(inputname, limits, readonly, vertical) {
            if (!readonly) {
                var container = $(document.getElementById(inputname).parentElement);
                var listcontainer = $('.stateful-order-list', container);
                var shufflebox = $('.stateful-order-shufflebox', container);
                var template = container.data('template');
                // Add some blanks.
                placeBlanks(listcontainer, template, vertical, false);
                placeBlanks(shufflebox, [], vertical, false);

                // Make the items draggable.
                container.find('.stateful-order-list-item').attr('draggable', 'true');
                container.find('.stateful-order-list-item').on('dragstart', (event) => {
                    // Activate blanks.
                    event.originalEvent.dataTransfer.setData('text/plain', $(event.target).attr('data-token'));
                    event.originalEvent.dataTransfer.effectAllowed = "move";

                    setTimeout((x) => {
                        // We just need to make the blanks appear after the item gets lifted for drag.
                        activateBlanks(listcontainer, $(event.target).attr('data-token'), template, vertical, false);
                        activateBlanks(shufflebox, $(event.target).attr('data-token'), [], vertical, false);

                        container.find('.stateful-order-list-blank').on('drop', (event) => {
                            event.preventDefault();
                            doMove(event.target, event.originalEvent.dataTransfer.getData('text/plain'), inputname, false);
                        });

                        container.find('.stateful-order-list-blank').on('dragover', (event) => {
                            event.preventDefault();
                            event.originalEvent.dataTransfer.dropEffect = "move";
                        });
                    }, 20);
                });

                container.find('.stateful-order-list-item').on('dragend', (event) => {
                    // Clear visibility.
                    placeBlanks(listcontainer, template, vertical, false);
                    placeBlanks(shufflebox, [], vertical, false);
                });
            }

            // Check that all possible numbering logic have been updated.
            setTimeout((x) => {input2ajax.executeListeners();}, 20);
        },

        fillInIndentInit: function(inputname, limits, readonly) {
            if (!readonly) {
                var container = $(document.getElementById(inputname).parentElement);
                var listcontainer = $('.stateful-order-list', container);
                var shufflebox = $('.stateful-order-shufflebox', container);
                var template = container.data('template');
                var indent = container.data('indent');
                var maxindent = container.data('maxindent');

                // Add some blanks.
                placeBlanks(listcontainer, template, false, true);
                placeBlanks(shufflebox, [], false, true);

                // Make the items draggable.
                container.find('.stateful-order-list-item').attr('draggable', 'true');
                container.find('.stateful-order-list-item').on('dragstart', (event) => {
                    // Activate blanks.
                    event.originalEvent.dataTransfer.setData('text/plain', $(event.target).attr('data-token'));
                    event.originalEvent.dataTransfer.effectAllowed = "move";

                    setTimeout((x) => {
                        // We just need to make the blanks appear after the item gets lifted for drag.
                        activateBlanks(listcontainer, $(event.target).attr('data-token'), template, false, true);
                        activateBlanks(shufflebox, $(event.target).attr('data-token'), [], false, true);

                        container.find('.stateful-order-list-blank').on('drop', (event) => {
                            event.preventDefault();
                            doMove(event.target, event.originalEvent.dataTransfer.getData('text/plain'), inputname, true);
                        });

                        container.find('.stateful-order-list-blank').on('dragover', (event) => {
                            event.preventDefault();
                            event.originalEvent.dataTransfer.dropEffect = "move";
                        });
                    }, 20);
                });

                container.find('.stateful-order-list-item').on('dragend', (event) => {
                    // Clear visibility.
                    placeBlanks(listcontainer, template, false, true);
                    placeBlanks(shufflebox, [], false, true);
                });

                // Add indent buttons.
                container.find('.stateful-order-list-item')
                         .append('<div class="stateful-order-indent-plus" style="display:none;">⇥</div>');
                container.find('.stateful-order-list-item')
                         .append('<div class="stateful-order-indent-minus" style="display:none;">⇤</div>');

                container.find('.stateful-order-indent-plus').on('click', (event) => {
                    event.preventDefault();
                    updateIndent($(event.target.parentElement), 1, maxindent, indent, inputname);
                });
                container.find('.stateful-order-indent-minus').on('click', (event) => {
                    event.preventDefault();
                    updateIndent($(event.target.parentElement), -1, maxindent, indent, inputname);
                });
                container.find('.stateful-order-list-item').on('mouseenter', (event) => {
                    var element = $(event.target);
                    element.find('.stateful-order-indent-plus').show();
                    element.find('.stateful-order-indent-minus').show();
                });
                container.find('.stateful-order-list-item').on('mouseleave', (event) => {
                    var element = $(event.target);
                    element.find('.stateful-order-indent-plus').hide();
                    element.find('.stateful-order-indent-minus').hide();
                });
                container.on('mouseenter', (event) => {
                    var element = $(event.target);
                    element.find('.stateful-order-indent-plus').hide();
                    element.find('.stateful-order-indent-minus').hide();
                });
            }

            // Check that all possible numbering logic have been updated.
            setTimeout((x) => {input2ajax.executeListeners();}, 20);
        },
    };

    return r;
});