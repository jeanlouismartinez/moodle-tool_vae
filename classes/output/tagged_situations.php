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
use tool_vae\local\whereami_panel;

/**
 * The situations tagged on a bloc of the preparation or on a node under it, for the part "lier" (lot 1b): each with its title,
 * its period and the way to it; or the sentence that says none is.
 *
 * Was whereami_panel::tagged_on_bloc() (lot 7, MMR-520 ticket 4): the same parameters. Nothing when the reader may not read the
 * situations, the competencies are off or the record is not imported: drawn() says so, for the page that holds it.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tagged_situations implements named_templatable, renderable {
    /** @var int The vaeiste. */
    private int $candidateid;

    /** @var string The record. */
    private string $fichecode;

    /** @var string The rank of the bloc, in lower case; '' for the whole record. */
    private string $blocrank;

    /**
     * The situations of one bloc.
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param string $blocrank The rank of the bloc as validation_catalog::parse() gives it (lower case); '' for the record as
     *        a whole, whose every node counts.
     */
    public function __construct(int $candidateid, string $fichecode, string $blocrank) {
        $this->candidateid = $candidateid;
        $this->fichecode = $fichecode;
        $this->blocrank = $blocrank;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/tagged_situations';
    }

    /**
     * Whether the piece draws anything for the current reader.
     *
     * @return bool
     */
    public function drawn(): bool {
        return whereami_panel::tagged($this->candidateid, $this->fichecode, $this->blocrank) !== null;
    }

    /**
     * The context of the template: the heading, then the sentence "none" or each situation with its period.
     *
     * @param renderer_base $output
     * @return array list: null when nothing is drawn.
     */
    public function export_for_template(renderer_base $output): array {
        $situations = whereami_panel::tagged($this->candidateid, $this->fichecode, $this->blocrank);
        if ($situations === null) {
            return ['list' => null];
        }
        $format = get_string('strftimedate', 'langconfig');
        $items = [];
        foreach ($situations as $slot => $situation) {
            $period = array_filter([
                $situation->periodstart === null ? '' : display_date::format((int) $situation->periodstart, $format),
                $situation->periodend === null ? '' : display_date::format((int) $situation->periodend, $format),
            ], fn(string $date) => $date !== '');
            $items[] = [
                'url' => (new \moodle_url('/admin/tool/vae/situation.php', [
                    'userid' => $this->candidateid, 'fiche' => $this->fichecode, 'slot' => (int) $slot,
                ]))->out(false),
                'title' => (string) $situation->title,
                'period' => $period ? ['text' => implode(' - ', $period)] : null,
            ];
        }

        return [
            'list' => [
                'heading' => get_string('validlinksituations', 'tool_vae'),
                'none' => $items ? null : ['text' => get_string('validlinksituationsnone', 'tool_vae')],
                'items' => $items ? ['situations' => $items] : null,
            ],
        ];
    }
}
