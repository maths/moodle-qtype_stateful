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

// We use the input definition wrapper to pass certain information to the test.
require_once __DIR__ . '/../core/inputs.class.php';

interface stack_answertest {

  /* The short identifier for this test e.g. "AlgEquiv", used for selecting
  * text segments and identifying the test in the database.
  */
  public function codename(): string;

  /* Checks that the parameters for the test are present and secure. Returns a map of errors
   * or NULL if none. e.g. array("options"=>"This test does not understand that option.")
   * This will not do a full depth evaluation of the parameters it just checks their
   * existence and that they are valid CAS statements meeting the security rules
   * i.e. while we know that something like tolerance in certain numeric tests must
   * be a positive numeric value we do not try to check that here it is perfectly 
   * acceptable to check that at CAS side as long as we check that we have some value 
   * to pass there. Note that the inputs are also parameters worth checking for features.
  */
  public function validate(string $sans, string $tans, string $options, stateful_inputs $input_definitions): array;

  /* Maps the parameters to a CAS code block that generates the correct response list.
  * e.g. "ATAlgEquiv($sans,$tans)"
  */
  public function cascall(string $sans, string $tans, string $options, stateful_inputs $input_definitions): string;


  /*
   * Describes the options for this test or gives an empty array to signal no options required.
   * This is mainly used for editor side UI adjustments. Why bother drawing the box if it is not used * and so on, but can also be used to describe validation rules for more complex validation engines
   * like the ones present in certain editors.
   */
  public function option_meta(): array;

  /*
   * Signals whether the tans field is required for this test. This is mainly used for editor
   * side UI adjustments. Why bother drawing the box if it is not used and so on.
   */
  public function requires_tans(): bool;

  /*
   * Signals whether the sans needs to be a raw input. i.e. for sigfigs...
   */
  public function requires_direct_input_ref(): bool;


  // TODO: The interface should probably also provide a function returning a description for 
  //       the test to help UI work and to avoid having an implicit link between tests and 
  //       documentation.
}


