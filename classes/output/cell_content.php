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
 * What a cell of a table holds, drawn by the template tool_vae/cell_content (lot 7, MMR-520 ticket 4).
 *
 * A row of parts, side by side with no space between them: a text, a link, a span or a block with their classes, or HTML
 * that the core made (the result of format_string(), a button of the renderer). A page gives texts, which the template
 * escapes, never HTML of its own. It is put in a cell of an html_table given to tool_vae\output\table, which draws it by the
 * renderer of the plugin.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cell_content implements named_templatable, renderable {
    /** @var string[] The kinds of part, in the order the context lists them. */
    private const KINDS = ['text', 'link', 'span', 'div', 'html'];

    /** @var array[] The parts, each with one kind set. */
    private array $parts;

    /**
     * The content of a cell.
     *
     * @param array[] $parts Made by text(), link(), span(), div() or html().
     * @throws \coding_exception When a part is of no known kind.
     */
    public function __construct(array $parts) {
        foreach ($parts as $part) {
            if (!is_array($part) || count($part) !== 1 || !in_array(key($part), self::KINDS, true)) {
                throw new \coding_exception('tool_vae\output\cell_content does not know this part: make it with text(), link(),'
                    . ' span(), div() or html().');
            }
        }
        $this->parts = array_values($parts);
    }

    /**
     * A text, escaped by the template.
     *
     * @param string $text Plain text, not escaped.
     * @return array A part.
     */
    public static function text(string $text): array {
        // The text is held in an object, as a link is: a section of the template that tested the text itself would draw
        // nothing for "0", which Mustache holds for empty.
        return ['text' => ['label' => $text]];
    }

    /**
     * A link.
     *
     * @param \moodle_url $url
     * @param string $label Plain text, not escaped.
     * @return array A part.
     */
    public static function link(\moodle_url $url, string $label): array {
        // The address is given unescaped: the template escapes it once, as the writer of the core did.
        return ['link' => ['url' => $url->out(false), 'label' => $label]];
    }

    /**
     * A span with its classes.
     *
     * @param string $class
     * @param string $label Plain text, not escaped.
     * @return array A part.
     */
    public static function span(string $class, string $label): array {
        return ['span' => ['class' => $class, 'label' => $label]];
    }

    /**
     * A block with its classes.
     *
     * @param string $class
     * @param string $label Plain text, not escaped.
     * @return array A part.
     */
    public static function div(string $class, string $label): array {
        return ['div' => ['class' => $class, 'label' => $label]];
    }

    /**
     * HTML that the core made: the result of format_string() or format_text(), or of a method of the renderer.
     *
     * @param string $html
     * @return array A part.
     */
    public static function html(string $html): array {
        // Held in an object for the same reason as a text: format_string() may give "0".
        return ['html' => ['texthtml' => $html]];
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/cell_content';
    }

    /**
     * The context of the template: each part with every kind named, the one it is set to an object, the others false.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $parts = [];
        foreach ($this->parts as $part) {
            $parts[] = array_merge(array_fill_keys(self::KINDS, false), $part);
        }

        return ['parts' => $parts];
    }
}
