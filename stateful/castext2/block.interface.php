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

require_once __DIR__ . '/CTP_classes.php';
require_once __DIR__ . '/../core/inputs.class.php';
require_once __DIR__ . '/processor.class.php';

abstract class stateful_cas_castext2_block {

    // In entry phase these are the params of the CTP_Node matching this block.
    // But in postprocess phase this will be NULL.
    public $params;
    // These are the blocks matching the children of the CTP block. NULL again in
    // postprocess phase.
    public $children;
    // We have no clue what this would be in postprocess phase.
    public $mathmode = false;

    public function __construct(
        $params,
        $children = [],
        $mathmode = false
    ) {
        $this->params   = $params;
        $this->children = $children;
        $this->mathmode = $mathmode;
    }

    /**
     * The compile function is suposed to generate a CAS expression that generates
     * the output of this block. Should this block not generate any output return NULL.
     * Otherwise the ouput should either create a string or a list as described elsewhere.
     */
    abstract public function compile():  ? string;

    /**
     * Should this block generate something else than direct string values it needs to
     * tell about it here.
     */
    public function is_flat() : bool {
        return true;
    }

    /**
     * If this is not a flat block this will be called with the response from CAS and
     * should execute whatever additional logic is needed. Register JavaScript and such
     * things it must then return the content that will take this blocks place.
     */
    public function postprocess(array $params, castext2_processor $processor): string {
        return '';
    }

    /**
     * Extracts whatever cas-commands this block would evaluate. Return them as
     * casstrings. Note that only this block is of interest and you do not need to
     * recurse to the children, they are handled elsewhere.
     */
    abstract public function validate_extract_attributes(): array;

    /**
     * Validates the parameters and potenttially the contents. Whatever matter to this
     * block e.g. some blocks might want all their contents to be flat-blocks.
     *
     * Currently, the only reference we give to the actual question are the input objects
     * active in this scope and the names of PRTs.
     */
    public function validate(&$errors = [], stateful_inputs $input_definitions = null, array $prts): bool {
        return true;
    }

    /**
     * Generic recurse tool for checking whether something is present in the children
     * of a block.
     */
    public function callbackRecurse($function) {
        for ($i = 0; $i < count($this->children); $i++) {
            $function($this->children[$i]);
            $this->children[$i]->callbackRecurse($function);
        }
    }
}
