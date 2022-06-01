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
declare(strict_types = 1);


require_once __DIR__ . '/../../stacklib.php';


/**
 * Collection of utility functions for turning primitives to
 * larger statements.
 *
 * All of these will return a code block and an array describing
 * what was referenced and how.
 */
class stateful_compiler {

    /**
     * Now using STACK side compile logic. Slightly different but better 
     * to be equal.
     */
    public static function compile_keyval(string $keyval, string $errorref, string $exceptionref, stack_cas_security $securitymodel): array {

        $refs = ['read' => [], 'write' => [], 'calls' => []];
        
        if (trim($keyval) == '') {
            return ['true', $refs];
        }

        $kv = new stack_cas_keyval($keyval);
        $kv->errclass = 'stateful_cas_error';

        if (!$kv->get_valid()) {
            throw new stateful_exception(stateful_string(
                    'validity_cas_invalid',
                    ['section' => $exceptionref,
                        'position' => null, 'statement' => $keyval,
                        'error' => implode(', ', $kv->get_errors())]), 'validity_cas_invalid',
                    ['section' => $exceptionref,
                        'position' => null, 'statement' => $keyval,
                        'error' => implode(', ', $kv->get_errors())]);
        }

        $comp = $kv->compile($errorref);
        $code = [];
        if ($comp['blockexternal'] !== null) {
            $code[] = $comp['blockexternal'];
        }
        if ($comp['contextvariables'] !== null) {
            $code[] = $comp['contextvariables'];
        }
        if ($comp['statement'] !== null) {
            $code[] = $comp['statement'];
        }
        if (count($code) === 0) {
            $code = 'true';
        } else if (count($code) === 1) {
            $code = $code[0];
        } else {
            $code = implode(',', $code);
        }

        // We'll probably reprase and process that $code again, but 
        // for now the STACK version does what we want.


        return [$code, $comp['references']];
    }


    /**
     * Takes unparsed keyval, will parse it for annotations and 
     * other things that STACK keyval does not.
     *
     * Also takes an error-reference that will be used in
     * sub-statement CAS-errors and a reference for exceptions
     * messages to mark exceptions generated here before evaluation.
     *
     * The code generated evaluates to 'true' and is just a group 
     * of statements.
     */
    public static function old_compile_keyval(string $keyval, string $errorref, string $exceptionref, stack_cas_security $securitymodel): array {
        $refs = ['read' => [], 'write' => [], 'calls' => []];
        
        if (trim($keyval) == '') {
            return ['true', $refs];
        }

        // Subtle one: must protect things inside strings before we do QMCHAR tricks.
        $str = $keyval;
        $strings = stack_utils::all_substring_strings($str);
        foreach ($strings as $key => $string) {
            $str = str_replace('"'.$string.'"', '[STR:'.$key.']', $str);
        }

        $str = str_replace('?', 'QMCHAR', $str);

        foreach ($strings as $key => $string) {
            $str = str_replace('[STR:'.$key.']', '"' .$string . '"', $str);
        }


        $ast = maxima_parser_utils::parse_and_insert_missing_semicolons($str);
        if (!$ast instanceof MP_Root) {
            // If not then it is a SyntaxError.
            $syntaxerror = $ast;
            $error = $syntaxerror->getMessage();
            if (isset($syntaxerror->grammarLine) && isset($syntaxerror->grammarColumn)) {
                throw new stateful_exception(stateful_string(
                    'validity_cas_invalid',
                    ['section' => $exceptionref,
                        'position' => ['line' => $syntaxerror->grammarLine, 'col' => $syntaxerror->grammarColumn], 'statement' => $keyval,
                        'error' => $error]), 'validity_cas_invalid',
                    ['section' => $exceptionref,
                        'position' => ['line' => $syntaxerror->grammarLine, 'col' => $syntaxerror->grammarColumn], 'statement' => $keyval,
                        'error' => $error]);
            } else {
                throw new stateful_exception(stateful_string(
                    'validity_cas_invalid',
                    ['section' => $exceptionref,
                        'position' => null, 'statement' => $keyval,
                        'error' => $error]), 'validity_cas_invalid',
                    ['section' => $exceptionref,
                        'position' => null, 'statement' => $keyval,
                        'error' => $error]);
            }
        }
        // Change positioning so that exceptions make sense.
        if (isset($ast->position['fixedsemicolons'])) {
            $ast = maxima_parser_utils::position_remap($ast, $ast->position['fixedsemicolons']);
        } else {
            $ast = maxima_parser_utils::position_remap($ast, $str);
        }

        $statements = [];
        $errors = [];
        $answernotes = [];
        $filteroptions = ['998_security' => ['security' => 't']];
        $pipeline = stack_parsing_rule_factory::get_filter_pipeline(['998_security', '999_strict'], $filteroptions, true);
        $tostringparams = ['nosemicolon' => true, 'pmchar' => 1];

        // Special rewrites filtter, might be a real AST-filter at some point.
        $rewrite = function($node) use (&$errors) {
            if ($node instanceof MP_FunctionCall) {
                if ($node->name instanceof MP_Identifier && $node->name->value === 'castext') {
                    // The very special case of seeing the castext-function inside castext.
                    if (count($node->arguments) !== 1 || !($node->arguments[0] instanceof MP_String)) {
                        $errors[] = 'Keyval castext()-compiler, wrong argument. Only works with one direct raw string.';
                        $node->position['invalid'] = true;
                        return true;
                    }
                    $compiled = castext2_parser_utils::compile($node->arguments[0]->value);
                    $compiled = maxima_parser_utils::parse($compiled);
                    if ($compiled instanceof MP_Root) {
                        $compiled = $compiled->items[0];
                    }
                    if ($compiled instanceof MP_Statement) {
                        $compiled = $compiled->statement;
                    }
                    $node->parentnode->replace($node, $compiled);
                    return false;
                }
            }
            return true;
        };

        // Process the AST.
        foreach ($ast->items as $item) {
            if ($item instanceof MP_Statement) {
                // Filter the AST.
                while ($item->callbackRecurse($rewrite, true) !== true) {}

                // Apply the normal filters.
                $item = $pipeline->filter($item, $errors, $answernotes, $securitymodel);

                // Render.
                $scope = stack_utils::php_string_to_maxima_string($errorref . '|' . $item->position['start'] . '-' . $item->position['end']);
                $statements[] = '_EC(errcatch(' . $item->toString($tostringparams) . '),' . $scope . ')';

                // Update references.
                $refs = maxima_parser_utils::variable_usage_finder($item, $refs);
            }
        }

        // Check if everything was valid.
        $hasinvalid = false;
        $findinvalid = function($node) use(&$hasinvalid) {
            if (isset($node->position['invalid']) && $node->position['invalid'] === true) {
                $hasinvalid = true;
                return false;
            }
            return true;
        };
        $ast->callbackRecurse($findinvalid, false);

        if ($hasinvalid) {
            throw new stateful_exception(stateful_string(
                    'validity_cas_invalid',
                    ['section' => $exceptionref,
                        'position' => null, 'statement' => $keyval,
                        'error' => implode(', ', $errors)]), 'validity_cas_invalid',
                    ['section' => $exceptionref,
                        'position' => null, 'statement' => $keyval,
                        'error' => implode(', ', $errors)]);
        }
        
        // Then glue it together.
        if (count($statements) === 0) {
            return ['true', $refs];
        }

        $code = '(' . implode(',', $statements) . ',true)';

        return [$code, $refs];
    }


}