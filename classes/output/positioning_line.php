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
use tool_vae\local\positioning_screen;

/**
 * One line of a positioning of the structure (T-90, lot 3): its status, who and when, and its reason.
 *
 * An evaluation of a competency or a certification of a bloc, never a validation. The reason and the reason of an alert are
 * free text, drawn by format_text() as plain text, as before. Was positioning_screen::line() (lot 7, MMR-520 ticket 4).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class positioning_line implements named_templatable, renderable {
    /** @var \stdClass A row of tool_vae_eval or tool_vae_certif. */
    private \stdClass $row;

    /** @var \context The context of the candidate. */
    private \context $context;

    /** @var string eval or certif. */
    private string $kind;

    /**
     * The line.
     *
     * @param \stdClass $row A row of tool_vae_eval or tool_vae_certif.
     * @param \context $context The context of the candidate.
     * @param string $kind eval or certif.
     */
    public function __construct(\stdClass $row, \context $context, string $kind) {
        $this->row = $row;
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
        return 'tool_vae/positioning_line';
    }

    /**
     * The context of the template: the words in plain text, the reasons drawn by format_text().
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $row = $this->row;
        $status = get_string($this->kind . 'status' . $row->status, 'tool_vae');
        if ($this->kind === 'certif' && !empty($row->decisiondate)) {
            $status .= ' (' . get_string('positioningcertifiedon', 'tool_vae', $row->decisiondate) . ')';
        }
        if ($this->kind === 'certif' && !empty($row->validuntil)) {
            $status .= ' - ' . get_string('positioninguntil', 'tool_vae', $row->validuntil);
        }
        $options = ['context' => $this->context, 'filter' => false];
        $alert = null;
        if (!empty($row->alertreason)) {
            $alert = [
                'label' => get_string('positioningalertreasonlabel', 'tool_vae'),
                'alerthtml' => format_text($row->alertreason, FORMAT_PLAIN, $options),
            ];
        }

        return [
            'status' => $status,
            'signature' => positioning_screen::signature($row),
            'reasonhtml' => format_text($row->reason, FORMAT_PLAIN, $options),
            'alert' => $alert,
        ];
    }
}
