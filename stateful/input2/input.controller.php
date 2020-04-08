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
 * This class handles everyhing related to inputs. You just give the definitions 
 * of inputs and the session (question-variables equivalent) to build on and it 
 * will add initialisation paramters to it for initialisation of everything.
 *
 * After initialisation you can give it the whole response array and a similar 
 * session (just the question-variables equivalent details is enough) and it 
 * will collect all validation details to it, render all the validation messages 
 * and generates the input values for the grading/feedback generation step.
 */
require_once __DIR__ . '/input_interfaces.php';
require_once __DIR__ . '/vboxes/custom_validation_box.class.php';
require_once __DIR__ . '/vboxes/basic_validation_box.class.php';


class stateful_input_controller {

    static private $registered_inputs = null;

    // Map of input objects by name.
    private $inputs;

    // Map of validation boxes by name.
    private $validation;

    // Precompiled things for bits that can work with them.
    private $cache;

    // Cached statements
    private $statements;

    // Status tracking, for usage errors.
    private $initializationcollected = false;
    private $initializationdelivered = false;
    private $validationcollected = false;
    private $validationrendercollected = false;

    // Tool for those needing an input object.
    public static function get_input_instance(string $type, string $name): stateful_input {
        if (stateful_input_controller::$registered_inputs === null) {
            stateful_input_controller::$registered_inputs = array();
            foreach (new DirectoryIterator(__DIR__ . '/inputs') as $item) {
                if ($item->isDot()) {
                    continue;
                }
                
                if (!$item->isDir()) {
                    $itemname = $item->getFilename();
                    if (substr($itemname, strlen($itemname) - strlen('.input.php')) === '.input.php') {
                        include_once(__DIR__ . '/inputs/' . $itemname);
                        $inputname = substr($itemname, 0, -strlen('.input.php'));
                        $class = "stateful_input_{$inputname}";
                        if (!class_exists($class)) {
                            continue;
                        }
                        stateful_input_controller::$registered_inputs[$inputname] = new $class('example');
                    }
                }
            }
        }
        if (isset(stateful_input_controller::$registered_inputs[$type])) {
            $class = 'stateful_input_' . $type;
            return new $class($name);
        }

        throw new stateful_exception('unknown input type: ' . $type);
    }

    public static function get_input_metadata(): array {
        if (stateful_input_controller::$registered_inputs === null) {
            // If we need to search for the classes...
            stateful_input_controller::get_input_instance('algebraic', 'init');
        }
        $r = array('schema' => array(), 'layout' => array());
        foreach (stateful_input_controller::$registered_inputs as $key => $input) {
            $r['schema'][$key] = array();
            $r['layout'][$key] = array();
            $r['tans-required'][$key] = $input instanceof stateful_input_teachers_answer_handling;
            if ($input instanceof stateful_input_options) {
                $r['schema'][$key] = $input->get_schema_for_options();
                $r['layout'][$key] = $input->get_layout_for_options();
            }
        }

        return $r;
    }


    // For when you need a validation box.
    public static function get_validation_box_instance(string $type, string $name, array $options): stateful_input_validation_box {
        if ($type === 'custom') {
            return new stateful_custom_validation_box($name, $options['text']);
        } else if ($type === 'auto') {
            $vars = true;
            $units = false;
            if (isset($options['list_variables'])) {
                $vars = $options['list_variables'];
            }
            if (isset($options['list_units'])) {
                $vars = $options['list_units'];
            }
            return new stateful_basic_validation_box($name, $vars, $units);
        }
        throw new stateful_exception('unknown validation box type: ' . $type);
    }

    // Constructs a controller from json/array representation of the inputs.
    // Give the cache array at this point and check it for changes after 
    // initialisation.
    public static function make_from_json(array $inputdeclarations, array $validationdeclarations, array $cache): stateful_input_controller {

        $ctrl = new stateful_input_controller();
        $ctrl->inputs = array();
        $ctrl->validation = array();
        $ctrl->cache = $cache;
        $ctrl->statements = array();

        // First declare the exotic boxes.
        foreach ($validationdeclarations as $name => $options) {
            $vbox = self::get_validation_box($name, $options);
            $ctrl->validation[$name] = $vbox;
        }

        // Then the inputs that register to them.
        foreach ($inputdeclarations as $name => $options) {
            $input = self::get_input_instance($options['type'], $name);
            $ctrl->inputs[$name] = $input;

            if ($input instanceof stateful_input_teachers_answer_handling) {
                // Most but not all inputs need a teachers answer.
                if (isset($options['tans'])) {
                    $input->set_teachers_answer($options['tans']);
                }
            }

            if ($input instanceof stateful_input_options) {
                // Most inputs receive options.
                $opt = clone $options;
                unset($opt['type']);
                if (isset($options['tans'])) {
                    unset($opt['tans']);
                }
                $input->set_options($opt);
            }

            if ($input instanceof stateful_input_validation_source) {
                // Some inputs declare a validation box of an automated 
                // template style other reference a customised one.
                $vbox = $input->get_validation_box($ctrl->validation);
                if ($vbox !== null) {
                    $ctrl->validation[$vbox->get_name()] = $vbox;
                    $vbox->register_input($input);
                }
            }
        }

        return $ctrl;
    }

    public static function make_from_objects(array $inputs, array $validation_boxes, array $cache): stateful_input_controller { 

        $ctrl = new stateful_input_controller();
        $ctrl->inputs = array();
        $ctrl->validation = array();
        $ctrl->cache = $cache;
        $ctrl->statements = array();

        foreach ($validation_boxes as $vbox) {
            $ctrl->validation[$vbox->get_name()] = $vbox;
        }

        foreach ($inputs as $input) {
            $ctrl->inputs[$input->get_name()] = $input;

            // Note that when working with objects we assume that the options 
            // and teachers answers have been already given to the objects.
            if ($input instanceof stateful_input_validation_source) {
                // Some inputs declare a validation box of an automated 
                // template style other reference a customised one.
                $vbox = $input->get_validation_box($ctrl->validation);
                if ($vbox !== null) {
                    $ctrl->validation[$vbox->get_name()] = $vbox;
                    // No need to register at upper layers, but if has been
                    // then we simply overwrite.
                    $vbox->register_input($input);
                }
            }
        }

        return $ctrl;
    }


    // returns the cache for upper layers to store where ever they store it if at all.
    public function get_cache(): array {
        return $this->cache;
    }

    // Collects all the initialisation statements for all inputs.
    public function collect_initialisation_statements(): array {
        $this->initializationcollected = true;
        $r = array();
        if (!isset($this->cache['input-initialisation'])) {
            $this->cache['input-initialisation'] = array();
        }
        foreach ($this->inputs as $name => $input) {
            if ($input instanceof stateful_input_caching_initialisation) {
                if (!isset($this->cache['input-initialisation'][$name])) {
                    $this->cache['input-initialisation'][$name] = $input->get_initialisation_commands();
                }
                $this->statements[$name] = new stack_secure_loader_value($this->cache['input-initialisation'][$name], 'input-init: ' . $name);
                $r[] = $this->statements[$name];
            } else if ($input instanceof stateful_input_non_caching_initialisation) {
                foreach ($input->get_initialisation_commands() as $stmt) {
                    // We do not need to hold onto these as they are held 
                    // by the other end.
                    $r[] = $stmt;
                }
            }
        }
        return $r;
    }

    // Deliver the initialisation results for those that have cached
    // initialisation.
    public function deliver_initialisation_results() {
        if ($this->initializationcollected === false) {
            throw new stateful_exception('Attempted to deliver initialisation before collecting initialisation');
        }
        $this->initializationdelivered = true;
        // First provide the initialisation values for those that had 
        // cached ones.
        foreach ($this->statements as $name => $stmt) {
            $this->inputs[$name]->set_initialisation_value($stmt->get_value());
        }
    }

    // After initialisation has been handled we can collect validation
    // logic for things that need CAS-validation.
    public function collect_validation_statements(array $response, stack_cas_security $rules): array {
        if ($this->initializationdelivered === false) {
            throw new stateful_exception('Attempted validation before initialisation delivery');
        }
        $this->validationcollected = true;

        $r = array();
        foreach ($this->inputs as $name => $input) {
            foreach ($input->get_validation_statements($response, $rules) as $stmt) {
                $r[] = $stmt;
            }
        }
        return $r;
    }

    // Once the validation statements have been evaluated we can ask if 
    // the inputs are valid.
    public function all_valid(bool $non_blank_only = true): bool {
        foreach ($this->inputs as $input) {
            if ($non_blank_only && $input->is_blank()) {
                continue;
            }
            if (!$input->is_valid()) {
                return false;
            }
        }
        return true;
    }

    public function all_blank(): bool {
        foreach ($this->inputs as $input) {
            if (!$input->is_blank()) {
                return false;
            }
        }
        return true;
    }

    public function all_valid_and_validated_or_blank() {
        foreach ($this->inputs as $input) {
            if (!$input->is_valid_and_validated_or_blank()) {
                return false;
            }
        }
        return true;    
    }

    // Check validity for a subset of inputs.
    // Depending on how you have defined the requirements you may need to
    // flip that bit...
    public function has_valid_for(array $inputnames, bool $checkbyvalue = true): bool {
        if ($checkbyvalue) {
            foreach ($inputnames as $name) {
                if (!$this->inputs[$name]->is_valid()) {
                    return false;
                }
            }
        } else {
            foreach ($inputnames as $name => $duh) {
                if (!$this->inputs[$name]->is_valid()) {
                    return false;
                }
            }
        }
        return true;
    }

    // Construct summary of input state.
    public function summarise(): string {
        $r = [];
        foreach ($this->inputs as $name => $input) {
            $sum = $input->summarise();
            if ($sum !== '') {
                $r[] = $sum;
            }
        }

        return implode(', ', $r);
    }


    // Direct accessors.
    public function is_valid(string $inputname): bool {
        return $this->inputs->is_valid();
    }
    public function is_blank(string $inputname): bool {
        return $this->inputs->is_blank();
    }


    // Then we can ask for statements that define these values in the CAS.
    public function collect_cas_values(): array {
        $r = array();
        $stringform = array();
        foreach ($this->inputs as $name => $input) {
            if ($input instanceof stateful_input_cas_value_generating) {
                if ($input->is_valid()) {
                    $r[] = $input->get_value();
                    $stringform[$name] = $input->get_string_value();
                }
            }
        }
        // This is way to access the original input string, used for example 
        // for checking significant-figures from the input.
        $r[] = new stack_secure_loader('_INPUT_STRING:' . stateful_utils::php_array_to_stackmap($stringform));
        return $r;
    }

    // When we have the values we can do PRT evaluation and we can also 
    // render validation messages at the same time.
    public function collect_validation_render_statements(): array {
        $r = array();
        if ($this->validationcollected === false) {
            throw new stateful_exception('Attempted validation render before validation');
        }
        $this->validationrendercollected = true;
        if (!isset($this->cache['validation-render'])) {
            $this->cache['validation-render'] = array();
        }

        foreach ($this->validation as $name => $vbox) {
            // Do cache management.
            if (!isset($this->cache['validation-render'][$name])) {
                $this->cache['validation-render'][$name] = $vbox->get_cached();
            } else {
                $vbox->set_cached($this->cache['validation-render'][$name]);
            }
            foreach ($vbox->get_render_statements() as $stmt) {
                $r[] = $stmt;
            }
        }
        return $r;
    }

    public function collect_expected_data(): array {
        $r = [];
        foreach ($this->inputs as $name => $input) {
            $r += $input->get_expected_data();
        }
        return $r;
    }


    public function render_controls(string $prefix, string $input): string {
        if (!$this->initializationcollected) {
            throw new stateful_exception('Attempt to render input before initialisation.'); 
        }

        return $this->inputs[$input]->render_controls($prefix);
    }

    // Typical use is to request for the values when building an API.
    // Otherwise you'll want to call the apply function that will simply
    // Push them all to the $PAGE.
    public function render_scripts(string $prefix, string $input): array {
        return $this->inputs[$input]->render_scripts($prefix);
    }

    public function apply_scripts(string $prefix, $page): void {
        if (!$this->initializationcollected) {
            throw new stateful_exception('Attempt to render input before initialisation.'); 
        }
        foreach ($this->inputs as $input) {
            $scripts = $input->render_scripts($prefix);

            foreach ($scripts as $function => $calls) {
                foreach ($calls as $arguments) {
                    switch ($function) {
                        case 'js_amd_inline':
                            $page->requires->js_amd_inline($arguments[0]);
                            break;
                        case 'js_call_amd':
                            if (count($arguments) === 3) {
                                $page->requires->js_call_amd($arguments[0], $arguments[1], $arguments[2]);
                            } else if (count($arguments) === 2) {
                                $page->requires->js_call_amd($arguments[0], $arguments[1]);
                            } else {
                                $page->requires->js_call_amd($arguments[0]);
                            }
                            break;
                        default:
                            throw new stateful_exception('unknown script function');
                            break;
                    }
                }
            }
        }
    }

    public function render_validation_box(string $name): string {
        return $this->validation[$name]->render();
    }


    // Primes a cache array with anything cacheable.
    public function prime_cache(): array {
        $cache = [
            'input-initialisation' => [],
            'validation-render' => []
        ];
        foreach ($this->inputs as $name => $input) {
            if ($input instanceof stateful_input_caching_initialisation) {  
                $cache['input-initialisation'][$name] = $input->get_initialisation_commands();
            }
        }
        foreach ($this->validation as $name => $vbox) {
            $cache['validation-render'][$name] = $vbox->get_cached();
        }
        return $cache;
    }

    // General render helpper.
    public function fill_in_placeholders(string $text, string $prefix, array $values, bool $readonly): string {
        $r = $text;
        if (!$this->initializationcollected) {
            throw new stateful_exception('Attempt to render input before initialisation.'); 
        }
        if (!$this->all_blank() && !$this->validationrendercollected) {
            throw new stateful_exception('Attempt to render validation before initialisation.');    
        }
        foreach ($this->inputs as $name => $input) {
            if (mb_strpos($r, "[[input:$name]]") !== false) {
                $r = str_replace("[[input:$name]]",
                    $input->render_controls($values, $prefix, $readonly), $r);
            }
        }
        foreach ($this->validation as $name => $vbox) {
            if (mb_strpos($r, "[[validation:$name]]") !== false) {
                $attr = [
                    'class' => 'statefulvalidation',
                    'id' => $prefix . 'vbox_' . $name
                ];
                $box = $vbox->render();
                if ($box === '') {
                    $attr['style'] = 'display:none;';
                    $box = 'NO VALIDATION MESSAGES TO DISPLAY';
                }

                $box = html_writer::tag('div', $box, $attr);
                $r = str_replace("[[validation:$name]]",
                    $box, $r);
            }   
        }
        return $r;
    }

    // Primarilly, for AJAX validation use.
    public function collect_validation_box_content(): array {
        $r = [];
        foreach ($this->validation as $name => $vbox) {
            $r[$name] = $vbox->render();
        }
        return $r;
    }

    // Primarilly, for AJAX validation use.
    public function collect_val_field_values(): array {
        $r = [];
        foreach ($this->inputs as $name => $input) {
            if ($input instanceof stateful_input_validation_source) {
                $r[$name . '__val'] = $input->get_val_field_value();    
            }
        }
        return $r;  
    }

    // In testing it is necessary to have simillarilly initialised controllers
    // do paraller validation of large numbers of inputs it is better to
    // clone an initialised one than to initialise multiple.
    public function clone(): stateful_input_controller {
        $r = clone $this;

        // For this cloning what matters is that we clone the inputs
        // and vboxes, everythin else is safe. As long as inputs do not 
        // start modifying the statements they sen to CAS for evaluation.
        $inputs = [];
        $vboxes = [];
        foreach ($this->inputs as $key => $value) {
            $inputs[$key] = clone $value;
        }
        foreach ($this->validation as $key => $value) {
            $vboxes[$key] = clone $value;
        }
        $r->inputs = $inputs;
        $r->validation = $vboxes;

        return $r;
    }
}