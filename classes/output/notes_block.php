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
use tool_vae\local\selfpos_screen;
use tool_vae\local\situation_screen;

/**
 * The block of the annotations of one target (lot 1b): under the vaeiste's text, signed and dated, apart from the vaeiste's
 * own voice, with the form for whoever may annotate. Was note_screen::block() (lot 7, MMR-520 ticket 4, wave 4a).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notes_block implements named_templatable, renderable {
    /** @var int The vaeiste. */
    private int $candidateid;

    /** @var string note_manager::TARGET_SITUATION or TARGET_SELFPOS. */
    private string $target;

    /** @var string The record. */
    private string $fichecode;

    /** @var int The slot of the situation. */
    private int $slot;

    /** @var string The node of the self-positioning. */
    private string $ficherang;

    /** @var \stdClass[] The lines. */
    private array $rows;

    /** @var bool Whether the current user may annotate. */
    private bool $canwrite;

    /** @var string The form, already drawn. */
    private string $formhtml;

    /**
     * The block of the annotations of one target.
     *
     * @param int $candidateid
     * @param string $target note_manager::TARGET_SITUATION or TARGET_SELFPOS.
     * @param string $fichecode
     * @param int $slot
     * @param string $ficherang
     * @param \stdClass[] $rows The lines of note_manager::for_target(), the earliest first, each with ->standing.
     * @param bool $canwrite Whether the current user may annotate (note_manager::can_write()).
     * @param string $formhtml The form, drawn for whoever may annotate only (moodleform::render()).
     */
    public function __construct(
        int $candidateid,
        string $target,
        string $fichecode,
        int $slot,
        string $ficherang,
        array $rows,
        bool $canwrite,
        string $formhtml = ''
    ) {
        $this->candidateid = $candidateid;
        $this->target = $target;
        $this->fichecode = $fichecode;
        $this->slot = $slot;
        $this->ficherang = $ficherang;
        $this->rows = $rows;
        $this->canwrite = $canwrite;
        $this->formhtml = $formhtml;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/notes_block';
    }

    /**
     * The context of the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $url = $this->target === note_manager::TARGET_SITUATION
            ? situation_screen::url($this->candidateid, $this->fichecode, $this->slot)
            : selfpos_screen::url($this->candidateid, $this->fichecode, $this->ficherang);

        return [
            'heading' => get_string('notesheading', 'tool_vae'),
            'lineshtml' => $output->render(new note_lines($this->candidateid, $this->rows, $url)),
            'formhtml' => $this->canwrite && $this->formhtml !== '' ? $this->formhtml : '',
        ];
    }
}
