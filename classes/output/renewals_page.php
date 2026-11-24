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
 * The page of the certifications whose date has passed (renewals.php), below its heading (T-90; lot 7, MMR-520 ticket 4).
 *
 * What a positioning is, what the list says, the day of the reading, then one sentence when no date has passed, or the
 * table, the earliest last day first: no total, no colour, no ranking of people. The reader's own line has no link: nobody
 * certifies themselves, and reading one's own dossier is another right. The page reads the rows; this class words them for
 * the template tool_vae/renewals_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class renewals_page implements named_templatable, renderable {
    /** @var string The day of the reading, YYYY-MM-DD. */
    private string $today;

    /** @var \stdClass[] The rows of renewal_report::rows(). */
    private array $rows;

    /** @var int The reader. */
    private int $readerid;

    /**
     * The page.
     *
     * @param string $today The day of the reading, YYYY-MM-DD.
     * @param \stdClass[] $rows renewal_report::rows(): the name fields of the vaeiste, candidateid, fichecode, ficherang,
     *     label, validuntil, days, held, reason.
     * @param int $readerid The reader, whose own line has no link.
     */
    public function __construct(string $today, array $rows, int $readerid) {
        $this->today = $today;
        $this->rows = $rows;
        $this->readerid = $readerid;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/renewals_page';
    }

    /**
     * The context of the template: plain texts, not escaped; HTML only in tablehtml.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'disclaimer' => get_string('positioningdisclaimer', 'tool_vae'),
            'intro' => get_string('renewalsintro', 'tool_vae'),
            'asof' => get_string('renewalsasof', 'tool_vae', $this->today),
            'none' => $this->rows ? false : get_string('renewalsnone', 'tool_vae'),
            'tablehtml' => $this->rows ? $output->render(new table($this->table())) : false,
        ];
    }

    /**
     * The table of the certifications to renew.
     *
     * @return \html_table
     */
    private function table(): \html_table {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-renewals';
        $table->head = [
            get_string('renewalscolcandidate', 'tool_vae'),
            get_string('renewalscolrecord', 'tool_vae'),
            get_string('renewalscolbloc', 'tool_vae'),
            get_string('renewalscoluntil', 'tool_vae'),
            get_string('renewalscoldays', 'tool_vae'),
            get_string('renewalscolaction', 'tool_vae'),
        ];
        foreach ($this->rows as $row) {
            $candidateid = (int) $row->candidateid;
            $dossier = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]);
            $page = positioning_screen::url('certify', $candidateid, (string) $row->fichecode, (string) $row->ficherang);
            // Nobody certifies themselves, and reading one's own dossier is another right: no link for one's own line.
            $own = $this->readerid === $candidateid;
            if ($own) {
                $action = cell_content::span('small text-muted', get_string('renewalsreadonly', 'tool_vae'));
            } else if (!$row->held) {
                // One sentence per cause: the record gone from the site, the sub-system off, no declaration left on it.
                $action = cell_content::span('small text-muted', get_string('renewals' . $row->reason, 'tool_vae'));
            } else {
                $action = cell_content::link($page, get_string('positioningrenewlink', 'tool_vae'));
            }
            $table->data[] = [
                new cell_content([$own ? cell_content::text(fullname($row)) : cell_content::link($dossier, fullname($row))]),
                new cell_content([cell_content::text((string) $row->fichecode)]),
                new cell_content([cell_content::text((string) $row->label)]),
                new cell_content([cell_content::text((string) $row->validuntil)]),
                (int) $row->days,
                new cell_content([$action]),
            ];
        }

        return $table;
    }
}
