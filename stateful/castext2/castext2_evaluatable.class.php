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

require_once __DIR__ . '/../../../stack/stack/cas/evaluatable_object.interfaces.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/blocks/root.specialblock.php';

class castext2_evaluatable implements cas_raw_value_extractor {

    private $compiled = null;
    private $source = null;
    private $value = null;
    private $evaluated = null;
    private $valid = null;
    private $errors = null;
    private $context = null;


    public static function make_from_compiled(string $compiled, string $context): castext2_evaluatable {
        $r = new castext2_evaluatable();
        $r->valid = true;
        $r->compiled = $compiled;
        $r->context = $context;
        return $r;
    }


    public static function make_from_source(string $source, string $context): castext2_evaluatable {
        $r = new castext2_evaluatable();
        $r->source = $source;
        $r->context = $context;
        return $r;
    }

    private function __construct() {
        $this->errors = array();
    }


    public function get_valid(): bool {
        if ($this->valid !== null) {
            return $this->valid;
        }
        // If not already valid then not compiled either.
        $ast = castext2_parser_utils::parse($this->source);
        $root = stateful_cas_castext2_special_root::make($ast);

        // Collect CAS statements.
        $css  = [];

        $collectstrings = function ($node) use (&$css) {
            foreach ($node->validate_extract_attributes() as $cs) {
                $css[] = $cs;
            }
        };
        $root->callbackRecurse($collectstrings);

        $this->valid = true;
        foreach ($css as $statement) {
            $this->valid = $this->valid && $statement->get_valid();
        }

        if ($this->valid) {
            $this->compiled = $root->compile();
        }
        return $this->valid;
    }

    public function get_evaluationform(): string {
        if ($this->compiled === null) {
            if (!$this->get_valid()) {
                throw new stateful_exception('trying to get evaluation form of invalid castext');
            }
        }
        return $this->compiled;
    }

    public function set_cas_status(array $errors, array $answernotes, array $feedback) {
        $this->errors = $errors;
        if (count($this->errors) > 0) {
            $this->valid = true;
        }
    }

    public function get_source_context(): string {
        return $this->context;
    }

    public function get_key(): string {
        return '';
    }

    public function set_cas_evaluated_value(string $ast) {
        $this->value = $ast;
    }

    // Functional features start from here.
    public function get_rendered(): string {
        if ($this->evaluated === null) {
            // Do the simpler parse of the value. The full MaximaParser
            // would obviously work but would be more expensive.
            // 
            // Note that pure strings are even simpler...
            if (mb_substr($this->value, 0, 1) === '"') {
                // If it was flat.
                $this->evaluated  = stack_utils::maxima_string_to_php_string($this->value);
            } else {
                $value = stateful_utils::string_to_list($this->value, true);
                $value = stateful_utils::unpack_maxima_strings($value);
                $this->evaluated = castext2_parser_utils::postprocess_parsed($value);
            }
            
        }
        return $this->evaluated;
    }

    // TODO: make CJS stop adding functions that make no sense to the 'cas_evaluatable'.
    public function get_errors($implode = true) {
        if ($implode) {
            return implode(', ', $this->errors);
        }
        return $errors;
    }
}