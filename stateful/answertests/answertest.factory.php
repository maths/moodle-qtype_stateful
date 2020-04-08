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

// Simple source for answertest classes.
require_once __DIR__ . '/answertest.interface.php';

class stateful_answertest_factory {

    private static $cache = false;
    private static $directory = false;

    public static function get(string $name): stack_answertest {
        if (self::$cache == false) {
            self::$cache = array();
            self::$directory = array();
            // The test defintions are stored in files named xxx_name.test.php in
            // subfolders of ../answertests. The subfolders and the xxx numbering
            // are just for providing other tools categorisation hints and ordering.

            foreach (new DirectoryIterator(__DIR__ . '/') as $item) {
                if ($item->isDot()) {
                    continue;
                }
                // We only have one level of categroy dirs for now so no need to recurse.
                if ($item->isDir()) {
                    $dirname = $item->getFilename();
                    foreach (new DirectoryIterator(__DIR__ . '/' . $dirname) as $i) {
                        if ($i->isDot() || $i->isDir()) {
                            continue;
                        }
                        $itemname = $i->getFilename();
                        if (substr($itemname, strlen($itemname) - strlen('.test.php')) === '.test.php') {
                            if (!array_key_exists($dirname, self::$directory)) {
                                self::$directory[$dirname] = array();
                            }
                            $file = __DIR__ . "/$dirname/$itemname";
                            include_once($file);
                            // Cut out the order number and the suffix to get the codename and classname
                            $testname = substr($itemname, 4, -strlen('.test.php'));
                            $class = "stack_test_{$testname}";
                            if (!class_exists($class)) {
                                continue;
                            }
                            self::$cache[$testname] = new $class();
                            self::$directory[$dirname][$testname] = self::$cache[$testname];
                        }
                    }
                }
            }
        }
        if (array_key_exists($name, self::$cache)) {
            return self::$cache[$name];
        }
        return null;
    }

    // Access to all tests.
    public static function get_all(): array {
        if (self::$directory === false) {
            // Must do one load...
            self::get('AlgEquiv');
        }
        return self::$directory;
    }
}

