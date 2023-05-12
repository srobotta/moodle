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
 * Form class for badge message.
 *
 * @package    core
 * @subpackage badges
 * @copyright  2012 onwards Totara Learning Solutions Ltd {@link http://www.totaralms.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 */

namespace core_badges\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->libdir . '/filelib.php');

use moodleform;

/**
 * Form to edit badge message.
 *
 * @copyright  2012 onwards Totara Learning Solutions Ltd {@link http://www.totaralms.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message extends moodleform {

    /**
     * Create the form.
     */
    public function definition() {
        global $CFG, $OUTPUT;

        $mform = $this->_form;
        $badge = $this->_customdata['badge'];
        $action = $this->_customdata['action'];
        $editoroptions = $this->_customdata['editoroptions'];

        // Add hidden fields.
        $mform->addElement('hidden', 'id', $badge->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_TEXT);

        $mform->addElement('header', 'badgemessage', get_string('configuremessage', 'badges'));
        $mform->addHelpButton('badgemessage', 'variablesubstitution', 'badges');

        $mform->addElement('text', 'messagesubject', get_string('subject', 'badges'), array('size' => '70'));
        $mform->setType('messagesubject', PARAM_TEXT);
        $mform->addRule('messagesubject', null, 'required');
        $mform->addRule('messagesubject', get_string('maximumchars', '', 255), 'maxlength', 255);

        $mform->addElement('editor', 'message_editor', get_string('message', 'badges'), null, $editoroptions);
        $mform->setType('message_editor', PARAM_RAW);
        $mform->addRule('message_editor', null, 'required');

        $mform->addElement('advcheckbox', 'attachment', get_string('attachment', 'badges'), '', null, array(0, 1));
        $mform->addHelpButton('attachment', 'attachment', 'badges');
        if (empty($CFG->allowattachments)) {
            $mform->freeze('attachment');
        }

        $options = array(
                BADGE_MESSAGE_NEVER   => get_string('never'),
                BADGE_MESSAGE_ALWAYS  => get_string('notifyevery', 'badges'),
                BADGE_MESSAGE_DAILY   => get_string('notifydaily', 'badges'),
                BADGE_MESSAGE_WEEKLY  => get_string('notifyweekly', 'badges'),
                BADGE_MESSAGE_MONTHLY => get_string('notifymonthly', 'badges'),
                );
        $mform->addElement('select', 'notification', get_string('notification', 'badges'), $options);
        $mform->addHelpButton('notification', 'notification', 'badges');

        $mform->addElement('text', 'notifyemail', get_string('notifyemail', 'badges'), ['size' => '70']);
        $mform->setType('notifyemail', PARAM_TAGLIST);
        $mform->addRule('notifyemail', get_string('notifyemail_err', 'badges'),
            'callback', '\core_badges\form\message::validate_notifyemail');
        $mform->addHelpButton('notifyemail', 'notifyemail', 'badges');

        $this->add_action_buttons();
        $this->set_data($badge);
    }

    /**
     * Validates the field, by splitting the string by the "," to validate each of the email addresses if they are
     * syntactically correct.
     *
     * @param string|null $value
     * @return bool
     */
    public static function validate_notifyemail(?string $value): bool {
        global $CFG;

        // Nothing to validate, we have an empty field.
        if (trim($value) === '') {
            return true;
        }

        // Include the Rule Registry to later validate the email addresses.
        include_once($CFG->libdir . '/pear/HTML/QuickForm/RuleRegistry.php');
        $registry =& \HTML_QuickForm_RuleRegistry::singleton();

        // Split the field value by ",".
        foreach (explode(',', $value) as $email) {
            $email = trim($email);
            if (empty($email)) {
                continue;
            }
            if (!$registry->validate('email', $email)) {
                return false;
            }
        }
        return true;
    }
}
