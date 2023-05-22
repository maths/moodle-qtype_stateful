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


/**
 * This tool takes a Stateful question and handles the act of saving it to Moodle.
 */

require_once __DIR__ . '/../../stacklib.php';
require_once __DIR__ . '/../core/statevariable.class.php';
require_once __DIR__ . '/../core/scene.class.php';
require_once __DIR__ . '/../core/prt.class.php';
require_once __DIR__ . '/../core/prt_node.class.php';
require_once $CFG->libdir . '/filelib.php';
require_once __DIR__ . '/../../questiontype.php';
require_once __DIR__ . '/../../question.php';

class stateful_handling_moodle {

    // Does the whole save process for a question, by default creates a new question every time
    // but can be used to write over old ones... Returns the question as loaded from the db

    //// Note that this cannot use the question type logic as it has different assumptions
    //// about attachments. Also this works on questions not on form objects.
    public static function save(
        qtype_stateful_question $questiontosave,
        int $draftfilearea,
        bool $overold = false
    ): qtype_stateful_question {
        global $DB, $USER;

        // Note that much of this logic allows one to upgrade existing questions without
        // giving database identifiers for the subobjects, but one must define the identifier
        // of the question.

        // MUST have category to work... id also if writing over.

        // What we do first is to generate the question object if necessary and match the
        // base parameters to it.

        $question = new stdClass();
        if (!empty($questiontosave->id) && $overold) {
            $question = $DB->get_record('question', ['id' => $questiontosave->
                id], '*', IGNORE_MISSING);
            question_require_capability_on($question, 'edit');

            if ($question === false) {
                $question = new stdClass();
            }
        }

        $question->category = $questiontosave->category;

        if (!$overold && $questiontosave->id) {
            $question->id = $questiontosave->id;
        }

        $context = self::get_context_by_category_id($question->category);

        // This default implementation is suitable for most
        // question types.
        // First, save the basic question itself.
        $question->qtype   = 'stateful';
        $question->name    = trim($questiontosave->name);
        $question->parent  = isset($questiontosave->parent) ? $questiontosave->parent : 0;
        $question->length  = 1;
        $question->penalty = isset($questiontosave->penalty) ? $questiontosave
            ->penalty : 0;
        // The trim call below has the effect of casting any strange values received,
        // like null or false, to an appropriate string, so we only need to test for
        // missing values. Be careful not to break the value '0' here.
        $question->questiontext       = $questiontosave->questiontext;
        $question->questiontextformat = $questiontosave->questiontextformat;

        $question->generalfeedback       = $questiontosave->generalfeedback;
        $question->generalfeedbackformat = $questiontosave->generalfeedbackformat;

        if (isset($questiontosave->defaultmark)) {
            $question->defaultmark = $questiontosave->defaultmark;
        }
        $question->questionbankentryid = $questiontosave->questionbankentryid;

        $questionbankentry = null;
        if (isset($question->id)) {
            $oldparent = $question->id;
            if (!empty($question->id)) {
                // Get the bank entry record where the question is referenced.
                $questionbankentry = get_question_bank_entry($question->id);
            }
        }

        // No ID after this as this will be created as new.
        unset($question->id);
        unset($questiontosave->id);

        // Set the unique code.
        $question->stamp       = make_unique_id_code();
        $question->createdby   = $USER->id;
        $question->timecreated = time();
        $question->id          = $DB->insert_record('question', $question);

        // Update the entrys.
        if (!$questionbankentry) {
            // Create a record for question_bank_entries, question_versions and question_references.
            $questionbankentry = new \stdClass();
            $questionbankentry->questioncategoryid = $questiontosave->category;
            $questionbankentry->idnumber = $questiontosave->idnumber;
            $questionbankentry->ownerid = $question->createdby;
            $questionbankentry->id = $DB->insert_record('question_bank_entries', $questionbankentry);
        } else {
            $questionbankentryold = new \stdClass();
            $questionbankentryold->id = $questionbankentry->id;
            $questionbankentryold->idnumber = $question->idnumber;
            $DB->update_record('question_bank_entries', $questionbankentryold);
        }

        // Create question_versions records.
        $questionversion = new \stdClass();
        $questionversion->questionbankentryid = $questionbankentry->id;
        $questionversion->questionid = $question->id;
        // Get the version and status from the parent question if parent is set.
        if (!$question->parent) {
            // Get the status field. It comes from the form, but for testing we can.
            $status = $form->status ?? $question->status ??
                \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
            $questionversion->version = get_next_version($questionbankentry->id);
            $questionversion->status = $status;
        } else {
            $parentversion = get_question_version($question->parent);
            $questionversion->version = $parentversion[array_key_first($parentversion)]->version;
            $questionversion->status = $parentversion[array_key_first($parentversion)]->status;
        }
        $questionversion->id = $DB->insert_record('question_versions', $questionversion);


        // Now, whether we are updating a existing question, or creating a new
        // one, we have to do the files processing and update the record.
        // Question already exists, update.
        $question->modifiedby   = $USER->id;
        $question->timemodified = time();

        $question->questiontext = self::save_files($questiontosave->
            questiontext, $draftfilearea, $context->id, 'question',
            'questiontext',
            $question->id);

        $question->generalfeedback = self::save_files($questiontosave->
            generalfeedback, $draftfilearea, $context->id, 'question',
            'generalfeedback',
            $question->id);

        $DB->update_record('question', $question);

        $questiontosave->id              = $question->id;
        $questiontosave->category        = $question->category;
        $questiontosave->qtype           = $question->qtype;
        $questiontosave->questiontext    = $question->questiontext;
        $questiontosave->generalfeedback = $question->generalfeedback;
        $questiontosave->context         = $context;

        if (is_array($questiontosave->variants)) {
            $questiontosave->variants = json_encode($questiontosave->variants);
        }

        /// Store options
        $options = $DB->get_record('qtype_stateful_options', ['questionid' =>
            $question->id], '*', IGNORE_MISSING);
        if ($options === false) {
            $options                    = new stdClass();
            $options->questionid        = $question->id;
            $options->questionvariables = $questiontosave->questionvariables;
            $options->questionnote      = $questiontosave->questionnote;
            $options->questionsimplify  = $questiontosave->options->
            get_option('simplify');
            $options->assumepositive = $questiontosave->options->
            get_option('assumepos');
            $options->assumereal = $questiontosave->options->
            get_option('assumereal');
            $options->multiplicationsign = $questiontosave->options->
            get_option('multiplicationsign');
            $options->sqrtsign = $questiontosave->options->
            get_option('sqrtsign');
            $options->complexno = $questiontosave->options->
            get_option('complexno');
            $options->inversetrig = $questiontosave->options->
            get_option('inversetrig');
            $options->matrixparens = $questiontosave->options->
            get_option('matrixparens');
            $options->entryscene      = $questiontosave->entryscene;
            $options->stackversion    = $questiontosave->stackversion;
            $options->statefulversion = $questiontosave->statefulversion;
            $options->variants        = $questiontosave->variants;
            $options->id              = $DB->insert_record(
                'qtype_stateful_options', $options, true);
        }

        if ($questiontosave->genericmeta !== null && !is_string($questiontosave
            ->genericmeta)) {
            $questiontosave->genericmeta = json_encode($questiontosave->
                genericmeta);
        } else if ($questiontosave->genericmeta === null || $questiontosave->
            genericmeta === '') {
            $questiontosave->genericmeta = '{}';
        }
        if ($questiontosave->compiledcache !== null && !is_string(
            $questiontosave->compiledcache)) {
            $questiontosave->compiledcache = json_encode($questiontosave->
                compiledcache);
        } else if ($questiontosave->compiledcache === null || $questiontosave->
            compiledcache === '') {
            $questiontosave->compiledcache = '{}';
        }

        $options->questionvariables = $questiontosave->questionvariables;
        $options->questionnote      = $questiontosave->questionnote;
        $options->questionsimplify  = $questiontosave->options->get_option(
            'simplify');
        $options->assumepositive = $questiontosave->options->get_option(
            'assumepos');
        $options->assumereal = $questiontosave->options->get_option(
            'assumereal');
        $options->multiplicationsign = $questiontosave->options->get_option(
            'multiplicationsign');
        $options->sqrtsign = $questiontosave->options->get_option(
            'sqrtsign');
        $options->complexno = $questiontosave->options->get_option(
            'complexno');
        $options->inversetrig = $questiontosave->options->get_option(
            'inversetrig');
        $options->matrixparens = $questiontosave->options->get_option(
            'matrixparens');
        $options->entryscene      = $questiontosave->entryscene;
        $options->stackversion    = $questiontosave->stackversion;
        $options->statefulversion = $questiontosave->statefulversion;
        $options->parlength       = $questiontosave->parlength;
        // Note that the compiled cache should be cleared and it is assumed that the given
        // new content either has cleared it or provides new values.
        $options->compiledcache = $questiontosave->compiledcache;
        $options->genericmeta   = $questiontosave->genericmeta;
        $options->variants      = $questiontosave->variants;


        $DB->update_record('qtype_stateful_options', $options);

        /// Store variables
        $current = $DB->get_records('qtype_stateful_variables',
            ['questionid' => $question->id], 'id ASC', '*');

        $orderwanted = array_keys($questiontosave->variables);
        $orderhad    = [];
        $storedvars  = [];
        foreach ($current as $sv) {
            $orderhad[]            = $sv->name;
            $storedvars[$sv->name] = $sv;
        }
        if ($orderwanted === $orderhad) {
            // All fine
        } else {
            // Rename all rows
            $i          = 0;
            $todelete   = [];
            $storedvars = [];
            // First handle the uniquenes by overwriting previous.
            foreach ($current as $sv) {
                if ($i >= count($orderwanted) || $sv->name !== $orderwanted[$i]
                ) {
                    $sv->name = str_pad("-$i", 250, ' -_');
                    // Do handle the uniquenes of the number also
                    $sv->number = -1 - $sv->number;
                    $DB->update_record('qtype_stateful_variables', $sv);
                }
                $i++;
            }
            // Then rewrite in correct order.
            $i = 0;
            foreach ($current as $sv) {
                if ($i < count($orderwanted)) {
                    if ($sv->name !== $orderwanted[$i]) {
                        $sv->name = $orderwanted[$i];
                        $DB->update_record('qtype_stateful_variables', $sv);
                    }
                    $storedvars[$sv->name] = $sv;
                } else {
                    $todelete[$sv->id] = $sv;
                }
                $i++;
            }
            // Delete extras
            if (count($todelete) > 0) {
                list($test, $params) = $DB->get_in_or_equal(array_keys(
                    $todelete));
                $params[] = $question->id;
                $DB->delete_records_select('qtype_stateful_variables',
                    'id ' . $test . ' AND questionid = ?', $params);
            }
        }

        foreach ($questiontosave->variables as $sv) {
            $row = null;
            if (array_key_exists($sv->name, $storedvars)) {
                $row    = $storedvars[$sv->name];
                $sv->id = $row->id;
            } else {
                $row               = new stdClass();
                $row->questionid   = $question->id;
                $row->name         = $sv->name;
                $row->type         = $sv->type;
                $row->number       = $sv->number;
                $row->description  = $sv->description;
                $row->initialvalue = $sv->initialvalue;
            }

            $row->name         = $sv->name;
            $row->type         = $sv->type;
            $row->number       = $sv->number;
            $row->description  = $sv->description;
            $row->initialvalue = $sv->initialvalue;

            if (isset($row->id)) {
                $DB->update_record('qtype_stateful_variables', $row);
            } else {
                $row->id = $DB->insert_record
                    ('qtype_stateful_variables', $row);
                $questiontosave->variables[$row->name]->id = $row->id;
            }
        }

        /// Then the scenes.
        $current = $DB->get_records('qtype_stateful_scenes',
            ['questionid' => $question->id], 'id ASC', '*');

        $orderwanted  = array_keys($questiontosave->scenes);
        $orderhad     = [];
        $storedscenes = [];
        foreach ($current as $scene) {
            $orderhad[]                 = $scene->name;
            $storedscenes[$scene->name] = $scene;
        }
        if ($orderwanted === $orderhad) {
            // All fine
        } else {
            // Rename all rows
            $i            = 0;
            $todelete     = [];
            $storedscenes = [];
            // First handle the uniquenes by overwriting previous.
            foreach ($current as $scene) {
                if ($i >= count($orderwanted) || $scene->name !== $orderwanted[
                    $i]) {
                    $scene->name = str_pad("-$i", 250, ' -_');
                    $DB->update_record('qtype_stateful_scenes', $scene);
                }
                $i++;
            }
            // Then rewrite in correct order.
            $i = 0;
            foreach ($current as $scene) {
                if ($i < count($orderwanted)) {
                    if ($scene->name !== $orderwanted[$i]) {
                        $scene->name = $orderwanted[$i];
                        $DB->update_record('qtype_stateful_scenes', $scene);
                    }
                    $storedscenes[$scene->name] = $scene;
                } else {
                    $todelete[$scene->id] = $scene;
                }
                $i++;
            }
            // Delete extras
            if (count($todelete) > 0) {
                // When throwing away scenes one needs to throw away quite alot.
                $prts = $DB->get_records_list('qtype_stateful_prts', 'sceneid',
                    array_keys($todelete));
                foreach ($prts as $prtid => $prt) {
                    $DB->delete_records('qtype_stateful_prt_nodes', ['prtid' =>
                        $prtid]);
                }

                list($test, $params) = $DB->get_in_or_equal(array_keys(
                                                                   $todelete));
                $DB->delete_records_select('qtype_stateful_inputs',
                    'sceneid ' . $test, $params);
                $DB->delete_records_select('qtype_stateful_vboxes',
                    'sceneid ' . $test, $params);
                $DB->delete_records_select('qtype_stateful_prts',
                    'sceneid ' . $test, $params);

                $params[] = $question->id;

                $DB->delete_records_select('qtype_stateful_scenes',
                    'id ' . $test . ' AND questionid = ?', $params);
                $current = $DB->get_records('qtype_stateful_scenes',
                    ['questionid' => $question->id], 'id ASC', '*');
            }
        }

        foreach ($questiontosave->scenes as $scene) {
            $row = null;
            if (array_key_exists($scene->name, $storedscenes)) {
                $row       = $storedscenes[$scene->name];
                $scene->id = $row->id;
            } else {
                $row                 = new stdClass();
                $row->questionid     = $question->id;
                $row->name           = $scene->name;
                $row->scenevariables = $scene->scenevariables;
                $row->scenetext      = ''; // We first need an identifier to tie the files.
                $row->description    = $scene->description;
                $row->id             = $DB->insert_record(
                    'qtype_stateful_scenes', $row);
                $scene->id = $row->id;
            }

            $row->scenetext = self::save_files($scene->scenetext,
                $draftfilearea, $context->id, 'qtype_stateful', 'scenetext',
                $scene->id);
            $row->name           = $scene->name;
            $row->scenevariables = $scene->scenevariables;
            $row->description    = $scene->description;
            $DB->update_record('qtype_stateful_scenes', $row);

            // Inputs,
            $current = $DB->get_records('qtype_stateful_inputs',
                ['sceneid' => $scene->id], 'id ASC', '*');

            $orderwanted  = array_keys($scene->inputs);
            $orderhad     = [];
            $storedinputs = [];
            foreach ($current as $input) {
                $orderhad[]                 = $input->name;
                $storedinputs[$input->name] = $input;
            }
            if ($orderwanted === $orderhad) {
                // All fine
            } else {
                // Rename all rows
                $i            = 0;
                $todelete     = [];
                $storedinputs = [];
                // First handle the uniquenes by overwriting previous.
                foreach ($current as $input) {
                    if ($i >= count($orderwanted) || $input->name !==
                        $orderwanted[$i]) {
                        $input->name = str_pad("-$i", 250, ' -_');
                        $DB->update_record('qtype_stateful_inputs', $input);
                    }
                    $i++;
                }
                // Then rewrite in correct order.
                $i = 0;
                foreach ($current as $input) {
                    if ($i < count($orderwanted)) {
                        if ($input->name !== $orderwanted[$i]) {
                            $input->name = $orderwanted[$i];
                            $DB->update_record('qtype_stateful_inputs', $input)
                            ;
                        }
                        $storedinputs[$input->name] = $input;
                    } else {
                        $todelete[$input->id] = $input;
                    }
                    $i++;
                }
                // Delete extras
                if (count($todelete) > 0) {
                    list($test, $params) = $DB->get_in_or_equal(array_keys(
                        $todelete));
                    $params[] = $scene->id;
                    $DB->delete_records_select('qtype_stateful_inputs',
                        'id ' . $test . ' AND sceneid = ?', $params);
                }
            }

            foreach ($scene->inputs as $name => $input) {
                $row = null;
                if (array_key_exists($name, $storedinputs)) {
                    $row       = $storedinputs[$name];
                    $input->id = $row->id;
                } else {
                    $row          = new stdClass();
                    $row->sceneid = $scene->id;
                    $row->name    = $name;
                    $row->type    = $input->get_type();
                    $row->tans    = '-';
                    
                    $row->options = '{}';
                    $row->id      = $DB->insert_record('qtype_stateful_inputs',
                        $row);
                    $input->id = $row->id;
                }

                $row->name = $name;
                $row->type = $input->get_type();

                $ser = $input->serialize(true);
                if (isset($ser['tans'])) {
                    $row->tans = $ser['tans'];
                }

                unset($ser['type']);
                unset($ser['tans']);
                unset($ser['name']);
                $row->options = json_encode($ser);

                $DB->update_record('qtype_stateful_inputs', $row);
            }

            // Vboxes,
            $current = $DB->get_records('qtype_stateful_vboxes',
                ['sceneid' => $scene->id], 'id ASC', '*');

            $orderwanted  = array_keys($scene->vboxes);
            $orderhad     = [];
            $storedvboxes = [];
            foreach ($current as $vbox) {
                $orderhad[]                 = $vbox->name;
                $storedvboxes[$vbox->name] = $vbox;
            }
            if ($orderwanted === $orderhad) {
                // All fine
            } else {
                // Rename all rows
                $i            = 0;
                $todelete     = [];
                $storedinputs = [];
                // First handle the uniquenes by overwriting previous.
                foreach ($current as $vbox) {
                    if ($i >= count($orderwanted) || $vbox->name !==
                        $orderwanted[$i]) {
                        $vbox->name = str_pad("-$i", 250, ' -_');
                        $DB->update_record('qtype_stateful_vboxes', $vbox);
                    }
                    $i++;
                }
                // Then rewrite in correct order.
                $i = 0;
                foreach ($current as $vbox) {
                    if ($i < count($orderwanted)) {
                        if ($vbox->name !== $orderwanted[$i]) {
                            $vbox->name = $orderwanted[$i];
                            $DB->update_record('qtype_stateful_vboxes', $vbox)
                            ;
                        }
                        $storedinputs[$vbox->name] = $vbox;
                    } else {
                        $todelete[$vbox->id] = $vbox;
                    }
                    $i++;
                }
                // Delete extras
                if (count($todelete) > 0) {
                    list($test, $params) = $DB->get_in_or_equal(array_keys(
                        $todelete));
                    $params[] = $scene->id;
                    $DB->delete_records_select('qtype_stateful_vboxes',
                        'id ' . $test . ' AND sceneid = ?', $params);
                }
            }

            foreach ($scene->vboxes as $name => $vbox) {
                $row = null;
                if (array_key_exists($name, $storedvboxes)) {
                    $row       = $storedvboxes[$name];
                } else {
                    $row          = new stdClass();
                    $row->sceneid = $scene->id;
                    $row->name    = $vbox->get_name();
                    $row->type    = $vbox->get_type();
                    $row->options = '{}';
                    $row->id      = $DB->insert_record('qtype_stateful_vboxes',
                        $row);
                }

                $row->name = $vbox->get_name();
                $row->type = $vbox->get_type();

                $ser = $vbox->serialize();

                unset($ser['type']);
                unset($ser['name']);
                $row->options = json_encode($ser);

                $DB->update_record('qtype_stateful_vboxes', $row);
            }

            // PRTs,
            $current = $DB->get_records('qtype_stateful_prts',
                ['sceneid' => $scene->id], 'id ASC', '*');

            $orderwanted = array_keys($scene->prts);
            $orderhad    = [];
            $storedprts  = [];
            foreach ($current as $prt) {
                $orderhad[]             = $prt->name;
                $storedprts[$prt->name] = $prt;
            }
            if ($orderwanted === $orderhad) {
                // All fine
            } else {
                // Rename all rows
                $i          = 0;
                $todelete   = [];
                $storedprts = [];
                // First handle the uniquenes by overwriting previous.
                foreach ($current as $prt) {
                    if ($i >= count($orderwanted) || $prt->name !==
                        $orderwanted[$i]) {
                        $prt->name = str_pad("-$i", 250, ' -_');
                        $DB->update_record('qtype_stateful_prts', $prt);
                    }
                    $i++;
                }
                // Then rewrite in correct order.
                $i = 0;
                foreach ($current as $prt) {
                    if ($i < count($orderwanted)) {
                        if ($prt->name !== $orderwanted[$i]) {
                            $prt->name = $orderwanted[$i];
                            $DB->update_record('qtype_stateful_prts', $prt);
                        }
                        $storedprts[$prt->name] = $prt;
                    } else {
                        $todelete[$prt->id] = $prt;
                    }
                    $i++;
                }
                // Delete extras
                if (count($todelete) > 0) {
                    foreach ($todelete as $id => $prt) {
                        $DB->delete_records('qtype_stateful_prt_nodes', [
                            'prtid' => $id]);
                    }

                    list($test, $params) = $DB->get_in_or_equal(array_keys(
                        $todelete));
                    $params[] = $scene->id;
                    $DB->delete_records_select('qtype_stateful_prts',
                        'id ' . $test . ' AND sceneid = ?', $params);
                }
            }

            foreach ($scene->prts as $prt) {
                $row = null;
                if (array_key_exists($prt->name, $storedprts)) {
                    $row     = $storedprts[$prt->name];
                    $prt->id = $row->id;
                } else {
                    $row                      = new stdClass();
                    $row->sceneid             = $scene->id;
                    $row->name                = $prt->name;
                    $row->value               = $prt->value;
                    $row->feedbackvariables   = $prt->feedbackvariables;
                    $row->firstnodename       = $prt->root->name;
                    $row->scoremode           = $prt->scoremode;
                    $row->scoremodeparameters = $prt->scoremodeparameters;
                    $row->id                  = $DB->insert_record(
                        'qtype_stateful_prts', $row);
                    $prt->id = $row->id;
                }
                $row->name                = $prt->name;
                $row->value               = $prt->value;
                $row->feedbackvariables   = $prt->feedbackvariables;
                $row->firstnodename       = $prt->root->name;
                $row->scoremode           = $prt->scoremode;
                $row->scoremodeparameters = $prt->scoremodeparameters === null
                ? '' : $prt->scoremodeparameters;
                $DB->update_record('qtype_stateful_prts', $row);

                // Nodes
                $current = $DB->get_records(
                    'qtype_stateful_prt_nodes', ['prtid' => $prt->id], 'id ASC'
                );
                $orderwanted = array_keys($prt->nodes);
                $orderhad    = [];
                $storednodes = [];
                foreach ($current as $node) {
                    $orderhad[]               = $node->name;
                    $storednodes[$node->name] = $node;
                }
                if ($orderwanted === $orderhad) {
                    // All fine
                } else {
                    // Rename all rows
                    $i           = 0;
                    $todelete    = [];
                    $storednodes = [];
                    // First handle the uniquenes by overwriting previous.
                    foreach ($current as $node) {
                        if ($i >= count($orderwanted) || $node->name !==
                            $orderwanted[$i]) {
                            $node->name = str_pad("-$i", 250, ' -_');
                            $DB->update_record('qtype_stateful_prt_nodes',
                                $node);
                        }
                        $i++;
                    }
                    // Then rewrite in correct order.
                    $i = 0;
                    foreach ($current as $node) {
                        if ($i < count($orderwanted)) {
                            if ($node->name !== $orderwanted[$i]) {
                                $node->name = $orderwanted[$i];
                                $DB->update_record('qtype_stateful_prt_nodes',
                                    $node);
                            }
                            $storednodes[$node->name] = $node;
                        } else {
                            $todelete[$node->id] = $node;
                        }
                        $i++;
                    }
                    // Delete extras
                    if (count($todelete) > 0) {
                        list($test, $params) = $DB->get_in_or_equal(array_keys(
                            $todelete));
                        $params[] = $prt->id;
                        $DB->delete_records_select('qtype_stateful_prt_nodes',
                            'id ' . $test . ' AND prtid = ?', $params);
                    }
                }
                foreach ($prt->nodes as $node) {
                    $row = null;

                    // JSON string unpacking may have happened with the tests.
                    if (!is_string($node->truetests) && $node->truetests !==
                        null) {
                        $node->truetests = json_encode($node->truetests);
                    }
                    if (!is_string($node->falsetests) && $node->falsetests !==
                        null) {
                        $node->falsetests = json_encode($node->falsetests);
                    }

                    if (array_key_exists($node->name, $storednodes)) {
                        $row      = $storednodes[$node->name];
                        $node->id = $row->id;

                    } else {
                        $row        = new stdClass();
                        $row->prtid = $prt->id;
                        $row->name  = $node->name;
                        $row->test  = $node->test;
                        $row->sans  = $node->sans;
                        $row->tans  = $node->tans === null ? '' : $node->tans
                        ;
                        $row->options = $node->options === null ? '' :

                        $row->truefeedback  = '';
                        $row->truevariables = '';
                        $row->truetests     = '[]';

                        $row->falsefeedback  = '';
                        $row->falsevariables = '';
                        $row->falsetests     = '[]';

                        $row->id = $DB->insert_record(
                            'qtype_stateful_prt_nodes', $row);
                        $node->id = $row->id;
                    }

                    if (!is_string($node->truetests) && $node->truetests !==
                        null) {
                        $node->truetests = json_encode($node->truetests);
                    }
                    if (!is_string($node->falsetests) && $node->falsetests !==
                        null) {
                        $node->falsetests = json_encode($node->falsetests);
                    }

                    $row->name = $node->name;
                    $row->test = $node->test;
                    $row->sans = $node->sans;

                    $row->tans = $node->tans === null ? '' : $node->tans;

                    $row->options = $node->options === null ? '' :
                    $node->options;
                    $row->quiet           = $node->quiet;
                    $row->truescoremode   = $node->truescoremode;
                    $row->truescore       = $node->truescore;
                    $row->truepenaltymode = $node->truepenaltymode;
                    $row->truepenalty     = $node->truepenalty;
                    $row->truenextnode    = $node->truenext;
                    $row->truevariables   = $node->truevariables === null ? ''
                    : $node->truevariables;
                    $row->truetests = $node->truetests === null ? '[]' :
                    $node->truetests;
                    $row->falsescoremode   = $node->falsescoremode;
                    $row->falsescore       = $node->falsescore;
                    $row->falsepenaltymode = $node->falsepenaltymode;
                    $row->falsepenalty     = $node->falsepenalty;
                    $row->falsenextnode    = $node->falsenext;
                    $row->falsevariables   = $node->falsevariables === null ?
                    '' : $node->falsevariables;
                    $row->falsetests = $node->falsetests === null ? '[]'
                    : $node->falsetests;

                    $row->truefeedback = self::save_files($node->truefeedback
                        === null ? '' : $node->truefeedback, $draftfilearea,
                        $context->id,
                        'qtype_stateful', 'prtnodetruefeedback', $node->id);
                    $row->falsefeedback = self::save_files($node->falsefeedback
                        === null ? '' : $node->falsefeedback, $draftfilearea,
                        $context->id,
                        'qtype_stateful', 'prtnodefalsefeedback', $node->id);

                    $DB->update_record('qtype_stateful_prt_nodes', $row);
                }
            }
        }

        // Invalidate cache.
        cache_helper::invalidate_by_definition('core', 'questiondata', [], [
            $questiontosave->id]);

        // Load it again.
        return question_bank::load_question($question->id);
    }

    public static function questiondata_to_object($questiondata, qtype_stateful_question $question = null): qtype_stateful_question {
        
        // We may or may not have an existing question object.
        if ($question === null) {
            $question = new qtype_stateful_question();
            $question->qtype = new qtype_stateful();

            // Some defaults. These are not null in database yet have no default there.
            $question->questiontext      = '';
            $question->generalfeedback   = '';
            $question->questionvariables = '';
        }

        // First the question_type->initialise_question_instance-default handling from base question_type.
        // Note by doing it like this we do not get the normal option collection.
        // Also we will probably have this breaking down when something above changes.
        $question->id = $questiondata->id;
        $question->category = $questiondata->category;
        $question->contextid = $questiondata->contextid;
        $question->parent = $questiondata->parent;
        $question->qtype = new qtype_stateful();
        $question->name = $questiondata->name;
        $question->questiontext = $questiondata->questiontext;
        $question->questiontextformat = $questiondata->questiontextformat;
        $question->generalfeedback = $questiondata->generalfeedback;
        $question->generalfeedbackformat = $questiondata->generalfeedbackformat;
        $question->defaultmark = $questiondata->defaultmark + 0;
        $question->length = $questiondata->length;
        $question->penalty = $questiondata->penalty;
        $question->stamp = $questiondata->stamp;
        $question->version = $questiondata->version;
        $question->hidden = $questiondata->hidden;
        if (isset($questiondata->idnumber)) {
            $question->idnumber = $questiondata->idnumber;
        }
        $question->timecreated = $questiondata->timecreated;
        $question->timemodified = $questiondata->timemodified;
        $question->createdby = $questiondata->createdby;
        $question->modifiedby = $questiondata->modifiedby;

        // Then do some colelction and initialsie the nested stuff.
        $question->questionvariables = $questiondata->options->questionvariables;
        $question->questionnote    = $questiondata->options->questionnote;
        $question->entryscene      = $questiondata->options->entryscene;
        $question->stackversion    = $questiondata->options->stackversion;
        $question->statefulversion = $questiondata->options->statefulversion;
        $question->compiledcache   = $questiondata->options->compiledcache;
        $question->genericmeta     = $questiondata->options->genericmeta;
        $question->parlength       = $questiondata->options->parlength;
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

        return $question;
    }


    // Saves files and rewrites the urls to @@PLUGINFILE@@
    // NOTE: we do not yet support attachements, this code is unlikely to work
    // it has never been tested.
    protected static function save_files(
        string $text,
        int $draftfilearea,
        int $contextid,
        string $component,
        string $filearea,
        int $itemid
    ): string {
        // TODO....
        return $text;
    }

    /**
     * Get question context by category id
     * @param int $category
     * @return object $context
     */
    protected static function get_context_by_category_id($category) {
        global $DB;
        $contextid = $DB->get_field('question_categories', 'contextid', ['id'
                                                                => $category]);
        $context   = context::instance_by_id($contextid, IGNORE_MISSING);
        return $context;
    }

}