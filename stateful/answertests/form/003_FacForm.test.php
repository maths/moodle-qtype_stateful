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

class stack_test_FacForm implements stack_answertest {

    public function codename(): string {
        return 'FacForm';
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

            'FacForm requires option to define the variable in the expression.';
        } else {
            $cs = stack_ast_container::make_from_teacher_source($options);
            if (!$cs->get_valid()) {
                $err['options'] = $cs->get_errors();
            }
        }
        if ($sans === null || trim($sans) === '') {
            $err['sans'] = 'FacForm needs a students answer.';
        } else {
            $cs = stack_ast_container::make_from_teacher_source($sans);
            if (!$cs->get_valid()) {
                $err['sans'] = $cs->get_errors();
            }
        }
        if ($tans === null || trim($tans) === '') {
            $err['tans'] = 'FacForm needs a teachers answer.';
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
        return "ATFacForm($sans,$tans,$options)";
    }

    public function option_meta(): array{
        return ['acceptable_types' => ['variable' => [
            'description' => 'The variable.',
            'constraint' => ['unbound()', 'atom()']]],
            'general_description' =>
            'This test requires the variable used for factoring.'
        ];
    }

    public function requires_tans(): bool {
        return true;
    }

    public function requires_direct_input_ref(): bool {
        return false;
    }

}