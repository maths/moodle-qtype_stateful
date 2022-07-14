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
require_once __DIR__ . '/mcq.input.php';


/**
 * This is a specific variant of the MCQ-input with the old option 
 *  definition of STACK. 
 *
 * In all simplicity this one just replaces the initialisation-logic
 * that turns the options into something the new -input handles and 
 * removes most of the settings it input has.
 */
class stateful_input_mcq_legacy extends stateful_input_mcq {

    public function get_type(): string {
        return 'mcq_legacy';
    }


    public function get_schema_for_options(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_schema_for_options();

        // Remove the "other" option.
        $typeoneof = [
            ['enum' => ['radio'], 'description' => stateful_string('input_option_mcqtype_enum_radio')],
            ['enum' => ['checkbox'], 'description' => stateful_string('input_option_mcqtype_enum_checkbox')],
            ['enum' => ['dropdown'], 'description' => stateful_string('input_option_mcqtype_enum_dropdown')]
        ];
        
        $base['properties']['mcq-type'] = [
            'default' => 'radio',
            'type' => 'string', 
            'oneOf' => $typeoneof,
            'title' => stateful_string('input_option_mcqtype_label'),
            'description' => stateful_string('input_option_mcqtype_description')
        ];

        // Extra options to remove.
        unset($base['properties']['mcq-randomise-order']);
        unset($base['properties']['mcq-random-corrects']);
        unset($base['properties']['mcq-random-distractors']);
        unset($base['properties']['mcq-dropdown-vanilla']);
        unset($base['properties']['mcq-options']);
        unset($base['properties']['input-width']);
        unset($base['properties']['require-same-type']);
        unset($base['properties']['fix-spaces']);
        unset($base['properties']['fix-stars']);
        unset($base['properties']['split-number-letter-boundary']);
        unset($base['properties']['split-prefixes-from-functions']);
        unset($base['properties']['split-implied-variables']);
        unset($base['properties']['split-to-single-letter-variables']);
        unset($base['properties']['require-lowest-terms']);
        unset($base['properties']['input-align']);

        // The new options.
        $base['properties']['mcq-legacy-options'] = [
            'default' => '[[true,true,"Yes"],[false,false,"No"]]',
            'type' => 'string',
            'title' => stateful_string('input_option_mcqlegacyoptions_label'),
            'description' => stateful_string('input_option_mcqlegacyoptions_description')
        ];


        $renderoneof = [
            ['enum' => ['latex'], 'description' => stateful_string('input_option_mcqrender_enum_latex')],
            ['enum' => ['casstring'], 'description' => stateful_string('input_option_mcqrender_enum_casstring')]
        ];

        $base['properties']['mcq-label-default-render'] = [
            'default' => 'latex',
            'type' => 'string', 
            'oneOf' => $renderoneof,
            'title' => stateful_string('input_option_mcqrender_label'),
            'description' => stateful_string('input_option_mcqrender_description')
        ];

        // Some tuning:
        $base['properties']['must-verify']['default'] = false;
        $base['properties']['validation-box']['default'] = '';


        return $base;
    }

    public function get_default_values(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_default_values();

        // Lets leave most of the default as they are, for
        // the parent-class.
        
        $base['must-verify'] = false;
        $base['validation-box'] = '';
        $base['mcq-legacy-options'] = '[[true,true,"Yes"],[false,false,"No"]]';

        // The legacy version does not allow the new one.
        $base['mcq-dropdown-vanilla'] = true;

        // In the old world we did not hide the values.
        $base['mcq-hidden-values'] = false;

        return $base;
    }

    public function get_layout_for_options(): array {
        static $l = array();
        if (!empty($l)) {
            return $l;
        }
        // This annoyingly ignoring the inheritance logic so should
        // new and relevant options appear upstream do add them here.
        $l = [];
        $l['widgets'] = [];
        $l['widgets']['validation-box'] = 'validationbox_declare';
        
        $l['fieldsets'] = [];
        $l['fieldsets'][] = ['title' => stateful_string('input_options_validation'), 'fields' => ['validation-box', 'must-verify']];
        $l['fieldsets'][] = ['title' => stateful_string('input_options_common'), 'fields' => ['allow-empty', 'guidance-label', 'hide-answer']];
        $l['fieldsets'][] = ['title' => stateful_string('input_options_keywords'), 'fields' => ['allow-words', 'forbid-words']];

        $l['fieldsets'][] = ['title' => stateful_string('input_options_mcq'), 'fields' => ['mcq-type', 'mcq-legacy-options', 'mcq-no-deselect', 'mcq-label-default-render']];

        $l['widgets']['mcq-label-default-render'] = 'select';
        $l['widgets']['mcq-legacy-options'] = 'casstring';

        return $l;
    }

    public function get_initialisation_commands(): string {
        // The construction of options is the main thing.
        $optgen = '%_mcqoptions:[]';

        $ct2options = ['errclass' => 'stateful_cas_error', 'context' => 'TODO-legacy-mcq'];
        // Default label.
        $label = castext2_parser_utils::compile('{@%_tmp@}', null, $ct2options);
        if ($this->get_option('mcq-label-default-render') !== 'latex') {
            $label = castext2_parser_utils::compile('{#%_tmp#}', null, $ct2options);
        }

        // The unpack.
        $optgen .= ',for %_opt in (' . $this->get_option('mcq-legacy-options') . ') do (';
        $optgen .= 'if length(%_opt) > 2 then (';
        $optgen .= '%_mcqoptions: append(%_mcqoptions,[[%_opt[1],%_opt[3]]])';
        $optgen .= ') else (';
        $optgen .= '%_tmp: %_opt[1]';
        $optgen .= ',%_mcqoptions:append(%_mcqoptions,[[%_opt[1],' . $label . ']])';
        $optgen .= '))';

        $init = 'block([%_tmp,%_opt,simp,%_mcqoptions],simp:false,' . $optgen . ',simp:false,[' . $this->rawteachersanswer . ',stack_dispvalue(' . $this->rawteachersanswer . '),%_mcqoptions])';

        // Note that _EC logic is present in this from the error tracking of
        // castext, we don't consider it as evil at this point.
        $init = str_replace('_EC(', '__MAGIC(', $init);

        $validation = stack_ast_container::make_from_teacher_source($init, 'ta for ' . $this->get_name());
        // Could throw some exceptions here?
        $validation->get_valid();
        $code = $validation->get_evaluationform();

        return str_replace('__MAGIC(', '_EC(', $code);
    }
}
