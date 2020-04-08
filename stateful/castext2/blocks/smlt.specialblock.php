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


/**
 * Special block handling the post processing using 
 * stack_maxima_latex_tidy() function.
 */
class stateful_cas_castext2_special_stack_maxima_latex_tidy extends stateful_cas_castext2_block {
    public $content;

    public function __construct($params, $children=array(), $mathmode=false, $value='') {
        parent::__construct($params, $children, $mathmode);
        $this->content = $value;
    }

    public function compile(): ?string {
        // Should not even happen. This is not a block that makes sense for 
        // end users.
        return '["smlt",' . stack_utils::php_string_to_maxima_string($this->content) . ']';
    }

    public function is_flat(): bool {
        return false;
    }

    public function postprocess(array $params, castext2_processor $processor): string {
        if (count($params) < 2) {
            // Nothing at all.
            return '';
        }

        $t = $params[1];
        
        return stack_maxima_latex_tidy($t);
    }

    public function validate_extract_attributes(): array {
        return array();
    }
}