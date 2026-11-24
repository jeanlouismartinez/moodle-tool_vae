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
use tool_vae\local\situation_screen;

/**
 * The annotations of the situations of a record that were deleted (D-1b-K): each former situation apart, said deleted;
 * nothing when there is none (drawn() says so). Was note_screen::orphans() (lot 7, MMR-520 ticket 4, wave 4a).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notes_orphans implements named_templatable, renderable {
    /** @var int The vaeiste. */
    private int $candidateid;

    /** @var \stdClass[] The lines whose situation is gone, the earliest first. */
    private array $rows;

    /**
     * The annotations of the deleted situations.
     *
     * @param int $candidateid
     * @param \stdClass[] $rows The lines of note_manager::for_candidate() whose situation is gone, the earliest first.
     */
    public function __construct(int $candidateid, array $rows) {
        $this->candidateid = $candidateid;
        $this->rows = $rows;
    }

    /**
     * Whether there is something to draw (the old method gave '' when there was none).
     *
     * @return bool
     */
    public function drawn(): bool {
        return $this->rows !== [];
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/notes_orphans';
    }

    /**
     * The context of the template.
     *
     * @param renderer_base $output
     * @return array section: an object, or null when there is nothing to draw.
     */
    public function export_for_template(renderer_base $output): array {
        if (!$this->drawn()) {
            return ['section' => null];
        }
        $groups = [];
        foreach ($this->rows as $row) {
            $groups[(string) $row->targetuuid][] = $row;
        }
        $parts = [];
        foreach ($groups as $group) {
            $first = reset($group);
            $parts[] = [
                'title' => get_string('validpart:situation', 'tool_vae', (int) $first->slot) . ' - '
                    . get_string('notetargetgone', 'tool_vae'),
                // The author takes back their word here too: the list of the situations is where it is still read.
                'lineshtml' => $output->render(new note_lines(
                    $this->candidateid,
                    $group,
                    situation_screen::url($this->candidateid, (string) $first->fichecode)
                )),
            ];
        }

        return ['section' => ['heading' => get_string('notesheading', 'tool_vae'), 'groups' => $parts]];
    }
}
