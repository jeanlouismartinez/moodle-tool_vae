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
use tool_vae\local\fiche_messages;
use tool_vae\local\fiche_refusal;
use tool_vae\local\fiche_screen;

/**
 * The preview of an import of records from an export of France compétences (0.47.0; lot 7, MMR-520 ticket 4).
 *
 * Where the export comes from, its dates, the warnings, then a table with a line for each number asked for: what the export
 * holds, what Moodle holds, what the import will do; then the provenance. Before lot 7, fiche_screen::preview() wrote it by
 * hand; the words are still read in tool_vae\local\fiche_screen, and every text that comes from the export (titles, labels,
 * state, end of registration, names) is given in clear to the templates, which escape it once. The only HTML that is not
 * drawn by a template is the sentence of a refusal, made safe by fiche_messages::html() (its parameters are escaped there).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fiche_preview implements named_templatable, renderable {
    /** @var \stdClass The plan, as the orchestrator of the import builds it. */
    private \stdClass $plan;

    /** @var int The time of the reading. */
    private int $now;

    /**
     * The preview of a plan.
     *
     * @param \stdClass $plan As the orchestrator of the import builds it.
     * @param int|null $now The time, for a test; else the clock of the core.
     */
    public function __construct(\stdClass $plan, ?int $now = null) {
        $this->plan = $plan;
        $this->now = $now ?? \core\di::get(\core\clock::class)->time();
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/fiche_preview';
    }

    /**
     * The context of the template: the texts in clear; the table and the sentence of the provenance as HTML.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $plan = $this->plan;
        $exportts = (int) $plan->exportts;

        $ready = 0;
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-fcimport';
        $table->caption = get_string('fcimportcaption', 'tool_vae');
        $table->head = [
            get_string('fcimportcolfiche', 'tool_vae'),
            get_string('fcimportcolexport', 'tool_vae'),
            get_string('fcimportcolmoodle', 'tool_vae'),
            get_string('fcimportcolaction', 'tool_vae'),
        ];
        $anyheld = false;
        foreach ($plan->records as $record) {
            $table->data[] = $this->line($record, $exportts);
            $ready += $record->status === 'ready' ? 1 : 0;
            $anyheld = $anyheld || ($record->status === 'ready' && $record->state !== 'new');
        }

        $provenance = fiche_screen::provenance($plan);
        // The link is drawn by its own template; the attribution and the licence are escaped here, as before, since the
        // sentence of the language file that holds the link is placed as HTML.
        $link = $output->render(new cell_content([cell_content::link(new \moodle_url($provenance['url']), 'data.gouv.fr')]));

        return [
            'source' => fiche_screen::source($plan),
            'dates' => fiche_screen::dates($plan),
            'warnings' => array_map(fn(string $warning) => ['text' => $warning], fiche_screen::warnings($plan, $this->now)),
            'check' => get_string('fcimportcheck', 'tool_vae'),
            'tablehtml' => $output->render(new table($table)),
            'none' => $ready ? false : ['text' => get_string('fcimportnone', 'tool_vae')],
            'provenancehtml' => get_string('fcimportprovenance', 'tool_vae', (object) [
                'source' => $link,
                'attribution' => s($provenance['attribution']),
                'licence' => s($provenance['licence']),
                'date' => $provenance['date'],
            ]),
            'gonenote' => $anyheld ? ['text' => get_string('fcimportgonenote', 'tool_vae')] : false,
            'visible' => get_string('fcimportvisible', 'tool_vae'),
        ];
    }

    /**
     * The line of a record in the table.
     *
     * @param \stdClass $record
     * @param int $exportts The day of the export, which a refusal may name.
     * @return array The cells.
     */
    private function line(\stdClass $record, int $exportts): array {
        // The first cell is the header of its row: a reader of a screen reads "RNCP36832, ..." before each of the others.
        $title = $record->title === '' ? null : trim((string) preg_replace('/\s+/u', ' ', $record->title));
        $first = new \html_table_cell(fiche_cell::record((string) $record->number, $title));
        $first->header = true;
        $first->scope = 'row';

        if ($record->status !== 'ready') {
            // The sentence is made safe by fiche_messages::html; the plan keeps the reason and the parameters, not the text.
            $why = fiche_messages::html(fiche_refusal::from_array((array) $record->refusal), $exportts);

            // One cell for the three others: a refusal has nothing to say about the export, Moodle or the action, only why.
            $reason = new \html_table_cell(fiche_cell::lines(null, [
                fiche_cell::html('', $why),
                fiche_cell::line('fw-bold', get_string('fcimportrefusedfoot', 'tool_vae')),
            ]));
            $reason->colspan = 3;

            return [$first, $reason];
        }

        return [
            $first,
            $this->export_cell($record),
            $this->moodle_cell($record),
            $this->action_cell($record, $exportts),
        ];
    }

    /**
     * What the export holds for a record: its blocs (folded away), its state, the end of its registration, what was shortened.
     *
     * @param \stdClass $record
     * @return fiche_cell
     */
    private function export_cell(\stdClass $record): fiche_cell {
        $rows = [];
        foreach ($record->rows as $row) {
            // The published label, whole: a bloc whose title was shortened carries it in the description.
            $label = (string) $row->description !== '' ? $row->description : $row->intitule;
            $rows[] = ['rang' => (string) $row->rang, 'label' => (string) $label];
        }
        $blocs = ['summary' => get_string('fcimportblocs', 'tool_vae', (int) $record->blocs), 'rows' => $rows];

        $lines = [];
        if ($record->active !== null && $record->active !== '') {
            $state = $record->active === 'ACTIVE'
                ? get_string('fcimportstateactive', 'tool_vae')
                : ($record->active === 'INACTIVE'
                    ? get_string('fcimportstateinactive', 'tool_vae')
                    : get_string('fcimportstateother', 'tool_vae', (string) $record->active));
            $lines[] = fiche_cell::line('small', $state);
        }
        if ($record->enddate !== null && $record->enddate !== '') {
            $past = preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $record->enddate, $parts)
                && checkdate((int) $parts[2], (int) $parts[1], (int) $parts[3])
                // The whole of the last day counts: a registration that ends today has not ended yet.
                && make_timestamp((int) $parts[3], (int) $parts[2], (int) $parts[1], 23, 59, 59, 99) < $this->now;
            $lines[] = fiche_cell::line(
                'small',
                get_string($past ? 'fcimportendregpast' : 'fcimportendreg', 'tool_vae', (string) $record->enddate)
            );
        }

        if ($record->long) {
            $lines[] = fiche_cell::line('small', get_string('fcimportnotelong', 'tool_vae', (object) [
                'count' => count($record->long), 'ranks' => implode(', ', $record->long),
            ]));
        }
        if ($record->markup) {
            $lines[] = fiche_cell::line('small', get_string('fcimportnotemarkup', 'tool_vae', (object) [
                'count' => count($record->markup), 'ranks' => implode(', ', $record->markup),
            ]));
        }
        // Said where the framework is created: the name of one that Moodle already holds is never changed by an import.
        if ($record->name !== $record->title && in_array($record->state, ['new', 'rebuild'], true)) {
            $lines[] = fiche_cell::line('small', get_string('fcimportnotetitle', 'tool_vae'));
        }

        return fiche_cell::lines($blocs, $lines);
    }

    /**
     * What Moodle holds for a record.
     *
     * @param \stdClass $record
     * @return fiche_cell
     */
    private function moodle_cell(\stdClass $record): fiche_cell {
        switch ($record->state) {
            case 'held':
                $held = fiche_screen::day((int) $record->heldts);
                $lines = [fiche_cell::line('', get_string('fcimportmoodleheld', 'tool_vae', $held))];
                if ((int) $record->declared > 0) {
                    $lines[] = fiche_cell::line('small', get_string('fcimportdeclared', 'tool_vae', (int) $record->declared));
                }

                return fiche_cell::lines(null, $lines);
            case 'rebuild':
                return fiche_cell::lines(null, [fiche_cell::line('', get_string('fcimportmoodlerebuild', 'tool_vae'))]);
            case 'adopted':
                return fiche_cell::lines(null, [fiche_cell::line('', get_string('fcimportmoodleadopt', 'tool_vae'))]);
            default:
                return fiche_cell::lines(null, [fiche_cell::line('', get_string('fcimportmoodlenew', 'tool_vae'))]);
        }
    }

    /**
     * What the import will do for a record.
     *
     * @param \stdClass $record
     * @param int $exportts The day of the export.
     * @return fiche_cell
     */
    private function action_cell(\stdClass $record, int $exportts): fiche_cell {
        // Older than what the site holds: said, and left to a gesture - the plugin informs, it does not forbid. What it would
        // change is not announced: nothing is changed unless that gesture is made.
        if (!empty($record->older)) {
            return fiche_cell::lines(null, [
                fiche_cell::line('', get_string('fcimportolderskip', 'tool_vae')),
                fiche_cell::line('small', get_string('fcimportolder', 'tool_vae', (object) [
                    'held' => fiche_screen::day((int) $record->heldts),
                    'export' => fiche_screen::day($exportts),
                ])),
            ]);
        }

        $lines = [fiche_cell::line('', get_string('fcimportready', 'tool_vae'))];

        $forecast = $record->forecast;
        if ($forecast !== null) {
            if (in_array($record->state, ['new', 'rebuild'], true)) {
                $lines[] = fiche_cell::line('small', get_string('fcimportdonenew', 'tool_vae', (int) $forecast->created));
            } else if (!$forecast->created && !$forecast->updated && !$forecast->gone && !$forecast->diverged) {
                $lines[] = fiche_cell::line('small', get_string('fcimportdonesame', 'tool_vae'));
            } else {
                $lines[] = fiche_cell::line('small', get_string('fcimportdoneheld', 'tool_vae', (object) [
                    'created' => (int) $forecast->created, 'updated' => (int) $forecast->updated,
                    'gone' => (int) $forecast->gone, 'diverged' => (int) $forecast->diverged,
                ]));
            }
        }

        return fiche_cell::lines(null, $lines);
    }
}
