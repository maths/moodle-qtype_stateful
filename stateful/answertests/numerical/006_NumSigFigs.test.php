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
require_once __DIR__ . '/../answertest.interface.php';
require_once __DIR__ . '/../../../stacklib.php';

class stack_test_NumSigFigs implements stack_answertest {

    public function codename(): string {
        return 'NumSigFigs';
    }

    public function validate(
        string $sans,
        string $tans,
        string $options,
        stateful_inputs $input_definitions
    ): array{
        $err = [];

        if ($options === null || trim($options) === '') {
            $err['options'] =

'NumSigFigs requires options to tell how many significant figures are needed and possibly how many are acceptable.'
            ;
        } else {
            $cs = stack_ast_container::make_from_teacher_source($options);
            if (!$cs->get_valid()) {
                $err['options'] = $cs->get_errors();
            }
        }

        if ($sans === null || trim($sans) === '') {
            $err['sans'] = 'NumSigFigs needs a students answer.';
        } else {
            $cs = stack_ast_container::make_from_teacher_source($sans);
            if (!$cs->get_valid()) {
                $err['sans'] = $cs->get_errors();
            }
        }
        if ($tans === null || trim($tans) === '') {
            $err['tans'] = 'NumSigFigs needs a teachers answer.';
        } else {
            $cs = stack_ast_container::make_from_teacher_source($tans);
            if (!$cs->get_valid()) {
                $err['tans'] = $cs->get_errors();
            }
        }

        if (!$input_definitions->exists(trim($sans))) {
            $err['sans'] =

'NumSigFigs needs the students answer to be a raw input value for inspection of significant digits.'
            ;
        } else {
            $input = $input_definitions->get(trim($sans));
            if ($input === null || (!($input->get_type() === 'algebraic') && !(
                $input->get_type() === 'numerical'))) {
                $err['sans'] =

'NumSigFigs answertest needs the students answer to come from an Algebraic or preferably from a Numerical type input.'
                ;
            }
        }

        return $err;
    }

    public function cascall(
        string $sans,
        string $tans,
        string $options,
        stateful_inputs $input_definitions
    ): string {
        // Note that we have an injection hole here if this gets generated for invalid options.
        // TODO. Construct that sans string better.
        return "ATNumSigFigs($sans,$tans,ev($options,simp)
                          ,stackmap_get(_INPUT_STRING,\"" . trim($sans) . '"))'
        ;
    }

    public function option_meta(): array{
        return ['acceptable_types' => ['integer' => [
            'description' =>
            'Number of significant digits required to be present and correct.',
            'constraint' => 'gte(1)'],
            'list' => [
                'description' =>

'List of two elements where the first one describes how many significant digits are required and the second describes how many of them must be correct. Should the second one be 0 then we do not actually check the value only the presentation and if the second one is -1 then we require that all significant digits are correct no matter how many are required. Typical use has [n,n-1] which allows small rounding errors to be present in the last digit.'
                ,
                'constraint' => [
                    'list' => 'length(2)',
                    'elements' => [
                        '0' => ['gte(1)', 'integer'],
                        '1' => ['gte(-1)', 'integer']
                    ]]
            ]],
            'general_description' =>

'Options for this test describe the required number of significant digits in the first numeric term in the response. You may also define the required number of accurate digits.'
        ];
    }

    public function requires_tans(): bool {
        return true;
    }

    public function requires_direct_input_ref(): bool {
        return true;
    }
}