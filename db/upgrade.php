<?php
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
 * Stateful question type upgrade rules.
 *
 * @copyright  2019 Matti Harjula
 * @copyright  2019 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

function xmldb_qtype_stateful_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if (false) {
        // Replace input system, this is held here for future STACK use.
        // No actual upgrade for import, this is just for the development
        // phase questions. Clean away after 2022, or once stack has input2.
        $inputs = $DB->get_records('qtype_stateful_inputs', null, null, '*');
        foreach ($inputs as $input) {
            $options = json_decode($input->options, true);
            $newoptions = [];
            // Common stuff, some renames.
            if (isset($options['showValidation'])) {
                switch ($options['showValidation']) {
                    case 0:
                        $newoptions['validation-box'] = '';
                        break;
                    case 1:
                        $newoptions['validation-box'] = 'automatic with list of variables';
                        if ($input->type === 'units') {
                            $newoptions['validationbox'] = 'automatic with list of units';
                        }
                        break;
                    case 2:
                        $newoptions['validation-box'] = 'automatic without listings';
                        break;
                }
            }
            if (isset($options['mustVerify'])) {
                $newoptions['must-verify'] = $options['mustVerify'];
            }
            if (isset($options['hideanswer'])) {
                $newoptions['hide-answer'] = $options['hideanswer'];
            }
            if (isset($options['boxWidth'])) {
                $newoptions['input-width'] = $options['boxWidth'];
            }
            if (isset($options['forbidWords']) && $options['forbidWords'] !== '') {
                $newoptions['forbid-words'] = $options['forbidWords'];
            }
            if (isset($options['allowWords']) && $options['forbidWords'] !== '') {
                $newoptions['allow-words'] = $options['allowWords'];
            }
            if (isset($options['forbidFloats'])) {
                $newoptions['forbid-floats'] = $options['forbidFloats'];
            }
            if (isset($options['sameType'])) {
                $newoptions['require-same-type'] = $options['sameType'];
            }
            if (isset($options['syntaxAttribute'])) {
                $newoptions['syntax-hint-type'] = $options['syntaxAttribute'];
            }
            if (isset($options['syntaxHint']) && $options['syntaxHint'] !== '') {
                $newoptions['syntax-hint'] = $options['syntaxHint'];
            }
            if (isset($options['lowestTerms']) && $options['lowestTerms']) {
                $newoptions['require-lowest-terms'] = $options['lowestTerms'];
            }

            if (isset($options['insertStars'])) {
                switch ($options['insertStars']) {
                    case 0:
                        $newoptions['fix-spaces'] = false;
                        $newoptions['fix-stars'] = false;
                        $newoptions['split-to-single-letter-variables'] = false;
                        break;
                    case 1:
                        $newoptions['fix-spaces'] = false;
                        $newoptions['fix-stars'] = true;
                        $newoptions['split-to-single-letter-variables'] = false;
                        break;
                    case 2:
                        $newoptions['fix-spaces'] = false;
                        $newoptions['fix-stars'] = true;
                        $newoptions['split-to-single-letter-variables'] = true;
                        break;
                    case 3:
                        $newoptions['fix-spaces'] = true;
                        $newoptions['fix-stars'] = false;
                        $newoptions['split-to-single-letter-variables'] = false;
                        break;
                    case 4:
                        $newoptions['fix-spaces'] = true;
                        $newoptions['fix-stars'] = true;
                        $newoptions['split-to-single-letter-variables'] = false;
                        break;
                    case 5:
                        $newoptions['fix-spaces'] = true;
                        $newoptions['fix-stars'] = true;
                        $newoptions['split-to-single-letter-variables'] = true;
                        break;
                }
            }
            if (isset($options['strictSyntax']) && $options['strictSyntax']) {
                $newoptions['fix-spaces'] = false;
                $newoptions['fix-stars'] = false;
            }

            switch ($input->type) {
                case 'string':
                    // None of the old ones were textareas.
                    $newoptions['input-height'] = 1;
                    break;
                case 'dropdown':
                    // The old way of defining MCQ options needs to be
                    // handled, we provide a wrapper that allows the old
                    // way as it might have its uses.
                    $input->type = 'mcq_legacy';
                    $newoptions['mcq-type'] = 'dropdown';
                    $newoptions['mcq-legacy-options'] = $input->tans;
                    $input->tans = '"moved to legacy-options"';
                    if (isset($options['options']) && $options['options'] !== '') {
                        if (strpos($options['options'], 'nonotanswered') !== false) {
                            $newoptions['mcq-no-deselect'] = true;
                        }
                        if (strpos($options['options'], 'casstring') !== false) {
                            $newoptions['mcq-label-default-render'] = 'value';
                        }
                        if (stripos($options['options'], 'latex') !== false) {
                            $newoptions['mcq-label-default-render'] = 'latex';
                        }
                    }
                    break;
                case 'radio':
                    $input->type = 'mcq_legacy';
                    $newoptions['mcq-type'] = 'radio';
                    $newoptions['mcq-legacy-options'] = $input->tans;
                    $input->tans = '"moved to legacy-options"';
                    if (isset($options['options']) && $options['options'] !== '') {
                        if (strpos($options['options'], 'nonotanswered') !== false) {
                            $newoptions['mcq-no-deselect'] = true;
                        }
                        if (strpos($options['options'], 'casstring') !== false) {
                            $newoptions['mcq-label-default-render'] = 'value';
                        }
                        if (stripos($options['options'], 'latex') !== false) {
                            $newoptions['mcq-label-default-render'] = 'latex';
                        }
                    }
                    break;
                case 'units':
                case 'numerical':
                case 'algebraic':

                    // Nothing special.
                    break;
                case 'button':
                    if (isset($options['aliasfor'])) {
                        $newoptions['alias-for'] = $options['aliasfor'];
                    }
                    if (isset($options['value'])) {
                        $newoptions['input-value'] = $options['value'];
                    }
                    // The new label definition is in CASText.
                    $newoptions['input-label'] = '{@' . $input->tans . '@}';
                    break;
            }

            $input->options = json_encode($newoptions);
            $DB->update_record('qtype_stateful_inputs', $input);
        }
    }

    if ($oldversion < 2020013100) {
        // Renamed Units as UnitsSigFigs.
        $nodes = $DB->get_records('qtype_stateful_prt_nodes', ['test' => 'Units'], null, '*');
        foreach ($nodes as $node) {
            $node->test = 'UnitsSigFigs';
            $DB->update_record('qtype_stateful_prt_nodes', $node);
        }
    }

    if ($oldversion < 2020020100) {
        // Renamed UnitsStrict as UnitsStrictSigFigs.
        $nodes = $DB->get_records('qtype_stateful_prt_nodes', ['test' => 'UnitsStrict'], null, '*');
        foreach ($nodes as $node) {
            $node->test = 'UnitsStrictSigFigs';
            $DB->update_record('qtype_stateful_prt_nodes', $node);
        }
    }

    $latest = 2020020100;
    if ($oldversion < $latest) {

        upgrade_plugin_savepoint(true, $latest, 'qtype', 'stateful');
        // For every update we clear the compile caches.
        $DB->execute('UPDATE {qtype_stateful_options} SET compiledcache = ?', ['']);
    }

    return true;
}
