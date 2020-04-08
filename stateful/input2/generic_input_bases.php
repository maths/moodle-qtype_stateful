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

// This is special. Would have wanted to require this as PHP-module
// but as the packaging of it in various platforms is quite bad and
// one cannot make a quitable requirement we need to include it in
// our plugin...
use Swaggest\JsonSchema\Schema;
require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/../../locallib.php';
require_once __DIR__ . '/input_interfaces.php';
require_once __DIR__ . '/vboxes/basic_validation_box.class.php';
require_once __DIR__ . '/../castext2/utils.php';


abstract class stateful_input_base_with_options implements stateful_input_options {

    private $name;
    private $options;


    public function __construct(
        string $name) {
        $this->name  = $name;
        $this->options = array();
    }

    public function get_name(): string {
        return $this->name;
    }

    abstract public function get_type(): string;

    public function get_expected_data(): array {
        $r = array($this->get_name() => PARAM_RAW);
        if ($this->get_option('must-verify')) {
            $r[$this->get_name() . '__val'] = PARAM_RAW;
        }
        return $r;
    }

    abstract public function get_validation_statements(array $response, stack_cas_security $rules): array;

    abstract public function is_valid(): bool;

    abstract public function is_blank(): bool;

    abstract public function is_valid_and_validated_or_blank(): bool;

    abstract public function summarise(): string;

    abstract public function render_controls(array $values, string $prefix, bool $readonly = false): string;

    public function render_scripts(string $prefix, bool $readonly = false): array {
        // Most inputs do not need scripts.

        // But most do have ajax validation.
        if (!$readonly && $this->get_option('validation-box') !== null && $this->get_option('validation-box') !== '') {
            $keys = [];
            foreach ($this->get_expected_data() as $key => $value) {
                if (strlen($key) < 5 || substr($key, -5) !== '__val') {
                    $keys[] = $key;
                }
            }
            $r = [];
            $r['js_call_amd'] = [];
            $r['js_call_amd'][] = ['qtype_stateful/ajaxvalidation',
                'register',
                [$keys, $prefix]];
            return $r;
        }

        return array();
    }

    public function get_schema_for_options(): array {
        // These are basic options that should be modified and/or dropped.
        static $s = array();
        if (!empty($s)) {
            return $s;
        }

        // The top level thing is a dictionary/object.
        $s['type'] = 'object';
        $s['properties'] = array();

        $s['properties']['allow-empty'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_allowempty_label'),
            'description' => stateful_string('input_option_allowempty_description')
        ];

        $s['properties']['hide-answer'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_hideanswer_label'),
            'description' => stateful_string('input_option_hideanswer_description')
        ];

        $s['properties']['guidance-label'] = [
            'default' => '$INPUT_NAME',
            'type' => 'string',
            'title' => stateful_string('input_option_guidancelabel_label'),
            'description' => stateful_string('input_option_guidancelabel_description')
        ];

        $s['properties']['syntax-hint'] = [
            'default' => '',
            'type' => 'string',
            'title' => stateful_string('input_option_syntaxhint_label'),
            'description' => stateful_string('input_option_syntaxhint_description')
        ];

        $s['properties']['no-units'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_nounits_label'),
            'description' => stateful_string('input_option_nounits_description')
        ];

        $oneof = [];
        foreach (stack_options::get_syntax_attribute_options() as $key => $value) {
            $oneof[] = ['enum'=> [$key], 'description' => $value];
        }

        $s['properties']['syntax-hint-type'] = [
            'default' => 1,
            'type' => 'integer', 
            'oneOf' => $oneof,
            'title' => stateful_string('input_option_syntaxhinttype_label'),
            'description' => stateful_string('input_option_syntaxhinttype_description')
        ];

        $s['properties']['validation-box'] = [
            'default' => 'automatic with list of variables',
            'type' => 'string',
            'title' => stateful_string('input_option_validationbox_label'),
            'description' => stateful_string('input_option_validationbox_description')
        ];        

        $s['properties']['must-verify'] = [
            'default' => true,
            'type' => 'boolean',
            'title' => stateful_string('input_option_mustverify_label'),
            'description' => stateful_string('input_option_mustverify_description')
        ];

        return $s;
    }

    public function get_layout_for_options(): array {
        static $l = array();

        if (!empty($l)) {
            return $l;
        }

        // If one wanted to override a widget of a given field.
        $l['widgets'] = array();
        $l['widgets']['validation-box'] = 'validationbox_declare';
        $l['widgets']['syntax-hint-type'] = 'select';
        
        $l['fieldsets'] = array();
        $l['fieldsets'][] = array('title' => stateful_string('input_options_validation'), 'fields' => ['validation-box', 'must-verify']);
        $l['fieldsets'][] = array('title' => stateful_string('input_options_common'), 'fields' => ['allow-empty', 'guidance-label', 'hide-answer', 'no-units']);
        $l['fieldsets'][] = array('title' => stateful_string('input_options_syntaxhint'), 'fields' => ['syntax-hint', 'syntax-hint-type']);

        return $l;
    }

    public function set_options(array $options): void {
        $this->options = $options;
    }

    public function get_default_values(): array {
        // These are basic options that should be modified and/or dropped.
        $defaults = array();

        if (!empty($defaults)) {
            return $defaults;
        }

        $defaults['allow-empty'] = false;
        $defaults['syntax-hint'] = '';
        $defaults['syntax-hint-type'] = 1;
        $defaults['validation-box'] = 'automatic with list of variables';
        $defaults['must-verify'] = true;
        $defaults['hide-answer'] = false;
        $defaults['no-units'] = false;
        $defaults['guidance-label'] = '$INPUT_NAME';

        return $defaults;
    }

    public function get_option(string $key) {
        if (isset($this->options[$key])) {
            $v = $this->options[$key];
            if (is_string($v) && strpos($v, '$INPUT_NAME') !== false) {
                $v = str_replace('$INPUT_NAME', $this->get_name(), $v);
            }
            return $v;
        } 
        $defaults = $this->get_default_values();
        if (isset($defaults[$key])) {
            $v = $defaults[$key];
            if (is_string($v) && strpos($v, '$INPUT_NAME') !== false) {
                $v = str_replace('$INPUT_NAME', $this->get_name(), $v);
            }
            return $v;  
        } 
        return null;
    }

    // The default implementation checks the schema and if any 
    // other rules are present also them. 
    public function validate_options(): array {
        $err = $this->validate_beyond_schema();

        // Problem... The library likes objects while I like arrays.
        $data = json_decode(json_encode($this->options), false);
        $schema = Schema::import(json_decode(json_encode($this->get_schema_for_options()), false));

        try {
            $schema->in($data);
        } catch (Exception $e) {
            $key = substr($e->path, strpos($e->path, ':') + 1);
            if (!isset($err[$key])) {
                $err[$key] = [];
            }
            $err[$key][] = $e->error;
        }

        /*
        // Note this bit is from the justinrainbow/json-schema
        // version, which had the benfit of spotting multiple 
        // errors but took way too long

        if (!$validator->isValid()) {
            // Lets do some translation.
            $props = [];
            foreach ($validator->getErrors() as $error) {
                if ($error['constraint'] === 'oneOf' || $error['constraint'] === 'type' || $error['constraint'] === 'enum' || $error['constraint'] === 'minimum' || $error['constraint'] === 'maximum' || $error['constraint'] === 'uniqueItems') {
                    // Debug phase.
                } else {
                    print_r($error);
                }

                if (isset($props[$error['property']])) {
                    $props[$error['property']][$error['constraint']] =  true;
                } else {
                    $props[$error['property']] = [$error['constraint'] =>  true];
                }
            }
            foreach ($props as $key => $typesofissues) {
                if (!isset($err[$key])) {
                    $err[$key] = [];
                }
                if (isset($typesofissues['type'])) {
                    $err[$key][] = stateful_string('option_type_not_expected');
                }
                if (isset($typesofissues['oneOf']) || isset($typesofissues['enum'])) {
                    $err[$key][] = stateful_string('option_not_one_of_expected');   
                }
                if (isset($typesofissues['minimum'])) {
                    $err[$key][] = stateful_string('option_value_too_small');
                }
                if (isset($typesofissues['maximum'])) {
                    $err[$key][] = stateful_string('option_value_too_big');
                }
                if (isset($typesofissues['uniqueItems'])) {
                    $err[$key][] = stateful_string('option_repeated_value');    
                }
            }
        }

        */

        // Basic parsing check for the options that are of 
        // the casstring-type note that the schema does not declare them.
        // But we do have some hints in the layout-schema.
        $widgets = [];
        $ls = $this->get_layout_for_options();
        if (isset($ls['widgets'])) {
            $widgets = $ls['widgets'];
        }
        foreach ($widgets as $key => $value) {
            if (strpos($value, 'casstring') !== false) {
                // Note we do not check for type of he cas-object.
                // To do that is something that is currently beyond us.
                if (isset($this->options[$key]) && $this->options[$key] !== null && trim($this->options[$key]) != '' && is_string($this->options[$key])) {
                    $test = stack_ast_container_silent::make_from_teacher_source($this->options[$key]);
                    if (!$test->get_valid()) {
                        if (!isset($err[$key])) {
                            $err[$key] = [];
                        }
                        $err[$key][] = $test->get_errors();
                    }
                }
            }
            if (strpos($value, 'castext') !== false) {
                if (isset($this->options[$key]) && $this->options[$key] !== null && trim($this->options[$key]) != '' && is_string($this->options[$key])) {
                    $tests = castext2_parser_utils::get_casstrings($this->options[$key]);
                    foreach ($tests as $test) {
                        if (!$test->get_valid()) {
                            if (!isset($err[$key])) {
                                $err[$key] = [];
                            }
                            $err[$key][] = $test->get_errors();
                        }
                    }
                }
            }
        }

        return $err;
    }

    // Should you need to validate something that the schema cannot 
    // easily declare then use this.
    public function validate_beyond_schema(): array {
        return array();
    }

    public function serialize(bool $prunedefaults): array {
        $r = [];
        $r['name'] = $this->get_name();
        $r['type'] = $this->get_type();
        if ($this->options !== null) {
            $r = $r + $this->options;
        }


        if ($prunedefaults) {
            foreach ($this->get_default_values() as $key => $value) {
                if (isset($r[$key]) && $r[$key] === $value) {
                    unset($r[$key]);
                }
            }
        }

        return $r;
    }

    abstract public function value_to_response(MP_Node $value): array;
}

abstract class stateful_input_base_with_options_and_validation extends stateful_input_base_with_options implements stateful_input_validation_source {

    public function get_validation_box(array $existing): ?stateful_input_validation_box {
        switch ($this->get_option('validation-box')) {
            case 'automatic without listings':
                if (isset($existing[$this->get_name()])) {
                    return $existing[$this->get_name()];
                }
                return new stateful_basic_validation_box($this->get_name(), false, false);
            case 'automatic with list of variables':
                if (isset($existing[$this->get_name()])) {
                    return $existing[$this->get_name()];
                }
                return new stateful_basic_validation_box($this->get_name(), true, false);
            case 'automatic with list of units':
                if (isset($existing[$this->get_name()])) {
                    return $existing[$this->get_name()];
                }
                return new stateful_basic_validation_box($this->get_name(), false, true);
            case '':
                return null;
            default:
                return $existing[$this->get_option('validation-box')];
        }
    }

    public function get_functions(): array {
        // Override if you can give these.
        return array();
    }

    public function get_variables(): array {
        // Override if you can give these.
        return array();
    }

    public function get_units(): array {
        // Override if you can give these.
        return array();
    }

    abstract public function get_errors(): array;

    abstract public function get_val_field_value(): string;

    public function get_value_override(): string {
        return $this->get_name();
    }

    public function get_invalid_value_override(): ?string {
        return null;
    }
}