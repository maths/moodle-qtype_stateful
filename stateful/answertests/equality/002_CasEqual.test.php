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

class stack_test_CasEqual implements stack_answertest {

    public function codename(): string {
        return 'CasEqual';
    }

    public function validate(
        string $sans,
        string $tans,
        string $options,
        stateful_inputs $input_definitions
    ): array{
        $err = [];

        if ($options !== null && trim($options) !== '') {
            $err['options'] = 'CasEqual has no options so do not give any';
        }
        if ($sans === null || trim($sans) === '') {
            $err['sans'] = 'CasEqual needs a students answer.';
        } else {
            $cs = stack_ast_container_silent::make_from_teacher_source($sans, 'at:CasEqual:sans', new stack_cas_security());
            if (!$cs->get_valid()) {
                $err['sans'] = $cs->get_errors();
            }
        }
        if ($tans === null || trim($tans) === '') {
            $err['tans'] = 'CasEqual needs a teachers answer.';
        } else {
            $cs = stack_ast_container_silent::make_from_teacher_source($tans, 'at:CasEqual:tans', new stack_cas_security());;
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
        return "ATCasEqual($sans,$tans)";
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