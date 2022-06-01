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
require_once __DIR__ . '/../../stacklib.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/json.php';
require_once __DIR__ . '/../../../../behaviour/stateful/behaviour.php';
require_once __DIR__ . '/../core/state_carrier.php';


// Tools for dealing with test-cases
class stateful_handling_testing {

    // Takes a scene and an array that has the values for all state variables 
    // and the seed. Outputs an array that lists all test-cases present in the scene
    // and the inputs for those that are active in this state.
    public static function generate_test_inputs(stateful_scene $scene, array $state): array {
        $seed = $state['RANDOM_SEED'];

        // Setup the session.
        $statements = array();
        foreach ($scene->question->get_state_variable_identifiers() as $varname) {
            if (isset($state[$varname])) {
                $statements[] = stateful_state_carrier::make_from_teacher_source($varname . ':' . $state[$varname], 'state');
            }
        }
        $statements[] = new stack_secure_loader('RANDOM_SEED:' . $seed, 'seed');
        $statements[] = $scene->question->get_compiled('qv');
        $statements[] = new stack_secure_loader('_question_variables(RANDOM_SEED)', 'load question');

        $sv = $scene->question->get_compiled('scene-' . $scene->name . '-variables');
        $statements[] = $sv;
        $statements[] = new stack_secure_loader(explode(':=', $sv->
                get_evaluationform())[0], 'load scene');

        // We will dump the result out as JSON, the state_carrier is ideal for that.
        $output = stateful_state_carrier::make_from_teacher_source('_tests:[]', 'output definition');
        $statements[] = $output;
        $tests = 0;
        $expects = array();
        // Some values are to be taken as raw. To simplify input of tests.
        $raws = array();
        // Collect tests.
        foreach ($scene->prts as $prt) {
            // Note that we do care about tests, but only tests that matter i.e. those in nodes connected to root through some path.
            foreach ($prt->get_reverse_postorder() as $node) {
                if (($node->truetests === null) || ($node->truetests === '[]') || ($node->truetests === '')) {
                    $node->truetests = array();
                } else if (is_string($node->truetests)) {
                    $node->truetests = json_decode($node->truetests, true);
                }
                if (($node->falsetests === null) || ($node->falsetests === '[]') || ($node->falsetests === '')) {
                    $node->falsetests = array();
                } else if (is_string($node->falsetests)) {
                    $node->falsetests = json_decode($node->falsetests, true);
                }
                // There could be tests in the structure tied to things that no longer lead to exit and we ignore them.
                if (($node->truenext === null) || (strpos($node->truenext, '$SCENE:') === 0) || (strpos($node->truenext, '$SVAR:') === 0)) {
                    foreach ($node->truetests as $testcase) {
                        $tests++;
                        $val = '["stack_map",["prt",' . stack_utils::php_string_to_maxima_string($prt->name) . '],';
                        $val .= '["test",' . stack_utils::php_string_to_maxima_string($testcase['name']) . '],';
                        $expects[$tests] = array($node->name, true);
                        $val .= '["inputs",if ev(is(' . $testcase['condition']. '),simp) then ["stack_map"';
                        foreach ($scene->inputs as $inputname => $input) {
                            if (isset($testcase['inputs'][$inputname]) && trim($testcase['inputs'][$inputname]) !== '') {
                                if (strpos($testcase['inputs'][$inputname], 'RAW:') === 0) {
                                    if (!isset($raws[$tests])) {
                                        $raws[$tests] = [];
                                    }
                                    $raws[$tests][$inputname] = substr($testcase['inputs'][$inputname], 4);
                                } else {
                                    $val .= ',[' . stack_utils::php_string_to_maxima_string($inputname) . ',(__tmp:' . $testcase['inputs'][$inputname] . ',stack_dispvalue(__tmp))]';
                                }
                            }
                        }
                        $val .= '] else "inactive"]]';
                        $statements[] = new stack_secure_loader('simp:false','build-test-case with simp:false');
                        $statements[] = stack_ast_container_silent::make_from_teacher_source('_tests:append(_tests,[' . $val . '])', 'build test-case');
                    }
                }
                if (($node->falsenext === null) || (strpos($node->falsenext, '$SCENE:') === 0) || (strpos($node->falsenext, '$SVAR:') === 0)) {
                    foreach ($node->falsetests as $testcase) {
                        $tests++;
                        $val = '["stack_map",["prt",' . stack_utils::php_string_to_maxima_string($prt->name) . '],';
                        $val .= '["test",' . stack_utils::php_string_to_maxima_string($testcase['name']) . '],';
                        $expects[$tests] = array($node->name, false);
                        $val .= '["inputs",if ev(is(' . $testcase['condition']. '),simp) then ["stack_map"';
                        foreach ($scene->inputs as $inputname => $input) {
                            if (isset($testcase['inputs'][$inputname]) && trim($testcase['inputs'][$inputname]) !== '') {
                                if (strpos($testcase['inputs'][$inputname], 'RAW:') === 0) {
                                    if (!isset($raws[$tests])) {
                                        $raws[$tests] = [];
                                    }
                                    $raws[$tests][$inputname] = substr($testcase['inputs'][$inputname], 4);
                                } else {
                                    $val .= ',[' . stack_utils::php_string_to_maxima_string($inputname) . ',(__tmp:' . $testcase['inputs'][$inputname] . ',stack_dispvalue(__tmp))]';
                                }
                            }
                        }
                        $val .= '] else "inactive"]]';
                        $statements[] = new stack_secure_loader('simp:false','build-test-case with simp:false');
                        $statements[] = stack_ast_container_silent::make_from_teacher_source('_tests:append(_tests,[' . $val . '])', 'build test-case');
                    }
                }
            }
        }
        // Convert the result to JSON-string.
        $statements[] = new stack_secure_loader('_tests:stackjson_stringify(_tests)', 'JSON-conversion');
        if ($tests === 0) {
            return array();
        }
        // Validate.
        foreach ($statements as $stmt) {
            if (!$stmt->get_valid()) {
                throw new stateful_exception(stateful_string('test_case_initialisation_error'));
            }
        }

        // Instantiate.
        $session = new stack_cas_session2($statements, $scene->question->options, $seed);
        $session->errclass = 'stateful_cas_error';
        $session->instantiate();

        // Extract the value.
        $data = $output->get_evaluated_state(); // Maxima-string.
        $data = stack_utils::maxima_string_to_php_string($data);
        $data = json_decode($data, true);

        // Plugin the expectations.
        $tests = 0;
        foreach ($data as $key => $value) {
            $tests++;
            $data[$key]['expected'] = $expects[$tests];
            if (isset($data[$key]['inputs']) && isset($raws[$tests])) {
                foreach ($raws[$tests] as $input => $raw) {
                    // Replace the raw inputs.
                    $data[$key]['inputs'][$input] = $raw;
                }
            }
        }
        return $data;
    }

    // Executes the tests of the question in the given state. If state has no seed
    // will use a static one and if state does not have all the necessary variables
    // will start from blank.
    // The return value will list the results of all the tests and the state after 
    // if the tests caused state changes.
    public static function test($question, array $state): array {
        // Ensure we have a seed.
        $seed = 1;
        if (isset($state['RANDOM_SEED'])) {
            $seed = $state['RANDOM_SEED'];
            $seed = intval($seed);
        } 
        $state['RANDOM_SEED'] = $seed;

        // Ensure we have a full state.
        $fullstate = true;
        foreach ($question->get_state_variable_identifiers() as $varname) {
            if (!isset($state[$varname])) {
                $fullstate = false;
                break;
            }
        }
        $fakestate = new qbehaviour_stateful_state_storage(null, $question->get_state_variable_identifiers());
        $fakestep = new question_attempt_step();
        $fakestep->set_qt_var('_seed', $seed);
        if (!$fullstate) {
            $question->set_state($fakestate);
            // We need to akip using predefined variants.
            $hold = $question->variants;
            $question->variants = '';
            // This would map from variant to seed so we
            // took away the maps.
            $question->start_attempt($fakestep, $seed);
            $question->variants = $hold;
            $state = $question->get_state_array();
        }

        // Init the correct scene. Prepares the teachers answers and inputs.
        $varset = ''; // We also reset to these later.
        foreach ($question->get_state_variable_identifiers() as $vnum => $varname) {
            $varset .= $varname . ':' . $state[$varname] . ',';
            $fakestate->set($vnum, $state[$varname]);
        }
        $question->set_state($fakestate);
        $question->apply_attempt_state($fakestep);

        // Get the inputs we are to test.
        $scene = $question->get_current_scene();
        $tests = self::generate_test_inputs($scene, $state);

        // Validate input values for each case using that cases specific objects.
        // Use those specific ones to inject to tests.
        if (count($tests) === 0) {
            return array('origin' => $state, 'results' => array());
        }

        // For each test case we need a separate input-controller to handle its
        // input validation, otherwise we need to execute every test in a 
        // separate session.
        $ics = [];
        foreach ($tests as $key => $value) {
            // As input-controllers need to be initialised this could get expensive quickly.
            // Luckily, one can clone an initialised ic and we initialised one when we 
            // applied our state.
            $ics[$key] = $question->inputs->clone();
        }

        // Setup the session for validation of inputs..
        $statements = array();
        foreach ($question->get_state_variable_identifiers() as $varname) {
            if (isset($state[$varname])) {
                $statements[] = stateful_state_carrier::make_from_teacher_source($varname . ':' . $state[$varname], 'state');
            }
        }
        $statements[] = new stack_secure_loader('RANDOM_SEED:' . $seed, 'seed');
        $statements[] = $question->get_compiled('qv');
        $statements[] = new stack_secure_loader('_question_variables(RANDOM_SEED)', 'load question');

        $sv = $question->get_compiled('scene-' . $scene->name . '-variables');
        $statements[] = $sv;
        $statements[] = new stack_secure_loader(explode(':=', $sv->get_evaluationform())[0], 'load scene');

        $cs = count($statements);

        foreach ($tests as $tc => $testcase) {
            if (!is_array($testcase['inputs'])) {
                // Inactive case no need for evaluation.
                continue;
            }

            $response = array();
            foreach ($testcase['inputs'] as $key => $value) {
                if ($value !== '') {
                    $ast = maxima_parser_utils::parse($value);

                    $response += $scene->inputs[$key]->value_to_response($ast);
                }
            }
            $statements = array_merge($statements, $ics[$tc]->collect_validation_statements($response, $question->security));
        }
        if ($cs < count($statements)) {
            // The input validation requires session evaluation.
            $session = new stack_cas_session2($statements, $question->options, $seed);
            $session->errclass = 'stateful_cas_error';
            $session->instantiate();
        }

        // Now then lets build the real session.
        $statements = [];
        foreach ($question->get_state_variable_identifiers() as $varname) {
            if (isset($state[$varname])) {
                $statements[] = stateful_state_carrier::make_from_teacher_source($varname . ':' . $state[$varname], 'state');
            }
        }
        $statements[] = new stack_secure_loader('RANDOM_SEED:' . $seed, 'seed');
        $statements[] = $question->get_compiled('qv');
        $statements[] = new stack_secure_loader('_question_variables(RANDOM_SEED)', 'load question');

        $sv = $question->get_compiled('scene-' . $scene->name . '-variables');
        $statements[] = $sv;
        $statements[] = new stack_secure_loader(explode(':=', $sv->get_evaluationform())[0], 'load scene');     
        // We need to include the logic for every PRT. Including the ones not tested.
        // Also collect their signatures at the same time.
        // Note that in real use we only load and call those that are relevant to valid inputs.
        $prtsigs = array();
        foreach ($scene->prts as $prt) {
            $prtlogic = $question->get_compiled('scene-' . $scene->name
                    . '-prt-' . $prt->name);
            $statements[] = $prtlogic;
            $prtsigs[$prt->name] = explode(':=', $prtlogic->get_evaluationform())[0];
        }

        // Construct some common code.
        // We will dump the result out as JSON, the state_carrier is ideal for that.
        $output = stateful_state_carrier::make_from_teacher_source('_TESTS:[]', 'output definition');
        $statements[] = $output;
        $testpreamble = 'block(local(_INPUT_STRING,_TEST_PRT,_TEST_CASE,SCENE_NEXT';
        foreach ($question->get_state_variable_identifiers() as $varname) {
            $testpreamble .= ',' . $varname;
        }
        // We regenerate the scene variables, not because they might have changed
        // but because the RANDOM_STATE may have and we want to keep it stable.
        $testpreamble .= '),SCENE_NEXT:false,' . $varset . explode(':=', $sv->get_evaluationform())[0];

        $testpost = ',if is(SCENE_NEXT#false) then (SCENE_PATH:append(SCENE_PATH,[SCENE_CURRENT]),SCENE_CURRENT:SCENE_NEXT,_TEST_CASE:stackmap_set(_TEST_CASE,"nextstate",["stack_map"';
        $testpost .= ',["RANDOM_SEED","'. $seed.'"]';
        foreach ($question->get_state_variable_identifiers() as $varname) {
            $testpost .= ',[' . stack_utils::php_string_to_maxima_string($varname) . ',string(' . $varname . ')]';
        }
        $testpost .= ']))';

        $testpost .= ',_TESTS:append(_TESTS,[_TEST_CASE]))';

        $somethingtoeval = false;
        foreach ($tests as $tc => $testcase) {

            // Check if the input vas valid.
            if ($testcase['inputs'] === 'inactive' || !$ics[$tc]->all_valid_and_validated_or_blank()) {
                continue;
            }
            $somethingtoeval = true;

            $statement = $testpreamble;
            $statement .= ',_TEST_CASE:["stack_map",["prt",'.stack_utils::php_string_to_maxima_string($testcase['prt']).'],["test",'.stack_utils::php_string_to_maxima_string($testcase['test']).']]';

            // Alway simp:false before bringing in input-values.
            $statement .= ',simp:false';
            foreach ($ics[$tc]->collect_cas_values() as $value) {
                $statement .= ',' . $value->get_evaluationform();
            }

            foreach ($prtsigs as $key => $value) {
                // Collect the result only from the target PRT.
                // Remember to always reset simp for PRT-calls.
                if ($key === $testcase['prt']) {
                    $statement .= ',simp:false,_TEST_PRT:' . $value;
                } else {
                    $statement .= ',simp:false,' . $value;
                }
            }

            $statement .= ',if is(_TEST_PRT=[]) then _TEST_CASE:stackmap_set(_TEST_CASE,"status","failed (not evaluated)") else if is(last(_TEST_PRT[1])=['.stack_utils::php_string_to_maxima_string($testcase['expected'][0]).','.($testcase['expected'][1]?'true':'false').']) then _TEST_CASE:stackmap_set(_TEST_CASE,"status","success") else (if is(length(sublist(map(first,_TEST_PRT[2]),is))=length(_TEST_PRT[2])) then _TEST_CASE:stackmap_set(_TEST_CASE,"status","failed (other exit)") else _TEST_CASE:stackmap_set(_TEST_CASE,"status","failed (invalid test)"))';
            
            $statement .= $testpost;
            $statements[] = new stack_secure_loader($statement, 'test case');
        }
        // Convert the result to JSON-string.
        $statements[] = new stack_secure_loader('_TESTS:stackjson_stringify(_TESTS)', 'JSON-conversion');


        $thedata = [];
        if ($somethingtoeval) {
            // Now execute and then collect the data.
            $session = new stack_cas_session2($statements, $question->options, $seed);
            $session->errclass = 'stateful_cas_error';
            $session->instantiate();

            // Merge this to the tests.
            // Extract the value.
            $thedata = $output->get_evaluated_state(); // Maxima-string.
            $thedata = stack_utils::maxima_string_to_php_string($thedata);
            $thedata = json_decode($thedata, true);
        }
        $result = array('origin' => $state, 'results' => array());
        foreach ($tests as $tc => $testcase) {
            if (!is_array($testcase['inputs'])) {
                unset($testcase['inputs']);
                $testcase['status'] = 'inactive';
                $result['results'][] = $testcase;
            } else {
                foreach ($thedata as $data) {
                    if (($data['prt'] === $testcase['prt']) && ($data['test'] === $testcase['test'])) {
                        $testcase['status'] = $data['status'];
                        if (isset($data['nextstate'])) {
                            $testcase['nextstate'] = $data['nextstate'];
                        }
                    }
                }
                if (!$ics[$tc]->all_valid_and_validated_or_blank()) {
                    $testcase['status'] = 'failed (invalid input)';
                }
                $result['results'][] = $testcase;
            }
        }

        return $result;
    }
}