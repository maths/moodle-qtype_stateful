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
require_once __DIR__ . '/basic_validation_box.class.php';

class stateful_custom_validation_box implements stateful_input_validation_box {
    private $name;
    private $inputs;
    private $cached;

    private $castext;

    private $statement;

    public function __construct(
        string $name,
        string $castext
    ) {
        $this->name      = $name;
        $this->cached    = false;
        $this->inputs    = array();
        $this->castext   = $castext;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function get_type(): string {
        return 'custom';
    }

    public function register_input(stateful_input $input): void {
        $this->inputs[$input->get_name()] = $input;
    }

    public function set_cached(string $value): void {
        $this->cached = $value;
    }

    public function get_cached(): string {
        if ($this->cached === false) {
            $this->cached = castext2_parser_utils::compile($this->castext);
        }

        return $this->cached;
    }

    public function get_render_statements(): array {
        $allblank = true;
        foreach ($this->inputs as $input) {
            if (!$input->is_blank()) {
                $allblank = false;
                break;
            }
        }
        if ($allblank) {
            // If no input has value then nothing to render.
            return array();
        }

        // Now then as we have multiple inputs in play we need to deal with
        // the issues of some of them being invalid or blank, therefore the rendering
        // logic needs to be given some fake values. Also some helppers like
        // %_blank_X and %_valid_X.
        $overrides = array();
        $extras = array();
        foreach ($this->inputs as $input) {
            $overrides['%_blank_' . $input->get_name()] = $input->is_blank() ? 'true' : 'false';
            $overrides['%_valid_' . $input->get_name()] = $input->is_valid() ? 'true' : 'false';
            // If the input is MCQ or numeric the input may itself override
            // the value e.g. wrap in `dispdp(...)` in such a case the original
            // value will be accessible through this:
            $overrides['%_actual_'  . $input->get_name()] = $input->get_name();
            if ($input->is_blank()) {
                $overrides[$input->get_name()] = stack_utils::php_string_to_maxima_string('\(\color{orange}{?}\)');
            } else if ($input->is_valid()) {
                if ($input->get_name() !== $input->get_value_override()) {
                    $overrides[$input->get_name()] = $input->get_value_override();
                }
            }
            if (!$input->is_valid() && !$input->is_blank()) {
                $escaped = $input->get_string_value();
                // TODO: make a fuction of this escape thing.
                $escaped = stateful_utils::inert_latex($escaped);

                $overrides[$input->get_name()] = stack_utils::php_string_to_maxima_string('\(\color{red}{' .  $escaped . '}\)');    
            }
            $extras[] = stack_utils::php_string_to_maxima_string($input->get_name());
            if (count($input->get_variables()) === 0) {
                $extras[] = '{}';
            } else {
                // We need to actually check if the identifiers are
                // constant or variables and we only show variables.
                // Variables are not bound its that simple.
                $log = [];
                foreach ($input->get_variables() as $id) {
                    $log[] = 'if not constantp(' . $id . ') then stack_disp(\'' . $id . ',"") else ""';

                }
                $extras[] = '{' . implode(',', $log) . '}';
            }
            if (count($input->get_units()) === 0) {
                $extras[] = '{}';
            } else {
                $extras[] = '{stack_disp(\'' . implode(',""),stack_disp(\'', $input->get_units()) . ',"")}';
            }
        }

        $statement = 'ev([' . $this->cached;
        if (count($extras) > 0) {
            // Should never be 0.
            $statement .= ',' . implode(',', $extras);
        }
        $statement .= ']';
        if (count($overrides) > 0) {
            // Should never be 0.
            foreach ($overrides as $key => $value) {
                $statement .= ',' . $key . '=' . $value;
            }
        }
        $statement .= ')';

        $this->statement = new stack_secure_loader_value($statement, 'validation ' . $this->name);
        return array($this->statement);
    }

    public function render(): string {
        $allblank = true;
        foreach ($this->inputs as $input) {
            if (!$input->is_blank()) {
                $allblank = false;
                break;
            }
        }

        if ($allblank) {
            // If no input has value then nothing to render.
            return '';
        }

        // First unpack the evaluated statement.
        $value = $this->statement->get_value();
        if ($value instanceof MP_Root) {
            $value = $value->items[0];
        }
        if ($value instanceof MP_Statement) {
            $value = $value->statement;
        }

        // The first item in the list is the castext as evaluated, that needs postprocessing.
        $evaluatedcastext = $value->items[0];

        // Collect the other values.
        $errors = array();
        $variables = array();
        $units = array();

        foreach ($this->inputs as $input) {
            if (!$input->is_blank()) {
                $errors[$input->get_name()] = $input->get_errors();
            } else {
                $errors[$input->get_name()] = array();
            }
        }
        for ($i = 1; $i < count($value->items); $i = $i + 3) {
            // The values are stored in the list as triplets, the first item being 
            // the input name and then the sets of variables and units.
            $input = $value->items[$i]->value;
            $variables[$input] = stateful_utils::mp_to_php($value->items[$i+1]);
            $units[$input] = stateful_utils::mp_to_php($value->items[$i+2]);
        }


        // Build the processor. Which handles the special input blocks i.e.
        // [[list_errors:ans1,ans2]] and such.
        $processor = new stateful_validation_castext_processor(new castext2_default_processor(), $errors, $variables, $units);

        // Process.
        return castext2_parser_utils::postprocess_mp_parsed($evaluatedcastext, $processor);

    }

    public function serialize(): array {
        $r = ['name' => $this->get_name(), 'type' => 'custom'];
        $r['text'] = $this->castext;

        return $r;
    }
}