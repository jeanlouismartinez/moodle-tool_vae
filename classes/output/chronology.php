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
use tool_vae\local\display_date;
use tool_vae\local\presenter;

/**
 * The chronology of a declaration, written out (T-40; lot 7, MMR-520 ticket 4).
 *
 * ACTS AND DATES ONLY, in order of date (presenter::chronology()): no name, no text. A fold, one line per act. Was
 * presenter::chronology_html().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class chronology implements named_templatable, renderable {
    /** @var \stdClass A row of tool_vae_evidence. */
    private \stdClass $evidence;

    /** @var \stdClass[] Rows of tool_vae_attest. */
    private array $attestations;

    /** @var \stdClass[] Rows of tool_vae_request. */
    private array $requests;

    /** @var array[] Acts of a carry, as for presenter::chronology(). */
    private array $carries;

    /**
     * The chronology of one declaration.
     *
     * @param \stdClass $evidence A row of tool_vae_evidence.
     * @param \stdClass[] $attestations Rows of tool_vae_attest.
     * @param \stdClass[] $requests Rows of tool_vae_request.
     * @param array[] $carries Acts of a carry, as for presenter::chronology().
     */
    public function __construct(\stdClass $evidence, array $attestations, array $requests, array $carries = []) {
        $this->evidence = $evidence;
        $this->attestations = $attestations;
        $this->requests = $requests;
        $this->carries = $carries;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/chronology';
    }

    /**
     * The context of the template: the heading of the fold and each act with its day.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $acts = [];
        foreach (presenter::chronology($this->evidence, $this->attestations, $this->requests, $this->carries) as $act) {
            $acts[] = [
                'date' => display_date::format($act['time'], get_string('strftimedate', 'langconfig')),
                'act' => get_string('chrono:' . $act['act'], 'tool_vae'),
            ];
        }

        return ['heading' => get_string('chronologyheading', 'tool_vae'), 'acts' => $acts];
    }
}
