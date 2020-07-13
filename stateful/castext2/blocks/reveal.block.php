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

require_once __DIR__ . '/../block.interface.php';
require_once __DIR__ . '/../block.factory.php';

/**
 * This block implements simplified scripting, it allows one to have
 * some content appear based on the value of a specific input. Typically,
 * used to reveal more questions when a student chooses some MCQ-value.
 *
 * For not it does a case-sensitive full value match, but could be
 * made to do more by adding some extra parameters.
 */
class stateful_cas_castext2_reveal extends stateful_cas_castext2_block {

    private static $count = 0;

    public function compile():  ? string{
        $r = '["reveal"';

        // We need to transfer the parameters forward.
        $p = [];
        if (isset($this->params['input'])) {
            $p['input'] = $this->params['input'];
        }
        if (array_key_exists('value', $this->params)) {
            $p['value'] = $this->params['value'];
        }
        $r .= ',' . stack_utils::php_string_to_maxima_string(json_encode($p));

        foreach ($this->children as $item) {
            $c = $item->compile();
            if ($c !== null) {
                $r .= ',' . $c;
            }
        }

        $r .= ']';

        return $r;
    }

    public function is_flat() : bool {
        return false;
    }

    public function postprocess(array $params, castext2_processor $processor): string {
        global $PAGE;

        // Unpack the procesed stuff.
        $parameters = json_decode($params[1], true);
        $value = $parameters['value'];
        $input = $parameters['input'];
        $content    = '';
        for ($i = 2; $i < count($params); $i++) {
            if (is_array($params[$i])) {
                $content .= $processor->process($params[$i][0], $params[$i]);
            } else {
                $content .= $params[$i];
            }
        }

        $attributes = [
            'style' => 'display:none;',
            'data-input' => $input,
            'data-val' => $value
        ];
        $attributes['id'] = 'ct2_reveal_' . self::$count;
        self::$count++;

        // Load the init-code.
        $PAGE->requires->js_call_amd('qtype_stateful/ct2_reveal', 'init', [$attributes['id']]);

        return html_writer::tag('div', $content, $attributes);
    }



    public function validate(&$errors = [], stateful_inputs $input_definitions = null, array $prts): bool {
        $ok = true;
        $seen = 0;
        foreach ($this->params as $key => $value) {
            switch ($key) {
                case 'input':
                    $seen++;
                    if ($input_definitions === null
                        || !$input_definitions->exists($value)) {
                        $errors[] = stack_string('stackBlock_reveal_input_missing',
                            ['var' => $value]);
                        $ok = false;
                    }
                    break;
                case 'value':
                    $seen++;
                    break;
                
                default:
                    $ok = false;
                    $errors[] = stack_string('stackBlock_reveal_unknown_parameter', ['key' => $key]);
                    break;
            }
        }
        if ($seen < 2) {
            $ok = false;
            $errors[] = stack_string('stackBlock_reveal_missing_key_parameters');
        }

        return $ok;
    }

    public function validate_extract_attributes(): array {
        return [];
    }
}