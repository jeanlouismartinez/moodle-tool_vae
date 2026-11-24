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

namespace tool_vae\output;

use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use tool_vae\local\piece_manager;
use tool_vae\local\spiece_manager;

/**
 * The three ways the vaeiste adds a piece to a situation (lot 1b), under the reminder to anonymise: a file, by the form of
 * the core with its file picker (spiece_file_form) built and read by the page; a link and a piece already there, by plain
 * forms of the page sent with the session key - the second only when there is a piece to attach.
 *
 * Was situation_screen::piece_forms() (lot 7, MMR-520 ticket 4, wave 4a): the two plain forms are written by the template,
 * their lists and their bounds given by the class (the form of wave 1c: a form in a template).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class situation_piece_forms implements named_templatable, renderable {
    /** @var \moodle_url The page of the situation. */
    private \moodle_url $url;

    /** @var \stdClass[] The pieces that may be attached here. */
    private array $attachable;

    /** @var \tool_vae\form\spiece_file_form The form of a file. */
    private \tool_vae\form\spiece_file_form $fileform;

    /**
     * The forms.
     *
     * @param \moodle_url $url The page of the situation.
     * @param \stdClass[] $attachable The pieces of the vaeiste on the record that may be attached here (not removed, not
     *        linked to this situation already).
     * @param \tool_vae\form\spiece_file_form $fileform The form of a file, as the page built it (drawn once, here).
     */
    public function __construct(\moodle_url $url, array $attachable, \tool_vae\form\spiece_file_form $fileform) {
        $this->url = $url;
        $this->attachable = $attachable;
        $this->fileform = $fileform;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/situation_piece_forms';
    }

    /**
     * The context of the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $natures = [['value' => '', 'selected' => true, 'label' => get_string('piecenaturechoose', 'tool_vae')]];
        foreach (spiece_manager::NATURES as $nature) {
            $natures[] = ['value' => $nature, 'selected' => false, 'label' => get_string('piecenature:' . $nature, 'tool_vae')];
        }
        $part = [
            'label' => get_string('spiecepart', 'tool_vae'),
            'maxlength' => spiece_manager::PART_MAXLENGTH,
        ];
        $attach = null;
        if ($this->attachable) {
            $choices = [];
            foreach ($this->attachable as $piece) {
                $words = get_string('piecenature:' . $piece->nature, 'tool_vae');
                if ((string) $piece->title !== '') {
                    $words .= ' - ' . $piece->title;
                }
                // The title is the vaeiste's text: escaped once, by the template.
                $choices[] = [
                    'value' => (int) $piece->id,
                    'label' => $words . ' (' . userdate((int) $piece->timecreated, get_string('strftimedate', 'langconfig')) . ')',
                ];
            }
            $attach = [
                'heading' => get_string('spieceattach', 'tool_vae'),
                'choices' => $choices,
                'part' => $part,
                'submit' => get_string('spieceattach', 'tool_vae'),
            ];
        }

        return [
            'anonymise' => get_string('anonymisepiece', 'tool_vae'),
            // The file: a form of the core with its file picker, built and read by the page.
            'filehtml' => $this->fileform->render(),
            'action' => $this->url->out(false),
            'sesskey' => sesskey(),
            'link' => [
                'heading' => get_string('piecelinkheading', 'tool_vae'),
                'help' => get_string('piecelinkhelp', 'tool_vae'),
                'naturelabel' => get_string('piecenature', 'tool_vae'),
                'natures' => $natures,
                'titlelabel' => get_string('piecestitle', 'tool_vae'),
                'titlemax' => piece_manager::TITLE_MAXLENGTH,
                'urllabel' => get_string('pieceurllabel', 'tool_vae'),
                'urlmax' => piece_manager::URL_MAXLENGTH,
                'part' => $part,
                'submit' => get_string('pieceaddlinksubmit', 'tool_vae'),
            ],
            'attach' => $attach,
        ];
    }
}
