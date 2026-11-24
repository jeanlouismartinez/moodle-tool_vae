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
use tool_vae\local\presenter;

/**
 * One claim, drawn for reading (lot 7, MMR-520 ticket 4).
 *
 * A definition list (the record, the competency and its bloc, the version of the record, the period, the setting and its
 * detail), then the narrative under its heading. Sober on purpose: no colour carrying meaning on its own, no layout that
 * breaks at 320 px. ESCAPING IS THE POINT: the narrative and the detail are free text written by a user, drawn in the
 * session of a manager who can read other people's dossiers. The narrative goes through format_text() with the format the
 * vaeiste chose (plain text or Markdown, HTML being excluded at the column); every other value is given plain and escaped
 * by the template. The lines are read by presenter::declaration_rows(). Was presenter::declaration().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class declaration implements named_templatable, renderable {
    /** @var \stdClass A tool_vae_evidence row. */
    private \stdClass $evidence;

    /** @var \context The vaeiste's user context, for format_text(). */
    private \context $context;

    /**
     * One claim.
     *
     * @param \stdClass $evidence A tool_vae_evidence row.
     * @param \context $context The vaeiste's user context, for format_text().
     */
    public function __construct(\stdClass $evidence, \context $context) {
        $this->evidence = $evidence;
        $this->context = $context;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/declaration';
    }

    /**
     * The context of the template: the lines as plain text, the narrative as HTML of format_text().
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $rows = [];
        foreach (presenter::declaration_rows($this->evidence) as $label => $value) {
            $rows[] = ['label' => (string) $label, 'value' => (string) $value];
        }

        return [
            'rows' => $rows,
            'situation' => get_string('situation', 'tool_vae'),
            'situationhtml' => format_text(
                $this->evidence->situation,
                (int) $this->evidence->situationformat,
                ['context' => $this->context, 'para' => true]
            ),
        ];
    }
}
