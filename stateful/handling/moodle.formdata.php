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


// Tools for turning qtype_stateful_question objects into
// the formdata form and back.
// https://docs.moodle.org/dev/Question_data_structures
//
// formdata is a raw array representation with no nesting
// as Stateful has quite a lot of depth and high number of 
// options we do not even try to map everything to a form
// therefore we provide only standard fields in the form 
// and encode the rest as a massive singular field in YAML
// or JSON depending on which is available.

require_once __DIR__ . '/../../question.php';
require_once __DIR__ . '/json.php';

class stateful_handling_moodle_formdata {

    // One can define a target base that already has something populated
    // this will only add or overwrite to it.
    public static function to(qtype_stateful_question $from, array $target = null): array {
        $t = [];
        if ($target !== null) {
            $t = $target;
        }

        $json = stateful_handling_json::to_array($from, true);
        // Some prefil if this is an empty question.
        if (!isset($json['questionvariables']) || $json['questionvariables'] === null) {
            $json['questionvariables'] = '';
        }
        if (!isset($json['questionnote']) || $json['questionnote'] === null) {
            $json['questionnote'] = '';
        }
        if (!isset($json['scenes']) || count($json['scenes']) === 0) {
            $json['scenes'] = [
                [ 'name' => 'entry',
                  'description' => 'Initial scene for a Stateful-question, no question can exists without a scene.',
                  'scenetext' => '<p>This is the entry to this question, you will need to do the following:</p><ul><li>Add some inputs</li><li>Some PRTs to process them</li><li>Additional scenes to transition to</li></ul>'
                ]
            ];
        }
        if (!isset($json['entryscenename']) || $json['entryscenename'] === null || $json['entryscenename'] === '') {
            $json['entryscenename'] = $json['scenes'][0]['name'];
        }

        // Things depend on whether we have yaml...
        if (function_exists('yaml_emit')) {
            $t['yaml'] = yaml_emit($json, YAML_UTF8_ENCODING);
        } else {
            $t['json'] = json_encode($json, JSON_PRETTY_PRINT);
        }


        foreach (['id', 'category', 'idnumber', 'version',
                  'stamp', 'parent', 'name', 'defaultmark',
                  'questiontext', 'generalfeedback'] as $key) {
            if (isset($from->$key)) {
                $t[$key] = $from->$key;
            }
        }
        $t['qtype'] = 'stateful';
        $t['questiontextformat'] = FORMAT_HTML;
        $t['generalfeedbackformat'] = FORMAT_HTML;

        return $t;
    }

    // This is what the edit-form does i.e. mangles the questiondata 
    // object so that it is represents formdata style setup in an object
    // form instead of an array.
    public static function data_preprocessing(stdClass $questiondata): stdClass {
        $questionobject = stateful_handling_moodle_questiondata::from($questiondata);
        $json = stateful_handling_json::to_array($questionobject, true);
        // These are the JSON-fields that are present in the default
        // formdata for the default edit-form.
        unset($json['id']);
        unset($json['category']);
        unset($json['name']);
        unset($json['description']);
        unset($json['pointvalue']);

        // Some prefil if this is an empty question.
        if (!isset($json['questionvariables']) || $json['questionvariables'] === null) {
            $json['questionvariables'] = '';
        }
        if (!isset($json['questionnote']) || $json['questionnote'] === null) {
            $json['questionnote'] = '';
        }
        if (!isset($json['scenes']) || count($json['scenes']) === 0) {
            $json['scenes'] = [
                [ 'name' => 'entry',
                  'description' => 'Initial scene for a Stateful-question, no question can exists without a scene.',
                  'scenetext' => '<p>This is the entry to this question, you will need to do the following:</p><ul><li>Add some inputs</li><li>Some PRTs to process them</li><li>Additional scenes to transition to</li></ul>'
                ]
            ];
        }
        if (!isset($json['entryscenename']) || $json['entryscenename'] === null || $json['entryscenename'] === '') {
            $json['entryscenename'] = $json['scenes'][0]['name'];
        }

        // Things depend on whether we have yaml...
        if (function_exists('yaml_emit')) {
            $questiondata->yaml = yaml_emit($json, YAML_UTF8_ENCODING);
        } else {
            $questiondata->json = json_encode($json, JSON_PRETTY_PRINT);
        }

        return $questiondata;
    }

    // For some reason formdata appears both as objects and arrays?
    public static function from_obj(stdClass $from): qtype_stateful_question {
        return self::from((array) $from);
    }

    public static function from(array $from): qtype_stateful_question {
        // First try to construct a JSON representation from 
        // the formdata.
        $data = [];
        if (function_exists('yaml_emit')) {
            $data = yaml_parse($from['yaml']);
        } else {
            $data = json_decode($from['json'], true);
        }
        // To make a question from that we need to transfer some 
        // attributes from the generic formdata.
        $data['name'] = $from['name'];
        $data['description'] = $from['questiontext'];
        if (isset($from['defaultmark'])) {
            $data['pointvalue'] = intval($from['defaultmark']);
        } else {
            $data['pointvalue'] = 1;
        }

        $q = stateful_handling_json::from_array($data);

        // Transfer some identity related stuff if present.
        foreach (['id', 'category', 'idnumber', 'version', 
                  'stamp', 'parent'] as $key) {
            if (isset($from[$key])) {
                $q->$key = $from[$key];
            }
        }

        return $q;
    }

}