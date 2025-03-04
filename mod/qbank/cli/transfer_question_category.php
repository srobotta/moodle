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
 * This script allows to do backup.
 *
 * @package    core
 * @subpackage cli
 * @copyright  2015 BFH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', 1);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/clilib.php');

$help = "Transfers a single question category from the question bank 4.x to 5.x.

Options:
-h, --help            Print out this help.
-t, --topcategory     The id of the top category to transfer.

Example:
\$ sudo -u www-data /usr/bin/php mod/qbank/cli/transfer_question_category.php -t 233445
";

list($options, $unrecognized) = cli_get_params(
    ['help' => false, 'topcategory' =>''],
    ['h' => 'help', 't' => 'topcategory']
);

if ($options['help']) {
    echo $help;
    exit(0);
}
if ((int)$options['topcategory'] <= 1) {
    echo "You must specify a top category.\n";
    exit(1);
}

/**
 * Class transfer_question_categories_cli is the same as the adhoc task transfer_question_categories
 * except that it allows to set the top category id and does not fetch all top categories.
 */
class transfer_question_categories_cli extends \mod_qbank\task\transfer_question_categories {
    protected $topcategory = 0;

    /**
     * Set the top category id.
     * @param int $topcategory
     * @return self
     */
    public function set_top_category(int $topcategory): self {
        $this->topcategory = $topcategory;
        return $this;
    }

    /**
     * Same as the parent except that it will empty the excludecategories array and
     * run only with the specified top category.
     */
    public function execute() {
        $this->excludecategories = [];
        parent::execute();
    }

    /**
     * Get the record set of the top category.
     * @return \moodle_recordset
     * @throws \moodle_exception
     */
    protected function get_record_set(): \moodle_recordset {
        global $DB;
        $parent = (int)$DB->get_field('question_categories', 'parent', ['id' => $this->topcategory]);
        if ($parent !== 0) {
            throw new \moodle_exception('error_topcategory_not_a_top_category', 'mod_qbank', $this->topcategory);
        }
        return $DB->get_recordset('question_categories', ['id' => $this->topcategory]);
    }
}

$task = new transfer_question_categories_cli();
$task->set_top_category((int)$options['topcategory'])->execute();
