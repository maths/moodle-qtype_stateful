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
require_once __DIR__ . '/../../stacklib.php';


// Special AST-container variant for carrying state variables.
// Also works for everything else not requiring that the response is 
// parsed by the session.
class stateful_state_carrier extends stack_ast_container_silent implements cas_raw_value_extractor {

    private $evaluatedvalue = null;


    public function set_cas_evaluated_value(string $value) {
        $this->evaluatedvalue = $value;
    }

    public function get_evaluated_state(): string {
        return $this->evaluatedvalue;
    }

}