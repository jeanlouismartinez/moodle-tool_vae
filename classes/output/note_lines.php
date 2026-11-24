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
use tool_vae\local\note_manager;
use tool_vae\local\note_screen;

/**
 * The lines of annotations of one target (lot 1b), with the sentence that no annotation validates.
 *
 * Each line is signed and dated; a withdrawn annotation is a grey line with no text; the text of a standing one is plain
 * or Markdown (format_text(), cleaned, no filter); its author alone, on a line that stands and was not restored, gets the
 * button to withdraw it when an address is given. Was note_screen::lines() (lot 7, MMR-520 ticket 4, wave 4a).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class note_lines implements named_templatable, renderable {
    /** @var int The vaeiste. */
    private int $candidateid;

    /** @var \stdClass[] The lines, the earliest first, each with ->standing. */
    private array $rows;

    /** @var \moodle_url|null The page the withdrawal is sent to; null draws no button. */
    private ?\moodle_url $url;

    /**
     * The lines.
     *
     * @param int $candidateid
     * @param \stdClass[] $rows
     * @param \moodle_url|null $url The page the withdrawal is sent to; null draws no button.
     */
    public function __construct(int $candidateid, array $rows, ?\moodle_url $url) {
        $this->candidateid = $candidateid;
        $this->rows = $rows;
        $this->url = $url;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/note_lines';
    }

    /**
     * The context of the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        $notes = [];
        $context = $this->rows ? \context_user::instance($this->candidateid) : null;
        foreach ($this->rows as $row) {
            $signature = get_string('noteby', 'tool_vae', (object) [
                'name' => note_screen::author($row),
                'when' => userdate((int) $row->timecreated, get_string('strftimedatetime', 'langconfig')),
            ]);
            if (!empty($row->importedat)) {
                $signature .= ' - ' . get_string('selfposrestored', 'tool_vae');
            }
            $withdrawn = null;
            $standing = null;
            if ($row->state === note_manager::WITHDRAWN) {
                $withdrawn = ['words' => get_string('notewithdrawn', 'tool_vae'), 'signature' => $signature];
            } else {
                $format = (int) $row->textformat === (int) FORMAT_MARKDOWN ? FORMAT_MARKDOWN : FORMAT_PLAIN;
                $mine = $row->authorid !== null && empty($row->importedat) && (int) $row->authorid === (int) $USER->id;
                $standing = [
                    'signature' => $signature,
                    // The annotation, plain or Markdown, through format_text() (cleaned, no filter), as before.
                    'texthtml' => format_text((string) $row->text, $format, ['context' => $context, 'filter' => false]),
                    // The button of the core, drawn by its renderer.
                    'buttonhtml' => $this->url !== null && !empty($row->standing) && $mine
                        ? $output->single_button(
                            new \moodle_url($this->url, ['action' => 'withdrawnote', 'note' => (int) $row->id]),
                            get_string('notewithdraw', 'tool_vae'),
                            'post'
                        )
                        : '',
                ];
            }
            $notes[] = [
                'class' => 'tool-vae-note border-start ps-2 ms-3 mb-2' . (empty($row->standing) ? ' text-muted' : ''),
                'withdrawn' => $withdrawn,
                'standing' => $standing,
            ];
        }

        return [
            'notvalidation' => get_string('notesnotvalidation', 'tool_vae'),
            'none' => $this->rows ? null : ['text' => get_string('notesnone', 'tool_vae')],
            'notes' => $notes,
        ];
    }
}
