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
 * The attestation history of a claim (lot 7, MMR-520 ticket 4).
 *
 * A withdrawn attestation still appears, saying only that it existed and when it was withdrawn. That sentence is what the
 * dossier is left with, and it is the point of holding the signature in this plugin rather than on the core record. The way
 * out (the vaeiste can ask a manager to attest again) is said only when no attestation stands, and once, after the last one
 * withdrawn: next to an attestation in force it would contradict the dossier. The PDF and the copies say it the same way.
 * A signature given elsewhere says so: nobody on this site can vouch for it. Was presenter::attestations().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attestations implements named_templatable, renderable {
    /** @var \stdClass[] Rows of tool_vae_attest, oldest first. */
    private array $attestations;

    /**
     * The history.
     *
     * @param \stdClass[] $attestations Rows of tool_vae_attest, oldest first.
     */
    public function __construct(array $attestations) {
        $this->attestations = $attestations;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/attestations';
    }

    /**
     * The context of the template: each attestation withdrawn (a sentence) or signed (role, lines of the statement, dates).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        if (!$this->attestations) {
            return ['none' => get_string('noattestationyet', 'tool_vae'), 'hasitems' => false, 'items' => []];
        }

        $dateformat = get_string('strftimedate', 'langconfig');
        $standing = (bool) array_filter($this->attestations, fn($attestation) => $attestation->withdrawnat === null);
        $last = array_key_last($this->attestations);

        $items = [];
        foreach ($this->attestations as $key => $attestation) {
            if ($attestation->withdrawnat !== null) {
                $sentence = get_string('attestationwithdrawnon', 'tool_vae', (object) [
                    'date' => display_date::format((int) $attestation->withdrawnat, $dateformat),
                    'reason' => get_string('withdrawreason:' . $attestation->withdrawnreason, 'tool_vae'),
                ]);
                if (!$standing && $key === $last) {
                    $sentence .= ' ' . get_string('attestationwithdrawnagain', 'tool_vae');
                }
                $items[] = ['withdrawn' => ['text' => $sentence], 'signed' => false];
                continue;
            }

            $items[] = ['withdrawn' => false, 'signed' => [
                'role' => (string) $attestation->attestantrole,
                'lines' => self::lines((string) $attestation->statement),
                'on' => get_string('attestedon', 'tool_vae', display_date::format((int) $attestation->attestedat, $dateformat)),
                'imported' => empty($attestation->importedat) ? false : [
                    'text' => get_string(
                        'attestationimportedon',
                        'tool_vae',
                        display_date::format((int) $attestation->importedat, $dateformat)
                    ),
                ],
            ]];
        }

        return ['none' => false, 'hasitems' => true, 'items' => $items];
    }

    /**
     * The lines of a statement, as nl2br() broke them: a break before each end of line, the end of line kept after it.
     *
     * @param string $statement
     * @return array[] Each {br: whether a break comes first, text: the end of line that follows it, then the line}.
     */
    private static function lines(string $statement): array {
        // The ends of line nl2br() knows, in the order it reads them: a pair first, then a single one.
        $parts = preg_split('/(\r\n|\n\r|\n|\r)/', $statement, -1, PREG_SPLIT_DELIM_CAPTURE);
        $lines = [['br' => false, 'text' => (string) array_shift($parts)]];
        while ($parts) {
            $end = (string) array_shift($parts);
            $lines[] = ['br' => true, 'text' => $end . (string) array_shift($parts)];
        }

        return $lines;
    }
}
