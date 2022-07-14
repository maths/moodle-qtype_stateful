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
require_once __DIR__ . '/stacklib.php';
require_once __DIR__ . '/../../behaviour/stateful/behaviour.php';
require_once __DIR__ . '/stateful/handling/testing.php';

defined('MOODLE_INTERNAL') || die();
class qtype_stateful_renderer extends qtype_renderer {

    public function formulation_and_controls(question_attempt $qa,
        question_display_options $options) {
        global $PAGE;

        // Ensure that we have the correct coding.
        $old = mb_internal_encoding();
        if ($old !== 'UTF-8') {
            mb_internal_encoding('UTF-8');
        }

        if ($qa->get_question()->state == null || $qa->get_question()->state->
            get(-1, null) == null) {
            // Here we have to deal with the problem of renderer being called before
            // the behaviour has been able to do its thing. Luckilly, we have access
            // to the question_attempt and can fill in the state.
            // Might also be that behaviour has done its thing but it has done it for
            // some other instance of the question object.
            $sidelinedstate = new qbehaviour_stateful_state_storage($qa,
                $qa->get_question()->get_state_variable_identifiers());
            $qa->get_question()->set_state($sidelinedstate);
        }


        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        // If we are in the preview page provide test-cases as fill options.
        $testthings = '';
        if (strpos($_SERVER['PHP_SELF'],"question/preview.php") !== false && !isset($response['testCaseDisable'])) {
            $filloptions = stateful_handling_testing::test($question, $question->get_state_array());
            $testthings .= '<hr>';
            $testthings .= '<p><strong>Preview test inputs.</strong> Define these in the PRT-nodes, you can also run automated tests that ensure these trigger the matching nodes. <b>Or disable this for now for faster preview: <input type="checkbox" name="' . $qa->get_qt_field_name('testCaseDisable') . '" value="true"/></b></p>';
            $fieldname = $qa->get_qt_field_name('testCaseFill');
            $testthings .= '<input type="hidden" id="'. $fieldname .'" name="'. $fieldname .'" value="-"/>';
            if (isset($filloptions['results'])) {
                $filloptions = $filloptions['results'];
            } else {
                $filloptions = array();
            }
            if (count($filloptions) > 0) {
                $testthings .= '<table>';
                $testthings .= '<thead><tr><th>Fill</th><th>Status</th><th>PRT</th><th>Test-name</th><th>Inputs</th></tr></tr></thead>';
            }
            foreach ($filloptions as $key => $value) {               
                $testthings .= '<tr>';
                // TODO: scripts into a file, maybe build this whole thing from that script...
                $testthings .= '<td><button onclick="document.getElementById(\'' . $fieldname . '\').value = \'' . $value['prt'] . '|' . $value['test'] .  '\';';
                // We need to clear all if we set the fields, also on client side.
                foreach ($question->get_expected_data() as $k => $v) {
                    if (strrpos($k, '_val') === (strlen($k) - 4)) {
                        $testthings .= 'document.getElementById(\'' . $qa->get_qt_field_name($k) . '\').value = \'\';';
                        $testthings .= 'document.getElementById(\'' . $qa->get_qt_field_name($k) . '\').dispatchEvent(new Event(\'change\'));';
                    }
                }

                $testthings .=  'document.getElementById(\'' . $qa->get_qt_field_name('-submit') . '\').click();">Fill</button></td>';
                $testthings .= '<td>' . $value['status'] . '</td>'; 

                $testthings .= '<td style="padding-left:0.4em;">' . $value['prt'] . '</td>';
                $testthings .= '<td style="padding-left:0.4em;">' . $value['test'] . '</td>';
                if (isset($value['inputs'])) {
                    $l = array();
                    foreach ($value['inputs'] as $k => $v) {
                        if ($question->get_current_scene()->inputs[$k]->get_type() === 'button') {
                            $btn = 'unknown button';
                            foreach ($question->get_current_scene()->inputs as $input) {
                                if ($input->get_type() === 'button') {
                                    if ($input->get_option('input-value') === $v) {
                                        $btn = $input->get_option('input-label');
                                    }
                                }
                            }
                            $l[] = 'Fill values and click "' . $btn . '" note may need to fill with empty values.';
                        } else {
                            $l[] = $k . '=' . $v;
                        }
                    }
                    $testthings .= '<td style="padding-left:0.4em;">' . implode(', ', $l) . '</td>';
                } else {
                    $testthings .= '<td style="padding-left:0.4em;">Inactive in this state.</td>';
                }
                $testthings .= '</tr>';
            }
            if (count($filloptions) > 0) {
                $testthings .= '</table>';
            } else {
                $testthings .= '<p>No test inputs defined.</p>';
            }

            // If we got a fill order?
            if (isset($response['testCaseFill']) && strlen($response['testCaseFill']) > 1) {
                $target = explode('|', $response['testCaseFill'], 2);
                foreach ($filloptions as $opt) {
                    if ($opt['prt'] === $target[0] && $opt['test'] === $target[1]) {
                        // So we overwrite whatever there is with these.
                        // Must also kill all validation.
                        foreach ($response as $key => $value) {
                            if (strrpos($key, '_val') === (strlen($key) - 4)) {
                                unset($response[$key]);
                            }
                        }
                        if (isset($opt['inputs'])) {
                            foreach ($opt['inputs'] as $key => $value) {
                                $input = $question->get_current_scene()->inputs[$key];
                                if ($input->get_type() !== 'button') {
                                    // Buttons are special.
                                    $ast = maxima_parser_utils::parse($value);
                                    $r = $input->value_to_response($ast);
                                    foreach ($r as $k => $v) {
                                        $response[$k] = $v;
                                    }
                                }
                            }
                        }
                        break;
                    }
                }
            }
        } else if (strpos($_SERVER['PHP_SELF'],"question/preview.php") !== false && isset($response['testCaseDisable'])) {
            $testthings .= '<hr>';
            $testthings .= '<p><strong>Preview test inputs.</strong> Define these in the PRT-nodes, you can also run automated tests that ensure these trigger the matching nodes. <b>Or disable this for now for faster preview: <input type="checkbox" name="' . $qa->get_qt_field_name('testCaseDisable') . '" value="true" checked="checked"/></b></p>';
        }

        $questiontext = $question->render('scenetext');

        // we need to process that response a bit in the case where a scene has changed
        // as we cannot display inputs as valid before the student has a chance to do
        // something. Bit dirty to access the behaviour vars like this, maybe we should
        // have the state object map to them.
        $currentstep = $qa->get_last_step();
        if ($qa->get_last_behaviour_var('_seqn_pre') < $qa->
            get_last_behaviour_var('_seqn_post') || 
            !$currentstep->has_behaviour_var('submit')) {
            // Add the magic input token. For those inputs that don't do validation at all
            // problem if all the inputs are like that and we have a countter based error
            // handling PRTs.
            $response['%deactivate%'] = true;
        }

        if (!$question->prtsprocessed) {
            // We now have a problem. Probably due to renderer being called for
            // a question object that is not the one that did the input 
            // processing.
            // So redo it to generate PRT-feedback and validation messages.
            // Luckily there is that cache, but wasted cycles anyway.
            $question->process_input($response, true);
        }

        // Replace inputs and validation boxes.
        $qaid  = $qa->get_database_id();
        $prefix = $qa->get_field_prefix();
        $questiontext = $question->inputs->fill_in_placeholders($questiontext, $prefix, $response, $options->readonly);

        // Figure out which PRTs should render feedback, don't render if not 
        // submitted to this now.
        $lastsubmitstep = $qa->get_last_step_with_qt_var('-submit');
        $lastsubmitstepvalues = $lastsubmitstep->get_qt_data();
        /// If we are in a different scene that the last submit we cannot show any feedback.
        $blockallfeedback = $question->get_scene_sequence_number() != $lastsubmitstep->get_behaviour_var('_seqn_pre');
        /// Now compare the raw input for each PRT in the last submitted step to current
        /// to figure out if that PRT has changed.
        $prtdisplay = [];
        foreach ($question->get_prts() as $name => $prt) {
            $prtdisplay[$name] = true;
            $inputs = $question->get_compiled('scene-' . $question->get_current_scene()->name . '-prt-' .
            $name . '|inputs');
            // All inputs use the full name or a prefix of "$name__".
            foreach ($inputs as $iname => $ignore) {
                // Not same base input-field.
                if (isset($response[$iname]) !== isset($lastsubmitstepvalues[$iname]) ||
                    (isset($response[$iname]) && $response[$iname] !== $lastsubmitstepvalues[$iname])) {
                    $prtdisplay[$name] = false;
                    break;
                }
                // Check the prefixed ones. Note needs to be checked both ways.
                foreach ($response as $key => $value) {
                    if (strpos($key, $iname . '__') === 0) {
                        if (isset($response[$key]) !== isset($lastsubmitstepvalues[$key]) ||
                            (isset($response[$key]) && $response[$key] !== $lastsubmitstepvalues[$key])) {
                            $prtdisplay[$name] = false;
                            break;
                        }
                    }
                }
                foreach ($lastsubmitstepvalues as $key => $value) {
                    if (strpos($key, $iname . '__') === 0) {
                        if (isset($response[$key]) !== isset($lastsubmitstepvalues[$key]) ||
                            (isset($response[$key]) && $response[$key] !== $lastsubmitstepvalues[$key])) {
                            $prtdisplay[$name] = false;
                            break;
                        }
                    }
                }
            }
        }

        // Replace PRTs.
        foreach ($question->get_prts() as $name => $prt) {
            // Note that we ignore the feedback option in Stateful questions you
            // do not not display the feedback if it exists.
            if (mb_strpos($questiontext, "[[feedback:$name]]") !==
                false) {
                $feedback = '';
                // Only generate and render if required.
                if (!$blockallfeedback && $prtdisplay[$name]) {
                    $feedback = $question->render("prt-$name", $response);
                }
                if (trim($feedback) !== '') {
                    $feedback = html_writer::nonempty_tag('div', $feedback,
                        ['class' => 'statefulprtfeedback']);
                }

                $questiontext = str_replace("[[feedback:$name]]", $feedback,
                    $questiontext);
            }
        }

        // Using the STACK display logic requires a qtype_stack_renderer,
        // luckilly it is not used for anything so we can just fake it.
        $dummyrenderer = new qtype_stack_renderer($PAGE, null);

        // Do format things, but the format will always be the (X)HTML-format.
        $questiontext = $question->format_text(
            stack_maths::process_display_castext($questiontext, $dummyrenderer)
            ,
            FORMAT_HTML,
            $qa, 'question', 'questiontext', $question->id);

        // Load in scripts for inputs.
        $question->inputs->apply_scripts($prefix, $PAGE);

        /*
        // Append some debug info.
        $questiontext .= '<hr/><h4>State</h4>';
        $questiontext .= $question->state->string_dump();
         */
        /*
        $questiontext .= '<hr/><h4>Compiled</h4>';
        $questiontext .= $question->compiled_dump();
         */

        // Some parameters for AJAX validation.
        $details = '<div id="' . $qa->get_field_prefix() . '__tech_details" style="display:none;" data-seqn="' . $question->get_scene_sequence_number($question->state) . '" data-qaid="' . $qaid . '"></div>';

        if ($old !== 'UTF-8') {
            // Due to good manners we return the setting if it was
            // something else.
            mb_internal_encoding($old);
        }


        return $questiontext . $testthings . $details;
    }

    public function specific_feedback(question_attempt $qa) {
        // This will not happen as the specific feedback will be inline.
        return '';
    }

    public function correct_response(question_attempt $qa) {
        return '';
    }


    public function general_feedback(question_attempt $qa) {
        global $PAGE;

        $question = $qa->get_question();
        if (empty($question->generalfeedback)) {
            return '';
        }

        return $question->render('modelsolution');
    }

}
