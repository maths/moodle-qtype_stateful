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
require_once __DIR__ . '/stacklib.php';

class stateful_exception extends moodle_exception {
    // Some exceptiosn have subtypes that we do not bother to create classes for.
    public $type = 'generic';

    // We often provide information about where in the question things broke.
    public $position = null;

    // Some subtypes have parameters.
    public $parameter = null;

    public function __construct($error, $type='generic', $position=null, $parameter=null) {
        parent::__construct('exceptionmessage', 'qtype_stateful', '', $error);
        $this->type = $type;
        $this->position = $position;
        $this->parameter = $parameter;
    }
}

// Steal this too we want to provide the same abilities in our output.
function stateful_string($key, $a = null) {
    return stack_maths::process_lang_string(get_string($key, 'qtype_stateful', $a));
}
