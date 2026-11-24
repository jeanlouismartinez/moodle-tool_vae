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

use tool_vae\local\situation_manager;
use tool_vae\output\form_note;
use tool_vae\output\whereami_tree;

/**
 * The title, the period, the setting and the tags of a work situation (lot 1b): saved by their own button, no version of the
 * text (D-1b-I).
 *
 * THE TAGS ARE THE BOXES OF THE PANEL "WHERE AM I": the page gives the panel, to be drawn with a box tags[] on each node a
 * situation may carry, and the form places it as the renderer draws it. They are not elements of the form: the page reads
 * them from the request once the form has been sent with its session key, and situation_manager::save_details() checks each
 * rank against the record.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class situation_details_form extends \moodleform {
    /** The id the free fields point at with aria-describedby. */
    private const REMINDER_ID = 'tool_vae_anonymise_situationdetails';

    /**
     * Build the form. The situation - vaeiste, record, slot - is in the address, not in the form.
     *
     * Custom data: `panel` (whereami_tree), the panel "where am I" with its boxes, drawn here by the renderer of the plugin
     * (until lot 7, wave 4c, the page gave it already drawn, as a string); anything else draws no panel.
     */
    protected function definition() {
        global $PAGE;

        $mform = $this->_form;
        $renderer = $PAGE->get_renderer('tool_vae');

        $mform->addElement('html', $renderer->render(form_note::short_with_id(self::REMINDER_ID)));
        $mform->addElement('text', 'title', get_string('situationtitle', 'tool_vae'), [
            'size' => 60, 'maxlength' => situation_manager::TITLE_MAXLENGTH, 'aria-describedby' => self::REMINDER_ID,
        ]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $mform->addElement('date_selector', 'periodstart', get_string('situationperiodstart', 'tool_vae'), ['optional' => true]);
        $mform->addElement('date_selector', 'periodend', get_string('situationperiodend', 'tool_vae'), ['optional' => true]);

        $mform->addElement('text', 'setting', get_string('situationsetting', 'tool_vae'), [
            'size' => 60, 'maxlength' => situation_manager::SETTING_MAXLENGTH, 'aria-describedby' => self::REMINDER_ID,
        ]);
        $mform->setType('setting', PARAM_TEXT);

        $mform->addElement('html', $renderer->render(form_note::tags_heading()));
        $panel = $this->_customdata['panel'] ?? null;
        $mform->addElement('html', $panel instanceof whereami_tree ? $renderer->render($panel) : '');

        $this->add_action_buttons(false, get_string('situationsavedetails', 'tool_vae'));
    }

    /**
     * Keep the reminder attached to the free fields when the form comes back with an error (T-72).
     */
    public function definition_after_data() {
        parent::definition_after_data();
        reminder_pointers::apply(['id_title' => [self::REMINDER_ID], 'id_setting' => [self::REMINDER_ID]]);
    }
}
