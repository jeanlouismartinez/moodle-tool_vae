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
 * The page "Mise en route" (lot S1), below its heading: the steps of setup_checklist, in their order.
 *
 * Each step shows its title, its state in words, its sentences, the parts of the installation guide and, when the reader may
 * open the screen that sets it, a link. A reader who may not open that screen reads its name and the right it asks, in plain
 * text: no link is shown that its reader cannot follow. The page draws no conclusion: no total, no count of the steps done,
 * no colour of success. It reads no person and writes nothing.
 *
 * The HTML is written by the template tool_vae/setup_page; this class gives it plain texts, worded here, which the template
 * escapes. The right a link asks is checked here, never in the template.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class setup_page implements named_templatable, renderable {
    /** @var \stdClass[] The steps, as setup_checklist::steps() gives them. */
    private array $steps;

    /**
     * The page of the given steps.
     *
     * @param \stdClass[] $steps As setup_checklist::steps() gives them.
     */
    public function __construct(array $steps) {
        $this->steps = $steps;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/setup_page';
    }

    /**
     * The context of the template: plain texts, not escaped (the template escapes them).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $items = [];
        foreach ($this->steps as $step) {
            $items[] = self::step($step);
        }

        return [
            'intro' => get_string('setupintro', 'tool_vae'),
            'steps' => $items,
            'footer' => get_string('setupfooter', 'tool_vae'),
        ];
    }

    /**
     * One step: title, state in words, sentences, parts of the guide, then the link or the name of the screen.
     *
     * @param \stdClass $step
     * @return array The context of the step, plain texts not escaped.
     */
    private static function step(\stdClass $step): array {
        return [
            'key' => $step->key,
            'title' => get_string('setupstep_' . $step->key, 'tool_vae'),
            'state' => get_string('setupstate_' . $step->state, 'tool_vae'),
            'details' => array_values(array_map('strval', $step->details)),
            'guide' => self::guide($step->guide),
        ] + self::link($step);
    }

    /**
     * The parts of the installation guide a step refers to: "part 4", or "parts 6 and 7" (the first, then the others).
     *
     * @param int[] $parts
     * @return string Plain text, not escaped.
     */
    private static function guide(array $parts): string {
        if (count($parts) < 2) {
            return get_string('setupguide', 'tool_vae', implode('', $parts));
        }
        $last = array_pop($parts);

        return get_string('setupguides', 'tool_vae', (object) ['first' => implode(', ', $parts), 'last' => $last]);
    }

    /**
     * The link to the screen of the step when the reader may open it; otherwise the name of the screen and the right it asks.
     *
     * A step with no url, or a url without the right it asks, gets neither link nor text: a right that is not named cannot
     * be checked, so no link is shown.
     *
     * @param \stdClass $step
     * @return array {link: false|array{url: string, label: string}, needs: false|string}; both false when the step has no
     *     screen or no right to check.
     */
    private static function link(\stdClass $step): array {
        $none = ['link' => false, 'needs' => false];
        if ($step->url === null || $step->capability === null) {
            return $none;
        }
        $screen = get_string('setuplink_' . $step->key, 'tool_vae');
        if (has_capability($step->capability, \context_system::instance())) {
            // The url is given unescaped: the template escapes it once, as html_writer did.
            $url = $step->url instanceof \moodle_url ? $step->url->out(false) : (string) $step->url;

            return ['link' => ['url' => $url, 'label' => $screen]] + $none;
        }

        return ['needs' => get_string('setupneeds', 'tool_vae', (object) [
            'screen' => $screen,
            'capability' => $step->capability,
        ])] + $none;
    }
}
