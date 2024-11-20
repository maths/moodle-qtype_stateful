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


// NOTE THIS CLASS EXISTS ONLY DUE TO NOT HAVING A SUITABLE
// COMBINATION IN STACK! AND THAT HOLDER LOGIC NEEDED A WORKAROUND
// WILL BE CLEANED AWAY.
// This was mainly because Stateful acts on more low level castext2 in many places.

class stateful_castext2_render {
	// The initial rendered bit
	public $rendered;
	// The held back bits that need to be applied after
	// Coulb be a castext2_evaluatable or a raw holder...
	public $holder;

	public function apply(string $to): string {
		if ($this->holder instanceof castext2_placeholder_holder) {
			return $this->holder->replace($to);
		} else if ($this->holder !== null){
			return $this->holder->apply_placeholder_holder($to);
		}
		return $to;
	}

	public function __construct($text, $someholder) {
		$this->rendered = $text;
		$this->holder = $someholder;
	}
}