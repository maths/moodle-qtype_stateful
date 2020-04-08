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
require_once __DIR__ . '/editor.interface.php';

class stateful_editor_factory {

    private static $cache = false;

    public static function get(string $name): ?stateful_editor_generic {
        if (self::$cache == false) {
            self::$cache = array();

            foreach (new DirectoryIterator(__DIR__ . '/') as $item) {
                if ($item->isDot()) {
                    continue;
                }
                if ($item->isDir()) {
                    $dirname = $item->getFilename();
                    if (file_exists(__DIR__ . '/' . $dirname . '/' . $dirname . '.editor.php')) {
                        include_once(__DIR__ . '/' . $dirname . '/' . $dirname . '.editor.php');
                        $classname = $dirname . '_editor';
                        if (!class_exists($classname)) {
                            continue;
                        }
                        self::$cache[$dirname] = new $classname();
                    }
                }
            }
        }
        if (array_key_exists($name, self::$cache)) {
            return self::$cache[$name];
        }
        return null;
    }

    public static function get_all(): array {
        if (self::$cache === false) {
            // Must do one load...
            self::get('whatever');
        }
        return self::$cache;
    }
}

