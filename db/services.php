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
 * Services.
 *
 * @copyright  2019 Matti Harjula
 * @copyright  2019 Aalto University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
$functions = [
    'qtype_stateful_validate_input' => [
        'classname' => '\qtype_stateful\external',
        'methodname' => 'validate_input',
        'classpath' => '',
        'description' => 'Validates Stateful question type input data',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => '',
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile']
    ]
];