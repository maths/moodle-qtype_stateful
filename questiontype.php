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
defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../../../lib/questionlib.php';
require_once __DIR__ . '/stacklib.php';
require_once __DIR__ . '/stateful/core/scene.class.php';
require_once __DIR__ . '/stateful/core/statevariable.class.php';
require_once __DIR__ . '/stateful/input2/input.controller.php';
require_once __DIR__ . '/stateful/handling/moodle.questiondata.php';
require_once __DIR__ . '/stateful/handling/moodle.formdata.php';

class qtype_stateful extends question_type {

    /**
     * If your question type has a table that extends the question table, and
     * you want the base class to automatically save, backup and restore the extra fields,
     * override this method to return an array where the first element is the table name,
     * and the subsequent entries are the column names (apart from id and questionid).
     *
     * @return mixed array as above, or null to tell the base class to do nothing.
     */
    public function extra_question_fields() {
        return ['qtype_stateful_options',
            'questionvariables',
            'questionnote',
            'questionsimplify',
            'assumepositive',
            'assumereal',
            'multiplicationsign',
            'sqrtsign',
            'complexno',
            'inversetrig',
            'matrixparens',
            'entryscene',
            'stackversion',
            'statefulversion',
            'compiledcache',
            'genericmeta', // Misc stuff, that tools that work on questions of this type might want to store.
            'parlength',
            'variants'
        ];
    }

    /**
     * Saves question-type specific options, or in our case the actual question structure.
     * we might as well store the whole thing as a JSON blurb but we like to be able to focus
     * certain query's on the database side without playing with vendor specific JSON logic.
     *
     * This is called by {@link save_question()} to save the question-type specific data
     * @return object $result->error or $result->notice
     * @param object $question  This holds the information from the editing form,
     *      it is not a standard question object.
     */
    public function save_question_options($fromform) {
        global $DB;

        // As the input is "fromform" we need to do some translation.
        // First turn it to question definition and then back to "questiondata"
        // We do so because the format is much simpler to deal with.
        $q = null;
        if (is_array($fromform)) {
            // Things that make little sense in some scopes "formdata" is 
            // in arrays in other cases in objects, we do not even try to 
            // understand. Essenttially the same logic but as our functions are 
            // typed we need to deal with this.
            $q = stateful_handling_moodle_formdata::from($fromform);
        } else {
            $q = stateful_handling_moodle_formdata::from_obj($fromform);
        }
        $question = stateful_handling_moodle_questiondata::to($q);

        // FOR TODO FILES.
        $context = self::get_context_by_category_id($question->category);

        // First the options.
        // We should use the default version but as our fromform 
        // does not include these...
        $qo = $DB->get_record('qtype_stateful_options', ['questionid' => $question->id]);
        if (!$qo) {
            $qo = $question->options;
            $qo->questionid = $question->id;
            $DB->insert_record('qtype_stateful_options', $qo);
        } else {
            $question->options->id = $qo->id;
            $qo = $question->options;
            $DB->update_record('qtype_stateful_options', $qo);
        }

        // Then try to match existing variables
        $current = $DB->get_records('qtype_stateful_variables',
            ['questionid' => $question->id], '',
            'name, id, questionid, type, number, description, initialvalue');
        $currentorderofnames = [];
        foreach ($current as $sv) {
            $currentorderofnames[] = $sv->name;
        }
        $requiredorderofnames = [];
        foreach ($question->variables as $sv) {
            $requiredorderofnames[] = $sv->name;
        }
        if ($currentorderofnames !== $requiredorderofnames) {
            // We have too many or too little or in the wrong order. 
            // Lets not try anything fancy just throw them all away.
            $DB->delete_records('qtype_stateful_variables', ['questionid' => $question->id]);
        } else {
            // Perfect order, map ids.
            $ids = [];
            foreach ($current as $sv) {
                $ids[] = $sv->id;
            }
            foreach ($question->variables as $sv) {
                $sv->id = array_shift($ids);
            }
        }
        // Finally save them.
        foreach ($question->variables as $sv) {
            if (isset($sv->id)) {
                $DB->update_record('qtype_stateful_variables', $sv);
            } else {
                $sv->id = $DB->insert_record('qtype_stateful_variables', $sv);
            }
        }

        // Then scenes and all in them,
        $current = $DB->get_records('qtype_stateful_scenes',
            ['questionid' => $question->id], '',
            'id, name, questionid, scenevariables, scenetext, description');
        $currentorderofnames = [];
        foreach ($current as $s) {
            $currentorderofnames[] = $s->name;
        }
        $requiredorderofnames = [];
        foreach ($question->scenes as $s) {
            $requiredorderofnames[] = $s->name;
        }
        if ($currentorderofnames !== $requiredorderofnames) {
            // We have too many or too little or in the wrong order. 
            // Lets not try anything fancy just throw them all away.
            // Need to clean vboxes, inputs, prts and prt-nodes first.
            if (count($current) > 0) {
                list($test, $params) = $DB->get_in_or_equal(array_keys($current));
                $DB->delete_records_select('qtype_stateful_inputs', 'sceneid ' . $test, $params);
                $DB->delete_records_select('qtype_stateful_vboxes', 'sceneid ' . $test, $params);

                // Need to collect the PRTs to match nodes.
                $prts = $DB->get_records_select('qtype_stateful_prts', 'sceneid ' . $test ,$params);
                if (count($prts) > 0) {
                    list($test2, $params2) = $DB->get_in_or_equal(array_keys($prts));
                    $DB->delete_records_select('qtype_stateful_prt_nodes', 'prtid ' . $test2, $params2);
                    $DB->delete_records_select('qtype_stateful_prts', 'sceneid ' . $test, $params);
                }

                $DB->delete_records('qtype_stateful_scenes', ['questionid' => $question->id]);
            }
        } else {
            // Perfect order, map ids.
            $ids = [];
            foreach ($current as $s) {
                $ids[] = $s->id;
            }
            foreach ($question->scenes as $s) {
                $s->id = array_shift($ids);
            }
        }
        foreach ($question->scenes as $s) {
            // TODO FILES: scenetext
            if (is_array($s->scenetext)) {
                $s->scenetext = $s->scenetext['text'];
            }

            if (isset($s->id)) {
                $DB->update_record('qtype_stateful_scenes', $s);
            } else {
                $s->id = $DB->insert_record('qtype_stateful_scenes', $s);
            }


            // Then the inputs.
            $current = $DB->get_records('qtype_stateful_inputs',
                ['sceneid' => $s->id], '','*');
            $currentorderofnames = [];
            foreach ($current as $i) {
                $currentorderofnames[] = $i->name;
            }
            $requiredorderofnames = [];
            foreach ($s->inputs as $i) {
                $requiredorderofnames[] = $i->name;
            }
            if ($currentorderofnames !== $requiredorderofnames) {
                // We have too many or too little or in the wrong order. 
                // Lets not try anything fancy just throw them all away.
                $DB->delete_records('qtype_stateful_inputs', ['sceneid' => $s->id]);
            } else {
                // Perfect order, map ids.
                $ids = [];
                foreach ($current as $i) {
                    $ids[] = $i->id;
                }
                foreach ($s->inputs as $i) {
                    $i->sceneid = $s->id;
                    $i->id = array_shift($ids);
                }
            }
            foreach ($s->inputs as $i) {
                if (isset($i->id)) {
                    $DB->update_record('qtype_stateful_inputs', $i);
                } else {
                    $i->sceneid = $s->id;
                    $i->id = $DB->insert_record('qtype_stateful_inputs', $i);
                }
            }

            // Then the vboxes.
            $current = $DB->get_records('qtype_stateful_vboxes',
                ['sceneid' => $s->id], '','*');
            $currentorderofnames = [];
            foreach ($current as $v) {
                $currentorderofnames[] = $v->name;
            }
            $requiredorderofnames = [];
            foreach ($s->vboxes as $v) {
                $requiredorderofnames[] = $v->name;
            }
            if ($currentorderofnames !== $requiredorderofnames) {
                // We have too many or too little or in the wrong order. 
                // Lets not try anything fancy just throw them all away.
                $DB->delete_records('qtype_stateful_vboxes', ['sceneid' => $s->id]);
            } else {
                // Perfect order, map ids.
                $ids = [];
                foreach ($current as $v) {
                    $ids[] = $v->id;
                }
                foreach ($s->vboxes as $v) {
                    $v->sceneid = $s->id;
                    $v->id = array_shift($ids);
                }
            }
            foreach ($s->vboxes as $v) {
                if (isset($v->id)) {
                    $DB->update_record('qtype_stateful_vboxes', $v);
                } else {
                    $v->sceneid = $s->id;
                    $v->id = $DB->insert_record('qtype_stateful_vboxes', $v);
                }

                // TODO FILES: text
            }

            // Then the prts.            
            $current = $DB->get_records('qtype_stateful_prts',
                ['sceneid' => $s->id], '','*');
            $currentorderofnames = [];
            foreach ($current as $p) {
                $currentorderofnames[] = $p->name;
            }
            $requiredorderofnames = [];
            foreach ($s->vboxes as $p) {
                $requiredorderofnames[] = $p->name;
            }
            if ($currentorderofnames !== $requiredorderofnames) {
                // We have too many or too little or in the wrong order. 
                // Lets not try anything fancy just throw them all away.
                if (count($current) > 0) {
                    list($test2, $params2) = $DB->get_in_or_equal(array_keys($current));
                    $DB->delete_records_select('qtype_stateful_prt_nodes', 'prtid ' . $test2, $params2);      

                    $DB->delete_records('qtype_stateful_prts', ['sceneid' => $s->id]);
                }
            } else {
                // Perfect order, map ids.
                $ids = [];
                foreach ($current as $p) {
                    $ids[] = $p->id;
                }
                foreach ($s->prts as $p) {
                    $p->sceneid = $s->id;
                    $p->id = array_shift($ids);
                }
            }
            foreach ($s->prts as $p) {
                if (isset($p->id)) {
                    $DB->update_record('qtype_stateful_prts', $p);
                } else {
                    $p->sceneid = $s->id;
                    $p->id = $DB->insert_record('qtype_stateful_prts', $p);
                }

                // The nodes.
                $current = $DB->get_records('qtype_stateful_prt_nodes',
                    ['prtid' => $p->id], '','*');
                $currentorderofnames = [];
                foreach ($current as $pn) {
                    $currentorderofnames[] = $pn->name;
                }
                $requiredorderofnames = [];
                foreach ($p->nodes as $pn) {
                    $requiredorderofnames[] = $pn->name;
                }
                if ($currentorderofnames !== $requiredorderofnames) {
                    // We have too many or too little or in the wrong order. 
                    // Lets not try anything fancy just throw them all away.
                    $DB->delete_records('qtype_stateful_prt_nodes', ['prtid' => $p->id]);
                } else {
                    // Perfect order, map ids.
                    $ids = [];
                    foreach ($current as $pn) {
                        $ids[] = $pn->id;
                    }
                    foreach ($p->nodes as $pn) {
                        $pn->prtid = $p->id;
                        $pn->id = array_shift($ids);
                    }
                }
                foreach ($p->nodes as $pn) {
                    if (isset($pn->id)) {
                        $DB->update_record('qtype_stateful_prt_nodes', $pn);
                    } else {
                        $pn->prtid = $p->id;
                        $pn->id = $DB->insert_record('qtype_stateful_prt_nodes', $pn);
                    }

                    // TODO FILES: truefeedback, falsefeedback
                }
            }
        }

    }

    public function get_question_options($question) {
        global $DB;

        parent::get_question_options($question);

        $question->variables = $DB->get_records('qtype_stateful_variables',
            ['questionid' => $question->id], 'id ASC');

        // The depth of the model might lead to this being rewritten as joins at some point.
        $scenes = $DB->get_records('qtype_stateful_scenes',
            ['questionid' => $question->id], 'id ASC');

        foreach ($scenes as $scene) {
            $scene->inputs = [];
            $scene->prts   = [];
            $scene->vboxes = [];
        }

        foreach ($DB->get_records_list('qtype_stateful_inputs', 'sceneid',
            array_keys($scenes), 'id ASC') as $input) {
            $scenes[$input->sceneid]->inputs[$input->id] = $input;
        }

        foreach ($DB->get_records_list('qtype_stateful_vboxes', 'sceneid',
            array_keys($scenes), 'id ASC') as $vbox) {
            $scenes[$vbox->sceneid]->vboxes[$vbox->id] = $vbox;
        }

        $prts = $DB->get_records_list('qtype_stateful_prts', 'sceneid',
            array_keys($scenes), 'id ASC');
        foreach ($prts as $prt) {
            $scenes[$prt->sceneid]->prts[$prt->id]       = $prt;
            $scenes[$prt->sceneid]->prts[$prt->id]->node = [];
        }

        foreach ($DB->get_records_list('qtype_stateful_prt_nodes', 'prtid',
            array_keys($prts), 'id ASC') as $node) {
            $prt = $prts[
                $node->prtid]; // TODO: Why did I write it like this? In parts?
            $scenes[$prt->sceneid]->prts[$prt->id]->nodes[$node->id] = $node;
        }

        $question->scenes = $scenes;
    }

    protected function initialise_question_instance(
        question_definition $question, $questiondata
    ) {
        parent::initialise_question_instance($question, $questiondata);

        $question->questionvariables = $questiondata->options->
        questionvariables;
        $question->questionnote    = $questiondata->options->questionnote;
        $question->entryscene      = $questiondata->options->entryscene;
        $question->stackversion    = $questiondata->options->stackversion;
        $question->statefulversion = $questiondata->options->statefulversion;
        $question->compiledcache   = $questiondata->options->compiledcache;
        $question->genericmeta     = $questiondata->options->genericmeta;
        $question->parlength       = intval($questiondata->options->parlength);
        $question->variants        = $questiondata->options->variants;

        $question->options = new stack_options();
        $question->options->set_option('multiplicationsign', $questiondata->
            options->multiplicationsign);
        $question->options->set_option('complexno', $questiondata->options->
            complexno);
        $question->options->set_option('inversetrig', $questiondata->options->
            inversetrig);
        $question->options->set_option('matrixparens', $questiondata->options->
            matrixparens);
        $question->options->set_option('sqrtsign', (bool) $questiondata->
            options->sqrtsign);
        $question->options->set_option('simplify', (bool) $questiondata->
            options->questionsimplify);
        $question->options->set_option('assumepos', (bool) $questiondata->
            options->assumepositive);
        $question->options->set_option('assumereal', (bool) $questiondata->
            options->assumereal);

        $question->variables = [];
        $question->scenes    = [];

        foreach ($questiondata->variables as $dbid => $vardata) {
            $sv = new stateful_statevariable(
                $question, $vardata);
            $question->variables[$sv->name] = $sv;
        }

        foreach ($questiondata->scenes as $dbid => $scenedata) {
            $s = new stateful_scene($question,
                $scenedata);
            $question->scenes[$s->name] = $s;
        }
    }

    public function delete_question(
        $questionid, $contextid
    ) {
        global $DB;

        // We need to trace back some relations. Might be better to just write
        // SQL but lets use the interface to keep this simple to read.
        $scenes = $DB->get_records('qtype_stateful_scenes',
            ['questionid' => $questionid]);

        $prts = $DB->get_records_list('qtype_stateful_prts', 'sceneid',
            array_keys($scenes));
        foreach ($prts as $prtid => $prt) {
            $DB->delete_records('qtype_stateful_prt_nodes', ['prtid' =>
                $prtid]);
        }

        foreach ($scenes as $sceneid => $scene) {
            $DB->delete_records('qtype_stateful_inputs', ['sceneid' =>
                $sceneid]);
            $DB->delete_records('qtype_stateful_prts', ['sceneid' =>
                $sceneid]);
            $DB->delete_records('qtype_stateful_vboxes', ['sceneid' =>
                $sceneid]);
        }

        $DB->delete_records('qtype_stateful_options', ['questionid' =>
            $questionid]);
        $DB->delete_records('qtype_stateful_scenes', ['questionid' =>
            $questionid]);
        $DB->delete_records('qtype_stateful_variables', ['questionid' =>
            $questionid]);

        parent::delete_question($questionid, $contextid);
    }

    public function move_files(
        $questionid, $oldcontextid, $newcontextid
    ) {
        global $DB;
        $fs = get_file_storage();
        // NOTE: we do not yet support attachements, this code is unlikely to work
        // it has never been tested.

        parent::move_files($questionid, $oldcontextid, $newcontextid);

        $fs->move_area_files_to_new_context($oldcontextid, $newcontextid,
            'question', 'generalfeedback', $questionid);

        $scenes = $DB->get_records('qtype_stateful_scenes',
            ['questionid' => $questionid]);

        foreach ($scenes as $sceneid => $scene) {
            $fs->move_area_files_to_new_context($oldcontextid, $newcontextid,
                'qtype_stateful', 'scenetext', $sceneid);
        }

        $prts = $DB->get_records_list('qtype_stateful_prts', 'sceneid',
            array_keys($scenes));

        $nodes = $DB->get_records_list('qtype_stateful_prt_nodes', 'prtid',
            array_keys($prts));
        foreach ($nodes as $nodeid => $notused) {
            $fs->move_area_files_to_new_context($oldcontextid, $newcontextid,
                'qtype_stateful', 'prtnodetruefeedback', $nodeid);
            $fs->move_area_files_to_new_context($oldcontextid, $newcontextid,
                'qtype_stateful', 'prtnodefalsefeedback', $nodeid);
        }
    }

    protected function delete_files(
        $questionid,
        $contextid
    ) {
        global $DB;
        $fs = get_file_storage();
        // NOTE: we do not yet support attachements, this code is unlikely to work
        // it has never been tested.

        parent::delete_files($questionid, $contextid);

        $fs->delete_area_files($contextid, 'question', 'generalfeedback',
            $questionid);

        $scenes = $DB->get_records('qtype_stateful_scenes',
            ['questionid' => $questionid]);

        foreach ($scenes as $sceneid => $scene) {
            $fs->delete_area_files($oldcontextid, $newcontextid,
                'qtype_stateful', 'scenetext', $sceneid);
        }

        $prts = $DB->get_records_list('qtype_stateful_prts', 'sceneid',
            array_keys($scenes));

        $nodes = $DB->get_records_list('qtype_stateful_prt_nodes', 'prtid',
            array_keys($prts));
        foreach ($nodes as $nodeid => $notused) {
            $fs->delete_area_files($oldcontextid, $newcontextid,
                'qtype_stateful', 'prtnodetruefeedback', $nodeid);
            $fs->delete_area_files($oldcontextid, $newcontextid,
                'qtype_stateful', 'prtnodefalsefeedback', $nodeid);
        }
    }

    public function import_from_xml(
        $xml,
        $question,
        qformat_xml $format,
        $extra = null
    ) {
        $stackconfig = stack_utils::get_config();
        if (!isset($xml['@']['type']) || $xml['@']['type'] != $this->name()) {
            return false;
        }
        $question = $format->import_headers($xml);

        // TODO: Whine about how I cannot find a way to push those attributes to
        // the <question>-tag. Reading would be easy but how does one write...

        // So within that <question type="stateful">...</question> is
        // a <stateful> tag with some meta as attributes and the payload in it.
        $question->options->entryscene   = $xml['#']['stateful'][0]['@']['entryscene'];
        $question->options->stackversion = $xml['#']['stateful'][0]['@']['stackversion']
        ;
        $question->options->statefulversion = $xml['#']['stateful'][0]['@'][
            'statefulversion'];
        $question->options->questionvariables = $format->getpath($xml, ['#',
            'stateful', 0, '#', 'questionvariables', 0, '#', 'text', 0, '#'],
            '', true);
        $question->options->questionnote = $format->getpath($xml, ['#', 'stateful',
            0, '#', 'questionnote', 0, '#', 'text', 0, '#'], '', true);
        $question->options->questionsimplify = $format->getpath($xml, ['#',
            'stateful', 0, '@', 'questionsimplify'], 1);
        $question->options->assumepositive = $format->getpath($xml, ['#',
            'stateful', 0, '@', 'assumepositive'], 0);
        $question->options->assumereal = $format->getpath($xml, ['#', 'stateful', 0
            , '@', 'assumereal'], 0);
        $question->options->multiplicationsign = $format->getpath($xml, ['#',
            'stateful', 0, '@', 'multiplicationsign'], 'dot');
        $question->options->sqrtsign = $format->getpath($xml, ['#', 'stateful', 0,
            '@', 'sqrtsign'], 1);
        $question->options->complexno = $format->getpath($xml, ['#', 'stateful', 0,
            '@', 'complexno'], 'i');
        $question->options->inversetrig = $format->getpath($xml, ['#', 'stateful',
            0, '@', 'inversetrig'], 'cos-1');
        $question->options->matrixparens = $format->getpath($xml, ['#', 'stateful',
            0, '@', 'matrixparens'], '[');
        $question->options->parlength = intval($format->getpath($xml, ['#', 'stateful',
            0, '@', 'parlength'], '-1'));
        $question->options->variants = $format->getpath($xml, ['#', 'stateful',
            0, '@', 'variants'], '{}');

        $question->scenes    = [];
        $question->variables = [];

        $scenes = $xml['#']['stateful'][0]['#']['scene'];

        $inputmeta = stateful_input_controller::get_input_metadata()['schema'];

        foreach ($scenes as $num => $sxml) {
            $scene              = new stdClass();
            $scene->inputs      = [];
            $scene->prts        = [];
            $scene->name        = $sxml['@']['name'];
            $scene->description = $format->getpath($sxml, ['#',
                'description', 0, '#', 'text', 0, '#'], '', true);
            $scene->scenevariables = $format->getpath($sxml, ['#',
                'scenevariables', 0, '#', 'text', 0, '#'], '', true);
            $scene->scenetext = $this->import_xml_text($sxml, ['#',
                'scenetext', 0], $format);

            if (isset($sxml['#']['input'])) {
                foreach ($sxml['#']['input'] as $inum => $ixml) {
                    $input          = new stdClass();
                    $input->name    = $ixml['@']['name'];
                    $input->type    = $ixml['@']['type'];
                    $input->tans    = json_decode($format->getpath($ixml, ['@', 'tans'], ''), true);
                    $input->options = [];
                    foreach ($ixml['@'] as $key => $v) {
                        $value = $format->getpath($ixml, ['@', $key], null);
                        if (array_key_exists($key, $inputmeta[$input->type]['properties']) &&
                            $value !== null) {
                            $input->options[$key] = json_decode($value, true);
                        }
                    }
                    $input->options = json_encode($input->options);
                    $scene->inputs[$input->name] = $input;
                }
            }

            if (isset($sxml['#']['vbox'])) {
                foreach ($sxml['#']['vbox'] as $inum => $ixml) {
                    $vbox          = new stdClass();
                    $vbox->name    = $ixml['@']['name'];
                    $vbox->type    = $ixml['@']['type'];
                    $vbox->options = [];
                    foreach ($ixml['@'] as $key => $v) {
                        $value = $format->getpath($ixml, ['@', $key], null);
                        if ($key !== 'name' && $key !== 'type') {
                            $vbox->options[$key] = json_decode($value, true);
                        }
                    }
                    $vbox->options = json_encode($vbox->options);
                    $scene->vboxes[$vbox->name] = $vbox;
                }
            }

            if (isset($sxml['#']['prt'])) {
                foreach ($sxml['#']['prt'] as $pnum => $pxml) {
                    $prt        = new stdClass();
                    $prt->name  = $pxml['@']['name'];
                    $prt->nodes = [];
                    $prt->value = $format->getpath($pxml, ['@', 'value'], 1
                    );
                    $prt->firstnodename = $format->getpath($pxml, ['@',
                        'firstnodename'], '');
                    $prt->feedbackvariables = $format->getpath($pxml, ['#',
                        'feedbackvariables', 0, '#', 'text', 0, '#'], '', true);
                    $prt->scoremode = $format->getpath($pxml, ['@',
                        'scoremode'], '');
                    $prt->scoremodeparameters = $format->getpath($pxml, ['@',
                        'scoremodeparameters'], '');

                    foreach ($pxml['#']['node'] as $nnum => $pnxml) {
                        $node       = new stdClass();
                        $node->name = $pnxml['@']['name'];
                        $node->test = $format->getpath($pnxml, ['@', 'test'],
                            '');
                        $node->sans = $format->getpath($pnxml, ['@', 'sans'],
                            '');
                        $node->tans = $format->getpath($pnxml, ['@', 'tans'],
                            '');
                        $node->options = $format->getpath($pnxml, ['@',
                            'options'], '');
                        $node->quiet = intval($format->getpath($pnxml, ['@',
                            'quiet'], 0));

                        $node->truescoremode = $format->getpath($pnxml, ['#',
                            'true', 0, '@', 'scoremode'], '=');
                        $node->truescore = $format->getpath($pnxml, ['#',
                            'true', 0, '@', 'score'], '1');
                        $node->truepenaltymode = $format->getpath($pnxml, ['#'
                            , 'true', 0, '@', 'penaltymode'], '=');
                        $node->truepenalty = $format->getpath($pnxml, ['#',
                            'true', 0, '@', 'penalty'], '');
                        $node->truenextnode = $format->getpath($pnxml, ['#',
                            'true', 0, '@', 'nextnode'], null);
                        $node->truefeedback = $this->import_xml_text($pnxml, [
                            '#', 'true', 0, '#', 'feedback', 0], $format);
                        $node->truevariables = $format->getpath($pnxml, ['#',
                            'true', 0, '#', 'variables', 0, '#', 'text', 0, '#'],
                            '', true);
                        $node->truetests = $format->getpath($pnxml, ['#',
                            'true', 0, '#', 'tests', 0, '#', 'text', 0, '#'], '',
                            true);

                        $node->falsescoremode = $format->getpath($pnxml, ['#',
                            'false', 0, '@', 'scoremode'], '=');
                        $node->falsescore = $format->getpath($pnxml, ['#',
                            'false', 0, '@', 'score'], '1');
                        $node->falsepenaltymode = $format->getpath($pnxml, [
                            '#', 'false', 0, '@', 'penaltymode'], '=');
                        $node->falsepenalty = $format->getpath($pnxml, ['#',
                            'false', 0, '@', 'penalty'], '');
                        $node->falsenextnode = $format->getpath($pnxml, ['#',
                            'false', 0, '@', 'nextnode'], null);
                        $node->falsefeedback = $this->import_xml_text($pnxml,
                            ['#', 'false', 0, '#', 'feedback', 0], $format);
                        $node->falsevariables = $format->getpath($pnxml, ['#',
                            'false', 0, '#', 'variables', 0, '#', 'text', 0, '#'],
                            '', true);
                        $node->falsetests = $format->getpath($pnxml, ['#',
                            'false', 0, '#', 'tests', 0, '#', 'text', 0, '#'], '',
                            true);

                        $prt->nodes[$node->name] = $node;
                    }
                    $scene->prts[$prt->name] = $prt;
                }
            }

            $question->scenes[$scene->name] = $scene;
        }
        $variables = [];
        if (isset($xml['#']['stateful'][0]['#']['variable'])) {
            // All sensible Stateful questions should have variables, but 
            // we have some demo materials that do not.
            $variables = $xml['#']['stateful'][0]['#']['variable'];
        }

        // As this is an import process the numbers for variables might not be
        // defined and we need to ensure that they are.
        $usedvarnums = [];

        foreach ($variables as $num => $vxml) {
            $variable       = new stdClass();
            $variable->name = $vxml['@']['name'];
            $variable->type = $format->getpath($vxml, ['@', 'type'], ''
            );
            $variable->initialvalue = $format->getpath($vxml, ['@',
                'initialvalue'], '');
            $variable->number = $format->getpath($vxml, ['@', 'number'],
                '?');
            if ($variable->number !== '?' && $variable->number !== '') {
                $usedvarnums[intval($variable->number)] = true;
            }
            $variable->description = $format->getpath($vxml, ['#',
                'description', 0, '#', 'text', 0, '#'], '', true);
            $question->variables[$variable->name] = $variable;
        }

        $vn = 1;
        while (isset($usedvarnums[$vn])) {
            $vn++;
        }
        foreach ($question->variables as $name => $variable) {
            if ($variable->number === '?' || $variable->number === '') {
                $variable->number = $vn;
                $usedvarnums[$vn] = true;
                while (isset($usedvarnums[$vn])) {
                    $vn++;
                }
            }
        }

        // Generic meta is usesful for tools to have.
        $question->genericmeta = $this->import_xml_text($xml, ['#',
            'stateful', 0, 'meta'], $format);
        $question->compiledcache = ''; // Never exported.

        $question->qtype = $this->name();

        // Note that we just built a "questiondata" styled object
        // as it is simpler to build but as we need to turn it
        // to "fromform" we just need to do so.
        $q = stateful_handling_moodle_questiondata::from($question);
        $fromform = stateful_handling_moodle_formdata::to($q);

        // However we again have an oddity $fromform is now expected to 
        // be and object and not an array...

        return (object) $fromform;
    }

    public function export_to_xml(
        $questiondata,
        qformat_xml $format,
        $extra = null

    ) {
        // Translate old logic so that function signature makes sense.
        $question = $questiondata;
        // This is for attachments.
        $contextid = $question->contextid;

        // Needed for input handling.
        $options = new stack_options();
        $options->set_option('multiplicationsign', $question->options->
            multiplicationsign);
        $options->set_option('complexno', $question->options->complexno);
        $options->set_option('inversetrig', $question->options->inversetrig);
        $options->set_option('matrixparens', $question->options->matrixparens);
        $options->set_option('sqrtsign', (bool) $question->options->sqrtsign);
        $options->set_option('simplify', (bool) $question->options->
            questionsimplify);
        $options->set_option('assumepos', (bool) $question->options->
            assumepositive);
        $options->set_option('assumereal', (bool) $question->options->
            assumereal);

        $r = '<stateful';
        // TODO: Find the part of Moodle that does attribute escaping... or
        // don't to keep portting options open. Maybe create a function.
        $r .= ' statefulversion="' . htmlspecialchars($question->options->
            statefulversion, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ' stackversion="' . htmlspecialchars($question->options->
            stackversion, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ' entryscene="' . htmlspecialchars($question->options->entryscene
            , ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ' questionsimplify="' . htmlspecialchars($question->options->
            questionsimplify, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ' assumepositive="' . htmlspecialchars($question->options->
            assumepositive, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ' assumereal="' . htmlspecialchars($question->options->assumereal
            , ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ' multiplicationsign="' . htmlspecialchars($question->options->
            multiplicationsign, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ' sqrtsign="' . htmlspecialchars($question->options->sqrtsign,
            ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ' complexno="' . htmlspecialchars($question->options->complexno,
            ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ' inversetrig="' . htmlspecialchars($question->options->
            inversetrig, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ' matrixparens="' . htmlspecialchars($question->options->
            matrixparens, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ' parlength="' . htmlspecialchars($question->options->
            parlength, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ' variants="' . htmlspecialchars($question->options->
            variants, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
        $r .= ">\n"; // Open <stateful>.

        $r .= "  <questionvariables>\n" . $format->writetext($question->options
                                                                          ->
            questionvariables, 0) . "\n  </questionvariables>\n";
        $r .=
        "  <questionnote>\n" . $format->writetext($question->options

                                                               ->
            questionnote, 0) . "\n  </questionnote>\n";

        foreach ($question->variables as $variable) {
            $indent = '  ';
            $r .= $indent . '<variable';
            $r .= ' name="' . htmlspecialchars($variable->name, ENT_XML1 |
                ENT_COMPAT, 'UTF-8') . '"';
            $r .= ' type="' . htmlspecialchars($variable->type, ENT_XML1 |
                ENT_COMPAT, 'UTF-8') . '"';
            $r .= ' initialvalue="' . htmlspecialchars($variable->initialvalue,
                ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
            $r .= ' number="' . htmlspecialchars($variable->number, ENT_XML1 |
                ENT_COMPAT, 'UTF-8') . '"';
            $r .= ">\n"; // Open <stateful><variable>;

            $r .= $indent . "  <description>\n" . $format->writetext($variable
                    ->description, 0) . "\n" . $indent . "  </description>\n";

            $r .= $indent . "</variable>\n";
        }

        foreach ($question->scenes as $scene) {
            $indent = '  ';
            $r .= $indent . '<scene';
            $r .= ' name="' . htmlspecialchars($scene->name, ENT_XML1 |
                ENT_COMPAT, 'UTF-8') . '"';
            $r .= ">\n"; // Open <stateful><scene>;

            $indent = '    ';

            $r .= $indent . "<description>\n" . $format->writetext($scene->
                description, 0) . "\n" . $indent . "</description>\n";
            $r .= $indent . "<scenevariables>\n" . $format->writetext($scene->
                scenevariables, 0) . "\n" . $indent . "</scenevariables>\n"
            ;
            $r .= $this->export_xml_text($format, 'scenetext', $scene->
                scenetext, $contextid, 'scenetext', $scene->id);

            foreach ($scene->inputs as $input) {
                $r .= $indent . '<input';
                // As we are working with the raw data we need to first construct the input...
                $inp = stateful_input_controller::get_input_instance($input->type, $input->name);
                if ($inp instanceof stateful_input_teachers_answer_handling) {
                    $inp->set_teachers_answer($input->tans);
                }
                if ($input->options !== '' && is_string($input->options)) {
                    $opts = json_decode($input->options, true);
                } else if (is_array($input->options)) {
                    $opts = [] + $input->options;
                }
                if ($inp instanceof stateful_input_options) {
                    $inp->set_options($opts);
                }

                $ser = $inp->serialize(true);
                foreach ($ser as $key => $value) {
                    if ($key !== 'type' && $key !== 'name') {
                        $r .= ' ' . $key . '="' . htmlspecialchars(json_encode($value), ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
                    } else {
                        $r .= ' ' . $key . '="' . htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
                    }
                }

                $r .= "/>\n";
            }

            foreach ($scene->vboxes as $vbox) {
                $r .= $indent . '<vbox';
                $r .= ' name="' . htmlspecialchars($vbox->name, ENT_XML1 |
                    ENT_COMPAT
                    , 'UTF-8') . '"';
                $r .= ' type="' . htmlspecialchars($vbox->type, ENT_XML1 |
                    ENT_COMPAT, 'UTF-8') . '"';
                $opts = json_decode($vbox->options, true);
                foreach ($opts as $key => $value) {
                    if ($key !== 'type' && $key !== 'name') {
                        if (is_bool($value)) {
                            $r .= ' ' . $key . '="' . ($value ? 'true' :
                                'false') . '"';
                        } else {
                            $r .= ' ' . $key . '="' . htmlspecialchars(json_encode($value),
                                ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
                        }
                    }   
                }

                $r .= "/>\n";
            }

            foreach ($scene->prts as $prt) {
                $r .= $indent . '<prt';
                $r .= ' name="' . htmlspecialchars($prt->name, ENT_XML1 |
                    ENT_COMPAT
                    , 'UTF-8') . '"';
                $r .= ' value="' . htmlspecialchars($prt->value, ENT_XML1 |
                    ENT_COMPAT, 'UTF-8') . '"';
                $r .= ' firstnodename="' . htmlspecialchars($prt->firstnodename
                    , ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
                $r .= ' scoremode="' . htmlspecialchars($prt->scoremode,
                    ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
                $r .= ' scoremodeparameters="' . htmlspecialchars($prt->
                    scoremodeparameters, ENT_XML1 | ENT_COMPAT, 'UTF-8') .
                    '"';
                $r .= ">\n"; // Open <stateful><scene><prt>

                $r .= $indent . "  <feedbackvariables>\n" . $format->writetext(
                    $prt->feedbackvariables, 0) . "\n" . $indent .
                    "  </feedbackvariables>\n";

                $indent = '      ';
                foreach ($prt->nodes as $node) {
                    $r .= $indent . '<node';
                    $r .= ' name="' . htmlspecialchars($node->name, ENT_XML1 |
                        ENT_COMPAT, 'UTF-8') . '"';
                    $r .= ' test="' . htmlspecialchars($node->test, ENT_XML1 |
                        ENT_COMPAT, 'UTF-8') . '"';
                    $r .= ' sans="' . htmlspecialchars($node->sans, ENT_XML1 |
                        ENT_COMPAT, 'UTF-8') . '"';
                    $r .= ' tans="' . htmlspecialchars($node->tans, ENT_XML1 |
                        ENT_COMPAT, 'UTF-8') . '"';
                    $r .= ' options="' . htmlspecialchars($node->options,
                        ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
                    $r .= ' quiet="' . htmlspecialchars($node->quiet, ENT_XML1
                         | ENT_COMPAT, 'UTF-8') . '"';
                    $r .= ">\n"; // Open <stateful><scene><prt><node>

                    $r .= $indent . '  <true';
                    $r .= ' scoremode="' . htmlspecialchars($node->
                        truescoremode, ENT_XML1 | ENT_COMPAT, 'UTF-8') .
                        '"';
                    $r .= ' score="' . htmlspecialchars($node->truescore,
                        ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
                    $r .= ' penaltymode="' . htmlspecialchars($node->
                        truepenaltymode, ENT_XML1 | ENT_COMPAT, 'UTF-8') .
                        '"';
                    $r .= ' penalty="' . htmlspecialchars($node->truepenalty,
                        ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
                    $r .= ' nextnode="' . htmlspecialchars($node->truenextnode,
                        ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
                    $r .= ">\n"; // Open <stateful><scene><prt><node><true>

                    $r .= $indent . "    <variables>\n" . $format->writetext(
                        $node->truevariables, 0) . "\n" . $indent .
                        "  </variables>\n";
                    $r .= $indent . "    <feedback>\n" . $format->writetext(
                        $node->truefeedback, 0) . "\n" . $indent .
                        "  </feedback>\n";
                    $r .= $indent . "    <tests>\n" . $format->writetext($node
                            ->truetests, 0) . "\n" . $indent . "  </tests>\n";

                        $r .= $indent . "  </true>\n";

                        $r .= $indent . '  <false';
                        $r .= ' scoremode="' . htmlspecialchars($node->
                        falsescoremode, ENT_XML1 | ENT_COMPAT, 'UTF-8') .
                        '"';
                    $r .= ' score="' . htmlspecialchars($node->falsescore,
                        ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
                    $r .= ' penaltymode="' . htmlspecialchars($node->
                        falsepenaltymode, ENT_XML1 | ENT_COMPAT, 'UTF-8') .
                        '"';
                    $r .= ' penalty="' . htmlspecialchars($node->falsepenalty,
                        ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
                    $r .= ' nextnode="' . htmlspecialchars($node->falsenextnode
                        , ENT_XML1 | ENT_COMPAT, 'UTF-8') . '"';
                    $r .= ">\n"; // Open <stateful><scene><prt><node><false>

                    $r .= $indent . "    <variables>\n" . $format->writetext(
                        $node->falsevariables, 0) . "\n" . $indent .
                        "  </variables>\n";
                    $r .= $indent . '    <feedback>\n' . $format->writetext(
                        $node->falsefeedback, 0) . "\n" . $indent .
                        "  </feedback>\n";
                    $r .= $indent . "    <tests>\n" . $format->writetext($node
                            ->falsetests, 0) . "\n" . $indent . "  </tests>\n";

                    $r .= $indent . "  </false>\n";
                    $r .= $indent . "</node>\n";
                }

                $indent = '    ';
                $r .= $indent . "</prt>\n";
            }

            $indent = '  ';
            $r .= $indent . "</scene>\n";
        }

        $r .= '</stateful>';
        return $r;
    }

    protected function import_xml_text(
        $xml,
        $path,
        qformat_xml $format
    ) {
        $text         = [];
        $text['text'] = $format->getpath($xml, array_merge($path, ['#',
            'text', 0, '#']), '', true);
        $text['format'] = FORMAT_HTML;
        $text['files']  = $format->import_files($format->getpath($xml,
            array_merge($path, ['#', 'file']), [], false));
        return $text;
    }

    protected function export_xml_text(
        qformat_xml $format,
        $tag,
        $text,
        $contextid,
        $filearea,
        $itemid,
        $indent = '    '
    ) {
        $fs    = get_file_storage();
        $files = $fs->get_area_files($contextid, 'qtype_stateful', $filearea,
            $itemid);

        $output = '';
        $output .= $indent . "<{$tag} {$format->format(FORMAT_HTML)}>\n";
        $output .= $indent . '  ' . $format->writetext($text);
        $output .= $format->write_files($files);
        $output .= $indent . "</{$tag}>\n";

        return $output;
    }
}