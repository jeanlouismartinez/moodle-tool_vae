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
use tool_vae\local\selfpos_screen;

/**
 * The tree of a record (lot 1a): every node with its level name, the vaeiste's current level and its date, and the way to
 * its page.
 *
 * NO TOTAL, NO RATE, NO COLOUR OF RANKING. The invitation to say where one stands is for the one who writes; a reader gets
 * the tree alone. Was selfpos_screen::tree() (lot 7, MMR-520 ticket 4).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class selfpos_record_tree implements named_templatable, renderable {
    /** @var int The vaeiste. */
    private int $candidateid;

    /** @var string The record. */
    private string $fichecode;

    /** @var \stdClass As selfpos_tree::for_record() gives it. */
    private \stdClass $tree;

    /** @var array Rank => the line that stands, as selfpos_manager::current() gives it. */
    private array $current;

    /** @var bool Whether the reader is the vaeiste and may position themselves. */
    private bool $canwrite;

    /**
     * The tree of a record.
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param \stdClass $tree As selfpos_tree::for_record() gives it.
     * @param array $current Rank => the line that stands, as selfpos_manager::current() gives it.
     * @param bool $canwrite Whether the reader is the vaeiste and may position themselves.
     */
    public function __construct(int $candidateid, string $fichecode, \stdClass $tree, array $current, bool $canwrite) {
        $this->candidateid = $candidateid;
        $this->fichecode = $fichecode;
        $this->tree = $tree;
        $this->current = $current;
        $this->canwrite = $canwrite;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/selfpos_record_tree';
    }

    /**
     * The context of the template: the sentences, the invitation for the one who writes, and each node.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $nodes = [];
        foreach ($this->tree->nodes as $node) {
            $positionable = null;
            if ($node->positionable) {
                $positionable = [
                    'words' => selfpos_screen::level_text($this->current[(string) $node->rank] ?? null),
                    'url' => selfpos_screen::url($this->candidateid, $this->fichecode, (string) $node->rank)->out(false),
                    'link' => get_string($this->canwrite ? 'selfposnodelink' : 'selfposreadlink', 'tool_vae'),
                ];
            }
            $nodes[] = [
                'style' => 'margin-left: ' . (((int) $node->level - 1) * 1.5) . 'rem',
                'level' => (string) $node->level,
                'levelname' => $node->levelname,
                'label' => $node->label,
                'explain' => $node->positionable ? null : ['text' => get_string('selfposexplainnotpositionable', 'tool_vae')],
                'positionable' => $positionable,
            ];
        }

        return [
            'disclaimerhtml' => $output->render(new selfpos_disclaimer($this->candidateid)),
            'intro' => $this->canwrite ? ['text' => get_string('selfposintro', 'tool_vae')] : null,
            'nodes' => $nodes,
        ];
    }
}
