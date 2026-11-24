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
use tool_vae\local\situation_screen;

/**
 * The lines that say a situation (lot 1b), in a block: period, setting, tags - each only when it has something to say -,
 * then the lines the caller adds (the number of pieces, where the text stands), one line break between two lines.
 *
 * Was situation_screen::facts() and the implode('<br>', ...) of list(), details() and inverse() (lot 7, MMR-520 ticket 4,
 * wave 4a): the break is written by the template between the lines, never after the last.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class situation_facts implements named_templatable, renderable {
    /** @var \stdClass A row of tool_vae_situation, with tags. */
    private \stdClass $row;

    /** @var \stdClass|null The tree of its record, to name the tags. */
    private ?\stdClass $tree;

    /** @var string[] The lines added after the facts, plain text. */
    private array $more;

    /** @var string The classes of the block. */
    private string $class;

    /**
     * The facts of a situation.
     *
     * @param \stdClass $row A row of tool_vae_situation, with tags.
     * @param \stdClass|null $tree The tree of its record, to name the tags.
     * @param string[] $more The lines added after the facts, plain text (escaped by the template).
     * @param string $class The classes of the block.
     */
    public function __construct(\stdClass $row, ?\stdClass $tree, array $more, string $class) {
        $this->row = $row;
        $this->tree = $tree;
        $this->more = $more;
        $this->class = $class;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/situation_facts';
    }

    /**
     * The context of the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $lines = [];
        $period = situation_screen::period(
            $this->row->periodstart === null ? null : (int) $this->row->periodstart,
            $this->row->periodend === null ? null : (int) $this->row->periodend
        );
        if ($period !== '') {
            $lines[] = ['label' => ['words' => get_string('situationperiod', 'tool_vae')], 'text' => $period];
        }
        if ((string) $this->row->setting !== '') {
            $lines[] = ['label' => ['words' => get_string('situationsetting', 'tool_vae')], 'text' => (string) $this->row->setting];
        }
        if (!empty($this->row->tags)) {
            $tags = array_map(fn(string $rank) => situation_screen::node_words($this->tree, $rank), $this->row->tags);
            $lines[] = ['label' => ['words' => get_string('situationtags', 'tool_vae')], 'text' => implode(' ; ', $tags)];
        }
        foreach ($this->more as $text) {
            $lines[] = ['label' => null, 'text' => $text];
        }
        foreach (array_keys($lines) as $i) {
            $lines[$i]['sep'] = $i > 0;
        }

        return ['class' => $this->class, 'lines' => $lines];
    }
}
