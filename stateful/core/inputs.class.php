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

require_once __DIR__ . '/../input2/input_interfaces.php';

// Essenttially this class is just a typed array holding relevant input objects
// so that we can pass a typed reference to them. It does however have a more
// nefarious purpose, it intentionally hides the question/scene object so that
// we can give just the information we want to e.g. answertest builders.
class stateful_inputs {
    private $inputs = array();

    public function __construct($inputs) {
        $this->inputs = array();
        foreach ($inputs as $name => $input) {
            $this->inputs[$name] = $input;
        }
    }

    public function get(string $name): ?stateful_input {
        if (array_key_exists($name, $this->inputs)) {
            return $this->inputs[$name];
        } else {
            return null;
        }
    }

    public function exists(string $name): bool {
        return array_key_exists($name, $this->inputs);
    }

    public function get_names(): array {
        return array_keys($this->inputs);
    }
}
