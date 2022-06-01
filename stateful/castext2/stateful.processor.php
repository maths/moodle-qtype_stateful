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

require_once(__DIR__ . '/../../stacklib.php');

/**
 * This class exists to allow us to plug in a short form name for
 * the Stateful version of the commonstring-block, it also 
 * carries $qa with it.
 */
class stateful_castext2_default_processor extends castext2_default_processor {
	public $qa;

    public function __construct($qa) {
        $this->qa = $qa;
    }

    public function process(string $blocktype, array $arguments, castext2_processor $override = null): string {
        if ($blocktype === '%css') { // An alias for shorter content.
	        $proc = $this;
	        if ($override !== null) {
	            $proc = $override;
	        }
            $block = new stateful_cas_castext2_commonstring([]);
        	return $block->postprocess($arguments, $proc);
        } else {
            return parent::process($blocktype, $arguments, $override);
        }
    }
}