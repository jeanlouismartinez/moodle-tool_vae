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

/**
 * What a declaration says of its carries to the record that replaces its own (T-30, lot 3; lot 7, MMR-520 ticket 4).
 *
 * Two sentences at most, never colour alone: where it was carried to, and where it comes from - with the reminder that the
 * carried one has to be attested again and that the old attestation stays where it is. Nothing when the declaration has no
 * carry. Was presenter::carry_notes().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class carry_notes implements named_templatable, renderable {
    /** @var \stdClass What carry_manager::notes() returns. */
    private \stdClass $notes;

    /**
     * The notes of one declaration.
     *
     * @param \stdClass $notes What carry_manager::notes() returns: carriedto and carriedfrom, each a row or null.
     */
    public function __construct(\stdClass $notes) {
        $this->notes = $notes;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/carry_notes';
    }

    /**
     * The context of the template: the sentences, as plain text.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $notes = [];
        foreach (['carriedto', 'carriedfrom'] as $direction) {
            if ($this->notes->$direction) {
                $notes[] = ['text' => get_string($direction, 'tool_vae', $this->describe($this->notes->$direction))];
            }
        }

        return ['notes' => $notes];
    }

    /**
     * The values of a sentence: the record, the day of the carry, and who decided the correspondence and when.
     *
     * @param \stdClass $row
     * @return \stdClass record, when, decision; plain text, escaped by the template with the sentence.
     */
    private function describe(\stdClass $row): \stdClass {
        $dateformat = get_string('strftimedate', 'langconfig');
        $decision = '';
        if ((int) $row->decidedat > 0) {
            // The space that joins the decision to the sentence is the code's, not the string's.
            $decision = ' ' . get_string('carrydecision', 'tool_vae', (object) [
                'who' => $row->decidername === '' ? get_string('carrywhoadmin', 'tool_vae') : $row->decidername,
                'when' => display_date::format((int) $row->decidedat, $dateformat),
            ]);
        }

        return (object) [
            'record' => $row->record,
            'when' => display_date::format((int) $row->carriedat, $dateformat),
            'decision' => $decision,
        ];
    }
}
