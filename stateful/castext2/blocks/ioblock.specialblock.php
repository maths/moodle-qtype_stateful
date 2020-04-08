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
defined('MOODLE_INTERNAL') || die();


require_once __DIR__ . '/../block.interface.php';


class stateful_cas_castext2_special_ioblock extends stateful_cas_castext2_block {

    public $channel;
    public $variable;

    public function __construct($params, $children=array(), $mathmode=false, $channel='', $variable='') {
        parent::__construct($params, $children, $mathmode);
        $this->channel = $channel;
        $this->variable = $variable;
    }

    public function compile(): ?string {
        return '["ioblock","' . $this->channel . '","' . $this->variable . '"]';
    }

    public function is_flat(): bool {
        return false;
    }

    public function validate_extract_attributes(): array {
        return array();
    }

    // Might seem odd to postprocess this but this is a hook that others connect to.
    public function postprocess(array $params, castext2_processor $processor): string {
        return '[[' . $params[1] . ':' . $params[2] . ']]';
    }

}