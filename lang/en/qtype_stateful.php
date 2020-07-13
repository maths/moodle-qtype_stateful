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
/// Expected keys.
$string['pluginname']      = 'Stateful';
$string['pluginname_help'] =
    'Stateful is a complex question type built around STACK';
$string['pluginnamesummary'] =

'Stateful provides a question type focused on asking parametric follow up questions based on the input received. Primarily, useful for testing understanding of algorithmic solution processes in mathematical sciences. Stateful is a wrapper around STACK so much of the same logic applies. However, if you do not need a scene graph to represent your question you will probably not want to use Stateful, as STACK will handle your case in a much simpler way and more robustly. STACK has been in use for more than a decade longer than Stateful and is thus better tested and understood.'
;
$string['pluginnameediting'] = 'Editing a Stateful question';
$string['pluginnameadding']  = 'Adding a Stateful question';

$string['mbstringrequired'] = 'The Stateful question-type requires the mbstring PHP-extension.';
$string['php71required'] = 'The Stateful question-type requires PHP 7.1+';
$string['yamlrecommended'] = 'The Stateful question-type recommends the yaml PHP-extension to ease editing of questions.';


// unknown.
$string['exceptionmessage'] = '{$a}';


//// NOTE: The original author will be displeased if these strings are not grouped by the file they are used in.
//// this does not matter for localised versions but keep the originals in this order and commented like this.


/// /edit_stateful_form.php
$string['questiontext_ie_description'] = 'Question description';
$string['yaml_edit'] = 'YAML-edit';
$string['json_edit'] = 'JSON-edit';
$string['editordesc'] = 'This is the fall-back editor for use when no other editor is available, you should really try to find a more refined editor. Should one exist on this system open it with the button near this text.';


/// /question.php
$string['error_in_processing'] = 'Validation error in input processing, should not happen, rules must have changed in an upgrade of STACK or Stateful.';
$string['error_in_input_processing_cas_evaluation']    = 'Error in CAS-processing input "{$a}"';
$string['error_in_state_variable_initialisation'] = 'Validation error in state-variable initialisation, should not happen, rules must have changed in an upgrade of STACK or Stateful.';
$string['error_in_state_variable_cas_initialisation'] = 'Error in CAS-initialising state variables "{$a}"';
$string['error_in_initialisation'] = 'Validation error in question initialisation, should not happen, rules must have changed in an upgrade of STACK or Stateful.';
$string['error_in_cas_initialisation']    = 'Error in CAS-initialisation "{$a}"';
$string['compilation_error'] = 'Error in question compilation.';


/// /stateful/core/functionbuilder.class.php
$string['validity_premature_state_var'] = 'Premature reference to a state-variable in question variables: {$a}';
$string['section_names_question_variables'] = 'question variables';
$string['validity_overwrite_question_var'] = 'Overwriting question variables in scene or any other scope is forbidden: {$a}';
$string['validity_scene_var_state_var'] = 'Writing to a state variable in scene variables if forbidden, you should only modify state variables in PRTs and even then preferably only at the branch ends leading to scene switch: {$a}';
$string['section_names_scene_variables'] = 'scene variables of scene "{$a}"';
$string['validity_cas_invalid'] = 'CAS statement failed validation at {$a->section} {$a->position} statement: "{$a->statement}" error: "{$a->error}"';
$string['validity_immutable_input'] = 'Input variables may not be overwritten, they are special: "{$a}"';
$string['section_names_feedback_variables'] = 'feedback variables of PRT "{$a->scene}":"{$a->prt}"';
$string['section_names_node_test_points'] = 'test or points of PRT "{$a->scene}":"{$a->prt}":"{$a->node}"';
$string['section_names_node_transition_variables'] = '{$a->branch} transition variables of PRT "{$a->scene}":"{$a->prt}":"{$a->node}"';


/// /stateful/stateful/core/prt.class.php
$string['instantiation_prt_bad_root_ref'] = 'PRT "{$a} lacks root node definition."';


/// /stateful/stateful/handling/validation.php
$string['scoremodeparameters_bestn_more_than_zero'] = 'When selecting N best cases one should give \\(N > 0\\), or select a non scoring mode so that we can skip calculating these score.';
$string['scoremodeparameters_bestn_integer'] = 'The N here needs to be an integer, especially a positive integer.';
$string['prt_value_numeric'] = 'The value of an PRT must be a non negative number.';

$string['unknown_aswertest'] = 'The answer-test specified, "{$a}", is unknown to the back end, maybe you need to upgrade something.';
$string['cyclic_prt'] = 'There is a cycle in the structure of the PRT. Trees may not have loops.';
$string['prt_name_must_be_non_empty'] = 'PRT names must be non empty and unique within the scene.';
$string['input_name_must_be_non_empty'] = 'Input names must be non empty and unique within the scene.';
$string['scene_name_must_be_non_empty'] = 'Scene names must be non empty and unique within the question.';

/// /stateful/stateful/handling/testing.php
$string['test_case_initialisation_error'] = 'Error initialising the test-cases for question-tests.';

/// /stateful/stateful/input2/generic_input_bases.php
$string['input_option_allowempty_label']       = 'Allow empty';
$string['input_option_allowempty_description'] = 'empty value for this field is valid and interpreted to contain the token EMPTYANSWER';
$string['input_option_hideanswer_label']       = 'Hide field';
$string['input_option_hideanswer_description'] = 'hides this fields answer from input values shown in short form logging and similar, for use with scripting';
$string['input_option_nounits_label']        = 'No units';
$string['input_option_nounits_description']  = 'The input value of this input does not contain "units", will not check for case-sensitivity issues related to units';
// Used also in ./inputs/button.input.php
$string['input_option_guidancelabel_label'] = 'Guidance label';
// Used also in ./inputs/button.input.php
$string['input_option_guidancelabel_description'] = 'a verbal label for this input for ARIA and other use';
$string['input_option_syntaxhint_label']       = 'Syntax hint';
$string['input_option_syntaxhint_description'] = 'any value you want to show in the field as a hint';
$string['input_option_syntaxhinttype_label'] = 'Type of syntax hint';
$string['input_option_syntaxhinttype_description'] = 'the type of rendering used for a syntax hint if any is provided';
$string['input_option_validationbox_label'] = 'Type of validation rendering';
$string['input_option_validationbox_description'] = 'should validation be rendered, how it should be rendered, either through automated messages or customised ones';
$string['input_option_mustverify_label']       = 'Must validate';
$string['input_option_mustverify_description'] = 'requires that the student sees the validation before the input is accepted';
// Used also in ./inputs/button.input.php, ./inputs/button.units.php
$string['input_options_common'] = 'Common';
$string['input_options_validation'] = 'Validation';
// Referenced in ./inputs/matrix.input.php, ./inputs/mcq.input.php
$string['input_options_syntaxhint'] = 'Syntax hinting';
$string['option_type_not_expected'] = 'The type of the option-value does not match expected. Please check the value/schema.';
$string['option_not_one_of_expected'] = 'The the option-value does not match any of expected. Please check the value/schema.';
$string['option_value_too_small'] = 'The the option-value is too small. Please check the value/schema.';
$string['option_value_too_big'] = 'The the option-value is too big. Please check the value/schema.';
$string['option_repeated_value'] = 'This option value must consist of a list of unique-items, you have a repeated value.';

/// /stateful/input2/inputs/algebraic.input.php
// Used also in ./inputs/numerical.input.php, ./inputs/string.input.php
$string['input_option_inputwidth_label'] = 'Width';
// Used also in ./inputs/numerical.input.php, ./inputs/string.input.php
$string['input_option_inputwidth_description'] = 'the width of this input field';
// Used also in ./inputs/units.input.php
$string['input_option_allowwords_label']        = 'Allow words';
// Used also in ./inputs/units.input.php
$string['input_option_allowwords_description']  = 'allows some typically forbidden functions and identifiers for the student';
// Used also in ./inputs/units.input.php
$string['input_option_forbidwords_label']       = 'Forbid words';
// Used also in ./inputs/units.input.php
$string['input_option_forbidwords_description'] = 'forbids some typically allowed functions and identifiers from the student';
$string['input_option_forbidfloats_label'] = 'Forbid floating point numbers';
$string['input_option_forbidfloats_description'] = 'forbids values containing decimal points and the floating point version of scientific notation';
$string['input_option_forbidstrings_label'] = 'Forbid strings';
$string['input_option_forbidstrings_description'] = 'no <code>"strings"</code> are allowed in the input';
$string['input_option_forbidlists_label'] = 'Forbid lists';
$string['input_option_forbidlists_description'] = 'no <code>[lists]</code> are allowed in the input, also affects matrices etc.';
$string['input_option_forbidsets_label'] = 'Forbid sets';
$string['input_option_forbidsets_description'] = 'no <code>{sets}</code> are allowed in the input';
$string['input_option_forbidgroups_label'] = 'Forbid grouping';
$string['input_option_forbidgroups_description'] = 'no <code>(g+r-o/u.p*s)</code> are allowed in the input, applies to all use of parenthesis beyond function calls';
$string['input_option_require_same_type_label'] = 'Same type';
$string['input_option_require_same_type_description'] = 'the type of the expression needs to be the same as the teachers answer';
$string['input_option_function_handling_label'] = 'Forbid/split functions';
$string['input_option_function_handling_description'] = 'Disable function calls by turning them to multiplications or forbid them, either for all functions or unknown functions.';
$string['input_option_function_handling_nothing_special'] = 'Do nothing to functions';
$string['input_option_function_handling_split_unknown'] = 'Disable unknown';
$string['input_option_function_handling_split_all'] = 'Disable all';
$string['input_option_function_handling_forbid_unknown'] = 'Forbid unknown';
$string['input_option_function_handling_forbid_all'] = 'Forbid all';
// Used also in ./inputs/numerical.input.php
$string['input_option_fixspaces_label'] = 'Fix spaces';
// Used also in ./inputs/numerical.input.php
$string['input_option_fixspaces_description'] = 'allow fixing of spaces';
// Used also in ./inputs/numerical.input.php
$string['input_option_fixstars_label'] = 'Fix stars';
// Used also in ./inputs/numerical.input.php
$string['input_option_fixstars_description'] = 'allow fixing by adding stars';
$string['input_option_split_to_single_letter_variables_label'] = 'Single letter variables';
$string['input_option_split_to_single_letter_variables_description'] = 'unknown variable-identifiers are split to known ones or all the way to single letter ones';
$string['input_option_split_implied_variables_label'] = 'Implied variables';
$string['input_option_split_implied_variables_description'] = 'identifiers used as both variable and function-identifiers will be considered as variables';
$string['input_option_split_number_letter_boundary_label'] = 'Number-letter boundaries';
$string['input_option_split_number_letter_boundary_description'] = 'split variables at number letter boundaries, assume a missing multiplication sign';
$string['input_option_split_prefixes_from_functions_label'] = 'Prefixes of known functions';
$string['input_option_split_prefixes_from_functions_description'] = 'split prefixes from known functions';
$string['input_option_require_lowest_terms_label'] = 'Lowest terms';
$string['input_option_require_lowest_terms_description'] = 'fractions must be expressed in lowest terms, i.e. simplified';
// Used also in ./inputs/numerical.input.php, ./inputs/string.input.php
$string['input_options_size'] = 'Size';
$string['input_options_keywords'] = 'Keywords';
$string['input_options_types'] = 'Type validation';
// Used also in ./inputs/numerical.input.php
$string['input_options_acceptable'] = 'Acceptable fixes';
$string['input_options_fixes'] = 'Automated fixes to apply';
$string['input_options_requirements'] = 'Requirements';
// Used also in ./inputs/mcq.input.php, ./inputs/numerical.input.php, ./inputs/string.input.php
$string['input_into'] = 'Input the following value as {$a}: ';
// Used also in ./inputs/numerical.input.php
$string['input_option_align_label'] = 'Text align';
// Used also in ./inputs/numerical.input.php
$string['input_option_align_description'] = 'Alignment of the value inputted in the input box';
// Used also in ./inputs/numerical.input.php
$string['input_option_align_left'] = 'Left';
// Used also in ./inputs/numerical.input.php
$string['input_option_align_right'] = 'Right';
// Used also in ./inputs/numerical.input.php
$string['input_option_align_browser_locale'] = 'Default';


/// /stateful/input2/inputs/button.input.php:
$string['input_option_aliasfor_label']       = 'Alias';
$string['input_option_aliasfor_description'] = 'this button acts as an alias for the designated button, pressing this button also triggers that other button but sets its value to this buttons value';
$string['input_option_label_label'] = 'Label';
$string['input_option_label_description'] = 'Label present in the input';
$string['input_option_value_label']       = 'Value';
$string['input_option_value_description'] = 'the value the input variable receives when this button is pressed';
$string['input_options_button'] = 'Button input specific options';


/// /stateful/input2/inputs/matrix.input.php
$string['input_matrix_no_blank_cells_in_data_mode'] = 'This input requires that you fill in all cells of the matrix/table/array';
$string['input_option_inputmincolumns_label'] = 'Columns min';
$string['input_option_inputmincolumns_description'] = 'minimum number of columns if different than maximum input allows adding of columns, does not apply in "data"-mode, if empty uses the teachers answers size';
$string['input_option_inputmaxcolumns_label'] = 'Columns max';
$string['input_option_inputmaxcolumns_description'] = 'maximum number of columns if different than minimum input allows adding of columns, does not apply in "data"-mode, if empty uses the teachers answers size';
$string['input_option_inputinitcolumns_label'] = 'Columns initial';
$string['input_option_inputinitcolumns_description'] = 'initial number of columns for when the number of columns is modifiable, does not apply in "data"-mode, if empty uses the teachers answers size';
$string['input_option_inputminrows_label'] = 'Rows min';
$string['input_option_inputminrows_description'] = 'minimum number of rows if different than maximum input allows adding of rows, if empty uses the teachers answers size';
$string['input_option_inputmaxrows_label'] = 'Rows max';
$string['input_option_inputmaxrows_description'] = 'maximum number of rows if different than minimum input allows adding of rows, if empty uses the teachers answers size';
$string['input_option_inputinitrows_label'] = 'Rows initial';
$string['input_option_inputinitrows_description'] = 'initial number of rows for when the number if rows is modifiable, if empty uses the teachers answers size';
$string['input_option_matrixmode_label'] = 'Operation mode';
$string['input_option_matrixmode_description'] = 'the operation mode of this input, in the "algebraic"-mode all cells are identical algebraic style inputs, in "data"-mode each column is separately controlled';
$string['input_option_matrixmode_enum_algebraic'] = 'Algebraic';
$string['input_option_matrixmode_enum_data'] = 'Data';
$string['input_option_matrixwrapleft_label'] = 'Left wrapper';
$string['input_option_matrixwrapleft_description'] = 'the left border symbol/label if any';
$string['input_option_matrixwrap_enum_default'] = 'Question default';
$string['input_option_matrixwrapleft_enum_paren'] = '(';
$string['input_option_matrixwrapleft_enum_bracket'] = '[';
$string['input_option_matrixwrapleft_enum_brace'] = '{';
$string['input_option_matrixwrap_enum_pipe'] = '|';
$string['input_option_matrixwrap_enum_none'] = 'none';
$string['input_option_matrixwrap_enum_rows_from_top'] = 'Numbered top to bottom';
$string['input_option_matrixwrap_enum_rows_from_bottom'] = 'Numbered bottom up';
$string['input_option_matrixwrapright_label'] = 'Right wrapper';
$string['input_option_matrixwrapright_description'] = 'the right border symbol/label if any';
$string['input_option_matrixwrapright_enum_paren'] = ')';
$string['input_option_matrixwrapright_enum_bracket'] = ']';
$string['input_option_matrixwrapright_enum_brace'] = '}';
$string['input_option_matrixwraptop_label'] = 'Top wrapper';
$string['input_option_matrixwraptop_description'] = 'the top border labelling, by default shows column labels in "data"-mode';
$string['input_option_matrixwrap_enum_labels'] = 'Column labels';
$string['input_option_matrixwrap_enum_columns_from_left'] = 'Numbered left to right';
$string['input_option_matrixwrap_enum_columns_from_right'] = 'Numbered right to left';
$string['input_option_matrixwrapbottom_label'] = 'Bottom wrapper';
$string['input_option_matrixwrapbottom_description'] = 'the bottom border labelling, by default blank';
$string['input_option_matrix_column_lines_label'] = 'Column lines';
$string['input_option_matrix_column_lines_description'] = 'draws lines between columns';
$string['input_option_matrixcolumns_label'] = 'Column definitions';
$string['input_option_matrixcolumns_description'] = 'in "data"-mode each column can have a label and a separate type with separate precision requirements';
$string['input_option_matrixcolumns_item_label_label'] = 'label/title for this column';
$string['input_option_matrixcolumns_item_type_label'] = 'type of operation for this columns inputs';
$string['input_option_matrixcolumns_item_minsf_label'] = 'require minimum of N significant-figures for inputs of this column';
$string['input_option_matrixcolumns_item_maxsf_label'] = 'require maximum of N significant-figures for inputs of this column';
$string['input_option_matrixcolumns_item_mindp_label'] = 'require minimum of N decimal-places for inputs of this column';
$string['input_option_matrixcolumns_item_maxdp_label'] = 'require maximum of N decimal-places for inputs of this column';
$string['input_option_matrixcolumns_type_enum_algebraic'] = 'Algebraic';
$string['input_option_matrixcolumns_type_enum_numeric'] = 'Numeric';
$string['input_option_matrixcolumns_type_enum_unit'] = 'Units';
$string['input_options_matrix_size'] = 'Matrix size';
$string['input_options_matrix_wrap_options'] = 'Matrix wrapping options';
$string['input_options_matrix_data_options'] = 'Matrix data-mode options';
$string['matrix_add_column'] = 'Add column';
$string['matrix_remove_column'] = 'Remove column';
$string['matrix_add_row'] = 'Add row';
$string['matrix_remove_row'] = 'Remove row';
$string['matrix_aria'] = '{$a->glabel} row {$a->row}, column {$a->col}';




/// /stateful/input2/inputs/mcq.input.php
$string['input_mcq_radio_unselect'] = 'Unselect, should you wish to leave this unanswered.';
$string['input_mcq_dropdown_select_one'] = 'Select one';
$string['input_option_mcqtype_label'] = 'Type of MCQ';
$string['input_option_mcqtype_description'] = 'note that the return value of the checkbox option is a list';
// Used also in ./inputs/mcq_legacy.input.php
$string['input_option_mcqtype_enum_radio'] = '1 of N using radio buttons';
$string['input_option_mcqtype_enum_radio_with_other'] = '1 of N using radio buttons with other as text input';
// Used also in ./inputs/mcq_legacy.input.php
$string['input_option_mcqtype_enum_checkbox'] = 'M of N using checkboxes';
// Used also in ./inputs/mcq_legacy.input.php
$string['input_option_mcqtype_enum_dropdown'] = '1 of N using a dropdown';
$string['input_option_mcqoption_enum_correct'] = 'A correct option';
$string['input_option_mcqoption_enum_distractor'] = 'An incorrect option';
$string['input_option_mcqoptions_item_label_label'] = 'Label for this option';
$string['input_option_mcqoptions_item_value_label'] = 'Value for this option';
$string['input_option_mcqoptions_item_group_label'] = 'Group for this option, i.e. is this one of the distractors';
$string['input_option_mcqoptions_item_inclusion_label'] = 'Inclusion rule for this option, i.e. should this option be present with the current values of the questions variables';
$string['input_option_mcqoptions_label'] = 'Options';
$string['input_option_mcqoptions_description'] = 'minimum number of two options needed, you may use the inclusion parameter to selectively remove some options, label is optional if none present will generate one from the value';
$string['input_option_mcqrandomiseorder_label'] = 'Random order';
$string['input_option_mcqrandomiseorder_description'] = 'shuffles the options';
$string['input_option_mcqrandomcorrects_label'] = 'N-Random corrects';
$string['input_option_mcqrandomcorrects_description'] = 'picks the specified number of options marked correct';
$string['input_option_mcqrandomdistractors_label'] = 'N-Random distractors';
$string['input_option_mcqrandomdistractors_description'] = 'picks the specified number of options marked as distractors';
$string['input_option_mcqnodeselect_label'] = 'Disable deselect';
$string['input_option_mcqnodeselect_description'] = 'removes the deselection option';
$string['input_option_mcq_dropdown_vanilla_label'] = 'Bare HTML widget';
$string['input_option_mcq_dropdown_vanilla_description'] = 'if your platform does not support the custom dropdown implementation you can turn it off, you will lose the math-rendering but will gain better ARIA support';
$string['input_option_mcq_hidden_values_label'] = 'Hidden values';
$string['input_option_mcq_hidden_values_description'] = 'The actual values related to the options are repalced with placeholders in the HTML. Saves space, but might make scripting difficult, if you need these valeus for "reveal"-purposes turn this off.';
$string['input_options_mcq'] = 'MCQ specific options';

/// /stateful/input2/inputs/mcq_legacy.input.php
$string['input_option_mcqlegacyoptions_label'] = 'Options (legacy)';
$string['input_option_mcqlegacyoptions_description'] = 'old form definition of MCQ-options, check pre 2021 STACK documentation and consider rewriting with the other MCQ input-type';
$string['input_option_mcqrender_enum_latex'] = 'LaTeX';
$string['input_option_mcqrender_enum_casstring'] = 'Maxima-code';
$string['input_option_mcqrender_label'] = 'Default label presentation';
$string['input_option_mcqrender_description'] = 'how the value should be presented should the label nto be defined';



/// /stateful/input2/inputs/numerical.input.php
$string['input_option_accept_float_vs_integer_both'] = 'Both decimal (floating-point) and integer values';
$string['input_option_accept_float_vs_integer_no_float'] = 'No decimal (floating-point) values';
$string['input_option_accept_float_vs_integer_no_integer'] = 'No integer values only values presented as decimal (floating-point)';
$string['input_option_accept_float_vs_integer_label'] = 'Floats or integers';
$string['input_option_accept_float_vs_integer_description'] = 'you may require that the numerical value is an integer or a float or allow both';
$string['input_option_accept_power_form_label'] = 'Accept power of 10';
$string['input_option_accept_power_form_description'] = 'accepts numbers represented with a power of ten multiplier e.g. <code>0.1*10^23</code> requires that the power is an integer';
$string['input_option_convert_label'] = 'Convert';
$string['input_option_convert_description'] = 'converts the input between specific forms, typically used to turn the answer to float for safer evaluation or to power of ten form for more accuracy';
$string['input_option_convert_none'] = 'no conversion';
$string['input_option_convert_to_float'] = 'from power of ten to float';
$string['input_option_convert_to_power'] = 'from float to power of ten';
$string['input_option_strict_sf_label'] = 'Strict sig-figs';
$string['input_option_strict_sf_description'] = 'validate according to the stricter significant-figures rules';
$string['input_option_min_sf_label'] = 'Min SF';
$string['input_option_min_sf_description'] = 'Require a minimal number of significant-figures, if blank or negative do not check';
$string['input_option_max_sf_label'] = 'Max SF';
$string['input_option_max_sf_description'] = 'Require a maximum number of significant-figures, if blank or negative do not check';
$string['input_option_min_dp_label'] = 'Min DP';
$string['input_option_min_dp_description'] = 'Require a minimal number of decimal-places, if blank or negative do not check';
$string['input_option_max_dp_label'] = 'Max DP';
$string['input_option_max_dp_description'] = 'Require a maximum number of decimal-places, if blank or negative do not check';
// Referenced in ./inputs/units.input.php
$string['input_options_numerical'] = 'Numerical input options';


/// /stateful/input2/inputs/string.input.php
$string['input_option_inputheight_label'] = 'Height';
$string['input_option_inputheight_description'] = 'the height of this input field, if more than 1 then will use a textarea';
$string['input_option_jsonmode_label'] = 'JSON-mode';
$string['input_option_jsonmode_description'] = 'the string value of this input is expected to be a JSON-value if it is not then the value is invalid and if its then it will be parsed as STACK-map style objects before being sent to the CAS thus lessening the evaluation costs there';
$string['input_options_string'] = 'String input options';
$string['json_input_parse_error'] = 'This input expects a valid JSON-formatted value, the received one was not such.';


/// /stateful/input2/inputs/units.input.php
$string['input_option_accept_constants_label'] = 'Accept constants';
$string['input_option_accept_constants_description'] = 'allows constants like <code>pi</code> in the answer';
$string['input_option_accept_variables_label'] = 'Accept variables';
$string['input_option_accept_variables_description'] = 'allows variables in the answer';
$string['input_option_floats_to_powers_label'] = 'Convert to powers';
$string['input_option_floats_to_powers_description'] = 'force conversion of floating point numbers to power of ten presentation, useful when dealing truly large or small values';
$string['input_option_mandatory_unit_label'] = 'Only system units';
$string['input_option_mandatory_unit_description'] = 'requires that the expression contains an identifier that the system knows is an unit, otherwise requires any other identifier to be present';
$string['input_options_units'] = 'Units input options';


/// /stateful/input2/vboxes/basic_validation_box.class.php
$string['your_answer_is_considered_invalid'] = 'Your input was considered invalid, check the syntax and any notes bellow:';
$string['variables_in_input'] = 'Variables present in the input:';
$string['errors_in_input'] = 'Errors detected:';
$string['units_in_input'] = 'Units present in the input:';
// Note accessed through a CASText-block.
$string['your_answer_interpreted_as'] = 'Your input was interpreted like this, if you meant something else fix the input before continuing:';
// Note accessed through a CASText-block.
$string['none_of_the_options_selected_are_you_sure'] = 'This input allows selection of none of the options, note that this selection of none registers the next time you press "check" and have valid values inputted to the other inputs graded at the same time.';
// Note accessed through a CASText-block.
$string['options_selected'] = 'You have selected the following options:';