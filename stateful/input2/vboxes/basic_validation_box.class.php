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
require_once __DIR__ . '/../input_interfaces.php';
require_once __DIR__ . '/../../castext2/utils.php';

/** 
 * This is the traditional automated validation box that requires no 
 * configuration beyond the decision of using it. There are however 
 * couple of variants depending on whether you want to list certain things.
 */

class stateful_basic_validation_box implements stateful_input_validation_box {
    private $name;
    private $input;
    private $cached;
    private $variables;
    private $units;

    private $statement;

    public function __construct(
        string $name,
        bool $variables = true,
        bool $units = false
    ) {
        $this->name      = $name;
        $this->variables = $variables;
        $this->units     = $units;
        $this->cached    = false;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function get_type(): string {
        return 'auto';
    }

    public function register_input(stateful_input $input): void {
        $this->input = $input;
    }

    public function set_cached(string $value): void {
        $this->cached = $value;
    }

    public function get_cached(): string {
        if ($this->cached !== false) {
            return $this->cached;
        }

        // When actually rendering we will inject the input value to the statement
        // through ev() to ensure that no outside things affects it nor it affects
        // outside things.
        // In the case of invalid string we inject the string form suitably processed,
        // we do so to allow validation display generation using full CASText capabilities
        // even if the value is bad.

        $name = $this->input->get_name();

        $validcastext = '<p>[[commonstring key="your_answer_interpreted_as"/]]</p>';
        $validcastext .= '[[if test="stringp(' . $name . ')"]]<p style="text-align:center">{@' . $name . '@}</p>';
        $validcastext .= '[[else]]\[{@' . $name . '@}\][[/if]]';

        if ($this->variables) {
            $validcastext .= '[[list_variables:' . $name . ']]';
        }

        if ($this->units) {
            $validcastext .= '[[list_units:' . $name . ']]';
        }

        $compiledvalid = castext2_parser_utils::compile($validcastext);

        $this->cached = $compiledvalid;
        return $this->cached;
    }

    // Collects any rendering related statements this validation box may 
    // require to be evaluated. These should be evaluated in the same session
    // as the PRTs.
    public function get_render_statements(): array {
        if ($this->input->is_blank()) {
            // No matter what a singular input will not generate validation
            // feedback if it is blank.
            return array();
        }
        if (!$this->input->is_valid()) {
            // No need to render if already known to be invalid. Could but if we return 
            // nothing the session may actually be empty and the whole evaluation skipped.
            return array();
        }
        $validcastext = $this->cached;
        // The "dispdp" override.
        if ($this->input->get_name() !== $this->input->get_value_override()) {
            $validcastext = 'ev(' . $validcastext . ',' . $this->input->get_name() . '=' . $this->input->get_value_override() . ')';
        }

        // The return value needs to contain various details for latter tasks.
        $statement = '[' . $validcastext;
        if (count($this->input->get_variables()) === 0) {
            $statement .= ',{}';
        } else {
            $statement .= ',{stack_disp(\'' . implode(',""),stack_disp(\'', $this->input->get_variables()) . ',"")}';
        }
        if (count($this->input->get_units()) === 0) {
            $statement .= ',{}';
        } else {
            $statement .= ',{stack_disp(\'' . implode(',""),stack_disp(\'', $this->input->get_units()) . ',"")}';
        }
        $statement .= ']';

        $this->statement = new stack_secure_loader_value($statement, 'validation ' . $this->name);
        return array($this->statement);
    }

    // Renders the contents of the validation box, empty string means
    // that no content is available and no box is to be rendered.
    public function render(): string {
        if ($this->input->is_blank()) {
            // No matter what a singular input will not generate validation
            // feedback if it is blank.
            return '';
        }
        if (!$this->input->is_valid()) {
            // Render by hand...
            // We might want to apply additional protective measures here.
            $override = $this->input->get_invalid_value_override();
            $stringvalue = $this->input->get_string_value();
            $errors = $this->input->get_errors();
            $r = '';
            if ($override === null)  {
                $r = '<p>' . stateful_string('your_answer_is_considered_invalid') . ' <code>' . $stringvalue . '</code></p>';
            } else {
                $r = '<p>' . stateful_string('your_answer_is_considered_invalid') . ' ' . $override . '</p>';
            }
            if (count($this->input->get_errors()) > 0) {
                $r .= '<ul>';
                foreach ($errors as $error) {
                    $r .= '<li>' . $error . '</li>';
                }
                $r .= '</ul>';
            }
            return $r;
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

        // Second item is the set of latex representations of variables.
        $rawvariables = $value->items[1];

        // Then the units.
        $rawunits = $value->items[2];

        $errors = array($this->input->get_name() => $this->input->get_errors());
        $variables = array($this->input->get_name() => stateful_utils::mp_to_php($rawvariables));
        $units = array($this->input->get_name() => stateful_utils::mp_to_php($rawunits));

        // Build the processor.
        $processor = new stateful_validation_castext_processor(new castext2_default_processor(), $errors, $variables, $units);

        // Process.
        return castext2_parser_utils::postprocess_mp_parsed($evaluatedcastext, $processor);
    }


    public function serialize(): array {
        // Note that the auto type of vboxes are practically never 
        // intended to be serialised or saved. This bit of logic 
        // exists for completenes sake.
        $r = ['name' => $this->get_name(), 'type' => 'auto'];
        $r['list_variables'] = $this->variables;
        $r['list_units'] = $this->units;

        return $r;
    }
}


class stateful_basic_validation_box_for_checkboxes extends stateful_basic_validation_box {
    // This is a simple modication of the default message for the special case of checkboxes.
    public function get_cached(): string {
        if ($this->cached !== false) {
            return $this->cached;
        }
        
        $name = $this->input->get_name();

        $validcastext = '<p>[[commonstring key="your_answer_interpreted_as"/]]</p>';
        $validcastext .= '[[if test="stringp(' . $name . ')"]]<p style="text-align:center">{@' . $name . '@}</p>';
        $validcastext .= '[[elif test="emptyp(' . $name. ')"]]';
        $validcastext .= '<p style="text-align:center"><i>[[commonstring key="none_of_the_options_selected_are_you_sure"/]]</i></p>';
        $validcastext .= '[[else]]';        
        $validcastext .= '<p style="text-align:center">[[commonstring key="options_selected"/]]</p>';
        $validcastext .= '<ul style="margin-left:auto;margin-right:auto;">';
        $validcastext .= '[[foreach _label="' . $name . '"]]<li>{@_label@}</li>[[/foreach]]';
        $validcastext .= '</ul>';
        $validcastext .= '[[/if]]';

        if ($this->variables) {
            $validcastext .= '[[list_variables:' . $name . ']]';
        }

        if ($this->units) {
            $validcastext .= '[[list_units:' . $name . ']]';
        }

        $compiledvalid = castext2_parser_utils::compile($validcastext);

        $this->cached = $compiledvalid;
        return $this->cached;
    }
}



/**
 * Extended castext2 processor handling some additional io-blocks.
 */
class stateful_validation_castext_processor implements castext2_processor {

    private $baseprocessor;
    private $errors;
    private $variables;
    private $units;

    // The arrays are keyed by the input name, as this block handler also works with 
    // multi input addresses.
    public function __construct(castext2_processor $base, array $errors, array $variables, 
                                array $units) {
        $this->baseprocessor = $base;
        $this->errors = $errors;
        $this->variables = $variables;
        $this->units = $units;
    }

    public function process(string $blocktype, array $arguments, castext2_processor $override = null): string {


        if ($blocktype === 'ioblock') {
            if ($arguments[1] === 'list_errors') {

                $inputs = explode(',', $arguments[2]);
                $errors = array();
                foreach ($inputs as $input) {
                    $errors = array_merge($errors, $this->errors[$input]);
                }
                if (count($errors) === 0) {
                    return '';
                } 
                $r = '<p>' . stateful_string('errors_in_input') . '</p><ul>';
                foreach ($errors as $error) {
                    $r .= '<li>' . $error . '</li>';
                }
                $r .= '</ul>';
                return $r;
            }
            if ($arguments[1] === 'list_variables') {
                $inputs = explode(',', $arguments[2]);
                $variables = array();
                // $variables here is an list of latex representations.
                foreach ($inputs as $input) {
                    $variables = array_merge($variables, $this->variables[trim($input)]);
                }
                if (count($variables) === 0) {
                    return '';
                } 
                sort($variables);
                $variables = array_unique($variables);
                $r = '<p>' . stateful_string('variables_in_input') . ' \(' . implode(',\: ', $variables) . '\)</p>';
                return $r;
            }
            if ($arguments[1] === 'list_units') {
                $inputs = explode(',', $arguments[2]);
                $units = array();
                // $units here is an list of latex representations.
                foreach ($inputs as $input) {
                    $units = array_merge($units, $this->units[trim($input)]);
                }
                if (count($units) === 0) {
                    return '';
                } 
                sort($units);
                $variables = array_unique($units);
                $r = '<p>' . stateful_string('units_in_input') . ' \(' . implode(',\: ', $units) . '\)</p>';
                return $r;
            }
        }

        return $this->baseprocessor->process($blocktype, $arguments, $this);
    }

}