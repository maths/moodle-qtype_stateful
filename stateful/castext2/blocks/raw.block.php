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

class stateful_cas_castext2_raw extends stateful_cas_castext2_block {

    public $content;

    public function __construct($params, $children=array(), $mathmode=false) {
        parent::__construct($params, $children, $mathmode);
        $this->content = $children[0]->content; // The child is a different type of RAW.
        $this->children = array(); // We want to modify the iteration here a bit.
    }

    public function compile(): ?string {
        // Convert possible simplification flags.
        $ev = stack_ast_container::make_from_teacher_source($this->content);
        $ev = $ev->get_evaluationform();

        // If the author enforces simplification on the content we need 
        // to not simplify when we reuse that content.
        // Also we only evaluate it only once.

        // So we need to know if there is simplification in play
        // from the given expression.

        $forcesimp = mb_strpos($ev, ',simp=true') !== false;
        $disablesimp = mb_strpos($ev, ',simp=false') !== false;

        $r = 'string(' . $ev . ')';
        if ($forcesimp) {
            // We need the temp to hold the value while we return simp to what it was.
            $r = 'block([_ct2_tmp,_ct2_simp],_ct2_simp:simp,simp:true,_ct2_tmp:' . $ev . ',_ct2_tmp:string(_ct2_tmp),simp:_ct2_simp,_ct2_tmp)';
        } else if ($disablesimp) {
            $r = 'block([_ct2_tmp,_ct2_simp],_ct2_simp:simp,simp:false,_ct2_tmp:' . $ev . ',_ct2_tmp:string(_ct2_tmp),simp:_ct2_simp,_ct2_tmp)';
        } else {
            $r = 'block([_ct2_tmp],_ct2_tmp:' . $ev . ',string(_ct2_tmp))';
        }
        return $r;
    }

    public function is_flat(): bool {
        return true;
    }

    public function validate_extract_attributes(): array {
        return array(stack_ast_container::make_from_teacher_source($this->content, 'ct2:raw', new stack_cas_security()));
    }
}