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


class stateful_input_string extends stateful_input_base_with_options_and_validation implements 
    stateful_input_teachers_answer_handling,
    stateful_input_caching_initialisation {

    private $rawteachersanswer;
    private $evaluatedteachersanswer;

    private $value;
    private $val;
    private $security;

    public function get_type(): string {
        return 'string';
    }

    public function get_validation_statements(array $response, stack_cas_security $rules): array {
        $this->security = $rules;
        // Collect the value.
        $this->value = '';
        if (isset($response[$this->get_name()])) {
            $this->value = $response[$this->get_name()];
        }
        // Also the validation.
        $this->val = null;
        if (isset($response[$this->get_name() . '__val'])) {
            $this->val = $response[$this->get_name() . '__val'];
        }

        // There is nothing to say about string inputs in the sense of validation.
        return array();
    }

    // Tells if the input has a valid value. Only called after whatever
    // get_validation_statements() returned have been evaluated.
    public function is_valid(): bool {
        if ($this->get_option('string-json-mode')) {
            // Test parse it.
            json_decode($this->value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }
        }

        if (trim($this->value) === '') {
            return $this->get_option('allow-empty');
        }
        
        return true;
    }

    // Tells if the input is blank, if all inputs are blank then nothing happens.
    public function is_blank(): bool {
        if (trim($this->value) === '') {
            return true;
        }
        return false;
    }

    public function is_valid_and_validated_or_blank(): bool {
        if (trim($this->value) === '') {
            return true;
        }
        if ($this->get_option('must-verify')) {
            // The thing is valid only when it has been validated.
            return $this->val === $this->value;
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
            return $this->get_name() . ': ' . $this->value . ' [VALID]';
        }
    }

    public function render_controls(array $values, string $prefix, bool $readonly = false): string {
        $fieldname = $prefix . $this->get_name();
        $attributes = array(
            'name'  => $fieldname,
            'id'    => $fieldname,
            'autocapitalize' => 'none',
            'spellcheck'     => 'false',
            'aria-label' => $this->get_option('guidance-label')
        );
        if ($readonly) {
            $attributes['disabled'] = 'disabled';
        }

        $value = '';
        if (isset($values[$this->get_name()])) {
            $value = $values[$this->get_name()];
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

        // If we have height we draw a textarea
        if ($this->get_option('input-height') > 1) {
            $attributes['cols'] = $this->get_option('input-width');
            $attributes['rows'] = $this->get_option('input-height');

            return html_writer::tag('textarea', htmlspecialchars($value), $attributes) . $val;
        } else {
            $attributes['size'] = $this->get_option('input-width');
            $attributes['value'] = $value;
            $attributes['type'] = 'text';

            return html_writer::empty_tag('input', $attributes) . $val;
        }
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
        $base['properties']['input-height'] = [
            'type' => 'integer', 
            'minimum' => 1,
            'maximum' => 80,
            'default' => 1,
            'title' => stateful_string('input_option_inputheight_label'),
            'description' => stateful_string('input_option_inputheight_description')
        ];
        $base['properties']['string-json-mode'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_jsonmode_label'),
            'description' => stateful_string('input_option_jsonmode_description')
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
        $base['input-height'] = 1;
        $base['validation-box'] = 'automatic without listings';
        $base['string-json-mode'] = false;
        return $base;
    }

    public function get_layout_for_options(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_layout_for_options();
        $base['fieldsets'][] = ['title' => stateful_string('input_options_size'), 'fields' => ['input-width', 'input-height']];
        $base['fieldsets'][] = ['title' => stateful_string('input_options_string'), 'fields' => ['string-json-mode']];
        $base['widgets']['validation-box'] = 'validationbox_declare';
        return $base;
    }

    public function get_initialisation_commands(): string {
        $validation = stack_ast_container::make_from_teacher_source($this->rawteachersanswer, 'ta for ' . $this->get_name());
        // Could throw some exceptions here?
        $validation->get_valid();
        return $validation->get_evaluationform();
    }

    public function set_initialisation_value(MP_Node $value): void {
        $this->evaluatedteachersanswer = $value;
        if ($value instanceof MP_Root) {
            $this->evaluatedteachersanswer = $value->items[0];
        }
        if ($this->evaluatedteachersanswer instanceof MP_Statement) {
            $this->evaluatedteachersanswer = $this->evaluatedteachersanswer->statement;
        }
    }

    public function set_teachers_answer(string $answer): void {
        $this->rawteachersanswer = $answer;
    }

    public function get_correct_input_guide(): string {
        if ($this->get_option('hide-answer')) {
            return '';
        }

        $r = '<code>' . htmlspecialchars($this->evaluatedteachersanswer->value) . '</code>';

        if ($this->get_option('guidance-label') !== $this->get_name() && $this->get_option('guidance-label') !== '') {
            $r = stateful_string('input_into', $this->get_option('guidance-label')) . $r;
        }
        return $r;
    }

    public function get_correct_response(): array {
        return array($this->get_name() => $this->evaluatedteachersanswer->value);
    }

    public function get_value(): cas_evaluatable {
        if ($this->get_option('string-json-mode')) {
            $parsed = json_decode($this->value, true);
            $mapped = stateful_utils::php_to_maxima($parsed);
            return stack_ast_container_silent::make_from_student_source($this->get_name() . ':' . $mapped, $this->get_name() . ' input value', $this->security);
        }

        // The proper way to escape a string. Uses the rules of the parser.
        $s = new MP_String($this->value);

        return stack_ast_container_silent::make_from_student_source($this->get_name() .
            ':' . $s->toString(), $this->get_name() . ' input value', $this->security);
    }
    
    public function get_string_value(): string {
        if ($this->value === null) {
            return '';
        }
        return $this->value;
    }

    public function get_value_override(): string {
        if ($this->get_option('string-json-mode')) {
            // Lets pretty print it just for fun.
            $parsed = json_decode($this->value, true);
            $pretty = json_encode($parsed, JSON_PRETTY_PRINT);
            $s = new MP_String('<pre>' . $pretty . '</pre>');
            return $s->toString();
        }
        return $this->get_name();
    }

    // There is nothing to say about string inputs in the sense of validation.
    public function get_errors(): array {
        if ($this->get_option('string-json-mode') && !$this->is_valid()) {
            return array(stateful_string('json_input_parse_error'));
        }
        return array();
    }

    public function serialize(bool $prunedefaults): array {
        $r = parent::serialize($prunedefaults);
        $r['tans'] = $this->rawteachersanswer;
        return $r;
    }

    public function value_to_response(MP_Node $value): array {
        $mpstring = $value;
        if ($mpstring instanceof MP_Root) {
            $mpstring = $mpstring->items[0];
        }
        if ($mpstring instanceof MP_Statement) {
            $mpstring = $mpstring->statement;
        }

        $r = [];
        if (!($mpstring instanceof MP_String) && !$this->get_option('string-json-mode')) {
            throw new stateful_exception('trying to map a non string input value to a string input');
        }
        if ($this->get_option('string-json-mode')) {
            $php = stateful_utils::mp_to_php($mpstring);
            $pretty = json_encode($php, JSON_PRETTY_PRINT);
            $r[$this->get_name()] = $pretty;
        } else {
            $r[$this->get_name()] = $mpstring->value;
        }

        if ($this->get_option('must-verify')) {
            $r[$this->get_name() . '__val'] = $r[$this->get_name()];
        }

        return $r;
    }

    public function get_val_field_value(): string {
        return $this->value;
    }
}