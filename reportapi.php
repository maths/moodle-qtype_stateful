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
define('AJAX_SCRIPT', true);

/**
 * This is an API for extracting paths taken in given question.
 * It is only meant for small scale analysis purposes.
 *
 * Also this API assumes that all attempts to the question use
 * the Stateful-behaviour, which is something that will most likely
 * hold as an assumption for quite some time. IF this changes this 
 * API will also need to change.
 *
 * This API does not provide the full state and input values at
 * each step of the way, use other tools to gain those details.
 * This only aims to tell the scenes visited and the PRT branches
 * used when exiting them, while generating those details this
 * will also count the unique inputs in those scenes.
 **/

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../behaviour/stateful/behaviour.php';
require_once __DIR__ . '/../../engine/lib.php';
require_once $CFG->libdir . '/questionlib.php';
require_once __DIR__ . '/../../previewlib.php';

require_once __DIR__ . '/question.php';

require_login();

if (!isset($_GET['questionid']) || !is_numeric($_GET['questionid'])) {
	http_response_code(404);
    echo 'Provide the "questionid" GET-parameter.';
}

    
$qid = $_GET['questionid'];
$question = question_bank::load_question($qid);
question_require_capability_on($question, 'edit');

if (!($question instanceof qtype_stateful_question)) {
    http_response_code(404);
    echo 'Tried opening a question that is not of the Stateful type';
}

// We need the ordered PRT-lists for each scene, to identify the one 
// that was responsible for the state change.
$prts = [];
foreach ($question->scenes as $name => $scene) {
	$prts[$name] = [];
	foreach ($scene->prts as $key => $ignored) {
		$prts[$name][] = $key;
	}
}

// We will stream the result out one path at a time, this is not
// optimal but will work.

\core\session\manager::write_close();
header('Content-Type: application/json');

$attempts = 'SELECT id FROM {question_attempts} WHERE questionid = ? AND behaviour = ?';
$params = [$qid, 'stateful'];

$attempts = $DB->get_records_sql($attempts, $params);

echo '{"count": ' . count($attempts);
echo ', "questioid": ' . $qid;
echo ', "retrieved": "' . date("c") . '"';
echo ', "paths" : [';
$first = true;
// Not fetching everything although _data does include a lot.
$interestingkeys = ['-_sv_-2', '-_sv_-1', '_seed', '-_data', '-_seqn_post', '-_seqn_pre', '-submit', '-finish'];
list($insql, $params) = $DB->get_in_or_equal($interestingkeys, SQL_PARAMS_NAMED);
foreach ($attempts as $questionattemptid => $irrelevant) {
	if (!$first) {
		echo ',';
	} else {
		$first = false;
	}
	echo '{"questionattemptid": ' . $questionattemptid;
	$params['aid'] = $questionattemptid;
	$stepdata = 'SELECT b.id, a.sequencenumber, a.state, a.timecreated, a.userid, b.name, b.value FROM {question_attempt_steps} a JOIN {question_attempt_step_data} b ON a.id = b.attemptstepid WHERE a.questionattemptid = :aid AND b.name ' . $insql . ' ORDER BY a.sequencenumber';

	$stepdata = $DB->get_records_sql($stepdata, $params);

	$steps = [];
	$uid = null;
	$seed = null;
	$step = [];
	$current = null;
	foreach ($stepdata as $item) {
		if ($uid === null) {
			$uid = $item->userid;
		}
		if ($item->name === '_seed') {
			$seed = $item->value;
		}
		if ($uid === $item->userid) {
			$step['user'] = true;
		}
		if ($current === null) {
			$step['state'] = $item->state;
			$step['timecreated'] = $item->timecreated;
			$current = $item->sequencenumber;
		} else if ($current !== $item->sequencenumber) {
			$steps[] = $step;
			$step = [];
			$step['state'] = $item->state;
			$step['timecreated'] = $item->timecreated;
			$current = $item->sequencenumber;
		}
		$step[$item->name] = $item->value;
	}
	if ($uid === $item->userid) {
		$step['user'] = true;
	}
	$steps[] = $step;

	// Now turn the steps to scenes.
	$scene = [];
	$scenes = [];
	$current = null;
	$SCENE_CURRENT = null;
	$SCENE_PATH = null;
	$datas = [];
	$submittimes = []; // The times when we received submits from the user, excluding any 
	// autosubmit or teacher actions.
	foreach ($steps as $step) {
		if (!isset($step['user'])) {
			continue;
		}
		if (isset($step['-_sv_-1'])) {
			$SCENE_CURRENT = $step['-_sv_-1'];
		}
		if (isset($step['-_sv_-2'])) {
			$SCENE_PATH = $step['-_sv_-2'];
		}
		if (isset($step['-_data']) && (mb_strpos($step['-_data'], '[CLASSIFYING INPUT]') !== false)) {
			$datas[$step['-_data']] = true;
		}
		if (isset($step['user']) && (isset($step['-submit']) || isset($step['-finish']))) {
			$submittimes[] = intval($step['timecreated']);
		}
		if ($current === null) {
			$scene['entry'] = intval($step['timecreated']);
			$scene['exit'] = null;
			$scene['exittransition'] = null; // The PRT and branch that triggered transition.
			$scene['extraactivity'] = 0; // Count of distinct classification before transition.
			$scene['name'] = stack_utils::maxima_string_to_php_string($SCENE_CURRENT);
			$current = $SCENE_PATH;
		} else if ($current !== $SCENE_PATH) {
			// To get the ext transition parse the _data. And find the last present PRT
			// in the previous scene.
			$prevscene = stateful_utils::string_to_list($SCENE_PATH);
			$prevscene = stack_utils::maxima_string_to_php_string($prevscene[count($prevscene) - 1]);
			$data = json_decode($step['-_data'], true);
			$triggered = null;
			foreach ($prts[$prevscene] as $prt) {
				if (isset($data[$prt]) && $data[$prt] !== '[]') {
					$triggered = $prt;
				}
			}
			$scene['exittransition'] = [$triggered => stateful_utils::string_to_list($data[$triggered], true)[0]];
			// Do some datatype cleaning.
			foreach ($scene['exittransition'][$triggered] as $n => $branch) {
				$scene['exittransition'][$triggered][$n][0] = stack_utils::maxima_string_to_php_string($branch[0]);
				$scene['exittransition'][$triggered][$n][1] = $branch[1] === 'true';
			}

			$scene['exit'] = intval($step['timecreated']);
			$scene['extraactivity'] = count($datas) - 1;
			if ($scene['extraactivity'] < 0) {
				$scene['extraactivity'] = 0;
			}
			$scenes[] = $scene;
			$scene = [];
			$datas = [];
			$scene['entry'] = intval($step['timecreated']);
			$scene['exit'] = null;
			$scene['exittransition'] = null;
			$scene['extraactivity'] = 0;
			$scene['name'] = stack_utils::maxima_string_to_php_string($SCENE_CURRENT);
			$current = $SCENE_PATH;
		}
	}
	// The last scene...
	$prevscene = stateful_utils::string_to_list($SCENE_PATH);
	$prevscene = stack_utils::maxima_string_to_php_string($prevscene[count($prevscene) - 1]);
	if (isset($step['-_data'])) {
		$data = json_decode($step['-_data'], true);
		$triggered = null;
		foreach ($prts[$prevscene] as $prt) {
			if (isset($data[$prt]) && $data[$prt] !== '[]') {
				$triggered = $prt;
			}
		}
		if ($triggered !== null) {
			$scene['exittransition'] = [$triggered => stateful_utils::string_to_list($data[$triggered], true)[0]];
			foreach ($scene['exittransition'][$triggered] as $n => $branch) {
				$scene['exittransition'][$triggered][$n][0] = stack_utils::maxima_string_to_php_string($branch[0]);
				$scene['exittransition'][$triggered][$n][1] = $branch[1] === 'true';
			}
		}
	}
	$scene['exit'] = intval($steps[count($steps) - 1]['timecreated']);
	$scene['extraactivity'] = count($datas) - 1;
	if ($scene['extraactivity'] < 0) {
		$scene['extraactivity'] = 0;
	}
	$scenes[] = $scene;

	echo ', "user": ' . $uid;
	echo ', "seed": ' . $seed;
	echo ', "times": ' . json_encode($submittimes);
	echo ', "scenes": ' . json_encode($scenes);
	echo '}';
}

echo ']}';