<?php

// We run tests using STACKs CAS-sessions.
require_once __DIR__ . '/../../stack/tests/fixtures/test_base.php';
require_once __DIR__ . '/../stateful/handling/json.php';
require_once __DIR__ . '/../stateful/handling/validation.php';
require_once __DIR__ . '/../stateful/handling/testing.php';

/**
 * This tests the question in samplematerials/answer-tests.json
 * It is tightly coupled to that question-definition.
 *
 * @group qtype_stateful
 * @group qtype_stateful_samples
 */
class stateful_question_based_the_integration extends qtype_stack_testcase {

    public function load_question(): qtype_stateful_question {
        // No need to load multiple times, keep a copy at hand to
        // reuse compilation/validation results.
        static $question = null;

        if ($question === null) {
            $data = file_get_contents(__DIR__ . '/material/the-integral.json');
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
    public function test_set_b_depth_4() {
        $question = $this->load_question();

        $variants = json_decode($question->variants, true);

        // Maintain a list of states to check.
        $states = [];
        $handledstates = [];
        foreach ($variants['B'] as $seed) {
            $states[] = ['RANDOM_SEED' => $seed];
        }

        // Depth check that we do not repeat too often.
        $depth = 0;

        // The names of tests that have failed.
        $failedtests = [];

        while ($depth < 4) {
            $nextstates = [];

            // Execute all states and collect next states.
            foreach ($states as $state) {
                $results = stateful_handling_testing::test($question, $state);

                foreach ($results['results'] as $test) {
                    if ($test['status'] === 'success') {
                        if (isset($test['nextstate'])) {
                            $nextstates[] = $test['nextstate'];
                        }
                    } else if ($test['status'] !== 'inactive') {
                        // Failure.
                        $fail = 'DEPTH:' . $depth . ' SEED:'. $state['RANDOM_SEED'] . ' SCENE:' . $results['origin']['SCENE_CURRENT'];
                        $failedtests[] = $fail;
                        // As the PHPUnit output cuts lines we will add more of them.
                        $fail2 = ' test: ' . $test['prt'] . '/' . $test['test'];
                        $failedtests[] = $fail2;
                    }
                }
            }
            $depth++;
            // Drop duplicates.
            $uniqs = array_unique($nextstates, SORT_REGULAR);
            $nextstates = [];
            foreach ($uniqs as $state) {
                if (array_search($state, $handledstates) === false) {
                    $nextstates[] = $state;
                }
            }
            $handledstates = array_merge($states, $handledstates);
            $states = $nextstates;
        }

        $this->assertEquals([], $failedtests, 'Test failure(s):');
    }

    /**
     * @large
     */
    public function test_set_b_depth_15_limited() {
        // Same stuff but limited transitions.
        $question = $this->load_question();

        $variants = json_decode($question->variants, true);

        // Maintain a list of states to check.
        $states = [];
        $handledstates = [];
        foreach ($variants['B'] as $seed) {
            $states[] = ['RANDOM_SEED' => $seed];
        }

        // Depth check that we do not repeat too often.
        $depth = 0;

        // The names of tests that have failed.
        $failedtests = [];

        while ($depth < 15) {
            $nextstates = [];

            // Execute all states and collect next states.
            foreach ($states as $state) {
                $results = stateful_handling_testing::test($question, $state);

                foreach ($results['results'] as $test) {
                    if ($test['status'] === 'success') {
                        if (isset($test['nextstate'])) {
                            switch($test['prt'] . '/' . $test['test']) {
                                case 'select/Correct direction':
                                case 'decision/Repeat':
                                case 'quickExit/Integrate it':
                                    $nextstates[] = $test['nextstate'];
                            }
                        }
                    } else if ($test['status'] !== 'inactive') {
                        // Failure.
                        $fail = 'DEPTH:' . $depth . ' SEED:'. $state['RANDOM_SEED'] . ' SCENE:' . $results['origin']['SCENE_CURRENT'];
                        $failedtests[] = $fail;
                        // As the PHPUnit output cuts lines we will add more of them.
                        $fail2 = ' test: ' . $test['prt'] . '/' . $test['test'];
                        $failedtests[] = $fail2;
                    }
                }
            }
            $depth++;
            // Drop duplicates.
            $uniqs = array_unique($nextstates, SORT_REGULAR);
            $nextstates = [];
            foreach ($uniqs as $state) {
                if (array_search($state, $handledstates) === false) {
                    $nextstates[] = $state;
                }
            }
            $handledstates = array_merge($states, $handledstates);
            $states = $nextstates;
        }

        $this->assertEquals([], $failedtests, 'Test failure(s):');
    }


    /**
     * @large
     */
    public function test_set_c_depth_4() {
        $question = $this->load_question();

        $variants = json_decode($question->variants, true);

        // Maintain a list of states to check.
        $states = [];
        $handledstates = [];
        foreach ($variants['C'] as $seed) {
            $states[] = ['RANDOM_SEED' => $seed];
        }

        // Depth check that we do not repeat too often.
        $depth = 0;

        // The names of tests that have failed.
        $failedtests = [];

        while ($depth < 4) {
            $nextstates = [];

            // Execute all states and collect next states.
            foreach ($states as $state) {
                $results = stateful_handling_testing::test($question, $state);

                foreach ($results['results'] as $test) {
                    if ($test['status'] === 'success') {
                        if (isset($test['nextstate'])) {
                            $nextstates[] = $test['nextstate'];
                        }
                    } else if ($test['status'] !== 'inactive') {
                        // Failure.
                        $fail = 'DEPTH:' . $depth . ' SEED:'. $state['RANDOM_SEED'] . ' SCENE:' . $results['origin']['SCENE_CURRENT'];
                        $failedtests[] = $fail;
                        // As the PHPUnit output cuts lines we will add more of them.
                        $fail2 = ' test: ' . $test['prt'] . '/' . $test['test'];
                        $failedtests[] = $fail2;
                    }
                }
            }
            $depth++;
            // Drop duplicates.
            $uniqs = array_unique($nextstates, SORT_REGULAR);
            $nextstates = [];
            foreach ($uniqs as $state) {
                if (array_search($state, $handledstates) === false) {
                    $nextstates[] = $state;
                }
            }
            $handledstates = array_merge($states, $handledstates);
            $states = $nextstates;
        }

        $this->assertEquals([], $failedtests, 'Test failure(s):');
    }

    /**
     * @large
     */
    public function test_set_c_depth_15_limited() {
        // Same stuff but limited transitions.
        $question = $this->load_question();

        $variants = json_decode($question->variants, true);

        // Maintain a list of states to check.
        $states = [];
        $handledstates = [];
        foreach ($variants['C'] as $seed) {
            $states[] = ['RANDOM_SEED' => $seed];
        }

        // Depth check that we do not repeat too often.
        $depth = 0;

        // The names of tests that have failed.
        $failedtests = [];

        while ($depth < 15) {
            $nextstates = [];

            // Execute all states and collect next states.
            foreach ($states as $state) {
                $results = stateful_handling_testing::test($question, $state);

                foreach ($results['results'] as $test) {
                    if ($test['status'] === 'success') {
                        if (isset($test['nextstate'])) {
                            switch($test['prt'] . '/' . $test['test']) {
                                case 'select/Correct direction':
                                case 'decision/Repeat':
                                case 'quickExit/Integrate it':
                                    $nextstates[] = $test['nextstate'];
                            }
                        }
                    } else if ($test['status'] !== 'inactive') {
                        // Failure.
                        $fail = 'DEPTH:' . $depth . ' SEED:'. $state['RANDOM_SEED'] . ' SCENE:' . $results['origin']['SCENE_CURRENT'];
                        $failedtests[] = $fail;
                        // As the PHPUnit output cuts lines we will add more of them.
                        $fail2 = ' test: ' . $test['prt'] . '/' . $test['test'];
                        $failedtests[] = $fail2;
                    }
                }
            }
            $depth++;
            // Drop duplicates.
            $uniqs = array_unique($nextstates, SORT_REGULAR);
            $nextstates = [];
            foreach ($uniqs as $state) {
                if (array_search($state, $handledstates) === false) {
                    $nextstates[] = $state;
                }
            }
            $handledstates = array_merge($states, $handledstates);
            $states = $nextstates;
        }

        $this->assertEquals([], $failedtests, 'Test failure(s):');
    }
}