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
use tool_vae\output\form_note;

defined('MOODLE_INTERNAL') || die();

// Not autoloaded - see withdraw_form.php for the fatal error this line prevents.
require_once($CFG->libdir . '/formslib.php');

/**
 * Attaching a file to a declaration as a supporting piece (T-13).
 *
 * The file picker of the core (review MMR-520): the file goes to a draft area of the vaeiste, the form sends the number of the
 * area, and the page reads it there (draft_upload). The plugin still checks the file itself (type read on the content,
 * antivirus, size, digest: piece_manager). The reminder sits against the title, the only free field: the name of the file is
 * never kept.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class piece_file_form extends \moodleform {
    /** The id the title field points at with aria-describedby. */
    private const REMINDER_ID = 'tool_vae_anonymise_piecefile';

    /**
     * The extensions the file picker offers and checks: those of piece_manager::TYPES, and .jpeg beside .jpg. The type that
     * counts is the one read on the content, by piece_manager::detect_type().
     */
    public const ACCEPTED_TYPES = ['.pdf', '.odt', '.docx', '.jpg', '.jpeg', '.png'];

    /**
     * Build the form. Custom data: `evidenceid` (int), `maxbytes` (int).
     */
    protected function definition() {
        global $PAGE;

        $mform = $this->_form;

        $mform->addElement('hidden', 'evidenceid', (int) $this->_customdata['evidenceid']);
        $mform->setType('evidenceid', PARAM_INT);

        $mform->addElement('header', 'filehdr', get_string('piecefilesheading', 'tool_vae'));
        $mform->addElement('static', 'filehelp', '', get_string(
            'piecefilehelp',
            'tool_vae',
            display_size((int) $this->_customdata['maxbytes'])
        ));
        $mform->addElement('html', $PAGE->get_renderer('tool_vae')->render(form_note::piece(self::REMINDER_ID)));

        $mform->addElement(
            'select',
            'filenature',
            get_string('piecenature', 'tool_vae'),
            ['' => get_string('piecenaturechoose', 'tool_vae')] + self::natures()
        );
        $mform->setType('filenature', PARAM_ALPHA);
        $mform->addRule('filenature', null, 'required', null, 'client');
        $mform->addElement('text', 'filetitle', get_string('piecestitle', 'tool_vae'), [
            'size' => 60, 'maxlength' => piece_manager::TITLE_MAXLENGTH, 'aria-describedby' => self::REMINDER_ID,
        ]);
        $mform->setType('filetitle', PARAM_TEXT);
        self::add_file_picker($mform, (int) $this->_customdata['maxbytes']);

        $this->add_action_buttons(false, get_string('pieceaddfilesubmit', 'tool_vae'));
    }

    /**
     * Keep the reminder attached to the title when the form comes back with an error (T-72).
     */
    public function definition_after_data() {
        parent::definition_after_data();
        reminder_pointers::apply(['id_filetitle' => [self::REMINDER_ID]]);
    }

    /**
     * The file picker of a piece: one file, of the accepted types, within the limit said on the page.
     *
     * The same for the pieces of a declaration and for those of a work situation (spiece_file_form). Not required by a rule of
     * the form: no file chosen is said by the page, in the words it always used (errorpiecenofile).
     *
     * @param \MoodleQuickForm $mform
     * @param int $maxbytes
     */
    public static function add_file_picker(\MoodleQuickForm $mform, int $maxbytes): void {
        $mform->addElement('filepicker', 'piecefile', get_string('piecefilelabel', 'tool_vae'), null, [
            'maxbytes' => $maxbytes,
            'accepted_types' => self::ACCEPTED_TYPES,
        ]);
    }

    /**
     * The natures of a piece, from the closed list, labelled.
     *
     * @return string[]
     */
    public static function natures(): array {
        $natures = [];
        foreach (piece_manager::NATURES as $nature) {
            $natures[$nature] = get_string('piecenature:' . $nature, 'tool_vae');
        }

        return $natures;
    }
}
