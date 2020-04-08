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

class stateful_prt_node implements stateful_model {

    public $id; // database id

    /* reference to the PRT, should not be needed but lets keep it around */
    public $prt;

    /* string name of the node */
    public $name;
    /* codename of a test */
    public $test;
    /* cas? expression of the teachers answer parameter */
    public $tans;
    /* cas? expression of the students answer parameter */
    public $sans;
    /* cas? expression of the test options */
    public $options;
    /* boolean flag for supressing test generated feedback */
    public $quiet;

    /* castext feedback */
    public $truefeedback;
    /* If end of branch variable tuning */
    public $truevariables;
    /* name of next node or scene reference */
    public $truenext;
    /* +, - or = */
    public $truescoremode = '+';
    /* cas expression for the value to use */
    public $truescore = '0';
    /* +, - or = */
    public $truepenaltymode = '=';
    /* cas expression for the value to use */
    public $truepenalty = '';

/* JSON stored representation of the test inputs that should lead to this exit */
    public $truetests;

    public $falsefeedback;
    public $falsevariables;
    public $falsenext;
    public $falsescoremode   = '+';
    public $falsescore       = '0';
    public $falsepenaltymode = '=';
    public $falsepenalty     = '';
    public $falsetests;

    public function __construct(
        stateful_prt $prt = null,
        $data = null
    ) {
        if ($prt === null) {
            return;
        }
        $this->prt = $prt;
        if ($data === null) {
            return;
        }
        $this->id = $data->id;

        // TODO: rename these three.
        $this->name    = $data->name;
        $this->test    = $data->test;
        $this->options = $data->options;
        $this->sans    = $data->sans;
        $this->tans    = $data->tans;
        $this->quiet   = $data->quiet;

        $this->truefeedback  = $data->truefeedback;
        $this->truevariables = $data->truevariables;
        // TODO: match names.
        $this->truenext        = $data->truenextnode;
        $this->truescoremode   = $data->truescoremode;
        $this->truescore       = $data->truescore;
        $this->truepenaltymode = $data->truepenaltymode;
        $this->truepenalty     = $data->truepenalty;
        $this->truetests       = $data->truetests;

        $this->falsefeedback    = $data->falsefeedback;
        $this->falsevariables   = $data->falsevariables;
        $this->falsenext        = $data->falsenextnode;
        $this->falsescoremode   = $data->falsescoremode;
        $this->falsescore       = $data->falsescore;
        $this->falsepenaltymode = $data->falsepenaltymode;
        $this->falsepenalty     = $data->falsepenalty;
        $this->falsetests       = $data->falsetests;

        // Sanitise TODO: fix import and other bits to not cause these.
        // Make database not allow these if possible...
        if ($this->truenext == '') {
            $this->truenext = null;
        }
        if ($this->falsenext == '') {
            $this->falsenext = null;
        }
        if ($this->options == null) {
            $this->options = '';
        }
    }
    public function get_model_type(): string {
        return 'prtnode';
    }
}
