<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This script deals with the fact that some question categories have the wrong parent
 * and tries to fix that.
 * This has been developed in MDL-84305 as part of the migration process. However, it
 * seems that this error still occurs at a later stage and this script is a way to fix it.
 *
 * @package    mod_qbank
 * @subpackage cli
 * @copyright  2025 BFH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', 1);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/clilib.php');

$help = "Fix question categories that have a different context id as their parent.

Options:
-h, --help     Print out this help.
-s, --show     Show the list of categories to fix.
-v, --verbose  Print out more information about the process.

Example:
\$ sudo -u www-data /usr/bin/php mod/qbank/cli/fix_wrong_parents.php
";

list($options, $unrecognized) = cli_get_params(
    ['help' => false, 'show' => false, 'verbose' => false],
    ['h' => 'help', 's' => 'show', 'v' => 'verbose']
);

if ($options['help']) {
    echo $help;
    exit(0);
}
if ($options['verbose']) {
    $CFG->debug = DEBUG_DEVELOPER;
}

$task = new \mod_qbank\task\transfer_question_categories();

if ($options['show']) {
    $list = $task->get_categories_in_a_different_context_to_their_parent();
    if (empty($list)) {
        echo "No categories have a different context id as their parent.\n";
    } else {
        echo "Child category\tChild context\n";
        foreach ($list as $id => $parent) {
            echo str_pad($id, 10) . "\t{$parent}\n";
        }
    }
} else {
    $task->fix_wrong_parents();
}
