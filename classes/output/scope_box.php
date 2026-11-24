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
 * What a vaeiste says is outside their activity (T-42; lot 7, MMR-520 ticket 4).
 *
 * A list of their own marks, and the sentence that says it exempts from nothing. A section of its own, apart from the
 * coverage: the figures of a record are not set beside it, and nothing is counted from it. The reader is the vaeiste, a
 * person who follows them, or someone who reads every dossier: the right to read the dossier is the caller's to check. The
 * vaeiste is given the way to change it. Nothing when there is nothing to show. Was presenter::scope_box().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope_box implements named_templatable, renderable {
    /** @var \stdClass[] As scope_manager::for_candidate() gives them, keyed by competency code. */
    private array $marks;

    /** @var string[] As scope_manager::labels() gives them. */
    private array $labels;

    /** @var bool Whether the reader is the vaeiste. */
    private bool $isown;

    /** @var bool Whether the site lets a vaeiste add a mark. */
    private bool $open;

    /**
     * The box of one dossier.
     *
     * @param \stdClass[] $marks As scope_manager::for_candidate() gives them.
     * @param string[] $labels As scope_manager::labels() gives them.
     * @param bool $isown Whether the reader is the vaeiste, who is given the way to change it.
     * @param bool $open Whether the site lets a vaeiste add a mark.
     */
    public function __construct(array $marks, array $labels, bool $isown, bool $open) {
        $this->marks = $marks;
        $this->labels = $labels;
        $this->isown = $isown;
        $this->open = $open;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/scope_box';
    }

    /**
     * The context of the template: texts and the address in clear; HTML only in headinghtml (a heading of the core).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        if (!$this->marks && !($this->isown && $this->open)) {
            return ['show' => false, 'headinghtml' => false, 'hasmarks' => false, 'marks' => [], 'sentence' => false,
                'none' => false, 'manage' => false];
        }

        $dateformat = get_string('strftimedate', 'langconfig');
        $marks = [];
        foreach ($this->marks as $key => $mark) {
            $label = $this->labels[$key] ?? '';
            $marks[] = [
                'key' => (string) $key,
                'label' => $label === '' ? false : ['text' => (string) $label],
                'said' => get_string('scopesaidonshort', 'tool_vae', display_date::format((int) $mark->timecreated, $dateformat)),
                'note' => $mark->note !== null && $mark->note !== '' ? ['text' => (string) $mark->note] : false,
            ];
        }

        return [
            'show' => true,
            'headinghtml' => $output->heading(get_string('scopedossierheading', 'tool_vae'), 3),
            'hasmarks' => $marks !== [],
            'marks' => $marks,
            'sentence' => get_string('scopesentence', 'tool_vae'),
            'none' => $marks ? false : get_string('scopenone', 'tool_vae'),
            'manage' => $this->isown ? [
                'url' => (new \moodle_url('/admin/tool/vae/scope.php'))->out(false),
                'label' => get_string('scopemanage', 'tool_vae'),
            ] : false,
        ];
    }
}
