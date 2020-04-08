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

// Tools for turning qtype_stateful_question objects into
// the questiondata form and back.
// https://docs.moodle.org/dev/Question_data_structures
//
// questiondata is the form closest to the database form in
// in it we deal with stdClass-objects. This largely simple 
// as the classes representing the parts of Stateful-questions
// already use this format as the initialisation value.

require_once __DIR__ . '/../../question.php';
require_once __DIR__ . '/../../questiontype.php';
require_once __DIR__ . '/../../stacklib.php';
require_once __DIR__ . '/../core/scene.class.php';
require_once __DIR__ . '/../core/statevariable.class.php';
require_once __DIR__ . '/../core/prt.class.php';
require_once __DIR__ . '/../core/prt_node.class.php';
require_once __DIR__ . '/../input2/input_interfaces.php';

class stateful_handling_moodle_questiondata {

    // One can define a target base that already has something populated
    // this will only add or overwrite to it. Will overwite scenes and 
    // variables completely, will not overwrite other meta.
    public static function to(qtype_stateful_question $from, stdClass $target = null): stdClass {
        $t = $target;
        if ($target === null) {
            $t = new stdClass();
        }
        $t->qtype = 'stateful';
        $t->questiontextformat = FORMAT_HTML;
        $t->generalfeedbackformat = FORMAT_HTML;

        foreach (['id', 'category', 'contextid', 'parent', 'name', 
                  'questiontext', 'generalfeedback', 'defaultmark',
                  'length', 'penalty', 'stamp', 'version', 'hidden',
                  'idnumber', 'timecreated', 'timemodified', 
                  'createdby', 'modifiedby'] as $key) {
            if (property_exists($from, $key)) {
                $t->$key = $from->$key;
            }
        }

        if (!property_exists($t, 'options')) {
            $t->options = new stdClass();
            $t->parlength = -1;
        }
        // The top level options
        foreach (['questionvariables', 'questionnote', 'entryscene',
                  'stackversion', 'statefulversion', 'compiledcache',
                  'genericmeta', 'parlength', 'variants'
                 ] as $key) {
            if (property_exists($from, $key)) {
                if (is_array($from->$key)) {
                    // Meta, cache and variants
                    $t->options->$key = json_encode($from->$key);
                } else {
                    $t->options->$key = $from->$key;
                }
            }   
        }
        // The options in stack_options.
        $t->options->multiplicationsign = $from->options->get_option('multiplicationsign');
        $t->options->complexno = $from->options->get_option('complexno');
        $t->options->inversetrig = $from->options->get_option('inversetrig');
        $t->options->matrixparens = $from->options->get_option('matrixparens');
        $t->options->sqrtsign = $from->options->get_option('sqrtsign');
        $t->options->questionsimplify = $from->options->get_option('simplify');
        $t->options->assumepositive = $from->options->get_option('assumepos');
        $t->options->assumereal = $from->options->get_option('assumereal');

        // Variables.
        $t->variables = [];
        foreach ($from->variables as $var) {
            $t->variables[] = self::to_variable($var);
        }

        // Scenes.
        $t->scenes = [];
        foreach ($from->scenes as $scene) {
            $t->scenes[] = self::to_scene($scene);
        }

        return $t;
    }

    public static function to_variable(stateful_statevariable $var): stdClass {
        $v = new stdClass();
        if (isset($var->question->id)) {
            $v->questionid   = $var->question->id;
        }
        $v->name         = $var->name;
        $v->type         = $var->type;
        $v->number       = $var->number;
        $v->description  = $var->description;
        $v->initialvalue = $var->initialvalue;
        if (isset($var->id)) {
            $v->id = $var->id;
        }

        return $v;
    }

    public static function to_scene(stateful_scene $scene): stdClass {
        $s = new stdClass();
        if (isset($scene->question->id)) {
            $s->questionid   = $scene->question->id;
        }
        $s->questionid     = $scene->question->id;
        $s->name           = $scene->name;
        $s->scenevariables = $scene->scenevariables;
        $s->scenetext      = $scene->scenetext;
        $s->description    = $scene->description;
        if (isset($scene->id)) {
            $s->id = $scene->id;
        }

        // Inputs
        $s->inputs = [];
        foreach ($scene->inputs as $input) {
            $s->inputs[] = self::to_input($input, $scene);
        }

        // VBoxes
        $s->vboxes = [];
        foreach ($scene->vboxes as $vbox) {
            $s->vboxes[] = self::to_vbox($vbox, $scene);
        }
        
        // PRTs
        $s->prts = [];
        foreach ($scene->prts as $prt) {
            $s->prts[] = self::to_prt($prt);
        }
        
        return $s;
    }

    public static function to_input(stateful_input $input, stateful_scene $scene): stdClass {
        $i = new stdClass();
        if (isset($scene->id)) {
            $i->sceneid   = $scene->id;
        }
        $i->name         = $input->get_name();
        $i->type         = $input->get_type();
        $i->tans         = '-';
        $i->options      = $input->serialize(false);
        unset($i->options['name']);
        unset($i->options['type']);
        if (isset($i->options['tans'])) {
            $i->tans = $i->options['tans'];
            unset($i->options['tans']);
        }
        $i->options = json_encode($i->options);

        return $i;
    }

    public static function to_vbox(stateful_input_validation_box $vbox, stateful_scene $scene): stdClass {
        $vb = new stdClass();
        if (isset($scene->id)) {
            $vb->sceneid   = $scene->id;
        }
        $vb->name         = $vbox->get_name();
        $vb->type         = $vbox->get_type();
        $vb->options      = $vbox->serialize(false);
        unset($vb->options['name']);
        unset($vb->options['type']);
        $vb->options = json_encode($vb->options);

        return $vb;
    }

    public static function to_prt(stateful_prt $prt): stdClass {
        $p = new stdClass();
        if (isset($prt->scene->id)) {
            $p->sceneid   = $prt->scene->id;
        }
        if (isset($prt->id)) {
            $p->id = $prt->id;
        }
        $p->name                = $prt->name;
        $p->value               = $prt->value;
        $p->feedbackvariables   = $prt->feedbackvariables;
        $p->firstnodename       = $prt->root->name;
        $p->scoremode           = $prt->scoremode;
        $p->scoremodeparameters = $prt->scoremodeparameters;

        $p->nodes = [];
        foreach ($prt->nodes as $node) {
            $p->nodes[] = self::to_prt_node($node);
        }

        return $p;
    }

    public static function to_prt_node(stateful_prt_node $node): stdClass {
        $n = new stdClass();
        if (isset($node->prt->id)) {
            $n->prtid   = $node->prt->id;
        }
        if (isset($node->id)) {
            $n->id = $node->id;
        }

        foreach (['name', 'test', 'options', 'sans', 'tans', 'quiet',
                  'truefeedback', 'truevariables', 'truescoremode',
                  'truescore', 'truepenaltymode', 'truepenalty',
                  'truetests',
                  'falsefeedback', 'falsevariables', 'falsescoremode',
                  'falsescore', 'falsepenaltymode', 'falsepenalty',
                  'falsetests',
                 ] as $key) {
            if (property_exists($node, $key)) {
                if (is_array($node->$key)) {
                    // Tests may have been unpacked.
                    $n->$key = json_encode($node->$key);
                } else {
                    $n->$key = $node->$key;
                }
            }   
        }
        $n->truenextnode = $node->truenext;
        $n->falsenextnode = $node->falsenext;

        return $n;
    }

    public static function from(stdClass $from): qtype_stateful_question {
        $q = new qtype_stateful_question();

        foreach (['id', 'category', 'contextid', 'parent', 'name', 
                  'questiontext', 'generalfeedback', 'defaultmark', 
                  'length', 'penalty', 'stamp', 'version', 'hidden', 
                  'idnumber', 'timecreated', 'timemodified', 'createdby', 
                  'modifiedby'] as $key) {
            if (property_exists($from, $key)) {
                $q->$key = $from->$key;
            }
        }

        $q->qtype = new qtype_stateful();

        // We will never have other formats.
        $q->questiontextformat = FORMAT_HTML;
        $q->generalfeedbackformat = FORMAT_HTML;
        
        // Some random typing.
        if (property_exists($from, 'defaultmark')) {
            $q->defaultmark = $from->defaultmark + 0;
        }

        // Some defaults.
        $q->parlength = -1;


        if (property_exists($from, 'options')) {
            foreach (['questionvariables', 'questionnote', 'entryscene', 
                      'stackversion', 'statefulversion', 'compiledcache', 
                      'genericmeta', 'parlength', 'variants'] as $key) {
                if (property_exists($from->options, $key)) {
                    $q->$key = $from->options->$key;
                }
            }
        }

        $q->options = new stack_options();
        if (isset($from->options->multiplicationsign)) {
            $q->options->set_option('multiplicationsign', $from->options->multiplicationsign);
        }
        if (isset($from->options->complexno)) {
            $q->options->set_option('complexno', $from->options->complexno);
        }
        if (isset($from->options->inversetrig)) {
            $q->options->set_option('inversetrig', $from->options->inversetrig);
        }
        if (isset($from->options->matrixparens)) {
            $q->options->set_option('matrixparens', $from->options->matrixparens);
        }
        if (isset($from->options->sqrtsign)) {
            $q->options->set_option('sqrtsign', (bool) $from->options->sqrtsign);
        }
        if (isset($from->options->questionsimplify)) {
            $q->options->set_option('simplify', (bool) $from->options->questionsimplify);
        }
        if (isset($from->options->assumepositive)) {
            $q->options->set_option('assumepos', (bool) $from->options->assumepositive);
        }
        if (isset($from->options->assumereal)) {
            $q->options->set_option('assumereal', (bool) $from->options->assumereal);
        }

        $q->variables = [];
        $q->scenes    = [];

        if (property_exists($from, 'variables')) {
            foreach ($from->variables as $dbid => $vardata) {
                $sv = new stateful_statevariable(
                    $q, $vardata);
                $q->variables[$sv->name] = $sv;
            }
        }
        if (property_exists($from, 'scenes')) {
            foreach ($from->scenes as $dbid => $scenedata) {
                $s = new stateful_scene($q,
                    $scenedata);
                $q->scenes[$s->name] = $s;
            }
        }

        return $q;
    }

}

