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
defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/question/type/edit_question_form.php';


require_once __DIR__ . '/locallib.php';
require_once __DIR__ . '/stateful/handling/json.php';
require_once __DIR__ . '/stateful/handling/moodle.questiondata.php';
require_once __DIR__ . '/stateful/handling/moodle.formdata.php';
require_once __DIR__ . '/stateful/handling/validation.php';
require_once __DIR__ . '/editors/editor.factory.php';


class qtype_stateful_edit_form extends question_edit_form {

    protected function definition_inner($mform) {
        $this->add_interactive_settings();
    }

    protected function data_preprocessing($questiondata) {
        $questiondata = parent::data_preprocessing($questiondata);
        $questiondata = $this->data_preprocessing_hints($questiondata);

        $questiondata = stateful_handling_moodle_formdata::data_preprocessing($questiondata);

        return $questiondata;
    }
    
    public function qtype() {
        return 'stateful';
    }

    protected function definition() {
        parent::definition();

        // Rename this...
        $this->_form->getElement('questiontext')->setLabel(stateful_string('questiontext_ie_description'));

        // Add the other editor buttons.
        $ed = $this->_form->createElement('static', 'editordesc', stateful_string('editordesc'));
        $this->_form->insertElementBefore($ed, 'generalheader');

        $editors = stateful_editor_factory::get_all();

        foreach ($editors as $id => $editor) {
            $el = $this->_form->createElement('submit', 'editor_' . $id, $editor->get_name());
            $this->_form->insertElementBefore($el, 'generalheader');
        }

    }

    protected function add_interactive_settings($withclearwrong = false, $withshownumpartscorrect = false) {

        if (function_exists('yaml_emit')) {
            $this->_form->addElement('textarea', 'yaml', stateful_string('yaml_edit'), ['rows' => 40, 'cols' => 80]);
        } else {
            $this->_form->addElement('textarea', 'json', stateful_string('json_edit'), ['rows'  => 40, 'cols' => 80]);
        }
    }

    public function validation($fromform, $files) {
        $editors = stateful_editor_factory::get_all();
        foreach ($editors as $id => $editor) {
            if (array_key_exists('editor_' . $id, $fromform)) {
                $question = null;
                if (isset($fromform['id']) && is_integer($fromform['id'])) {
                    $question = question_bank::load_question($fromform['id']);
                } else {
                    // We need to know the category but how!?
                    $cat = substr($fromform['returnurl'], strpos($fromform['returnurl'], '&cat=') + 5);
                    $cat = substr($cat, 0, strpos($cat, '&'));
                    $cat = explode('%2C', $cat);
                    $cat = $cat[0];


                    // The new question case...
                    // Lets try to make up something.
                    $question = new qtype_stateful_question();
                    $question->category = $cat;
                    $question->name = 'New question';
                    $question->scenes = array();
                    $question->entryscene = 'entry';
                    $question->scenes['entry'] = new stateful_scene($question);
                    $question->scenes['entry']->name = 'entry';
                    $question->scenes['entry']->scenetext = '<p>For startters you probably need an input and then a PRT, some state variables and more scenes.</p>';
                }

                $editor->open_editor($question, $fromform['makecopy'] === 1);
                exit();
            }
        }

        $question = stateful_handling_moodle_formdata::from($fromform);
        $fullval = stateful_handling_validation::validate($question);
        $errors = parent::validation($fromform, $files);

        // In the YAML/JSON mode all the errors go to the same place.
        $err = '';
        $bypath = [];

        foreach ($fullval['errors'] as $e) {
            if (!isset($bypath[$e['path']])) {
                $bypath[$e['path']] = [];
            }
            $bypath[$e['path']][] = $e['message'];
        }
        ksort($bypath);
        foreach ($bypath as $path => $errs) {
            $err .= '<p>' . str_replace('|\\|', '/', $path) . '</p><ul><li>';
            $err .= implode('</li><li>', $errs);
            $err .= '</li></ul>';
        }

        
        if ($err !== '') {
            if (function_exists('yaml_emit')) {
                $errors['yaml'] = $err;
            } else {
                $errors['json'] = $err;
            }
        }


        return $errors;
    }

}