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
require_once __DIR__ . '/../generic_input_bases.php';
require_once __DIR__ . '/../../castext2/utils.php';
require_once __DIR__ . '/algebraic.input.php';
require_once __DIR__ . '/../../../../../../filter/mathjaxloader/filter.php';

/**
 * This is the base class of all MCQ inputs. Note that it extends 
 * the algebraic input to provide "other" as valid field.
 */
class stateful_input_mcq extends stateful_input_algebraic {


    // The maximum number of checkboxes, for out of order execution.
    public const MAX_CHECKBOXES = 50;

    /**
     * The options that have been evaluated value => label array.
     */
    private $mcqoptions = null;

    // Separate list of checked options. For the chekcbox mode.
    private $checked;

    public function get_type(): string {
        return 'mcq';
    }

    protected function collect_value(array $response) {
        // Collect the value.
        $this->rawvalue = '';
        if (isset($response[$this->get_name()])) {
            switch ($this->get_option('mcq-type')) {
                case 'radio with other':
                if ($response[$this->get_name()] === '%_other') {
                    $this->rawvalue = $response[$this->get_name() . '__other'];
                    break;
                }
                case 'radio':
                case 'dropdown':
                    // Values of form "%0", "%1"...
                    $i = intval(substr($response[$this->get_name()], 1));
                    if ($response[$this->get_name()] === '') {
                        $i = -1;
                    }
                    $j = 0;
                    foreach ($this->mcqoptions as $value => $label) {
                        if ($i === $j) {
                            $this->rawvalue = $value;
                            break;
                        }
                        $j = $j + 1;
                    }
                break;
            }
        }
        if ($this->get_option('mcq-type') === 'checkbox') {
            $this->checked = [];
            $this->rawvalue = '[';
            $first = true;
            $i = 0;
            foreach ($this->mcqoptions as $value => $label) {
                if (isset($response[$this->get_name() . '__' . $i])) {
                    if ($response[$this->get_name() . '__' . $i] === 'true') {
                        $this->checked[$i] = true;
                        if ($first) {
                            $first = false;
                            $this->rawvalue .= $value;
                        } else {
                            $this->rawvalue .= ',' . $value;
                        }
                    } else {
                        $this->checked[$i] = false;
                    }
                } else {
                    $this->checked[$i] = false;
                }
                $i = $i + 1;
            }
            $this->rawvalue .= ']';
        }
        // Also the validation.
        $this->val = null;
        if (isset($response[$this->get_name() . '__val'])) {
            $this->val = $response[$this->get_name() . '__val'];
        }
    }

    public function render_controls(array $values, string $prefix, bool $readonly = false): string {

        $main = '';

        $fieldname = $prefix . $this->get_name();
        $attributes = array(
            'name'  => $fieldname,
            'id'    => $fieldname
        );
        if ($readonly) {
            $attributes['disabled'] = 'disabled';
        }
        
        $internal = '';
        switch ($this->get_option('mcq-type')) {
            case 'radio with other':
            case 'radio':
                $internal = '<table>';
                $valuesv = '';
                if (isset($values[$this->get_name()])) {
                    $valuesv = $values[$this->get_name()];
                }
                $i = 0;
                $found = false;
                foreach ($this->mcqoptions as $value => $label) {
                    $attr = ['type' => 'radio',
                             'value' => '%' . $i, 
                             'name' => $fieldname,
                             'id' => $fieldname . '__' . $i];
                    if ($attr['value'] === $valuesv) {
                        $attr['checked'] = 'checked';
                        $found = true;
                    }
                    if ($readonly) {
                        $attr['disabled'] = 'disabled';
                    }
                    $opt = '<td>' . html_writer::empty_tag('input', $attr) . '</td><td>'. html_writer::tag('label', $label, ['for' => $fieldname . '__' . $i]) . '</td>';
                    $internal .= html_writer::tag('tr', $opt, ['class' => 'option']);
                    $i = $i + 1;
                }

                if ($this->get_option('mcq-type') === 'radio with other') {
                    $attr = ['type' => 'radio',
                             'value' => '%_other', 
                             'name' => $fieldname,
                             'id' => $fieldname . '__o'];
                    if ($readonly) {
                        $attr['disabled'] = 'disabled';
                    }
                    if ($valuesv === '%_other') {
                        $attr['checked'] = 'checked';
                    }
                    $valuesvv = '';
                    if (isset($values[$this->get_name() . '__other'])) {
                        $valuesvv = $values[$this->get_name() . '__other'];
                    }
                    $opt = '<td>' . html_writer::empty_tag('input', $attr) . '</td>';
                    $attr = ['type' => 'text', 'value' => $valuesvv,
                             'name' => $fieldname . '__other',
                             'id' => $fieldname . '__other'];
                    if ($found) {
                        $attr['value'] = '';
                    }
                    if ($readonly) {
                        $attr['disabled'] = 'disabled';
                    }
                    // TODO: how to label the other? 
                    // Needs one label for the radio and another for the input?
                    $opt .= '<td>' . html_writer::empty_tag('input', $attr) . '</td>';
                    $internal .= html_writer::tag('tr', $opt, ['class' => 'option']);
                }

                // We might provide unselect.
                if ($this->get_option('mcq-no-deselect') === false) {
                    $attr = ['type' => 'radio',
                             'value' => '%_unselect', 
                             'name' => $fieldname,
                             'id' => $fieldname . '__unselect'];
                    if ($readonly) {
                        $attr['disabled'] = 'disabled';
                    }
                    $opt = '<td>'. html_writer::empty_tag('input', $attr) . '</td><td>' . html_writer::tag('label', stateful_string('input_mcq_radio_unselect'), ['for' => $fieldname . '__unselect']) . '</td>';
                    $internal .= html_writer::tag('tr', $opt, ['class' => 'option', 'id' => $fieldname . '__unselect__row', 'style' => 'display:none;']);
                }
                $internal .= '</table>';
                $main = html_writer::tag('div', $internal, ['class' => 'stateful_input2_mcq_radio']);
            break;
            case 'checkbox':
                $i = 0;
                $internal = '<table>';
                foreach ($this->mcqoptions as $value => $label) {
                    $attr = ['type' => 'checkbox',
                             'value' => 'true', 
                             'name' => $fieldname . '__' . $i,
                             'id' => $fieldname . '__' . $i];
                    if ($this->checked[$i]) {
                        $attr['checked'] = 'checked';
                    }
                    if ($readonly) {
                        $attr['disabled'] = 'disabled';
                    }
                    $i = $i + 1;
                    $opt = '<tr><td>' . html_writer::empty_tag('input', $attr) . '</td><td>' . html_writer::tag('label', html_writer::tag('p', $label), ['for' => $fieldname . '__' . $i]) . '</td></tr>';
                    $internal .= $opt;
                }
                $internal .= '</table>';
                $main = html_writer::tag('div', $internal, ['class' => 'stateful_input2_mcq_checkbox']);
            break;
            case 'dropdown':
                $valuesv = '';
                if (isset($values[$this->get_name()])) {
                    $valuesv = $values[$this->get_name()];
                }
                $hadone = false;
                $i = 0;
                foreach ($this->mcqoptions as $value => $label) {
                    $attr = ['value' => '%' . $i];
                    // Use "numeric" value to stop submission of anythign else.
                    if ('%' . $i === $valuesv) {
                        $hadone = true;
                        $attr['selected'] = 'selected';
                    } 
                    $i = $i + 1;
                    $internal .= html_writer::tag('option', $label, $attr);
                }
                $attr = ['value' => ''];
                if (!$hadone) {
                    $attr['selected'] = 'selected';
                }
                $internal = html_writer::tag('option', stateful_string('input_mcq_dropdown_select_one'), $attr) . $internal;

                $main = html_writer::tag('select', $internal, $attributes);
            break;
        }

        
        // If validation is needed.
        $val = '';
        if ($this->get_option('must-verify')) {
            $attr = array(
                'name'  => $fieldname . '__val',
                'id'    => $fieldname . '__val',
                'type'  => 'hidden',
                'value' => $this->rawvalue
            );
            $val = html_writer::empty_tag('input', $attr);
        }

        return $main . $val;
    }

    public function is_blank(): bool {
        if (trim($this->rawvalue) === '') {
            return true;
        }
        if (trim($this->rawvalue) === '[]' && $this->get_option('mcq-type') === 'checkbox') {
            return !$this->get_option('allow-empty');
        }
        return false;
    }

    public function is_valid_and_validated_or_blank(): bool {
        if ($this->is_blank()) {
            return true;
        }
        if ($this->get_option('must-verify')) {
            // The thing is valid only when it has been validated.
            return $this->rawvalue === $this->val;
        }
        return true;
    }

    public function summarise(): string {
        if ($this->get_option('hide-answer')) {
            return '';
        } else {
            if ($this->is_blank() && $this->is_valid()) {
                return $this->get_name() . ' [VALID AS BLANK]';
            } else if ($this->is_blank()) {
                return '';
            }
            $r = $this->get_name() . ': ' . $this->rawvalue;
            if ($this->get_option('must-verify')) {
                if ($this->val !== $this->rawvalue) {
                    return $r . ' [UNCORFIRMED]';
                }
            }
            if ($this->is_valid()) {
                return $r . ' [VALID]';
            } else {
                return $r . ' [INVALID]';
            }
        }
    }

    public function render_scripts(string $prefix, bool $readonly = false): array {
        $r = parent::render_scripts($prefix, $readonly);

        switch ($this->get_option('mcq-type')) {
            case 'radio with other':
            case 'radio':
                if ($this->get_option('mcq-no-deselect') === false) {
                    if (!isset($r['js_call_amd'])) {
                        $r['js_call_amd'] = [];
                    }
                    $r['js_call_amd'][] = ['qtype_stateful/mcq', 'deselectRadioSetup', [$prefix . $this->get_name()]];
                }
            break;

            case 'dropdown':
                if ($this->get_option('mcq-dropdown-vanilla')) {
                    return $r;
                }
                if (!isset($r['js_call_amd'])) {
                    $r['js_call_amd'] = [];
                }
                // Problem here is that we want to apply MathJax on our own terms
                // not too early. If we use the labels in elements that go through the normal
                // select-options they will get the natural rendering but we cannot copy
                // them as they have unique identifiers, therefore we need to provide 
                // sufficient wrapping for the labels so that we can activate client 
                // side rendering only when we are ready. NOTE! This will probably cause 
                // trouble for some, that is why we provide the vanilla dropdown option, 
                // which also works better with ARIA.
                // I expect Tim to say that this is bad... and works only with MathJax.
                // But MathJax is my target and I know of no way of doing this otherwise.
                $mjx = new filter_mathjaxloader(null, []); // No need for setup, if the page needs 
                // this it already has it.
                $opts = [['value' => '', 'label' => stateful_string('input_mcq_dropdown_select_one')]];
                $i = 0;
                foreach ($this->mcqoptions as $value => $label) {
                    $opts[] = ['value' => '%' . $i, 'label' => $mjx->filter($label)];
                    $i = $i + 1;
                }
                $r['js_call_amd'][] = ['qtype_stateful/mcq', 'declareDropdown', [$prefix . $this->get_name(), $opts]];
            break;
        }


        return $r;
    }

    public function get_expected_data(): array {
        $r = [];
        if ($this->get_option('mcq-type') === 'radio with other') {
            $r[$this->get_name() . '__other'] = PARAM_RAW_TRIMMED;
        }

        if ($this->get_option('mcq-type') === 'checkbox') {
            // Problem is that if this function gets called before
            // this input has been initialised we cannot give 
            // the correct nubmer of expected inputs. So lets give
            // enough.
            if ($this->mcqoptions === null) {
                for ($i = 0; $i < stateful_input_algebraic::MAX_CHECKBOXES; $i++) {
                    $r[$this->get_name() . '__' . $i] = PARAM_RAW_TRIMMED;
                }
                return $r;
            }

            $i = 0;
            foreach ($this->mcqoptions as $value => $label) {
                $r[$this->get_name() . '__' . $i] = PARAM_RAW_TRIMMED;
                $i = $i + 1;
            }
        } else {
            $r[$this->get_name()] = PARAM_RAW_TRIMMED;
        }

        return $r;
    }

    public function get_schema_for_options(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_schema_for_options();

        $typeoneof = [
            ['enum' => ['radio'], 'description' => stateful_string('input_option_mcqtype_enum_radio')],
            ['enum' => ['radio with other'], 'description' => stateful_string('input_option_mcqtype_enum_radio_with_other')],
            ['enum' => ['checkbox'], 'description' => stateful_string('input_option_mcqtype_enum_checkbox')],
            ['enum' => ['dropdown'], 'description' => stateful_string('input_option_mcqtype_enum_dropdown')]
        ];
        
        $base['properties']['mcq-type'] = [
            'default' => 'radio',
            'type' => 'string', 
            'oneOf' => $typeoneof,
            'title' => stateful_string('input_option_mcqtype_label'),
            'description' => stateful_string('input_option_mcqtype_description')
        ];

        $grouponeof = [
            ['enum' => ['correct'], 'description' => stateful_string('input_option_mcqoption_enum_correct')],
            ['enum' => ['distractor'], 'description' => stateful_string('input_option_mcqoption_enum_distractor')]
        ];

        $base['properties']['mcq-options'] = [
            'default' => [['value' => 'true','label' => 'Yes','group' => 'correct'],
                          ['value' => 'false','label' => 'No','group' => 'distractor']],
            'type' => 'array',
            'minItems' => 2,
            'uniqueItems' => true,
            'items' => [
                'type' => 'object',
                'properties' => [
                    'value' => ['type' => 'string', 'title' => stateful_string('input_option_mcqoptions_item_value_label')],
                    'label' => ['type' => 'string', 'title' => stateful_string('input_option_mcqoptions_item_label_label')],
                    'group' => ['type' => 'string', 'title' => stateful_string('input_option_mcqoptions_item_group_label'), 'oneOf' => $grouponeof, 'default' => 'correct'],
                    'inclusion' => ['type' => 'string', 'title' => stateful_string('input_option_mcqoptions_item_inclusion_label'), 'default' => 'true']
                ],
                'required' => ['value']
            ],
            'title' => stateful_string('input_option_mcqoptions_label'),
            'description' => stateful_string('input_option_mcqoptions_description')
        ];

        $base['properties']['mcq-randomise-order'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_mcqrandomiseorder_label'),
            'description' => stateful_string('input_option_mcqrandomiseorder_description')
        ];

        $base['properties']['mcq-random-corrects'] = [
            'type' => 'integer', 
            'minimum' => -1,
            'maximum' => 20,
            'default' => -1,
            'title' => stateful_string('input_option_mcqrandomcorrects_label'),
            'description' => stateful_string('input_option_mcqrandomcorrects_description')
        ];

        $base['properties']['mcq-random-distractors'] = [
            'type' => 'integer', 
            'minimum' => -1,
            'maximum' => 20,
            'default' => -1,
            'title' => stateful_string('input_option_mcqrandomdistractors_label'),
            'description' => stateful_string('input_option_mcqrandomdistractors_description')
        ];

        $base['properties']['mcq-no-deselect'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_mcqnodeselect_label'),
            'description' => stateful_string('input_option_mcqnodeselect_description')
        ];


        $base['properties']['mcq-dropdown-vanilla'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_mcq_dropdown_vanilla_label'),
            'description' => stateful_string('input_option_mcq_dropdown_vanilla_description')
        ];

        // No use for these.
        unset($base['properties']['syntax-hint-type']);
        unset($base['properties']['syntax-hint']);

        return $base;
    }

    public function get_default_values(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_default_values();

        $base['mcq-type'] = 'radio';
        $base['mcq-options'] = [['value' => 'true','label' => 'Yes','group' => 'correct'],['value' => 'false','label' => 'No','group' => 'distractor']];
        $base['mcq-randomise-order'] = false;
        $base['mcq-random-corrects'] = -1;
        $base['mcq-random-distractors'] = -1;
        $base['mcq-no-deselect'] = false;
        $base['mcq-dropdown-vanilla'] = false;
        $base['validation-box'] = '';
        $base['must-verify'] = false;
        $base['validation-box'] = '';

        // Relatively common use case is to use string-values,
        // as they can never change due to simplification.
        $base['forbid-strings'] = false;

        // This is a concession for the old STACK way, not a visible
        // option of the new system.
        $base['mcq-label-default-render'] = 'latex';


        return $base;
    }


    public function get_layout_for_options(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_layout_for_options();

        $fieldset = ['title' => stateful_string('input_options_mcq'), 'fields' => ['mcq-type', 'mcq-options', 'mcq-random-corrects', 'mcq-random-distractors', 'mcq-randomise-order', 'mcq-no-deselect', 'mcq-dropdown-vanilla']];

        // Lets position that among the fieldsets. Also we drop syntaxhints.
        $newfieldsetslist = [];
        foreach ($base['fieldsets'] as $fs) {
            if ($fs['title'] === stateful_string('input_options_syntaxhint')) {
                $newfieldsetslist[] = $fieldset;
            } else {
                $newfieldsetslist[] = $fs;
            }
        }
        $base['fieldsets'] = $newfieldsetslist;

        if (!isset($base['widgets'])) {
            $base['widgets'] = [];
        }
        $base['widgets']['mcq-options'] = 'mcqoptions';
        $base['widgets']['mcq-type'] = 'select';


        unset($base['widgets']['syntax-hint-type']);
        unset($base['widgets']['syntax-hint']);

        return $base;
    }

    public function get_initialisation_commands(): string {
        // The construction of options is the main thing.
        $optgen = '%_mcqoptions:[]';

        // Keep the order if need be.
        $i = 1;
        foreach ($this->get_option('mcq-options') as $opt) {
            $append = '';

            // If activation rules in play append if active.
            if (isset($opt['inclusion']) && $opt['inclusion'] !== null && trim($opt['inclusion']) !== '' && trim($opt['inclusion'] !== 'true')) {
                $active = stack_ast_container::make_from_teacher_source($opt['inclusion'], 'activation for option ' . $i . ' in ' . $this->get_name());
                $append = 'if ' . $active->get_evaluationform() . ' then ';
            }
            // The order, used for uniquenes as well.
            $append .= '%_mcqoptions:append(%_mcqoptions,[[' . $i . ',';

            // Value
            $value = stack_ast_container::make_from_teacher_source('stack_dispvalue(' . $opt['value'] . ')', 'value for option ' . $i . ' in ' . $this->get_name());
            $append .= $value->get_evaluationform() . ',';

            $label = null;
            // Label
            if (isset($opt['label']) && $opt['label'] !== null && trim($opt['label']) !== '') {
                $label = castext2_parser_utils::compile($opt['label']);
            } else if ($this->get_option('mcq-label-default-render') !== 'latex' || $this->get_option('mcq-dropdown-vanilla') === true) {
                $label = castext2_parser_utils::compile('{#' . $opt['value'] . '#}');
            } else {
                $label = castext2_parser_utils::compile('{@' . $opt['value'] . '@}');
            }
            
            $append .= $label . ',';


            if (isset($opt['group']) && $opt['group'] !== 'correct') {
                $append .= '"d"]])';
            } else {
                $append .= '"c"]])';
            }

            $optgen .= ',' . $append;

            $i = $i + 1;
        }

        // At this point %_optgen has all the options that are active in 
        // definition order.
        // Now if we need to limit randoms lets do so.
        if ($this->get_option('mcq-random-corrects') !== -1) {
            if ($this->get_option('mcq-random-corrects') === 0) {
                $optgen .= ',%_mcqoptions:sublist(%_mcqoptions,lambda([%_tmp],last(%_tmp)#"c"))';
            } else {
                $optgen .= ',%_tmp:sublist_indices(%_mcqoptions,lambda([%_tmp],%_tmp[4]="c"))';
                $optgen .= ',while length(%_tmp) > ' . $this->get_option('mcq-random-corrects') . ' do ' . 
                '(%_mcqoptions:delete(%_mcqoptions[rand(%_tmp)],%_mcqoptions)' 
                . ',%_tmp:sublist_indices(%_mcqoptions,lambda([%_tmp],%_tmp[4]="c")))';
            }
        }
        if ($this->get_option('mcq-random-distractors') !== -1) {
            if ($this->get_option('mcq-random-distractors') === 0) {
                $optgen .= ',%_mcqoptions:sublist(%_mcqoptions,lambda([%_tmp],last(%_tmp)#"d"))';
            } else {
                $optgen .= ',%_tmp:sublist_indices(%_mcqoptions,lambda([%_tmp],%_tmp[4]="d"))';
                $optgen .= ',while length(%_tmp) > ' . $this->get_option('mcq-random-distractors') . ' do ' . 
                '(%_mcqoptions:delete(%_mcqoptions[rand(%_tmp)],%_mcqoptions)' 
                . ',%_tmp:sublist_indices(%_mcqoptions,lambda([%_tmp],%_tmp[4]="d")))';
            }
        }

        // We can now drop the order and type details.
        $optgen .= ',%_mcqoptions:map(lambda([%_tmp],[%_tmp[2],%_tmp[3]]),%_mcqoptions)';

        // Finally do we randomise order?
        if ($this->get_option('mcq-randomise-order')) {
            $optgen .= ',%_mcqoptions:random_permutation(%_mcqoptions)';
        }

        $init = 'block([%_tmp,simp,%_mcqoptions],simp:false,' . $optgen . ',simp:false,[' . $this->rawteachersanswer . ',stack_dispvalue(' . $this->rawteachersanswer . '),%_mcqoptions])';
        $validation = stack_ast_container::make_from_teacher_source($init, 'ta for ' . $this->get_name());
        // Could throw some exceptions here?
        $validation->get_valid();
        return $validation->get_evaluationform();
    }

    public function set_initialisation_value(MP_Node $value): void {
        // No need to replicate teachers answer extraction.
        parent::set_initialisation_value($value);

        // Collect the selected options.
        $list = $value;
        if ($list instanceof MP_Root) {
            $list = $list->items[0];
        }
        if ($list instanceof MP_Statement) {
            $list = $list->statement;
        }
        $optlist = $list->items[2];
        $this->mcqoptions = [];
        foreach ($optlist->items as $opt) {
            $this->mcqoptions[$opt->items[0]->value] = castext2_parser_utils::postprocess_mp_parsed($opt->items[1]);
        }
    }


    public function get_correct_input_guide(): string {
        if ($this->get_option('hide-answer')) {
            return '';
        }

        $r = '<code>' . htmlspecialchars($this->evaluatedteachersanswerdisp->value) . '</code>';

        // TODO: word it differently and handle checkboxes.

        if ($this->get_option('guidance-label') !== $this->get_name() && $this->get_option('guidance-label') !== '') {
            $r = stateful_string('input_into', $this->get_option('guidance-label')) . $r;
        }
        return $r;
    }

    public function get_correct_response(): array {
        $theone = $this->evaluatedteachersanswer->toString();
        if ($theone === 'EMPTYANSWER') {
            return array();
        }
        switch ($this->get_option('mcq-type')) {
            case 'radio':
            case 'radio with other':
            case 'dropdown':
                // Which value?
                $i = 0;
                foreach ($this->mcqoptions as $value => $label) {
                    if ($value === $theone) {
                        return [$this->get_name() => '%' . $i];
                    }
                    $i = $i + 1;
                }
                return [$this->get_name() => '%_other', $this->get_name() . '__other' => $theone];
            case 'checkbox':
                $theones = [];
                $list = $this->evaluatedteachersanswer;
                if ($list instanceof MP_Root) {
                    $list = $list->items[0];
                }
                if ($list instanceof MP_Statement) {
                    $list = $list->statement;
                }
                foreach ($list->items as $item) {
                    $theones[$item->toString()] = true;
                }
                $r = [];
                $i = 0;
                foreach ($this->mcqoptions as $value => $label) {
                    if (isset($theones[$value])) {
                        $r[$this->get_name() . '__' . $i] = 'true';
                    }
                    $i = $i + 1;
                }
                return $r;
            
        }
        return array();
    }

    public function get_value(): cas_evaluatable {
        if ($this->input->get_valid()) {
            return new stack_secure_loader($this->get_name() . ':' . $this->input->get_evaluationform(), $this->get_name() . ' input value');
        }
        // No one should call this unless we have blank...
        if (!$this->is_blank()) {
            throw new stateful_exception('trying to extract invalid value for evaluation');
        }
        // The empty answer does not need to be validated with full student logic...
        return new stack_secure_loader($this->get_name() . ':EMPTYANSWER', $this->get_name() . ' input value');
    }

    public function value_to_response(MP_Node $evvalue): array {
        $val = $evvalue;
        if ($val instanceof MP_Root) {
            $val = $val->items[0];
        }
        if ($val instanceof MP_Statement) {
            $val = $val->statement;
        }
    
        switch ($this->get_option('mcq-type')) {
            case 'checkbox':
                if (!($val instanceof MP_List)) {
                    throw new stateful_exception('trying to map a non list input value to a checkbox input');
                }
                $theones = [];
                foreach ($val->items as $item) {
                    $theones[$item->toString()] = true;
                }
                $r = [];
                $i = 0;
                foreach ($this->mcqoptions as $value => $label) {
                    if (isset($theones[$value])) {
                        $r[$this->get_name() . '__' . $i] = 'true';
                    }
                    $i = $i + 1;
                }
                return $r;
            case 'radio':
            case 'radio with other':
            case 'dropdown':
                // Which value?
                $theone = $val->toString();
                $i = 0;
                foreach ($this->mcqoptions as $value => $label) {
                    if ($value === $theone) {
                        return [$this->get_name() => '%' . $i];
                    }
                    $i = $i + 1;
                }
                return [$this->get_name() => '%_other', $this->get_name() . '__other' => $theone];
        }
        throw new stateful_exception('trying to map an input value to an unknown mcq-type input');
    }

    public function get_val_field_value(): string {
        return $this->rawvalue;
    }

    public function get_value_override(): string {
        // This will never work well it will always require special handling 
        // from the side of the validation-display.
        if ($this->get_option('mcq-type') === 'checkbox') {
            $r = '[';
            $first = true;
            $i = 0;
            foreach ($this->mcqoptions as $value => $label) {
                if ($this->checked[$i]) {
                    if (!$first) {
                        $r .= ',';  
                    } else {
                        $first = false;
                    }

                    $r .= stack_utils::php_string_to_maxima_string($label);
                }
                $i = $i + 1;
            } 
            $r .= ']';

            return $r;
        }
        
        foreach ($this->mcqoptions as $value => $label) {
            if ($value === $this->rawvalue) {
                return stack_utils::php_string_to_maxima_string($label);    
            }
        }

        return $this->get_name();
    }


    public function get_validation_box(array $existing): ?stateful_input_validation_box {
        // In the case of checkboxen we need to override the validation box, luckilly we can do it here.
        if ($this->get_option('mcq-type') !== 'checkbox') {
            return parent::get_validation_box($existing);
        }
        switch ($this->get_option('validation-box')) {
            case 'automatic without listings':
                if (isset($existing[$this->get_name()])) {
                    return $existing[$this->get_name()];
                }
                return new stateful_basic_validation_box_for_checkboxes($this->get_name(), false, false);
            case 'automatic with list of variables':
                if (isset($existing[$this->get_name()])) {
                    return $existing[$this->get_name()];
                }
                return new stateful_basic_validation_box_for_checkboxes($this->get_name(), true, false);
            case 'automatic with list of units':
                if (isset($existing[$this->get_name()])) {
                    return $existing[$this->get_name()];
                }
                return new stateful_basic_validation_box_for_checkboxes($this->get_name(), false, true);
            case '':
                return null;
            default:
                return $existing[$this->get_option('validation-box')];
        }
    }
}