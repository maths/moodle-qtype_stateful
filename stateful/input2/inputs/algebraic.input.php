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
 * This is the base class of all 1D inputs that are more complex 
 * than just strings. There are some hooks present.
 */
class stateful_input_algebraic extends stateful_input_base_with_options_and_validation implements 
    stateful_input_teachers_answer_handling,
    stateful_input_caching_initialisation,
    stateful_input_cas_value_generating {

    // Raw is what we get from the database/question meta.
    protected $rawteachersanswer;
    // We evaluate the value as AST and as a display-value string.
    protected $evaluatedteachersanswer;
    protected $evaluatedteachersanswerdisp;

    // The value inputted.
    protected $rawvalue;
    // The validation value if any.
    protected $val;

    // Errors about it. Before CAS validation.
    protected $preerrors = null;

    // The statements.
    protected $input;

    // Hold onto the security, for later steps.
    protected $security;

    public function get_type(): string {
        return 'algebraic';
    }

    // Hold onto specific validation statements. An array.
    private $validationstatements;

    public function get_validation_statements(array $response, stack_cas_security $rules): array {

        $this->preerrors = array();
        $this->input = null;
        $this->validationstatements = [];

        $this->collect_value($response);


        // The security needs to be adapted to input level words.
        $this->security = clone $rules;
        $this->security->set_allowedwords($this->get_option('allow-words'));
        $this->security->set_forbiddenwords($this->get_option('forbid-words'));
        if ($this->get_option('no-units')) {
            $this->security->set_units(false);
        }

        // Do the parse. 
        // TODO: look into not using the validation of stack_ast_container as that does change
        // instead define raw statements here.
        $this->input = stack_ast_container::make_from_student_source($this->rawvalue, $this->get_name() . ' input validation', $this->security, $this->get_filters());
        $this->input->get_valid();

        $this->preerrors = array_merge($this->input->get_errors('array'), $this->custom_validation($this->rawvalue, $this->input));

        if ($this->input->get_valid()) {
            $r = [$this->input];
            if ($this->get_option('require-same-type')) {
                $code = 'ATSameType(' . $this->input->get_inputform(true, 1) . ',' . $this->rawteachersanswer . ')';
                $sta = stack_ast_container::make_from_teacher_source($code, $this->get_name() . ' input validation: type check', $this->security);
                $r[] = $sta;
                $this->validationstatements['require-same-type'] = $sta;
            }
            if ($this->get_option('require-lowest-terms')) {
                $code = 'all_lowest_termsex(' . $this->input->get_inputform(true,1) . ')';
                $sta = stack_ast_container::make_from_teacher_source($code, $this->get_name() . ' input validation: lowest terms check', $this->security);
                $r[] = $sta;
                $this->validationstatements['require-lowest-terms'] = $sta;
            }
            return $r;
        }
        return array();
    }

    // Hook for overriding the collection of the basic value.
    protected function collect_value(array $response) {
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
    }

    // A way of getting the basic filter pipeline to the common parse logic.
    public function get_filters(): array {
        $r = array();
        if ($this->get_option('forbid-floats')) {
            $r[] = '101_no_floats';
        }
        if ($this->get_option('forbid-strings')) {
            $r[] = '102_no_strings';
        }
        if ($this->get_option('forbid-lists')) {
            $r[] = '103_no_lists';
        }
        if ($this->get_option('forbid-sets')) {
            $r[] = '104_no_sets';
        }
        if ($this->get_option('forbid-groups')) {
            $r[] = '105_no_grouppings';
        }

        // This will probably never become an option,
        // maybe for some multi-line input though.
        $r[] = '106_no_control_flow';

        // Elective function handling.
        switch ($this->get_option('forbid-functions')) {
            case 'split_unknown_functions':
                $r[] = '441_split_unknown_functions';
                break;
            case 'split_all_functions':
                $r[] = '442_split_all_functions';
                break;
            case 'forbid_unknown_functions':
                $r[] = '541_no_unknown_functions';
                break;
            case 'forbid_all_functions':
                $r[] = '542_no_functions_at_all';
                break;
        }

        // Elective filters.
        if ($this->get_option('split-prefixes-from-functions')) {
            $r[] = '402_split_prefix_from_common_function_name';
        }
        if ($this->get_option('split-number-letter-boundary')) {
            $r[] = '403_split_at_number_letter_boundary';
        }
        if ($this->get_option('split-implied-variables')) {
            $r[] = '406_split_implied_variable_names';
        }
        if ($this->get_option('split-to-single-letter-variables')) {
            $r[] = '410_single_char_vars';
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

    public function custom_validation(string $input, stack_ast_container $ast): array {
        return array();
    }

    public function is_valid(): bool {
        if ($this->is_blank()) {
            return $this->get_option('allow-empty');
        }
        if ($this->input === null) {
            return false;
        }
        foreach ($this->validationstatements as $key => $value) {
            switch ($key) {
                case 'require-same-type':
                    // ATSameType result.
                    if ($value->get_list_element(1, true)->value === false) {
                        return false;
                    }
                    break;
                case 'require-lowest-terms':
                    // all_lowest_termsex result.
                    $bool = $value->get_evaluated();
                    if ($bool instanceof MP_Root) {
                        $bool = $bool->items[0];
                    }
                    if ($bool instanceof MP_Statement) {
                        $bool = $bool->statement;
                    }
                    if ($bool->value === false) {
                        return false;
                    }
                    
                    break;              
            }
        }

        return $this->input->get_valid();
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

    public function is_blank(): bool {
        if (trim($this->rawvalue) === '') {
            return true;
        }
        return false;
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

        $base['properties']['allow-words'] = [
            'default' => '',
            'type' => 'string',
            'title' => stateful_string('input_option_allowwords_label'),
            'description' => stateful_string('input_option_allowwords_description')
        ];

        $base['properties']['forbid-words'] = [
            'default' => '',
            'type' => 'string',
            'title' => stateful_string('input_option_forbidwords_label'),
            'description' => stateful_string('input_option_forbidwords_description')
        ];



        // The types of things to forbid in the syntax of the input.
        $base['properties']['forbid-floats'] = [
            'default' => true,
            'type' => 'boolean',
            'title' => stateful_string('input_option_forbidfloats_label'),
            'description' => stateful_string('input_option_forbidfloats_description')
        ];
        $base['properties']['forbid-strings'] = [
            'default' => true,
            'type' => 'boolean',
            'title' => stateful_string('input_option_forbidstrings_label'),
            'description' => stateful_string('input_option_forbidstrings_description')
        ];
        $base['properties']['forbid-lists'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_forbidlists_label'),
            'description' => stateful_string('input_option_forbidlists_description')
        ];
        $base['properties']['forbid-sets'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_forbidsets_label'),
            'description' => stateful_string('input_option_forbidsets_description')
        ];
        $base['properties']['forbid-groups'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_forbidgroups_label'),
            'description' => stateful_string('input_option_forbidgroups_description')
        ];

        $base['properties']['require-same-type'] = [ 
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_require_same_type_label'),
            'description' => stateful_string('input_option_require_same_type_description')
        ];  


        $functionsplit = [
            ['enum' => ['nothing'], 'description' => stateful_string('input_option_function_handling_nothing_special')],
            ['enum' => ['split_unknown_functions'], 'description' => stateful_string('input_option_function_handling_split_unknown')],
            ['enum' => ['split_all_functions'], 'description' => stateful_string('input_option_function_handling_split_all')],
            ['enum' => ['forbid_unknown_functions'], 'description' => stateful_string('input_option_function_handling_forbid_unknown')],
            ['enum' => ['forbid_all_functions'], 'description' => stateful_string('input_option_function_handling_forbid_all')]
        ];

        $base['properties']['forbid-functions'] = [
            'default' => 'nothing',
            'type' => 'string', 
            'oneOf' => $functionsplit,
            'title' => stateful_string('input_option_function_handling_label'),
            'description' => stateful_string('input_option_function_handling_description')
        ];


        // What is acceptable to fix.
        $base['properties']['fix-spaces'] = [ 
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_fixspaces_label'),
            'description' => stateful_string('input_option_fixspaces_description')
        ];
        $base['properties']['fix-stars'] = [ 
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_fixstars_label'),
            'description' => stateful_string('input_option_fixstars_description')
        ];

        // Where to suggest or apply fixes.
        $base['properties']['split-number-letter-boundary'] = [ 
            'default' => true,
            'type' => 'boolean',
            'title' => stateful_string('input_option_split_number_letter_boundary_label'),
            'description' => stateful_string('input_option_split_number_letter_boundary_description')
        ];
        $base['properties']['split-prefixes-from-functions'] = [ 
            'default' => true,
            'type' => 'boolean',
            'title' => stateful_string('input_option_split_prefixes_from_functions_label'),
            'description' => stateful_string('input_option_split_prefixes_from_functions_description')
        ];
        $base['properties']['split-implied-variables'] = [ 
            'default' => true,
            'type' => 'boolean',
            'title' => stateful_string('input_option_split_implied_variables_label'),
            'description' => stateful_string('input_option_split_implied_variables_description')
        ];
        $base['properties']['split-to-single-letter-variables'] = [ 
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_split_to_single_letter_variables_label'),
            'description' => stateful_string('input_option_split_to_single_letter_variables_description')
        ];

        // Other things.
        $base['properties']['require-lowest-terms'] = [ 
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_require_lowest_terms_label'),
            'description' => stateful_string('input_option_require_lowest_terms_description')
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

        return $base;
    }

    public function get_default_values(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }

        $base = parent::get_default_values();
        $base['input-width'] = 15;
        $base['validation-box'] = 'automatic with list of variables';
        $base['allow-words'] = '';
        $base['forbid-words'] = '';

        $base['require-same-type'] = false;
        $base['forbid-floats'] = true;
        $base['forbid-strings'] = true;
        $base['forbid-lists'] = false;
        $base['forbid-sets'] = false;
        $base['forbid-groups'] = false;
        $base['forbid-functions'] = 'nothing';

        $base['fix-spaces'] = false;
        $base['fix-stars'] = false;

        $base['split-number-letter-boundary'] = true;
        $base['split-prefixes-from-functions'] = true;
        $base['split-implied-variables'] = true;
        $base['split-to-single-letter-variables'] = false;

        $base['require-lowest-terms'] = false;

        $base['input-align'] = 'browser default';

        return $base;
    }

    public function get_layout_for_options(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }

        $base = parent::get_layout_for_options();
        $base['fieldsets'][] = array('title' => stateful_string('input_options_size'), 'fields' => ['input-width', 'input-align']);
        $base['fieldsets'][] = array('title' => stateful_string('input_options_keywords'), 'fields' => ['allow-words', 'forbid-words']);
        $base['fieldsets'][] = array('title' => stateful_string('input_options_types'), 'fields' => ['forbid-strings', 'forbid-floats', 'forbid-lists', 'forbid-sets', 'forbid-groups', 'forbid-functions']);
        $base['fieldsets'][] = array('title' => stateful_string('input_options_acceptable'), 'fields' => ['fix-stars', 'fix-spaces']);
        $base['fieldsets'][] = array('title' => stateful_string('input_options_fixes'), 'fields' => ['split-number-letter-boundary', 'split-prefixes-from-functions', 'split-implied-variables', 'split-to-single-letter-variables']);

        $base['fieldsets'][] = array('title' => stateful_string('input_options_requirements'), 'fields' => ['require-lowest-terms', 'require-same-type']);

        if (!isset($base['widgets'])) {
            $base['widgets'] = [];
        }
        $base['widgets']['forbid-functions'] = 'select';
        $base['widgets']['input-align'] = 'select';
        return $base;
    }

    public function get_initialisation_commands(): string {
        $init = 'block([simp],simp:false,[' . $this->rawteachersanswer . ',stack_dispvalue(' . $this->rawteachersanswer . ')])';
        $validation = stack_ast_container::make_from_teacher_source($init, 'ta for ' . $this->get_name());
        // Could throw some exceptions here?
        $validation->get_valid();
        // As long as we do not throw a custom one above it gets 
        // thrown here.
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
        if (!$this->is_valid() || !$this->security->get_units()) {
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

    public function get_errors(): array {
        // Collect potenttial errors from other sources
        $posterrors = [];
        foreach ($this->validationstatements as $key => $value) {
            switch ($key) {
                case 'require-same-type':
                    // ATSameType result.
                    if ($value->get_list_element(1, true)->value === false) {
                        $posterrors = array_merge($posterrors, [stack_maxima_translate($value->get_feedback())]);
                    }
                    break;
                case 'require-lowest-terms':
                    // all_lowest_termsex result.
                    $bool = $value->get_evaluated();
                    if ($bool instanceof MP_Root) {
                        $bool = $bool->items[0];
                    }
                    if ($bool instanceof MP_Statement) {
                        $bool = $bool->statement;
                    }
                    if ($bool->value === false) {
                        $posterrors[] = stack_string('Lowest_Terms');
                    }
                    break;
            }
        }       

        return array_merge($this->preerrors, $posterrors);
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

}