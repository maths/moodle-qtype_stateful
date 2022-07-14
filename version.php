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
$plugin->version      = 2022052000;
$plugin->requires     = 2018051700;
$plugin->component    = 'qtype_stateful';
$plugin->maturity     = MATURITY_STABLE;
$plugin->release      = '1.1 for Moodle 3.9+, PHP 7.1+, STACK 4.4+';
$plugin->dependencies = [
    'qtype_stack' => 2022071300,
    'qbehaviour_stateful' => 2020040800
];