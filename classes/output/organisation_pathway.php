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
use tool_vae\local\organisation_screen;
use tool_vae\local\piece_manager;

/**
 * The tables of the declared records of a pathway, then the list of the records a plan names without a declaration, on the
 * tab "My pathway" of organisation.php (T-90; lot 7, MMR-520 ticket 4, wave 4b).
 *
 * COUNTS, NEVER A VERDICT: plain counts and dates, no total, no percentage, no colour that ranks one line above another. A
 * bloc line adds up the counts of its own line and of the competencies under it; its courses and plans are its own. The
 * column of pieces is drawn when the site opens the pieces or the person holds one. Draws nothing when there is neither a
 * declared record nor a record a plan names. Was organisation_screen::mine(), record() and planned().
 *
 * The title of a record is given to the heading of the core, which does not escape: it keeps the s() of the old code, as
 * does the code of the record in the caption of its table, which tool_vae\output\table places as it is.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class organisation_pathway implements named_templatable, renderable {
    /** @var \stdClass What organisation::for_candidate() gives. */
    private \stdClass $pathway;

    /**
     * The tables of a pathway.
     *
     * @param \stdClass $pathway What organisation::for_candidate() gives.
     */
    public function __construct(\stdClass $pathway) {
        $this->pathway = $pathway;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/organisation_pathway';
    }

    /**
     * The context of the template: each declared record, the records a plan names, the note on unreadable plans.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $pathway = $this->pathway;
        // The pieces are a function the site opens: closed, a column of zeros would say "no piece" of something the person
        // cannot do. The column stays as soon as the person holds a piece, whatever the setting is now.
        $showpieces = piece_manager::is_open() || organisation_screen::holds_pieces($pathway);

        $records = [];
        foreach ($pathway->declared as $record) {
            $records[] = $this->record($record, $pathway->plansreadable, $showpieces, $output);
        }

        return [
            'records' => $records,
            'planned' => $pathway->planned ? $this->planned($pathway->planned, $output) : null,
            // The records only a plan names cannot be listed when the plans cannot be read: said once, not by leaving them out.
            'unreadable' => $pathway->declared && !$pathway->plansreadable
                ? ['label' => get_string('orgplansunreadablenote', 'tool_vae')]
                : null,
        ];
    }

    /**
     * One declared record: its title, where its text comes from, and the table of its lines.
     *
     * @param \stdClass $record As organisation::for_candidate() gives it in declared.
     * @param bool $plansreadable Whether the reader may read plans at all.
     * @param bool $showpieces Whether the column of pieces is drawn.
     * @param renderer_base $output
     * @return array
     */
    private function record(\stdClass $record, bool $plansreadable, bool $showpieces, renderer_base $output): array {
        $dateformat = get_string('strftimedate', 'langconfig');
        $title = $record->fichecode;
        if ($record->name !== $record->fichecode) {
            $title .= ' — ' . $record->name;
        }
        if ($record->source) {
            $title .= ' — ' . get_string($record->source->fichetype === 'rs' ? 'typers' : 'typerncp', 'tool_vae');
        }

        $table = new \html_table();
        $table->caption = get_string('orgcaption', 'tool_vae', s($record->fichecode));
        $table->captionhide = true;
        $table->head = array_merge(
            [get_string('orgcolcompetency', 'tool_vae'), get_string('orgcoldeclarations', 'tool_vae')],
            $showpieces ? [get_string('orgcolpieces', 'tool_vae')] : [],
            [
                get_string('orgcolattested', 'tool_vae'),
                get_string('orgcollastact', 'tool_vae'),
                get_string('orgcolcourses', 'tool_vae'),
                get_string('orgcolactivities', 'tool_vae'),
                get_string('orgcolplans', 'tool_vae'),
            ]
        );
        $table->attributes['class'] = 'generaltable tool-vae-organisation';

        foreach ($record->competencies as $row) {
            $declarations = $row->declarations;
            $pieces = $row->pieces;
            $attested = $row->attested;
            $last = $row->lastact;
            if ($row->isbloc) {
                $declarations = $row->blocdeclarations;
                $pieces = $row->blocpieces;
                $attested = $row->blocattested;
                $last = $row->bloclastact;
            }

            $competency = new \html_table_cell(organisation_cell::competency($row));
            $competency->header = true;
            $competency->scope = 'row';
            if ($row->bloc !== null) {
                $competency->attributes['class'] = 'ps-4';
            }

            $table->data[] = array_merge(
                [$competency, (string) $declarations],
                $showpieces ? [(string) $pieces] : [],
                [
                    (string) $attested,
                    $last === null ? '' : new cell_content([cell_content::text(display_date::format($last, $dateformat))]),
                    organisation_cell::courses($row->courses, $row->coursesunreadable),
                    organisation_cell::activities($row->activities, $row->activitiesunreadable),
                    $plansreadable
                        ? organisation_cell::plans($row->plans)
                        : new cell_content([cell_content::text(get_string('orgplansunreadable', 'tool_vae'))]),
                ]
            );
        }

        return [
            'headinghtml' => $output->heading(s($title), 3),
            // The labels of the record are printed: the licence asks for the attribution wherever they are.
            'provenancehtml' => $record->source ? $output->render(new provenance($record->source)) : '',
            'blocnote' => $record->hasblocs ? ['label' => get_string('orgblocnote', 'tool_vae')] : null,
            'tablehtml' => $output->render(new table($table)),
        ];
    }

    /**
     * The records only a plan names: each with its courses and its plans, and no count.
     *
     * @param \stdClass[] $planned As organisation::for_candidate() gives them in planned.
     * @param renderer_base $output
     * @return array
     */
    private function planned(array $planned, renderer_base $output): array {
        $items = [];
        foreach ($planned as $record) {
            $items[] = [
                'code' => $record->fichecode,
                'name' => $record->name !== $record->fichecode ? ['label' => $record->name] : null,
                'courses' => get_string('orgcolcourses', 'tool_vae'),
                'courseshtml' => $output->render(organisation_cell::courses($record->courses, $record->coursesunreadable)),
                'activities' => get_string('orgcolactivities', 'tool_vae'),
                'activitieshtml' => $output->render(
                    organisation_cell::activities($record->activities, $record->activitiesunreadable)
                ),
                'plans' => get_string('orgcolplans', 'tool_vae'),
                'planshtml' => $output->render(organisation_cell::plans($record->plans)),
            ];
        }

        return [
            'headinghtml' => $output->heading(get_string('orgplannedheading', 'tool_vae'), 3),
            'intro' => get_string('orgplannedintro', 'tool_vae'),
            'items' => $items,
        ];
    }
}
