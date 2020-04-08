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
declare(strict_types = 1);

require_once __DIR__ . '/../../locallib.php';
require_once __DIR__ . '/../utils.class.php';
require_once __DIR__ . '/../../stacklib.php';
require_once __DIR__ . '/../answertests/answertest.factory.php';
require_once __DIR__ . '/../castext2/utils.php';
require_once __DIR__ . '/../handling/validation.php';
require_once __DIR__ . '/compiler.php';


// This is where the heavy lifting happens, this essenttially takes a question
// definition and compiles it to CAS functions that take into account variable
// definition order and shadowing.

// TODO: Once parser is part of STACK casstring push the AST there directly.

// Idea is that this will produce CAS functions at a great cost and validation
// and you store them so that you do not pay that cost again.
class stateful_function_builder {

    // Creates references to specific fields.
    static private function pc(
        $object,
        $field, $maxima=true
    ) {
        $path = stateful_handling_validation::path_creator($object, $field, '/'
            , true);
        if ($maxima) {
            return stack_utils::php_string_to_maxima_string($path);
        }
        return $path;
    }

    // Result of this is a function that you give the seed as a parameter and
    // which then sets all the question variables as they should be.
    // This function will return true if everything works and
    // will update the _ERR list if errors are triggered.
    // CALL WITH simp:true!
    static function question_variables(qtype_stateful_question $question):
    string {
        if ($question->questionvariables === null || trim($question->
            questionvariables) === '') {
            // Add unit definitions if relevant.
            if ($question->has_units()) {
                return '_question_variables(RANDOM_SEED):=(stack_randseed(RANDOM_SEED),stack_unit_si_declare(true),true)';
            }
            return '_question_variables(RANDOM_SEED):=(stack_randseed(RANDOM_SEED),true)';
        }

        list($compiled, $varref) = stateful_compiler::compile_keyval(
            $question->questionvariables, 
            self::pc($question, 'questionvariables', false),
            stateful_string('section_names_question_variables'),
            new stack_cas_security($question->has_units()));

        // Check that there are no STATE-VARIABLES here. Question variables cannot
        // depend on them nor can they be used to define them other than to initialise them
        // and that happens elsewhere.
        foreach ($question->variables as $var) {
            if (array_key_exists($var->name, $varref['read']) ||
                array_key_exists($var->name, $varref['write'])) {
                throw new stateful_exception(stateful_string(
                    'validity_premature_state_var', $var->name),
                    'validity_premature_state_var',
                    null, $var_name);
            }
        }

        // We do wish to give more specific errors.
        $r = '_question_variables(RANDOM_SEED):=(stack_randseed(RANDOM_SEED)';

        // Add unit definitions if relevant.
        if ($question->has_units()) {
            $r .= ',stack_unit_si_declare(true)';
        }

        $r .= ',' . $compiled;

        $r .= ',is(length(%ERR)=1))';

        return $r;
    }

    // Result of this is a function that evaluates the variables of a scene,
    // It will set the seed based on the given one and the path traversed to provide
    // different behaviour on re-entry.
    // This function will return true if everything works and
    // will update the _ERR list if errors are triggered.
    // CALL WITH simp:true!
    static function scene_variables(stateful_scene $scene): string{
        $fname = '_scene_' . stateful_utils::string_to_varname($scene->name) .
            '_variables';
        if ($scene->scenevariables === null || trim($scene->scenevariables) ===
            '') {
            return $fname .

         '(RANDOM_SEED):=(stack_randseed(RANDOM_SEED+5+length(SCENE_PATH)),true)';
         // Note the 5 there. If we did not have that the random seed for the first 
         // scene would be the same as for the question and that might result in 
         // surprising effects.
        }

        // QV-usage and code.
        list($qvcompiled, $varref1) = stateful_compiler::compile_keyval(
            $scene->question->questionvariables, 
            self::pc($scene->question, 'questionvariables', false),
            stateful_string('section_names_question_variables'),
            new stack_cas_security($scene->question->has_units()));

        // SV-usage and code.
        list($svcompiled, $varref2) = stateful_compiler::compile_keyval(
            $scene->scenevariables, 
            self::pc($scene, 'scenevariables', false),
            stateful_string('section_names_scene_variables', $scene->name),
            new stack_cas_security($scene->question->has_units()));


        $write = array();
        if (count($varref1['write']) > 0) {
            $write = array_intersect_key($varref1['write'], $varref2['write']);
        }

        if (count($write) > 0) {
            throw new stateful_exception(stateful_string(
                'validity_overwrite_question_var', implode(', ', array_keys(
                    $write))),
                'validity_overwrite_question_var');
        }

        foreach ($scene->question->variables as $var) {
            if (array_key_exists($var->name, $varref2['write'])) {
                throw new stateful_exception(stateful_string(
                    'validity_scene_var_state_var', $var->name),
                    'validity_scene_var_state_var',
                    null, $var->name);
            }
        }

        $r = $fname .
            '(RANDOM_SEED):=(stack_randseed(RANDOM_SEED+5+length(SCENE_PATH))';

        $r .= ',' . $svcompiled;

        $r .= ',is(length(%ERR)=1))';

        return $r;
    }


    // This includes protection logic and evaluation of feedback variables and
    // transition logic. Should be called after both question and scene variables
    // have been evaluated.
    // Note that the variable defense here is important as this might or might
    // not be called and might or might not be followed by another one and it should
    // not matter to that following one whether this was called or not.
    // Due to the defense the signature of this function is not constant and you will
    // need to extract it from the return value (explode(':=',...)[0]).
    // CALL WITH simp:false! Critical to ensure input values are not simplified.
    static function prt_logic(stateful_prt $prt): string{
        $sec = new stack_cas_security($prt->scene->question->has_units());
        list($fvcompiled, $varref) = stateful_compiler::compile_keyval(
            $prt->feedbackvariables, 
            self::pc($prt, 'feedbackvariables', false),
            stateful_string('section_names_feedback_variables', ['scene' =>
                $prt->scene->name, 'prt' => $prt->name]),
            $sec);
        
        $usage  = $prt->get_variable_usage();
        $inputs = $prt->scene->get_input_definition();

        // What inputs are needed?
        $needs = [];
        foreach ($usage['read'] as $varname => $duh) {
            if ($inputs->exists($varname)) {
                $needs[] = $varname;
                if (array_key_exists($varname, $usage['write'])) {
                    throw new stateful_exception(stateful_string(
                        'validity_immutable_input', $varname),
                        'validity_immutable_input', null,
                        $varname);
                }
            }
        }

        // What variables get written and need to be protected?
        $blocks = ['_INPUT_STRING'];
        foreach ($usage['write'] as $varname => $duh) {
            if (!array_key_exists($varname, $prt->scene->question->variables))
                                                                               {
                $blocks[] = $varname;
            }
        }
        $fname = '_scene_' . stateful_utils::string_to_varname($prt->scene->
            name) . '_prt_' . stateful_utils::string_to_varname($prt->name)
        ;
        $r = $fname . '(' . implode(',', array_merge($needs, $blocks)) .
            '):=block([_PATH,_SCORE,_PENALTY,_RESULTS,_TMP,_FEEDBACK],';

        // The feedback variables and other scope is being simplified.
        $r .= 'simp:true,';

        // First check that we have all the required inputs. the _STRING_INPUT map
        // only contains valid ones.
        // TODO: if we need more than two inputs we might want do the check using set operations.
        //       would save chars and so on.
        $tests = ['is(SCENE_NEXT=false)']; // Note that PRT evaluation stops when this gets a value.
        foreach ($needs as $need) {
            $tests[] = 'stackmap_has_key(_INPUT_STRING,' . stack_utils::
                php_string_to_maxima_string($need) . ')';
        }
        $r .= 'if not (' . implode(' and ', $tests) . ') then return([])';
        // Then initialise the core logic vars:

        $defp = '' . $prt->scene->question->penalty;
        $cs   = stack_ast_container_silent::make_from_teacher_source($defp);
        if ($cs->get_valid() !== true) {
            throw new stateful_exception('DEFAULT PENALTY...');
        }
        $defp = $cs->get_evaluationform();

        $r .=
            ',_PATH:[],_SCORE:0,_PENALTY:'. $defp . ',_RESULTS:[],_FEEDBACK:""';

        // evaluate feedback vars.
        $r .= ',' . $fvcompiled;

        // Safe break,
        $r .= ', if is(length(%ERR)>1) then return([])';
        // Then the nodes...
        $entrys = [];
        foreach ($prt->get_reverse_postorder() as $node) {
            $tests = [];
            // TODO: again set operations...
            if (!array_key_exists($node->name, $entrys)) {
                // This is the root...
                $tests[] = 'true';
            } else {
                foreach ($entrys[$node->name] as $entrypath) {
                    $tests[] = "member($entrypath,_PATH)";
                }
            }
            // update the $entrys at the same time.
            if (!array_key_exists($node->truenext, $entrys)) {
                $entrys[$node->truenext] = ['[' . stack_utils::
                        php_string_to_maxima_string($node->name) . ',true]'];
            } else {
                $entrys[$node->truenext][] = '[' . stack_utils::
                    php_string_to_maxima_string($node->name) . ',true]';
            }
            if (!array_key_exists($node->falsenext, $entrys)) {
                $entrys[$node->falsenext] = ['[' . stack_utils::
                        php_string_to_maxima_string($node->name) . ',false]'];
            } else {
                $entrys[$node->falsenext][] = '[' . stack_utils::
                    php_string_to_maxima_string($node->name) . ',false]';
            }

            // This is the guard clause, process this node only if we traveled a path that took here.
            $r .= ',if ' . implode(' or ', $tests) . ' then (';

            $nn   = self::pc($node, 'test');
            $test =
            stateful_answertest_factory::get($node->test);
            $test = $test->cascall($node->sans, $node->tans, $node->options,
                $inputs);
            $cs   = stack_ast_container_silent::make_from_teacher_source($test);
            $test = $cs->get_evaluationform();
            if ($cs->get_valid() !== true) {
                throw new stateful_exception(stateful_string(
                    'validity_cas_invalid', ['section' => stateful_string(
                        'section_names_node_test_points', ['scene' => $prt
                                ->scene->name, 'prt' =>
                            $prt->name, 'node' => $node->name]), 'position' =>
                        '', 'statement' => $test,
                        'error' => $cs->get_errors()]), 'answertest_error');
            }
            // Tests are executed simp:false to ensure input values are not simplified.
            $test = $cs->get_evaluationform();
            $r .= "simp:false,";
            $r .= "_EC(errcatch(_TMP:$test),$nn)";
            // Everything else keeps simp:true
            $r .= ",simp:true";

            // Now we have the result of the test and need to start acting on it.
            $r .= ',_RESULTS:append(_RESULTS,[_TMP])';
            $r .= ',_PATH:append(_PATH,[[' . stack_utils::
                php_string_to_maxima_string($node->name) . ',_TMP[2]]])';

            // If we are not quiet we need to include the test feedback to the feedback stream
            // lets not do that if it is empty...
            if (!$node->quiet) {
                $r .= ',if length(_TMP) > 3 and slength(_TMP[4]) > 0 then _FEEDBACK:castext_concat(_FEEDBACK,["%strans",_TMP[4]])';
            }

            $r .= ',if _TMP[2] then (';

            // True branch
            if ($prt->scoremode !== 'no score') {
                $s  = $node->truescore === '' ? '0' : $node->truescore;
                $cs = stack_ast_container_silent::make_from_teacher_source($s);
                if ($cs->get_valid() !== true) {
                    throw new stateful_exception(stateful_string(
                        'validity_cas_invalid', ['section' =>
                            stateful_string(
                                'section_names_node_test_points', ['scene' =>
                                    $prt->scene->name, 'prt' =>
                                    $prt->name, 'node' => $node->name]),
                            'position' => '', 'statement' => $node->
                            truescore, 'error' => $cs->get_errors()]),
                        'scoring_error');
                }
                $s = $cs->get_evaluationform();

                // TODO: maybe score and penalty logic could skip error handling if the values are pure numbers?
                $nn   = self::pc($node, 'truescore');
                switch ($node->truescoremode) {
                case '=':
                    $r .= "_EC(errcatch(_SCORE:{$s}),$nn)"
                    ;
                    break;
                case '+':
                    $r .= "_EC(errcatch(_SCORE:_SCORE+{$s}),$nn)"
                    ;
                    break;
                case '-':
                    $r .= "_EC(errcatch(_SCORE:_SCORE-{$s}),$nn)"
                    ;
                    break;
                }

                $p = $node->truepenalty === '' ? '' . $prt->scene->question->
                penalty : $node->truepenalty;
                if ($node->truepenaltymode !== '=' && $node->truepenalty === '') {
                    $p = '0';
                }
                $cs = stack_ast_container_silent::make_from_teacher_source($p);
                if ($cs->get_valid() !== true) {
                    throw new stateful_exception(stateful_string(
                        'validity_cas_invalid', ['section' =>
                            stateful_string(
                                'section_names_node_test_points', ['scene' =>
                                    $prt->scene->name, 'prt' =>
                                    $prt->name, 'node' => $node->name]),
                            'position' => '', 'statement' => $p,
                            'error' => $cs->get_errors()]), 'scoring_error');
                }
                $p = $cs->get_evaluationform();
                $nn   = self::pc($node, 'truepenalty');
                switch ($node->truepenaltymode) {
                case '=':
                    $r .= ",_EC(errcatch(_PENALTY:{$p}),$nn)"
                    ;
                    break;
                case '+':
                    $r .= ",_EC(errcatch(_PENALTY:_PENALTY+{$p}),$nn)"
                    ;
                    break;
                case '-':
                    $r .= ",_EC(errcatch(_PENALTY:_PENALTY-{$p}),$nn)"
                    ;
                    break;
                }
            } else {
                $r .= '"no score"'; // Must have something in the branch.
            }

            // This is the bit that differs from STACK, i.e. we may run a block of code
            // at the ends of the PRT branches that lead to state transition.
            if ($node->truenext !== null && strpos($node->truenext, '$SCENE:') === 0) {
                $r .= ',SCENE_NEXT:' . stack_utils::php_string_to_maxima_string
                    (substr($node->truenext, 7));
                // This commentted out bit happens outside the PRT logic to ensure
                // random seed logic stays sensible.
                // $r .= ',SCENE_PATH:append(SCENE_PATH,[SCENE_NEXT])';
                list($tv, $vr) = stateful_compiler::compile_keyval(
            $node->truevariables, 
            self::pc($node, 'truevariables', false),
            stateful_string('section_names_node_transition_variables',
                        ['scene' => $prt->scene->name, 'prt' => $prt->name
                            , 'node' => $node->name,
                            'branch' => 'true']),
            $sec);

                $r .= ',' . $tv;
            } else if ($node->truenext !== null && strpos($node->truenext, '$SVAR:') === 0) {
                $r .= ',SCENE_NEXT:' . substr($node->truenext, 6);
                // This commentted out bit happens outside the PRT logic to ensure
                // random seed logic stays sensible.
                // $r .= ',SCENE_PATH:append(SCENE_PATH,[SCENE_NEXT])';
                list($tv, $vr) = stateful_compiler::compile_keyval(
            $node->truevariables, 
            self::pc($node, 'truevariables', false),
            stateful_string('section_names_node_transition_variables',
                        ['scene' => $prt->scene->name, 'prt' => $prt->name
                            , 'node' => $node->name,
                            'branch' => 'true']),
            $sec);

                $r .= ',' . $tv;
            }

            // Once the variables are in we can render the feedback. Probably would
            // be better to render feedback before scene change variables get into
            // the play as there is little point in applying them to the feedback
            // and feedback is useless anyhow if we just changed scene...
            if ($node->truefeedback !== null && trim($node->truefeedback) !==
                '') {
                $code = castext2_parser_utils::compile($node->truefeedback);
                $nn   = self::pc($node, 'truefeedback');
                $r .= ",_EC(errcatch(_FEEDBACK:castext_concat(_FEEDBACK,$code)),$nn)"
                ;
            }

            $r .= ') else (';
            // False branch
            if ($prt->scoremode !== 'no score') {
                $s  = $node->falsescore === '' ? '0' : $node->falsescore;
                $cs = stack_ast_container_silent::make_from_teacher_source($s);
                if ($cs->get_valid() !== true) {
                    throw new stateful_exception(stateful_string(
                        'validity_cas_invalid', ['section' =>
                            stateful_string(
                                'section_names_node_test_points', ['scene' =>
                                    $prt->scene->name, 'prt' =>
                                    $prt->name, 'node' => $node->name]),
                            'position' => '', 'statement' => $node->
                            falsescore, 'error' => $cs->get_errors()]),
                        'scoring_error');
                }
                $s = $cs->get_evaluationform();
                $nn   = self::pc($node, 'falsescore');
                switch ($node->falsescoremode) {
                case '=':
                    $r .= "_EC(errcatch(_SCORE:{$s}),$nn)"
                    ;
                    break;
                case '+':
                    $r .= "_EC(errcatch(_SCORE:_SCORE+{$s}),$nn)"
                    ;
                    break;
                case '-':
                    $r .= "_EC(errcatch(_SCORE:_SCORE-{$s}),$nn)"
                    ;
                    break;
                }

                $nn = self::pc($node, 'falsepenalty');
                $p  = $node->falsepenalty === '' ? '' . $prt->scene->question->
                penalty : $node->falsepenalty;
                if ($node->falsepenaltymode !== '=' && $node->falsepenalty === '') {
                    $p = '0';
                }
                $cs = stack_ast_container_silent::make_from_teacher_source($p);
                if ($cs->get_valid() !== true) {
                    throw new stateful_exception(stateful_string(
                        'validity_cas_invalid', ['section' =>
                            stateful_string(
                                'section_names_node_test_points', ['scene' =>
                                    $prt->scene->name, 'prt' =>
                                    $prt->name, 'node' => $node->name]),
                            'position' => '', 'statement' => $p,
                            'error' => $cs->get_errors()]), 'scoring_error');
                }
                $p = $cs->get_evaluationform();
                switch ($node->falsepenaltymode) {
                case '=':
                    $r .= ",_EC(errcatch(_PENALTY:{$p}),$nn)"
                    ;
                    break;
                case '+':
                    $r .= ",_EC(errcatch(_PENALTY:_PENALTY+{$p}),$nn)"
                    ;
                    break;
                case '-':
                    $r .= ",_EC(errcatch(_PENALTY:_PENALTY-{$p}),$nn)"
                    ;
                    break;
                }
            } else {
                $r .= '"no score"'; // Must have something in the branch.
            }

            // This is the bit that differs from STACK, i.e. we may run a block of code
            // at the ends of the PRT branches that lead to state transition.
            if ($node->falsenext !== null && strpos($node->falsenext, '$SCENE:') === 0) {
                $r .= ',SCENE_NEXT:' . stack_utils::php_string_to_maxima_string
                    (substr($node->falsenext, 7));
                //$r .= ',SCENE_PATH:append(SCENE_PATH,[SCENE_NEXT])';
                list($tv, $vr) = stateful_compiler::compile_keyval(
            $node->falsevariables, 
            self::pc($node, 'falsevariables', false),
            stateful_string('section_names_node_transition_variables',
                        ['scene' => $prt->scene->name, 'prt' => $prt->name
                            , 'node' => $node->name,
                            'branch' => 'false']),
            $sec);

                $r .= ',' . $tv;
            } else if ($node->falsenext !== null && strpos($node->falsenext, '$SVAR:') === 0) {
                $r .= ',SCENE_NEXT:' . substr($node->falsenext, 6);
                // This commentted out bit happens outside the PRT logic to ensure
                // random seed logic stays sensible.
                // $r .= ',SCENE_PATH:append(SCENE_PATH,[SCENE_NEXT])';
                list($tv, $vr) = stateful_compiler::compile_keyval(
            $node->falsevariables, 
            self::pc($node, 'falsevariables', false),
            stateful_string('section_names_node_transition_variables',
                        ['scene' => $prt->scene->name, 'prt' => $prt->name
                            , 'node' => $node->name,
                            'branch' => 'false']),
            $sec);

                $r .= ',' . $tv;
            }

            // Once the variables are in we can render the feedback. Probably would
            // be better to render feedback before scene change variables get into
            // the play as there is little point in applying them to the feedback
            // and feedback is useless anyhow if we just changed scene...
            if ($node->falsefeedback !== null && trim($node->falsefeedback) !==
                '') {
                $code = castext2_parser_utils::compile($node->falsefeedback);
                $nn   = self::pc($node, 'falsefeedback');
                $r .= ",_EC(errcatch(_FEEDBACK:castext_concat(_FEEDBACK,$code)),$nn)"
                ;
            }

            $r .= ')'; // branch
            // Safe break,
            $r .= ', if is(length(%ERR)>1) then return([])';
            $r .= ')'; // guard
        }

        // Add the valid target scene check. Bit late in the process but should someone manage
        // to generate wrong targets the system will hang...
        $scenes = [];
        foreach ($prt->scene->question->scenes as $name => $scene) {
            $scenes[] = stack_utils::php_string_to_maxima_string($name);
        }
        $r .= ',if (not SCENE_NEXT = false) and (not member(SCENE_NEXT,[' .
        implode(',', $scenes) .
        '])) then (_APPEND_ERR(["bad scene name"],' .
        self::pc($prt, '') . '))';

        // Do some rounding. And cap the values.
        $r .= ',_SCORE:float(floor(max(min(_SCORE,1.0),0.0)*1000)/1000)';
        $r .= ',_PENALTY:float(floor(max(min(_PENALTY,1.0),0.0)*1000)/1000)';

        // Finally, output the results.
        $r .= ',return([_PATH,_RESULTS,_SCORE,_PENALTY,_FEEDBACK]))';

        return $r;
    }

    // Takes a list of statements and generates the code to evaluate them with
    // error catching. Pushesh scope information to the errors and exceptions.
    static function statements_to_evaluation_with_errs(
        array $statements,
        string $scope,
        string $sectionstring
    ): string{
        $r = '';
        foreach ($statements as $statement) {
            // TODO: redo the position data thing.
            $pos = null;
            $raw = $statement->get_evaluationform();

            if ($statement->get_valid() !== true) {
                throw new stateful_exception(stateful_string(
                    'validity_cas_invalid',
                    ['section' => $sectionstring,
                        'position' => $pos, 'statement' => $raw,
                        'error' => $cs->get_errors()]), 'validity_cas_invalid',
                    ['section' => $sectionstring,
                        'position' => $pos, 'statement' => $raw,
                        'error' => $cs->get_errors()]);
            }
            
            $r .= ",_EC(errcatch($raw),$scope)";
        }
        return $r;
    }

}
