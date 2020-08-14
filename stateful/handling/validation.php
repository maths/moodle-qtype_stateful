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
require_once __DIR__ . '/../core/functionbuilder.class.php';
require_once __DIR__ . '/../castext2/utils.php';
require_once __DIR__ . '/../answertests/answertest.factory.php';
require_once __DIR__ . '/../input2/input.controller.php';

// As validation is of somewhat high importance this class collects all things related to it
class stateful_handling_validation {

    /**
     * Takes a question and returns an array with errors/warnings and a simple result boolean.
     */
    public static function validate(qtype_stateful_question $question): array{
        $r = ['result' => true, 'errors' => [], 'warnings' => []
        ];

        $r = self::check_migrations($question, $r);
        $r = self::check_inputs($question, $r);
        $r = self::check_compile($question, $r);
        $r = self::check_prts($question, $r);
        $r = self::check_model_solution($question, $r);
        $r = self::check_question_note($question, $r);

        return $r;
    }

    public static function check_migrations(qtype_stateful_question $question,
        array &$result = []): array{
        return $result;
    }


    private static function recurse_nodes(
        $node,
        $path
    ) {
        if (array_key_exists($node->name, $path)) {
            return false;
        }
        $path[$node->name] = true;
        $both              = true;
        if ($node->truenext !== null && $node->truenext !== '' && !
            (strpos($node->truenext, '$SCENE:') === 0 || strpos(
                $node->truenext, '$SVAR:') === 0)) {
            $both = $both && self::recurse_nodes($node->prt->nodes[$node->
                truenext],
                $path);
        }
        if ($node->falsenext !== null && $node->falsenext !== '' &&
            !(strpos($node->falsenext, '$SCENE:') === 0 || strpos(
                $node->falsenext, '$SVAR:') === 0)) {
            $both = $both && self::recurse_nodes($node->prt->nodes[$node->
                falsenext],
                $path);
        }
        return $both;
    }

    public static function check_prts(
        qtype_stateful_question $question,
        array
         &$result = ['result' => 'true', 'errors' => [],
            'warnings' => []]
    ): array{

        foreach ($question->scenes as $scene) {
            $inputs = $scene->get_input_definition();
            foreach ($scene->prts as $prt) {
                if (trim($prt->name) === '') {
                    $result['result']   = 'false';
                    $result['errors'][] = ['path' => self::path_creator
                        ($prt, 'name'), 'message' =>
                        stateful_string(
                            'prt_name_must_be_non_empty')
                    ];
                    // No point to continue.
                    return $result;
                }

                if ($prt->scoremode === 'bestn') {
                    if (trim($prt->scoremodeparameters) !== '' &&
                        ctype_digit(trim($prt->scoremodeparameters))) {
                        $i = intval(trim($prt->scoremodeparameters));
                        if ($i <= 0) {
                            $result['result']   = 'false';
                            $result['errors'][] = ['path' => self::path_creator
                                ($prt, 'scoremodeparameters'), 'message' =>
                                stateful_string(
                                    'scoremodeparameters_bestn_more_than_zero')
                            ];
                        }
                    } else {
                        $result['result']   = 'false';
                        $result['errors'][] = ['path' => self::path_creator(
                            $prt, 'scoremodeparameters'), 'message' =>
                            stateful_string(
                                'scoremodeparameters_bestn_integer')];
                    }
                }

                if (!is_numeric($prt->value) || $prt->value < 0) {
                    $result['result']   = 'false';
                    $result['errors'][] = ['path' => self::path_creator(
                        $prt, 'value'), 'message' => stateful_string(
                        'prt_value_numeric')];
                }

                if ($prt->feedbackvariables !== null && trim($prt->
                    feedbackvariables) !== '') {
                    $ast = null;
                    // One could use the keyval class but we wish to have more control
                    try {
                        $ast = maxima_parser_utils::parse($prt->
                            feedbackvariables);
                    } catch (Exception $e) {
                        $result['result']   = 'false';
                        $result['errors'][] = ['path' => self::path_creator(
                            $prt, 'feedbackvariables'), 'message' => $e->
                            getMessage()];
                    }
                    if ($ast !== null) {
                        foreach ($ast->items as $statement) {
                            if (is_a($statement, 'MP_Statement')) {
                                $cs = stack_ast_container::make_from_teacher_source($statement->
                                    toString(), '', new stack_cas_security());
                                if (!$cs->get_valid()) {
                                    $result['result'] = 'false';
                                    foreach ($cs->get_errors(true) as $err) {
                                        $result['errors'][] = ['path' => self::
                                                path_creator(
                                                $prt, 'feedbackvariables'),
                                            'message' => $err];
                                    }
                                }
                            }
                        }
                    }
                }
                foreach ($prt->nodes as $node) {
                    $test = stateful_answertest_factory::get($node->test);
                    if ($test === null) {
                        $result['result']   = 'false';
                        $result['errors'][] = ['path' => self::
                                path_creator(
                                $node, 'test'),
                            'message' => stateful_string('unknown_aswertest', $node->test)];
                    } else {
                        $err = $test->validate($node->sans, $node->tans, $node
                                ->options, $inputs);
                            foreach ($err as $key => $error) {
                            $result['result']   = 'false';
                            $result['errors'][] = ['path' => self::
                                    path_creator(
                                    $node, $key),
                                'message' => $error];
                        }
                    }
                    if ($node->truefeedback !== null && trim($node->
                        truefeedback) !== '') {
                        $css = castext2_parser_utils::get_casstrings($node->
                            truefeedback);
                        foreach ($css as $cs) {
                            if (!$cs->get_valid()) {
                                $result['result'] = 'false';
                                foreach ($cs->get_errors(true) as $err) {
                                    $result['errors'][] = ['path' => self::
                                            path_creator(
                                            $node, 'truefeedback'),
                                        'message' => $err];
                                }
                            }
                        }
                    }
                    if ($node->falsefeedback !== null && trim($node->
                        falsefeedback) !== '') {
                        $css = castext2_parser_utils::get_casstrings($node->
                            falsefeedback);
                        foreach ($css as $cs) {
                            if (!$cs->get_valid()) {
                                $result['result'] = 'false';
                                foreach ($cs->get_errors(true) as $err) {
                                    $result['errors'][] = ['path' => self::
                                            path_creator(
                                            $node, 'falsefeedback'),
                                        'message' => $err];
                                }
                            }
                        }
                    }
                    if ($node->truenext !== null && $node->truenext !== '' &&
                        $node->truevariables !== null && trim($node->
                            truevariables) !== '') {
                        if (strpos($node->truenext, '$SCENE:') === 0 || strpos(
                            $node->truenext, '$SVAR:') === 0) {
                            try {
                                $ast = maxima_parser_utils::parse($node->
                                    truevariables);
                            } catch (Exception $e) {
                                $result['result']   = 'false';
                                $result['errors'][] = ['path' => self::
                                        path_creator(
                                        $node, 'truevariables'), 'message' =>
                                    $e->
                                    getMessage()];
                            }
                            if ($ast !== null) {
                                foreach ($ast->items as $statement) {
                                    if (is_a($statement, 'MP_Statement')) {
                                        $cs = $cs = stack_ast_container::make_from_teacher_source($statement->
                                    toString(), '', new stack_cas_security());
                                        if (!$cs->get_valid()) {
                                            $result['result'] = 'false';
                                            foreach ($cs->get_errors(true) as
                                                $err) {
                                                $result['errors'][] = ['path'
                                                                       => self::
                                                        path_creator(
                                                        $node, 'truevariables')
                                                    ,
                                                    'message' => $err];
                                            }
                                        }
                                    }
                                }
                            }

                        }
                    }
                    if ($node->falsenext !== null && $node->falsenext !== '' &&
                        $node->falsevariables !== null && trim($node->
                            falsevariables) !== '') {
                        if (strpos($node->falsenext, '$SCENE:') === 0 || strpos
                            ($node->falsenext, '$SVAR:') === 0) {
                            try {
                                $ast = maxima_parser_utils::parse($node->
                                    falsevariables);
                            } catch (Exception $e) {
                                $result['result']   = 'false';
                                $result['errors'][] = ['path' => self::
                                        path_creator(
                                        $node, 'falsevariables'), 'message' =>
                                    $e->
                                    getMessage()];
                            }
                            if ($ast !== null) {
                                foreach ($ast->items as $statement) {
                                    if (is_a($statement, 'MP_Statement')) {
                                        $cs = stack_ast_container::make_from_teacher_source($statement->
                                    toString(), '', new stack_cas_security());
                                        if (!$cs->get_valid()) {
                                            $result['result'] = 'false';
                                            foreach ($cs->get_errors(true) as
                                                $err) {
                                                $result['errors'][] = ['path'
                                                                       => self::
                                                        path_creator(
                                                        $node, 'falsevariables'
                                                    )
                                                    ,
                                                    'message' => $err];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Check for loops.

                if (!self::recurse_nodes($prt->root, [])) {

                    $result['errors'][] = ['path' => self::
                            path_creator(
                            $prt, 'generic')
                        ,
                        'message' => stateful_string('cyclic_prt')];
                }
            }
        }

        return $result;
    }

    public static function check_inputs(qtype_stateful_question $question,
        array &$result = ['result' => 'true', 'errors' => [],
            'warnings' => []]): array{
        foreach ($question->scenes as $scene) {
            $i = 0;
            foreach ($scene->inputs as $input) {
                if ($input instanceof stateful_input_options || $input instanceof stateful_input_teachers_answer_handling) {
                    $err = $input->validate_options();
                    foreach ($err as $key => $errs) {
                        if ($key === '') {
                            // Still some errors in the schema-validator
                            continue;
                        }
                        $result['result'] = 'false';
                        // Now that inputs in input2 do not know their scene... We need to build the paths by hand.
                        $path = self::path_creator($scene, 'input|\\|' . $i . '|\\|');
                        if ($key !== 'tans') {
                            $path = $path . 'options|' . $key;
                        } else {
                            $path = $path . 'tans';
                        }
                        foreach ($errs as $error) {
                            $result['errors'][] = ['path' => $path,
                                'message' => $error];           
                        }
                    }
                }
                if (trim($input->get_name()) === '') {
                   $path = self::path_creator($scene, 'input|\\|' . $i . '|\\|name'); 
                   $result['result'] = 'false';
                   $result['errors'][] = ['path' => $path,
                                'message' => stateful_string('input_name_must_be_non_empty')];
                }
                $i = $i + 1;
            }
        }
        return $result;
    }

    public static function check_model_solution(
        qtype_stateful_question
         $question,
        array &$result = ['result' => 'true', 'errors' => [],
            'warnings' => []]
    ): array{
        // TODO, check that the model solution is valid as it is not part of cached logic.
        return $result;
    }

    public static function check_question_note(
        qtype_stateful_question
         $question,
        array &$result = ['result' => 'true', 'errors' => [],
            'warnings' => []]
    ): array{
        // TODO, check that the question note is valid as it too is not part of cached logic.
        return $result;
    }

    public static function check_compile(
        qtype_stateful_question $question,
        array &$result = ['result' => 'true', 'errors' => [],
            'warnings' => []
        ]): array{

        $question->compiledcache = [];
        $forbiddenkeys           = [];

        $question->compiledcache['random'] = false;
        $sec = new stack_cas_security();

        try {
            // The question variables.
            $question->compiledcache['qv'] = stateful_function_builder::
                question_variables($question);
            $kv = new stack_cas_keyval($question->questionvariables);
            foreach ($kv->get_session()->get_session() as $cs) {
                $key = $cs->get_key();
                if ($key !== '' && $key !== null) {
                    $forbiddenkeys[$key] = true;
                }
            }
            $usage = $kv->get_variable_usage();
            if (isset($usage['calls'])) {
                foreach ($usage['calls'] as $key => $value) {
                    if ($sec->get_feature($key, 'random') === true) {
                        $question->compiledcache['random'] = true;
                    }
                }
            }
        } catch (stateful_exception $e) {
            if ($e->type === 'validity_premature_state_var') {
                $result['result']   = false;
                $result['errors'][] = ['section' => 'questionvariables',
                    'message' => $e->getMessage(), 'path' => self::path_creator
                    ($question, 'questionvariables')];
            } else if ($e->type === 'validity_cas_invalid') {
                $result['result']   = false;
                $result['errors'][] = ['section' => $e->position['section'
                ], 'message' => $e->getMessage(), 'position' => $e->position,
                    'path' => self::path_creator($question, 'questionvariables'
                    )];
            } else {
                // TODO: make absolutely sure we never get here.
                error_log($e->getMessage());
                $result['result'] = false;
                // No section or finer resolution data provided for exceptions of unknown type.
                $result['errors'][] = ['message' => $e->getMessage(), 'path' =>
                    self::path_creator($question, 'questionvariables')];
            }
        } catch (Exception $ee) {
            // Should something new fail or maybe STACK exceptions...
            $result['result'] = false;
            // No section or finer resolution data provided for exceptions of unknown type.
            $result['errors'][] = ['message' => $ee->getMessage(), 'path' =>
                self::path_creator($question, 'questionvariables')];
        }

        // All scene variables.
        foreach ($question->scenes as $scene) {
            if (trim($scene->name) === '') {
                $result['result'] = false;
                $result['errors'][] = ['message' => stateful_string('scene_name_must_be_non_empty'), 'path' =>
                self::path_creator($scene, 'name')];       
                // No sense in continuing.
                return $result;
            }
            try {
                $question->compiledcache['scene-' . $scene->name . '-variables'
                ] =
                stateful_function_builder::scene_variables($scene);
            } catch (stateful_exception $e) {
                if ($e->type === 'validity_overwrite_question_var') {
                    $result['result']   = false;
                    $result['errors'][] = ['section' => $e->position[
                        'section'], 'message' => $e->getMessage(), 'position'
       => $e->position, 'path' => self::path_creator($scene, 'scenevariables')];
                } else if ($e->type === 'validity_scene_var_state_var') {
                    $result['result']   = false;
                    $result['errors'][] = ['section' => $e->position[
                        'section'], 'message' => $e->getMessage(), 'position'
       => $e->position, 'path' => self::path_creator($scene, 'scenevariables')];
                } else if ($e->type === 'validity_cas_invalid') {
                    $result['result']   = false;
                    $result['errors'][] = ['section' => $e->position[
                        'section'], 'message' => $e->getMessage(), 'position'
       => $e->position, 'path' => self::path_creator($scene, 'scenevariables')];
                } else {
                    // TODO: make absolutely sure we never get here.
                    error_log($e->getMessage());
                    $result['result'] = false;
                    // No section or finer resolution data provided for exceptions of unknown type.
                    $result['errors'][] = ['message' => $e->getMessage(),
                        'path' => self::path_creator($scene, 'scenevariables')]
                    ;
                }
            } catch (Exception $ee) {
                // Should something new fail or maybe STACK exceptions...
                $result['result'] = false;
                // No section or finer resolution data provided for exceptions of unknown type.
                $result['errors'][] = ['message' => $e->getMessage(), 'path' =>
                    self::path_creator($scene, 'scenevariables')];
            }

            // Also the code scenetext.
            try {
                $question->compiledcache['scene-' . $scene->name .
                    '-text'] =
                castext2_parser_utils::compile($scene->scenetext);
            } catch (Exception $ee) {
                $result['errors'][] = ['message' => $ee->getMessage(), 'path'
                                                                             =>
                    self::path_creator($scene, 'scenetext')];
            }

            // All the PRT-logic.
            foreach ($scene->prts as $prt) {
                try {
                    $question->compiledcache['scene-' . $scene->name . '-prt-'
                        . $prt->name] =
                    stateful_function_builder::prt_logic($prt);
                } catch (stateful_exception $e) {
                    if ($e->type === 'validity_immutable_input') {
                        $result['result']   = false;
                        $result['errors'][] = ['section' => $e->position[
                            'section'], 'message' => $e->getMessage(), 'path'
                                        => self::path_creator($prt, 'generic')];
                    } else if ($e->type === 'answertest_error') {
                        $result['result'] = false;
                        // Error generated elsewhere. In the PRT-checks.
                    } else if ($e->type === 'scoring_error') {
                        $result['result']   = false;
                        $result['errors'][] = ['section' => $e->position[
                            'section'], 'message' => $e->getMessage(),
                            'position' => $e->position, 'path' => self::
                                path_creator($prt, 'generic')];
                    } else if ($e->type === 'validity_cas_invalid') {
                        $result['result']   = false;
                        $result['errors'][] = ['section' => $e->position[
                            'section'], 'message' => $e->getMessage(),
                            'position' => $e->position, 'path' => self::
                                path_creator($prt, 'generic')];
                    } else {
                        // TODO: make absolutely sure we never get here.
                        error_log($e->getMessage());
                        $result['result'] = false;
                        // No section or finer resolution data provided for exceptions of unknown type.
                        $result['errors'][] = ['message' => $e->getMessage
                            (), 'path' => self::path_creator($prt, 'generic')];
                    }
                } catch (Exception $ee) {
                    // Should something new fail or maybe STACK exceptions...
                    $result['result'] = false;
                    // No section or finer resolution data provided for exceptions of unknown type.
                    $result['errors'][] = ['message' => $ee->getMessage(),
                        'path' => self::path_creator($prt, 'generic')];
                }

                // Also store required inputs for the PRT.
                $inputs = [];
                $usage  = $prt->get_variable_usage();
                foreach ($scene->inputs as $name => $input) {
                    if (isset($usage['read'][$name])) {
                        $inputs[$name] = true;
                    }
                }
                $question->compiledcache['scene-' . $scene->name . '-prt-' .
                    $prt->name . '|inputs'] =
                    $inputs;
            }

            // And keys for forbiddenkeys.
            $kv = new stack_cas_keyval($scene->scenevariables, null, null, 't')
            ;
            foreach ($kv->get_session() as $cs) {
                $key = $cs->get_key();
                if ($key !== '' && $key !== null) {
                    $forbiddenkeys[$key] = true;
                }
            }
            $usage = $kv->get_variable_usage();
            if (isset($usage['calls'])) {
                foreach ($usage['calls'] as $key => $value) {
                    if ($sec->get_feature($key, 'random') === true) {
                        $question->compiledcache['random'] = true;
                    }
                }
            }

            // Condense input definitions.
            $iocache = [];
            if ($result['result']) {
                $ic = stateful_input_controller::make_from_objects($scene->inputs, $scene->vboxes, $iocache);
                $iocache = $ic->prime_cache();
            }

            $question->compiledcache['scene-' . $scene->name . '|io-cache'] = $iocache;
        }

        // The forbidden variablenames
        $question->compiledcache['forbiddenkeys'] = array_keys($forbiddenkeys);

        $gf = $question->generalfeedback;
        if ($gf === null) {
            $gf = '';
        }
        $question->compiledcache['modelsolution'] = castext2_parser_utils::compile($gf);

        return $result;
    }

    // Creates a simple identifier for any property of the question
    // only special features are the question->options and input->options
    // which need to be denoted with $field = 'options|' . $fieldname
    // These identifiers are recommended to be used in UI-work as they can
    // be reimplemented in whatever scripting is in use and can be used
    // to simplify things. Use 'generic' as the field if you cannot specify
    // where in the object the failure is.
    public static function path_creator(

        stateful_model $object,
        string $field,
        string $separator = '|\\|',
        bool $short = false

    ): string{
        // The default separator here is in use elsewhere, if changed must provide
        // API access to new one.

        // TODO: handle tests...
        // TODO: are input2 things stateful_models?
        // This is one of those places where the order of objects matters.
        $r = [];
        switch ($object->get_model_type()) {
        case 'scene':
            if ($short) {
                $r[] = 's';
            } else {
                $r[] = 'scene';
            }
            $r[] = array_search($object->name, array_keys($object->question->
                scenes));
            break;
        case 'prt':
            if ($short) {
                $r[] = 's';
            } else {
                $r[] = 'scene';
            }
            $r[] = array_search($object->scene->name, array_keys($object->scene
                                                                            ->
                question->scenes));
            if ($short) {
                $r[] = 'p';
            } else {
                $r[] = 'prt';
            }
            $r[] =
                array_search($object->name, array_keys($object->scene->prts)
            );
            break;
        case 'prtnode':
            if ($short) {
                $r[] = 's';
            } else {
                $r[] = 'scene';
            }$r[] = array_search($object->prt->scene->name, array_keys($object
                    ->
                prt->scene->question->scenes));
            if ($short) {
                $r[] = 'p';
            } else {
                $r[] = 'prt';
            }
            $r[] = array_search($object->prt->name, array_keys($object->prt->
                scene->prts));
            if ($short) {
                $r[] = 'n';
            } else {
                $r[] = 'node';
            }
            $r[] = array_search($object->name, array_keys($object->prt->nodes))
            ;
            break;

        case 'input':
            if ($short) {
                $r[] = 's';
            } else {
                $r[] = 'scene';
            }
            $r[] = array_search($object->scene->name, array_keys($object->scene
                                                                            ->
                question->scenes));
            if ($short) {
                $r[] = 'i';
            } else {
                $r[] = 'input';
            }
            $r[] =
                array_search($object->name, array_keys($object->scene->inputs)
            );
            break;
        case 'variable':
            if ($short) {
                $r[] = 'v';
            } else {
                $r[] = 'variable';
            }
            $r[] = array_search($object->name, array_keys($object->question->
                variables));
            break;
        default:
            // Something badly wrong.
            break;
        }

        $r[] = $field;

        return implode($separator, $r);
    }

    public static function convert_short_to_long_path(
        string $path,
        string $shortseparator = '/',
        string $longseparator = '|\\|'
    ): string{
        $tokens = explode($shortseparator, $path);
        if ($tokens[0] === 'v') {
            $tokens[0] = 'variable';
        } else if ($tokens[0] === 's') {
            $tokens[0] = 'scene';
        }
        if (count($tokens) > 2) {
            if ($tokens[2] === 'p') {
                $tokens[2] = 'prt';
                if (count($tokens) > 4) {
                    if ($tokens[4] === 'n') {
                        $tokens[4] = 'node';
                    }
                }
            } else if ($tokens[2] === 'i') {
                $tokens[2] = 'input';
            }
        }
        return implode($longseparator, $tokens);
    }

/* TODO: DO WE NEED THIS ON THIS SIDE OF WORLD? THE OTHER WAY
IS NEEDED FOR ADDRESSABLE ERROR MESSAGES BUT THIS?
public static function get_path(
stateful_question $question,
string $path,
string $separator = '|\\|'
): any {

return null;
}
 */
}