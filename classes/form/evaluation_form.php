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

use tool_vae\local\evaluation_manager;
use tool_vae\output\form_note;

/**
 * Evaluating a competency (T-90, lot 3): a status and a written reason. The positioning of the structure, never a validation.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluation_form extends \moodleform {
    /**
     * Build the form. The target - candidate, record, rank - is in the address, not in the form.
     */
    protected function definition() {
        global $PAGE;

        $mform = $this->_form;

        $options = [];
        foreach (evaluation_manager::STATUSES as $status) {
            $options[$status] = get_string('evalstatus' . $status, 'tool_vae');
        }
        $mform->addElement('select', 'status', get_string('positioningstatuslabel', 'tool_vae'), $options);
        $mform->setType('status', PARAM_ALPHA);

        $mform->addElement('html', $PAGE->get_renderer('tool_vae')->render(form_note::short()));
        $mform->addElement('textarea', 'reason', get_string('positioningreasonlabel', 'tool_vae'), ['rows' => 6, 'cols' => 70]);
        // Free text: PARAM_RAW, as Moodle asks for a text area; checked by its manager and cleaned or escaped when shown.
        $mform->setType('reason', PARAM_RAW);
        $mform->addRule('reason', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('positioningsubmit', 'tool_vae'));
    }
}
