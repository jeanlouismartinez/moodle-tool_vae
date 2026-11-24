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
 * The history of a rank (T-90, lot 3): the line that stands, then the earlier ones, the latest first.
 *
 * Each line is drawn by the piece positioning_line. Was positioning_screen::history() (lot 7, MMR-520 ticket 4).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class positioning_history implements named_templatable, renderable {
    /** @var \stdClass[] The rows, the earliest first. */
    private array $rows;

    /** @var \context The context of the candidate. */
    private \context $context;

    /** @var string eval or certif. */
    private string $kind;

    /**
     * The history.
     *
     * @param \stdClass[] $rows The rows, the earliest first.
     * @param \context $context The context of the candidate.
     * @param string $kind eval or certif.
     */
    public function __construct(array $rows, \context $context, string $kind) {
        $this->rows = $rows;
        $this->context = $context;
        $this->kind = $kind;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/positioning_history';
    }

    /**
     * The context of the template: the lines in the order they are read, a heading before the first and before the second.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        if (!$this->rows) {
            return ['none' => ['text' => get_string('positioningnoline', 'tool_vae')], 'lines' => []];
        }
        $rows = array_values($this->rows);
        $standing = array_pop($rows);
        $lines = [[
            'heading' => ['text' => get_string('positioningcurrent', 'tool_vae')],
            'linehtml' => $output->render(new positioning_line($standing, $this->context, $this->kind)),
        ]];
        foreach (array_reverse($rows) as $i => $row) {
            $lines[] = [
                'heading' => $i === 0 ? ['text' => get_string('positioningearlier', 'tool_vae')] : null,
                'linehtml' => $output->render(new positioning_line($row, $this->context, $this->kind)),
            ];
        }

        return ['none' => null, 'lines' => $lines];
    }
}
