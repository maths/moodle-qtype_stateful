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
require_once __DIR__ . '/../../../stacklib.php';

class stateful_cas_castext2_commonstring extends stateful_cas_castext2_block {

    public function compile(): ?string {
        return '["commonstring",' . stack_utils::php_string_to_maxima_string($this->params['key']) . ']';
    }

    public function is_flat(): bool {
        return false;
    }

    public function validate_extract_attributes(): array {
        return array();
    }

    public function postprocess(array $params, castext2_processor $processor): string {
        return stateful_string($params[1]);
    }
}