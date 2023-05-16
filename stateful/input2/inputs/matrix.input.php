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
require_once __DIR__ . '/algebraic.input.php';

/**
 * This input represents an array-form input. It will 
 * return a matrix but can be used to input lists
 * and sets as well as tables of data.
 *
 * To keep things simple it will include the logic that 
 * the algebraic input uses as its basis to apply to every
 * cell but will allow adding additional rules by column if 
 * in data entry mode.
 */
class stateful_input_matrix extends stateful_input_algebraic {

    // The maximum width or height of a matrix, for out of order execution.
    public const MAX_DIM = 20;


    // Sizing.
    private $rowmin = null;
    private $rowmax = null;
    private $rowinit = null;
    private $colmin = null;
    private $colmax = null;
    private $colinit = null;

    // Global wrap setting. The one from the evaluation session.
    private $wrap = null;

    // DP/SF/label
    private $cols = null;

    // Cell-values. Zero indexed from top-left first index is row.
    private $tacells = null;
    private $anscells = null;

    // The slice of the potenttial cells to read if this answer has 
    // resize features.
    private $ansrows = null;
    private $anscols = null;

    // We do colelct usage stats during the validation, as reparsing is pointless.
    private $usage = null;

    // Handles to CAS-validation, if we happen to have it active
    // through algebraic-mode or columns.
    // Array where items are groupped by column.
    private $validationhandles = null;

    // The validation display... i.e. replacement for whatever 
    // the validation boxes want to display.
    private $validationdisplay = null;


    public function __construct(
        string $name) {
        parent::__construct($name);
        $this->tacells = [];
        // These get value when we get the response.
        $this->anscells = null;
    }

    public function get_type(): string {
        return 'matrix';
    }

    public function get_expected_data(): array {
        // In the case of matrices the expected data depends on 
        // whether the matrix is resisable.
        $r = [];
        $n = $this->get_name() . '__';
        if ($this->get_option('must-verify')) {
            $r[$n . 'val'] = PARAM_RAW;
        }

        // If we find that $this->colmax === null we know
        // that this input has not been initialised before
        // the expected data is being asked for in that case
        // we need to push out the potenttial inputs...
        if ($this->colmax === null) {
            $rows = stateful_input_matrix::MAX_DIM;
            $cols = stateful_input_matrix::MAX_DIM;
            if ($this->get_option('matrix-mode') === 'data') {
                $cols = count($this->get_option('matrix-columns'));
            }
            for ($i = 0; $i < $rows; $i++) {
                for ($j = 0; $j < $cols; $j++) {
                    $r[$n . $i . '_' . $j] = PARAM_RAW;
                }
            }
            $r[$n . 'rows'] = PARAM_INT;
            $r[$n . 'cols'] = PARAM_INT;
            return $r;
        }


        $cols = $this->colmax;
        if ($this->get_option('matrix-mode') === 'algebraic') {
            if ($this->rowmin !== $this->rowmax) {
                $r[$n . 'rows'] = PARAM_INT;
            }
            if ($this->colmin !== $this->colmax) {
                $r[$n . 'cols'] = PARAM_INT;
            }
        } else {
            // In the data-mode we ignore any max or min values 
            // for columns
            if ($this->rowmin !== $this->rowmax) {
                $r[$n . 'rows'] = PARAM_INT;
            }
            $cols = count($this->get_option('matrix-columns'));
        }
        for ($i = 0; $i < $this->rowmax; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                $r[$n . $i . '_' . $j] = PARAM_RAW;
            }
        }

        return $r;
    }

    public function is_blank(): bool {
        $cols = $this->anscols;
        if ($this->get_option('matrix-mode') === 'data') {
            $cols = count($this->get_option('matrix-columns'));
        } else if ($this->anscols === null) {
            $cols = $this->colinit;
        }
        $rows = $this->rowinit;
        if ($this->ansrows !== null) {
            $rows = $this->ansrows;
        }
        // Is any of those cells non blank?
        for ($i = 0; $i < $rows; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                if ($this->anscells[$i][$j] !== '') {
                    return false;
                }
            }
        }       

        return true;
    }

    public function get_errors(): array {
        $errs = [];
        $lowest = false;
        if ($this->validationhandles !== null) {
            foreach ($this->validationhandles as $group) {
                foreach ($group as $value) {
                    $bool = $value->get_evaluated();
                    if ($bool instanceof MP_Root) {
                        $bool = $bool->items[0];
                    }
                    if ($bool instanceof MP_Statement) {
                        $bool = $bool->statement;
                    }
                    $lowest = $lowest || !$bool->value;
                    if ($lowest) {
                        break;
                    }
                }
                if ($lowest) {
                    break;
                }
            }
        }

        if ($lowest) {
            // Might be nice to tell which cell, but lets let them do the work.
            // We actually have the validation statements separated by column if we ever want this.
            $errs[] = stack_string('Lowest_Terms');
        }
        
        return array_merge($this->preerrors, $errs);
    }

    public function summarise(): string {
        if ($this->get_option('hide-answer')) {
            return '';
        } else {
            if ($this->is_blank() && $this->is_valid()) {
                return $this->get_name() . ' [VALID AS BLANK]';
            } else if ($this->is_blank()) {
                return '';
            }
            $r = $this->get_name() . ': ' . $this->rawvalue;
            if ($this->get_option('must-verify')) {
                if ($this->val !== $this->rawvalue) {
                    return $r . ' [UNCORFIRMED]';
                }
            }
            if ($this->is_valid()) {
                $inputform = $this->input->get_inputform(true, null);
                if ($inputform === $this->rawvalue) {
                    return $r . ' [VALID]';
                } else {
                    return $r . ' [INTERPRETED AS] ' . $inputform . ' [VALID]';
                }
            } else {
                return $r . ' [INVALID]';
            }
        }
    }

    public function get_validation_statements(array $response, stack_cas_security $rules): array {

        // Collect the input values.
        $rows = $this->rowmax;
        $cols = $this->colmax;
        if ($this->get_option('matrix-mode') === 'data') {
            $cols = count($this->get_option('matrix-columns'));
        }
        $this->anscells = [];
        for ($i = 0; $i < $rows; $i++) {
            $this->anscells[$i] = [];
            for ($j = 0; $j < $cols; $j++) {
                if (isset($response[$this->get_name() . '__' . $i . '_' . $j])) {
                    $this->anscells[$i][$j] = trim($response[$this->get_name() . '__' . $i . '_' . $j]);
                } else {
                    $this->anscells[$i][$j] = '';
                }
            }
        }
        // And the size if relevant.
        $this->anscols = null;
        $this->ansrows = null;
        if (isset($response[$this->get_name() . '__cols'])) {
            $this->anscols = intval($response[$this->get_name() . '__cols']);
        }
        if (isset($response[$this->get_name() . '__rows'])) {
            $this->ansrows = intval($response[$this->get_name() . '__rows']);
        }
        // Decide the relevant size to validate.
        $cols = $this->anscols;
        if ($this->get_option('matrix-mode') === 'data') {
            $cols = count($this->get_option('matrix-columns'));
        } else if ($this->anscols === null) {
            $cols = $this->colinit;
        }
        $rows = $this->rowinit;
        if ($this->ansrows !== null) {
            $rows = $this->ansrows;
        }

        // Construct validation-field value. Note not necessarily valid.
        $this->rawvalue = 'matrix(';
        for ($i = 0; $i < $rows; $i++) {
            if ($i > 0) {
                $this->rawvalue .= ',';
            }
            $this->rawvalue .= '[';
            for ($j = 0; $j < $cols; $j++) {
                if ($j > 0) {
                    $this->rawvalue .= ',';
                }
                $this->rawvalue .= $this->anscells[$i][$j];
            }
            $this->rawvalue .= ']';
        }
        $this->rawvalue .= ')';
        // Collect the last validations one.
        $this->val = null;
        if (isset($response[$this->get_name() . '__val'])) {
            $this->val = $response[$this->get_name() . '__val'];
        }

        // Form the filter lists for each column.
        $algebraic = $this->get_filters();
        $filters = [];
        $filteroptions = [];
        for ($j = 0; $j < $cols; $j++) {
            if ($this->get_option('matrix-mode') === 'data') {
                $f = [];
                $o = [];
                if (!$this->get_option('fix-stars') && !$this->get_option('fix-spaces')) {
                    $f[] = '999_strict';
                } else if (!$this->get_option('fix-stars')) {
                    $f[] = '991_no_fixing_stars';
                } else if (!$this->get_option('fix-spaces')) {
                    $f[] = '990_no_fixing_spaces';
                }

                if ($this->get_option('matrix-columns')[$j]['type'] === 'numeric') {
                    $f = [
                        '102_no_strings',
                        '103_no_lists',
                        '104_no_sets',
                        '105_no_grouppings',
                        '106_no_control_flow',
                        '441_split_unknown_functions',
                        '403_split_at_number_letter_boundary',
                        '406_split_implied_variable_names',
                        '801_singleton_numeric'
                    ];
                    $o['801_singleton_numeric'] = [
                        'integer' => true,
                        'float' => true,
                        'power' => true,
                        'convert' => 'none'
                    ];
                } else if ($this->get_option('matrix-columns')[$j]['type'] === 'unit') {
                    $f = [
                        '102_no_strings',
                        '103_no_lists',
                        '104_no_sets',
                        '105_no_grouppings',
                        '106_no_control_flow',
                        '441_split_unknown_functions',
                        '403_split_at_number_letter_boundary',
                        '406_split_implied_variable_names',
                        '410_single_char_vars',
                        '802_singleton_units'
                    ];
                    $o['802_singleton_units'] = [
                        'allowvariables' => false,
                        'allowconstants' => false,
                        'floattopower' => false,
                        'mandatoryunit' => true
                    ];
                } else {
                    // Overwriting does not lose the 999,991,990 data
                    // this already contains it.
                    $f = array_merge($algebraic, []);
                }

                // Include the presentational accuracy things
                // even for the algebraic case.
                if ($this->get_option('matrix-columns')[$j]['type'] !== 'algebraic' &&
                     (isset($this->cols[$j]['sf-min']) || isset($this->cols[$j]['sf-max']))) {
                    $f[] = '201_sig_figs_validation';
                    $o['201_sig_figs_validation'] = ['min' => null, 'max' => null, 'strict' => false];
                    if (isset($this->cols[$j]['sf-min']) && trim($this->cols[$j]['sf-min']) !== '') {
                        $o['201_sig_figs_validation']['min'] = $this->cols[$j]['sf-min'];
                    }
                    if (isset($this->cols[$j]['sf-max']) && trim($this->cols[$j]['sf-max']) !== '') {
                        $o['201_sig_figs_validation']['max'] = $this->cols[$j]['sf-max'];
                    }
                }
                if ($this->get_option('matrix-columns')[$j]['type'] !== 'algebraic' &&
                     (isset($this->cols[$j]['dp-min']) || isset($this->cols[$j]['dp-max']))) {
                    $f[] = '202_decimal_places_validation';
                    $o['202_decimal_places_validation'] = ['min' => null, 'max' => null];
                    if (isset($this->cols[$j]['dp-min']) && trim($this->cols[$j]['dp-min']) !== '') {
                        $o['202_decimal_places_validation']['min'] = $this->cols[$j]['dp-min'];
                    }
                    if (isset($this->cols[$j]['dp-max']) && trim($this->cols[$j]['dp-max']) !== '') {
                        $o['202_decimal_places_validation']['max'] = $this->cols[$j]['dp-max'];
                    }
                }

                $filters[$j] = $f;
                $filteroptions[$j] = $o;
            } else {
                // We don't actualyl do anything with these. In this mode.
                $filters[$j] = $algebraic;
                $filteroptions[$j] = [];
            }
        }

        // Security.
        $this->security = clone $rules;
        $this->security->set_allowedwords($this->get_option('allow-words'));
        $this->security->set_forbiddenwords($this->get_option('forbid-words'));
        if ($this->get_option('no-units')) {
            $this->security->set_units(false);
        }

        $secrender = clone $rules;
        $secrender->set_allowedwords('stackunits_make,dispdp,displaysci,ev');

        // Now we have what we need to parse. Lets collect and parse the 
        // things. No need to parse similar values multiple times.
        $astcells = [];
        // Also for validation messages we need to have a separate AST 
        // with protected numbers.
        $rendercells = [];
        // Usages.
        $this->usage = [];
        if ($this->get_option('matrix-mode') === 'data') {
            // In data mode group similars by column.
            for ($i = 0; $i < $rows; $i++) {
                $astcells[$i] = [];
                $rendercells[$i] = [];
            }

            for ($j = 0; $j < $cols; $j++) {
                $vals = [];
                for ($i = 0; $i < $rows; $i++) {
                    $vals[] = $this->anscells[$i][$j];
                }   
                $vals = array_unique($vals);
                $valtoast = [];
                $valtorenderast = [];

                $renderfilters = array_merge($filters[$j], []);
                $renderfilters[] = '910_inert_float_for_display';
                $renderfilters = array_filter($renderfilters, function ($name) {
                        switch ($name) {
                            case '201_sig_figs_validation':
                            case '202_decimal_places_validation':
                            case '801_singleton_numeric':
                            case '802_singleton_units':
                            case '999_strict':
                            case '991_no_fixing_stars':
                            case '990_no_fixing_spaces':
                                return false;
                        }
                        return true;
                });

                foreach ($vals as $value) {
                    if ($value === '') {
                        // Use the algebraic filters here to avoid senseless errors.
                        $valtoast[$value] = stack_ast_container::make_from_student_source('0', $this->get_name() . ' input validation', $this->security, $algebraic, $filteroptions[$j]);
                        $valtorenderast[$value] = $valtoast[$value];
                    } else {
                        $valtoast[$value] = stack_ast_container::make_from_student_source($value, $this->get_name() . ' input validation', $this->security, $filters[$j], $filteroptions[$j]);
                        $this->usage = $valtoast[$value]->get_variable_usage($this->usage);
                        if (($this->get_option('matrix-columns')[$j]['type'] === 'unit') && $valtoast[$value]->get_valid()) {
                            $valtorenderast[$value] = stack_ast_container::make_from_student_source('stackunits_make('.$value.')', $this->get_name() . ' input validation', $secrender, $renderfilters);
                        } else {
                            $valtorenderast[$value] = stack_ast_container::make_from_student_source($value, $this->get_name() . ' input validation', $secrender, $renderfilters);
                        }
                    }
                    $valtoast[$value]->get_valid();
                    $valtorenderast[$value]->get_valid();
                }
                for ($i = 0; $i < $rows; $i++) {
                    $astcells[$i][$j] = $valtoast[$this->anscells[$i][$j]];
                    $rendercells[$i][$j] = $valtorenderast[$this->anscells[$i][$j]];
                }
            }
        } else {
            // Group all similars.
            $renderfilters = array_merge($algebraic, []);
            $renderfilters[] = '910_inert_float_for_display';
            $renderfilters = array_filter($renderfilters, function ($name) {
                    switch ($name) {
                        case '801_singleton_numeric':
                        case '802_singleton_units':
                        case '999_strict':
                        case '991_no_fixing_stars':
                        case '990_no_fixing_spaces':
                            return false;
                    }
                    return true;
            });

            $vals = [];
            foreach ($this->anscells as $row) {
                $vals = array_unique(array_merge($vals, $row));
            }
            $valtoast = [];
            $valtorenderast = [];
            foreach ($vals as $value) {
                if ($value === '') {
                    $valtoast[$value] = stack_ast_container::make_from_student_source('0', $this->get_name() . ' input validation', $this->security, $algebraic);
                    $valtorenderast[$value] = $valtoast[$value];
                } else {
                    $valtoast[$value] = stack_ast_container::make_from_student_source($value, $this->get_name() . ' input validation', $this->security, $algebraic);
                    $this->usage = $valtoast[$value]->get_variable_usage($this->usage);
                    $valtorenderast[$value] = stack_ast_container::make_from_student_source($value, $this->get_name() . ' input validation', $this->security, $renderfilters);
                }
                $valtorenderast[$value]->get_valid();
                $valtoast[$value]->get_valid();
            }
            for ($i = 0; $i < $rows; $i++) {
                $astcells[$i] = [];
                $rendercells[$i] = [];
                for ($j = 0; $j < $cols; $j++) {
                    $astcells[$i][$j] = $valtoast[$this->anscells[$i][$j]];
                    $rendercells[$i][$j] = $valtorenderast[$this->anscells[$i][$j]];
                }
            }
        }

        // Parsed, collect errors. Tag by column in data-mode.
        $hasblank = false;
        $this->preerrors = [];
        $distinctast = [];
        $astforcasval = ['all' => []];
        for ($i = 0; $i < $rows; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                if (!isset($astforcasval[$j])) {
                    $astforcasval[$j] = [];
                }
                if ($this->anscells[$i][$j] === '') {
                    $hasblank = true;
                }
                $ast = $astcells[$i][$j];
                if (array_search($ast, $distinctast, true) === false) {
                    $distinctast[] = $ast;
                    $errs = $ast->get_errors('array');
                    if ($this->get_option('matrix-mode') === 'data') {
                        $that = $this;
                        $errs = array_map(function ($err) use ($j, $that) {
                            return '"' . $that->cols[$j]['label'] . '" ' . $err;
                        }, $errs);
                        if ($this->get_option('matrix-columns')[$j]['type'] === 'algebraic') {
                            $astforcasval[$j][]  = $ast;
                        }
                    } else {
                        $astforcasval['all'][] = $ast;
                    }
                    $this->preerrors = array_merge($this->preerrors, $errs);
                }
            }
        }
        if ($this->get_option('matrix-mode') === 'data' && $hasblank) {
            $this->preerrors[] = stateful_string('input_matrix_no_blank_cells_in_data_mode');
        }
        $this->preerrors = array_unique($this->preerrors);
        sort($this->preerrors);

        // Now add CAS-validation if need be.
        $statementstoreturn = [];
        if ($this->get_option('require-lowest-terms')) {
            $this->validationhandles = [];
            if ($this->get_option('matrix-mode') === 'data') {
                for ($j = 0; $j < $cols; $j++) {
                    $this->validationhandles[$j] = [];
                    foreach ($astforcasval[$j] as $ast) {
                        $code = 'all_lowest_termsex(' . $ast->get_inputform(true,1) . ')';
                        $sta = stack_ast_container::make_from_teacher_source($code, $this->get_name() . ' input validation: lowest terms check', $this->security);
                        $this->validationhandles[$j][] = $sta;
                        $statementstoreturn[] = $sta;
                    }
                }
            } else {
                $this->validationhandles['all'] = [];
                foreach ($astforcasval['all'] as $ast) {
                    $code = 'all_lowest_termsex(' . $ast->get_inputform(true,1) . ')';
                    $sta = stack_ast_container::make_from_teacher_source($code, $this->get_name() . ' input validation: lowest terms check', $this->security);
                    $this->validationhandles['all'][] = $sta;
                    $statementstoreturn[] = $sta;
                }
            }
        }

        // Construct LaTex representation of the thing to override
        // validation display. We do not actually evaluate this here
        // it just exists in case some validation-box needs it.
        // Due to the GCL argument thing we do not call sconcat directly
        // we do a lreduce instead.
        // For less escapes we use the AST-node classes to build this.
        $strings = [];
        if (($this->get_option('matrix-mode') === 'data' && (
            $this->get_option('matrix-wrap-top') === 'labels' ||
            $this->get_option('matrix-wrap-bottom') === 'labels')
        )) {
            // If we need to deal with labels for the columns
            // we need to do this in another way.
            
            // Do we need wrapping?
            $left = false;
            $right = false;
            $leftnum = false;
            $rightnum = false;
            switch ($this->get_option('matrix-wrap-left')) {
                case 'default':
                    switch ($this->wrap) {
                        case '(':
                            $left = 'stateful_miw_left_paren_part';
                            break;
                        case '|':
                            $left = 'stateful_miw_left_pipe_part';
                            break;
                        case '[':
                            $left = 'stateful_miw_left_bracket_part';
                            break;
                        case '{':
                            $left = 'stateful_miw_left_brace_part';
                    }
                    break;
                case '(':
                    $left = 'stateful_miw_left_paren_part';
                    break;
                case '|':
                    $left = 'stateful_miw_left_pipe_part';
                    break;
                case '[':
                    $left = 'stateful_miw_left_bracket_part';
                    break;
                case '{':
                    $left = 'stateful_miw_left_brace_part';
                    break;
                case '1...N.';
                case 'N...1.';
                    $leftnum = true;
            }
            switch ($this->get_option('matrix-wrap-right')) {
                case 'default':
                    switch ($this->wrap) {
                        case '(':
                            $right = 'stateful_miw_right_paren_part';
                            break;
                        case '|':
                            $right = 'stateful_miw_right_pipe_part';
                            break;
                        case '[':
                            $right = 'stateful_miw_right_bracket_part';
                            break;
                        case '{':
                            $right = 'stateful_miw_right_brace_part';
                    }
                    break;
                case ')':
                    $right = 'stateful_miw_right_paren_part';
                    break;
                case '|':
                    $right = 'stateful_miw_right_pipe_part';
                    break;
                case ']':
                    $right = 'stateful_miw_right_bracket_part';
                    break;
                case '}':
                    $right = 'stateful_miw_right_brace_part';
                    break;
                case '1...N.';
                case 'N...1.';
                    $rightnum = true;
            }

            // Open the fancy wrappers if we have them.
            if ($left !== false || $right !== false) {
                $strings[] = new MP_String('<table class="stateful_matrix_input_container smic_center"><tr>');
                if ($left !== false) {
                    $strings[] = new MP_String('<td class="' . $left . '1">&nbsp;</td>');
                }
                $strings[] = new MP_String('<td rowspan="4">');
            }

            // Build the actual table.
            if ($left !== false || $right !== false) {
                if ($this->get_option('matrix-column-lines')) {
                    $strings[] = new MP_String('<table class="collines">');
                } else {
                    $strings[] = new MP_String('<table>');
                }
            } else {
                if ($this->get_option('matrix-column-lines')) {
                    $strings[] = new MP_String('<table class="collines stateful_matrix_input_container smic_center">');
                } else {
                    $strings[] = new MP_String('<table class="stateful_matrix_input_container smic_center">');
                }
            }
            if ($this->get_option('matrix-wrap-top') !== '') {
                if ($this->get_option('matrix-wrap-top') === 'labels' && $this->get_option('matrix-mode') === 'data') {
                    $strings[] = new MP_String('<thead><tr>');
                    if ($leftnum) {
                        $strings[] = new MP_String('<th class="numcol"> </th>');
                    }
                    foreach ($this->cols as $coldata) {
                        $strings[] = new MP_String('<th>' . $coldata['label'] . '</th>');
                    }
                    if ($rightnum) {
                        $strings[] = new MP_String('<th class="numcol"> </th>');
                    }
                    $strings[] = new MP_String('</tr></thead>');
                } else if ($this->get_option('matrix-wrap-top') === '1...N.') {
                    $strings[] = new MP_String('<thead><tr>');
                    if ($leftnum) {
                        $strings[] = new MP_String('<th class="numcol"> </th>');
                    }
                    for ($i = 1; $i <= $cols; $i++) {
                        $strings[] = new MP_String('<th class="col' . ($i - 1) . '">' . $i . '.</th>');
                    }
                    if ($rightnum) {
                        $strings[] = new MP_String('<th class="numcol"> </th>');
                    }
                    $strings[] = new MP_String('</tr></thead>');
                } else if ($this->get_option('matrix-wrap-top') === 'N...1.') {
                    // TODO: kill this option, it is a mess.
                    $strings[] = new MP_String('<thead><tr>');
                    if ($leftnum) {
                        $strings[] = new MP_String('<th class="numcol"> </th>');
                    }
                    for ($i = 1; $i <= $cols; $i++) {
                        $strings[] = new MP_String('<th class="tnum col' . ($i - 1) . '">' . ($cols - $i + 1) . '.</th>');
                    }
                    if ($rightnum) {
                        $strings[] = new MP_String('<th class="numcol"> </th>');
                    }
                    $strings[] = new MP_String('</tr></thead>');
                }
            }
                    
            $strings[] = new MP_String('<tbody>');
            // Content
            for ($i = 0; $i < $rows; $i++) {
                $strings[] = new MP_String('<tr>');
                if ($this->get_option('matrix-wrap-left') === '1...N.') {
                    $strings[] = new MP_String('<th class="numcol">' . ($i + 1) . '.</th>');
                } else if ($this->get_option('matrix-wrap-left') === 'N...1.') {
                    $strings[] = new MP_String('<th class="numcol">' . ($rows - $i) . '.</th>');
                }
                for ($j = 0; $j < $cols; $j++) {
                    $strings[] = new MP_String('<td>');
                    
                    // Now we need to deal with possible bad values.
                    if ($this->anscells[$i][$j] === '') {
                        if ($this->get_option('matrix-mode') === 'data') {
                            $strings[] = new MP_String('\( {\color{red}?} \)');
                        } else {
                            $strings[] = new MP_String('\( {\color{gray}0} \)');
                        }
                    } else if ($astcells[$i][$j]->get_valid()){
                        // Real values are special.
                        $fc = new MP_FunctionCall(new MP_Identifier('stack_disp'),[]);
                        // We have no direct way of getting the AST
                        // from the AST-container so parse again.
                        $ev = $rendercells[$i][$j]->get_evaluationform();
                        $ast = null;
                        if (isset($ast2[$ev])) {
                            $ast = $ast2[$ev];
                        } else {
                            $ast = maxima_parser_utils::parse($ev);
                            $ast2[$ev] = $ast;
                        }
                        if ($this->get_option('matrix-columns')[$j]['type'] === 'unit') {
                            $fc->arguments[] = new MP_FunctionCall(new MP_Identifier('ev'), [$ast->items[0]->statement, new MP_Identifier('simp')]);
                        } else {
                            $fc->arguments[] = $ast->items[0]->statement;
                        }
                        // Let it do the wrapping..
                        $fc->arguments[] = new MP_String('i');
                        $strings[] = $fc;
                    } else {
                        $strings[] = new MP_String('\( {\color{red}{' . 
                            stateful_utils::inert_latex($this->anscells[$i][$j]) . '}} \)');
                    }
                    $strings[] = new MP_String('</td>');
                }
                if ($this->get_option('matrix-wrap-right') === '1...N.') {
                    $strings[] = new MP_String('<th class="numcol">' . ($i + 1) . '.</th>');
                } else if ($this->get_option('matrix-wrap-right') === 'N...1.') {
                    $strings[] = new MP_String('<th class="numcol">' . ($rows - $i) . '.</th>');
                }
                $strings[] = new MP_String('</tr>');
            }
            $strings[] = new MP_String('</tbody>');

            // Close
            if ($this->get_option('matrix-wrap-bottom') !== '') {
                if ($this->get_option('matrix-wrap-bottom') === 'labels' && $this->get_option('matrix-mode') === 'data') {
                    $strings[] = new MP_String('<tfoot><tr>');
                    if ($leftnum) {
                        $strings[] = new MP_String('<th> </th>');
                    }
                    foreach ($this->cols as $coldata) {
                        $strings[] = new MP_String('<th>' . $coldata['label'] . '</th>');
                    }
                    if ($rightnum) {
                        $strings[] = new MP_String('<th> </th>');
                    }
                    $strings[] = new MP_String('<tr></tfoot>');
                } else if ($this->get_option('matrix-wrap-bottom') === '1...N.') {
                    $strings[] = new MP_String('<tfoot><tr>');
                    if ($leftnum) {
                        $strings[] = new MP_String('<th> </th>');
                    }
                    for ($i = 1; $i <= $cols; $i++) {
                        $strings[] = new MP_String('<th class="col' . ($i - 1) . '">' . $i . '.</th>');
                    }
                    if ($rightnum) {
                        $strings[] = new MP_String('<th> </th>');
                    }
                    $strings[] = new MP_String('<tr></tfoot>');
                } else if ($this->get_option('matrix-wrap-bottom') === 'N...1.') {
                    $strings[] = new MP_String('<tfoot><tr>');
                    if ($leftnum) {
                        $strings[] = new MP_String('<th> </th>');
                    }
                    for ($i = 1; $i <= $cols; $i++) {
                        $strings[] = new MP_String('<th class="bnum col' . ($i - 1) . '">' . ($cols - $i + 1) . '.</th>');
                    }
                    if ($rightnum) {
                        $strings[] = new MP_String('<th> </th>');
                    }
                    $strings[] = new MP_String('<tr></tfoot>');
                }
            }

            $strings[] = new MP_String('</table>');

            // If we had fancy wrappers we need to close them.
            if ($left !== false || $right !== false) {
                $strings[] = new MP_String('</td>');
                if ($right !== false) {
                    $strings[] = new MP_String('<td class="' . $right . '1">&nbsp;</td>');
                }
                $strings[] = new MP_String('</tr>');
                for ($i = 2; $i < 5; $i++) {
                    $strings[] = new MP_String('<tr>');
                    if ($left !== false) {
                        $strings[] = new MP_String('<td class="' . $left . $i . '">&nbsp;</td>');
                    }
                    if ($right !== false) {
                        $strings[] = new MP_String('<td class="' . $right . $i . '">&nbsp;</td>');
                    }
                    $strings[] = new MP_String('</tr>');
                }
                $strings[] = new MP_String('</table>');
            }

        } else {
            // Do we have a left-wrapper?
            $tmp = '\begin{array}{';

            switch ($this->get_option('matrix-wrap-left')) {
                case 'default':
                    switch ($this->wrap) {
                        case '(':
                            $strings[] = new MP_String('\left(');
                            break;
                        case '|':
                            $strings[] = new MP_String('\left|');
                            break;
                        case '[':
                            $strings[] = new MP_String('\left[');
                            break;
                        case '{':
                            $strings[] = new MP_String('\left\{');
                            break;
                        default:
                            $strings[] = new MP_String('\left.');
                    }
                    break;
                case '(':
                case '[':
                case '|':
                    $strings[] = new MP_String('\left' . $this->get_option('matrix-wrap-left'));
                    break;
                case '{':
                    $strings[] = new MP_String('\left\{');
                    break;
                case '1...N.':
                case 'N...1.':
                    $tmp .= 'r|';
                default:
                    $strings[] = new MP_String('\left.');
            }
            for ($j = 0; $j < $cols; $j++) {
                if ($this->get_option('matrix-column-lines') && $j > 0) {
                    $tmp .= '|';
                }
                $tmp .= 'c';
            }
            if ($this->get_option('matrix-wrap-left') === '1...N.' || $this->get_option('matrix-wrap-left') === 'N...1.') {
                $tmp .= '|l';
            }
            $tmp .= '}';
            $strings[] = new MP_String($tmp);
            $ast2 = [];

            // If we play with column numbers, problem here is with the left/right parenthesis.
            if ($this->get_option('matrix-wrap-top') === '1...N.' || $this->get_option('matrix-wrap-top') === 'N...1.') {
                if ($this->get_option('matrix-wrap-left') === '1...N.' || $this->get_option('matrix-wrap-left') === 'N...1.') {
                    $strings[] = new MP_String('  &amp;');
                }
                for ($j = 0; $j < $cols; $j++) {
                    if ($j > 0) {
                        $strings[] = new MP_String(' &amp; ');
                    }
                    if ($this->get_option('matrix-wrap-top') === '1...N.') {
                        $strings[] = new MP_String(($j + 1) . '. ');
                    } else if ($this->get_option('matrix-wrap-top') === 'N...1.') {
                        $strings[] = new MP_String(($rows - $j) . '. ');
                    }
                }
                if ($this->get_option('matrix-wrap-right') === '1...N.' || $this->get_option('matrix-wrap-right') === 'N...1.') {
                    $strings[] = new MP_String('&amp; ');
                }
                $strings[] = new MP_String(' \\\\ \hline ');
            }

            // All the cells.
            for ($i = 0; $i < $rows; $i++) {
                if ($i > 0) {
                    $strings[] = new MP_String(' \\\\ ');
                }
                if ($this->get_option('matrix-wrap-left') === '1...N.') {
                    $strings[] = new MP_String(($i + 1) . '. &amp; ');
                } else if ($this->get_option('matrix-wrap-left') === 'N...1.') {
                    $strings[] = new MP_String(($rows - $i) . '. &amp; ');
                }
                for ($j = 0; $j < $cols; $j++) {
                    if ($j > 0) {
                        $strings[] = new MP_String(' &amp; ');
                    }
                    // Now we need to deal with possible bad values.
                    if ($this->anscells[$i][$j] === '') {
                        if ($this->get_option('matrix-mode') === 'data') {
                            $strings[] = new MP_String(' {\color{red}?} ');
                        } else {
                            $strings[] = new MP_String(' {\color{gray}0} ');
                        }
                    } else if ($astcells[$i][$j]->get_valid()){
                        // Real values are special.
                        $fc = new MP_FunctionCall(new MP_Identifier('stack_disp'),[]);
                        // We have no direct way of getting the AST
                        // from the AST-container so parse again.
                        $ev = $rendercells[$i][$j]->get_evaluationform();
                        $ast = null;
                        if (isset($ast2[$ev])) {
                            $ast = $ast2[$ev];
                        } else {
                            $ast = maxima_parser_utils::parse($ev);
                            $ast2[$ev] = $ast;
                        }
                        $fc->arguments[] = $ast->items[0]->statement;
                        $fc->arguments[] = new MP_String('');
                        $strings[] = $fc;
                    } else {
                        $strings[] = new MP_String(' {\color{red}{' . 
                            stateful_utils::inert_latex($this->anscells[$i][$j]) . '}} ');
                    }
                }
                if ($this->get_option('matrix-wrap-right') === '1...N.') {
                    $strings[] = new MP_String(' &amp; ' . ($i + 1) . '.');
                } else if ($this->get_option('matrix-wrap-right') === 'N...1.') {
                    $strings[] = new MP_String(' &amp; ' . ($rows - $i) . '.');
                }
            }

            // If we play with column numbers, problem here is with the left/right parenthesis.
            if ($this->get_option('matrix-wrap-bottom') === '1...N.' || $this->get_option('matrix-wrap-bottom') === 'N...1.') {
                $strings[] = new MP_String(' \\\\ \hline ');
                if ($this->get_option('matrix-wrap-left') === '1...N.' || $this->get_option('matrix-wrap-left') === 'N...1.') {
                    $strings[] = new MP_String('  &amp;');
                }
                for ($j = 0; $j < $cols; $j++) {
                    if ($j > 0) {
                        $strings[] = new MP_String(' &amp; ');
                    }
                    if ($this->get_option('matrix-wrap-bottom') === '1...N.') {
                        $strings[] = new MP_String(($j + 1) . '. ');
                    } else if ($this->get_option('matrix-wrap-bottom') === 'N...1.') {
                        $strings[] = new MP_String(($rows - $j) . '. ');
                    }
                }
                if ($this->get_option('matrix-wrap-right') === '1...N.' || $this->get_option('matrix-wrap-right') === 'N...1.') {
                    $strings[] = new MP_String('&amp;  ');
                }
            }

            $strings[] = new MP_String('\end{array}');
            // Do we have a right-wrapper?
            switch ($this->get_option('matrix-wrap-right')) {
                case 'default':
                    switch ($this->wrap) {
                        case '(':
                            $strings[] = new MP_String('\right)');
                            break;
                        case '|':
                            $strings[] = new MP_String('\right|');
                            break;
                        case '[':
                            $strings[] = new MP_String('\right]');
                            break;
                        case '{':
                            $strings[] = new MP_String('\right\}');
                            break;
                        default:
                            $strings[] = new MP_String('\left.');
                    }
                    break;
                case '}':
                    $strings[] = new MP_String('\right\}');
                    break;
                case ')':
                case ']':
                case '|':
                    $strings[] = new MP_String('\right' . $this->get_option('matrix-wrap-right'));
                    break;
                default:
                    $strings[] = new MP_String('\left.');
            }
        }
        // Both ends cannot be '\left.'
        if ($strings[0] instanceof MP_String && $strings[0]->value === '\left.') {
            if ($strings[count($strings)-1] instanceof MP_String && $strings[count($strings)-1]->value === '\left.') {
                $strings = array_slice($strings, 1, -1);
            }    
        }

        $strings = stateful_utils::string_list_reduce($strings);
        if (count($strings) > 1) {
            $fc = new MP_FunctionCall(new MP_Identifier('lreduce'),[new MP_Identifier('sconcat'), new MP_List($strings)]);
        } else {
            $fc = $strings[0];
        }

        $this->validationdisplay = stack_ast_container::make_from_teacher_ast(new MP_Statement($fc, []), $this->get_name() . ' validation render', new stack_cas_security());
        $statementstoreturn[] = $this->validationdisplay;

        // Finally it might make sense to build the singular value to use...
        if (count($this->preerrors) === 0) {
            $src = 'matrix(';
            for ($i=0; $i < $rows; $i++) {
                if ($i > 0) {
                    $src .= ',';
                }
                $src .= '[';
                for ($j = 0; $j < $cols; $j++) {
                    if ($j > 0) {
                        $src .= ',';
                    }   
                    $src .= $astcells[$i][$j]->get_evaluationform();
                }
                $src .= ']';
            }
            $src .= ')';
            $this->input = stack_ast_container::make_from_teacher_source($src, $this->get_name() . 'input-value', new stack_cas_security());
        } else {
            $this->input = stack_ast_container::make_from_teacher_source('in valid', $this->get_name() . ' invalid placeholder', $this->security);
        }

        return $statementstoreturn;
    }

    public function is_valid(): bool {
        if ($this->is_blank()) {
            return $this->get_option('allow-empty');
        }
        if ($this->input->get_valid() === false) {
            return false;
        }
        if (count($this->get_errors()) > 0) {
            return false;
        }

        return true;
    }

    public function render_controls(array $values, string $prefix, bool $readonly = false): string {
        $r = '';
        $fieldname = $prefix . $this->get_name();
        if ($this->get_option('must-verify')) {
            $attr = [
                'name'  => $fieldname . '__val',
                'id'    => $fieldname . '__val',
                'type'  => 'hidden',
                'value' => $this->rawvalue
            ];
            $r = html_writer::empty_tag('input', $attr);
        }

        $rowmod = false;
        $colmod = false;
        $viscols = $this->colinit;
        $visrows = $this->rowinit;

        if (isset($values[$this->get_name() . '__cols'])) {
            $viscols = intval($values[$this->get_name() . '__cols']);
        }

        if (isset($values[$this->get_name() . '__rows'])) {
            $visrows = intval($values[$this->get_name() . '__rows']);
        }

        // Do we track size? Is it adjustable.
        if ($this->rowmin !== $this->rowmax) {
            $rowmod = true;
            $attr = [
                'name'  => $fieldname . '__rows',
                'id'    => $fieldname . '__rows',
                'type'  => 'hidden',
                'value' => $visrows
            ];
            $r .= html_writer::empty_tag('input', $attr);       
        }
        if (($this->colmin !== $this->colmax) && $this->get_option('matrix-mode') !== 'data') {
            $colmod = true;
            $attr = [
                'name'  => $fieldname . '__cols',
                'id'    => $fieldname . '__cols',
                'type'  => 'hidden',
                'value' => $viscols
            ];
            $r .= html_writer::empty_tag('input', $attr);       
        }

        // Do we need wrapping?
        $left = false;
        $right = false;
        $leftnum = false;
        $rightnum = false;
        switch ($this->get_option('matrix-wrap-left')) {
            case 'default':
                switch ($this->wrap) {
                    case '(':
                        $left = 'stateful_miw_left_paren_part';
                        break;
                    case '|':
                        $left = 'stateful_miw_left_pipe_part';
                        break;
                    case '[':
                        $left = 'stateful_miw_left_bracket_part';
                        break;
                    case '{':
                        $left = 'stateful_miw_left_brace_part';
                }
                break;
            case '(':
                $left = 'stateful_miw_left_paren_part';
                break;
            case '|':
                $left = 'stateful_miw_left_pipe_part';
                break;
            case '[':
                $left = 'stateful_miw_left_bracket_part';
                break;
            case '{':
                $left = 'stateful_miw_left_brace_part';
                break;
            case '1...N.';
            case 'N...1.';
                $leftnum = true;
        }
        switch ($this->get_option('matrix-wrap-right')) {
            case 'default':
                switch ($this->wrap) {
                    case '(':
                        $right = 'stateful_miw_right_paren_part';
                        break;
                    case '|':
                        $right = 'stateful_miw_right_pipe_part';
                        break;
                    case '[':
                        $right = 'stateful_miw_right_bracket_part';
                        break;
                    case '{':
                        $right = 'stateful_miw_right_brace_part';
                }
                break;
            case ')':
                $right = 'stateful_miw_right_paren_part';
                break;
            case '|':
                $right = 'stateful_miw_right_pipe_part';
                break;
            case ']':
                $right = 'stateful_miw_right_bracket_part';
                break;
            case '}':
                $right = 'stateful_miw_right_brace_part';
                break;
            case '1...N.';
            case 'N...1.';
                $rightnum = true;
        }

        // Open the fancy wrappers if we have them.
        if ($left !== false || $right !== false) {
            $r .= '<table class="stateful_matrix_input_container">';
            $r .= '<tr>';
            if ($left !== false) {
                $r .= '<td class="' . $left . '1"> </td>';
            }
            $r .= '<td rowspan="4">';
        }

        // Build the actuall table.
        if ($left !== false || $right !== false) {
            if ($this->get_option('matrix-column-lines')) {
                $r .= '<table class="collines">';
            } else {
                $r .= '<table>';
            }
        } else {
            if ($this->get_option('matrix-column-lines')) {
                $r .= '<table class="collines stateful_matrix_input_container">';
            } else {
                $r .= '<table class="stateful_matrix_input_container">';
            }
        }
        if ($this->get_option('matrix-wrap-top') !== '') {
            if ($this->get_option('matrix-wrap-top') === 'labels' && $this->get_option('matrix-mode') === 'data') {
                $r .= '<thead><tr>';
                if ($leftnum) {
                    $r .= '<th class="numcol"> </th>';
                }
                foreach ($this->cols as $coldata) {
                    $r .= '<th>' . $coldata['label'] . '</th>';
                }
                if ($rightnum) {
                    $r .= '<th class="numcol"> </th>';
                }
                if ($colmod) {
                    $r .= '<th class="modcol"> </th>';
                }
                $r .= '</tr></thead>';
            } else if ($this->get_option('matrix-wrap-top') === '1...N.') {
                $r .= '<thead><tr>';
                if ($leftnum) {
                    $r .= '<th class="numcol"> </th>';
                }
                for ($i = 1; $i <= $this->colmax; $i++) {
                    if ($i > $viscols) {
                        $r .= '<th style="display:none;" class="col' . ($i - 1) . '">' . $i . '.</th>';
                    } else {
                        $r .= '<th class="col' . ($i - 1) . '">' . $i . '.</th>';
                    }
                }
                if ($rightnum) {
                    $r .= '<th class="numcol"> </th>';
                }
                if ($colmod) {
                    $r .= '<th class="modcol"> </th>';
                }
                $r .= '</tr></thead>';
            } else if ($this->get_option('matrix-wrap-top') === 'N...1.') {
                // TODO: kill this option, it is a mess.
                $r .= '<thead><tr>';
                if ($leftnum) {
                    $r .= '<th class="numcol"> </th>';
                }
                for ($i = 1; $i <= $this->colmax; $i++) {
                    if ($i > $viscols) {
                        $r .= '<th style="display:none;" class="tnum col' . ($i - 1) . '">' . ($viscols - $i + 1) . '.</th>';
                    } else {
                        $r .= '<th class="tnum col' . ($i - 1) . '">' . ($viscols - $i + 1) . '.</th>';
                    }
                }
                if ($rightnum) {
                    $r .= '<th class="numcol"> </th>';
                }
                if ($colmod) {
                    $r .= '<th class="modcol"> </th>';
                }
                $r .= '</tr></thead>';
            }
        }

        // The input fields go here.
        $r .= '<tbody>';

        for ($i=0; $i < $this->rowmax; $i++) {
            $r .= '<tr class="row' . $i . '"';
            if (($i + 1) > $visrows) {
                $r .= ' style="display:none;"';
            }
            $r .= '>';
            if ($this->get_option('matrix-wrap-left') === '1...N.') {
                $r .= '<th class="numcol">' . ($i + 1) . '.</th>';
            }
            if ($this->get_option('matrix-wrap-left') === 'N...1.') {
                $r .= '<th class="numcol lnum">' . ($visrows - $i) . '.</th>';
            }
            for ($j=0; $j < $this->colmax; $j++) {
                $r .= '<td class="col' . $j . '"';
                if (($j + 1) > $viscols) {
                    $r .= ' style="display:none;"';     
                }
                $r .= '>';
                $attributes = array(
                    'name'  => $fieldname . '__' . $i . '_' . $j,
                    'id'    => $fieldname . '__' . $i . '_' . $j,
                    'autocapitalize' => 'none',
                    'spellcheck'     => 'false',
                    'size' => $this->get_option('input-width'),
                    'type' => 'text',
                    'value' => '',
                    'aria-label' => stateful_string('matrix_aria' ,['glabel' => $this->get_option('guidance-label'), 'row' => $i+1, 'col' => $j+1])
                );
                if ($this->get_option('matrix-mode') === 'data') {
                    // TODO: Find a way to block MathJax in here
                    // So that we can have these labels in the case
                    // where the column name has maths.
                    /// $attributes['aria-label'] = stateful_string('matrix_aria' ,['glabel' => $this->get_option('guidance-label'), 'row' => $i+1, 'col' => $this->cols[$j]['label']]);
                }
                if (isset($values[$this->get_name() . '__' . $i . '_' . $j])) {
                    $attributes['value'] = $values[$this->get_name() . '__' . $i . '_' . $j];
                }
                if ($readonly) {
                    $attributes['disabled'] = 'disabled';
                }
                if ($this->get_option('input-align') === 'left') {
                    $attributes['style'] = 'text-align:left;';
                } else if ($this->get_option('input-align') === 'right') {
                    $attributes['style'] = 'text-align:right;';
                }
                $r .= html_writer::empty_tag('input', $attributes);
                $r .= '</td>';
            }
            if ($this->get_option('matrix-wrap-right') === '1...N.') {
                $r .= '<th class"numcol">' . ($i + 1) . '.</th>';
            }
            if ($this->get_option('matrix-wrap-right') === 'N...1.') {
                $r .= '<th class="numcol rnum">' . ($visrows - $i) . '.</th>';
            }
            if ($i == 0 && $colmod && !$readonly) {
                $r .= '<td class="modcol">';
                $attr = [
                    'id' => $fieldname . '__addcol',
                    'aria-label' => stateful_string('matrix_add_col'),
                    'class' => 'addcol',
                    'value' => 'addcol'
                ];
                if ($viscols >= $this->colmax) {
                    $attr['disabled'] = 'disabled';
                }
                $r .= html_writer::tag('button', '+', $attr);
                $attr = [
                    'id' => $fieldname . '__remcol',
                    'aria-label' => stateful_string('matrix_remove_col'),
                    'class' => 'remcol',
                    'value' => 'remcol'
                ];
                if ($viscols <= $this->colmin) {
                    $attr['disabled'] = 'disabled';
                }
                $r .= html_writer::tag('button', '-', $attr);
                $r .= '</td>';
            }
            $r .= '</tr>';
        }
        if ($rowmod && !$readonly) { 
            $r .= '<tr class="modrow">';
            if ($leftnum) {
                $r .= '<td class"numcol"> </td>';
            }
            $r .= '<td>';
            $attr = [
                'id' => $fieldname . '__addrow',
                'aria-label' => stateful_string('matrix_add_row'),
                'class' => 'addrow',
                'value' => 'addrow'
            ];
            if ($visrows >= $this->rowmax) {
                $attr['disabled'] = 'disabled';
            }
            $r .= html_writer::tag('button', '+', $attr);
            $attr = [
                'id' => $fieldname . '__remrow',
                'aria-label' => stateful_string('matrix_remove_row'),
                'class' => 'remrow',
                'value' => 'remrow'
            ];
            if ($visrows <= $this->rowmin) {
                $attr['disabled'] = 'disabled';
            }
            $r .= html_writer::tag('button', '-', $attr);
            $r .= '</td>';
            $r .= '</tr>';
        }

        $r .= '</tbody>';

        if ($this->get_option('matrix-wrap-bottom') !== '') {
            if ($this->get_option('matrix-wrap-bottom') === 'labels' && $this->get_option('matrix-mode') === 'data') {
                $r .= '<tfoot><tr>';
                if ($leftnum) {
                    $r .= '<th> </th>';
                }
                foreach ($this->cols as $coldata) {
                    $r .= '<th>' . $coldata['label'] . '</th>';
                }
                if ($rightnum) {
                    $r .= '<th> </th>';
                }
                if ($colmod) {
                    $r .= '<th> </th>';
                }
                $r .= '<tr></tfoot>';
            } else if ($this->get_option('matrix-wrap-bottom') === '1...N.') {
                $r .= '<tfoot><tr>';
                if ($leftnum) {
                    $r .= '<th> </th>';
                }
                for ($i = 1; $i <= $this->colmax; $i++) {
                    if ($i > $viscols) {
                        $r .= '<th style="display:none;" class="col' . ($i - 1) . '">' . $i . '.</th>';
                    } else {
                        $r .= '<th class="col' . ($i - 1) . '">' . $i . '.</th>';
                    }
                }
                if ($rightnum) {
                    $r .= '<th> </th>';
                }
                if ($colmod) {
                    $r .= '<th> </th>';
                }
                $r .= '<tr></tfoot>';
            } else if ($this->get_option('matrix-wrap-bottom') === 'N...1.') {
                $r .= '<tfoot><tr>';
                if ($leftnum) {
                    $r .= '<th> </th>';
                }
                for ($i = 1; $i <= $this->colmax; $i++) {
                    if ($i > $viscols) {
                        $r .= '<th style="display:none;" class="bnum col' . ($i - 1) . '">' . ($viscols - $i + 1) . '.</th>';
                    } else {
                        $r .= '<th class="bnum col' . ($i - 1) . '">' . ($viscols - $i + 1) . '.</th>';
                    }
                }
                if ($rightnum) {
                    $r .= '<th> </th>';
                }
                if ($colmod) {
                    $r .= '<th> </th>';
                }
                $r .= '<tr></tfoot>';
            }
        }

        $r .= '</table>';

        // If we had fancy wrappers we need to close them.
        if ($left !== false || $right !== false) {
            $r .= '</td>';
            if ($right !== false) {
                $r .= '<td class="' . $right . '1"> </td>';
            }
            $r .= '</tr>';
            for ($i = 2; $i < 5; $i++) {
                $r .= '<tr>';
                if ($left !== false) {
                    $r .= '<td class="' . $left . $i . '"> </td>';
                }
                if ($right !== false) {
                    $r .= '<td class="' . $right . $i . '"> </td>';
                }
                $r .= '</tr>';
            }
            $r .= '</table>';
        }

        return $r;
    }

    public function render_scripts(string $prefix, bool $readonly = false): array {
        $r = [];

        if (!$readonly && $this->get_option('validation-box') !== null && $this->get_option('validation-box') !== '') {
            $keys = [];
            $v = $this->get_name() . '__val';
            foreach ($this->get_expected_data() as $key => $value) {
                if ($key !== $v) {
                    $keys[] = $key;
                }
            }
            $r['js_call_amd'] = [];
            $r['js_call_amd'][] = ['qtype_stateful/ajaxvalidation',
                'register',
                [$keys, $prefix]];
        
        }

        if ($this->rowmin !== $this->rowmax) {
            if (!isset($r['js_call_amd'])) {
                $r['js_call_amd'] = [];
            }
            $r['js_call_amd'][] = ['qtype_stateful/matrix',
                'registerRowMod',
                [$this->get_name(), $prefix, $this->rowmin, $this->rowmax, $this->get_option('matrix-wrap-left') === 'N...1.', $this->get_option('matrix-wrap-right') === 'N...1.']];   
        }

        if (($this->colmin !== $this->colmax) && ($this->get_option('matrix-mode') !== 'data')) {
            if (!isset($r['js_call_amd'])) {
                $r['js_call_amd'] = [];
            }
            $r['js_call_amd'][] = ['qtype_stateful/matrix',
                'registerColMod',
                [$this->get_name(), $prefix, $this->colmin, $this->colmax, $this->get_option('matrix-wrap-top') === 'N...1.', $this->get_option('matrix-wrap-bottom') === 'N...1.']];   
        }

        return $r;
    }

    public function get_initialisation_commands(): string {
        // We need to generate the teachers answer as well 
        // as the size parameters.
        $init = 'block([simp],simp:false,[' . $this->rawteachersanswer . ',stack_dispvalue(' . $this->rawteachersanswer . ')';
        // 2,3,4 row min,max,init
        if (trim($this->get_option('matrix-min-rows')) !== '') {
            $init .= ',ev(' . trim($this->get_option('matrix-min-rows')) . ',simp)';
        } else {
            $init .= ',ev(matrix_size(' . $this->rawteachersanswer . ')[1],simp)';
        }
        if (trim($this->get_option('matrix-max-rows')) !== '') {
            $init .= ',ev(' . trim($this->get_option('matrix-max-rows')) . ',simp)';
        } else {
            $init .= ',ev(matrix_size(' . $this->rawteachersanswer . ')[1],simp)';
        }
        if (trim($this->get_option('matrix-init-rows')) !== '') {
            $init .= ',ev(' . trim($this->get_option('matrix-init-rows')) . ',simp)';
        } else {
            $init .= ',ev(matrix_size(' . $this->rawteachersanswer . ')[1],simp)';
        }

        // 5,6,7 col min,max,init
        if (trim($this->get_option('matrix-min-columns')) !== '') {
            $init .= ',ev(' . trim($this->get_option('matrix-min-columns')) . ',simp)';
        } else {
            $init .= ',ev(matrix_size(' . $this->rawteachersanswer . ')[2],simp)';
        }
        if (trim($this->get_option('matrix-max-columns')) !== '') {
            $init .= ',ev(' . trim($this->get_option('matrix-max-columns')) . ',simp)';
        } else {
            $init .= ',ev(matrix_size(' . $this->rawteachersanswer . ')[2],simp)';
        }
        if (trim($this->get_option('matrix-init-columns')) !== '') {
            $init .= ',ev(' . trim($this->get_option('matrix-init-columns')) . ',simp)';
        } else {
            $init .= ',ev(matrix_size(' . $this->rawteachersanswer . ')[2],simp)';
        }

        // Now then if we are in "data"-mode we might have dp and sf
        // numbers. And labels.
        if ($this->get_option('matrix-mode') === 'data') {
            foreach ($this->get_option('matrix-columns') as $coldata) {
                $init .= ',' . castext2_parser_utils::compile($coldata['label'], null, ['errclass' => 'stateful_cas_error', 'context' => 'TODO-label'])->toString();
                if ($coldata['type'] !== 'algebraic') {
                    if (isset($coldata['dp-min']) && trim($coldata['dp-min']) !== '') {
                        $init .= ',ev(' . $coldata['dp-min'] . ',simp)';
                    }
                    if (isset($coldata['dp-max']) && trim($coldata['dp-max']) !== '') {
                        $init .= ',ev(' . $coldata['dp-max'] . ',simp)';
                    }
                    if (isset($coldata['sf-min']) && trim($coldata['sf-min']) !== '') {
                        $init .= ',ev(' . $coldata['sf-min'] . ',simp)';
                    }
                    if (isset($coldata['sf-max']) && trim($coldata['sf-max']) !== '') {
                        $init .= ',ev(' . $coldata['sf-max'] . ',simp)';
                    }
                }
            }
        }

        if ($this->get_option('matrix-wrap-left') === 'default' ||
            $this->get_option('matrix-wrap-right') === 'default') {
            $init .= ',matrixparens';
        }

        $init .= '])';


        // Note that _EC logic is present in this from the error tracking of
        // castext, we don't consider it as evil at this point.
        $init = str_replace('_EC(', '__MAGIC(', $init);

        $validation = stack_ast_container::make_from_teacher_source($init, 'init for ' . $this->get_name());
        // Could throw some exceptions here?
        $validation->get_valid();
        $code = $validation->get_evaluationform();

        return str_replace('__MAGIC(', '_EC(', $code);
    }

    public function set_initialisation_value(MP_Node $value): void {
        // Value is a list in this case.
        $list = $value;
        if ($list instanceof MP_Root) {
            $list = $list->items[0];
        }
        if ($list instanceof MP_Statement) {
            $list = $list->statement;
        }
        $this->evaluatedteachersanswer = $list->items[0];
        $this->evaluatedteachersanswerdisp = $list->items[1];

        // Now then all six of these should be positive integers.
        $fail = false;
        if ($list->items[2] instanceof MP_Integer) {
            $this->rowmin = $list->items[2]->value;
        } else {
            $fail = true;
        }
        if ($list->items[3] instanceof MP_Integer) {
            $this->rowmax = $list->items[3]->value;
        } else {
            $fail = true;
        }
        if ($list->items[4] instanceof MP_Integer) {
            $this->rowinit = $list->items[4]->value;
        } else {
            $fail = true;
        }
        if ($list->items[5] instanceof MP_Integer) {
            $this->colmin = $list->items[5]->value;
        } else {
            $fail = true;
        }
        if ($list->items[6] instanceof MP_Integer) {
            $this->colmax = $list->items[6]->value;
        } else {
            $fail = true;
        }
        if ($list->items[7] instanceof MP_Integer) {
            $this->colinit = $list->items[7]->value;
        } else {
            $fail = true;
        }

        if ($fail) {
            throw new stateful_exception('Matrix input size-parameters need to evaluate to positive integers');
        }

        $fail = false;
        $c = 0;
        $this->cols = [];
        $i = 8;
        if ($this->get_option('matrix-mode') === 'data') {
            $this->colmax = count($this->get_option('matrix-columns'));
            $this->colinit = $this->colmax;
            $this->colmin = $this->colmax;
            foreach ($this->get_option('matrix-columns') as $coldata) {
                $this->cols[$c] = [];
                $this->cols[$c]['label'] = castext2_parser_utils::postprocess_mp_parsed($list->items[$i]);
                $i = $i + 1;
                if ($coldata['type'] !== 'algebraic') {
                    if (isset($coldata['dp-min']) && trim($coldata['dp-min']) !== '') {
                        if ($list->items[$i] instanceof MP_Integer) {
                            $this->cols[$c]['dp-min'] = $list->items[$i]->value; 
                        } else {
                            $fail = true;
                        }
                        $i = $i + 1;
                    }
                    if (isset($coldata['dp-max']) && trim($coldata['dp-max']) !== '') {
                        if ($list->items[$i] instanceof MP_Integer) {
                            $this->cols[$c]['dp-max'] = $list->items[$i]->value; 
                        } else {
                            $fail = true;
                        }
                        $i = $i + 1;
                    }
                    if (isset($coldata['sf-min']) && trim($coldata['sf-min']) !== '') {
                        if ($list->items[$i] instanceof MP_Integer) {
                            $this->cols[$c]['sf-min'] = $list->items[$i]->value; 
                        } else {
                            $fail = true;
                        }
                        $i = $i + 1;
                    }
                    if (isset($coldata['sf-max']) && trim($coldata['sf-max']) !== '') {
                        if ($list->items[$i] instanceof MP_Integer) {
                            $this->cols[$c]['sf-max'] = $list->items[$i]->value; 
                        } else {
                            $fail = true;
                        }
                        $i = $i + 1;
                    }
                }
                $c = $c + 1;
            }
        }

        if ($this->get_option('matrix-wrap-left') === 'default' ||
            $this->get_option('matrix-wrap-right') === 'default') {
            $this->wrap = $list->items[$i]->value;
        }

        if ($fail) {
            throw new stateful_exception('Matrix input column accuracy-parameters need to evaluate to positive integers');
        }

        // Then unpack the teachers answer
        if ($this->evaluatedteachersanswer instanceof MP_FunctionCall &&
            $this->evaluatedteachersanswer->name instanceof MP_Identifier && $this->evaluatedteachersanswer->name->value === 'matrix') {
            
            /* TODO: DO WE NEED THIS?
            // We cannot use the real value we need to use 
            // the display-value.
            $ast = maxima_parser_utils::parse($this->evaluatedteachersanswerdisp);
            if ($ast instanceof MP_Root) {
                $ast = $ast->items[0];
            }
            if ($ast instanceof MP_Statement) {
                $ast = $ast->statement;
            }

            $this->tacells = [];
            for ($i = 0; $i < $this->rowmax; $i++) {
                $this->tacells[] = [];
                for ($j=0; $j < $this->colmax; $j++) {
                    $this->tacells[$i][] = '';
                }
            }
            $row = 0;
            foreach ($ast->arguments as $cols) {
                $col = 0;
                foreach ($cols->items as $value) {
                    $this->tacells[$row][$col] = $value->toString();
                    $col = $col + 1;
                }
                $row = $row + 1;
            }
            */
        } else {
            throw new stateful_exception('Matrix input teachers answer is not a raw matrix, ensure that it does not have an unary prefix minus.');  
        }
    }

    public function get_schema_for_options(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        $base = parent::get_schema_for_options();

        // Basic size settings.
        $base['properties']['matrix-min-columns'] = [
            'type' => 'string', 
            'default' => '',
            'title' => stateful_string('input_option_inputmincolumns_label'),
            'description' => stateful_string('input_option_inputmincolumns_description')
        ];
        $base['properties']['matrix-init-columns'] = [
            'type' => 'string', 
            'default' => '',
            'title' => stateful_string('input_option_inputinitcolumns_label'),
            'description' => stateful_string('input_option_inputinitcolumns_description')
        ];
        $base['properties']['matrix-max-columns'] = [
            'type' => 'string', 
            'default' => '',
            'title' => stateful_string('input_option_inputmaxcolumns_label'),
            'description' => stateful_string('input_option_inputmaxcolumns_description')
        ];
        $base['properties']['matrix-min-rows'] = [
            'type' => 'string', 
            'default' => '',
            'title' => stateful_string('input_option_inputminrows_label'),
            'description' => stateful_string('input_option_inputminrows_description')
        ];
        $base['properties']['matrix-init-rows'] = [
            'type' => 'string', 
            'default' => '',
            'title' => stateful_string('input_option_inputinitrows_label'),
            'description' => stateful_string('input_option_inputinitrows_description')
        ];
        $base['properties']['matrix-max-rows'] = [
            'type' => 'string', 
            'default' => '',
            'title' => stateful_string('input_option_inputmaxrows_label'),
            'description' => stateful_string('input_option_inputmaxrows_description')
        ];

        // Operation mode.
        //  'algebraic' is the old mode of just having an array of
        //       algebraic inputs with shared options
        //  'data' is a new approach where each column can have 
        //       specific options, it will only work it the number
        //       of columns is fixed
        $modeoneof = [
            ['enum' => ['algebraic'], 'description' => stateful_string('input_option_matrixmode_enum_algebraic')],
            ['enum' => ['data'], 'description' => stateful_string('input_option_matrixmode_enum_data')]
        ];

        $base['properties']['matrix-mode'] = [
            'default' => 'algebraic',
            'type' => 'string', 
            'oneOf' => $modeoneof,
            'title' => stateful_string('input_option_matrixmode_label'),
            'description' => stateful_string('input_option_matrixmode_description')
        ];

        // Left and right parenthesis can be tuned separately 
        // and we also have some options for top and bottom as 
        // those may be relevant for the 'data'-mode.
        $leftoneof = [
            ['enum' => ['default'], 'description' => stateful_string('input_option_matrixwrap_enum_default')],
            ['enum' => ['('], 'description' => stateful_string('input_option_matrixwrapleft_enum_paren')],
            ['enum' => ['['], 'description' => stateful_string('input_option_matrixwrapleft_enum_bracket')],
            ['enum' => ['{'], 'description' => stateful_string('input_option_matrixwrapleft_enum_brace')],
            ['enum' => ['|'], 'description' => stateful_string('input_option_matrixwrap_enum_pipe')],
            ['enum' => [''], 'description' => stateful_string('input_option_matrixwrap_enum_none')],
            ['enum' => ['1...N.'], 'description' => stateful_string('input_option_matrixwrap_enum_rows_from_top')],
            ['enum' => ['N...1.'], 'description' => stateful_string('input_option_matrixwrap_enum_rows_from_bottom')]
        ];

        $base['properties']['matrix-wrap-left'] = [
            'default' => 'default',
            'type' => 'string', 
            'oneOf' => $leftoneof,
            'title' => stateful_string('input_option_matrixwrapleft_label'),
            'description' => stateful_string('input_option_matrixwrapleft_description')
        ];

        $rightoneof = [
            ['enum' => ['default'], 'description' => stateful_string('input_option_matrixwrap_enum_default')],
            ['enum' => [')'], 'description' => stateful_string('input_option_matrixwrapright_enum_paren')],
            ['enum' => [']'], 'description' => stateful_string('input_option_matrixwrapright_enum_bracket')],
            ['enum' => ['}'], 'description' => stateful_string('input_option_matrixwrapright_enum_brace')],
            ['enum' => ['|'], 'description' => stateful_string('input_option_matrixwrap_enum_pipe')],
            ['enum' => [''], 'description' => stateful_string('input_option_matrixwrap_enum_none')],
            ['enum' => ['1...N.'], 'description' => stateful_string('input_option_matrixwrap_enum_rows_from_top')],
            ['enum' => ['N...1.'], 'description' => stateful_string('input_option_matrixwrap_enum_rows_from_bottom')]
        ];

        $base['properties']['matrix-wrap-right'] = [
            'default' => 'default',
            'type' => 'string', 
            'oneOf' => $rightoneof,
            'title' => stateful_string('input_option_matrixwrapright_label'),
            'description' => stateful_string('input_option_matrixwrapright_description')
        ];


        $toponeof = [
            ['enum' => ['labels'], 'description' => stateful_string('input_option_matrixwrap_enum_labels')],
            ['enum' => [''], 'description' => stateful_string('input_option_matrixwrap_enum_none')],
            ['enum' => ['1...N.'], 'description' => stateful_string('input_option_matrixwrap_enum_columns_from_left')],
            ['enum' => ['N...1.'], 'description' => stateful_string('input_option_matrixwrap_enum_columns_from_right')]
        ];

        $base['properties']['matrix-wrap-top'] = [
            'default' => 'labels',
            'type' => 'string', 
            'oneOf' => $toponeof,
            'title' => stateful_string('input_option_matrixwraptop_label'),
            'description' => stateful_string('input_option_matrixwraptop_description')
        ];

        $bottomoneof = [
            ['enum' => ['labels'], 'description' => stateful_string('input_option_matrixwrap_enum_labels')],
            ['enum' => [''], 'description' => stateful_string('input_option_matrixwrap_enum_none')],
            ['enum' => ['1...N.'], 'description' => stateful_string('input_option_matrixwrap_enum_columns_from_left')],
            ['enum' => ['N...1.'], 'description' => stateful_string('input_option_matrixwrap_enum_columns_from_right')]
        ];

        $base['properties']['matrix-wrap-bottom'] = [
            'default' => '',
            'type' => 'string', 
            'oneOf' => $bottomoneof,
            'title' => stateful_string('input_option_matrixwrapbottom_label'),
            'description' => stateful_string('input_option_matrixwrapbottom_description')
        ];

        // Lines between columns, might be useful with 'data'-mode.
        $base['properties']['matrix-column-lines'] = [
            'default' => false,
            'type' => 'boolean',
            'title' => stateful_string('input_option_matrix_column_lines_label'),
            'description' => stateful_string('input_option_matrix_column_lines_description')
        ];

        // Column definitions for 'data'-mode, a column has:
        //  a label (castext)
        //  basic type: algebraic, numeric, unit
        //  optional sf and dp limits (casstrings)
        //
        // In 'data'-mode there must be a column definition for
        // each potenttial column, the number of columns must never
        // exceed the number of column definitions.
        $columntypeoneof =  [
            ['enum' => ['algebraic'], 'description' => stateful_string('input_option_matrixcolumns_type_enum_algebraic')],
            ['enum' => ['numeric'], 'description' => stateful_string('input_option_matrixcolumns_type_enum_numeric')],
            ['enum' => ['unit'], 'description' => stateful_string('input_option_matrixcolumns_type_enum_unit')]
        ];

        $base['properties']['matrix-columns'] = [
            'default' => [],
            'type' => 'array',
            'uniqueItems' => false,
            'items' => [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'title' => stateful_string('input_option_matrixcolumns_item_label_label')],
                    'type' => ['type' => 'string', 'title' => stateful_string('input_option_matrixcolumns_item_type_label'), 'oneOf' => $columntypeoneof, 'default' => 'algebraic'],
                    'sf-min' => ['type' => 'string', 'title' => stateful_string('input_option_matrixcolumns_item_minsf_label'), 'default' => ''],
                    'sf-max' => ['type' => 'string', 'title' => stateful_string('input_option_matrixcolumns_item_maxsf_label'), 'default' => ''],
                    'dp-min' => ['type' => 'string', 'title' => stateful_string('input_option_matrixcolumns_item_mindp_label'), 'default' => ''],
                    'dp-max' => ['type' => 'string', 'title' => stateful_string('input_option_matrixcolumns_item_maxdp_label'), 'default' => '']
                ],
                'required' => ['label']
            ],
            'title' => stateful_string('input_option_matrixcolumns_label'),
            'description' => stateful_string('input_option_matrixcolumns_description')
        ];

        // This we do not check.
        unset($base['properties']['require-same-type']);

        return $base;
    }

    public function get_default_values(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }

        $base = parent::get_default_values();
        $base['input-width'] = 8;
        $base['matrix-min-columns'] = '';
        $base['matrix-init-columns'] = '';
        $base['matrix-max-columns'] = '';
        $base['matrix-min-rows'] = '';
        $base['matrix-init-rows'] = '';
        $base['matrix-max-rows'] = '';
        $base['matrix-mode'] = 'algebraic';

        $base['matrix-wrap-left'] = 'default';
        $base['matrix-wrap-right'] = 'default';
        $base['matrix-wrap-top'] = 'labels';
        $base['matrix-wrap-bottom'] = '';

        $base['matrix-column-lines'] = false;
        $base['matrix-columns'] = [];

        unset($base['require-same-type']);


        return $base;
    }

    public function get_layout_for_options(): array {
        static $base = array();
        if (!empty($base)) {
            return $base;
        }
        
        $base = parent::get_layout_for_options();

        if (!isset($base['widgets'])) {
            $base['widgets'] = [];
        }
        $base['widgets']['matrix-columns'] = 'matrixcolumnoptions';
        $base['widgets']['matrix-mode'] = 'select';
        $base['widgets']['matrix-min-rows'] = 'casstring-integer';
        $base['widgets']['matrix-init-rows'] = 'casstring-integer';
        $base['widgets']['matrix-max-rows'] = 'casstring-integer';
        $base['widgets']['matrix-min-columns'] = 'casstring-integer';
        $base['widgets']['matrix-init-columns'] = 'casstring-integer';
        $base['widgets']['matrix-max-columns'] = 'casstring-integer';

        $base['widgets']['matrix-wrap-left'] = 'select';
        $base['widgets']['matrix-wrap-right'] = 'select';
        $base['widgets']['matrix-wrap-top'] = 'select';
        $base['widgets']['matrix-wrap-bottom'] = 'select';


        $base['fieldsets'][] = ['title' => stateful_string('input_options_matrix_size'), 'fields' => ['matrix-min-rows', 'matrix-init-rows', 'matrix-max-rows', 'matrix-min-columns', 'matrix-init-columns', 'matrix-max-columns']];

        $base['fieldsets'][] = ['title' => stateful_string('input_options_matrix_wrap_options'), 'fields' => ['matrix-wrap-left', 'matrix-wrap-right', 'matrix-wrap-top', 'matrix-wrap-bottom']];

        $base['fieldsets'][] = ['title' => stateful_string('input_options_matrix_data_options'), 'fields' => ['matrix-mode', 'matrix-columns', 'matrix-column-lines']];


        // Filter out certain options.
        $newfieldsetslist = [];
        foreach ($base['fieldsets'] as $fs) {
            if ($fs['title'] !== stateful_string('input_options_syntaxhint')) {
                $fs['fields'] = array_filter($fs['fields'], function($el) {
                    return ($el !== 'require-same-type');
                });
                $newfieldsetslist[] = $fs;
            }
        }
        $base['fieldsets'] = $newfieldsetslist;


        return $base;
    }

    public function get_value_override(): string {
        if ($this->validationdisplay instanceof stack_ast_container) {
            $tmp = $this->validationdisplay->get_evaluated();
            if ($tmp instanceof MP_Root) {
                $tmp = $tmp->items[0];
            }
            if ($tmp instanceof MP_Statement) {
                $tmp = $tmp->statement;
            }
            // It better be a string now.
            if (($this->get_option('matrix-mode') === 'data' && (
            $this->get_option('matrix-wrap-top') === 'labels' ||
            $this->get_option('matrix-wrap-bottom') === 'labels'))) {
                return $tmp->toString();
            }
            return (new MP_String('\\[' . ($tmp->value) . '\\]'))->toString();
        }

        return $this->get_name();
    }

    public function get_invalid_value_override(): ?string {
        if ($this->validationdisplay instanceof stack_ast_container) {
            $tmp = $this->validationdisplay->get_evaluated();
            if ($tmp instanceof MP_Root) {
                $tmp = $tmp->items[0];
            }
            if ($tmp instanceof MP_Statement) {
                $tmp = $tmp->statement;
            }
            // It better be a string now.
            if (($this->get_option('matrix-mode') === 'data' && (
            $this->get_option('matrix-wrap-top') === 'labels' ||
            $this->get_option('matrix-wrap-bottom') === 'labels'))) {
                return $tmp->value;
            }
            return '\\[' . $tmp->value . '\\]';
        }

        return null;
    }

    public function get_variables(): array {
        if (!$this->is_valid()) {
            return array();
        }

        $r = array();
        if (isset($this->usage['read'])) {
            $r = array_keys($this->usage['read']);
        }
        if (isset($this->usage['write'])) {
            $r = array_merge($r, array_keys($this->usage['write']));
        }
        sort($r);
        $vars = array();
        foreach ($r as $key) {
            // Units are also contants
            if (!$this->security->has_feature($key, 'constant')) {
                $vars[$key] = $key;
            }
        }
        return $vars;
    }

    public function get_units(): array {
        if (!$this->is_valid() || !$this->security->get_units()) {
            return array();         
        }
    
        $r = array();
        if (isset($this->usage['read'])) {
            $r = array_keys($this->usage['read']);
        }
        if (isset($this->usage['write'])) {
            $r = array_merge($r, array_keys($this->usage['write']));
        }
        sort($r);
        $ids = array();
        $units = stack_cas_casstring_units::get_permitted_units(0);
        foreach ($r as $key) {
            if (isset($units[$key])) {
                $ids[$key] = $key;
            }
        }
        return $ids;
    }

    public function value_to_response(MP_Node $value): array {
        $val = $value;
        if ($val instanceof MP_Root) {
            $val = $val->items[0];
        }
        if ($val instanceof MP_Statement) {
            $val = $val->statement;
        }
        $ps = ['inputform' => true, 'qmchar' => true, 'nounify' => false];
        $pf = $this->get_name() . '__';
        $fail = true;
        $validation = 'matrix(';
        $cols = -1;
        $rows = -1;
        if ($val instanceof MP_FunctionCall && $val->name instanceof MP_Identifier &&
            $val->name->value === 'matrix') {
            $fail = false;
            $rows = count($val->arguments);
            for ($i = 0; $i < $rows; $i++) {
                if ($i > 0) {
                    $validation .= ',';
                }
                $validation .= '[';
                if ($cols < count($val->arguments[$i]->items)) {
                    $cols = count($val->arguments[$i]->items);
                }
                if ($val->arguments[$i] instanceof MP_List) {
                    for ($j = 0; $j < count($val->arguments[$i]->items); $j++) {
                        if ($j > 0) {
                            $validation .= ',';
                        }
                        $r[$pf . $i . '_' . $j] = $val->arguments[$i]->items[$j]->toString($ps);
                        $validation .= $r[$pf . $i . '_' . $j];
                    }
                } else {
                    $fail = true;
                }
                $validation .= ']';
            }
        } 
        $validation .= ')';

        if ($this->rowmin !== $this->rowmax) {
            $r[$pf . 'rows'] = $rows;
        }
        if ($this->colmin !== $this->colmax && $this->get_option('matrix-mode') !== 'data') {
            $r[$pf . 'cols'] = $cols;
        }

        if ($fail) {
            throw new stateful_exception('Tried to map non matrix value to a Matrix-input');
        }

        if ($this->get_option('must-verify')) {
            $r[$pf . 'val'] = $validation;
        }

        return $r;
    }
}