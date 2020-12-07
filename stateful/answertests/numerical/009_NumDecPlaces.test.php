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

class stack_test_NumDecPlaces implements stack_answertest {

    public function codename(): string {
        return 'NumDecPlaces';
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

'NumDecPlaces requires options to tell how many decimal-places are needed.'
            ;
        } else {
            $cs = stack_ast_container::make_from_teacher_source($options);
            if (!$cs->get_valid()) {
                $err['options'] = $cs->get_errors('t');
            }
        }

        if ($sans === null || trim($sans) === '') {
            $err['sans'] = 'NumDecPlaces needs a students answer.';
        } else {
            $cs = stack_ast_container::make_from_teacher_source($sans);
            if (!$cs->get_valid()) {
                $err['sans'] = $cs->get_errors('t');
            }
        }
        if ($tans === null || trim($tans) === '') {
            $err['tans'] = 'NumDecPlaces needs a teachers answer.';
        } else {
            $cs = stack_ast_container::make_from_teacher_source($tans);
            if (!$cs->get_valid()) {
                $err['tans'] = $cs->get_errors();
            }
        }

        if (!$input_definitions->exists(trim($sans))) {
            $err['sans'] =

'NumDecPlaces needs the students answer to be a raw input value for inspection of decimal-places.'
            ;
        } else {
            $input = $input_definitions->get(trim($sans));
            if ($input === null || (!($input->get_type() === 'algebraic') && !(
                $input->get_type() ===
                'numerical'))) {
                $err['sans'] =

'NumDecPlaces answertest needs the students answer to come from an Algebraic or preferably from a Numerical type input.'
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
        return "ATNumDecPlaces($sans,$tans,ev($options,simp)
                         ,stackmap_get(_INPUT_STRING,\"" . trim($sans) . '"))'
        ;
    }

    public function option_meta(): array{
        return ['acceptable_types' => ['integer' => [
            'description' =>
            'Number of decimal-places required to be present.',
            'constraint' => 'gte(1)']],
            'general_description' =>

'Options for this test describe the required number of decimal-places in the first numeric term in the response.'

        ];
    }

    public function requires_tans(): bool {
        return true;
    }

    public function requires_direct_input_ref(): bool {
        return true;
    }
}