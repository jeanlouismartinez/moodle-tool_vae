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
 * The banner "Ce que je peux faire maintenant" (lot S2; lot 7, MMR-520 ticket 4): the actions of next_actions, in their order.
 *
 * The block of the vaeiste shows one button per action, worded in words; the block of the manager shows one line per entry,
 * its number in the text of a link readable out of context. An empty list reads a plain sentence, without congratulation.
 * The banner draws no conclusion: no total, no percentage, no "N of M", no colour of success. It names nobody, reads nothing
 * and writes nothing: it only renders the objects it is given (as tool_vae\local\next_actions gives them).
 *
 * The HTML is written by the template tool_vae/next_actions (lot Q10); this class gives it plain texts, worded here (the
 * singular or the plural included), which the template escapes. Before lot 7, tool_vae\local\next_actions_screen gave the
 * same context to the same template.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class next_actions implements named_templatable, renderable {
    /** @var bool Whether this is the block of the manager. */
    private bool $manager;

    /** @var \stdClass[] The actions or the lines: {key, count, url}. */
    private array $entries;

    /**
     * Use candidate() or manager().
     *
     * @param bool $manager
     * @param \stdClass[] $entries
     */
    private function __construct(bool $manager, array $entries) {
        $this->manager = $manager;
        $this->entries = $entries;
    }

    /**
     * The block of the vaeiste, on the tab "Mon parcours".
     *
     * @param \stdClass[] $actions As next_actions::for_vaeiste() gives them: {key, count, url}.
     * @return self
     */
    public static function candidate(array $actions): self {
        return new self(false, $actions);
    }

    /**
     * The block of the manager, on the tab "Vaeistes suivis".
     *
     * @param \stdClass[] $rows As next_actions::for_manager() gives them: {key, count, url}, count above zero.
     * @return self
     */
    public static function manager(array $rows): self {
        return new self(true, $rows);
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/next_actions';
    }

    /**
     * The context of the template: plain texts, the title, then the buttons, the lines or the plain sentence.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return ($this->manager ? $this->lines() : $this->buttons()) + [
            'title' => get_string('nextactionstitle', 'tool_vae'),
            'none' => false,
            'hasbuttons' => false,
            'buttons' => [],
            'haslines' => false,
            'lines' => [],
            'note' => '',
        ];
    }

    /**
     * The buttons of the vaeiste, or the plain sentence.
     *
     * @return array
     */
    private function buttons(): array {
        if (!$this->entries) {
            return ['none' => get_string('nextactionsnone', 'tool_vae')];
        }
        $buttons = [];
        foreach ($this->entries as $action) {
            // The number is told only when several requests wait: one request reads in the singular, without a number.
            $several = $action->key === \tool_vae\local\next_actions::ANSWER && (int) $action->count > 1;
            $prefix = $several ? 'nextactionsactions_' : 'nextactionsaction_';
            $buttons[] = self::link($action->url, get_string($prefix . $action->key, 'tool_vae', (int) $action->count));
        }

        return ['hasbuttons' => true, 'buttons' => $buttons];
    }

    /**
     * The lines of the manager, or the plain sentence.
     *
     * @return array
     */
    private function lines(): array {
        if (!$this->entries) {
            return ['none' => get_string('nextactionsnonemanager', 'tool_vae')];
        }
        $lines = [];
        foreach ($this->entries as $row) {
            $count = (int) $row->count;
            $key = ($count === 1 ? 'nextactionsline_' : 'nextactionslines_') . $row->key;
            $lines[] = self::link($row->url, get_string($key, 'tool_vae', $count));
        }

        return ['haslines' => true, 'lines' => $lines, 'note' => get_string('nextactionsallblocs', 'tool_vae')];
    }

    /**
     * One link of the banner, for the template: its url and its text, neither escaped (the template escapes them once).
     *
     * @param \moodle_url|string $url
     * @param string $label
     * @return array{url: string, label: string}
     */
    private static function link(\moodle_url|string $url, string $label): array {
        return ['url' => $url instanceof \moodle_url ? $url->out(false) : $url, 'label' => $label];
    }
}
