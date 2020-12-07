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

/* CASText2 parser utils */

require_once __DIR__ . '/CTP_classes.php';
require_once __DIR__ . '/processor.class.php';
require_once __DIR__ . '/../../stacklib.php';

require_once __DIR__ . '/../utils.class.php';

// Again deal with no mb_string..
require_once __DIR__ . '/autogen/parser.mbstring.php';


class castext2_parser_utils {

    // Does the whole compile process.
    public static function compile(string $castext): string {
        if ($castext === '' || $castext === null) {
            return '""';
        }

        $ast  = self::parse($castext);
        $root = stateful_cas_castext2_special_root::make($ast);
        return $root->compile();
    }

    public static function get_casstrings(string $castext): array{
        if ($castext === '' || $castext === null) {
            return [];
        }

        $ast  = self::parse($castext);
        $root = stateful_cas_castext2_special_root::make($ast);
        $css  = [];

        $collectstrings = function ($node) use (&$css) {
            foreach ($node->validate_extract_attributes() as $cs) {
                $css[] = $cs;
            }
        };
        $root->callbackRecurse($collectstrings);
        return $css;
    }

    // Postprocesses the result from CAS. For those that have not yet fully
    // parsed the response. Does not use the full maximaparser infrastructure
    // as the result is just an list of strings... well should be for all simple
    // blocks for now.
    public static function postprocess_string(string $casresult): string {
        if (mb_substr($casresult, 0, 1) === '"') {
            // If it was flat.
            return stack_utils::maxima_string_to_php_string($casresult);
        }

        $parsed = maxima_parser_utils::parse($casresult);

        return self::postprocess_mp_parsed($parsed);
    }

    // Postprocesses the result from CAS. For those that have parsed the response
    // to PHP array/string form. Note that you need to give unescaped strings...
    public static function postprocess_parsed(array $casresult, castext2_processor $processor=null): string{
        if ($processor === null) {
            $processor = new castext2_default_processor();
        }
        return $processor->process($casresult[0], $casresult);
    }

    // Postprocesses AST style result, as often one includes stuff in larger structures.
    public static function postprocess_mp_parsed(MP_Node $result, castext2_processor $processor=null): string {
        // Some common unpacking.
        if ($result instanceof MP_Root) {
            $result = $result->items[0];
        }
        if ($result instanceof MP_Statement) {
            $result = $result->statement;
        }
        if ($result instanceof MP_String) {
            return $result->value;
        }
        return self::postprocess_parsed(stateful_utils::mp_to_php($result), $processor);
    }

    // Parses a string of castext code to an AST tree for use elsewhere.
    public static function parse(string $code): CTP_Root{
        $parser = new CTP_Parser();
        $ast    = $parser->parse($code);

        // As the base parser does not do math-mode paintting we need to
        // deal with it here.

        $ast = self::math_paint($ast, $code);

        return $ast;
    }

    // Searches mathmode information and sets the nodes to match. Note that
    // This aims to ignore comments
    public static function math_paint(
        CTP_Root $ast,
        string $code
    ): CTP_Root {
        // These are the environments considered mathmode.
        static $mathmodeenvs = ['align', 'align*', 'alignat', 'alignat*',
            'eqnarray', 'eqnarray*', 'equation', 'equation*', 'gather',
            'gather*', 'multline', 'multline*'];

        // Ensure that we have the correct coding.
        $old = mb_internal_encoding();
        if ($old !== 'UTF-8') {
            mb_internal_encoding('UTF-8');
        }
        

        // First identify skipped segments. i.e. ignore the contents of comments
        $skipmap = [];

        $populateskipmap = function ($node) use (&$skipmap) {
            // First we skip the whole comment blocks.
            if ($node instanceof CTP_Block && $node->name === 'comment') {
                $skipmap[$node->position['start']] = $node->position['end'];
            } else if ($node instanceof CTP_Block) {
                // We should also ignore attributes to blocks as they will probably never
                // get to be outputted atleast at that position.
                foreach ($node->parameters as $key => $value) {
                    if ($node->name === 'if') {
                        // if is magical.
                        if ($key !== ' branch lengths' && $key === 'test') {
                            if (is_array($value)) {
                                foreach ($value as $item) {
                                    // These are the conditions for each branch.
                                    $skipmap[$item->position['start']] = $item
                                        ->position['end'];
                                }
                            } else {
                                // single branch case.
                                $skipmap[$value->position['start']] = $value->
                                position['end'];
                            }
                        }
                    } else {
                        // This probs has nothing that matters?
                        /*
                    $value[$value->position['start']] = $value->position[
                    'end'];
                     */
                    }
                }
            }
            // TODO: we might also want to handle escapes and ignore {@...@} contents.
            return true;
        };
        $ast->callbackRecurse($populateskipmap);

        // Then we scan the string for mathmode status shifts.
        $i = 0; // The current char
        $j = 0; // The current char with skipping taken into account.

        $skipped = ''; // A string that has had all the skipped parts removed.
        // First generate the skipped one. We use this to match long strings that
        // might go over a skipped bit.
        $len = mb_strlen($code);
        // Do a single splitting of the unicode string to chars.
        $chars = preg_split('//u', $code, -1, PREG_SPLIT_NO_EMPTY);
        while ($i < $len) {
            if (isset($skipmap[$i])) {
                $i = $skipmap[$i];
            } else {
                $skipped .= $chars[$i];
                $i = $i + 1;
            }
        }

        $mathmodes = [];
        $mathmode  = false;
        $i         = 0;
        $lastslash = false;
        // Then the scan
        while ($i < $len) {
            if (isset($skipmap[$i])) {
                $i = $skipmap[$i];
            } else {
                $c = $chars[$i];
                if ($c === '\\') {
                    $lastslash = !$lastslash;
                }

                if ($lastslash && $c !== '\\') {
                    $lastslash = false;
                    if ($c === '[' || $c === '(') {
                        $mathmode = true;
                    }
                    if ($c === ']' || $c === ')') {
                        $mathmode = false;
                    }
                    if ($c === 'b') {
                        // So do we have a \begin{ here?
                        $slice = mb_substr($skipped, $j);
                        if (mb_strpos($slice, 'begin{') === 0) {
                            foreach ($mathmodeenvs as $envname) {
                                if (mb_strpos($slice, 'begin{' .
                                    $envname . '}') === 0) {
                                    $mathmode = true;
                                    break;
                                }
                            }
                        }
                    }
                    if ($c === 'e') {
                        // So do we have an \end{ here?
                        $slice = mb_substr($skipped, $j);
                        if (mb_strpos($slice, 'end{') === 0) {
                            foreach ($mathmodeenvs as $envname) {
                                if (mb_strpos($slice, 'end{' . $envname
                                    . '}') === 0) {
                                    $mathmode = false;
                                    break;
                                }
                            }
                        }
                    }
                }

                $mathmodes[$i] = $mathmode;

                $i = $i + 1;
                $j = $j + 1;
            }
        }

        // Now we have the map for the mathmode of each char in the code.
        // Then to apply it.
        $paint = function ($node) use ($mathmodes) {
            if (isset($node->position) && array_key_exists($node->position['start'], $mathmodes)) {
                $node->mathmode = $mathmodes[$node->position['start']];
            }
            return true;
        };
        $ast->callbackRecurse($paint);

        if ($old !== 'UTF-8') {
            mb_internal_encoding($old);
        }

        return $ast;
    }

}