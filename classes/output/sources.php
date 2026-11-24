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
 * The certification records this site holds, and where each one comes from (lot 7, MMR-520 ticket 4).
 *
 * A heading of the core, then the table: the code with its provenance line (an obligation of the Licence Ouverte 2.0, not a
 * courtesy), the name of the certification then its register (with six records of the same register the administrator had
 * nothing but the code to tell one from another), the count of competencies with the divergences an import recorded, the
 * count of declarations (what a re-import will and will not touch), the day of the last import and its outcome. What is
 * counted is read by presenter::source_facts(); this class words it for the template tool_vae/sources. Was
 * presenter::sources().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sources implements named_templatable, renderable {
    /** @var \stdClass[] Rows of tool_vae_source. */
    private array $sources;

    /**
     * The table of the records.
     *
     * @param \stdClass[] $sources Rows of tool_vae_source.
     */
    public function __construct(array $sources) {
        $this->sources = $sources;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/sources';
    }

    /**
     * The context of the template: the heading of the core and the table, as HTML of the core and of tool_vae/table.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'headinghtml' => $output->heading(get_string('sourcetable', 'tool_vae'), 3),
            'tablehtml' => $output->render(new table($this->table())),
        ];
    }

    /**
     * The table: code, name, register, competencies, declarations, last import.
     *
     * @return \html_table
     */
    private function table(): \html_table {
        $table = new \html_table();
        $table->head = [
            get_string('sourcecode', 'tool_vae'),
            get_string('importname', 'tool_vae'),
            get_string('importfichetype', 'tool_vae'),
            get_string('sourcecompetencies', 'tool_vae'),
            get_string('sourcedeclarations', 'tool_vae'),
            get_string('sourcelastimport', 'tool_vae'),
        ];
        $table->attributes['class'] = 'generaltable tool-vae-sources';

        $dateformat = get_string('strftimedate', 'langconfig');
        foreach (presenter::source_facts($this->sources) as $fact) {
            $source = $fact->source;
            $status = $source->lastimportstatus === 'ok'
                ? get_string('sourcestatusok', 'tool_vae')
                : get_string('sourcestatuserror', 'tool_vae');

            $competencies = [cell_content::text((string) $fact->competencies)];
            if ($fact->gone || $fact->diverged) {
                $competencies[] = cell_content::div(
                    'small tool-vae-divergence',
                    get_string('sourcedivergences', 'tool_vae', (object) ['gone' => $fact->gone, 'diverged' => $fact->diverged])
                );
            }

            $table->data[] = [
                new source_code((string) $source->fichecode, new provenance($source)),
                new cell_content([cell_content::text($fact->name)]),
                new cell_content([cell_content::text($source->fichetype === 'rs'
                    ? get_string('typers', 'tool_vae')
                    : get_string('typerncp', 'tool_vae'))]),
                new cell_content($competencies),
                (int) $fact->claims,
                new cell_content([cell_content::text(
                    display_date::format((int) $source->lastimport, $dateformat) . ' — ' . $status
                )]),
            ];
        }

        return $table;
    }
}
