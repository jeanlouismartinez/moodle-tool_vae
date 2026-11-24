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
use tool_vae\local\piece_manager;
use tool_vae\local\presenter;

/**
 * The coverage of a dossier, one table per certification record (R3; lot 7, MMR-520 ticket 4).
 *
 * Plain counts and a date, nothing else: no percentage, no total, no colour that ranks one line above another. A competency
 * with nothing declared shows its zeros like any other line, with no mark drawing the eye to it - the vaeiste reads the
 * gap, nobody points at it. The competency cell is the row header, so a screen reader announces which competency a number
 * belongs to; the caption names the record for whoever navigates by table. A bloc line says so in words and adds up its
 * competencies (T-33); a competency under a bloc is set in.
 *
 * AHEAD OF THE SECTION, ONE BOX for the addresses where the certifiers publish their rule of validation (T-41), once the
 * site has opened them (presenter::coverage_refs()): set beside a count, a rule becomes a target (D-1). In no colour, never
 * on the line of a bloc; nothing is quoted. The plugin never opens an address; the click is the reader's, in a new tab,
 * with the address in full under the pointer (and the host as the network reads it when it is not plain ASCII). Was
 * presenter::coverage() and presenter::rule_refs().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coverage implements named_templatable, renderable {
    /** @var \stdClass[] What coverage::for_candidate() returns. */
    private array $fiches;

    /**
     * The coverage.
     *
     * @param \stdClass[] $fiches What coverage::for_candidate() returns.
     */
    public function __construct(array $fiches) {
        $this->fiches = $fiches;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/coverage';
    }

    /**
     * The context of the template: texts and addresses in clear; HTML only in the keys ...html (headings of the core, the
     * provenance line, the tables).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        if (!$this->fiches) {
            return ['show' => false, 'rulerefs' => false, 'headinghtml' => false, 'intro' => false, 'fiches' => []];
        }

        $fiches = [];
        foreach ($this->fiches as $fiche) {
            $title = $fiche->fichecode;
            if ($fiche->source) {
                $title .= ' — ' . get_string($fiche->source->fichetype === 'rs' ? 'typers' : 'typerncp', 'tool_vae');
            }
            $fiches[] = [
                // The heading of the core does not escape its text: escaped here, as before.
                'titlehtml' => $output->heading(s($title), 4),
                'provenancehtml' => $fiche->source ? $output->render(new provenance($fiche->source)) : false,
                'blocnote' => empty($fiche->hasblocs) ? false : get_string('coverageblocnote', 'tool_vae'),
                'tablehtml' => $output->render(new table($this->table($fiche))),
            ];
        }

        return [
            'show' => true,
            'rulerefs' => $this->rule_refs(presenter::coverage_refs($this->fiches)),
            'headinghtml' => $output->heading(get_string('coverage', 'tool_vae'), 3),
            'intro' => get_string('coverageintro', 'tool_vae'),
            'fiches' => $fiches,
        ];
    }

    /**
     * The table of one record.
     *
     * @param \stdClass $fiche
     * @return \html_table
     */
    private function table(\stdClass $fiche): \html_table {
        $dateformat = get_string('strftimedate', 'langconfig');
        $table = new \html_table();
        $table->caption = get_string('coveragecaption', 'tool_vae', $fiche->fichecode);
        $table->captionhide = true;
        $table->head = [
            get_string('coveragecompetency', 'tool_vae'),
            get_string('coveragedeclarations', 'tool_vae'),
            get_string('coverageattested', 'tool_vae'),
            get_string('coveragelastattested', 'tool_vae'),
        ];
        $table->attributes['class'] = 'generaltable tool-vae-coverage-table';

        foreach ($fiche->competencies as $row) {
            $declarations = $row->declarations;
            $attested = $row->attested;
            $last = $row->lastattested;
            $isbloc = !empty($row->isbloc);
            if ($isbloc) {
                $declarations = $row->blocdeclarations;
                $attested = $row->blocattested;
                $last = $row->bloclastattested;
            }

            $competency = new \html_table_cell(new coverage_label(
                (string) $row->idnumber,
                $row->shortname === null ? null : (string) $row->shortname,
                (bool) $row->gone,
                $isbloc
            ));
            $competency->header = true;
            $competency->scope = 'row';
            if (!empty($row->bloc)) {
                $competency->attributes['class'] = 'ps-4';
            }

            $table->data[] = [
                $competency,
                (string) $declarations,
                (string) $attested,
                $last === null ? '' : new cell_content([cell_content::text(display_date::format($last, $dateformat))]),
            ];
        }

        return $table;
    }

    /**
     * The box of the addresses of the rule, or false when no record has one.
     *
     * @param \stdClass[][] $refs The addresses by record code, as ref_manager::for_fiches() gives them.
     * @return array|false {label, lines, sentence}: each line {code, links}, each link {first, url, title, tip, checked}.
     */
    private function rule_refs(array $refs) {
        $dateformat = get_string('strftimedate', 'langconfig');
        $lines = [];
        foreach ($refs as $code => $list) {
            $links = [];
            foreach (array_values($list) as $rank => $ref) {
                // The address in full, and the host as the network reads it when it is not plain ASCII: a look-alike shows.
                $host = piece_manager::display_host($ref->url);
                $links[] = [
                    'first' => $rank === 0,
                    'url' => (string) $ref->url,
                    'title' => (string) $ref->title,
                    'tip' => strpos($ref->url, $host) === false ? $ref->url . ' (' . $host . ')' : (string) $ref->url,
                    // A day, stored at noon UTC and shown in UTC: the same day for a reader in Paris and one in Guadeloupe.
                    'checked' => $ref->checkedat === null ? false : [
                        'text' => get_string('refcheckedon', 'tool_vae', display_date::format($ref->checkedat, $dateformat, 'UTC')),
                    ],
                ];
            }
            $lines[] = ['code' => (string) $code, 'links' => $links];
        }
        if (!$lines) {
            return false;
        }

        return [
            'label' => get_string('refrulelabel', 'tool_vae'),
            'lines' => $lines,
            'sentence' => get_string('refsentence', 'tool_vae'),
        ];
    }
}
