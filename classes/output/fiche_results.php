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

/**
 * The result of the confirmation of an import of records: what became of each record (0.47.0; lot 7, MMR-520 ticket 4).
 *
 * One item per record, then, when one was imported, the way to say that one replaces another. Before lot 7,
 * fiche_screen::results() wrote it by hand. Each sentence of the language file holds what the import said of the record
 * ($result->html: HTML that is already safe, written by the orchestrator of the import), so it is placed as HTML, and the
 * number of the record is escaped here, as before.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fiche_results implements named_templatable, renderable {
    /** @var \stdClass A plan with its results. */
    private \stdClass $plan;

    /**
     * The result of a plan.
     *
     * @param \stdClass $plan A plan with its results.
     */
    public function __construct(\stdClass $plan) {
        $this->plan = $plan;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/fiche_results';
    }

    /**
     * The context of the template: one sentence of HTML per record, and the sentence of the replacement or false.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $items = [];
        $imported = false;
        foreach ($this->plan->results as $result) {
            // The sentence is HTML (what the import said is already safe): the number is escaped as it enters it.
            $code = s($result->number);
            switch ($result->status) {
                case 'imported':
                    $imported = true;
                    $text = get_string('fcimportresultline', 'tool_vae', (object) ['code' => $code, 'summary' => $result->html]);
                    break;
                case 'failed':
                    $text = get_string('fcimportresultfailed', 'tool_vae', (object) ['code' => $code, 'reason' => $result->html]);
                    break;
                case 'older':
                    $text = get_string('fcimportresultolder', 'tool_vae', $code);
                    break;
                case 'refused':
                    $text = get_string('fcimportresultrefused', 'tool_vae', (object) ['code' => $code, 'reason' => $result->html]);
                    break;
                default:
                    $text = get_string('fcimportresultnotrun', 'tool_vae', $code);
                    break;
            }
            $items[] = ['texthtml' => $text];
        }

        $next = false;
        if ($imported) {
            // The link is drawn by its own template, and given to the sentence of the language file that holds it.
            $link = $output->render(new cell_content([cell_content::link(
                new \moodle_url('/admin/tool/vae/supersession.php'),
                get_string('supersessionheading', 'tool_vae')
            )]));
            $next = get_string('fcimportnextreplace', 'tool_vae', $link);
        }

        return ['items' => $items, 'nexthtml' => $next];
    }
}
