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

class restore_qtype_stateful_plugin extends restore_qtype_plugin {

    private $currentsceneid;
    private $currentprtid;

    protected function define_question_plugin_structure() {

        $paths = array();

        // List the relevant paths in the XML.
        $elements = array(
            'qtype_stateful_options'   => '/statefuloptions',
            'qtype_stateful_scenes'    => '/statefulscenes/statefulscene',
            'qtype_stateful_prts'      => '/statefulscenes/statefulscene/statefulprts/statefulprt',
            'qtype_stateful_prt_nodes' => '/statefulscenes/statefulscene/statefulprts/statefulprt/statefulprtnodes/statefulprtnode',
            'qtype_stateful_inputs'    => '/statefulscenes/statefulscene/statefulinputs/statefulinput',
            'qtype_stateful_vboxes'    => '/statefulscenes/statefulscene/statefulvboxes/statefulvbox',
            'qtype_stateful_variables' => '/statefulvariables/statefulvariable'
        );
        foreach ($elements as $elename => $path) {
            $paths[] = new restore_path_element($elename, $this->get_pathfor($path));
        }

        return $paths;
    }

    public function process_qtype_stateful_options($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $questioncreated = (bool) $this->get_mappingid('question_created', $this->get_old_parentid('question'));

        // If the question is being created by restore, save the stack options.
        if ($questioncreated) {
            $oldid = $data->id;
            $data->questionid = $this->get_new_parentid('question');
            $newitemid = $DB->insert_record('qtype_stateful_options', $data);
        }
    }

    public function process_qtype_stateful_scenes($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $questioncreated = (bool) $this->get_mappingid('question_created', $this->get_old_parentid('question'));

        $this->currentsceneid = $data->id;

        // If the question is being created by restore, save the stack options.
        if ($questioncreated) {
            $oldid = $data->id;
            $data->questionid = $this->get_new_parentid('question');
            $newitemid = $DB->insert_record('qtype_stateful_scenes', $data);
            $this->currentsceneid = $newitemid;
        }
    }

    public function process_qtype_stateful_variables($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $questioncreated = (bool) $this->get_mappingid('question_created', $this->get_old_parentid('question'));

        // If the question is being created by restore, save the stack options.
        if ($questioncreated) {
            $oldid = $data->id;
            $data->questionid = $this->get_new_parentid('question');
            $newitemid = $DB->insert_record('qtype_stateful_variables', $data);
        }
    }

    public function process_qtype_stateful_inputs($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $questioncreated = (bool) $this->get_mappingid('question_created', $this->get_old_parentid('question'));

        // If the question is being created by restore, save the stack options.
        if ($questioncreated) {
            $oldid = $data->id;
            $data->sceneid = $this->currentsceneid;
            $newitemid = $DB->insert_record('qtype_stateful_inputs', $data);
        }
    }

    public function process_qtype_stateful_vboxes($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $questioncreated = (bool) $this->get_mappingid('question_created', $this->get_old_parentid('question'));

        // If the question is being created by restore, save the stack options.
        if ($questioncreated) {
            $oldid = $data->id;
            $data->sceneid = $this->currentsceneid;
            $newitemid = $DB->insert_record('qtype_stateful_vboxes', $data);
        }
    }

    public function process_qtype_stateful_prts($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $questioncreated = (bool) $this->get_mappingid('question_created', $this->get_old_parentid('question'));

        $this->currentprtid = $data->id;

        // If the question is being created by restore, save the stack options.
        if ($questioncreated) {
            $oldid = $data->id;
            $data->sceneid = $this->currentsceneid;
            $newitemid = $DB->insert_record('qtype_stateful_prts', $data);
            $this->currentprtid = $newitemid;
        }
    }

    public function process_qtype_stateful_prt_nodes($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $questioncreated = (bool) $this->get_mappingid('question_created', $this->get_old_parentid('question'));

        // If the question is being created by restore, save the stack options.
        if ($questioncreated) {
            $oldid = $data->id;
            $data->prtid = $this->currentprtid;
            $newitemid = $DB->insert_record('qtype_stateful_prt_nodes', $data);
        }
    }
}
