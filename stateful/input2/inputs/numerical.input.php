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

/**
 * This input is for inputting a single scalar value that has none or 
 * very minimal number of operators or other structure.
 *
 * This is loosely the same as the old STACK numerical input. but extends 
 * it with some additions.
 */
class stateful_input_numerical extends stateful_input_base_with_options_and_validation implements 
    stateful_input_teachers_answer_handling,
    stateful_input_caching_initialisation,
    stateful_input_cas_value_generating {

    // Raw is what we get from the database/question meta.
    protected $rawteachersanswer;
    // We evaluate the value as AST and as a display-value string.
    protected $evaluatedteachersanswer;
    protected $evaluatedteachersanswerdisp;
    protected $mindp = null;
    protected $maxdp = null;
    protected $minsf = null;
    protected $maxsf = null;


    // The value inputted.
    protected $rawvalue;
    // The validation value if any.
    protected $val;

    // The presentation override.
    // If we do conversions and as we are dealing with floats
    // it is nice to present the number to the student at 
    // the same accuracy they gave. Even when the system threw
    // trailing zeros away.
    protected $presentation = null;

    // Errors about it. Before CAS validation.
    protected $preerrors;

    // The statements.
    protected $input;

    // Hold onto the security, for later steps.
    protected $security;

    public function get_type(): string {
        return 'numerical';
    }


    public function get_validation_statements(array $response, stack_cas_security $rules): array {
        $this->input = null;
        $this->validationstatements = [];
        // Collect the value.
        $this->rawvalue = '';
        if (isset($response[$this->get_name()])) {
            $this->rawvalue = $response[$this->get_name()];
        }
        // Also the validation.
        $this->val = null;
        if (isset($response[$this->get_name() . '__val'])) {
            $this->val = $response[$this->get_name() . '__val'];
        }

        $this->security = clone $rules;
        $this->security->set_forbiddenwords('(,{,[,_,\,,/');
        if ($this->get_option('no-units')) {
            $this->security->set_units(false);
        }
        
        // Map the filter options.
        $filteroptions = ['801_singleton_numeric' => [
            'float' => $this->get_option('numerical-accept-float-vs-integer') !== 'no float',
            'integer' => $this->get_option('numerical-accept-float-vs-integer') !== 'no integer',
            'power' => $this->get_option('numerical-accept-power-form'),
            'convert' => $this->get_option('numerical-convert')
        ]];

        if ($this->minsf !== null || $this->maxsf !== null) {
            $filteroptions['201_sig_figs_validation'] = [
                'min' => $this->minsf,
                'max' => $this->maxsf,
                'strict' => $this->get_option('sf-strict')
            ];
        }

        if ($this->mindp !== null || $this->maxdp !== null) {
            $filteroptions['202_decimal_places_validation'] = [
                'min' => $this->mindp,
                'max' => $this->maxdp
            ];
        }

        // Do the parse. 
        $this->input = stack_ast_container::make_from_student_source($this->rawvalue, $this->get_name() . ' input validation', $this->security, $this->get_filters(), $filteroptions);
        if ($this->input->get_valid()) {
            // Generate the presentation version.
            $filters = [];
            $filters[] = '441_split_unknown_functions';
            $filters[] = '403_split_at_number_letter_boundary';
            $filters[] = '406_split_implied_variable_names';
            $filters[] = '910_inert_float_for_display'; // This is the key here.
            $this->security->set_allowedwords('dispdp,displaysci');
            $this->security->set_forbiddenwords('');
            $presentation = stack_ast_container::make_from_student_source($this->rawvalue, $this->get_name() . ' input validation', $this->security, $filters);
            $this->presentation = $presentation->get_evaluationform();
        }



        // There is nothing to validate beyond being parseable and passign the filters...
        return array();
    }


    public function get_filters(): array {
        $r = [
            '102_no_strings',
            '103_no_lists',
            '104_no_sets',
            '105_no_grouppings',
            '106_no_control_flow',
            '441_split_unknown_functions',
            '403_split_at_number_letter_boundary',
            '406_split_implied_variable_names',
            '801_singleton_numeric'
        ];

        if ($this->minsf !== null || $this->maxsf !== null) {
            $r[] = '201_sig_figs_validation';
        }
        if ($this->mindp !== null || $this->maxdp !== null) {
            $r[] = '202_decimal_places_validation';
        }

        // The acceptable things.
        if (!$this->get_option('fix-stars') && !$this->get_option('fix-spaces')) {
            $r[] = '999_strict';
        } else if (!$this->get_option('fix-stars')) {
            $r[] = '991_no_fixing_stars';
        } else if (!$this->get_option('fix-spaces')) {
            $r[] = '990_no_fixing_spaces';
        }
        return $r;
    }


    public function get_schema_for_options(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_schema_for_options();
        $base['properties']['input-width'] = [
            'type' => 'integer', 
            'minimum' => 1,
            'maximum' => 80,
            'default' => 15,
            'title' => stateful_string('input_option_inputwidth_label'),
            'description' => stateful_string('input_option_inputwidth_description')
        ];

        $alignoneof = [
            ['enum' => ['left'], 'description' => stateful_string('input_option_align_left')],
            ['enum' => ['right'], 'description' => stateful_string('input_option_align_right')],
            ['enum' => ['browser default'], 'description' => stateful_string('input_option_align_browser_locale')],
        ];

        $base['properties']['input-align'] = [
            'default' => 'browser default',
            'type' => 'string', 
            'oneOf' => $alignoneof,
            'title' => stateful_string('input_option_align_label'),
            'description' => stateful_string('input_option_align_description')
        ];

        $acceptoneof = [
            ['enum' => ['both'], 'description' => stateful_string('input_option_accept_float_vs_integer_both')],
            ['enum' => ['no float'], 'description' => stateful_string('input_option_accept_float_vs_integer_no_float')],
            ['enum' => ['no integer'], 'description' => stateful_string('input_option_accept_float_vs_integer_no_integer')],
        ];


        // What types of numbers do we accept.
        $base['properties']['numerical-accept-float-vs-integer'] = [ 
            'default' => 'both',
            'type' => 'string',
            'oneOf' => $acceptoneof,
            'title' => stateful_string('input_option_accept_float_vs_integer_label'),
            'description' => stateful_string('input_option_accept_float_vs_integer_description')
        ];
        $base['properties']['numerical-accept-power-form'] = [ 
            'default' => true,
            'type' => 'boolean',
            'title' => stateful_string('input_option_accept_power_form_label'),
            'description' => stateful_string('input_option_accept_power_form_description')
        ];

        $convertoneof = [
            ['enum' => ['none'], 'description' => stateful_string('input_option_convert_none')],
            ['enum' => ['to float'], 'description' => stateful_string('input_option_convert_to_float')],
            ['enum' => ['to power'], 'description' => stateful_string('input_option_convert_to_power')],
        ];

        $base['properties']['numerical-convert'] = [
            'default' => 'none',
            'type' => 'string', 
            'oneOf' => $convertoneof,
            'title' => stateful_string('input_option_convert_label'),
            'description' => stateful_string('input_option_convert_description')
        ];


        // What is acceptable to fix.
        $base['properties']['fix-spaces'] = [ 
            'default' => true,
            'type' => 'boolean',
            'title' => stateful_string('input_option_fixspaces_label'),
            'description' => stateful_string('input_option_fixspaces_description')
        ];
        $base['properties']['fix-stars'] = [ 
            'default' => true,
            'type' => 'boolean',
            'title' => stateful_string('input_option_fixstars_label'),
            'description' => stateful_string('input_option_fixstars_description')
        ];

        // DP/SF
        $base['properties']['sf-strict'] = [ 
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_strict_sf_label'),
            'description' => stateful_string('input_option_strict_sf_description')
        ];

        $base['properties']['sf-min'] = [
            'default' => '',
            'type' => 'string',
            'title' => stateful_string('input_option_min_sf_label'),
            'description' => stateful_string('input_option_min_sf_description')
        ];

        $base['properties']['sf-max'] = [
            'default' => '',
            'type' => 'string',
            'title' => stateful_string('input_option_max_sf_label'),
            'description' => stateful_string('input_option_max_sf_description')
        ];

        $base['properties']['dp-min'] = [
            'default' => '',
            'type' => 'string',
            'title' => stateful_string('input_option_min_dp_label'),
            'description' => stateful_string('input_option_min_dp_description')
        ];

        $base['properties']['dp-max'] = [
            'default' => '',
            'type' => 'string',
            'title' => stateful_string('input_option_max_dp_label'),
            'description' => stateful_string('input_option_max_dp_description')
        ];

        // Irrelevant here.
        unset($base['properties']['no-units']);

        return $base;
    }

    public function get_default_values(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_default_values();
        $base['input-width'] = 15;
        $base['input-align'] = 'browser default';
        $base['validation-box'] = 'automatic with list of variables';

        $base['numerical-accept-float-vs-integer'] = 'both';
        $base['numerical-accept-power-form'] = true;

        $base['numerical-convert'] = 'none';

        $base['fix-spaces'] = true;
        $base['fix-stars'] = true;

        $base['sf-strict'] = false;
        $base['sf-min'] = '';
        $base['dp-min'] = '';
        $base['sf-max'] = '';
        $base['dp-max'] = '';

        // We do not consider anything as units here.
        $base['no-units'] = true;

        return $base;
    }

    public function get_layout_for_options(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_layout_for_options();
        $base['fieldsets'][] = array('title' => stateful_string('input_options_size'), 'fields' => ['input-width', 'input-align']);
        $base['fieldsets'][] = array('title' => stateful_string('input_options_acceptable'), 'fields' => ['fix-stars', 'fix-spaces']);
        $base['fieldsets'][] = array('title' => stateful_string('input_options_numerical'), 'fields' => ['numerical-accept-float-vs-integer', 'numerical-accept-power-form', 'numerical-convert', 'sf-min', 'sf-max', 'sf-strict', 'dp-min', 'dp-max']);


        foreach ($base['fieldsets'] as $k => $fs) {
             if ($fs['title'] === stateful_string('input_options_common')) {
                $base['fieldsets'][$k]['fields'] = array_filter($fs['fields'], 
                    function($v) {
                        return $v !== 'no-units';
                    }
                );
            } 
        }


        if (!isset($base['widgets'])) {
            $base['widgets'] = [];
        }
        $base['widgets']['input-align'] = 'select';
        $base['widgets']['numerical-convert'] = 'select';
        $base['widgets']['numerical-accept-float-vs-integer'] = 'select';
        $base['widgets']['sf-min'] = 'casstring-integer';
        $base['widgets']['sf-max'] = 'casstring-integer';
        $base['widgets']['dp-min'] = 'casstring-integer';
        $base['widgets']['dp-max'] = 'casstring-integer';

        return $base;
    }




    public function get_expected_data(): array {
        $r = array($this->get_name() => PARAM_RAW_TRIMMED);
        if ($this->get_option('must-verify')) {
            $r[$this->get_name() . '__val'] = PARAM_RAW_TRIMMED;
        }
        return $r;
    }

    public function render_controls(array $values, string $prefix, bool $readonly = false): string {
        $fieldname = $prefix . $this->get_name();
        $value = '';
        if (isset($values[$this->get_name()])) {
            $value = $values[$this->get_name()];
        }

        $attributes = array(
            'name'  => $fieldname,
            'id'    => $fieldname,
            'autocapitalize' => 'none',
            'spellcheck'     => 'false',
            'size' => $this->get_option('input-width'),
            'value' => $value,
            'type' => 'text',
            'aria-label' => $this->get_option('guidance-label')
        );
        if ($readonly) {
            $attributes['disabled'] = 'disabled';
        }

        if ($this->get_option('input-align') === 'left') {
            $attributes['style'] = 'text-align:left;';
        } else if ($this->get_option('input-align') === 'right') {
            $attributes['style'] = 'text-align:right;';
        }
        
        // If validation is needed.
        $val = '';
        if ($this->get_option('must-verify')) {
            $attr = array(
                'name'  => $fieldname . '__val',
                'id'    => $fieldname . '__val',
                'type'  => 'hidden',
                'value' => $value
            );
            $val = html_writer::empty_tag('input', $attr);
        }

        return html_writer::empty_tag('input', $attributes) . $val;
    }

    public function get_initialisation_commands(): string {
        $init = 'block([simp],simp:false,[' . $this->rawteachersanswer . ',stack_dispvalue(' . $this->rawteachersanswer . ')';
        if (trim($this->get_option('sf-min')) !== '') {
            $init .= ',ev(' . trim($this->get_option('sf-min')) . ',simp)';
        }
        if (trim($this->get_option('sf-max')) !== '') {
            $init .= ',ev(' . trim($this->get_option('sf-max')) . ',simp)';
        }
        if (trim($this->get_option('dp-min')) !== '') {
            $init .= ',ev(' . trim($this->get_option('dp-min')) . ',simp)';
        }
        if (trim($this->get_option('dp-max')) !== '') {
            $init .= ',ev(' . trim($this->get_option('dp-max')) . ',simp)';
        }

        $init .= '])';
        $validation = stack_ast_container::make_from_teacher_source($init, 'ta for ' . $this->get_name());
        // Could throw some exceptions here?
        $validation->get_valid();
        return $validation->get_evaluationform();
    }

    public function set_initialisation_value(MP_Node $value): void {
        // Value is a list in this case.
        $list = $value;
        if ($list instanceof MP_Root) {
            $list = $list->items[0];
        }
        if ($list instanceof MP_Statement) {
            $list = $list->statement;
        }
        $this->evaluatedteachersanswer = $list->items[0];
        $this->evaluatedteachersanswerdisp = $list->items[1];
        $i = 2;
        // These better be MP_Integers.
        if (trim($this->get_option('sf-min')) !== '') {
            $this->minsf = intval($list->items[$i]->toString());
            $i++;
        }
        if (trim($this->get_option('sf-max')) !== '') {
            $this->maxsf = intval($list->items[$i]->toString());
            $i++;
        }
        if (trim($this->get_option('dp-min')) !== '') {
            $this->mindp = intval($list->items[$i]->toString());
            $i++;
        }
        if (trim($this->get_option('dp-max')) !== '') {
            $this->maxdp = intval($list->items[$i]->toString());
            $i++;
        }
    }

    public function set_teachers_answer(string $answer): void {
        $this->rawteachersanswer = $answer;
    }

    public function get_correct_input_guide(): string {
        if ($this->get_option('hide-answer')) {
            return '';
        }

        $r = '<code>' . htmlspecialchars($this->evaluatedteachersanswerdisp->value) . '</code>';

        if ($this->get_option('guidance-label') !== $this->get_name() && $this->get_option('guidance-label') !== '') {
            $r = stateful_string('input_into', $this->get_option('guidance-label')) . $r;
        }
        return $r;
    }

    public function get_correct_response(): array {
        if ($this->evaluatedteachersanswer->toString() === 'EMPTYANSWER') {
            return array();
        }

        return array($this->get_name() => $this->evaluatedteachersanswerdisp->value);
    }

    public function get_string_value(): string {
        if ($this->rawvalue === null) {
            return '';
        }
        return $this->rawvalue;
    }

    public function get_functions(): array {
        // Override if you can give these.
        return array();
    }

    public function get_variables(): array {
        if (!$this->is_valid()) {
            return array();
        }

        $usage = $this->input->get_variable_usage();

        $r = array();
        if (isset($usage['read'])) {
            $r = array_keys($usage['read']);
        }
        if (isset($usage['write'])) {
            $r = array_merge($r, array_keys($usage['write']));
        }
        sort($r);
        $vars = array();
        foreach ($r as $key) {
            // Units are also contants
            if (!$this->security->has_feature($key, 'constant')) {
                $vars[$key] = $key;
            }
        }
        return $vars;
    }

    public function get_units(): array {
        if (!$this->security->get_units() || !$this->is_valid()) {
            return array();         
        }
        $usage = $this->input->get_variable_usage();

        $r = array();
        if (isset($usage['read'])) {
            $r = array_keys($usage['read']);
        }
        if (isset($usage['write'])) {
            $r = array_merge($r, array_keys($usage['write']));
        }
        sort($r);
        $ids = array();
        $units = stack_cas_casstring_units::get_permitted_units(0);
        foreach ($r as $key) {
            if (isset($units[$key])) {
                $ids[$key] = $key;
            }
        }
        return $ids;
    }


    public function serialize(bool $prunedefaults): array {
        $r = parent::serialize($prunedefaults);
        $r['tans'] = $this->rawteachersanswer;
        return $r;
    }

    public function value_to_response(MP_Node $value): array {
        $val = $value;
        if ($val instanceof MP_Root) {
            $val = $val->items[0];
        }
        if ($val instanceof MP_Statement) {
            $val = $val->statement;
        }
        $r = [$this->get_name() => $val->toString(['inputform' => true, 'qmchar' => true, 'nounify' => false])];
        if ($this->get_option('must-verify')) {
            $r[$this->get_name() . '__val'] = $r[$this->get_name()];
        }

        return $r;
    }

    public function get_val_field_value(): string {
        return $this->rawvalue;
    }

    public function get_errors(): array {
        return $this->input->get_errors('array');
    }

    public function is_valid(): bool {
        if ($this->is_blank()) {
            return $this->get_option('allow-empty');
        }
        if ($this->input === null) {
            return false;
        }
        return $this->input->get_valid();
    }

    public function is_blank(): bool {
        if (trim($this->rawvalue) === '') {
            return true;
        }
        return false;
    }

    public function is_valid_and_validated_or_blank(): bool {
        if ($this->is_blank()) {
            return true;
        }
        if ($this->get_option('must-verify')) {
            // The thing is valid only when it has been validated.
            return $this->val === $this->rawvalue;
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
                $inputform = $this->input->get_inputform(true, null);
                if ($inputform === $this->rawvalue) {
                    return $r . ' [VALID]';
                } else {
                    return $r . ' [INTERPRETED AS] ' . $inputform . ' [VALID]';
                }
            } else {
                return $r . ' [INVALID]';
            }
        }
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

    public function get_value_override(): string {
        if ($this->presentation === null) {
            return $this->get_name();
        }
        return $this->presentation;
    }
}

