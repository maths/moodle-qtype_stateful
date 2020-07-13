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
 * This block is part of a pair of blocks the `indexing` and `index` blocks
 * only work with each other. The latter requires to be inside the former and
 * the former only does something if it has some `index`-blocks inside it.
 *
 * In practice the `indexing` block defines the presentation and initial offset
 * of a sequence of numbers to be injected to `index` blocks within it. It may
 * also define a name for itself so that there may exist more than one 
 * individual sequences within it. Typically, that name is just the default
 * blank-string and the `index`-blocks refer to their default blank-string
 * which means the closest parent `indexing-block`.
 */
class stateful_cas_castext2_indexing extends stateful_cas_castext2_block {

    /* Has the JavaScript been loaded? */
    private static $loaded = false;

    public function compile():  ? string{
        $r = '["indexing"';

        // We need to transfer the parameters forward.
        $p = [];
        if (isset($this->params['name'])) {
            $p['name'] = $this->params['name'];
        }
        if (array_key_exists('style', $this->params)) {
            $p['style'] = $this->params['style'];
        }
        $r .= ',' . stack_utils::php_string_to_maxima_string(json_encode($p));

        // The starting offset is the only current CAS-parameter.
        if (array_key_exists('start', $this->params)) {
            $ev = stack_ast_container::make_from_teacher_source('string(ev(' . $this->params['start'] . ',simp))');
            $r .= ',' . $ev->get_evaluationform();
        } else {
            $r .= ',"1"';
        }

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
        $style = '1';
        if (array_key_exists('style', $parameters)) {
            $style = $parameters['style'];
        }
        $name = null;
        if (isset($parameters['name'])) {
            $name = $parameters['name'];
        }
        $start = $params[2];
        $content    = '';
        for ($i = 3; $i < count($params); $i++) {
            if (is_array($params[$i])) {
                $content .= $processor->process($params[$i][0], $params[$i]);
            } else {
                $content .= $params[$i];
            }
        }

        $attributes = [
            'class' => 'stack_ct2_indexing',
            'data-style' => $style,
            'data-start' => $start
        ];
        if ($name !== null) {
            $attributes['data-name'] = $name;
        }

        // Load the init-code.
        if (!self::$loaded) {
            self::$loaded = true;
            $PAGE->requires->js_call_amd('qtype_stateful/ct2_indexing', 'init');
        }

        return html_writer::tag('div', $content, $attributes);
    }

    public function validate(&$errors = [], stateful_inputs $input_definitions = null, array $prts): bool {
        $ok = true;

        foreach ($this->params as $key => $value) {
            switch ($key) {
                case 'start':
                case 'name':
                    break;
                case 'style':
                    switch ($value) {
                        case '00':
                        case '000':
                        case '0000':
                            // Zero paddings.
                            break;
                        case '1':
                            // The default running.
                            break;
                        case '1.':
                            // The one with the dot.
                            break;
                        case 'I':
                            // Romans.
                            break;
                        default:
                            $ok = false;
                            $errors[] = stack_string('stackBlock_indexing_unknown_style', ['key' => $value]);       
                            break;
                    }
                    break;
                default:
                    $ok = false;
                    $errors[] = stack_string('stackBlock_indexing_unknown_parameter', ['key' => $key]);
                    break;
            }
        }

        return $ok;
    }

    public function validate_extract_attributes(): array {
        $r = array();
        if (isset($this->params['start'])) {
            $r[] = stack_ast_container_silent::make_from_teacher_source('start:' . $this->params['start'], 'ct2:indexing', new stack_cas_security());
        }
        return $r;
    }
}
