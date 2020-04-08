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
require_once __DIR__ . '/numerical.input.php';

/**
 * This input is for inputting a single dimensional value. The input 
 * enforces
 *
 * This is loosely the same as the old STACK numerical input. but extends 
 * it with some additions.
 */
class stateful_input_units extends stateful_input_numerical {

    public function get_type(): string {
        return 'units';
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
        $this->security->set_allowedwords($this->get_option('allow-words'));
        $this->security->set_forbiddenwords($this->get_option('forbid-words'));

        

        // Map the filter options.
        $filteroptions = ['802_singleton_units' => [
            'allowvariables' => $this->get_option('allow-variables'),
            'allowconstants' => $this->get_option('allow-constants'),
            'floattopower' => $this->get_option('units-floats-to-powers'),
            'mandatoryunit' => $this->get_option('require-unit')
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
            $filters[] = '403_split_at_number_letter_boundary';
            $filters[] = '406_split_implied_variable_names';
            $filters[] = '910_inert_float_for_display'; // This is the key here.
            $this->security->set_allowedwords('dispdp,displaysci,stackunits_make');
            $this->security->set_forbiddenwords('');
            $presentation = stack_ast_container::make_from_student_source('stackunits_make(' . $this->rawvalue . ')', $this->get_name() . ' input validation', $this->security, $filters);
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
            '106_no_control_flow',
            '441_split_unknown_functions',
            '403_split_at_number_letter_boundary',
            '406_split_implied_variable_names',
            '410_single_char_vars',
            '802_singleton_units'
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
        
        // Certain options make little sense here.
        unset($base['properties']['numerical-accept-float-vs-integer']);
        unset($base['properties']['numerical-accept-power-form']);
        unset($base['properties']['numerical-convert']);
        unset($base['properties']['no-units']);

        // To control certain identifiers
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


        $base['properties']['allow-constants'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_accept_constants_label'),
            'description' => stateful_string('input_option_accept_constants_description')
        ];

        $base['properties']['allow-variables'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_accept_variables_label'),
            'description' => stateful_string('input_option_accept_variables_description')
        ];

        $base['properties']['units-floats-to-powers'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_floats_to_powers_label'),
            'description' => stateful_string('input_option_floats_to_powers_description')
        ];        

        $base['properties']['require-unit'] = [
            'default' => true,
            'type' => 'boolean',
            'title' => stateful_string('input_option_mandatory_unit_label'),
            'description' => stateful_string('input_option_mandatory_unit_description')
        ];   

        return $base;
    }

    public function get_default_values(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_default_values();
        $base['validation-box'] = 'automatic with list of units';

        // No need for these.
        unset($base['numerical-accept-float-vs-integer']);
        unset($base['numerical-accept-power-form']);
        unset($base['numerical-convert']);
        
        // Always this.
        $base['no-units'] = false;

        $base['allow-words'] = '';
        $base['forbid-words'] = '';
        $base['allow-constants'] = false;
        $base['allow-variables'] = false;
        $base['units-floats-to-powers'] = false;
        $base['require-unit'] = true;

        return $base;
    }

    public function get_layout_for_options(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_layout_for_options();

        $fieldset = ['title' => stateful_string('input_options_units'), 
                     'fields' => ['allow-constants', 'allow-variables', 'require-unit', 'allow-words', 'forbid-words', 'units-floats-to-powers', 'sf-min', 'sf-max', 'sf-strict', 'dp-min', 'dp-max']];

        // Replace the option-set of numerical inputs.
        $newfieldsetslist = [];
        foreach ($base['fieldsets'] as $fs) {
            if ($fs['title'] === stateful_string('input_options_numerical')) {
                $newfieldsetslist[] = $fieldset;
            } else {
                $newfieldsetslist[] = $fs;
            }
        }
        $base['fieldsets'] = $newfieldsetslist;

        unset($base['widgets']['numerical-convert']);
        unset($base['widgets']['numerical-accept-float-vs-integer']);

        return $base;
    }


}

