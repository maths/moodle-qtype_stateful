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

/**
 * This script serves text files generated on demand by rendering CASText
 * of a given question with a given seed. For generated data transfer needs.
 *
 * @copyright  2022 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../stack/vle_specific.php');
require_once(__DIR__ . '/stacklib.php');

global $CFG;
require_once($CFG->libdir . '/questionlib.php');

require_login();

// Start by checking that we have what we need.
if (!(isset($_GET['qaid']) && isset($_GET['id']) && isset($_GET['name']))) {
    header('HTTP/1.0 404 Not Found');
    header('Content-Type: text/plain;charset=UTF-8');
    echo 'Incomplete request';
    die();
}

// Extract the details we need for this action.
$qaid = $_GET['qaid'];
$tdid = $_GET['id'];
$name = $_GET['name'];

// Check that they are of the correct type.
if (!is_numeric($qaid) || !is_numeric($tdid)) {
    header('HTTP/1.0 404 Not Found');
    header('Content-Type: text/plain;charset=UTF-8');
    echo 'Incomplete request';
    die();
}

// So what we are doing is that we need to instanttiate the question
// of that attempt to have correct seed and then we need to render
// that specific td-file and serve it out with a specific name.
$dm = new question_engine_data_mapper();
$qa = $dm->load_question_attempt($qaid);
$question = $qa->get_question();
// The process of initting is not exactly complete here, so we push 
// in some details normally coming from elsewhere.
$sidelinedstate = new qbehaviour_stateful_state_storage($qa,
    $question->get_state_variable_identifiers());
$question->set_state($sidelinedstate);
$question->castextprocessor = new stateful_castext2_default_processor($qa);
$question->apply_attempt_state($qa->get_last_step());

if (!stack_user_can_view_question($question)) {
    header('HTTP/1.0 403 Forbidden');
    header('Content-Type: text/plain;charset=UTF-8');
    echo 'This question is not accessible for the active user';
    die();
}
// Unlock session during instantiation.
\core\session\manager::write_close();

// Make sure that the cache is good, as this is one of those places where
// the identifier for the cached item comes from outside we cannot
// cannot directly ask for it as that would allow people to force the cache
// to be regenerated.

// This will generate the cache if it is missing, which is highly unlikely.
$question->get_compiled('random');

if (!isset($question->compiledcache['td-' . $tdid])) {
    header('HTTP/1.0 404 Not Found');
    header('Content-Type: text/plain;charset=UTF-8');
    echo 'No such textdownload object in this question';
    die();
}


// Render it.
$content = $question->render('td-' . $tdid);

// Now pick some sensible headers.
header('HTTP/1.0 200 OK');
header("Content-Disposition: attachment; filename=\"$name\"");
if (strripos($name, '.csv') === strlen($name) - 4) {
    header('Content-Type: text/csv;charset=UTF-8');
} else {
    header('Content-Type: text/plain;charset=UTF-8');
}
echo($content);
