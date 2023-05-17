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
defined('MOODLE_INTERNAL') || die();
require_once __DIR__ . '/stateful/core/model.class.php';
require_once __DIR__ . '/stateful/core/state_carrier.php';
require_once __DIR__ . '/stateful/utils.class.php';
require_once __DIR__ . '/stateful/core/functionbuilder.class.php';
require_once __DIR__ . '/stateful/input2/input.controller.php';
require_once __DIR__ . '/stateful/handling/validation.php';
require_once __DIR__ . '/../../behaviour/stateful/behaviour.php';
require_once __DIR__ . '/stacklib.php';
require_once __DIR__ . '/locallib.php';


class qtype_stateful_question extends question_definition implements
question_stateful, stateful_model {
    // These are the properties we simply copy from STACK.

    /**
     * @var string STACK specific: variables, as authored by the teacher.
     */
    public $questionvariables;

    /**
     * @var string STACK specific: used to separate random variants.
     */
    public $questionnote;

    /**
     * @var stack_options STACK specific: question-level options.
     */
    public $options;
    // Then the more specific ones.
    // Store the scenes. Keyed by name ordered by definition order.
    public $scenes;
    // And the full definitions for the state variables. Keyed by name ordered by definition order.
    public $variables;
    // The name of the entry scene.
    public $entryscene;

    public $stackversion;
    public $statefulversion;

    // Compiledcache is a complex set of expressions and lists stored in the database.
    // Basically the precompiled PRT functions and such.
    public $compiledcache;

    // Data that will not get used in execution, just holding it for export actions.
    public $genericmeta;

    public $parlength;

    public $variants;

    // The state storage access object from the behaviour.
    public $state = null;
    // Code readability vs. efficiency.
    const SCENE_CURRENT = -1;
    const SCENE_PATH    = -2;


    // e.g. teachers answers evaluated for this scene.
    private $casparams = [];

    // As we need to init the input values we may need to keep track on which
    // scene was last initted.
    private $lastsceneinited = null;

    public $security = null;

    // Current active input controller.
    // stateful_input_controller
    public $inputs;

    // Scenetext gets rendered during init lets keep a hold on in.
    private $scenetext;

    // General feedback is also rendered during init.
    private $modelsolution;

    // Some flags to track execution, in case we are using the question 
    // through simpler APIs that do things direct.
    public $sceneinitialised = false;
    public $inputvalidated = false;
    public $prtsprocessed = false;
    public $inputvalidationrendered = false;

    /**
     * @var castext2_processor an accesspoint to the question attempt for
     * the castext2 post-processing logic for pluginfile url-writing.
     */
    public $castextprocessor = null;

    public function make_behaviour(
        question_attempt $qa,
        $preferredbehaviour
    ) {
        $this->castextprocessor = new stateful_castext2_default_processor($qa);
        return question_engine::make_behaviour('stateful', $qa,
            $preferredbehaviour);
    }

    /**
     * Gives the question access to the state storage. You can assume that this
     * has been called early in the process, before anything that requires
     * evaluation of responses.
     * @param qbehaviour_stateful_state_storage access to the storage of this step.
     */
    public function set_state(qbehaviour_stateful_state_storage $state) {
        $this->state = $state;
    }

    public function get_state() {
        return $this->state;
    }

    public function get_state_array(): array {
        $r = array('RANDOM_SEED' => $this->seed);
        $r['SCENE_CURRENT'] = $this->state->get(self::SCENE_CURRENT);
        $r['SCENE_PATH'] = $this->state->get(self::SCENE_PATH);
        foreach ($this->variables as $variable) {
            $r[$variable->name] = $this->state->get($variable->number);
        }
        return $r;
    }

    /**
     * Asks the question for an array defining the numeric identifiers of all the state variables it needs stored or retrieved from storage. Always, called before set_state. Also provides matching names for debug displays.
     * @return array of integer identifiers mapped to variable names.
     */
    public function get_state_variable_identifiers(): array{
        // These are the stored main variables. SCENE_NEXT is not stored.
        $r = [self::SCENE_CURRENT => 'SCENE_CURRENT',
            self::SCENE_PATH => 'SCENE_PATH'];
        // These are the ones coming from the logic.

        foreach ($this->variables as $variable) {
            $r[$variable->number] = $variable->name;
        }
        return $r;
    }

    public function get_scene_sequence_number(qbehaviour_stateful_state_storage
         $state=null): int {
        $path = [];
        if ($state !== null) {
            $path = stateful_utils::string_to_list($state->get(self::SCENE_PATH, '[]'));
        } else {
            $path = stateful_utils::string_to_list($this->state->get(self::SCENE_PATH, '[]'));
        }
        return count($path);
    }

    public function is_valid_input(array $values): bool{
        $scene = $this->get_current_scene();
        if ($this->lastsceneinited !== $scene->name) {
            $this->init_from_state();
        }        

        $valid = $this->validate_input($values);

        return $valid;
    }

    public function is_in_end_scene(): bool{
        $scene = $this->get_current_scene();
        return count($scene->inputs) === 0;
    }

    public function get_max_fraction() {
        return $this->defaultmark;
    }

    public function get_min_fraction() {
        return 0.0;
    }

    //private function get_current_scene(): stateful_scene {
    public function get_current_scene() {
        // Use this while the behaviour state passing fails.
        if ($this->state === null) {
            echo '<pre>';
            debug_print_backtrace();
            echo '</pre>';
        }

        return $this->scenes[stack_utils::maxima_string_to_php_string($this->
            state->get(self::SCENE_CURRENT, stack_utils::
                    php_string_to_maxima_string($this->
                    entryscene)))];
    }



    private function validate_input(array $input): bool {
        // To validate input we need to build a session with:
        //  - Question-variables
        //  - State-variables
        //  - Scene-variables
        //  - The validation statements of the inputs.
        // Should the last set be empty we do not need to evaluate
        // anything in CAS.
        $scene = $this->get_current_scene();
        $valid = true;
        $sec = $this->security;

        $validationstatements = $this->inputs->collect_validation_statements($input, $sec);

        $this->inputvalidated = true;

        if (count($validationstatements) === 0) {
            return $this->inputs->all_valid();
        }


        $statements = [];
        if (is_numeric($this->seed) || is_integer($this->seed)) {
            $statements[] = new stack_secure_loader('RANDOM_SEED:' . $this->seed, 'validate_input', $sec);
        } else {
            $statements[] = stack_ast_container_silent::make_from_teacher_source('RANDOM_SEED:' . $this->seed, 'validate_input', $sec);
        }

        // If we are using localisation we should tell the CAS side logic about it.
        // For castext rendering and other tasks.
        if (count($this->get_compiled('langs')) > 0) {
            $ml = new stack_multilang();
            $selected = $ml->pick_lang($this->get_compiled('langs'));
            $statements[] = new stack_secure_loader('%_STACK_LANG:' .
                stack_utils::php_string_to_maxima_string($selected), 'language setting');
        }

        // The question-variables.
        $statements[] = $this->get_compiled('qv');
        // Then call them.
        $statements[] = new stack_secure_loader('_question_variables(RANDOM_SEED)', 'validate_input');

        // The state-variables
        foreach ($this->variables as $statevar) {
            $statements[] = stateful_state_carrier::make_from_teacher_source($statevar->name .
                ':' .
                $this->state->get($statevar->number), 'validate_input', $sec);
        }
        $statements[] = stack_ast_container_silent::make_from_teacher_source('SCENE_CURRENT:' . $this->
            state->get(self::SCENE_CURRENT), 'validate_input', $sec);
        $statements[] = stack_ast_container_silent::make_from_teacher_source('SCENE_PATH:' . $this->
            state->get(self::SCENE_PATH), 'validate_input', $sec);
        $statements[] = stack_ast_container_silent::make_from_teacher_source('SCENE_NEXT:false', 'validate_input', $sec);

        // And the scene variables.
        $svfunction = $this->get_compiled('scene-' . $scene->name .
            '-variables');
        $statements[] = $svfunction;
        // And to call it we need its signature.
        $statements[] = new stack_secure_loader(explode(':=', $svfunction->
            get_evaluationform())[0], 'validate_input');

        // Actual validation happens in simp:false.
        $statements[] = new stack_secure_loader('simp:false', 'validate_input');

        // Include the validation statements into the session.
        $statements = array_merge($statements, $validationstatements);

        // And execute.
        $session = new stack_cas_session2($statements, $this->options, $this->seed);
        $session->errclass = 'stateful_cas_error';
        $session->instantiate();


        // the input controller now has all the information it needs.
        return $this->inputs->all_valid_and_validated_or_blank();
    }

    // Generates validation boxes in cases where PRTs are not being evaluated.
    // Otherwise this will be inlined to PRT evaluation.
    public function render_validation(array $input): void {
        $scene = $this->get_current_scene();

        if ($this->lastsceneinited !== $scene->name) {
            $this->init_from_state();
        }

        $validationrender = $this->inputs->collect_validation_render_statements();
        if (count($validationrender) > 0) {
            // So we need to construct the session for these.
            $sec = $this->security;

            $statements = [stack_ast_container_silent::make_from_teacher_source('RANDOM_SEED:' . $this
                    ->seed, 'render_validation', $sec)];
            // If we are using localisation we should tell the CAS side logic about it.
            // For castext rendering and other tasks.
            if (count($this->get_compiled('langs')) > 0) {
                $ml = new stack_multilang();
                $selected = $ml->pick_lang($this->get_compiled('langs'));
                $statements[] = new stack_secure_loader('%_STACK_LANG:' .
                    stack_utils::php_string_to_maxima_string($selected), 'language setting');
            }

            // 1. We need the question variables for this seed.
            $qvfunction   = $this->get_compiled('qv');
            $statements[] = $qvfunction;
            // Then call it.
            $statements[] = new stack_secure_loader('_question_variables(RANDOM_SEED)', 'render_validation');

            // 2. Add the state into it.
            foreach ($this->variables as $statevar) {
                $statements[] = stateful_state_carrier::make_from_teacher_source($statevar->name .
                    ':' .
                    $this->state->get($statevar->number), 'render_validation', $sec);
            }
            $statements[] = stack_ast_container_silent::make_from_teacher_source('SCENE_CURRENT:' . $this->
                state->get(self::SCENE_CURRENT), 'render_validation', $sec);
            $statements[] = stack_ast_container_silent::make_from_teacher_source('SCENE_PATH:' . $this->
                state->get(self::SCENE_PATH), 'render_validation', $sec);
            $statements[] = stack_ast_container_silent::make_from_teacher_source('SCENE_NEXT:false', 'render_validation', $sec);

            // 3. We also need the scene variables for this seed and state.
            $svfunction = $this->get_compiled('scene-' . $scene->name .
                '-variables');
            $statements[] = $svfunction;
            // And to call it we need its signature.
            $statements[] = new stack_secure_loader(explode(':=', $svfunction->
                get_evaluationform())[0], 'render_validation');

            // 4. Include input values.
            $statements[] = new stack_secure_loader('simp:false', 'render_validation');

            $statements = array_merge($statements, $this->inputs->collect_cas_values());

            // 5. The validation statements...
            $statements = array_merge($statements, $validationrender);
            foreach ($statements as $statement) {
                if ($statement->get_valid() !== true) {
                    throw new stateful_exception(stateful_string(
                        'error_in_processing'));
                }
            }

            $session = new stack_cas_session2($statements, $this->options, $this->seed);
            $session->errclass = 'stateful_cas_error';
            $session->instantiate();

            // Check that error.
            if ($session->get_errors() !== null && $session->get_errors() !== '') {
                throw new stateful_exception(stateful_string(
                    'error_in_input_processing_cas_evaluation', $session->get_errors()));
            }
        }
        $this->inputvalidationrendered = true;
    }

    public function process_input(
        array $input,
        bool $return_feedback = false
    ): array{
        $scene = $this->get_current_scene();

        if ($this->lastsceneinited !== $scene->name) {
            $this->init_from_state();
        }

        $r = ['_attemptstatus' => question_state::$todo, '_summary' => '',
            '_feedback' => []]
        ;

        $this->prtsprocessed = true;

        // 0. Stop processing if something is invalid, just in case something
        // might be missed or done too early.
        // 0.1. Also stop if we are dealing with the testcases.
        if (!$this->is_valid_input($input) || (isset($input['testCaseFill']) && $input['testCaseFill'] !== '-')) {
            $r['_attemptstatus'] = question_state::$invalid;
            $summary             = ['[UNCONFIRMED OR INVALID INPUT]'];
            $summary[] = $this->inputs->summarise();

            $r['_summary'] = implode(', ', $summary);
            foreach ($scene->prts as $prt) {
                $prtid = 'prt-result-' . $this->get_scene_sequence_number($this
                        ->state) . '-' . $prt->name;
                $this->casparams[$prtid] = 'NOEVAL';
            }
            $this->render_validation($input);
            return $r;
        }

        // 0.1. If this is an end scene why do we continue at all?
        if ($this->is_in_end_scene()) {
            foreach ($scene->prts as $prt) {
                $prtid = 'prt-result-' . $this->get_scene_sequence_number($this
                        ->state) . '-' . $prt->name;
                    $this->casparams[$prtid] = 'NOEVAL';
                }

            $r['_attemptstatus'] = question_state::$complete;
            $r['_summary']       = '[END]';
            $this->render_validation($input);
            return $r;
        }
        // 1. Do we have input and is it enough to trigger any PRTs?
        $prts = [];
        foreach ($scene->prts as $prt) {
            $required = $this->get_compiled('scene-' . $scene->name . '-prt-' .
            $prt->name . '|inputs');

            if ($this->inputs->has_valid_for($required, false)) {
                // All seem to be available for this PRT.
                $prts[$prt->name] = $this->get_compiled('scene-' . $scene->name
                    . '-prt-' . $prt->name);
            } else {
                $prtid = 'prt-result-' . $this->get_scene_sequence_number($this
                    ->state) . '-' . $prt->name;
                $this->casparams[$prtid] = 'NOEVAL';
            }
        }

        $summary = [];

        // 2. Now we have the processing functions for all PRTs we may need to process
        // lets build the scope for them.
        if (count($prts) > 0) {
            $summary[] = '[CLASSIFYING INPUT]';
            $sec = $this->security;

            $statements = [stack_ast_container_silent::make_from_teacher_source('RANDOM_SEED:' . $this
                    ->seed, 'process_input', $sec)];

            // If we are using localisation we should tell the CAS side logic about it.
            // For castext rendering and other tasks.
            if (count($this->get_compiled('langs')) > 0) {
                $ml = new stack_multilang();
                $selected = $ml->pick_lang($this->get_compiled('langs'));
                $statements[] = new stack_secure_loader('%_STACK_LANG:' .
                    stack_utils::php_string_to_maxima_string($selected), 'language setting');
            }

            // 1. We need the question variables for this seed.
            $qvfunction   = $this->get_compiled('qv');
            $statements[] = $qvfunction;
            // Then call it.
            $statements[] = new stack_secure_loader('_question_variables(RANDOM_SEED)', 'process_input');

            // 2. Add the state into it.
            foreach ($this->variables as $statevar) {
                $statements[] = stateful_state_carrier::make_from_teacher_source($statevar->name .
                    ':' .
                    $this->state->get($statevar->number), 'process_input', $sec);
            }
            $statements[] = stack_ast_container_silent::make_from_teacher_source('SCENE_CURRENT:' . $this->
                state->get(self::SCENE_CURRENT), 'process_input', $sec);
            $statements[] = stack_ast_container_silent::make_from_teacher_source('SCENE_PATH:' . $this->
                state->get(self::SCENE_PATH), 'process_input', $sec);
            $statements[] = stack_ast_container_silent::make_from_teacher_source('SCENE_NEXT:false', 'process_input', $sec);

            // 3. We also need the scene variables for this seed and state.
            $svfunction = $this->get_compiled('scene-' . $scene->name .
                '-variables');
            $statements[] = $svfunction;
            // And to call it we need its signature.
            $statements[] = new stack_secure_loader(explode(':=', $svfunction->
                get_evaluationform())[0], 'process_input');

            // 4. Include input values.
            $statements[] = new stack_secure_loader('simp:false', 'process_input');

            $summary[] = $this->inputs->summarise();
            $statements = array_merge($statements, $this->inputs->collect_cas_values());

            // 4.5 Include validation display values.
            $statements = array_merge($statements, $this->inputs->collect_validation_render_statements());

            // 5. Define the PRT functions.
            foreach ($prts as $prtfunction) {
                $statements[] = $prtfunction;
            }

            // Ensure simp:false after validation display logic.
            $statements[] = new stack_secure_loader('simp:false', 'process_input');

            // 6. Call them and catch the values.
            $i = 0;
            $prtresults = array();
            foreach ($prts as $prtfunction) {
                $sta = stack_ast_container::make_from_teacher_source('__PRT_RESULT_' . $i .
                    ':' .
                    explode(':=', $prtfunction->get_evaluationform())[0], 'process_input', $sec);
                $prtresults[$i] = $sta;
                // Ensure that each gets called with simp:false.
                $statements[] = new stack_secure_loader('simp:false', 'process_input');
                $statements[] = $sta;
                $i = $i + 1;
            }
            // After that simplify.
            $statements[] = new stack_secure_loader('simp:true', 'process_input');

            // 7. Ensure output of state variables for storage.
            // First append to the path... and output new values for the SCENE-vars
            $state = array();
            $state['SCENE_PATH'] = stateful_state_carrier::make_from_teacher_source(

'SCENE_PATH:if not is(SCENE_NEXT=false) then (SCENE_PATH:append(SCENE_PATH,[SCENE_CURRENT]),SCENE_CURRENT:SCENE_NEXT,SCENE_PATH) else SCENE_PATH'
            , 'process_input', $sec);
            $state['SCENE_CURRENT'] = stateful_state_carrier::make_from_teacher_source(
                'SCENE_CURRENT:SCENE_CURRENT', 'process_input', $sec);
            $state['SCENE_NEXT'] = stateful_state_carrier::make_from_teacher_source('SCENE_NEXT:SCENE_NEXT', 'process_input', $sec);
            foreach ($this->variables as $statevar) {
                // Then cause output of current values of other vars.
                $state[$statevar->name] = stateful_state_carrier::make_from_teacher_source($statevar->name . ':' .
                    $statevar->name, 'process_input', $sec);
            }

            $statements = array_merge($statements, array_values($state));

            // Again instantiate, and validate already validated.
            // TODO: that session that does not require this.
            foreach ($statements as $statement) {
                if ($statement->get_valid() !== true) {
                    throw new stateful_exception(stateful_string(
                        'error_in_processing'));
                }
            }


            $session = new stack_cas_session2($statements, $this->options, $this
                ->seed);
            $session->errclass = 'stateful_cas_error';
            $session->instantiate();

            // Check that error.
            if ($session->get_errors() !== null && $session->get_errors() !== '') {
                throw new stateful_exception(stateful_string(
                    'error_in_input_processing_cas_evaluation', $session->get_errors()));
            }

            // 8. Read in the results and state. Also store the prt result to cache.
            $i = 0;
            foreach ($prts as $name => $duh) {
                $r[$name] = $prtresults[$i]->get_value();
                // Now we need the sequence number to identify for which scene
                // the prt results apply.
                $prtid = 'prt-result-' . $this->get_scene_sequence_number($this
                        ->state) . '-' . $name;
                $this->casparams[$prtid] = $r[$name];
                if ($prtresults[$i]->is_list(true) > 3) {
                    $feedback = $prtresults[$i]->get_list_element(4, true);
                    $list = $prtresults[$i]->get_evaluated();
                    if ($list instanceof MP_Root) {
                        $list = $list->items[0];
                    }
                    if ($list instanceof MP_Statement) {
                        $list = $list->statement;
                    }
                    // Do some cleaning so that we do not store certain things.
                    $short = clone $list;
                    $short->items = array_slice($short->items, 0, 4);
                    $r['_feedback'][$name] = $feedback;
                    $r[$name]              = $list->toString();
                }
                $i = $i + 1;
            }

            // 9. If scene change happened initialise inputs. And store the changed state.
            // state only changes if scene changes.
            // And scene changes may have been deactivated.
            if ($state['SCENE_NEXT']->get_evaluated_state() !== 'false' && ! isset($input['%deactivate%'])) {
                foreach ($this->variables as $statevar) {
                    $this->state->set($statevar->number, $state[
                        $statevar->name]->get_evaluated_state());
                }
                $this->state->set(self::SCENE_PATH, $state[
                    'SCENE_PATH']->get_evaluated_state());
                $this->state->set(self::SCENE_CURRENT, $state['SCENE_NEXT']->get_evaluated_state());
                $this->init_from_state();
                // Ensure reinitilaisation of input in cases where
                // input names are shared in following scenes.
                $this->validate_input($input);
                $this->render_validation($input);
                $summary[0]          = '[INPUT CAUSED STATE CHANGE]';
                $r['_attemptstatus'] = question_state::$complete;
            } else {
                // Evaluating, whether this was wrong or not is not sensible.
                $r['_attemptstatus'] = question_state::$complete;
            }
        } else {
            $summary[] = '[NO ACTIONABLE INPUT]';
            $summary[] = $this->inputs->summarise();
            $r['_attemptstatus'] = question_state::$todo;

            // We do still need to produce validation renders if we 
            // have content for them.
            $this->render_validation($input);
        }
        $r['_summary'] = implode(', ', $summary);

        if (!$return_feedback) {
            unset($r['_feedback']);
        }

        return $r;
    }

    public function evaluate_total_grade(
        array $sequence,
        bool $penalties
    ):
    array{
        $score = 0.0;

        // Get the path.
        $path = stateful_utils::string_to_list($this->state->get(self::
                SCENE_PATH, '[' . stack_utils::php_string_to_maxima_string(
                $this->entryscene) .
            ']'));

        // Note that the path does not include current scene.
        $path[] = $this->state->get(self::SCENE_CURRENT, $this->entryscene);

        // Collect scores of all PRTs. For all scenes
        $prts = [];

        // For each step in the path check matching stored partial results.
        $i = 0;
        foreach ($path as $scenename) {
            if (!array_key_exists($i, $sequence)) {
                $i++;
                continue;
            }

            $scene = $this->scenes[stack_utils::maxima_string_to_php_string(
                $scenename)];

            $sceneprt = [];

            foreach ($scene->prts as $prt) {
                switch ($prt->scoremode) {
                case 'best':
                case 'first':
                case 'bestn':
                    $sceneprt[$prt->name] = ['mode' => $prt->scoremode,
                        'modeparam' => $prt->scoremodeparameters, 'penalty' =>
                        0, 'value' => $prt->value
                    ];
                    break;
                }
            }
            foreach ($sequence[$i] as $attempt) {

                foreach ($attempt as $prtname => $res) {
                    if (array_key_exists($prtname, $sceneprt)) {
                        // If it does not exist it probably is a no score PRT.

                        // [_PATH,_RESULTS,_SCORE,_PENALTY] is the structure.
                        $res = stateful_utils::string_to_list($res);
                        if (count($res) > 3) {
                            // If length is les than 4 then that PRT did not
                            // trigger completly.
                            $sc  = floatval($res[2]);
                            $pen = floatval($res[3]);

                            if ($penalties) {
                                $sceneprt[$prtname]['penalty'] = $sceneprt[
                                    $prtname]['penalty'] + $pen;
                                $sceneprt[$prtname]['penalty'] = min($sceneprt[
                                    $prtname]['penalty'], 1.0);
                                $sc = max($sc - $sceneprt[$prtname]['penalty'],
                                    0);
                            }
                            if (array_key_exists('score', $sceneprt[$prtname])
                                && $sc > $sceneprt[$prtname]['score']) {
                                $sceneprt[$prtname]['score'] = $sc;
                            } else if (!array_key_exists('score', $sceneprt[
                                $prtname])) {
                                $sceneprt[$prtname]['score'] = $sc;
                            }
                        }
                    }
                }

                // Reset the prt data and transfer to the outter layer as scene
                // visitation score.
                foreach ($sceneprt as $prtname => $prtscore) {
                    if (array_key_exists('score', $prtscore)) {
                        $visit = ['score' => $prtscore['score'], 'mode' =>
                            $prtscore['mode'], 'modeparam' => $prtscore[
                                'modeparam'], 'value' => $prtscore[
                                'value']];
                        if (!array_key_exists($scene->name . '/|/' . $prtname,
                            $prts)) {
                            $prts[$scene->name . '/|/' . $prtname] = [];
                        }

                        $prts[$scene->name . '/|/' . $prtname][] = $visit;
                        // Reset.
                        $prtscore['penalty'] = 0;
                        unset($prtscore['score']);
                    }
                }
            }
            $i++;
        }

        // Now the visitations to scenes have been collected, calculate
        // agregates based on individual rules.
        foreach ($prts as $visits) {
            $aggregate = -1;
            $first     = $visits[0];
            $ordered   = [];
            foreach ($visits as $visit) {
                $ordered[] = $visit['score'];
            }
            rsort($ordered, SORT_NUMERIC); 
            switch ($first['mode']) {
            case 'best':
                // Just pick the max.
                $aggregate = $ordered[0];
                break;
            case 'bestn':
                // Select N largest.
                $aggregate = 0;
                for ($k = 0; $k < intval($first['modeparam']) && $k < count(
                    $ordered); $k++) {
                    $aggregate = $aggregate + $ordered[$k];
                }
                break;
            case 'first':
                $aggregate = $first['score'];
                break;
            }

            if ($aggregate > -1) {
                // Scale the score by PRT value.
                $score = $score + ($aggregate * floatval($first['value']));
            }
        }

        // OK so we need to return this to 0...1
        if (floatval($this->defaultmark) == 0.0) {
            $score = 0.0;
        } else {
            $score = $score / floatval($this->defaultmark);
        }


        return ['total' => $score];
    }
    // Pretty much the standard way but the $state object has been given with set_state
    // and the only thing that we want to store and pass with the step is the seed.
    // Key here is that when we start an attempt we need to initialise the state variable
    // values and set them to the $state.
    public function start_attempt(
        question_attempt_step $step,
        $variant
    ) {
        $variants = array();
        if (!($this->variants === '' || $this->variants === '{}' || $this->variants === null)) {
            $variants = json_decode($this->variants, true);    
        }
        
        if (isset($variants['_set']) && isset($variants[$variants['_set']])) {
            $this->seed = (int) $variants[$variants['_set']][$variant % count($variants[$variants['_set']])];
        } else {
            $this->seed = (int) $variant;
        }
        $step->set_qt_var('_seed', $this->seed);
        
        $this->sceneinitialised = false;
        $this->inputvalidated = false;
        $this->prtsprocessed = false;
        $this->inputvalidationrendered = false;

        // If we are being called by the AJAX-validation, we can ignore these
        // the relevant ones are called once it has defiend additinal details.
        if (strpos($_SERVER['PHP_SELF'],"lib/ajax/service.php") === false) {
            $this->init_state_vars();
            $this->init_from_state();
        }
    }
    // Pretty much the standard way but the $state object has been given with set_state
    // and the only thing that we want to store and pass with the step is the seed.
    public function apply_attempt_state(question_attempt_step $step) {
        // We keep the seed in the initial step for other things to see as opposed to
        // storing it as a state variable.
        $this->seed = (int) $step->get_qt_var('_seed');

        $this->sceneinitialised = false;
        $this->inputvalidated = false;
        $this->prtsprocessed = false;
        $this->inputvalidationrendered = false;

        // Now we have a problem. For whatever reason some parts of the question
        // engine do not call this through the behaviour and thus the behaviour
        // cannot provide us the state. Therefore we need to react to that.
        if ($this->state->get(self::SCENE_CURRENT, null) == null) {
            //      echo "<pre>";
            //      debug_print_backtrace();
            //      echo "</pre>";
        } else {
            $this->init_from_state();
        }
    }


    private function init_state_vars() {
        // Generates initial values for state variables and saves them to the state object.
        // We can use pretty much standard STACK cas sessions here.
        // 1. We need the question variables for this seed.
        $statements = [];
        if (is_numeric($this->seed) || is_integer($this->seed)) {
            $statements[] = new stack_secure_loader('RANDOM_SEED:' . $this->seed, 'init_state_vars', $this->security);
        } else {
            $statements[] = stack_ast_container_silent::make_from_teacher_source('RANDOM_SEED:' . $this->seed, 'init_state_vars', $this->security);
        }

        // If we are using localisation we should tell the CAS side logic about it.
        // For castext rendering and other tasks.
        if (count($this->get_compiled('langs')) > 0) {
            $ml = new stack_multilang();
            $selected = $ml->pick_lang($this->get_compiled('langs'));
            $statements[] = new stack_secure_loader('%_STACK_LANG:' .
                stack_utils::php_string_to_maxima_string($selected), 'language setting');
        }

        // First define the function that evaluates the question variables.
        // This comes from stateful_function_builder::question_variables.
        $statements[] = $this->get_compiled('qv');
        // Then call it.
        $statements[] = new stack_secure_loader(
            '_question_variables(RANDOM_SEED)', '');
        // Now call the initialisations for each variable.
        $vars = array();
        foreach ($this->variables as $statevar) {
            $var = stateful_state_carrier::make_from_teacher_source(
                $statevar->name . ':' . $statevar->initialvalue);
            $vars[$statevar->name] = $var;
            $statements[] = $var;
        }

        // Validate just in case. All the compiled bits have already been validated.
        // So we might skip this. But lets keep this short in this phase of development.
        foreach ($statements as $statement) {
            if ($statement->get_valid() !== true) {
                throw new stateful_exception(stateful_string(
                    'error_in_state_variable_initialisation'));
            }
        }

        $session = new stack_cas_session2($statements, $this->options, $this->
            seed);
        $session->errclass = 'stateful_cas_error';
        $session->instantiate();

        // Check for error.
        if ($session->get_errors() !== null && $session->get_errors() !== '') {            throw new stateful_exception(stateful_string(
                'error_in_state_variable_cas_initialisation', $session->get_errors()));
        }

        // Read in.
        foreach ($this->variables as $statevar) {
            $this->state->set($statevar->number, $vars[$statevar->name]->get_evaluated_state());
        }
        // Also the SCENE ones.
        $this->state->set(self::SCENE_PATH, '[]');
        $this->state->set(self::SCENE_CURRENT, stack_utils::
                php_string_to_maxima_string($this->entryscene));
    }

    private function init_from_state() {
        // Basic initialisation consists of initialisation of the inputs and rendering of the scene text.
        // For that we need to evaluate the teachers answers for each input and not much more.
        // But only for the active scene.
        // NOTE: after a scene transition this needs to be called again to initialise
        // the inputs of the new scene.
        $scene = $this->get_current_scene();



        // Build the input controller.
        $cache = $this->get_compiled('scene-' . $scene->name .
            '|io-cache');


        // Note that we use the cached JSON declarations instead of 
        // the objects. Also cache updates do not happen here.
        $this->inputs = stateful_input_controller::make_from_objects($scene->inputs, $scene->vboxes, $cache);

        $this->security = new stack_cas_security($this->has_units(), '', '', $this->get_compiled('forbiddenkeys'));


        $statements = [];
        if (is_numeric($this->seed) || is_integer($this->seed)) {
            $statements[] = new stack_secure_loader('RANDOM_SEED:' . $this->seed, 'init_from_state', $this->security);
        } else {
            $statements[] = stack_ast_container_silent::make_from_teacher_source('RANDOM_SEED:' . $this->seed, 'init_from_state', $this->security);
        }

        // If we are using localisation we should tell the CAS side logic about it.
        // For castext rendering and other tasks.
        if (count($this->get_compiled('langs')) > 0) {
            $ml = new stack_multilang();
            $selected = $ml->pick_lang($this->get_compiled('langs'));
            $statements[] = new stack_secure_loader('%_STACK_LANG:' .
                stack_utils::php_string_to_maxima_string($selected), 'language setting');
        }

        // 1. We need the question variables for this seed.
        $statements[] = $this->get_compiled('qv');
        // Then call it.
        $statements[] = new stack_secure_loader(
            '_question_variables(RANDOM_SEED)', '');

        // 2. Add the state into it.
        foreach ($this->variables as $statevar) {
            $statements[] = stateful_state_carrier::make_from_teacher_source(
                $statevar->name . ':' . $this->state->get($statevar->number));
        }
        $statements[] = stack_ast_container_silent::make_from_teacher_source(
            'SCENE_CURRENT:' . $this->state->get(self::SCENE_CURRENT));
        $statements[] = stack_ast_container_silent::make_from_teacher_source(
            'SCENE_PATH:' . $this->state->get(self::SCENE_PATH));

        // 3. We also need the scene variables for this seed and state.
        $svfunction = $this->get_compiled('scene-' . $scene->name .
            '-variables');
        $statements[] = $svfunction;
        // And to call it we need its signature.
        $statements[] = new stack_secure_loader(explode(':=', $svfunction->
            get_evaluationform())[0], '');

        // 4. Scene-text. and the model solution.
        $scenetext = $this->get_compiled('scene-' . $scene->name . '-text');
        $statements[] = $scenetext;
        $modelsolution = $this->get_compiled('modelsolution');
        $statements[] = $modelsolution;

        // 5. Input initialisation. Note that the input controller handles all.
        $statements = array_merge($statements, $this->inputs->collect_initialisation_statements());


        // Note that as nothing changes the state here we do not output it and
        // store it after instanttiation. That happens only when we A) initialise
        // state variables, or B) process input.

        // Validate just in case. All the compiled bits have already been validated.
        // So we might skip this. But lets keep this short in this phase of development.
        // TODO: build a non validating version of cassession that can be given such
        // already validated things to evaluate, maybe one with better control on what
        // is outputted, the old one outputs quite a lot of things that are not used.
        
        foreach ($statements as $statement) {
            if ($statement->get_valid() !== true) {
                throw new stateful_exception(stateful_string(
                    'error_in_initialisation'));
            }
        }

        // Instantiate
        $session = new stack_cas_session2($statements, $this->options, $this
            ->seed);
        $session->errclass = 'stateful_cas_error';
        $session->instantiate();

        // Check that error.
        if ($session->get_errors() !== null && $session->get_errors() !== '') {
            throw new stateful_exception(stateful_string(
                'error_in_cas_initialisation', $session->get_errors()));
        }

        // Give the input initialisation results to the inputs.
        // Earlier we gave them to them only at the time of validation
        // but as some of the results affect the expected-data it became
        // difficult to have the correct expectations for selecting 
        // what to give to the validation.
        $this->inputs->deliver_initialisation_results();

        $this->sceneinitialised = true;
        $this->lastsceneinited = $scene->name;

        // Take in the scene-text.
        $this->scenetext = $scenetext->get_rendered($this->castextprocessor);
        $this->modelsolution = $modelsolution;
    }

    // All things should exist in compiled form. But this might be some exotic situation.
    // As exotic as the first execution... Also does STACK validation disabling at the same time.
    // Public because testing uses this.
    public function get_compiled(string $thing) {
        global $DB;
        // If we have cached stuff but still in JSON form.
        if (is_string($this->compiledcache) && $this->compiledcache !== '') {
            $this->compiledcache = json_decode($this->compiledcache, true);
        } else if ($this->compiledcache === null || $this->compiledcache === ''
        ) {
            $this->compiledcache = [];
        }

        // Do we have that particular thing in the cache?
        if (!array_key_exists($thing, $this->compiledcache)) {
            $errs = stateful_handling_validation::check_compile($this);

            if (!$errs['result']) {
                throw new stateful_exception(stateful_string('compilation_error'));
            }

            if (is_integer($this->id) || is_numeric($this->id)) {
                // Save to DB. If the question is there.
                $sql =

  'UPDATE {qtype_stateful_options} SET compiledcache = ? WHERE questionid = ?';
                $params[] = json_encode($this->compiledcache);
                $params[] = $this->id;
                $DB->execute($sql, $params);

                // Invalidate the question definition cache.
                cache::make('core', 'questiondata')->delete($this->id);
            }
        }

        // Some non casstring cases.
        if ($thing === 'forbiddenkeys' || $thing === 'random' 
            || substr($thing, -strlen('|inputs')) === '|inputs' 
            || substr($thing, -strlen('|io-cache')) === '|io-cache'
            || $thing === 'langs') {
            return $this->compiledcache[$thing];
        }

        // CASText
        if ((strpos($thing, 'scene-') === 0 && substr($thing, -strlen('-text'))
            === '-text') || $thing === 'modelsolution' || strpos($thing, 'td-') === 0) {
            return castext2_evaluatable::make_from_compiled($this->compiledcache[$thing], 'scenetext', $this->get_compiled('static-castext-strings'));
        }

        // Static string replacements.
        if ($thing === 'static-castext-strings') {
            if (is_array($this->compiledcache[$thing])) {
                $this->compiledcache[$thing] = new castext2_static_replacer($this->compiledcache[$thing]);
            } 
            return $this->compiledcache[$thing];
        }


        $cs = new stack_secure_loader($this->compiledcache[$thing], '');

        return $cs;
    }

    public function compiled_dump(): string{
        // This is very much a debug function. Do not use this for anything.
        $out = '<table><tr><th>key</th><th>value</th></tr>';

        $out .= '<tr><td>qv</td><td>' . $this->get_compiled('qv')->
            get_evaluationform() . '</td></tr>';

        $out .= '<tr><td>forbiddenkeys</td><td>' . implode(',', array_keys(
            $this->get_compiled('forbiddenkeys'))) . '</td></tr>';

        foreach ($this->scenes as $scene) {
            $key = 'scene-' . $scene->name . '-variables';
            $out .= '<tr><td>' . $key . '</td><td><pre>' . stateful_utils::
                pretty_print_maxima($this->get_compiled($key)

                                          ->get_evaluationform()) .
                '</pre></td></tr>';
            $key = 'scene-' . $scene->name . '-textvariables';
            $out .= '<tr><td>' . $key . '</td><td><pre>' . stateful_utils::
                pretty_print_maxima($this->get_compiled($key)

                                          ->get_evaluationform()) .
                '</pre></td></tr>';
            foreach ($scene->prts as $prt) {
                $key = 'scene-' . $scene->name . '-prt-' . $prt->name;
                $out .= '<tr><td>' . $key . '</td><td><pre>' . stateful_utils::
                    pretty_print_maxima($this->get_compiled($key)

                                              ->get_evaluationform()) .
                    '</pre></td></tr>';
                $key = 'scene-' . $scene->name . '-prt-' . $prt->name .
                    '|inputs';
                $out .= '<tr><td>' . $key . '</td><td>' . implode(',',
                    array_keys($this->get_compiled($key))) . '</td></tr>';
            }
        }

        $out .= '</table>';

        return $out;
    }


    // Used to decide whether this question should load units into it and
    // whether they should be used in security scopes.
    // In the future maybe use annotations to control this and select the type 
    // of units or add new units.
    public function has_units(): bool {
        foreach ($this->scenes as $scene) {
            foreach ($scene->inputs as $input) {
                if ($input->get_type() === 'units') {
                    return true;
                } else if ($input->get_type() === 'matrix') {
                    // The new complication. Units in data-mode.
                    if ($input->get_option('matrix-mode') === 'data') {
                        foreach ($input->get_option('matrix-columns') as $coldata) {
                            if (isset($coldata['type']) && $coldata['type'] === 'unit') {
                                return true;
                            }
                        }
                    }
                }
            }
            foreach ($scene->prts as $prt) {
                foreach ($prt->nodes as $node) {
                    if (strpos($node->test, 'Units') === 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }


    public function get_expected_data() {
        // We may need to init the input-controller.
        $scene = $this->get_current_scene();
        if ($this->lastsceneinited !== $scene->name) {
            $this->init_from_state();
        }

        $expected = $this->inputs->collect_expected_data();

        // If this the teachers test mode we also need to bring overriding settings.
        if (strpos($_SERVER['PHP_SELF'], 'question/preview.php') !== false) {
            $expected['testCaseFill'] = PARAM_RAW;
            $expected['testCaseDisable'] = PARAM_RAW;
        }   

        return $expected;
    }

    // Renders a given bit of text if available. The additonal params are for dealing
    // with PRT feedback rendering before grading...
    public function render(
        string $what,
        $params = null
    ): string{
        $scene = $this->get_current_scene();

        if ('scenetext' === $what) {
            if ($this->lastsceneinited !== $scene->name) {
                $this->init_from_state();
            }
            return $this->scenetext;
        }
        if ('modelsolution' === $what) {
            if ($this->modelsolution === null) {
                $this->init_from_state();   
            }
            if ($this->modelsolution instanceof castext2_evaluatable) {
                // No need to render twice if ever.
                $this->modelsolution = $this->modelsolution->get_rendered($this->castextprocessor);
            }
            return $this->modelsolution;
        }

        // Common statements.
        $statements = [];
        if (is_numeric($this->seed) || is_integer($this->seed)) {
            $statements[] = new stack_secure_loader('RANDOM_SEED:' . $this->seed, 'render ' . $what, $this->security);
        } else {
            $statements[] = stack_ast_container_silent::make_from_teacher_source('RANDOM_SEED:' . $this->seed, 'render ' . $what, $this->security);
        }
        // If we are using localisation we should tell the CAS side logic about it.
        // For castext rendering and other tasks.
        if (count($this->get_compiled('langs')) > 0) {
            $ml = new stack_multilang();
            $selected = $ml->pick_lang($this->get_compiled('langs'));
            $statements[] = new stack_secure_loader('%_STACK_LANG:' .
                stack_utils::php_string_to_maxima_string($selected), 'language setting');
        }

        // Note that even for feedback we use the state variables that we have
        // after processing as feedback is only displayed when no state transfer
        // has happened and thus we do not need to access the variables from
        // before.
        foreach ($this->variables as $statevar) {
            $statements[] = stateful_state_carrier::make_from_teacher_source(
                $statevar->name . ':' . $this->state->get($statevar->number));
        }
        $statements[] = stack_ast_container_silent::make_from_teacher_source(
            'SCENE_CURRENT:' . $this->state->get(self::SCENE_CURRENT));
        $statements[] = stack_ast_container_silent::make_from_teacher_source(
            'SCENE_PATH:' . $this->state->get(self::SCENE_PATH));
        $statements[] = new stack_secure_loader('SCENE_NEXT:false');

        if (strpos($what, 'prt-') === 0) {
            // Key format is 'prt-$name' without the sequence number...
            // Stored format is very different if it even exists.
            $name  = mb_substr($what, 4);
            $prtid = 'prt-result-' . $this->get_scene_sequence_number($this->
                state) . '-' . $name;

            if (array_key_exists($prtid, $this->casparams)) {
                if ($this->casparams[$prtid] === 'NOEVAL') {
                    // No eval case.
                    return '';
                }
                $ast = maxima_parser_utils::parse($this->casparams[
                    $prtid]);

                // We have stuff from the evaluation logic.
                return castext2_parser_utils::postprocess_mp_parsed($ast->items
                    [0]->statement->items[4]);
            } else {
                // There is no data. Due to things happening in the wrong order or
                // to a different instance of this question...
                // Lets prime the caches. An try again.
                $r = $this->process_input($params, true);
                if (!array_key_exists($name, $r['_feedback'])) {
                    return '';
                }

                return castext2_parser_utils::postprocess_mp_parsed($r[
                    '_feedback'][$name]);
            }

            // Nothing for wrong name/sequence.
            return '';
        } else if (strpos($what, 'td-') === 0) {
            $ct = $this->get_compiled($what);
            // Add some context.
            // The question-variables.
            $statements[] = $this->get_compiled('qv');
            // Then call them.
            $statements[] = new stack_secure_loader('_question_variables(RANDOM_SEED)', 'text_download');
            // Then add scene variables.
            $statements[] = new stack_secure_loader('_question_variables(RANDOM_SEED)', 'text_download');
            // And the scene variables.
            $svfunction = $this->get_compiled('scene-' . $scene->name .
                '-variables');
            $statements[] = $svfunction;
            // And to call it we need its signature.
            $statements[] = new stack_secure_loader(explode(':=', $svfunction->
                get_evaluationform())[0], 'text_download');

            $statements[] = $ct;
            $session = new stack_cas_session2($statements, $this->options, $this->seed);
            $session->errclass = 'stateful_cas_error';
            $session->instantiate();
            return $ct->get_rendered($this->castextprocessor);
        }

        return 'NO RENDER OUTPUT FOR ' . $what;
    }

    // Returns the evaluated teachers answer for a given input name.
    // Only works for correct call sequence.
    public function get_ta(
        string $name,
        string $default = ''
    ): string {
        if (isset($this->casparams['input-ta']) && isset($this->casparams[
            'input-ta'][$name])) {
            return $this->casparams['input-ta'][$name];
        }
        return $default;
    }

    // Returns the inputs of the active scene.
    public function get_inputs(): array{
        $scene = $this->get_current_scene();

        // We need to ensure that those inputs have been initialised.
        if ($this->lastsceneinited !== $scene->name) {
            $this->init_from_state();
        }

        return $scene->inputs;
    }

    // Returns the PRTs of the active scene.
    public function get_prts(): array{
        $scene = $this->get_current_scene();
        return $scene->prts;
    }

    // TODO: Do we use the sensibly named question interface?
    public function get_correct_response() {
        return array();

        // TODO: whine to Tim about the way the prevview does not behave like the engine
        // and the way the engine drops objects it could reuse in so many places not to 
        // mention the bits where it skipps the selected classes and uses base classes
        /*
        if ($this->state === null) {
            // We cannot answer this question before we get state. Yet the preview thing 
            // assumes we can.
            return array('   foo' => 'bar');
        }

        $scene = $this->get_current_scene();
        $teacheranswer = array();
        foreach ($scene->inputs as $name => $input) {
            $teacheranswer = array_merge($teacheranswer,
                    $input->get_correct_response($this->casparams['input-ta'][$name]));
        }
        return $teacheranswer;
        */
    }

    public function get_validation_error(array $response) {
        // TODO: Talk about cases where there is not enough input for any PRT.
        return '';
    }

    public function grade_response(array $response) {
        // TODO: Provide some way for this to work.
        return [];
    }

    public function get_hint(
        $hintnumber,
        question_attempt $qa
    ) {
        // There are no hints. IF someone wants them they can use state to
        // decide what to show and when.
        return null;
    }

    public function get_right_answer_summary() {
        // Sure we know the correct chain of responses but how to return that...
        // So lets give up.
        return null;
    }

    public function is_gradable_response(array $response) {
        return count($response) > 0;
    }

    public function is_complete_response(array $response) {
        return $this->is_in_end_scene();
    }

    public function is_same_response(
        array $prevresponse,
        array $newresponse
    ) {
        // TODO: Cannot do this without knowing the state ...
        return false;
    }

    public function summarise_response(array $response) {
        return '';
    }

    public function un_summarise_response(string $summary) {
        return array();
    }

    public function classify_response(array $response) {
        return '';
    }

    public function get_model_type(): string {
        return 'question';
    }

    public function get_num_variants() {
        if ($this->get_compiled('random') === false) {
            return 1;
        }

        if ($this->variants === '' || $this->variants === '[]' || $this->variants === '{}' || $this->variants === null) {
            return 1000000;
        }
        $variants = json_decode($this->variants, true);
        
        if (isset($variants['_set']) && isset($variants[$variants['_set']])) {
            return count($variants[$variants['_set']]);
        }
        if (isset($variants['_set']) && $variants['_set'] === '') {
            return 1;
        }
        return 1000000;
    }

    public function get_expected_sequence_length(): ?int {
        if ($this->parlength === null || $this->parlength == -1) {
            return null;
        }
        return $this->parlength;
    }

    /**
     * Moodle specific acessor for question capabilities.
     */
    public function has_cap(string $capname): bool {
        return $this->has_question_capability($capname);
    }

    protected function has_question_capability($type) {
        global $USER;
        $context = $this->get_context();
        return has_capability("moodle/question:{$type}all", $context) ||
                ($USER->id == $this->createdby && has_capability("moodle/question:{$type}mine", $context));
    }

    public function get_context() {
        return context::instance_by_id($this->contextid);
    }
}
