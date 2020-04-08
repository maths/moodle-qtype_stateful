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
define('AJAX_SCRIPT', true);

/**
 * This is the API for editors that the Stateful question type provides.
 * It might make sense to port this to other platforms as the real editors
 * are supposed to be built for this and not for direct Moodle communication.
 *
 * Note that this assumes the use of the JSON representation of Stateful
 * questions. But will not actually output full presentation in the case
 * of questions with attachments, i.e. only references will be outputted
 * not the contents of those attachments, new attachments are obviously
 * inputted in the full form.
 **/

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../behaviour/stateful/behaviour.php';
require_once __DIR__ . '/../../engine/lib.php';
require_once $CFG->libdir . '/questionlib.php';
require_once __DIR__ . '/../../previewlib.php';
require_once __DIR__ . '/stacklib.php';
require_once __DIR__ . '/stateful/answertests/answertest.factory.php';
require_once __DIR__ . '/stateful/handling/json.php';
require_once __DIR__ . '/stateful/handling/moodle.php';
require_once __DIR__ . '/stateful/handling/validation.php';
require_once __DIR__ . '/stateful/handling/testing.php';
require_once __DIR__ . '/stateful/castext2/utils.php';
require_once __DIR__ . '/renderer.php';


require_once __DIR__ . '/question.php';

require_login();

$apimode = 'info';

if (isset($_GET['mode'])) {
    $apimode = trim($_GET['mode']);
}

switch ($apimode) {
    case 'debug':
        $qid = $_GET['questionid'];
        $question = question_bank::load_question($qid);
        question_require_capability_on($question, 'edit');
        header('Content-Type: application/json');
        echo $question->compiledcache;
        break;
    case 'load':
    case 'loadfull':
        $qid = $_GET['questionid'];

        $format = 'JSON';
        if (isset($_GET['format']) && $_GET['format']) {
            $format = 'YAML';
        }

        $question = question_bank::load_question($qid);

        question_require_capability_on($question, 'edit');


        if ($question instanceof qtype_stateful_question) {
            header('Content-Type: application/json');
            echo stateful_handling_json::to_json($question, $apimode === 'loadfull'
            );
        } else {
            http_response_code(404);
            echo 'Tried loading question that is not of the Stateful type';
        }

        // Receives identity of a question to be loaded. Returns version with
        // or without attachement contents. Question ID-paramters as GET as
        // this is pretty REST.
        break;

    case 'savenew':
    case 'save':
    case 'validate':
        // Receives a JSON presentation of a question, in the case of
        // saving must have a top level attribute of the previous identifier
        // to save over and when creating a new an identifier for the target
        // category to save into. Target context identifiers are provided to
        // editors during initialisation.

        // In the case of savenew the response will contain the identifier
        // for the new question. Otherwise all will contain a top level flag
        // about validity and error messages for each field of the document
        // that has trouble. There is a generral erro field as well for
        // trouble spanning multiple fields.

        $data             = json_decode(file_get_contents('php://input'), true);
        $question         = stateful_handling_json::from_array($data);
        $validationresult = stateful_handling_validation::validate($question);

        if ($validationresult['result'] && ($apimode === 'save' || $apimode ===
            'savenew')) {
            $question = stateful_handling_moodle::save($question, $apimode ===
                'save');
            header('Content-Type: application/json');
            echo stateful_handling_json::to_json($question, false);
        } else {
            header('Content-Type: application/json');
            echo json_encode($validationresult);
        }

        break;

    case 'qnote':
        // Simplified render of the question note, just receives
        // the question-variables and question-note as well as some description
        // of the seeds to generate and will then render the notes for those
        // seeds.
        $data = json_decode(file_get_contents('php://input'), true);
        $qv = '';
        if (isset($data['question-variables'])) {
            $qv = $data['question-variables'];
        }
        $qn = '';
        if (isset($data['question-note'])) {
            $qn = $data['question-note'];
        }

        $qv = new stack_cas_keyval($qv);
        // Construct a qv-function by hand.
        $qvf = '_qv(RANDOM_SEED):=(stack_randseed(RANDOM_SEED)';
        foreach ($qv->get_session()->get_session() as $stmt) {
            if (!$stmt->get_valid()) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid question-variables.']);
            }
            $qvf .= ',_EC(errcatch(' . $stmt->get_evaluationform() . '),"question-variables")';
        }
        $qvf .= ')';

        // Similar function for the note.
        $qnf = '_qn(RANDOM_SEED):=';
        $qnf .= castext2_parser_utils::compile($qn);

        $stmts = [];
        $stmts[] = new stack_secure_loader($qvf, 'question-variables');
        $stmts[] = stack_ast_container_silent::make_from_teacher_source($qnf, 'question-note');

        // Identify seeds to generate.
        $seeds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11,
                  12, 13, 14, 15, 16, 17, 18, 19, 20];
        if (isset($data['seeds'])) {
            $seeds = $data['seeds'];
        }

        $seds = '%_SEEDS:[';
        $first = true;
        foreach (array_unique($seeds) as $seed) {
            if (is_integer($seed)) {
                if ($first) {
                    $first = false;
                } else {
                    $seds .= ',';
                }
                $seds .= $seed;
            }
        }

        $seds .= ']';
        $stmts[] = stack_ast_container_silent::make_from_teacher_source($seds, 'seeds');
        
        // Add logic to execute those.
        $stmts[] = stack_ast_container_silent::make_from_teacher_source('%_RESULT:[]', 'result-store');
        $stmts[] = stack_ast_container_silent::make_from_teacher_source('%_SET:{}', 'distinct-results');
        $stmts[] = stack_ast_container_silent::make_from_teacher_source('for %_SEED in %_SEEDS do (_qv(%_SEED),%_TMP:_qn(%_SEED),if not elementp(%_TMP,%_SET) then (%_SET:union(%_SET,{%_TMP}),%_RESULT:append(%_RESULT,[[%_SEED,%_TMP]])))', 'execution');
        $result = new stack_secure_loader_value('%_RESULT', 'results');
        $stmts[] = $result;

        foreach ($stmts as $stmt) {
            $stmt->get_valid();
        }

        // New session.
        $ses = new stack_cas_session2($stmts);
        $ses->instantiate();

        // Extract results and render them.
        $response = ['results' => []];
        $result = $result->get_value();
        if ($result instanceof MP_Root) {
            $result = $result->items[0];
        }
        $result = stateful_utils::mp_to_php($result);
        foreach ($result as $item) {
            if (is_string($item[1])) {
                $response['results'][$item[0]] = $item[1];
            } else {
                $response['results'][$item[0]] = castext2_parser_utils::postprocess_parsed($item[1]);
            }
        }
        header('Content-Type: application/json');
        echo json_encode($response);

        break;
    case 'just-schema':
        // Dumps out jsut the question schema.
        header('Content-Type: application/json');
        echo json_encode(stateful_handling_json::schema());
        break;
    case 'info':
        /**
         * Provides information that could be usable for an editor. e.g.
         *  1. Urls for various actions, e.g. preview
         *  2. Listings of various option values
         *  3. Forbidden words lists
         *  4. Supportted answertests
         *  5. Version numbers
         */
        $data = ['urls' => [], 'options' => [], 'words' => [],
            'answertests' => [], 'version' => []];

        // Schema.
        $data['json-schema'] = stateful_handling_json::schema();

        // URLs.
        $data['urls']['info'] = (new moodle_url(
            '/question/type/stateful/editorapi.php', ['mode' => 'info']))->out(false);
        $data['urls']['validate'] = (new moodle_url(
            '/question/type/stateful/editorapi.php', ['mode' => 'validate']))->out(false);
        $data['urls']['load'] = (new moodle_url(
            '/question/type/stateful/editorapi.php', ['mode' => 'load']))->out(false);
        $data['urls']['loadfull'] = (new moodle_url(
            '/question/type/stateful/editorapi.php', ['mode' => 'loadfull']))->out(false);
        $data['urls']['save'] = (new moodle_url(
            '/question/type/stateful/editorapi.php', ['mode' => 'save']))->out(false);
        $data['urls']['savenew'] = (new moodle_url(
            '/question/type/stateful/editorapi.php', ['mode' => 'savenew']))->out(false);

        $data['urls']['preview'] = (new moodle_url('/question/preview.php'))->out(false);

        $data['urls']['test'] = (new moodle_url(
            '/question/type/stateful/editorapi.php', ['mode' => 'test']))->out(false);

        $data['urls']['render'] = (new moodle_url(
            '/question/type/stateful/editorapi.php', ['mode' => 'render']))->out(false);

        $data['urls']['qnote'] = (new moodle_url(
            '/question/type/stateful/editorapi.php', ['mode' => 'qnote']))->out(false);


        // Options.
        $stackopts = new stack_options();
        $stackopts->set_site_defaults();
        // TODO: it would be nice if that class would provide a listing of all the options...
        // so we wouldn't need to do this.
        $data['options']['question level']                       = [];
        $data['options']['question level']['multiplicationsign'] = [
            'default' => $stackopts->get_option('multiplicationsign'), 'type' =>
            'select'];
        $data['options']['question level']['multiplicationsign']['values'] =
        stack_options::get_multiplication_sign_options();

        $data['options']['question level']['complexno'] = ['default' =>
            $stackopts->get_option('complexno'), 'type' => 'select'];
        $data['options']['question level']['complexno']['values'] = stack_options::
            get_complex_no_options();

        $data['options']['question level']['inversetrig'] = ['default' =>
            $stackopts->get_option('inversetrig'), 'type' => 'select'];
        $data['options']['question level']['inversetrig']['values'] = stack_options::get_inverse_trig_options();

        $data['options']['question level']['sqrtsign'] = ['default' =>
            $stackopts->get_option('sqrtsign'), 'type' => 'boolean'];
        $data['options']['question level']['sqrtsign']['values'] = stack_options::
            get_yes_no_options();

        $data['options']['question level']['questionsimplify'] = ['default' =>
            $stackopts->get_option('simplify'), 'type' => 'boolean'];
        $data['options']['question level']['questionsimplify']['values'] =
        stack_options::get_yes_no_options();

        $data['options']['question level']['assumepositive'] = ['default' =>
            $stackopts->get_option('assumepos'), 'type' => 'boolean'];
        $data['options']['question level']['assumepositive']['values'] =
        stack_options::get_yes_no_options();

        $data['options']['question level']['assumereal'] = ['default' =>
            $stackopts->get_option('assumereal'), 'type' => 'boolean'];
        $data['options']['question level']['assumereal']['values'] = stack_options::get_yes_no_options();

        $data['options']['question level']['matrixparens'] = ['default' =>
            $stackopts->get_option('matrixparens'), 'type' => 'select'];
        $data['options']['question level']['matrixparens']['values'] =
        stack_options::get_matrix_parens_options();

        $stackconfig = stack_utils::get_config();


        $data['options']['inputs'] = stateful_input_controller::get_input_metadata();

        // Forbidden words.
        // TODO: get the other end to provide interface for accessing this.
        $sd = file_get_contents(__DIR__ .
            '/../stack/stack/cas/security-map.json');
        $data['words']['map']   = json_decode($sd, true);
        $data['words']['units'] = array_keys(stack_cas_casstring_units::
                get_permitted_units(0));

        // Answertests.
        foreach (stateful_answertest_factory::get_all() as $cat => $tests) {
            $cate = ['name' => $cat, 'tests' => []];
            foreach ($tests as $name => $test) {
                $td = ['name' => $name];
                $td['tans required'] = $test->requires_tans();
                $td['raw input required'] = $test->requires_direct_input_ref();
                $td['options'] = $test->option_meta();
                $cate['tests'][] = $td;
            }
            $data['answertests'][] = $cate;
        }

        // Versions.
        $pm                          = core_plugin_manager::instance();
        $data['version']['Stateful'] = $pm->get_plugin_info('qtype_stateful')->versiondisk;
        $data['version']['STACK'] = $pm->get_plugin_info('qtype_stack')->versiondisk;

        // Meta.
        $data['meta'] = ['backend' => 'Moodle'];

        header('Content-Type: application/json');
        echo json_encode($data);
        break;

    case 'test':
        // Executes tests following specific settings, must either provide
        // a definition for unsaved question or the identity of an previously
        // saved one. Can run BFS testing with known test inputs starting
        // from given states and seeds to some depth- or timelimit or
        // alternatively target path testing. Stops on error. Returns:
        //  1. Entry state of a scene and seed or just seed for starting from
        //     the entry scene.
        //  2. List of tests with valid condition
        //    - test input for each test
        //    - success
        //    - if state transfer then the exit state
        // Note that iterating the BFS testing may never end and that it will
        // more thatn likely generate astronomical amounts of data if being
        // executed with a large depth setting. So run it in waves and use
        // the exit states to select new points to continue from, use some
        // heuristic to select the ones you wish to dig deepper from.
        // Does not simulate grading.

        $data             = json_decode(file_get_contents('php://input'), true);
        $question         = stateful_handling_json::from_array($data);
        $validationresult = stateful_handling_validation::validate($question);
        \core\session\manager::write_close();
        header('Content-Type: application/json');

        if (!$validationresult['result']) {
            http_response_code(400);
            $response = $validationresult;
            $response['status'] = 'invalid question definition';
            echo json_encode($data);
        } else {
            switch ($data['mode']) {
                // Check just the given states
                case 'check':
                    // We stream now. So print out tested things one at a time.
                    echo '{"result":[';
                    $first = true;
                    foreach ($data['states'] as $state) {
                        $result = stateful_handling_testing::test($question, $state);
                        if ($first) {
                            $first = false;
                        } else {
                            echo ',';
                        }
                        echo json_encode($result);
                    }

                    echo '],"status":"complete"}';
                    break;
                case 'depth':
                    $limit = 3;
                    if (isset($data['test_depth']) && is_numeric($data['test_depth'])) {
                        $limit = $data['test_depth'];
                    }
                    $totest = $data['states'];
                    $totest[] = $limit;
                    $tonottest = array();
                    if (isset($data['ignore_states'])) {
                        foreach ($data['ignore_states'] as $state) {
                            $s = $state;
                            ksort($s);
                            $tonottest[] = $s;
                        }
                    }

                    echo '{"result":[';
                    $first = true;
                    for ($i = 0; $i < count($totest); $i++) {
                        set_time_limit(30);
                        $state = $totest[$i];
                        ksort($state);
                        if (is_array($state)) {
                            $result = stateful_handling_testing::test($question, $state);
                            if ($first) {
                                $first = false;
                            } else {
                                echo ',';
                            }
                            echo json_encode($result);
                            flush();
                            // Move new states to the end of the queue, do however check if they
                            // are already there.
                            foreach ($result['results'] as $testcase) {
                                if (isset($testcase['nextstate'])) {
                                    $ns = $testcase['nextstate'];
                                    ksort($ns);
                                    $found = false;
                                    // TODO: This loop should limit the search to active depth.
                                    foreach ($totest as $testing) {
                                        // Note on equality, these come from the same
                                        // generator and are ordered by the question definition.
                                        // Thus equality is simple.
                                        if ($testing == $ns) {
                                            $found = true;
                                            break;
                                        }
                                    }
                                    if (!$found) {
                                        foreach ($tonottest as $nottest) {
                                            if ($nottest == $ns) {
                                                $found = true;
                                                break;
                                            }
                                        }
                                    }

                                    if (!$found) {
                                        $totest[] = $ns;
                                    }
                                }
                            }
                        } else if ($state <= 1) {
                            break;
                        } else {
                            // Until the next limit.
                            $totest[] = $state - 1;
                        }
                    }

                    echo '],"status":"complete"}';
                    break;
            }
        }
        break;

    case 'render':
        // Renders a static presentation of the given question in a given state.
        $data             = json_decode(file_get_contents('php://input'), true);
        $question         = stateful_handling_json::from_array($data);
        $validationresult = stateful_handling_validation::validate($question);
        \core\session\manager::write_close();

        header('Content-Type: text/html');


        // Get the state.
        $state = array();
        $seed = 1;
        if (isset($data['state'])) {
            $state = $data['state'];
        }
        if (isset($state['RANDOM_SEED'])) {
            $seed = $state['RANDOM_SEED'];
            $seed = intval($seed);
        } 

        // Check that it is fully defined.
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
            $question->start_attempt($fakestep, $seed);
            $state = $question->get_state_array();
        }

        // Setup the question to the state.
        foreach ($question->get_state_variable_identifiers() as $vnum => $varname) {
            $fakestate->set($vnum, $state[$varname]);
        }
        $question->set_state($fakestate);
        $question->apply_attempt_state($fakestep);

        // Build some more fake things.
        $fakeattempt = new question_attempt($question, -1);

        // Manage output.
        $PAGE = new \moodle_page();
        $PAGE->set_course($SITE);
        $context = context_system::instance();
        $PAGE->set_context($context);
        $PAGE->set_pagelayout('embedded');
        $PAGE->set_url(new moodle_url(
            '/question/type/stateful/editorapi.php', ['mode' => 'render']));

        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        echo $OUTPUT->header();
        echo '<style type="text/css">div.que div.info {display: none;} div.que div.content {margin-left: 0;}</style>';

        $options = new question_preview_options($question);
        $options->load_user_defaults();
        $options->set_from_request();
        $options->history = question_display_options::HIDDEN;
        $options->rightanswer = question_display_options::HIDDEN;
        $options->behaviour = 'stateful';
        $quba = question_engine::make_questions_usage_by_activity(
                'core_question_preview', context_user::instance($USER->id));
        $quba->set_preferred_behaviour($options->behaviour);
        $slot = $quba->add_question($question, $options->maxmark);
        $quba->start_question($slot, $seed);
        $displaynumber = '1';

        $question->set_state($fakestate);
        $question->apply_attempt_state($fakestep);


        echo '<form>';
        echo $quba->render_question($slot, $options, $displaynumber);
        echo '</form>';

        $PAGE->requires->js_module('core_question_engine');
        $PAGE->requires->strings_for_js(array(
            'closepreview',
        ), 'question');
        echo $OUTPUT->footer();
        break;

    case 'grade':
        // Grades a sequence of inputs on a given start seed. Again provide
        // a full question or a reference to previously saved one.
        // This will simulate both penalty non penalty process.

        break;

    default:
        http_response_code(404);
        echo "Unknown API mode '$mode'.";
}