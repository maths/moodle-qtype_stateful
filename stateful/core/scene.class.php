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
require_once __DIR__ . '/inputs.class.php';
require_once __DIR__ . '/prt.class.php';
require_once __DIR__ . '/../input2/input.controller.php';
require_once __DIR__ . '/../input2/input_interfaces.php';
require_once __DIR__ . '/model.class.php';

class stateful_scene implements stateful_model {

    public $id; // database id

    // Non functional parts.
    public $name;
    public $description;
    public $question;

    // Logic
    public $scenevariables;
    public $prts; // This is an array with the prt name as the key but the order is still the declaration order.
    public $inputs; // Same here. 
    public $vboxes;

    // Presentation
    public $scenetext;


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
        if (isset($data->id)) {
            $this->id             = $data->id;
        }
        $this->name           = $data->name;
        $this->description    = $data->description;
        $this->scenevariables = $data->scenevariables;
        $this->scenetext      = $data->scenetext;

        // Again name and declaration order trickery.
        $tmp          = [];
        foreach ($data->inputs as $id => $inputdata) {
            $opts = [];
            if ($inputdata->options !== '' && is_string($inputdata->options)) {
                $opts = json_decode($inputdata->options, true);
            } else if (is_array($inputdata->options)) {
                $opts = [] + $inputdata->options;
            }

            $input = stateful_input_controller::get_input_instance($inputdata->type, $inputdata->name);
            $tmp[] = $input;

            if ($input instanceof stateful_input_teachers_answer_handling) {
                $input->set_teachers_answer($inputdata->tans);
            }
            if ($input instanceof stateful_input_options) {
                $input->set_options($opts);
            }
        }
        $this->vboxes = [];
        foreach ($data->vboxes as $id => $vboxdata) {
            $opts = [];
            if ($vboxdata->options !== '' && is_string($vboxdata->options)) {
                $opts = json_decode($vboxdata->options, true);
            }

            $vbox = stateful_input_controller::get_validation_box_instance($vboxdata->type, $vboxdata->name, $opts);
            $this->vboxes[$vbox->get_name()] = $vbox;
        }

        ksort($tmp);
        $this->inputs = [];
        foreach ($tmp as $input) {
            $this->inputs[$input->get_name()] = $input;
        }
        $tmp = [];
        foreach ($data->prts as $id => $prtdata) {
            $tmp[$id] = new stateful_prt($this, $prtdata);
        }
        ksort($tmp);
        $this->prts = [];
        foreach ($tmp as $prt) {
            $this->prts[$prt->name] = $prt;
        }

    }

    // Special wrapper class so that we can pass the input defintion to certain functions
    // Without giving access to anything bad.
    public function get_input_definition(): stateful_inputs {
        // We could probably store this object for reuse but why bother.
        return new stateful_inputs($this->inputs);
    }

    public function get_model_type(): string {
        return 'scene';
    }
}