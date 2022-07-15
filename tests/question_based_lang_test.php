<?php

// We run tests using STACKs CAS-sessions.
require_once __DIR__ . '/../../stack/tests/fixtures/test_base.php';
require_once __DIR__ . '/../stateful/handling/json.php';
require_once __DIR__ . '/../stateful/handling/validation.php';
require_once __DIR__ . '/../stateful/handling/testing.php';

/**
 * This tests the question in materials/lang-test.json
 * The test is a simple check that the scenetext will be "1"
 *
 * @group qtype_stateful
 * @group qtype_stateful_samples
 */
class question_based_lang_test extends qtype_stack_walkthrough_test_base {

	public function load_question(): qtype_stateful_question {
        // No need to load multiple times, keep a copy at hand to
        // reuse compilation/validation results.
        static $question = null;

        if ($question === null) {
            $data = file_get_contents(__DIR__ . '/material/lang-test.json');
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

        $this->start_attempt_at_question($question, 'adaptive', 4);

        $scenetext = $question->render('scenetext');

        // The render must be "1" if its is "" then the default 
        // language was not selected. If it is something more then
        // most likely it is an error message or multiple languages 
        // were selected.
        $this->assertEquals("1", $scenetext);
    }

}