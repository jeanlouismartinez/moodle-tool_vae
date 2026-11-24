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

use tool_vae\output\form_note;
use tool_vae\output\remove_record_leaves;

// Not autoloaded - see withdraw_form.php for the fatal error this line prevents.
require_once($CFG->libdir . '/formslib.php');

/**
 * Confirming the removal of the row of a record whose framework Moodle no longer holds.
 *
 * A separate, deliberate screen rather than a link with a confirmation dialogue: the row cannot be recovered, and what goes with
 * it was typed by hand (the addresses, the referents, the correspondences, the replacing record). The screen lists it, so that
 * the manager sees what leaves before saying yes. The form carries the key of the session like any form of the library, and the
 * removal itself is checked again on the server (record_remover::remove()).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_record_form extends \moodleform {
    /**
     * Build the form. Custom data: `inventory` (what record_remover::inventory() gives for the record).
     */
    protected function definition() {
        global $PAGE;

        $mform = $this->_form;
        $inventory = $this->_customdata['inventory'];

        $mform->addElement('hidden', 'fichecode', $inventory->fichecode);
        $mform->setType('fichecode', PARAM_ALPHANUMEXT);

        $renderer = $PAGE->get_renderer('tool_vae');
        $mform->addElement('html', $renderer->render(form_note::removal()));
        // What leaves with the row, as a list (was self::leaves()).
        $mform->addElement('html', $renderer->render(new remove_record_leaves($inventory)));

        $this->add_action_buttons(true, get_string('removerecordbutton', 'tool_vae'));
    }
}
