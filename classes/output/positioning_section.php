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
use tool_vae\local\certification_manager;
use tool_vae\local\evaluation_manager;
use tool_vae\local\positioning_rules;
use tool_vae\local\positioning_screen;
use tool_vae\local\positioning_successor;
use tool_vae\local\selfpos_manager;
use tool_vae\local\selfpos_screen;

/**
 * The positionings of the structure in the dossier (T-90, lot 3): for each record, each bloc with its certification and its
 * competencies with their evaluation.
 *
 * THE EVALUATIONS OF THE COMPETENCIES AND THE CERTIFICATIONS OF THE BLOCS, NEVER VALIDATIONS. The section is built on the
 * tree of each record the candidate declared on, not on the declarations: a competency nobody has declared on still has a
 * place, and so has a bloc. It says in every case that a positioning is not a validation, which only the jury gives, outside
 * this platform. Free text goes through format_text() as plain text (positioning_line); labels and codes are escaped by the
 * template. Was positioning_screen::section() (lot 7, MMR-520 ticket 4).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class positioning_section implements named_templatable, renderable {
    /** @var int The candidate. */
    private int $candidateid;

    /** @var \context The context of the candidate. */
    private \context $context;

    /**
     * The section of a candidate.
     *
     * @param int $candidateid
     * @param \context $context The context of the candidate.
     */
    public function __construct(int $candidateid, \context $context) {
        $this->candidateid = $candidateid;
        $this->context = $context;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/positioning_section';
    }

    /**
     * The context of the template: the whole section (full), the archive of the structure alone (bare), or nothing.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $candidateid = $this->candidateid;
        $evaluations = new evaluation_manager();
        $certifications = new certification_manager();
        $records = [];
        foreach (positioning_screen::codes($candidateid) as $code) {
            $layout = positioning_rules::layout((string) $code);
            if (!$layout || !$layout->leaves) {
                continue;
            }
            $evaluated = $evaluations->current($candidateid, (string) $code);
            $certified = $certifications->current($candidateid, (string) $code);
            // What the record this one replaces positioned, read and never copied.
            $older = positioning_successor::readings($candidateid, (string) $code, $layout);
            // The vaeiste's own word on each node (lot 1a), put next to the structure's, without any gap. The reader has the
            // right to read the dossier: the caller asked it at the door.
            $selfpos = positioning_rules::latest(selfpos_manager::TABLE, $candidateid, (string) $code);
            $parts = [];
            foreach ($layout->blocs as $rank => $bloc) {
                $parts[] = $this->bloc($output, (string) $code, (string) $rank, $bloc, $evaluated, $certified, $older, $selfpos);
            }
            $alone = array_filter($layout->leaves, fn($leaf) => $leaf->bloc === null && $layout->hasblocs);
            if ($alone) {
                $parts[] = [
                    'bloc' => null,
                    'items' => $this->leaves($output, (string) $code, $alone, $evaluated, $older['eval'], $selfpos),
                ];
            }
            $records[] = ['code' => (string) $code, 'parts' => $parts];
        }
        if (!$records) {
            // The records are gone from the site, but the archive of the structure in place is still the vaeiste's to take.
            $structure = new positioning_structure($candidateid);

            return [
                'bare' => $structure->drawn() ? ['structurehtml' => $output->render($structure)] : null,
                'full' => null,
            ];
        }

        // The way to the self-positioning of the vaeiste at every level (lot 1a): the vaeiste goes to position themselves,
        // anyone else who reads the dossier goes to read it. The right to read was asked at the door of the dossier.
        $selfposlink = selfpos_manager::can_write($candidateid) ? 'selfposlink' : 'selfposreadall';

        return [
            'bare' => null,
            'full' => [
                'heading' => get_string('positioningheading', 'tool_vae'),
                'disclaimer' => get_string('positioningdisclaimer', 'tool_vae'),
                'selfposurl' => selfpos_screen::url($candidateid)->out(false),
                'selfposlink' => get_string($selfposlink, 'tool_vae'),
                'structurehtml' => $output->render(new positioning_structure($candidateid)),
                'records' => $records,
            ],
        ];
    }

    /**
     * One bloc: its certification, the lacks, and its competencies.
     *
     * @param renderer_base $output
     * @param string $code
     * @param string $rank
     * @param \stdClass $bloc label, leaves.
     * @param \stdClass[] $evaluated Rank => latest evaluation.
     * @param \stdClass[] $certified Rank => latest certification.
     * @param array $older What the record this one replaces positioned, as positioning_successor::readings() gives it.
     * @param \stdClass[] $selfpos Rank => the vaeiste's own line that stands (lot 1a).
     * @return array A part: bloc and items.
     */
    private function bloc(
        renderer_base $output,
        string $code,
        string $rank,
        \stdClass $bloc,
        array $evaluated,
        array $certified,
        array $older,
        array $selfpos
    ): array {
        $title = $rank === '' ? get_string('positioningwhole', 'tool_vae') : $bloc->label;
        $none = null;
        $linehtml = '';
        $expired = null;
        $stale = null;

        $line = $certified[$rank] ?? null;
        $isexpired = false;
        if ($line === null || $line->status === positioning_rules::WITHDRAWN) {
            $none = ['text' => get_string('positioningnone', 'tool_vae')];
        } else {
            $linehtml = $output->render(new positioning_line($line, $this->context, 'certif'));
            // Told on reading, on the day of the reader, and written nowhere: the same words for whoever reads the dossier.
            $isexpired = certification_manager::is_expired($line, certification_manager::today());
            if ($isexpired) {
                $days = certification_manager::days_overdue((string) $line->validuntil, certification_manager::today());
                $expired = [
                    'torenew' => get_string('positioningtorenew', 'tool_vae'),
                    'text' => get_string('positioningexpired', 'tool_vae', (object) [
                        'until' => $line->validuntil, 'days' => $days,
                    ]),
                ];
            }
            $newest = 0;
            foreach (array_keys($bloc->leaves) as $leafrank) {
                $newest = max($newest, (int) (($evaluated[(string) $leafrank]->timecreated ?? 0)));
            }
            if ($newest > (int) $line->timecreated) {
                $stale = ['text' => get_string('positioningstale', 'tool_vae')];
            }
        }
        $address = positioning_screen::url('certify', $this->candidateid, $code, $rank)->out(false);
        $links = [];
        if (certification_manager::can_write($this->candidateid)) {
            $links[] = [$address, get_string($isexpired ? 'positioningrenewlink' : 'positioningcertifylink', 'tool_vae')];
        }
        $links[] = [$address, get_string('positioninghistory', 'tool_vae')];

        return [
            'bloc' => [
                'heading' => get_string('positioningbloc', 'tool_vae') . ' - ' . $title,
                'none' => $none,
                'linehtml' => $linehtml,
                'expired' => $expired,
                'stale' => $stale,
                'older' => $this->older_lines($older['certif'][$rank] ?? [], 'certif'),
                'selfpos' => $this->selfpos_line($selfpos[$rank] ?? null),
                'links' => self::links($links),
            ],
            'items' => $this->leaves($output, $code, $bloc->leaves, $evaluated, $older['eval'], $selfpos),
        ];
    }

    /**
     * The competencies of a bloc (or standing alone), each with the line that stands.
     *
     * @param renderer_base $output
     * @param string $code
     * @param \stdClass[] $leaves Rank => label, gone.
     * @param \stdClass[] $evaluated Rank => latest evaluation.
     * @param array $older Rank => what the record this one replaces positioned on the equivalent competency.
     * @param \stdClass[] $selfpos Rank => the vaeiste's own line that stands (lot 1a).
     * @return array[] The items of the list.
     */
    private function leaves(
        renderer_base $output,
        string $code,
        array $leaves,
        array $evaluated,
        array $older,
        array $selfpos
    ): array {
        $items = [];
        $maywrite = evaluation_manager::can_write($this->candidateid);
        foreach ($leaves as $rank => $leaf) {
            $line = $evaluated[(string) $rank] ?? null;
            $none = null;
            $linehtml = '';
            if ($line === null || $line->status === positioning_rules::WITHDRAWN) {
                $none = ['text' => get_string('positioningnone', 'tool_vae')];
            } else {
                $linehtml = $output->render(new positioning_line($line, $this->context, 'eval'));
            }
            $address = positioning_screen::url('evaluate', $this->candidateid, $code, (string) $rank)->out(false);
            $links = [[$address, get_string('positioninghistory', 'tool_vae')]];
            if ($maywrite) {
                array_unshift($links, [$address, get_string('positioningevaluatelink', 'tool_vae')]);
            }
            $items[] = [
                'label' => $leaf->label,
                'gone' => $leaf->gone ? ['text' => get_string('coveragegone', 'tool_vae')] : null,
                'none' => $none,
                'linehtml' => $linehtml,
                'older' => $this->older_lines($older[(string) $rank] ?? [], 'eval'),
                'selfpos' => $this->selfpos_line($selfpos[(string) $rank] ?? null),
                'links' => self::links($links),
            ];
        }

        return $items;
    }

    /**
     * The vaeiste's own word on a node (lot 1a), as a line to read next to the structure's: no gap, no link to write from here.
     *
     * @param \stdClass|null $latest The vaeiste's line that stands on the node, if any.
     * @return array|null The line (text), or null when the vaeiste gave no level, or withdrew it.
     */
    private function selfpos_line(?\stdClass $latest): ?array {
        if (!selfpos_manager::standing($latest)) {
            return null;
        }

        return ['text' => get_string('selfposonevaluation', 'tool_vae', (object) [
            'label' => $latest->levellabel,
            'when' => userdate((int) $latest->timecreated, get_string('strftimedate', 'langconfig')),
        ])];
    }

    /**
     * What the record this one replaces positioned, as lines to read: no link, nothing to write on it from here.
     *
     * @param \stdClass[] $entries As positioning_successor::readings() gives them for one rank.
     * @param string $kind eval or certif.
     * @return array[] Each: text.
     */
    private function older_lines(array $entries, string $kind): array {
        $lines = [];
        foreach ($entries as $entry) {
            $when = $kind === 'certif'
                ? (string) $entry->decisiondate
                : userdate((int) $entry->time, get_string('strftimedate', 'langconfig'));
            $text = get_string('positioningonold', 'tool_vae', (object) [
                'code' => $entry->fromcode,
                'rank' => $entry->fromrank,
                'status' => get_string($kind . 'status' . $entry->status, 'tool_vae'),
                'when' => $when,
            ]);
            if ($kind === 'certif' && !empty($entry->validuntil)) {
                $text .= ' - ' . get_string('positioninguntil', 'tool_vae', $entry->validuntil);
            }
            $lines[] = ['text' => $text];
        }

        return $lines;
    }

    /**
     * The links of a bloc or a competency, each drawn once, separated as before.
     *
     * The old screen dropped a link drawn twice (array_unique() on the drawn links): the same address with the same words.
     *
     * @param array[] $links Each: [address, words].
     * @return array[] Each: sep (whether a separator comes before it), url, label.
     */
    private static function links(array $links): array {
        $drawn = [];
        foreach ($links as [$url, $label]) {
            $key = $url . "\n" . $label;
            if (!isset($drawn[$key])) {
                $drawn[$key] = ['sep' => $drawn !== [], 'url' => $url, 'label' => $label];
            }
        }

        return array_values($drawn);
    }
}
