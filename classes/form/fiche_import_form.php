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

use tool_vae\local\fiche_import;
use tool_vae\local\fiche_messages;
use tool_vae\local\fiche_refusal;
use tool_vae\output\form_default_submit;
use tool_vae\output\form_link;

defined('MOODLE_INTERNAL') || die();

// The parent class is not autoloaded - see declare_form.php for what that cost once.
require_once($CFG->libdir . '/formslib.php');

/**
 * The numbers of the records to import, and the way to get the export: fetch it, or upload it (0.47.0).
 *
 * ONE FORM, TWO BUTTONS. Fetching the export is only uploading it preceded by a download, so the two ways share the numbers and
 * the screen that follows; each button says what it does, and no JavaScript hides anything (the scenarios that play the screen
 * have none). The key of the session is the one of every moodleform: without it, nothing leaves - above all not the request to
 * data.gouv.fr, which is only ever the answer to a click on a button of a form sent by a signed-in administrator.
 *
 * THE UPLOAD IS THE FILE PICKER OF THE CORE (review MMR-520): the zip goes to a draft area of the administrator, the form sends
 * the number of the area, and the page reads the file there (draft_upload, fiche_import::upload_path()), then empties the area.
 * The file picker offers and checks the extension .zip; what the archive holds is read by fiche_archive, as before.
 *
 * Custom data: `maxbytes` (int), the largest file this site accepts to upload.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fiche_import_form extends \moodleform {
    /** @var int The size under which the limit of upload is said: the export weighs about 10 Mo and grows. */
    private const SAYS_LIMIT_BELOW = 12 * 1024 * 1024;

    /**
     * Build the form.
     */
    protected function definition() {
        global $PAGE;

        $mform = $this->_form;
        $maxbytes = (int) ($this->_customdata['maxbytes'] ?? 0);

        // THE FIRST SUBMIT BUTTON OF A FORM IS THE ONE THAT ENTER PRESSES in a field (a box ticked, a list of days): it must
        // not be the one that makes the server go out onto the Internet. This one does nothing, and the page says so.
        $mform->addElement('html', $PAGE->get_renderer('tool_vae')->render(new form_default_submit()));

        $mform->addElement('textarea', 'numbers', get_string('fcimportnumbers', 'tool_vae'), ['rows' => 6, 'cols' => 40]);
        $mform->setType('numbers', PARAM_TEXT);
        $mform->addHelpButton('numbers', 'fcimportnumbers', 'tool_vae');

        $mform->addElement('header', 'fetchhdr', get_string('fcimportfetchheading', 'tool_vae'));
        $mform->setExpanded('fetchhdr');
        $mform->addElement('static', 'fetchtext', '', get_string('fcimportfetchtext', 'tool_vae'));
        $mform->addElement('submit', 'fetchbutton', get_string('fcimportfetchbutton', 'tool_vae'));

        $mform->addElement('header', 'ziphdr', get_string('fcimportzipheading', 'tool_vae'));
        $mform->setExpanded('ziphdr');
        $mform->addElement('static', 'ziptext', '', get_string(
            'fcimportziptext',
            'tool_vae',
            $PAGE->get_renderer('tool_vae')->render(new form_link(fiche_import::SOURCE_URL, 'data.gouv.fr'))
        ));
        if ($maxbytes > 0 && $maxbytes < self::SAYS_LIMIT_BELOW) {
            $mform->addElement('static', 'zipmax', '', get_string('fcimportzipmax', 'tool_vae', display_size($maxbytes)));
        }

        // Not required by a rule of the form: the fetch button sends the same form without a file, and the page says, in the
        // words of the import, that no file was chosen (fiche_refusal::ZIP_MISSING).
        $mform->addElement('filepicker', 'exportzip', get_string('fcimportzipfile', 'tool_vae'), null, [
            'maxbytes' => $maxbytes,
            'accepted_types' => ['.zip'],
        ]);

        // Optional: the day is read from the name of the file, and typed only when the name no longer gives it.
        $mform->addElement(
            'date_selector',
            'exportdate',
            get_string('fcimportzipdate', 'tool_vae'),
            ['startyear' => 2021, 'stopyear' => (int) userdate(time(), '%Y'), 'optional' => true]
        );
        $mform->addElement('submit', 'zipbutton', get_string('fcimportzipbutton', 'tool_vae'));

        $mform->addElement('static', 'wait', '', get_string('fcimportwait', 'tool_vae'));
        $mform->addElement('cancel');
    }

    /**
     * Server side checks. The client side ones are a convenience, never a guarantee.
     *
     * The numbers only: the file is not read here (the page reads it from the draft area, once, after the form gave its data).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        try {
            fiche_import::parse_numbers((string) ($data['numbers'] ?? ''));
        } catch (fiche_refusal $refusal) {
            // The sentence is already safe: the form shows an error as it is given.
            $errors['numbers'] = fiche_messages::html($refusal);
        }

        return $errors;
    }
}
