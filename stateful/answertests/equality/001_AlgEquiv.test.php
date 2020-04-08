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

class stack_test_AlgEquiv implements stack_answertest {

    public function codename(): string {
        return 'AlgEquiv';
    }

    public function validate(
        string $sans,
        string $tans,
        string $options,
        stateful_inputs $input_definitions
    ): array{
        $err = [];

        if ($options !== null && trim($options) !== '') {
            $err['options'] = 'AlgEquiv has no options so do not give any';
        }
        if ($sans === null || trim($sans) === '') {
            $err['sans'] = 'AlgEquiv needs a students answer.';
        } else {
            $cs = stack_ast_container_silent::make_from_teacher_source($sans, 'at:AlgEquiv:sans', new stack_cas_security());
            if (!$cs->get_valid()) {
                $err['sans'] = $cs->get_errors();
            }
        }
        if ($tans === null || trim($tans) === '') {
            $err['tans'] = 'AlgEquiv needs a teachers answer.';
        } else {
            $cs = stack_ast_container_silent::make_from_teacher_source($tans, 'at:AlgEquiv:tans', new stack_cas_security());;
            if (!$cs->get_valid()) {
                $err['tans'] = $cs->get_errors();
            }
        }


        return $err;
    }

    /* Maps the parameters to a CAS code block that generates the correct response list.
     * e.g. "ATAlgEquiv($sans,$tans)"
     */
    public function cascall(
        string $sans,
        string $tans,
        string $options,
        stateful_inputs $input_definitions
    ): string {
        return "ATAlgEquiv($sans,$tans)";
    }

    public function option_meta(): array{
        return [];
    }

    public function requires_tans(): bool {
        return true;
    }

    public function requires_direct_input_ref(): bool {
        return false;
    }

}