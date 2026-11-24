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

use tool_vae\local\piece_manager;
use tool_vae\local\spiece_manager;

defined('MOODLE_INTERNAL') || die();

// Not autoloaded - see withdraw_form.php for the fatal error this line prevents.
require_once($CFG->libdir . '/formslib.php');

/**
 * Attaching a file to a work situation (lot 1b), with the file picker of the core (review MMR-520).
 *
 * The same fields as the form written by hand that it replaces, under the same ids: the nature, the title, the file, the part
 * of the piece the situation is about. The file goes to a draft area of the vaeiste (piece_file_form::add_file_picker()); the
 * page reads it there and spiece_manager checks it as every piece is checked. The reminder not to name anyone sits against the
 * two fields the vaeiste types in, the title and the part: the name of the file is never kept.
 *
 * Custom data: `maxbytes` (int), the limit said on the page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class spiece_file_form extends \moodleform {
    /** The id of the reminder, drawn above the forms of the pieces by tool_vae\output\situation_piece_forms. */
    public const REMINDER_ID = 'tool_vae_anonymise_spiece';

    /**
     * Build the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $described = ['aria-describedby' => self::REMINDER_ID];

        // The heading and the help of the page of a declaration (piece_file_form), so that both say it in the same way.
        $mform->addElement('header', 'spiecefilehdr', get_string('piecefilesheading', 'tool_vae'));
        $mform->setExpanded('spiecefilehdr');
        $mform->addElement('static', 'spiecefilehelp', '', s(get_string(
            'piecefilehelp',
            'tool_vae',
            display_size((int) $this->_customdata['maxbytes'])
        )));

        $natures = ['' => get_string('piecenaturechoose', 'tool_vae')];
        foreach (spiece_manager::NATURES as $nature) {
            $natures[$nature] = get_string('piecenature:' . $nature, 'tool_vae');
        }
        $mform->addElement('select', 'filenature', get_string('piecenature', 'tool_vae'), $natures);
        $mform->setType('filenature', PARAM_ALPHA);
        $mform->addRule('filenature', null, 'required', null, 'client');

        $mform->addElement('text', 'filetitle', get_string('piecestitle', 'tool_vae'), [
            'size' => 60, 'maxlength' => piece_manager::TITLE_MAXLENGTH,
        ] + $described);
        $mform->setType('filetitle', PARAM_TEXT);

        piece_file_form::add_file_picker($mform, (int) $this->_customdata['maxbytes']);

        $mform->addElement('text', 'filepart', get_string('spiecepart', 'tool_vae'), [
            'size' => 60, 'maxlength' => spiece_manager::PART_MAXLENGTH,
        ] + $described);
        // Free text ("Page 3 <-> 4" would lose its arrow under PARAM_TEXT): checked by spiece_manager::clean_part() (width, no
        // control character) and escaped wherever it is shown.
        $mform->setType('filepart', PARAM_RAW);

        $this->add_action_buttons(false, get_string('pieceaddfilesubmit', 'tool_vae'));
    }

    /**
     * Keep the reminder attached to the two free fields when the form comes back with an error (T-72).
     */
    public function definition_after_data() {
        parent::definition_after_data();
        reminder_pointers::apply(['id_filetitle' => [self::REMINDER_ID], 'id_filepart' => [self::REMINDER_ID]]);
    }
}
