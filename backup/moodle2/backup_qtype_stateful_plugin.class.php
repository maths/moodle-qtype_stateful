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

class backup_qtype_stateful_plugin extends backup_qtype_plugin {

    /**
     * @return backup_plugin_element the qtype information to attach to question element.
     */
    protected function define_question_plugin_structure() {

        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, '../../qtype', 'stateful');

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        // Now the structure, note that we have some depth here.
        // NOTE! Very specificly not including the cache here! The cache has
        // security implications and thus never transferred.
        $statefuloptions = new backup_nested_element('statefuloptions', ['id'],
            [
                'questionvariables',
                'questionnote',
                'questionsimplify',
                'assumepositive',
                'assumereal',
                'multiplicationsign',
                'sqrtsign',
                'complexno',
                'inversetrig',
                'matrixparens',
                'entryscene', // Luckily, this is a name based reference.
                'stackversion',
                'statefulversion',
                'genericmeta',
                'parlength',
                'variants'
            ]
        );

        $statefulscene = new backup_nested_element('statefulscene', ['id'],
            [
                'name',
                'description',
                'scenevariables',
                'scenetext'
            ]
        );

        $statefulvariable = new backup_nested_element('statefulvariable', ['id'],
            [
                'type',
                'name',
                'number',
                'description',
                'initialvalue'
            ]
        );

        $statefulinput = new backup_nested_element('statefulinput', ['id'],
            [
                'name',
                'type',
                'tans',
                'options'
            ]
        );

        $statefulvbox = new backup_nested_element('statefulvbox', ['id'],
            [
                'name',
                'type',
                'options'
            ]
        );

        $statefulprt = new backup_nested_element('statefulprt', ['id'],
            [
                'name',
                'value',
                'feedbackvariables',
                'firstnodename',
                'scoremode',
                'scoremodeparameters'
            ]
        );

        $statefulprtnode = new backup_nested_element('statefulprtnode', ['id'],
            [
                'name',
                'test',
                'sans',
                'tans',
                'options',
                'quiet',
                'truescoremode',
                'truescore',
                'truepenaltymode',
                'truepenalty',
                'truenextnode',
                'truefeedback',
                'truevariables',
                'truetests',
                'falsescoremode',
                'falsescore',
                'falsepenaltymode',
                'falsepenalty',
                'falsenextnode',
                'falsefeedback',
                'falsevariables',
                'falsetests'
            ]
        );

        // Do the tree building.
        $pluginwrapper->add_child($statefuloptions);

        $scenes = new backup_nested_element('statefulscenes');
        $pluginwrapper->add_child($scenes);
        $scenes->add_child($statefulscene);

        $inputs = new backup_nested_element('statefulinputs');
        $statefulscene->add_child($inputs);
        $inputs->add_child($statefulinput);

        $vboxes = new backup_nested_element('statefulvboxes');
        $statefulscene->add_child($vboxes);
        $vboxes->add_child($statefulvbox);

        $prts = new backup_nested_element('statefulprts');
        $statefulscene->add_child($prts);
        $prts->add_child($statefulprt);

        $nodes = new backup_nested_element('statefulprtnodes');
        $statefulprt->add_child($nodes);
        $nodes->add_child($statefulprtnode);

        $variables = new backup_nested_element('statefulvariables');
        $pluginwrapper->add_child($variables);
        $variables->add_child($statefulvariable);

        // Map data.
        $statefuloptions->set_source_table('qtype_stateful_options', ['questionid' => backup::VAR_PARENTID]);
        $statefulscene->set_source_table('qtype_stateful_scenes', ['questionid' => backup::VAR_PARENTID]);
        $statefulvariable->set_source_table('qtype_stateful_variables', ['questionid' => backup::VAR_PARENTID]);

        $statefulprt->set_source_table('qtype_stateful_prts', ['sceneid' => '../../id']);
        $statefulvbox->set_source_table('qtype_stateful_vboxes', ['sceneid' => '../../id']);
        $statefulinput->set_source_table('qtype_stateful_inputs', ['sceneid' => '../../id']);

        $statefulprtnode->set_source_table('qtype_stateful_prt_nodes', ['prtid' => '../../id']);

        return $plugin;
    }


}