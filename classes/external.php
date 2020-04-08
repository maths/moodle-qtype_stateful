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
namespace qtype_stateful;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once $CFG->libdir . '/externallib.php';
require_once __DIR__ . '/../../../../config.php';
require_once $CFG->libdir . '/questionlib.php';
require_once __DIR__ . '/../stacklib.php';
require_once __DIR__ . '/../../../behaviour/stateful/behaviour.php';

require_login();


/**
 * External API for AJAX-validation calls.
 *
 * @copyright  2019 Matti Harjula
 * @copyright  2019 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends \external_api {

    /**
     * Returns parameter types for validate_input function.
     *
     * @return \external_function_parameters Parameters
     */
    public static function validate_input_parameters() {
        return new \external_function_parameters([
            'qaid' => new \external_value(PARAM_INT, 'Question attempt id'),
            'seqn' => new \external_value(PARAM_INT, 'Sequence number for the stateful logic'),
            'data' => new \external_value(PARAM_RAW, 'The data describing all input values.')
        ]);
    }

    /**
     * Returns result type for validate_input function.
     *
     * @return \external_description Result type
     */
    public static function validate_input_returns() {
        return new \external_single_structure([
            'val_update' => new \external_value(PARAM_RAW, 'New values for _val fields.'),
            'vbox_update' => new \external_value(PARAM_RAW, 'New values for vboxes.')
        ]);
    }
    /**
     * Validates Stateful (STACK input2) question type input data.
     *
     * @param int $qaid Question attempt id
     * @param int $seqn Sequence nubmer in stateful sequence
     * @param string $data the values of the inputs.
     * @return array Array of updates to _val fields and vboxes.
     */
    public static function validate_input($qaid, $seqn, $data) {
        global $CFG, $PAGE;
        // Ensure that we have the correct coding.
        mb_internal_encoding('UTF-8');
        
        $params = self::validate_parameters(
                self::validate_input_parameters(),
                ['qaid' => $qaid, 'seqn' => $seqn, 'data' => $data]);
        self::validate_context(\context_system::instance());
        $dm = new \question_engine_data_mapper();
        $qa = $dm->load_question_attempt($params['qaid']);
        $question = $qa->get_question();

        // The input data.
        $json = json_decode($data, true);

        // Now the tricky bit. We need to set the state of the question to
        // match the state currently presented. Luckily we have the $seqn.
        $qstate = new \qbehaviour_stateful_state_storage($qa,
                        $question->get_state_variable_identifiers());
        $qstate->rewind_to_seqn($params['seqn']);
        $question->set_state($qstate);
        // Load the seed and execute initialisation of inputs.
        $question->apply_attempt_state($qa->get_step(0)); // First CAS session.

        // Validate the input.
        $question->is_valid_input($json); // Second CAS session.

        // Render validation boxes, might as well do the full PRT eval also.
        // Maybe look at that at some point.
        $question->render_validation($json); // Third CAS session.

        $r = [];
        // If only I could just return these as dicts...
        $r['val_update'] = json_encode($question->inputs->collect_val_field_values());
        // Validation messages need some work.

        // Using the STACK display logic requires a qtype_stack_renderer,
        // luckilly it is not used for anything so we can just fake it.
        $dummyrenderer = new \qtype_stack_renderer($PAGE, null);

        $vboxcontent = $question->inputs->collect_validation_box_content();
        // Do format things, but the format will always be the (X)HTML-format.
        foreach ($vboxcontent as $key => $value) {
            $vboxcontent[$key] = $question->format_text(
                \stack_maths::process_display_castext($value, $dummyrenderer)
            ,
            FORMAT_HTML,
            $qa, 'question', 'questiontext', $question->id);
        }

        $r['vbox_update'] = json_encode($vboxcontent);

        return $r;
    }
}