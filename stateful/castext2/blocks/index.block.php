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
require_once __DIR__ . '/../block.factory.php';

/**
 * This block is part of a pair of blocks the `indexing` and `index` blocks
 * only work with each other. The latter requires to be inside the former and
 * the former only does something if it has some `index`-blocks inside it.
 *
 * In practice the `indexing` block defines the presentation and initial offset
 * of a sequence of numbers to be injected to `index` blocks within it. It may
 * also define a name for itself so that there may exist more than one 
 * individual sequences within it. Typically, that name is just the default
 * blank-string and the `index`-blocks refer to their default blank-string
 * which means the closest parent `indexing-block`.
 */
class stateful_cas_castext2_index extends stateful_cas_castext2_block {
	private static $count = 0;

    public function compile():  ? string{
    	$r = '["index"';

    	if (isset($this->params['name'])) {
    		$r .= ',' . stack_utils::php_string_to_maxima_string($this->params['name']);
    	}

    	return $r . ']';
	}

    public function is_flat() : bool {
        return false;
    }

    public function postprocess(array $params, castext2_processor $processor): string {
    	// There is practically nothing to do as the logic happens client side.
    	$attributes = [
    		'class' => 'stack_ct2_index',
    		/* Note that we want to ensure that the identifiers are unique on 
    		   the page. Even if AJAX brings more identifiers to the page. */
    		'data-c' => substr($_SERVER['REQUEST_TIME_FLOAT'], -7) . self::$count
    	];
    	self::$count++;
    	if (count($params) > 1) {
    		$attributes['name'] = stack_utils::maxima_string_to_php_string($params[1]);
    	}
    	return html_writer::tag('span', '&nbsp;', $attributes);
    }

    public function validate(&$errors = [], stateful_inputs $input_definitions = null, array $prts): bool {
    	$ok = true;

    	foreach ($this->params as $key => $value) {
    		switch ($key) {
    			case 'name':
    				break;
    			default:
    				$ok = false;
    				$errors[] = stack_string('stackBlock_index_unknown_parameter', ['key' => $key]);
    				break;
    		}
    	}

        return $ok;
    }

    public function validate_extract_attributes(): array {
        return [];
    }
}
