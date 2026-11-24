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

namespace tool_vae\local;

/**
 * What the preview of an import of records from an export of France compétences says, in words (0.47.0; lot 7, MMR-520).
 *
 * THE WORDS, NOT THE HTML. Since lot 7 the preview and the result are drawn by tool_vae\output\fiche_preview and
 * tool_vae\output\fiche_results and their templates, which escape every text once. This class keeps what is read from the
 * plan and from the orchestrator of the import: the warnings, the line that says where the export comes from, the dates, the
 * provenance. Every text it returns is plain, NOT escaped: what comes from the export (a name, a number typed twice) is put
 * into its sentence as it is, and the template escapes the whole sentence.
 *
 * WHAT THE PREVIEW SAYS THAT THE FORM OF THE CSV FILE NEVER COULD. The title of each record as the source publishes it, whole: a
 * number that is wrong but exists imports another certification with no error, and the only protection is to read the title
 * before confirming. What the export holds for each number, what Moodle holds, and what the import will do, side by side.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fiche_screen {
    /**
     * The warnings of a plan: things worth a look that stop nothing.
     *
     * @param \stdClass $plan
     * @param int|null $now The time, for a test; else the clock of the core.
     * @return string[] Plain texts, not escaped.
     */
    public static function warnings(\stdClass $plan, ?int $now = null): array {
        $now ??= \core\di::get(\core\clock::class)->time();
        $warnings = [];

        $age = intdiv($now - (int) $plan->exportts, DAYSECS);
        if ($age >= fiche_import::STALE_DAYS) {
            // The export that was just fetched is the latest there is: the advice to fetch it would be circular.
            $warnings[] = get_string(
                $plan->origin === fiche_import::ORIGIN_FETCH ? 'fcimportwarnstalefetch' : 'fcimportwarnstale',
                'tool_vae',
                $age
            );
        }

        $first = fiche_import::to_timestamp($plan->innerdates->blocs);
        $second = fiche_import::to_timestamp($plan->innerdates->standard);
        if (abs((int) $plan->exportts - $first) > fiche_import::GAP_DAYS * DAYSECS) {
            $warnings[] = get_string('fcimportwarngap', 'tool_vae', (object) [
                'export' => self::day((int) $plan->exportts),
                'inner' => self::day($first),
            ]);
        }
        if ($first !== $second) {
            $warnings[] = get_string('fcimportwarndates', 'tool_vae', (object) [
                'first' => self::day($first),
                'second' => self::day($second),
            ]);
        }
        foreach ($plan->repeated as $number) {
            $warnings[] = get_string('fcimportwarnrepeated', 'tool_vae', (string) $number);
        }

        return $warnings;
    }

    /**
     * The line that says where the export comes from.
     *
     * @param \stdClass $plan
     * @return string Plain text, not escaped.
     */
    public static function source(\stdClass $plan): string {
        return get_string(
            $plan->origin === fiche_import::ORIGIN_FETCH ? 'fcimportsourcefetch' : 'fcimportsourcezip',
            'tool_vae',
            (object) ['name' => (string) $plan->originname, 'size' => display_size((int) $plan->bytes)]
        );
    }

    /**
     * The line of the dates: the day of the export, then the day its files carry.
     *
     * @param \stdClass $plan
     * @return string Plain text.
     */
    public static function dates(\stdClass $plan): string {
        return get_string('fcimportexportdate', 'tool_vae', self::day((int) $plan->exportts))
            . ' ' . get_string('fcimportdatenote', 'tool_vae', self::day(fiche_import::to_timestamp($plan->innerdates->blocs)));
    }

    /**
     * What the sentence of the provenance names: the page of the dataset, the attribution, the licence, the day of the export.
     *
     * @param \stdClass $plan
     * @return array{url: string, attribution: string, licence: string, date: string} Plain texts.
     */
    public static function provenance(\stdClass $plan): array {
        return [
            'url' => fiche_import::SOURCE_URL,
            'attribution' => fiche_import::ATTRIBUTION,
            'licence' => fiche_import::LICENCE,
            'date' => self::day((int) $plan->exportts),
        ];
    }

    /**
     * A day, as a person reads it.
     *
     * @param int $timestamp
     * @return string
     */
    public static function day(int $timestamp): string {
        return display_date::format($timestamp, get_string('strftimedaydate', 'langconfig'));
    }
}
