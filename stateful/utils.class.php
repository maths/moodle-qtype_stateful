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

// We provide extended functionality over the STACK utils class.
require_once __DIR__ . '/../stacklib.php';

class stateful_utils {

    public static function stackmap_to_php_array(string $grind_representation):
    array{
        $top = stateful_utils::string_to_list($grind_representation);
        if ($top[0] !== '"stack_map"') {
            throw new stateful_exception(

   'To parse a stack_map we need to have something like a stack_map to parse.');
        }
        $result = [];
        for ($i = 1; $i < count($top); $i++) {
            $item = stateful_utils::string_to_list($top[$i]);
            $key  = stack_utils::maxima_string_to_php_string($item[0]);
            if (trim($item[1]) === 'false') {
                $result[$key] = false;
            } else if (trim($item[1]) === 'true') {
                $result[$key] = true;
            } else if (strpos(trim($item[1]), '"') === 0) {
                $result[$key] = stack_utils::maxima_string_to_php_string(trim(
                    $item[1]));
            } else if (is_numeric(trim($item[1]))) {
                $result[$key] = trim($item[1]) + 0;
            } else if (trim($item[1]) === 'und') {
                $result[$key] = NULL;
            } else {
                // We might want to parse nested structures but for now we ignore them.
                // Note that the next function will break them on return...
                $result[$key] = trim($item[1]);
            }
        }
        return $result;
    }

    public static function php_array_to_stackmap(array $array_representation):
    string {
        $result = ['["stack_map"'];

        foreach ($array_representation as $key => $value) {
            $item = '[' . stack_utils::php_string_to_maxima_string($key) . ',';
            switch (gettype($value)) {
            case 'string':
                $item = $item . stack_utils::php_string_to_maxima_string($value
                );
                break;
            case 'boolean':
                $item = $item . $value ? 'true' : 'false';
                break;
            case 'integer':
            case 'double':
                $item = $item . $value;
                break;
            case 'NULL':
                $item = $item . 'und';
                break;
                // Note that we do not deal with nesting..
            }
            $result[] = $item . ']';
        }

        return implode(',', $result) . ']';
    }

    // This takes a top level list, set or group and splits it taking into account
    // strings...
    // The original versions of those stack_utils functions should really be
    // resurrected as they did this already but were lost due to fear of strings.
    public static function string_to_list(
        string $string_with_commas_and_nesting,
        bool $deep = false
    ): array{
        $strings = stack_utils::all_substring_strings(
            $string_with_commas_and_nesting);
        $safe = stack_utils::eliminate_strings(
            $string_with_commas_and_nesting);
        $elems = stack_utils::list_to_array($safe, false);
        if (count($strings) == 0) {
            return $elems;
        }
        // If there were strings we need to inject them back.
        $c = 0;
        for ($i = 0; $i < count($elems); $i++) {
            $split = explode('""', $elems[$i]);
            if (count($split) > 1) {
                $to_implode = [];
                for ($j = 0; $j < count($split); $j++) {
                    $to_implode[] = $split[$j];
                    if ($j < (count($split) - 1)) {
                        $to_implode[] = $strings[$c];
                        $c            = $c + 1;
                    }
                }
                $elems[$i] = implode('"', $to_implode);
            }
            if ($deep) {
                $chr = mb_substr($elems[$i], 0, 1);
                if ($chr === '[' || $chr === '{') {
                    $elems[$i] = self::string_to_list($elems[$i], true);
                }
            }
        }
        return $elems;
    }

    // Takes a nested array with string valued elements assumed to 
    // represent Maxima escaped strings and turns them to raw PHP-strings.
    public static function unpack_maxima_strings(array $context): array {
        $r = [];
        foreach ($context as $value) {
            if (is_array($value)) {
                $r[] = self::unpack_maxima_strings($value);
            } else if (is_string($value)) {
                $r[] = stack_utils::maxima_string_to_php_string($value);
            } else {
                $r[] = $value;
            }
        }
        return $r;
    }

    // used to turn any string (e.g. scene-name) to understandable CAS variable/function name.
    public static function string_to_varname(string $in): string{
        $out = str_replace([' ', '-', '+', '*', '/', '[', ']'], '_', $in);
        $out = str_replace([',', '.', '!', '?', '(', ')', '&'], '_', $out);
        $out = str_replace([';', ':', '$', '@', '{', '}', '#'], '_', $out);
        $out = str_replace(['"', '`', "'", '£', '€', '<', '>'], '_', $out);
        $out = str_replace(['¤', '^', '~'], '_', $out);
        // There are more there is no doubt of it but lets start with these.
        if (ctype_digit(mb_substr($out, 0, 1))) {
            $out = '_' . $out;
        }
        return $out;
    }

    // Turns MP_Nodes to raw PHP objects like strings/numbers arrays...
    // Note that this identifies stackmaps by default.
    // Also after this has done its thing you will not be able to separate string from identifiers.
    // Intended for processing complex return values from CAS.
    public static function mp_to_php(
        MP_Node $in,
        bool $stackmaps = true
    ) {
        if ($in instanceof MP_Atom) {
            return $in->value;
        }
        if ($in instanceof MP_Root) {
            return self::mp_to_php($in->items[0]);
        }
        if ($in instanceof MP_Statement) {
            return self::mp_to_php($in->statement);
        }
        if ($in instanceof MP_Set || ($in instanceof MP_List && !$stackmaps)) {
            $r = [];
            foreach ($in->items as $item) {
                $r[] = self::mp_to_php($item);
            }
            return $r;
        }
        if ($in instanceof MP_List) {
            $r = [];
            foreach ($in->items as $item) {
                $r[] = self::mp_to_php($item);
            }
            if (count($r) > 0 && $r[0] === 'stack_map') {
                $m = [];
                for ($i = 1; $i < count($r); $i++) {
                    $m[$r[$i][0]] = $r[$i][1];
                }
                return $m;
            } else {
                return $r;
            }
        }

        throw new stateful_exception(
            'Tried to convert something not fully evaluated to PHP object.');
    }

    // Everthing but objects for now.
    public static function php_to_maxima($something): string {
        if ($something === null) {
            return 'und';
        }
        switch (gettype($something)) {
            case 'string':
                return stack_utils::php_string_to_maxima_string($something
                );
                break;
            case 'boolean':
                return $something ? 'true' : 'false';
                break;
            case 'integer':
            case 'double':
                return '' . $something;
                break;
            case 'array':
                $r = '[';
                if(array_keys($something) !== range(0, count($something) - 1)) {
                    $r .= '"stack_map"';
                    foreach ($something as $key => $value) {
                        $r .= ',[' . stack_utils::php_string_to_maxima_string($key) . ',' . stateful_utils::php_to_maxima($value) . ']';
                    }
                } else {
                    $first = true;
                    foreach ($something as $key => $value) {
                        if ($first) {
                            $first = false;
                        } else {
                            $r .= ',';
                        }
                        $r .= stateful_utils::php_to_maxima($value);
                    }

                }
                $r .= ']';
                return $r;
        }
        // Something safe just in case.
        return '[]';
    }


    public static function pretty_print_maxima(string $code): string {
        if ($code === null || trim($code) === '') {
            return '';
        }
        try {
            $ast = maxima_parser_utils::parse($code);
        } catch (SyntaxError $e) {
            return '[ERRORED ' . $e->grammarLine . '-' . $e->grammarColumn .
                                                                    ']' . $code;
        }
        return $ast->toString(['pretty' => true]);
    }

    public static function inert_latex(string $latex): string {
        $escaped = str_replace('\\', '\backslash', $latex);
        $escaped = str_replace('{', '\lbrace', $escaped);
        $escaped = str_replace('}', '\rbrace', $escaped);
        $escaped = str_replace('^', '\hat{}', $escaped);
        $escaped = str_replace('_', '\_', $escaped);
        $escaped = str_replace('&', '\&', $escaped);
        $escaped = str_replace('%', '\%', $escaped);
        $escaped = str_replace('#', '\#', $escaped);
        $escaped = str_replace('~', '\tilde{}', $escaped);
        return $escaped;
    }

    // Reduces a list that has MP_String-elements mixed with other stuff.
    // By reduce we mean that it merges the adjacent MP_Strings to cut 
    // down the parsers work.
    public static function string_list_reduce(array $list, bool $ignorefirst=false): array {
        $r = [];
        $work = array_reverse($list);
        if ($ignorefirst) {
            $r[] = array_pop($work);
        }
        $tmp = null;
        while (count($work) > 0) {
            $item = array_pop($work);
            if ($item instanceof MP_String) {
                if ($tmp === null) {
                    $tmp = new MP_String($item->value);
                } else {
                    $tmp->value = $tmp->value . $item->value;
                }
            } else {
                if ($tmp !== null) {
                    $r[] = $tmp;    
                }
                $r[] = $item;
                $tmp = null;
            }
        }
        if ($tmp !== null) {
            $r[] = $tmp;    
        }
        return $r;
    }

}
