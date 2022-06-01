<?php
// This file is part of Stack - http://stack.maths.ed.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Encapsulates the location of an error happening in CAS with the actual error.
 * Allows us to decide the level of error message specificity at the point of output.
 *
 * This class also defines the syntax for those context/location paths.
 *
 * @copyright  2022 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stateful_cas_error extends stack_cas_error {


    /**
     * This works on the short path format of Stateful.
     */
    public static function interpret_context(string $context, $question) {
   		$interpreted = ['field' => '?'];
        $tokens = explode('/', $context);
        $t = array_shift($tokens);
        if ($t === 's') {
        	$t = array_shift($tokens);
        	$interpreted['scene'] = array_values($question->scenes)[intval($t)];
        	$t = array_shift($tokens);
        	if ($t === 'i') {
        		$t = array_shift($tokens);
        		$interpreted['input'] = array_values($interpreted['scene']->inputs)[intval($t)];
                if (count($tokens) > 0) {
                    $interpreted['field'] = array_shift($tokens);   
                }
        	} else if ($t === 'p') {
        		$t = array_shift($tokens);
        		$interpreted['prt'] = array_values($interpreted['scene']->prts)[intval($t)];
        		$t = array_shift($tokens);
        		if ($t === 'n') {
        			$t = array_shift($tokens);
        			$interpreted['prtnode'] = array_values($interpreted['prt']->nodes)[intval($t)];
                    if (count($tokens) > 0) {
                        $interpreted['field'] = array_shift($tokens);   
                    }       
        		} else {
        			$interpreted['field'] = $t;	
        		}
        	} else {
        		$interpreted['field'] = $t;
        	}
        } else if ($t === 'v') {
			$t = array_shift($tokens);
        	$interpreted['variable'] = array_values($question->variables)[intval($t)];
            if (count($tokens) > 0) {
                $interpreted['field'] = array_shift($tokens);   
            }
        } else {
        	$interpreted['field'] = $t;
        }

        if (count($tokens) > 0) {
        	$interpreted['position'] = array_shift($tokens);	
        }

        return $interpreted;
    }


    /**
     * Gives an error message customised to the user's role.
     *
     * Note that we do not define the type of a question in the function declaration
     * this makes it simpler for other systems (i.e. Stateful) to extend this class.
     *
     * @param $question A question that can be used to interpret the path and that can
     * tell us if the user can edit it and should therefore see more descriptive errors.
     * @return string
     */
    public function get_error($question): string {
        // NOTE the lang strings have not been created, the idea si to have something like:
        // 'errorinfeedbackvarswithdetail' = '{$a->err} in feedback-variables of {$a->prt} specifically at {$a->detail}.'
        // 'errorinfeedbackvars' = '{$a->err} in feedback-variables of {$a->prt}.'
        // Order as you want and there are other vars available.

        $ctx = $this->get_interpreted_context($question);

        // TODO...

        // Everything else is a general error.
        return stack_string('generalerrorhappened');
    }
}
