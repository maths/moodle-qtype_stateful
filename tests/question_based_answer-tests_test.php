<?php

// We run tests using STACKs CAS-sessions.
require_once __DIR__ . '/../../stack/tests/fixtures/test_base.php';
require_once __DIR__ . '/../stateful/handling/json.php';
require_once __DIR__ . '/../stateful/handling/validation.php';
require_once __DIR__ . '/../stateful/handling/testing.php';

/**
 * This tests the question in samplematerials/the-integral.json
 * The test is a brute force style iteration of various states of
 * that question.
 *
 * @group qtype_stateful
 * @group qtype_stateful_samples
 */
class stateful_question_based_answer_tests extends qtype_stack_testcase {

    public function load_question(): qtype_stateful_question {
        // No need to load multiple times, keep a copy at hand to
        // reuse compilation/validation results.
        static $question = null;

        if ($question === null) {
            $data = file_get_contents(__DIR__ . '/material/answer-tests.json');
            $data = json_decode($data, true);
            $question = stateful_handling_json::from_array($data);
            $validationresult = stateful_handling_validation::validate($question);

            // Just check that what we have is valid.
            $this->assertTrue($validationresult['result']);
        }

        return $question;
    }

    /**
     * @large
     */
    public function test_depth1() {
        // The test question is only one level deep. Lets go 
        // through all the scenes there are test transitions into
        // and check if all the tests pass in all those scenes.
        $initialstate = ['RANDOM_SEED' => 1];
        $results = stateful_handling_testing::test($this->load_question(), $initialstate);

        // The names of tests that have failed.
        $failedtests = [];
        // The names of scenes with no tests.
        $notests = [];
        foreach ($results['results'] as $test) {
            // If the top level tests do not point to valid scenes might as well fail.
            $this->assertEquals('success', $test['status'], 'Failure in root "' . $test['test'] . '"');

            // Evaluate the scene.
            $sceneresults = stateful_handling_testing::test($this->load_question(), $test['nextstate']);

            $notest = true;
            foreach ($sceneresults['results'] as $stest) {
                if ($stest['status'] === 'inactive') {
                    // pass
                } else if ($stest['status'] === 'success') {
                    $notest = false;
                } else {
                    $notest = false;
                    $failedtests[] = $test['nextstate']['SCENE_CURRENT'] . ': ' . $stest['prt'] .'/' . $stest['test'];
                }
            }
            if ($notest) {
                $notests[] = $test['nextstate']['SCENE_CURRENT'];
            }
        }

        $this->assertEquals([], $failedtests, 'Test failure(s):');
        $this->assertEquals([], $notests, 'Missing tests in scenes:');
    }
}