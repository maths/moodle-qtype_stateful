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
require_once __DIR__ . '/model.class.php';

class stateful_statevariable implements stateful_model {

    // Non functional parts.
    public $name;
    public $description;
    public $type; // Used for non run-time validation
    public $question;

    // Logic
    public $initialvalue;

    // Tech background.
    public $id; // database id
    public $number; // The variables have a specific storage number so that the storage system
    // can store arbitrarily long keys with less syntax constraints, the number is not
    // the DB-identifier as that might change during backup and restore.

    public function __construct(
        $question = null,
        $data = null
    ) {
        if ($question === null) {
            return;
        }
        $this->question = $question;

        if ($data === null) {
            return;
        }

        $this->id           = $data->id;
        $this->name         = $data->name;
        $this->description  = $data->description;
        $this->type         = $data->type;
        $this->initialvalue = $data->initialvalue;
        $this->number       = 0 + $data->number;
    }

    public function get_model_type(): string {
        return 'variable';
    }
}