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

class stack_test_NumDecPlacesWrong implements stack_answertest {

    public function codename(): string {
        return 'NumDecPlacesWrong';
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

'NumDecPlacesWrong requires options to tell how many most significant decimal-places need to match wherever they are.'
            ;
        } else {
            $cs = stack_ast_container::make_from_teacher_source($options);
            if (!$cs->get_valid()) {
                $err['options'] = $cs->get_errors('t');
            }
        }

        if ($sans === null || trim($sans) === '') {
            $err['sans'] = 'NumDecPlacesWrong needs a students answer.';
        } else {
            $cs = stack_ast_container::make_from_teacher_source($sans);
            if (!$cs->get_valid()) {
                $err['sans'] = $cs->get_errors('t');
            }
        }
        if ($tans === null || trim($tans) === '') {
            $err['tans'] = 'NumDecPlacesWrong needs a teachers answer.';
        } else {
            $cs = stack_ast_container::make_from_teacher_source($tans);
            if (!$cs->get_valid()) {
                $err['tans'] = $cs->get_errors();
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
        return "ATDecimalPlacesWrong($sans,$tans,ev($options,simp))";
    }

    public function option_meta(): array{
        return ['acceptable_types' => ['integer' => [
            'description' =>
            'Number of most-significant decimal-places required to be correct.',
            'constraint' => 'gte(1)']],
            'general_description' =>

'Options for this test describe the number of most signifincat decimals that are being matched.'

        ];
    }

    public function requires_tans(): bool {
        return true;
    }

    public function requires_direct_input_ref(): bool {
        return false;
    }
}