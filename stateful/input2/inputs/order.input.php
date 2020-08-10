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
require_once __DIR__ . '/../generic_input_bases.php';
require_once __DIR__ . '/../../castext2/utils.php';

/**
 * This input is for arranging elements in a list or into a list
 * with or without extra elements.
 *
 * It also includes indentation capabilities for providing Parsons problems.
 * Which is the biggest reason for why this ties heavily to the indexing
 * features in CASText2.
 *
 * This input has very few validation features as the student only produces
 * lists of predefined tokens, however one can to restrict the lengths
 * of those lists and fix some elements into particular positions.
 */
class stateful_input_order extends stateful_input_base_with_options_and_validation implements 
    stateful_input_teachers_answer_handling,
    stateful_input_caching_initialisation,
    stateful_input_cas_value_generating {

    // Raw is what we get from the database/question meta.
    protected $rawteachersanswer;
    // We evaluate the value as AST and as a display-value string.
    protected $evaluatedteachersanswer;
    // The value inputted.
    protected $rawvalue = null;
    // The validated value.
    private $valvalue = null;
    // The state of the shuffle-box.
    private $shufflebox = null;

    // Errors.
    private $errors;

    // Id => label. As evaluated by CAS.
    private $elements = [];
    // A random order as evalauted by CAS.
    private $rand = [];
    // The template from CAS.
    private $template = [];
    // The numbers that could be CAS-params.
    private $indent = 4;
    private $maxindent = 4;

    // Frames. The templates to which the UI gets plugged in.
    private $frame = '';
    private $valframe = '';

    // Every draggable item we generate will have its own id. Not an "#id" though.
    private static $idcount = 0;

    public function get_type(): string {
        return 'order';
    }

    public function get_schema_for_options(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_schema_for_options();
        // No use for these.
        unset($base['properties']['syntax-hint-type']);
        unset($base['properties']['syntax-hint']);
        unset($base['properties']['allow-empty']);
        unset($base['properties']['no-units']);

        // The types.
        $typeoneof = [
            ['enum' => ['in-place horizontal'], 'description' => stateful_string('input_option_order_enum_ip_horizontal')],
            ['enum' => ['in-place vertical'], 'description' => stateful_string('input_option_order_enum_ip_vertical')],
            ['enum' => ['fill-in horizontal'], 'description' => stateful_string('input_option_order_enum_fi_horizontal')],
            ['enum' => ['fill-in vertical'], 'description' => stateful_string('input_option_order_enum_fi_vertical')],
            ['enum' => ['fill-in vertical with indentation'], 'description' => stateful_string('input_option_order_enum_fi_vertical_indent')]
        ];
        
        $base['properties']['order-type'] = [
            'default' => 'in-place horizontal',
            'type' => 'string', 
            'oneOf' => $typeoneof,
            'title' => stateful_string('input_option_order_type_label'),
            'description' => stateful_string('input_option_order_type_description')
        ];

        // The template or the initial configuration/blank answer.
        $base['properties']['order-template'] = [
            'default' => '[]',
            'type' => 'string', 
            'title' => stateful_string('input_option_order_template_label'),
            'description' => stateful_string('input_option_order_template_description')
        ];

        // The indexing setting.
        $typeoneof = [
            ['enum' => ['00'], 'description' => stateful_string('input_option_order_enum_indexing_type_zp2')],
            ['enum' => ['000'], 'description' => stateful_string('input_option_order_enum_indexing_type_zp3')],
            ['enum' => ['0000'], 'description' => stateful_string('input_option_order_enum_indexing_type_zp4')],
            ['enum' => ['1'], 'description' => stateful_string('input_option_order_enum_indexing_type_num')],
            ['enum' => ['1.'], 'description' => stateful_string('input_option_order_enum_indexing_type_num_dot')],
            ['enum' => ['I'], 'description' => stateful_string('input_option_order_enum_indexing_type_roman')]
        ];
        $base['properties']['order-indexing-type'] = [
            'default' => '1',
            'type' => 'string', 
            'oneOf' => $typeoneof,
            'title' => stateful_string('input_option_order_indexing_type_label'),
            'description' => stateful_string('input_option_order_indexing_type_description')
        ];
        $base['properties']['order-indexing-offset'] = [
            'default' => '1',
            'type' => 'string', 
            'title' => stateful_string('input_option_order_indexing_offset_label'),
            'description' => stateful_string('input_option_order_indexing_offset_description')
        ];
        $base['properties']['order-indent'] = [
            'default' => '4',
            'type' => 'string', 
            'title' => stateful_string('input_option_order_indent_label'),
            'description' => stateful_string('input_option_order_indent_description')
        ];

        $base['properties']['order-max-indent'] = [
            'type' => 'integer', 
            'minimum' => 1,
            'maximum' => 10,
            'default' => 4,
            'title' => stateful_string('input_option_order_max_indent_label'),
            'description' => stateful_string('input_option_order_max_indent_description')
        ];

        // The tokens.
        $base['properties']['order-tokens'] = [
            'default' => [['value' => 'A','label' => 'Yes'],
                          ['value' => 'B','label' => 'No']],
            'type' => 'array',
            'minItems' => 2,
            'uniqueItems' => true,
            'items' => [
                'type' => 'object',
                'properties' => [
                    'value' => ['type' => 'string', 'title' => stateful_string('input_option_order_tokens_item_value_label')],
                    'label' => ['type' => 'string', 'title' => stateful_string('input_option_order_tokens_item_label_label')],
                    'inclusion' => ['type' => 'string', 'title' => stateful_string('input_option_order_tokens_item_inclusion_label'), 'default' => 'true']
                ],
                'required' => ['value', 'label']
            ],
            'title' => stateful_string('input_option_order_tokens_label'),
            'description' => stateful_string('input_option_order_tokens_description')
        ];

        $base['properties']['order-shuffle'] = [
            'default' => true,
            'type' => 'boolean', 
            'title' => stateful_string('input_option_order_shuffle_label'),
            'description' => stateful_string('input_option_order_shuffle_description')
        ];

        return $base;
    }

    public function get_default_values(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_default_values();

        $base['order-type'] = 'in-place horizontal';
        $base['order-template'] = '[]';
        $base['order-indexing-type'] = '1';
        $base['order-indexing-offset'] = '1';
        $base['order-indent'] = '4';
        $base['order-max-indent'] = 4;
        $base['order-shuffle'] = true;
        $base['order-tokens'] = [['value' => 'A','label' => 'Yes'],
                                 ['value' => 'B','label' => 'No']];

        // No use for these.
        unset($base['syntax-hint-type']);
        unset($base['syntax-hint']);
        unset($base['allow-empty']);
        unset($base['no-units']);

        return $base;
    }


    public function get_layout_for_options(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_layout_for_options();

        $fieldset = ['title' => stateful_string('input_options_order'), 'fields' => ['order-type', 'order-tokens', 'order-template', 'order-indexing-type', 'order-indexing-offset', 'order-indent', 'order-max-indent', 'order-shuffle']];

        // Lets position that among the fieldsets. Also we drop syntaxhints.
        $newfieldsetslist = [];
        foreach ($base['fieldsets'] as $fs) {
            if ($fs['title'] === stateful_string('input_options_syntaxhint')) {
                $newfieldsetslist[] = $fieldset;
            } else if ($fs['title'] === stateful_string('input_options_common')) {
                $n = [];
                foreach ($fs['fields'] as $key => $v) {
                    if ($v !== 'no-units' && $v !== 'allow-empty') {
                        $n[] = $v;
                    }
                }
                $fs['fields'] = $n;
                $newfieldsetslist[] = $fs;
            } else {
                $newfieldsetslist[] = $fs;
            }
        }
        $base['fieldsets'] = $newfieldsetslist;

        if (!isset($base['widgets'])) {
            $base['widgets'] = [];
        }

        $base['widgets']['order-tokens'] = 'ordertokens';
        $base['widgets']['order-type'] = 'select';
        $base['widgets']['order-template'] = 'casstring-list';
        $base['widgets']['order-indexing-type'] = 'select';
        $base['widgets']['order-indexing-offset'] = 'casstring-integer';
        $base['widgets']['order-indent'] = 'casstring-integer';

        // Again not needed.
        unset($base['widgets']['syntax-hint-type']);
        unset($base['widgets']['syntax-hint']);

        return $base;
    }

    public function get_initialisation_commands(): string {
        $cmd = 'block([%_elements,%_tokens,%_ta,%_template,%_tmp],%_elements:[],%_tokens,[';
        // 0. the template.
        $cmd .= '%_template:' . $this->get_option('order-template');

        // 1. the elements.
        $cmd .= ',(%_elements:[],%_tokens:[]';
        foreach ($this->get_option('order-tokens') as $element) {
            // Rules of inclusion are a bit messy, each token has
            // its own condition, but if there is a template specifically
            // referencing that token then it must be present. Templates 
            // only matter if we are doing something else than in-place.
            $castext = castext2_parser_utils::compile($element['label']);
            $token = stack_utils::php_string_to_maxima_string($element['value']);
            if (!isset($element['inclusion']) || $element['inclusion'] === 'true') {
                $cmd .= ',%_tokens:append(%_tokens,[' . $token . '])';
                $cmd .= ',%_elements:append(%_elements,[[' . $token . ',' . $castext . ']])';
            } else {
                if (substr($this->get_option('order-type'), 0, 8) === 'in-place') {
                    $cmd .= ',if(' . $element['inclusion'] . ')then(';
                    $cmd .= '%_tokens:append(%_tokens,[' . $token . '])';
                    $cmd .= ',%_elements:append(%_elements,[[' . $token . ',' . $castext . ']])';
                    $cmd .= ')'; // That if.
                } else {
                    $cmd .= ',%_tmp:' . $element['inclusion'];
                    // Check the template for use of that token.
                    if ($this->get_option('order-type') !== 'fill-in vertical with indentation') {
                        $cmd .= ',if(not %_tmp)then(%_tmp:ev(is(length(sublist(%_template,lambda([x],is(second(x)=' . $token . '))))>0)),simp)';
                    } else {
                        $cmd .= ',if(not %_tmp)then(%_tmp:ev(is(length(sublist(%_template,lambda([x],is(listp(second(x)) and first(second(x))=' . $token . '))))>0)),simp)';
                    }
                    $cmd .= ',if(%_tmp)then(';
                    $cmd .= '%_tokens:append(%_tokens,[' . $token . '])';
                    $cmd .= ',%_elements:append(%_elements,[[' . $token . ',' . $castext . ']])';
                    $cmd .= ')'; // That if.
                }
            }
        }
        $cmd .= ',%_elements),';

        // 2. teachers answer.
        $cmd .= '%_ta:' . $this->rawteachersanswer;

        // 3. random token order != teachers answer.
        if ($this->get_option('order-type') !== 'fill-in vertical with indentation') {
            // Teachers answer is a raw list of tokens.
            $cmd .= ',(%_tmp: %_ta,';
        } else {
            // We need to remove the indent.
            $cmd .= ',(%_tmp:map(second, %_ta),';
        }
        // Permute until different from the answer.
        $cmd .= '%_tokens:random_permutation(%_tokens), while(is(%_tokens=%_tmp) and length(%_tokens)>0) do(%_tokens:random_permutation(%_tokens)),';
        $cmd .= '%_tokens)';
        

        // 4. UI-frame.
        $uiframe = '""';
        switch ($this->get_option('order-type')) {
            case 'in-place horizontal':
                $uiframe = '<div class="stateful-order-container-horizontal stateful-order-in-place">';
                $uiframe .= '[[indexing style="' . $this->get_option('order-indexing-type') . '" start="' . $this->get_option('order-indexing-offset') . '"]]<div class="stateful-order-list">%%SORTAREA%%</div>[[/indexing]]';
                $uiframe .= '%%INPUTS%%</div>';
                break;
            case 'in-place vertical':
                $uiframe = '<div class="stateful-order-container stateful-order-in-place">';
                $uiframe .= '[[indexing style="' . $this->get_option('order-indexing-type') . '" start="' . $this->get_option('order-indexing-offset') . '"]]<div class="stateful-order-list">%%SORTAREA%%</div>[[/indexing]]';
                $uiframe .= '%%INPUTS%%</div>';
                break;
            case 'fill-in horizontal':
                $uiframe = '<div class="stateful-order-container-horizontal" data-template={#str_to_html(stackjson_stringify(%_template)),simp=true#}>';
                $uiframe .= '[[indexing style="' . $this->get_option('order-indexing-type') . '" start="' . $this->get_option('order-indexing-offset') . '"]]<div class="stateful-order-list">%%SORTAREA%%</div>[[/indexing]]';
                $uiframe .= '[[indexing style=" "]]<div class="stateful-order-shufflebox">';
                $uiframe .= '<span class="stateful-order-shufflebox-label">[[commonstring key="input_order_shufflebox_label"/]]</span>';
                $uiframe .= '%%SHUFFLEBOX%%</div>[[/indexing]]';
                $uiframe .= '%%INPUTS%%</div>';
                break;
            case 'fill-in vertical':
                $uiframe = '<div class="stateful-order-container" data-template={#str_to_html(stackjson_stringify(%_template)),simp=true#}>';
                $uiframe .= '[[indexing style="' . $this->get_option('order-indexing-type') . '" start="' . $this->get_option('order-indexing-offset') . '"]]<div class="stateful-order-list">%%SORTAREA%%</div>[[/indexing]]';
                $uiframe .= '[[indexing style=" "]]<div class="stateful-order-shufflebox">';
                $uiframe .= '<span class="stateful-order-shufflebox-label">[[commonstring key="input_order_shufflebox_label"/]]</span>';
                $uiframe .= '%%SHUFFLEBOX%%</div>[[/indexing]]';
                $uiframe .= '%%INPUTS%%</div>';
                break;
            case 'fill-in vertical with indentation':
                $uiframe = '<div class="stateful-order-container" data-template={#str_to_html(stackjson_stringify(%_template)),simp=true#}';
                $uiframe .= ' data-indent="{#' . $this->get_option('order-indent'). ',simp=true#}"';
                $uiframe .= ' data-maxindent="{#' . $this->get_option('order-max-indent'). ',simp=true#}">';
                $uiframe .= '[[indexing style="' . $this->get_option('order-indexing-type') . '" start="' . $this->get_option('order-indexing-offset') . '"]]<div class="stateful-order-list">%%SORTAREA%%</div>[[/indexing]]';
                $uiframe .= '[[indexing style=" "]]<div class="stateful-order-shufflebox">';
                $uiframe .= '<span class="stateful-order-shufflebox-label">[[commonstring key="input_order_shufflebox_label"/]]</span>';
                $uiframe .= '%%SHUFFLEBOX%%</div>[[/indexing]]';
                $uiframe .= '%%INPUTS%%</div>';
                break;
        }

        $cmd .= ',' . castext2_parser_utils::compile($uiframe);
        
        // 5. Validation-frame.
        $valframe = '';
        switch ($this->get_option('order-type')) {
            case 'in-place horizontal':
            case 'fill-in horizontal':
                $valframe .= '<div class="stateful-order-container-horizontal stateful-order-in-place">[[indexing style="' . $this->get_option('order-indexing-type') . '" start="' . $this->get_option('order-indexing-offset') . '"]]<div class="stateful-order-list">%%SORTAREA%%</div>[[/indexing]]</div>';
                break;
            case 'in-place vertical':
            case 'fill-in vertical':
            case 'fill-in vertical with indentation':
                $valframe .= '<div class="stateful-order-container stateful-order-in-place">[[indexing style="' . $this->get_option('order-indexing-type') . '" start="' . $this->get_option('order-indexing-offset') . '"]]<div class="stateful-order-list">%%SORTAREA%%</div>[[/indexing]]</div>';
                break;
        }
        
        $cmd .= ',' . castext2_parser_utils::compile($valframe);

        // 6. The indent size.
        $cmd .= ',' . $this->get_option('order-indent');

        // 7. The max indent size.
        $cmd .= ',' . $this->get_option('order-max-indent');

        // Close up.
        $cmd .= '])';
        
        $validation = stack_ast_container::make_from_teacher_source($cmd, 'init for ' . $this->get_name());
        // Could throw some exceptions here?
        $validation->get_valid();
        return $validation->get_evaluationform();
    }

    public function set_initialisation_value(MP_Node $value): void {
        $list = $value;
        if ($list instanceof MP_Root) {
            $list = $list->items[0];
        }
        if ($list instanceof MP_Statement) {
            $list = $list->statement;
        }
        $list = $list->items;

        // 0. the template.
        $this->template = stateful_utils::mp_to_php($list[0]);


        // 1. the elements.
        $this->elements = [];
        foreach ($list[1]->items as $opt) {
            // Note don't postprocess here as you might reuse the lement in the validation message.
            $this->elements[$opt->items[0]->value] = $opt->items[1];
        }
       
        // 2. teachers answer.
        $this->evaluatedteachersanswer = $list[2];

        // 3. random token order != teachers answer.
        $this->rand = [];
        foreach ($list[3]->items as $opt) {
            $this->rand[] = $opt->value;
        }

        // 4. The UI-frame.
        $this->frame = castext2_parser_utils::postprocess_mp_parsed($list[4]);

        // 5. The validation-frame.
        $this->valframe = castext2_parser_utils::postprocess_mp_parsed($list[5]);

        // 6. The indent size.
        $this->indent = $list[6]->value;

        // 7. The max indent.
        $this->maxindent = $list[7]->value;
    }

    public function set_teachers_answer(string $answer): void {
        $this->rawteachersanswer = $answer;
    }

    public function get_correct_input_guide(): string {
        return "TODO";
    }

    public function get_correct_response(): array {
        return $this->value_to_response($this->evaluatedteachersanswer);
    }


    public function get_errors(): array {
        return $this->errors;
    }

    public function get_val_field_value(): string {
        return json_encode($this->rawvalue);
    }

    public function value_to_response(MP_Node $value): array {
        // We can safely ignore the shufflebox, it wil lbe generated if need be.
        $r = [];
        $val = $value;
        if ($val instanceof MP_Root) {
            $val = $val->items[0];
        }
        if ($val instanceof MP_Statement) {
            $val = $val->statement;
        }
        $r[$this->get_name()] = json_encode(stateful_utils::mp_to_php($value));

        if ($this->get_option('must-verify')) {
            $r[$this->get_name() . '__val'] = $r[$this->get_name()];
        }

        return $r;
    }

    public function get_validation_statements(array $response, stack_cas_security $rules): array {
        $this->rawvalue = ','; // Invalid for JSON.
        if (isset($response[$this->get_name()])) {
            $this->rawvalue = $response[$this->get_name()];
        }
        $this->valvalue = ',';
        if (isset($response[$this->get_name() . '__val'])) {
            $this->valvalue = $response[$this->get_name() . '__val'];
        }
        $this->shufflebox = ',';
        if (isset($response[$this->get_name() . '__sb'])) {
            $this->shufflebox = $response[$this->get_name() . '__sb'];
        }
        // We have no need to send anything to the CAS, we can simply check if the values
        // are JSON-lists like we expect here.
        $this->rawvalue = json_decode($this->rawvalue);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->rawvalue = null;
        }
        $this->valvalue = json_decode($this->valvalue);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->valvalue = null;
        }
        $this->shufflebox = json_decode($this->shufflebox);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->shufflebox = null;
        }

        return [];
    }

    public function is_valid(): bool {
        $this->errors = [];
        // Always an array.
        if (!is_array($this->rawvalue) || $this->is_blank()) {
            return false;
        }

        if (substr($this->get_option('order-type'), 0, 8) === 'in-place') {
            $copy = array_merge($this->rawvalue, []);
            $copy = array_unique($this->rawvalue);
            // Must be unique and must contain all.
            if (count($copy) !== count($this->rawvalue) || count($copy) !== count($this->elements)) {
                return false;
            }
            // Every single one must be a valid token id.
            foreach ($copy as $item) {
                if (!array_key_exists($item, $this->elements)) {
                    return false;
                }
            }
        } else if ($this->get_option('order-type') === 'fill-in vertical with indentation') {
            $keys = [];
            // Extract just the keys.
            foreach ($this->rawvalue as $value) {
                $keys[] = $value[0];
            }
            $keys = array_unique($keys);
            // Must be unique keys.
            if (count($keys) !== count($this->rawvalue)) {
                return false;
            }
            // Every single one must be a valid token id.
            foreach ($keys as $item) {
                if (!array_key_exists($item, $this->elements)) {
                    return false;
                }
            }
            // Check that indents are sensible.
            foreach ($this->rawvalue as $value) {
                if ($value[1] < 0 || $value[1] > $this->get_option('order-max-indent') || !is_int($value[1])) {
                    return false;
                }
            }
            // Check fixed template indents. Should not be input these, but DOM-editing.
            foreach ($this->template as $item) {
                if ($item[0] === 'fixed') {
                    foreach ($this->rawvalue as $val) {
                        if ($val[0] === $item[0] && $val[1] !== $item[1]) {
                            return false;
                        }
                    }
                }
            }
        } else {
            $copy = array_merge($this->rawvalue, []);
            $copy = array_unique($this->rawvalue);
            // Must be unique and must contain all.
            if (count($copy) !== count($this->rawvalue)) {
                return false;
            }
            // Every single one must be a valid token id.
            foreach ($copy as $item) {
                if (!array_key_exists($item, $this->elements)) {
                    return false;
                }
            }
        }

        if (strpos($this->get_option('order-type'), 'fill-in') === false) {
            // After this we match template patterns.
            return true;
        }

        // The template checks actually generate error messages.
        // What we have.
        $raw = array_merge($this->rawvalue, []);
        if ($this->get_option('order-type') === 'fill-in vertical with indentation') {
            $keys = [];
            // Extract just the keys.
            foreach ($this->rawvalue as $value) {
                $keys[] = $value[0];
            }
            $raw = $keys;
        }

        // What we need.
        $pattern = $this->get_pattern();
        $fixeds = $pattern['fixeds'];
        $pattern = $pattern['pattern'];

        // Eat the input.
        foreach ($raw as $item) {
            if (count($pattern) === 0) {
                $this->errors[] = stateful_string('input_order_error_excess_input');
                return false;
            }
            if (isset($fixeds[$item]) && $fixeds[$item]) {
                while (count($pattern) > 0 && $pattern[0] === 0) {
                    $pattern = array_slice($pattern, 1);
                }
                if ($pattern[0] === $item) {
                    $pattern = array_slice($pattern, 1);   
                    $fixeds[$item] = false;
                } else {
                    $this->errors[] = stateful_string('input_order_error_unfilled_slots');
                    return false;       
                }
            } else {
                if ($pattern[0] === 1 || $pattern[0] === 0) {
                    $pattern = array_slice($pattern, 1);   
                }
            }
        }
        foreach ($fixeds as $key => $value) {
            if ($value) {
                $this->errors[] = stateful_string('input_order_error_fixed_element_missing');
                return false;
            }
        }
        while (count($pattern) > 0 && $pattern[0] === 0) {
            $pattern = array_slice($pattern, 1);
        }
        if (count($pattern) > 0) {
            $this->errors[] = stateful_string('input_order_error_unfilled_slots');
            return false;
        }        

        return true;
    }

    // Turns the template into simpler to use pattern.
    private function get_pattern(): array {
        $pattern = [];
        $fixeds = [];
        $fixindents = [];
        foreach ($this->template as $item) {
            if ($item[0] === 'empty') {
                for ($i = 0; $i < $item[1]; $i++) {
                    $pattern[] = 1;        
                }
                if (count($item) == 3) {
                    for ($i = $item[1]; $i < $item[2]; $i++) {
                        $pattern[] = 0;        
                    }
                }
            } else if ($item[0] === 'initial') {
                $pattern[] = 1;
            } else if ($item[0] === 'fixed')  {
                if ($this->get_option('order-type') === 'fill-in vertical with indentation') {
                    $pattern[] = $item[1][0];
                    $fixeds[$item[1][0]] = true;
                    $fixindents[$item[1][0]] = $item[1][1];
                } else {
                    $pattern[] = $item[1];
                    $fixeds[$item[1]] = true;
                    $fixindents[$item[1]] = 0;
                }
            }
        }

        // Sort the 11001 sequences to 11100 etc...
        $zeros = [];
        $ones = [];
        foreach ($pattern as $key => $value) {
            if ($value === 0) {
                $zeros[$key] = true;
            } else if ($value ===1) {
                $ones[$key] = true;
            }
        }
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($zeros as $key => $value) {
                if (isset($ones[$key + 1])) {
                    $changed = true;
                    unset($ones[$key + 1]);
                    unset($zeros[$key]);
                    $pattern[$key] = 1;
                    $pattern[$key + 1] = 0;
                    $ones[$key] = true;
                    $zeros[$key + 1] = true;
                    break;
                }
            }
        }

        return ['pattern' => $pattern, 'fixeds' => $fixeds, 'indents' => $fixindents];
    }

    public function is_blank(): bool {
        if (!is_array($this->rawvalue) || count($this->rawvalue) == 0) {
            return true;
        }
        // Can be blank if the input matches the templates fixed and initial values.
        $init = [];
        foreach ($this->template as $key => $value) {
            if ($value[0] !== 'empty') {
                $init[] = $value[1];
            }
        }
        if ($init == $this->rawvalue) {
            return true;
        }
        // Also random in-place config is a blank.
        if (strpos($this->get_option('order-type'), 'in-place') !== false) {
            if ($this->rand == $this->rawvalue) {
                return true;
            }
        }
        return false;
    }

    public function is_valid_and_validated_or_blank(): bool {
        if ($this->is_blank()) {
            return true;
        }
        if (!$this->is_valid()) {
            return false;
        }
        if ($this->get_option('must-verify')) {
            // The thing is valid only when it has been validated.
            return $this->valvalue === $this->rawvalue;
        }
        return true;
    }

    public function summarise(): string {
        if ($this->get_option('hide-answer') || $this->is_blank()) {
            return '';
        } else {
            $r = $this->get_name() . ': ' . json_encode($this->rawvalue);
            if ($this->is_valid()) {
                $r .= ' [VALID]';
            } else {
                $r .= ' [INVALID]';
            }
            return $r;
        }
    }

    private function indent_spans(string $content, int $indent): string {
        $ind = '<span class="stateful-order-indent" data-indent="' . $indent . '">' . str_repeat(str_repeat('&nbsp;', $this->indent), $indent) . '</span>';
        return str_replace('#indent#', $ind, $content);
    }

    public function render_controls(array $values, string $prefix, bool $readonly = false): string {
        $r = $this->frame;

        // Output the elements.
        $list = '';
        $shufflebox = '';
        $inputs = '';

        // Real values for inputs.
        $reallist = [];
        $realshuffle = [];

        // Common detail.
        $indent = $this->get_option('order-type') === 'fill-in vertical with indentation';

        // In-place cases.
        if (strpos($this->get_option('order-type'), 'in-place') !== false) {
            // Required and accepted.
            $required = [];
            $terms = [];
            foreach ($this->rand as $value) {
                $required[$value] = true;
            }
            // Do we have an existing answer?
            if ($this->rawvalue !== null) {
                foreach ($this->rawvalue as $value) {
                    if ($required[$value]) { // Only valid terms.
                        $required[$value] = false;
                        $terms[] = $value;
                    }
                }
            }
            // If terms are missing add them.
            foreach ($required as $key => $value) {
                if ($value) {
                    $terms[] = $key;
                }
            }
            // Render as a list.
            foreach ($terms as $term) {
                $reallist[] = $term;
                $attributes = [
                    'class' => 'stateful-order-list-item',
                    'data-token' => $term,
                    'data-tokenid' => self::$idcount++,
                    'data-indent' => '0'
                ];
                $content =  castext2_parser_utils::postprocess_mp_parsed($this->elements[$term]);
                $content = $this->indent_spans($content, 0);
                $list .= html_writer::tag('div', $content, $attributes);
            }
        } else {
            $used = [];
            foreach ($this->rand as $value) {
                $used[$value] = false;
            }
            // What we need.
            $pattern = $this->get_pattern();
            $fixeds = $pattern['fixeds'];
            $fixedindents = $pattern['indents'];
            $pattern = $pattern['pattern'];

            if ($this->rawvalue !== null) {
                // Restore list from the answer for terms we know are in valid positions.
                foreach ($this->rawvalue as $key => $value) {
                    if (count($pattern) > 0) {
                        $ind = 0;
                        $term = $value;
                        if ($indent) {
                            // If this is that indentation variant.
                            $ind = $value[1];
                            $term = $value[0];
                        }
                        if (isset($fixeds[$term])) {
                            while (count($pattern) > 0 && $pattern[0] !== $term) {
                                // Move to next fixed.
                                while ($pattern[0] === 1 || $pattern[0] === 0) {
                                    $pattern = array_slice($pattern, 1);   
                                }
                                // Write it out.
                                $attributes = [
                                    'class' => 'stateful-order-list-fixed-item',
                                    'data-token' => $pattern[0],
                                    'data-tokenid' => self::$idcount++,
                                    'data-indent' => '' . $fixedindents[$pattern[0]]
                                ];
                                if (!$used[$pattern[0]]) {
                                    if ($indent) {
                                        $reallist[] = [$pattern[0], $fixedindents[$pattern[0]]];
                                    } else {
                                        $reallist[] = $pattern[0];
                                    }
                                    $content = castext2_parser_utils::postprocess_mp_parsed($this->elements[$pattern[0]]);
                                    $content = $this->indent_spans($content, $ind);
                                    $list .= html_writer::tag('div', $content, $attributes);
                                }
                                $used[$pattern[0]] = true;
                                if ($pattern[0] === $term) {
                                    // We have filled all the fixed ones up to that term.
                                    $pattern = array_slice($pattern, 1);
                                    break;
                                }
                            }
                            if ($pattern[0] === $term) {
                                // Write it out.
                                $attributes = [
                                    'class' => 'stateful-order-list-fixed-item',
                                    'data-token' => $pattern[0],
                                    'data-tokenid' => self::$idcount++,
                                    'data-indent' => '' . $fixedindents[$pattern[0]]
                                ];
                                if (!$used[$pattern[0]]) {
                                    if ($indent) {
                                        $reallist[] = [$pattern[0], $fixedindents[$pattern[0]]];
                                    } else {
                                        $reallist[] = $pattern[0];
                                    }
                                    $content = castext2_parser_utils::postprocess_mp_parsed($this->elements[$pattern[0]]);
                                    $content = $this->indent_spans($content, $ind);
                                    $list .= html_writer::tag('div', $content, $attributes);
                                }
                                $used[$pattern[0]] = true;
                                $pattern = array_slice($pattern, 1);
                            }
                        } else if ($pattern[0] === 1 || $pattern[0] === 0) {
                            $attributes = [
                                'class' => 'stateful-order-list-item',
                                'data-token' => $term,
                                'data-tokenid' => self::$idcount++,
                                'data-indent' => '' . $ind
                            ];
                            if (!$used[$term]) {
                                if ($indent) {
                                    $reallist[] = [$term, $ind];
                                } else {
                                    $reallist[] = $term;
                                }
                                $content = castext2_parser_utils::postprocess_mp_parsed($this->elements[$term]);
                                $content = $this->indent_spans($content, $ind);
                                $list .= html_writer::tag('div', $content, $attributes);
                            }
                            $used[$term] = true;
                            $pattern = array_slice($pattern, 1);
                        }
                    }
                }
            } else {
                // Initialise with the template.
                foreach ($this->template as $value) {
                    if ($value[0] === 'initial') {
                        $ind = 0;
                        $term = $value[1];
                        if ($indent) {
                            // If this is that indentation variant.
                            $ind = $value[1][1];
                            $term = $value[1][0];
                        }
                        $used[$term] = true;
                        $reallist[] = $value[1];
                        $attributes = [
                            'class' => 'stateful-order-list-item',
                            'data-token' => $term,
                            'data-tokenid' => self::$idcount++,
                            'data-indent' => '' . $ind
                        ];
                        $content = castext2_parser_utils::postprocess_mp_parsed($this->elements[$term]);
                        $content = $this->indent_spans($content, $ind);
                        $list .= html_writer::tag('div', $content, $attributes);
                    } else if ($value[0] === 'fixed') {
                        $ind = 0;
                        $term = $value[1];
                        if ($indent) {
                            // If this is that indentation variant.
                            $ind = $value[1][1];
                            $term = $value[1][0];
                        }
                        $used[$term] = true;
                        $reallist[] = $value[1];
                        $attributes = [
                            'class' => 'stateful-order-list-fixed-item',
                            'data-token' => $term,
                            'data-tokenid' => self::$idcount++,
                            'data-indent' => '' . $ind
                        ];
                        $content = castext2_parser_utils::postprocess_mp_parsed($this->elements[$term]);
                        $content = $this->indent_spans($content, $ind);
                        $list .= html_writer::tag('div', $content, $attributes);
                    }
                }
            }


            // Maintain old shufflebox order for those that we know to be there.
            if ($this->shufflebox !== null) {
                foreach ($this->shufflebox as $value) {
                    $ind = 0;
                    $term = $value[1];
                    if ($indent) {
                        // If this is that indentation variant.
                        $ind = $value[1][1];
                        $term = $value[1][0];
                    }
                    if (!$used[$term]) {
                        $used[$term] = true;
                        if ($indent) {
                            $realshuffle[] = [$term, $ind];
                        } else {
                            $realshuffle[] = $term;
                        }
                        $attributes = [
                            'class' => 'stateful-order-list-item',
                            'data-token' => $term,
                            'data-tokenid' => self::$idcount++,
                            'data-indent' => '' . $ind
                        ];
                        $content = castext2_parser_utils::postprocess_mp_parsed($this->elements[$term]);
                        $content = $this->indent_spans($content, $ind);
                        $shufflebox .= html_writer::tag('div', $content, $attributes);
                    }
                }
            }

            // Generate the shufflebox contents for all the unused ones, should have none.
            // Just in case someone does DOM-trickery and for the initialisation.
            foreach ($used as $term => $u) {
                if (!$u) {
                    if ($indent) {
                        $realshuffle[] = [$term, 0];
                    } else {
                        $realshuffle[] = $term;
                    }
                    $attributes = [
                        'class' => 'stateful-order-list-item',
                        'data-token' => $term,
                        'data-tokenid' => self::$idcount++,
                        'data-indent' => '0'
                    ];
                    $content = castext2_parser_utils::postprocess_mp_parsed($this->elements[$term]);
                    $content = $this->indent_spans($content, 0);
                    $shufflebox .= html_writer::tag('div', $content, $attributes);
                }
            }

            // The shufflebox-input.
            $attributes = [
                'name' => $prefix . $this->get_name() . '__sb',
                'id' => $prefix . $this->get_name() . '__sb',
                'type' => 'hidden',
                'value' => json_encode($realshuffle)
            ];
            $inputs .= html_writer::empty_tag('input', $attributes);
        }

        if ($this->get_option('must-verify')) {
            // Validation value.
            $attributes = [
                'name' => $prefix . $this->get_name() . '__val',
                'id' => $prefix . $this->get_name() . '__val',
                'type' => 'hidden',
                'value' => json_encode($reallist)
            ];
            $inputs .= html_writer::empty_tag('input', $attributes);
        }

        // Actual input.
        $attributes = [
            'name' => $prefix . $this->get_name(),
            'id' => $prefix . $this->get_name(),
            'type' => 'hidden',
            'value' => json_encode($reallist)
        ];
        $inputs .= html_writer::empty_tag('input', $attributes);


        $r = str_replace('%%SORTAREA%%', $list, $r);
        $r = str_replace('%%SHUFFLEBOX%%', $shufflebox, $r);
        $r = str_replace('%%INPUTS%%', $inputs, $r);
        return $r;
    }

    public function render_scripts(string $prefix, bool $readonly = false): array {
        $r = parent::render_scripts($prefix, $readonly);
        if (!isset($r['js_call_amd'])) {
            $r['js_call_amd'] = [];
        }

        $vertical = true;
        switch ($this->get_option('order-type')) {
            case 'in-place horizontal':
                $vertical = false;
            case 'in-place vertical':
                $r['js_call_amd'][] = ['qtype_stateful/order', 'inPlaceInit', [$prefix . $this->get_name(), $readonly, $vertical]];
                break;
            case 'fill-in horizontal':
                $vertical = false;
            case 'fill-in vertical':
            case 'fill-in vertical with indentation':
                // The max terms around fixed ones.
                $limits = [];
                if (count($this->template) === 0) {
                    $limits = count($this->rand);
                } else {
                    $c = 0;
                    foreach ($this->template as $value) {
                        if ($value[0] === 'initial') {
                            $c = $c + 1;
                        } else if ($value[0] === 'fixed') {
                            $limits[] = $c;
                            $limits[] = $value[1];
                            $c = 0;
                        } else {
                            if (isset($value[2])) {
                                $c = $c + $value[2];
                            } else {
                                $c = $c + $value[1];
                            }
                        }
                    }
                    $limits[] = $c;
                }

                if ($this->get_option('order-type') !== 'fill-in vertical with indentation') {
                    $r['js_call_amd'][] = ['qtype_stateful/order', 'fillInInit', [$prefix . $this->get_name(), $limits, $readonly, $vertical]];
                } else {
                    $r['js_call_amd'][] = ['qtype_stateful/order', 'fillInIndentInit', [$prefix . $this->get_name(), $limits, $readonly]];
                }
                break;
        }


        return $r;
    }

    public function get_value(): cas_evaluatable {
        if ($this->is_valid()) {
            $arr = [];
            foreach ($this->rawvalue as $value) {
                if ($this->get_option('order-type') === 'fill-in vertical with indentation') {
                    $arr[] = '[' . stack_utils::php_string_to_maxima_string($value[0]) . ',' . $value[1] . ']';
                } else {
                    $arr[] = stack_utils::php_string_to_maxima_string($value);
                }
            }
            $arr = '[' . implode(',', $arr) . ']';
            return new stack_secure_loader($this->get_name() . ':' . $arr, $this->get_name() . ' input value');
        }
        // No one should call this unless we have blank...
        if (!$this->is_blank()) {
            throw new stateful_exception('trying to extract invalid value for evaluation');
        }
        // The empty answer does not need to be validated with full student logic...
        return new stack_secure_loader($this->get_name() . ':EMPTYANSWER', $this->get_name() . ' input value');
    }
    
    public function get_string_value(): string {
        if ($this->rawvalue === null) {
            return '';
        }
        return json_encode($this->rawvalue);
    }

    public function serialize(bool $prunedefaults): array {
        $r = parent::serialize($prunedefaults);
        $r['tans'] = $this->rawteachersanswer;
        return $r;
    }

    public function get_value_override(): string {
        if ($this->is_blank()) {
            return '';
        }
        // Same just escaped.
        return stack_utils::php_string_to_maxima_string($this->get_invalid_value_override());
    }

    public function get_invalid_value_override(): ?string {
        if ($this->is_blank()) {
            return null;
        }
        // We already have the placeholder available, so we just need to fill in the elements.
        $r = '';
        $indent = $this->get_option('order-type') === 'fill-in vertical with indentation';
        error_log(print_r($this->rawvalue,true));
        foreach ($this->rawvalue as $value) {
            $ind = 0;
            $term = $value;
            if ($indent) {
                // If this is that indentation variant.
                $ind = $value[1];
                $term = $value[0];
            }
            $r = $r . '<div class="stateful-order-list-item">' . $this->indent_spans(castext2_parser_utils::postprocess_mp_parsed($this->elements[$term]), $ind) . '</div>';
       } 
       return str_replace('%%SORTAREA%%', $r, $this->valframe);
    }
}