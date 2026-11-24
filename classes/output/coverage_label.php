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

/**
 * The competency of a line of the coverage, in its header cell (T-33; lot 7, MMR-520 ticket 4).
 *
 * Its code and its short name, the word that it is gone from the record when it is; a bloc line in bold, followed after a
 * space by the word that it is a bloc - in words, never colour alone. Put in the header cell of a line of the table of the
 * coverage (tool_vae\output\coverage), drawn by the renderer of the plugin.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coverage_label implements named_templatable, renderable {
    /** @var string The code of the competency. */
    private string $code;

    /** @var string|null Its short name, or null when there is none. */
    private ?string $shortname;

    /** @var bool Whether it is gone from the record. */
    private bool $gone;

    /** @var bool Whether the line is a bloc. */
    private bool $isbloc;

    /**
     * The label of one line.
     *
     * @param string $code The code of the competency.
     * @param string|null $shortname Its short name; null when there is none (an empty one is still joined by a dash).
     * @param bool $gone Whether it is gone from the record.
     * @param bool $isbloc Whether the line is a bloc.
     */
    public function __construct(string $code, ?string $shortname, bool $gone, bool $isbloc) {
        $this->code = $code;
        $this->shortname = $shortname;
        $this->gone = $gone;
        $this->isbloc = $isbloc;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/coverage_label';
    }

    /**
     * The context of the template: plain texts, each optional one held in an object or false.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'code' => $this->code,
            'short' => $this->shortname === null ? false : ['text' => $this->shortname],
            'gone' => $this->gone ? ['text' => get_string('coveragegone', 'tool_vae')] : false,
            'isbloc' => $this->isbloc,
            'bloctag' => get_string('coveragebloctag', 'tool_vae'),
        ];
    }
}
