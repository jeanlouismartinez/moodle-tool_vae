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
use tool_vae\local\draft_manager;
use tool_vae\local\selfpos_manager;
use tool_vae\local\selfpos_screen;
use tool_vae\local\situation_manager;
use tool_vae\local\whereami_panel;

/**
 * The panel "where am I" of one record (lot 1b, task 7; spec 1a § 6.6): the tree of the record in reading, every node with its
 * level name, its label, the vaeiste's own level and how many situations and declarations carry it - its own, nothing added
 * up -, then the ranks that carry something but left the record, apart.
 *
 * Was whereami_panel::render() (lot 7, MMR-520 ticket 4): the same parameters. Nothing when the reader may not view the
 * dossier, the competencies are off or the record is not imported: drawn() says so, for the page that holds it.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class whereami_tree implements named_templatable, renderable {
    /** @var int The vaeiste. */
    private int $candidateid;

    /** @var string The record. */
    private string $fichecode;

    /** @var array The options, as whereami_panel::render() took them. */
    private array $options;

    /**
     * The panel of one record.
     *
     * @param int $candidateid The vaeiste.
     * @param string $fichecode The record.
     * @param array $options checked (string[]: the ranks whose box is ticked), boxes (bool: a box tags[] on each node a
     *        situation may carry), link (bool: the way to the self-positioning, "position myself" to the vaeiste who may,
     *        "read" to anyone else), title (string, already translated; '' for none; by default "where I stand" to the
     *        vaeiste reading their own, "where they stand" to anyone else - T15, point Z1).
     */
    public function __construct(int $candidateid, string $fichecode, array $options = []) {
        $this->candidateid = $candidateid;
        $this->fichecode = $fichecode;
        $this->options = $options;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/whereami_tree';
    }

    /**
     * Whether the panel draws anything for the current reader.
     *
     * @return bool
     */
    public function drawn(): bool {
        return whereami_panel::tree($this->candidateid, $this->fichecode) !== null;
    }

    /**
     * The context of the template: the title, the way to the self-positioning, the nodes of the tree, the ranks gone.
     *
     * @param renderer_base $output
     * @return array panel: null when nothing is drawn.
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        $tree = whereami_panel::tree($this->candidateid, $this->fichecode);
        if ($tree === null) {
            return ['panel' => null];
        }
        $options = $this->options;
        $boxes = !empty($options['boxes']);
        $checked = array_flip(array_map('strval', (array) ($options['checked'] ?? [])));
        // The first person is the vaeiste's alone: a reader - a manager who follows, the direction - reads the third.
        $words = (int) $USER->id === $this->candidateid ? 'whereamititle' : 'whereamititlereader';
        $title = array_key_exists('title', $options) ? (string) $options['title'] : get_string($words, 'tool_vae');

        $current = (new selfpos_manager())->current($this->candidateid, $this->fichecode);
        $counts = whereami_panel::counts($this->candidateid, $this->fichecode);
        $readable = draft_manager::can_read_doctype($this->candidateid, draft_manager::DOCTYPE_VALIDATION);

        $nodes = [];
        $shown = [];
        foreach ($tree->nodes as $node) {
            $rank = (string) $node->rank;
            $shown[$rank] = true;
            $line = [
                'levelname' => (string) $node->levelname,
                'labelhtml' => format_string($node->label, true, ['context' => \context_system::instance()]),
                'box' => null,
                'explain' => null,
                'selfpos' => null,
                'count' => null,
            ];
            if (!$node->positionable) {
                $line['explain'] = ['text' => get_string('selfposexplainnotpositionable', 'tool_vae')];
                // Something written on it before it lost its rank stays told here, where it still is: never "gone".
                if (isset($counts[$rank])) {
                    $line['count'] = $this->count($rank, $counts[$rank], $readable);
                }
            } else {
                if ($boxes && (int) $node->level >= situation_manager::MIN_TAG_LEVEL) {
                    $line['box'] = [
                        'id' => 'tool-vae-whereami-tag-' . $rank,
                        'value' => $rank,
                        'checked' => isset($checked[$rank]),
                    ];
                }
                $line['selfpos'] = ['words' => selfpos_screen::level_text($current[$rank] ?? null)];
                $line['count'] = $this->count($rank, $counts[$rank] ?? null, $readable);
            }
            $nodes[] = self::item((int) $node->level, $rank) + ['node' => $line, 'gone' => null];
        }

        $link = null;
        if (!empty($options['link'])) {
            $words = selfpos_manager::can_write($this->candidateid) ? 'selfposlink' : 'selfposreadall';
            $link = [
                'url' => selfpos_screen::url($this->candidateid, $this->fichecode)->out(false),
                'text' => get_string($words, 'tool_vae'),
            ];
        }

        // What was written on a node the record no longer holds: by its rank, apart, never given to a parent.
        $gone = null;
        $left = array_diff_key($counts, $shown);
        if ($left) {
            $items = [];
            foreach ($left as $rank => $count) {
                $items[] = self::item(1, (string) $rank) + [
                    'node' => null,
                    'gone' => ['rank' => (string) $rank, 'count' => $this->count((string) $rank, $count, $readable)],
                ];
            }
            $gone = ['heading' => get_string('whereamigone', 'tool_vae'), 'items' => $items];
        }

        return [
            'panel' => [
                'title' => $title === '' ? null : ['text' => $title],
                'selfposlink' => $link,
                'nodes' => $nodes,
                'gone' => $gone,
            ],
        ];
    }

    /**
     * The two counts of one node, and the way to what carries it when something does.
     *
     * @param string $rank
     * @param \stdClass|null $count As whereami_panel::counts() gives it; null for nothing.
     * @param bool $readable Whether the reader may read the situations: if not, they are never counted, not even as none.
     * @return array words (plain text), inverse (the way to the list: url and text) or null.
     */
    private function count(string $rank, ?\stdClass $count, bool $readable): array {
        $count = $count ?? (object) ['situations' => $readable ? 0 : null, 'declarations' => 0];
        $readable = $readable && $count->situations !== null;
        $words = $readable ? 'whereamicounts' : 'whereamideclarationsonly';
        $inverse = null;
        // The way to the list is offered to whoever may read it alone: situations.php refuses the others (T15, point G1).
        if ($readable && ($count->situations > 0 || $count->declarations > 0)) {
            $inverse = [
                'url' => (new \moodle_url('/admin/tool/vae/situations.php', [
                    'userid' => $this->candidateid, 'fiche' => $this->fichecode, 'rank' => $rank,
                ]))->out(false),
                'text' => get_string('whereamiinverse', 'tool_vae'),
            ];
        }

        return ['words' => get_string($words, 'tool_vae', $count), 'inverse' => $inverse];
    }

    /**
     * What sets one line of the panel in by its level.
     *
     * @param int $level
     * @param string $rank
     * @return array level, style, rank.
     */
    private static function item(int $level, string $rank): array {
        return [
            'level' => (string) $level,
            'style' => 'margin-left: ' . (($level - 1) * 1.5) . 'rem',
            'rank' => $rank,
        ];
    }
}
