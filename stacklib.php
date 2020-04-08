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
// This file collects together all the requirements that Stateful has on STACK
// It is well known that loading all this will often load things that are not needed
// but that cost compared to the isseus that may arise if we start using more than 
// these are of larger concern so keep this lsit short and keep it here.

// We need some localisation related things from STACK
require_once __DIR__ . '/../stack/locallib.php';

// The STACK options object is quite commontly used.
require_once __DIR__ . '/../stack/stack/options.class.php';

// There are plenty of handy utils in STACK
require_once __DIR__ . '/../stack/stack/utils.class.php';

// Inputs are used pretty much as is, although there are wrappers.
require_once __DIR__ . '/../stack/stack/input/inputbase.class.php';
require_once __DIR__ . '/../stack/stack/input/factory.class.php';

// We do every now and then ask for the list of units:
// stack_cas_casstring_units::get_permitted_units()
require_once __DIR__ . '/../stack/stack/cas/casstring.units.class.php';

// Some output processing in the form of:
// stack_maths::process_lang_string()
require_once __DIR__ . '/../stack/stack/mathsoutput/mathsoutput.class.php';

// The parser is key in many places.
require_once __DIR__ . '/../stack/stack/maximaparser/utils.php';

// We need to deal with input states when using inputs in input processing.
require_once __DIR__ . '/../stack/stack/input/inputstate.class.php';

// Casstrings are used for validation
require_once __DIR__ . '/../stack/stack/cas/ast.container.class.php';
require_once __DIR__ . '/../stack/stack/cas/ast.container.silent.class.php';
require_once __DIR__ . '/../stack/stack/cas/secure_loader.class.php';

// We do have long lists of casstrings.
require_once __DIR__ . '/../stack/stack/cas/keyval.class.php';

// Cassessions handle instantiations.
require_once __DIR__ . '/../stack/stack/cas/cassession2.class.php';

// We do reuse parts of the renderer.
require_once __DIR__ . '/../stack/renderer.php';

// Some cases like in inputs we use filter pipelines.
require_once __DIR__ . '/../stack/stack/cas/parsingrules/parsingrule.factory.php';
