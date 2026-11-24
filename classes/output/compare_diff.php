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
use tool_vae\local\text_diff;

/**
 * The block of the comparison of two versions of a section: what was added underlined, what was removed struck through
 * (lot 7, MMR-520 ticket 4, wave 4c).
 *
 * Was text_diff::html(), whose HTML compare_page placed in its block; the block is drawn here with it. The reading of the
 * operations stays in tool_vae\local\text_diff (diff(), counts()). NEVER COLOUR ALONE: each run carries its own words for a
 * screen reader - "added:" and "removed:" - and a mark a sighted reader sees without any colour. The block keeps white
 * space as written (pre-wrap): the template writes nothing between its tags and the runs, and the block is drawn whole,
 * since the renderer of the core trims what a template renders (a text that begins or ends with a space would lose it).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class compare_diff implements named_templatable, renderable {
    /** @var array[] As text_diff::diff() returns them. */
    private array $ops;

    /**
     * The comparison.
     *
     * @param array[] $ops As text_diff::diff() returns them.
     */
    public function __construct(array $ops) {
        $this->ops = $ops;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/compare_diff';
    }

    /**
     * The context of the template: each run, its kind and its text, plain; the words for a screen reader.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $runs = [];
        foreach ($this->ops as $op) {
            $equal = $op['op'] === text_diff::EQUAL;
            $insert = !$equal && $op['op'] === text_diff::INSERT;
            $runs[] = ['equal' => $equal, 'insert' => $insert, 'remove' => !$equal && !$insert, 'text' => (string) $op['text']];
        }

        return [
            'added' => get_string('diffadded', 'tool_vae'),
            'removed' => get_string('diffremoved', 'tool_vae'),
            'runs' => $runs,
        ];
    }
}
