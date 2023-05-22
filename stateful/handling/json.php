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
// 
/* This tool takes a Stateful question and turns it into JSON or the other way around.*/

require_once __DIR__ . '/../../stacklib.php';
require_once __DIR__ . '/../../questiontype.php';
require_once __DIR__ . '/../core/statevariable.class.php';
require_once __DIR__ . '/../core/scene.class.php';
require_once __DIR__ . '/../core/prt.class.php';
require_once __DIR__ . '/../core/prt_node.class.php';
require_once __DIR__ . '/../input2/input.controller.php';
require_once __DIR__ . '/../answertests/answertest.factory.php';

require_once __DIR__ . '/../../../../../lib/filelib.php';

class stateful_handling_json {

    // NOTE: this does not try to merge to an existing one it creates a non
    // stored question with very little if any contextual data. i.e. add timestamps
    // and creator information manually.
    // Note that this process will load attached files to draft files if they are not
    // otherwise present.
    public static function from_json(string $json): qtype_stateful_question {
        return self::from_array(json_decode($json, true));
    }

    public static function from_array(array $data): qtype_stateful_question{
        $r = new qtype_stateful_question();
        $r->qtype = new qtype_stateful();

        // Some defaults. These are not null in database yet have no default there.
        $r->questiontext      = '';
        $r->generalfeedback   = '';
        $r->questionvariables = '';

        // These should be overwritten by the data so that we can see if there are
        // migrations to worry in validation.
        $pm                 = core_plugin_manager::instance();
        $r->statefulversion = $pm->get_plugin_info('qtype_stateful')->
            versiondisk;
        $r->stackversion = $pm->get_plugin_info('qtype_stack')->versiondisk;

        if (array_key_exists('id', $data)) {
            $r->id = $data['id'];
        }
        if (array_key_exists('category', $data)) {
            $r->category = $data['category'];
        }
        if (array_key_exists('questionbankentryid', $data)) {
            $r->questionbankentryid = $data['questionbankentryid'];
        }
        if (array_key_exists('name', $data)) {
            $r->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $r->questiontext       = $data['description'];
            $r->questiontextformat = FORMAT_HTML;
        }
        if (array_key_exists('pointvalue', $data)) {
            $r->defaultmark = $data['pointvalue'];
        }
        if (array_key_exists('parlength', $data)) {
            $r->parlength = $data['parlength'];
        } else {
            $r->parlength = -1;
        }
        if (array_key_exists('penalty', $data)) {
            $r->penalty = $data['penalty'];
        }
        if (array_key_exists('questionvariables', $data)) {
            $r->questionvariables = $data['questionvariables'];
        }
        if (array_key_exists('entryscenename', $data)) {
            // Better exist otherwise things will be broken...
            $r->entryscene = $data['entryscenename'];
        }
        if (array_key_exists('questionnote', $data)) {
            $r->questionnote = $data['questionnote'];
        }
        if (array_key_exists('variants', $data)) {
            $r->variants = json_encode($data['variants']);
        } else {
            $r->variants = '{}';
        }

        if (array_key_exists('meta', $data)) {
            $r->genericmeta = $data['meta'];
        } else {
            $r->genericmeta = [];
        }

        $r->options = new stack_options();
        $r->options->set_site_defaults();
        if (array_key_exists('options', $data)) {
            if (array_key_exists('assumepositive', $data['options'])) {
                $r->options->set_option('assumepos', $data['options'][
                    'assumepositive']);
            }
            if (array_key_exists('assumereal', $data['options'])) {
                $r->options->set_option('assumereal', $data['options'][
                    'assumereal']);
            }
            if (array_key_exists('complexno', $data['options'])) {
                $r->options->set_option('complexno', $data['options'][
                    'complexno']);
            }
            if (array_key_exists('multiplicationsign', $data['options'])) {
                $r->options->set_option('multiplicationsign', $data['options'][
                    'multiplicationsign']);
            }
            if (array_key_exists('sqrtsign', $data['options'])) {
                $r->options->set_option('sqrtsign', $data['options']['sqrtsign'
                ]);
            }
            if (array_key_exists('inversetrig', $data['options'])) {
                $r->options->set_option('inversetrig', $data['options'][
                    'inversetrig']);
            }
            if (array_key_exists('matrixparens', $data['options'])) {
                $r->options->set_option('matrixparens', $data['options'][
                    'matrixparens']);
            }
            if (array_key_exists('questionsimplify', $data['options'])) {
                $r->options->set_option('simplify', $data['options'][
                    'questionsimplify']);
            }
        }

        // Note that we drag along the version data we receive but at the moment we save
        // these will be set to our versions.
        if (array_key_exists('varsions', $data)) {
            if (array_key_exists('STACK', $data['versions'])) {
                $r->stackversion = $data['versions']['STACK'];
            }
            if (array_key_exists('Stateful', $data['versions'])) {
                $r->statefulversion = $data['versions']['Stateful'];
            }
        }

        $r->variables = [];

        if (array_key_exists('statevariables', $data)) {
            foreach ($data['statevariables'] as $value) {
                $sv           = new stateful_statevariable();
                $sv->question = $r;
                $sv->name     = $value['name'];
                if (array_key_exists('description', $value)) {
                    $sv->description = $value['description'];
                }
                if (array_key_exists('initialvalue', $value)) {
                    $sv->initialvalue = $value['initialvalue'];
                }
                if (array_key_exists('type', $value)) {
                    $sv->type = $value['type'];
                }
                if (array_key_exists('number', $value)) {
                    $sv->number = $value['number'];
                }
                $r->variables[$sv->name] = $sv;
            }
        }

        // Fill in numbers for state variable storage.
        $reservednumbers = [];
        if (array_key_exists('statevariablenumbers', $r->genericmeta)) {
            $reservednumbers = $r->genericmeta['statevariablenumbers'];
        }
        foreach ($r->variables as $name => $sv) {
            if (array_key_exists($name, $reservednumbers)) {
                $sv->number = $reservednumbers[$name];
            } else if (is_integer($sv->number)) {
                $reservednumbers[$name] = $sv->number;
            }
        }
        $i = 1;
        foreach ($r->variables as $name => $sv) {
            if (!is_integer($sv->number)) {
                // Fill in a number.
                $reverse = array_flip($reservednumbers);
                while (array_key_exists($i, $reverse)) {
                    $i = $i + 1;
                }
                $sv->number             = $i;
                $reservednumbers[$name] = $sv->number;
                $i                      = $i + 1;
            }
        }
        // Store that listing for editors that might not keep track.
        $r->genericmeta['statevariablenumbers'] = $reservednumbers;

        // Then the more problematic bits, which may have files.
        if (!array_key_exists('files', $data)) {
            $data['files'] = [];
        }

        if (array_key_exists('modelsolution', $data)) {
            $r->generalfeedback = self::inject_files($data['modelsolution'],
                $data['files'], $r->id, 'question', 'generalfeedback');
            $r->generalfeedbackformat = FORMAT_HTML;
        }

        $r->scenes = [];
        if (array_key_exists('scenes', $data)) {
            // Better exist otherwise things will be broken...
            foreach ($data['scenes'] as $value) {
                $scene = self::from_array_scene($value, $data['files'], $r->
                    options);
                $scene->question         = $r;
                $r->scenes[$scene->name] = $scene;
            }
        }

        return $r;
    }

    public static function from_array_scene(
        array $data,
        array &$files,
        stack_options $options
    ): stateful_scene{
        $r = new stateful_scene();

        $r->name = $data['name'];
        if ($r->name === null) {
            $r->name = '';
        }

        if (array_key_exists('description', $data)) {
            $r->description = $data['description'];
        }
        if (array_key_exists('scenevariables', $data)) {
            $r->scenevariables = $data['scenevariables'];
        }
        if ($r->scenevariables === null) {
            $r->scenevariables = '';
        }
        if ($r->scenetext === null) {
            $r->scenetext = '';
        }

        if (array_key_exists('scenetext', $data)) {
            if (isset($r->id) && $r->id !== null) {
                $r->scenetext = self::inject_files($data['scenetext'], $files, $r->
                    id, 'qtype_stateful', 'scenetext');
            } else {
                $r->scenetext = $data['scenetext'];
            }
        }

        $r->inputs = [];
        $r->prts   = [];
        $r->vboxes = [];

        if (array_key_exists('inputs', $data)) {
            foreach ($data['inputs'] as $value) {
                $opts = [] + $value;
                unset($opts['type']);
                unset($opts['tans']);
                unset($opts['name']);

                $name = '';
                if (isset($value['name']) && $value['name'] !== null) {
                    $name = $value['name'];
                }


                $input = stateful_input_controller::get_input_instance($value['type'], $name);

                if ($input instanceof stateful_input_teachers_answer_handling) {
                    $input->set_teachers_answer($value['tans']);
                }
                if ($input instanceof stateful_input_options) {
                    $input->set_options($opts);
                }

                $r->inputs[$value['name']] = $input;
            }
        }

        if (array_key_exists('vboxes', $data)) {
            foreach ($data['vboxes'] as $value) {
                $opts = [] + $value;
                unset($opts['type']);
                unset($opts['name']);

                $name = '';
                if (isset($value['name']) && $value['name'] !== null) {
                    $name = $value['name'];
                }

                $vbox = stateful_input_controller::get_validation_box_instance($value['type'], $name, $opts);

                $r->vboxes[$value['name']] = $vbox;
            }
        }

        if (array_key_exists('prts', $data)) {
            foreach ($data['prts'] as $value) {
                $prt                 = self::from_array_prt($value, $files);
                $prt->scene          = $r;
                $r->prts[$prt->name] = $prt;
            }
        }

        return $r;
    }

    public static function from_array_prt(
        array $data,
        array &$files
    ):
    stateful_prt{
        $r       = new stateful_prt();
        $r->name = $data['name'];
        if ($r->name === null) {
            $r->name = '';
        }

        if (array_key_exists('feedbackvariables', $data)) {
            $r->feedbackvariables = $data['feedbackvariables'];
        }
        if ($r->feedbackvariables == null) {
            $r->feedbackvariables = '';
        }
        if (array_key_exists('scoremode', $data)) {
            $r->scoremode = $data['scoremode'];
        }
        if (array_key_exists('scoremodeparameters', $data)) {
            $r->scoremodeparameters = $data['scoremodeparameters'];
        } else {
            $r->scoremodeparameters = '';
        }
        if (array_key_exists('value', $data)) {
            $r->value = $data['value'];
        }
        if (array_key_exists('scoremode', $data)) {
            $r->scoremode = $data['scoremode'];
        }

        $r->nodes = [];
        foreach ($data['nodes'] as $value) {
            $n       = new stateful_prt_node();
            $n->name = $value['name'];
            $n->prt  = $r;

            if (array_key_exists('test', $value)) {
                $n->test = $value['test'];
            }
            if (array_key_exists('sans', $value) && $value['sans'] !== null) {
                $n->sans = $value['sans'];
            } else {
                $n->sans = '';
            }
            if (array_key_exists('tans', $value)) {
                $n->tans = $value['tans'];
            } else {
                $n->tans = '';
            }
            if (array_key_exists('options', $value) && $value['options'] !==
                                                                         null) {
                $n->options = $value['options'];
            } else {
                $n->options = '';
            }
            if (array_key_exists('quiet', $value) && $value['quiet'] !== null)
                                                                               {
                $n->quiet = $value['quiet'];
            } else {
                $n->quiet = false;
            }

            if (array_key_exists('true', $value)) {
                if (array_key_exists('feedback', $value['true']) && $value[
                    'true']['feedback'] !== null) {
                    $n->truefeedback = self::inject_files($value['true'][
                        'feedback'], $files, $n->id, 'qtype_stateful',
                        'prtnodetruefeedback');
                } else {
                    $n->truefeedback = '';
                }
                if (array_key_exists('variables', $value['true']) && $value[
                    'true']['variables'] !== null) {
                    $n->truevariables = $value['true']['variables'];
                } else {
                    $n->truevariables = '';
                }
                if (array_key_exists('next', $value['true'])) {
                    $n->truenext = $value['true']['next'];
                } else {
                    $n->truenext = null;
                }
                if (array_key_exists('score', $value['true']) && $value['true']
                    ['score'] !== null) {
                    $n->truescore = $value['true']['score'];
                } else {
                    $n->truescore = '1';
                }
                if (array_key_exists('scoremode', $value['true']) && $value[
                    'true']['scoremode'] !== null) {
                    $n->truescoremode = $value['true']['scoremode'];
                } else {
                    $n->truescoremode = '=';
                }
                if (array_key_exists('penalty', $value['true']) && $value[
                    'true']['penalty'] !== null) {
                    $n->truepenalty = $value['true']['penalty'];
                } else {
                    $n->truepenalty = '';
                }
                if (array_key_exists('penaltymode', $value['true']) && $value[
                    'true']['penaltymode'] !== null) {
                    $n->truepenaltymode = $value['true']['penaltymode'];
                } else {
                    $n->truepenaltymode = '=';
                }
                if (array_key_exists('tests', $value['true']) && $value['true']
                    ['tests'] !== null) {
                    $n->truetests = $value['true']['tests'];
                } else {
                    $n->truetests = [];
                }
            } else {
                $n->truefeedback    = '';
                $n->truevariables   = '';
                $n->truenext        = null;
                $n->truescore       = '1';
                $n->truescoremode   = '=';
                $n->truepenalty     = '';
                $n->truepenaltymode = '=';
                $n->truetests       = [];
            }

            if (array_key_exists('false', $value)) {
                if (array_key_exists('feedback', $value['false']) && $value[
                    'false']['feedback'] !== null) {
                    $n->falsefeedback = self::inject_files($value['false'][
                        'feedback'], $files, $n->id, 'qtype_stateful',
                        'prtnodefalsefeedback');
                } else {
                    $n->falsefeedback = '';
                }
                if (array_key_exists('variables', $value['false']) && $value[
                    'false']['variables'] !== null) {
                    $n->falsevariables = $value['false']['variables'];
                } else {
                    $n->falsevariables = '';
                }
                if (array_key_exists('next', $value['false'])) {
                    $n->falsenext = $value['false']['next'];
                } else {
                    $n->falsenext = null;
                }
                if (array_key_exists('score', $value['false']) && $value[
                    'false']['score'] !== null) {
                    $n->falsescore = $value['false']['score'];
                } else {
                    $n->falsescore = '0';
                }
                if (array_key_exists('scoremode', $value['false']) && $value[
                    'false']['scoremode'] !== null) {
                    $n->falsescoremode = $value['false']['scoremode'];
                } else {
                    $n->falsescoremode = '=';
                }
                if (array_key_exists('penalty', $value['false']) && $value[
                    'false']['penalty'] !== null) {
                    $n->falsepenalty = $value['false']['penalty'];
                } else {
                    $n->falsepenalty = '';
                }
                if (array_key_exists('penaltymode', $value['false']) && $value[
                    'false']['penaltymode'] !== null) {
                    $n->falsepenaltymode = $value['false']['penaltymode'];
                } else {
                    $n->falsepenaltymode = '=';
                }
                if (array_key_exists('tests', $value['false']) && $value[
                    'false']['tests'] !== null) {
                    $n->falsetests = $value['false']['tests'];
                } else {
                    $n->falsetests = [];
                }
            } else {
                $n->falsefeedback    = '';
                $n->falsevariables   = '';
                $n->falsenext        = null;
                $n->falsescore       = '0';
                $n->falsescoremode   = '=';
                $n->falsepenalty     = '';
                $n->falsepenaltymode = '=';
                $n->falsetests       = [];
            }

            $r->nodes[$n->name] = $n;
        }

        if (array_key_exists('root', $data)) {
            $r->root = $r->nodes[$data['root']];
        }
        return $r;
    }

    public static function to_json(
        qtype_stateful_question $question,
        bool
         $with_attachments
    ): string{
        // We need to do something about serialisation if we use numbers instead of strings for certain values.
        $current_ser_prec = ini_get('serialize_precision');

        ini_set('serialize_precision', '-1');
        $r = self::to_array($question, $with_attachments);
        $j = json_encode($r, JSON_PRETTY_PRINT);

        ini_set('serialize_precision', $current_ser_prec);
        return $j;
    }

    public static function to_array(
        qtype_stateful_question $question,
        bool
         $with_attachments
    ): array{
        $f                = [];
        $r                = [];
        $r['id']          = (int) $question->id;
        $r['category']    = (int) $question->category;
        $r['questionbankentryid'] = (int) $question->questionbankentryid;
        $r['version']     = (int) $question->version;
        $r['name']        = $question->name;
        $r['description'] = $question->questiontext;
        $r['pointvalue']  = self::decimal_clear($question->defaultmark);
        if (intval($r['pointvalue']) == $r['pointvalue']) {
            $r['pointvalue'] = intval($r['pointvalue']);
        } else {
            $r['pointvalue'] = floatval($r['pointvalue']);
        }

        $r['penalty'] = self::decimal_clear($question->penalty);
        if (intval($r['penalty']) == $r['penalty']) {
            $r['penalty'] = intval($r['penalty']);
        } else {
            $r['penalty'] = floatval($r['penalty']);
        }

        $r['questionvariables'] = $question->questionvariables;

        $r['statevariables'] = [];

        if ($question->parlength !== null && $question->parlength > 0) {
            $r['parlength'] = intval($question->parlength);
        }

        $reservednumbers = [];
        if ($question->genericmeta === null || $question->genericmeta === '') {
            $question->genericmeta = [];
        } else if (is_string($question->genericmeta)) {
            $question->genericmeta = json_decode($question->genericmeta, true);
        }

        if (array_key_exists('statevariablenumbers', $question->genericmeta)) {
            $reservednumbers = $question->genericmeta['statevariablenumbers'];
        }

        foreach ($question->variables as $name => $variable) {
            $r['statevariables'][] = [
                'name' => $variable->name,
                'description' => $variable->description,
                'type' => $variable->type,
                'initialvalue' => $variable->initialvalue,
                'number' => (int) $variable->number
            ];
            $reservednumbers[$name] = $variable->number;
        }

        $r['questionnote'] = $question->questionnote;
        if ($question->variants === '' || $question->variants === '{}' || $question->variants === null) {
            $r['variants'] = [];
        } else {
            $r['variants'] = json_decode($question->variants, true);
        }

        if ($question->id !== null && $question->generalfeedback !== null) {
            $r['modelsolution'] = self::extract_files($question->generalfeedback,
                $with_attachments, $f, $question->id, 'question', 'generalfeedback'
            );
        }

        $r['entryscenename'] = $question->entryscene;

        $r['scenes'] = [];

        foreach ($question->scenes as $name => $scene) {
            $r['scenes'][] = self::to_array_scene($scene,
                $with_attachments, $f);
        }

        $r['options']                   = [];
        $r['options']['assumepositive'] = $question->options->get_option(
            'assumepos');
        $r['options']['assumereal'] = $question->options->get_option(
            'assumereal');
        $r['options']['complexno'] = $question->options->get_option('complexno'
        );
        $r['options']['multiplicationsign'] = $question->options->get_option(
            'multiplicationsign');
        $r['options']['sqrtsign'] = $question->options->get_option(
            'sqrtsign');
        $r['options']['inversetrig'] = $question->options->get_option(
            'inversetrig');
        $r['options']['matrixparens'] = $question->options->get_option(
            'matrixparens');
        $r['options']['questionsimplify'] = $question->options->get_option(
            'simplify');

        $r['versions'] = [
            'Stateful' => $question->statefulversion,
            'STACK' => $question->stackversion
        ];
        if ($r['versions']['Stateful'] === null) {
            $pm                        = core_plugin_manager::instance();
            $r['versions']['Stateful'] = $pm->get_plugin_info('qtype_stateful')->versiondisk;
        }
        if ($r['versions']['STACK'] === null) {
            $pm                        = core_plugin_manager::instance();
            $r['versions']['STACK'] = $pm->get_plugin_info('qtype_stack')->versiondisk;
        }

        $r['meta']                         = $question->genericmeta;
        $r['meta']['statevariablenumbers'] = $reservednumbers;

        if (count($f) > 0) {
            $r['files'] = $f;
        }

        return $r;
    }

    public static function to_array_scene(
        stateful_scene $scene,
        bool
         $with_attachments,
        array &$extract_to
    ): array{
        $r                   = [];
        $r['name']           = $scene->name;
        $r['description']    = $scene->description;
        $r['scenevariables'] = $scene->scenevariables;
        if (isset($scene->id) && $scene->id !== null) {
            $r['scenetext']      = self::extract_files($scene->scenetext,
                $with_attachments, $extract_to, $scene->id, 'qtype_stateful',
                'scenetext');
        } else {
            $r['scenetext'] = $scene->scenetext;
        }

        $r['inputs'] = [];
        foreach ($scene->inputs as $name => $input) {
            // Prune the options.
            $r['inputs'][] = $input->serialize(true);
        }

        if (count($scene->vboxes) > 0) {
            $r['vboxes'] = [];
            foreach ($scene->vboxes as $name => $vbox) {
                $r['vboxes'][] = $vbox->serialize();
            }
        }

        $r['prts'] = [];
        foreach ($scene->prts as $prt) {
            $r['prts'][] = self::to_array_prt($prt, $with_attachments,
                $extract_to);
        }

        return $r;
    }

    public static function to_array_prt(
        stateful_prt $prt,
        bool
         $with_attachments,
        array &$extract_to
    ): array{
        $r                        = [];
        $r['name']                = $prt->name;
        $r['feedbackvariables']   = $prt->feedbackvariables;
        $r['scoremode']           = $prt->scoremode;
        $r['scoremodeparameters'] = $prt->scoremodeparameters;
        $r['value']               = self::decimal_clear($prt->value);
        if (intval($r['value']) == $r['value']) {
            $r['value'] = intval($r['value']);
        } else {
            $r['value'] = floatval($r['value']);
        }

        $r['root'] = $prt->root->name;

        $r['nodes'] = [];

        foreach ($prt->nodes as $node) {
            $n = ['name' => $node->name, 'test' => $node->test, 'sans' =>
                $node->
                sans, 'tans' => $node->tans, 'options' => $node->options,
                'quiet' => $node->
                quiet];
            if ($n['quiet'] === '0') {
                $n['quiet'] = false;
            } else if ($n['quiet'] === '1') {
                $n['quiet'] = true;
            }

            $n['true'] = [
                'feedback' => self::extract_files($node->truefeedback,
                    $with_attachments, $extract_to, $node->id, 'qtype_stateful'
                    ,
                    'prtnodetruefeedback'),
                'variables' => $node->truevariables,
                'next' => $node->truenext,
                'scoremode' => $node->truescoremode,
                'score' => $node->truescore,
                'penaltymode' => $node->truepenaltymode,
                'penalty' => $node->truepenalty,
                'tests' => $node->truetests
            ];
            if ($n['true']['next'] === null) {
                unset($n['true']['next']);
            }

            if ($node->truetests === null || $node->truetests === '') {
                $n['true']['tests'] = [];
            } else if (is_string($node->truetests)) {
                // As these are rarely used they might not have been decoded yet.
                $n['true']['tests'] = json_decode($node->
                    truetests, true);
            }

            $n['false'] = [
                'feedback' => self::extract_files($node->falsefeedback,
                    $with_attachments, $extract_to, $node->id, 'qtype_stateful'
                    ,
                    'prtnodefalsefeedback'),
                'variables' => $node->falsevariables,
                'next' => $node->falsenext,
                'scoremode' => $node->falsescoremode,
                'score' => $node->falsescore,
                'penaltymode' => $node->falsepenaltymode,
                'penalty' => $node->falsepenalty,
                'tests' => $node->falsetests
            ];
            if ($n['false']['next'] === null) {
                unset($n['false']['next']);
            }
            if ($node->falsetests === null || $node->falsetests === '') {
                $n['false']['tests'] = [];
            } else if (is_string($node->falsetests)) {
                // As these are rarely used they might not have been decoded yet.
                $n['false']['tests'] = json_decode($node->
                    falsetests, true);
            }
            $r['nodes'][] = $n;
        }

        return $r;
    }

    // Takes Moodle references to Moodle attachments and fetches them if need be to the file-array
    // also turns those references to another form.
    // NOTE: we do not yet support attachements, this code is unlikely to work
    // it has never been tested.
    public static function extract_files(
        $content,
        bool $with_attachments,
        array &$extract_to,
        $itemid,
        string $component,
        string $filearea
    ):
    string {
        global $DB;

        $text = '';
        if (is_string($content)) {
            $text = $content;
        } else if ($content !== null) {
            $text = $content['text'];
        }
        if ($text === null) {
            $text = '';
        }

        // In moodle the file reference is of the form @@PLUGINFILE@@/filename where the filename
        // can be anything therefore making identification of the filename from the source difficult
        // as there is no end character present. The only way to work with that reference is to also
        // have a listing of filenames available which makes consistency checking only work in
        // one direction. For Stateful formats we will add a terminator to the syntax so that
        // arbitrary form filenames can be extracted reliably and checked against inventory, this
        // means that while the Moodle form allows one to append ?forcedownload=1 to the filename
        // and get the desired results the stateful will not allow that and will instead provide
        // other syntax methods to do that.

        // @@URL@filename@@   and   @@SAVEASURL@filename@@

        // Naturally, whatever VLE is in use it will then need to revert this if it uses the Moodle
        // way. Note that we do not do this for the Moodle-XML format of Stateful.

        // NOte that unlike in Moodle-XML we store the files separately at the end of the document
        // and therefore do much more to try to identify their positions. Also we may reference
        // The same file in many more places.

        if (mb_strpos($text, '@@PLUGINFILE@@') === false) {
            return $text;
        }

        $files = $DB->get_records('files', [
            'itemid' => $itemid,
            'component' => $component,
            'filearea' => $filearea]);

        $candidates = [];
        foreach ($files as $file) {
            if (mb_strpos($text, '@@PLUGINFILE@@' . $file->filepath .
                $file->filename) !== false) {
                // Is it this file or some other that starts like this. Oh the questions...
                // e.g. "@@PLUGINFILE@@/pkg.tar.gz" and files "pkg.tar" and "pkg.tar.gz"
                $candidates[$file->filepath . $file->filename] = $file;
            }
        }

        if (count($candidates) === 0) {
            // Would be nice to consider this as an error.
            return $text;
        }

        // Identify ensure identification and rewrite.
        $stems = [];
        foreach ($candidates as $key => $value) {
            $stems[$key] = [];
        }
        foreach ($candidates as $key => $value) {
            foreach ($stems as $stem => $v) {
                if (mb_strpos($key, $stem) === 0 && $key !== $stem) {
                    $stems[$stem][] = $key;
                }
            }
        }

        $R = $text;

        $filenames = [];

        // It is important to go these through from longest to shortest.
        $stemsinorder = array_keys($stems);
        usort($stemsinorder, function (
            string $a,
            string $b
        ) {
            return strlen($a)
            < strlen($b);});

        foreach ($stemsinorder as $stem) {
            $options = $stems[$stem];
            if (count($options) === 0) {
                if (mb_strpos($R, '@@PLUGINFILE@@' . $stem) !== false)
                                                                               {
                    $filenames[$stem] = $candidates[$stem];

                    if (mb_strpos($R, '@@PLUGINFILE@@' . $stem .
                        '?forcedownload=1') !== false) {
                        $R = core_text::str_replace('@@PLUGINFILE@@' . $stem .
                            '?forcedownload=1', '@@SAVEASURL@' . $stem . '@@',
                            $R);
                    }
                    $R = core_text::str_replace('@@PLUGINFILE@@' . $stem,
                        '@@URL@' . $stem . '@@', $R);
                }
            } else {
                // Go through longest first.
                usort($options, function (
                    string $a,
                    string $b
                ) {
                    return strlen(
                        $a) < strlen($b);});

                foreach ($options as $stm) {
                    if (mb_strpos($R, '@@PLUGINFILE@@' . $stm) !==
                        false) {
                        $filenames[$stm] = $candidates[$stm];

                        if (mb_strpos($R, '@@PLUGINFILE@@' . $stm .
                            '?forcedownload=1') !== false) {
                            $R = core_text::str_replace('@@PLUGINFILE@@' . $stm
                                . '?forcedownload=1', '@@SAVEASURL@' . $stm .
                                '@@', $R);
                        }
                        $R = core_text::str_replace('@@PLUGINFILE@@' . $stm,
                            '@@URL@' . $stm . '@@', $R);
                    }
                }

            }
        }

        $fs = get_file_storage();

        // Then update the files listing.
        foreach ($filenames as $key => $file) {
            $extract_to[$key] = ['size' => $file->filesize, 'mime' =>
                $file->mimetype];
            if ($with_attachments) {
                // This version is independent of the originating system.
                $f = $fs->get_file($file->contextid, $file->component, $file->
                    filearea,
                    $file->itemid, $file->filepath, $file->filename);
                $extract_to[$key]['content'] = chunk_split(base64_encode($f->
                    get_content()));
            } else {
                // This one is not independent and requires access to the URLs to
                // fetch the content. Usable when doing local editing tasks but not for
                // transfer.
                $extract_to[$key]['url'] = (moodle_url::make_pluginfile_url(
                    $file->contextid, $file->component, $file->filearea,
                    $file->itemid, $file->filepath, $file->filename))->out(
                    false);
            }
        }

        return $R;
    }

    // Inverse of extract_files just moves any files to draftfiles. No matter if they already exist
    // as we cannot guarantee the $itemid to stay the same...
    // NOTE: we do not yet support attachements, this code is unlikely to work
    // it has never been tested.
    public static function inject_files(
        string $text,
        array $filemeta,
        $itemid,
        string $component,
        string $filearea
    ): string {
        global $DB, $CFG;

        if (mb_strpos($text, '@@URL@') === false && mb_strpos(
            $text, '@@SAVEASURL@') === false) {
            // No files present.
            return $text;
        }

        $r = $text;

        $fs = get_file_storage();

        foreach ($filemeta as $filename => $metadata) {
            $found = false;
            if (mb_strpos($text, '@@URL@' . $filename . '@@') !== false
            ) {
                $found = true;
            } else if (mb_strpos($text, '@@SAVEASURL@' . $filename .
                '@@') !== false) {
                $found = true;
            }
            if ($found) {
                // So the file was used here.
                $draftfilename = $filename;
                // Lets fetch its contents, from where ever they are and store them again as
                // a draftfile. Lets hope the Moodle installation has the CRON running...

                $fileinfo = [
                    'contextid' => get_system_context(),
                    'component' => 'user',
                    'filearea' => 'draft',
                    'filepath' => '/',
                    'mimetype' => $filemeta['mime'],
                    'itemid' => 0 // Should probably make something sensible here.
                ];

                // All paths in the JSON format are with the correct separators... and
                // start from root.
                $pathsplit = explode('/', $filename);
                if (count($pathsplit) === 2) {
                    $fileinfo['filename'] = $pathsplit[1];
                } else if (count($pathsplit) === 1) {
                    $fileinfo['filename'] = $pathsplit[0];
                } else {
                    $fileinfo['filename'] = $pathsplit[count($pathsplit) - 1];
                    $fileinfo['filepath'] = '/' . implode('/', array_slice(
                        $pathsplit, 0, -1)) . '/';
                }

                $f = null;
                if (array_key_exists('content', $filemeta)) {
                    // So this is a fresh upload.
                    $filedata = base64_decode($filemeta['content']);
                    // Save as a draftfile.
                    $f = $fs->create_file_from_string($fileinfo, $filedata);
                } else {
                    // If url is local get it from local source otherwise download.
                    $filedata = null;

                    // Save as a draftfile.
                    if (mb_strpos($filemeta['url'], $CFG->wwwroot .
                        '/pluginfile.php') === 0) {
                        $stored = null;
                        // TODO...
                        $f = $fs->create_file_from_storedfile($fileinfo,
                            $stored);
                    } else {
                        $f = $fs->create_file_from_url($fileinfo, $filemeta[
                            'url']);
                    }
                }

                // TODO! Not bothering now.

                $r = core_text::str_replace('@@SAVEASURL@' . $filename . '@@',
                    '@@PLUGINFILE@@' . $draftfilename . '?forcedownload=1',
                    $text);
                $r = core_text::str_replace('@@URL@' . $filename . '@@',
                    '@@PLUGINFILE@@' . $draftfilename, $text);
            }
        }

        return $r;
    }

    public static function decimal_clear(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        // Takes a decimal value coming from DB and strips the zeros from the end as they look silly.
        $r = $value;
        while (strrpos($r, '00') === strlen($r) - 2) {
            $r = substr($r, 0, -1);
        }

        if (strpos($r, '.') < strlen($r) - 2 && strrpos($r, '0') === strlen($r)
             - 1) {
            $r = substr($r, 0, -1);
        }

        return $r;
    }

    // This is a representation of https://json-schema.org/ that can be used to
    // do minimal structural validation.
    public static function schema(): array{
        $r = [];

        // The root.
        $r['type']                   = 'object';
        $r['properties']             = [];
        $r['properties']['id']       = ['type' => 'integer'];
        $r['properties']['category'] = ['type' => 'integer'];


        $r['properties']['name']     = [
            'type' => 'string',
            'maxLength' => 255,
            'minLength' => 1
        ];
        $r['properties']['description'] = ['type' => 'string'];
        // In case the 0.0005 seems od it is there to limit the number of decimals
        // and as we are playing with floats 0.0005 is way more accurate than the
        // implied 0.001 although one can now split that in half...
        $r['properties']['pointvalue'] = ['type' => 'number',
            'minimum' => 0,
            'multipleOf' => 0.0005];
        $r['properties']['penalty'] = ['type' => 'number',
            'minimum' => 0,
            'maximum' => 1,
            'multipleOf' => 0.0005];
        $r['properties']['parlength'] = ['type' => 'integer', 'minimum' => -1];
        
        $r['properties']['questionvariables'] = ['type' => 'string'];
        $r['properties']['questionnote']      = ['type' => 'string'];
        $r['properties']['modelsolution']     = ['type' => 'string'];

        // We cannot check for uniquenes of names or numbers but we can atleast stop spamming of the same var...
        $r['properties']['statevariables'] = [
            'type' => 'array',
            'uniqueItems' => true,
            'items' => [
                'type' => 'object',
                'properties' => [
                ]]];
        // lets keep this a bit more readable.
        $sv = $r['properties']['statevariables']['items'];

        $sv['properties']['name'] = ['type' => 'string',
            'minLength' => 1,
            'maxLength' => 255
        ];
        $sv['properties']['initialvalue'] = ['type' => 'string',
            'minLength' => 1
        ];
        $sv['properties']['number'] = ['type' => 'integer', 'minimum' => 0
        ];
        $sv['properties']['description'] = ['type' => 'string'];
        $sv['properties']['type']        = ['type' => 'string',
            'enum' => ['Any', 'List', 'Set', 'Integer', 'Boolean', 'String',
                'Scene']];
        $sv['required']             = ['name', 'initialvalue'];
        $sv['additionalProperties'] = false;

        $r['properties']['statevariables']['items'] = $sv;

        // We cannot check for uniquenes of names but we can atleast stop spamming of the same scene...
        // and again no we cannot represent this list as a map as the ordering is nice to keep
        $r['properties']['scenes'] = [
            'type' => 'array',
            'minItems' => 1,
            'uniqueItems' => true,
            'items' => [
                'type' => 'object',
                'properties' => [
                ]
            ]];

        $s                       = $r['properties']['scenes']['items'];
        $s['properties']['name'] = ['type' => 'string',
            'minLength' => 1,
            'maxLength' => 255
        ];

        $s['properties']['description']    = ['type' => 'string'];
        $s['properties']['scenevariables'] = ['type' => 'string'];
        $s['properties']['scenetext']      = ['type' => 'string'];

        $s['properties']['inputs'] = [
            'type' => 'array',
            'uniqueItems' => true,
            'items' => [
                'type' => 'object',
                'properties' => [
                ]
            ]];

        // Now things get interesting as we need to define valid options for all input types.
        $i                       = $s['properties']['inputs']['items'];
        $i['properties']['name'] = [
            'type' => 'string',
            'minLength' => 1,
            'maxLength' => 255];
        $i['oneOf']              = [];
        $i['properties']['type'] = ['type' => 'string', 'enum' => []];

        foreach (stateful_input_controller::get_input_metadata()['schema'] as $type =>
            $options) {
            $i['properties']['type']['enum'][] = $type;
            $o                                 = ['properties' =>
                ['type' => ['type' => 'string', 'enum' => [$type]]]
            ];
            $o['title'] = $type;
            $o['properties']['name']   = ['type' => 'string'];
            if ((stateful_input_controller::get_input_instance($type, 'test')) instanceof stateful_input_teachers_answer_handling) {
                $o['properties']['tans']   = ['type' => 'string'];
                $o['required'] = ['tans'];
            }
            $o['additionalProperties'] = false;
            foreach ($options['properties'] as $key => $meta) {
                $o['properties'][$key] = $meta;
            }

            $i['oneOf'][] = $o;
        }

        $i['required']                      = ['name', 'type'];

        $s['properties']['inputs']['items'] = $i;

        $s['properties']['vboxes'] = [
            'type' => 'array',
            'uniqueItems' => true,
            'items' => [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'minLength' => 1,
                        'maxLength' => 255],
                    'type' => ['type' => 'string'],
                    'text' => ['type' => 'string']
                ]
            ]];
        // For now every vbox is of the custom type with the text-property.
        // Things will change eventtually.



        $s['properties']['prts'] = [
            'type' => 'array',
            'uniqueItems' => true,
            'items' => [
                'type' => 'object',
                'properties' => [
                ]
            ]];

        $p = $s['properties']['prts']['items'];

        $p['properties']['name'] = ['type' => 'string',
            'minLength' => 1,
            'maxLength' => 255];
        $p['properties']['feedbackvariables'] = ['type' => 'string'];
        $p['properties']['value']             = ['type' => 'number', 'minimum'
                                                                         => 0];
        $p['properties']['root']              = ['type' => 'string',
            'minLength' => 1];
        $p['properties']['scoremode'] = ['type' => 'string',
            'enum' => ['no score', 'first', 'best', 'bestn']];
        $p['properties']['scoremodeparameters'] = ['type' => 'string'];
        $p['properties']['nodes']               = [
            'type' => 'array',
            'uniqueItems' => true,
            'minItems' => 1,
            'items' => [
                'type' => 'object',
                'properties' => [
                ]
            ]];
        $n = $p['properties']['nodes']['items'];

        $n['properties']['name']   = ['type' => 'string',
            'minLength' => 1,
            'maxLength' => 255
        ];
        $n['properties']['sans']    = ['type' => 'string', 'minLength' => 1];
        $n['properties']['quiet']   = ['type' => 'boolean'];
        $n['properties']['true']    = ['type' => 'object',
            'properties' => []];
        $n['required'] = ['name', 'sans'];

        $t                            = $n['properties']['true'];
        $t['properties']['feedback']  = ['type' => 'string'];
        $t['properties']['variables'] = ['type' => 'string'];
        $t['properties']['next']      = ['type' => 'string'];
        $t['properties']['score']     = ['type' => 'string'];
        $t['properties']['penalty']   = ['type' => 'string'];
        $t['properties']['scoremode'] = ['type' => 'string', 'enum' => ['+',
            '-', '=']];
        $t['properties']['penaltymode'] = ['type' => 'string', 'enum' => ['+',
            '-', '=']];
        $t['properties']['tests'] = ['type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'condition' => ['type' => 'string'],
                    'inputs' => ['type' => 'object']
                ]]];

        $n['properties']['true'] = $t;

        $n['properties']['false'] = $n['properties']['true'];

        // Enforce the requirements of tests.
        $n['oneOf'] = [];
        foreach (stateful_answertest_factory::get_all() as $cat => $tests) {
            foreach ($tests as $tname => $test) {
                $o = ['properties' => ['test' => [
                    'type' => 'string', 'enum' => [$tname]]]];
                $o['title'] = $tname;
                $o['required'] = ['test'];
                if ($test->requires_tans()) {
                    $o['properties']['tans']    = ['type' => 'string', 'minLength' => 1];
                    $o['required'][] = 'tans';
                }
                if (count($test->option_meta()) > 0) {
                    $o['properties']['options'] = ['type' => 'string', 'minLength' => 1];
                    $o['required'][] = 'options';
                }
                $n['oneOf'][] = $o;
            }
        }

        $p['properties']['nodes']['items'] = $n;

        $p['required']             = ['name', 'root', 'nodes'];
        $p['additionalProperties'] = false;

        $s['properties']['prts']['items'] = $p;

        $s['required']                      = ['name', 'scenetext'];
        $s['additionalProperties']          = false;
        $r['properties']['scenes']['items'] = $s;

        $r['properties']['entryscenename'] = ['type' => 'string', 'minLength'
                                                                          => 1,
            'maxLength' => 255];

        // This is a catch all container for whatever needs any connected tools have.
        $r['properties']['meta'] = ['type' => 'object'];

        $r['properties']['versions'] = ['type' => 'object',
            'properties' => [
                'Stateful' => ['type' => 'string'],
                'STACK' => ['type' => 'string']
            ]];

        $r['properties']['variants'] = ['type' => 'object'];
        $r['properties']['variants']['additionalProperties'] = true;
        $r['properties']['variants']['_set'] = ['type' => 'string'];

        $stackopts = new stack_options();

        $r['properties']['options'] = ['type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'multiplicationsign' => ['type' => 'string', 'enum' =>
                    array_keys(stack_options::get_multiplication_sign_options()
                    )],
                'complexno' => ['type' => 'string', 'enum' => array_keys(
                    stack_options::get_complex_no_options())],
                'inversetrig' => ['type' => 'string', 'enum' => array_keys(
                    stack_options::get_inverse_trig_options())],
                'sqrtsign' => ['type' => 'boolean'],
                'questionsimplify' => ['type' => 'boolean'],
                'assumepositive' => ['type' => 'boolean'],
                'assumereal' => ['type' => 'boolean'],
                'matrixparens' => ['type' => 'string', 'enum' => array_keys(
                    stack_options::get_matrix_parens_options())]
            ]];

        // These define the minimal question critical data.
        // Note that you do not even need to have statevariables.
        $r['required'] = ['name', 'pointvalue', 'scenes', 'entryscenename'

        ];
        // Lets just keep it clean.
        $r['additionalProperties'] = false;

        return $r;
    }

}