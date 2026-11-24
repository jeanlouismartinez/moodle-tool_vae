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

namespace tool_vae\form;

defined('MOODLE_INTERNAL') || die();

// Not autoloaded - see withdraw_form.php for the fatal error this line prevents.
require_once($CFG->libdir . '/formslib.php');

use tool_vae\local\scope_manager;
use tool_vae\output\form_note;

/**
 * Saying that a competency is outside one's activity (T-42).
 *
 * The choice is checked again on the server (scope_manager::mark()), which is where it counts.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scope_form extends \moodleform {
    /**
     * Build the form. Custom data: `options` (the code and rank of a competency, joined by a hyphen => label to show).
     */
    protected function definition() {
        global $PAGE;

        $mform = $this->_form;
        $options = (array) ($this->_customdata['options'] ?? []);

        $mform->addElement('select', 'competency', get_string('scopecompetency', 'tool_vae'), $options);
        $mform->addRule('competency', null, 'required', null, 'client');
        // Not PARAM_ALPHANUMEXT: the key is the idnumber of a competency, which tool_lp lets an administrator change into any text
        // (RNCP36832-C7.1, RNCP36832-BC 1: evidence_manager::validate()). The select only returns one of its own keys.
        $mform->setType('competency', PARAM_RAW_TRIMMED);

        // The note is free text: the reminder is on it, as on every free field.
        $mform->addElement('html', $PAGE->get_renderer('tool_vae')->render(form_note::short()));
        $mform->addElement('text', 'note', get_string('scopenote', 'tool_vae'), [
            'maxlength' => scope_manager::NOTE_MAXLENGTH,
            'size' => 60,
        ]);
        $mform->setType('note', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('scopeadd', 'tool_vae'));
    }
}
