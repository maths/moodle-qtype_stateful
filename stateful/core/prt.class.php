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
require_once __DIR__ . '/prt_node.class.php';
require_once __DIR__ . '/../../stacklib.php';
require_once __DIR__ . '/../answertests/answertest.factory.php';
require_once __DIR__ . '/../castext2/utils.php';
require_once __DIR__ . '/model.class.php';

class stateful_prt implements stateful_model {

    public $id; // database id

    /* reference to the scene, should not be needed but lets keep it around */
    public $scene;

    /* string name of the prt used for feedback direction */
    public $name;

    public $feedbackvariables;
    public $scoremode;
    public $scoremodeparameters;
    public $value;

    public $root; // node object.
    public $nodes; // Keyed by name.

    public function __construct(
        stateful_scene $scene = null,
        $data = null
    ) {
        if ($scene === null) {
            return;
        }

        $this->scene = $scene;
        if ($data === null) {
            return;
        }
        if (isset($data->id)) {
            $this->id              = $data->id;
        }
        $this->name                = $data->name;
        $this->feedbackvariables   = $data->feedbackvariables;
        $this->scoremode           = $data->scoremode;
        $this->scoremodeparameters = $data->scoremodeparameters;
        $this->value               = $data->value;

        // the order does not matter here, so no need for the same trickery as elsewhere.
        $this->nodes = [];
        foreach ($data->nodes as $nodedata) {
            $this->nodes[$nodedata->name] = new stateful_prt_node($this,
                $nodedata);
        }
        if (!array_key_exists($data->firstnodename, $this->nodes)) {
            error_log(var_export($data, true));
            throw new stateful_exception(stateful_string(
                'instantiation_prt_bad_root_ref', $this->name));
        }
        $this->root = $this->nodes[$data->firstnodename];
    }

    public function get_node($name):  ? stateful_prt_node {
        if ($name === null) {
            return null;
        }
        if (array_key_exists($name, $this->nodes)) {
            return $this->nodes[$name];
        }
        return null;
    }

    public function get_reverse_postorder() : array{
        // i.e. list the nodes in the order they are last visited to allow simple
        // guard clauses... nice feature of acyclic graphs... drops the orphans too.
        $order   = [];
        $visited = [];
        $this->po_recurse($this->root, $order, $visited);
        return array_reverse($order);
    }

    private function po_recurse(
        stateful_prt_node $node,
        array &$postorder,
        array &$visited
    ): array{
        $truenode             = $this->get_node($node->truenext);
        $falsenode            = $this->get_node($node->falsenext);
        $visited[$node->name] = $node;
        if ($truenode != null && !array_key_exists($truenode->name, $visited))
                                                                               {
            $this->po_recurse($truenode, $postorder, $visited);
        }
        if ($falsenode != null && !array_key_exists($falsenode->name, $visited)
        ) {
            $this->po_recurse($falsenode, $postorder, $visited);
        }

        $postorder[] = $node;
        return $postorder;
    }

    public function get_variable_usage(): array{
        // Get both written and read variables from the logic of the tree, feedback
        // is not part of logic.
        $r = ['write' => [], 'read' => []];

        $r = maxima_parser_utils::variable_usage_finder(maxima_parser_utils::
                parse($this->feedbackvariables), $r);

        $inputs   = $this->scene->get_input_definition();
        $combined = '';
        foreach ($this->get_reverse_postorder() as $node) {
            $testclass = stateful_answertest_factory::get($node->test);
            $combined .= $testclass->cascall($node->sans, $node->tans, $node->
                options, $inputs) . ';';
            if ($node->truescore != null && trim($node->truescore) !== '') {
                $combined .= $node->truescore . ';';
            }
            if ($node->truepenalty != null && trim($node->truepenalty) !== '')
                                                                               {
                $combined .= $node->truepenalty . ';';
            }
            if ($node->falsescore != null && trim($node->falsescore) !== '') {
                $combined .= $node->falsescore . ';';
            }
            if ($node->falsepenalty != null && trim($node->falsepenalty) !== ''
            ) {
                $combined .= $node->falsepenalty . ';';
            }
            if ($node->truenext !== null && strpos($node->truenext, '$SCENE:')
                === 0) {
                $r = maxima_parser_utils::variable_usage_finder(
                    maxima_parser_utils::parse($node->truevariables), $r);
            }
            if ($node->falsenext !== null && strpos($node->falsenext, '$SCENE:'
            ) === 0) {
                $r = maxima_parser_utils::variable_usage_finder(
                    maxima_parser_utils::parse($node->falsevariables), $r);
            }
            // TODO: the variable target case...
        }

        $r = maxima_parser_utils::variable_usage_finder(maxima_parser_utils::
                parse($combined), $r);
        return $r;
    }

    public function get_model_type(): string {
        return 'prt';
    }
}
