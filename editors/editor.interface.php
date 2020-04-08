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

interface stateful_editor_generic {
    /* Gives the name to display for this editor */
    public function get_name(): string;

    /* Gives a short description to display for this editor */
    public function get_description(): string;

    /* Executes whatever magic is needed to open an editor for this question. */
    public function open_editor(qtype_stateful_question $question, bool $clone=false);
}


interface stateful_editor_specific extends stateful_editor_generic {
    /* Does this editor have some constraints about the structure of the question? */
    public function can_edit(qtype_stateful_question $question): bool;
}