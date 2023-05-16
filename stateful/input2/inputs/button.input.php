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
require_once __DIR__ . '/../../../stacklib.php';

class stateful_input_button extends stateful_input_base_with_options implements stateful_input_cas_value_generating, stateful_input_caching_initialisation {

    // The label in the button.
    private $label;

    // The value this button should generate.
    private $value;

    // The value received.
    private $input;
    private $rawvalue;

    public function get_type(): string {
        return 'button';
    }

    public function get_schema_for_options(): array {
        static $s = array();
        if (!empty($s)) {
            return $s;
        }
        // The top level thing is a dictionary/object.
        $s['type'] = 'object';
        $s['properties'] = array();


        $s['properties']['guidance-label'] = [
            'default' => '$INPUT_NAME',
            'type' => 'string',
            'title' => stateful_string('input_option_guidancelabel_label'),
            'description' => stateful_string('input_option_guidancelabel_description')
        ];

        $s['properties']['alias-for'] = [
            'default' => '',
            'type' => 'string',
            'title' => stateful_string('input_option_aliasfor_label'),
            'description' => stateful_string('input_option_aliasfor_description')
        ];

        $s['properties']['input-label'] = [
            'default' => 'Click',
            'type' => 'string',
            'title' => stateful_string('input_option_label_label'),
            'description' => stateful_string('input_option_label_description')
        ];

        $s['properties']['input-value'] = [
            'default' => 'true',
            'type' => 'string',
            'title' => stateful_string('input_option_value_label'),
            'description' => stateful_string('input_option_value_description')
        ];

        return $s;
    }

    public function get_layout_for_options(): array {
        static $l = array();
        if (!empty($l)) {
            return $l;
        }
        $l['widgets'] = array();
        $l['widgets']['alias-for'] = 'buttoninputref';
        $l['widgets']['input-label'] = 'castext';
        $l['widgets']['input-value'] = 'casstring';
        
        $l['fieldsets'] = array();
        $l['fieldsets'][] = array('title' => stateful_string('input_options_common'), 'fields' => ['guidance-label']);
        
        $l['fieldsets'][] = array('title' => stateful_string('input_options_button'), 'fields' => ['input-label','input-value','alias-for']);

        return $l;
    }

    public function get_default_values(): array {
        static $defaults = array();
        if (!empty($defaults)) {
            return $defaults;
        }
        $defaults['guidance-label'] = '$INPUT_NAME';
        $defaults['alias-for'] = '';
        $defaults['input-label'] = 'Click';
        $defaults['input-value'] = 'true';

        return $defaults;
    }

    public function get_validation_statements(array $response, stack_cas_security $rules): array {

        $this->input = null;
        // Collect the value.
        $this->rawvalue = '';
        if (isset($response[$this->get_name()])) {
            $this->rawvalue = $response[$this->get_name()];
        }
        $this->input = stack_ast_container::make_from_student_source($this->rawvalue, $this->get_name() . ' input validation', $rules, []);
        $this->input->get_valid();
        // Note that while we check for securitys sake that the input
        // coming in is valid we do not handle the case where it is
        // invalid, things will just not happen in that case.
        // Before you wonder why we have things coming in for a button input,
        // it is due to the 'alias-for' feature that may set values for 
        // multiple buttons and those buttons may not know what the values 
        // are, and cannot thus simply use predefined values.
        if ($this->input->get_valid()) {
            return array($this->input);
        }
        return array();
    }

    public function is_valid(): bool {
        if ($this->is_blank()) {
            return false;
        }
        return $this->input->get_valid();
    }

    public function is_blank(): bool {
        if ($this->rawvalue === null || $this->rawvalue === '') {
            return true;
        }
        return false;
    }

    public function is_valid_and_validated_or_blank(): bool {
        return true;
    }

    public function summarise(): string {
        if ($this->get_option('hide-answer')) {
            return '';
        } else {
            if ($this->is_blank()) {
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
    

    public function render_controls(array $values, string $prefix, bool $readonly = false): string {
        $fieldname = $prefix . $this->get_name();
        $r = '';

        $attributes = [
            'type' => 'hidden',
            'name' => $fieldname,
            'id' => $fieldname,
            'value' => ''
        ];

        if ($readonly) {
            $attributes['readonly'] = 'readonly';
        }

        $r .= html_writer::empty_tag('input', $attributes);


        $attributes = [
            'id' => $fieldname . '__button',
            'aria-label' => $this->get_option('guidance-label')
        ];

        if ($readonly) {
            $attributes['disabled'] = 'disabled';
        }

        return $r . html_writer::tag('button', $this->label, $attributes);
    }


    public function render_scripts(string $prefix, bool $readonly = false): array {
        $fieldname = $prefix . $this->get_name();
        $r = parent::render_scripts($prefix, $readonly);

        $alias  = '';
        if ($this->get_option('alias-for') !== null && $this->get_option('alias-for') !== '') {
            $alias = $prefix . $this->get_option('alias-for');
        }
        if (!isset($r['js_call_amd'])) {
            $r['js_call_amd'] = [];
        }
        $r['js_call_amd'][] = ['qtype_stateful/button',
            'registerButton',
            [$fieldname, $this->value, $alias]];

        return $r;
    }


    public function get_value(): cas_evaluatable {
        if ($this->input->get_valid()) {
            return new stack_secure_loader($this->get_name() . ':' . $this->input->get_evaluationform(), $this->get_name() . ' input value');
        } else {                        
            throw new stateful_exception('trying to extract invalid value for evaluation');
        }
    }
    
    public function get_string_value(): string {
        if ($this->rawvalue === null) {
            return '';
        }
        return $this->rawvalue;
    }



    public function get_initialisation_commands(): string {
        $label = castext2_parser_utils::compile($this->get_option('input-label'), null, ['errclass' => 'stateful_cas_error', 'context' => 'TODO-button'])->toString();
        $init = 'block([simp],simp:false,[' . $this->get_option('input-value') . ','. $label .'])';
        $validation = stack_ast_container::make_from_teacher_source($init, 'value and label for ' . $this->get_name());
        // Could throw some exceptions here?
        $validation->get_valid();
        return $validation->get_evaluationform();

    }

    public function set_initialisation_value(MP_Node $value): void {
        $list = $value;
        if ($list instanceof MP_Root) {
            $list = $list->items[0];
        }
        if ($list instanceof MP_Statement) {
            $list = $list->statement;
        }
        $this->value = $list->items[0]->toString();
        $this->label = castext2_parser_utils::postprocess_mp_parsed($list->items[1]);
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
        if ($this->get_option('alias-for') !== '') {
            $r[$this->get_option('alias-for')] = $r[$this->get_name()];
        }
        return $r;
    }

}