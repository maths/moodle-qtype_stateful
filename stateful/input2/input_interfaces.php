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
 * The new input system expects inputs to behave according to some key rules, 
 * but allows some input to do much less than others. When creating an input try 
 * to only implement those bits that you absolutely require.
 *
 * Note that for certain bits there are options on how things can be done and in 
 * those cases just implement the one that better suits use case.
 *
 * In general the functions listed in this file are listed in order of execution.
 * However, various features declared through these interfaces do interleave.
 *
 * Note that the bits that dela with cached statements need to follow the general
 * rule that cached statements do not affect the global scope and set no values. 
 * If they return a value it is just the value of the statement itself.
 */

require_once __DIR__ . '/../../stacklib.php';

/**
 * The base interface over which all others build.
 */
interface stateful_input {
    // The name of the input e.g. `ans1`
    public function get_name(): string;

    // The type of the input e.g. `button`
    public function get_type(): string;

    // Takes in the student response array and generates whatever is needed 
    // to validate it on the CAS side. Returns a list of cas_evaluatables.
    public function get_validation_statements(array $response, stack_cas_security $rules): array;

    // Tells if the input has a valid value. Only called after whatever
    // get_validation_statements() returned have been evaluated.
    public function is_valid(): bool;

    // Tells if the input is blank, if all inputs are blank then nothing happens.
    public function is_blank(): bool;

    // Tells if the input is both valid and validated. `_val` and so on.
    public function is_valid_and_validated_or_blank(): bool;

    // Summarises for step-logs.
    public function summarise(): string;

    // Renders the input controls using a given prefix, this will be called
    // early and the prefix may not be known at that point, if so assume that
    // it is a placeholder and will be replaced later.
    public function render_controls(array $values, string $prefix, bool $readonly = false): string;

    // Generates any script declarations this input requires. Note that one 
    // must not call the $PAGE->js_... functions directly as we need to collect
    // the calls for relay to various APIs. In this case the prefix is known.
    // Return an array with the names of the js_... functions as keys and arrays 
    // of argument lists as the values.
    // In theory scripts could also enforce readonly behaviour.
    public function render_scripts(string $prefix, bool $readonly = false): array;

    // Tell the VLE the data to expect.
    public function get_expected_data(): array;

    // Serialise the options of this input, all of them.
    public function serialize(bool $prunedefaults): array;

    // Value to response array, turns a CAS-evaluated value to a response
    // that can be used as an input value to input configured like this one.
    public function value_to_response(MP_Node $value): array;
}

/**
 * What separates notes from actual inputs.
 */
interface stateful_input_cas_value_generating extends stateful_input {
    // Returns a cas_evaluatable that declares that value within the CAS.
    // e.g. `ans1:2/4+y`. Note that the value is expected to be based on 
    // the response-data that was delivered through get_validation_statements().
    public function get_value(): cas_evaluatable;
    
    // Same but represents the raw input value for use in sigfigs testing 
    // and similar uses.
    public function get_string_value(): string;
}

/**
 * Should a teachers answer have any meaning the input processes it.
 */ 
interface stateful_input_teachers_answer_handling extends stateful_input {

    // Gives the input the teachers answer, this happens before initialisation.
    // The received values is not evaluated if you need it to be evaluated add it 
    // to the initialisation session.
    public function set_teachers_answer(string $answer): void;

    // Returns a verbal input guidance on how to input that answer.
    public function get_correct_input_guide(): string;

    // Returns the answer as represented as a response array, for testing purposes.
    public function get_correct_response(): array;

    // Returns a map of errors about the options. Key being the option with 
    // trouble and the value a list of strings describing that trouble. In 
    // the case of teachers answer use the key `tans`.
    public function validate_options(): array;
}

/**
 * Some inputs have additional options, which are declared as a JSON object.
 */
interface stateful_input_options extends stateful_input {
    // Gives a JSON schema for expected options.
    public function get_schema_for_options(): array;

    // Gives a suggested layout for expected options. Basically, 
    // the schema is enough to define the editor but one might want to
    // group things in some way.
    public function get_layout_for_options(): array;

    // Gives the options to the input, this will happen before initialisation.
    public function set_options(array $options): void;

    // Returns a map of errors about the options. Key being the option with 
    // trouble and the value a list of strings describing that trouble.
    public function validate_options(): array;
}

/**
 * These two are for two diffrent ways of initialisation, 
 * if your initialisation does not depend on the teachers answer or you can
 * handle packing and unpacking of the caching logic use the caching version, 
 * if you need to parse the teachers answer on PHP side to decide on what to 
 * send you are doing things the wrong way and have to use the other version.
 *
 * Not all inputs need initialisation at all. But if they do they do it 
 * before validation.
 */
interface stateful_input_caching_initialisation extends stateful_input {
    // The initialisation command is a string that will be evaluated 
    // in CAS and the response will be the value of that string.
    // If a cached command is in store this will not get called.
    public function get_initialisation_commands(): string;

    // The Maxima-parser parsed return value of that command.
    public function set_initialisation_value(MP_Node $value): void;
}

// Note that this interface by design conflicts with the previous.
interface stateful_input_non_caching_initialisation extends stateful_input {
    // In the non caching situation the commands are given as 
    // a list cas_evaluatables, and the reuturn values are not given
    // as the refrences to those cas_evaluatables will handle that.
    public function get_initialisation_commands(): array;
}

/**
 * These represent the boxes displaying the input validation information.
 * Inputs may define those pretty freely.
 */
interface stateful_input_validation_box {
    // The name of the vbox e.g. `ans1` or `combo1`.
    public function get_name(): string;

    // The type of the vbox e.g. `auto`
    public function get_type(): string;

    // This gives the box access to an input object it is being paired with.
    // Note that a given box may be paired with multiple inputs.
    // Expect that no two inputs have the same name and if one with the same
    // name is given replace the old.
    public function register_input(stateful_input $input): void;

    // As validation always mixes cacheable with stuff that cannot be cached
    // we provide a generic interface for it to store e.g. castext2-statements.
    // The controller will call the first if it has something in the cache,
    public function set_cached(string $value): void;
    // The latter will be called if nothing is in the cache.
    public function get_cached(): string;

    // Collects any rendering related statements this validation box may 
    // require to be evaluated. These should be evaluated in the same session
    // as the PRTs.
    public function get_render_statements(): array;

    // Renders the contents of the validation box, empty string means
    // that no content is available and no box is to be rendered.
    public function render(): string;

    // Everything worth knowing about this vbox should be here.
    public function serialize(): array;
}

/**
 * Most inputs provide validation, and to do that they need to declare
 * where that validation gets piped to and provide that target infromation
 * about the validity.
 */
interface stateful_input_validation_source extends stateful_input_cas_value_generating {
    // The input needs to provide a validation_box. As some boxes may
    // connect to multiple inputs the input must check if a box of same
    // name would already be in the map of existing ones and return it 
    // from there if it is there.
    public function get_validation_box(array $existing): ?stateful_input_validation_box;

    // A list of functions in the input to be displayed in the validation box.
    // These are called after the statements of get_validation_statements() 
    // have been evaluated. And some of these are sent to CAS for rendering.
    public function get_functions(): array;

    // A list of variables in the input to be displayed in the validation box.
    // This does not need to know if `i` or `e` etc. are actually variables,
    // a simple list of directly referenced identifiers is enough.
    // The logic higher up separates the true variables from bound values.
    public function get_variables(): array;

    // A list of units in the input to be displayed in the validation box.
    public function get_units(): array;

    // A list of errors to be displayed in the validation box.
    // Called after the statements of get_validation_statements() have been
    // evaluated.
    public function get_errors(): array;

    // Returns a value to store in a `_val` field. Part of the two step 
    // validation process.
    public function get_val_field_value(): string;

    // Overrides the direct value of input in representation.
    // For example, numerical input may enforce the presentation of 
    // decimal places to match the inputted, or a MCQ input may
    // provide the label instead of the value.
    // If not overriding should just return the get_name()
    // which is what the validation uses to reference the value loaded 
    // to the session.
    public function get_value_override(): string;

    // Overrides the display value of an invalid value in representation.
    // Primarily meant for matrix-inputs so that they may control how
    // a partially invalid input gets displayed.
    // Returns null if the normal iner string of the raw input works.
    // Otherwise a string with whatever layout is expected.
    public function get_invalid_value_override(): ?string;
}
