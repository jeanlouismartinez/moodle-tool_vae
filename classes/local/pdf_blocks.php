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

namespace tool_vae\local;

/**
 * The blocks of the printable copy of a dossier, and their drawing by the template tool_vae/pdf_blocks.
 *
 * tool_vae\local\pdf_renderer decides WHAT the PDF says; this class holds the shape of the context the template reads: a
 * block sets one kind (a heading, a paragraph, a rule, a table, a list, text that is already HTML) and leaves the others
 * false, so that the template never reads a key from the block above it. The HTML that comes out is the one TCPDF reads;
 * nothing of it is written in PHP.
 *
 * Kept apart from pdf_renderer, whose name the coding standard takes for a renderer of a page: a renderer reaches the output
 * by $this->output, and this class is not one.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pdf_blocks {
    /** @var string[] The kinds of block of the template: a block sets one and leaves the others false. */
    private const KINDS = ['h1', 'h2', 'h3', 'h4', 'p', 'note', 'hr', 'table', 'html', 'list'];

    /**
     * One block of text: a heading (h1 to h4), a paragraph (p), a paragraph in italics (note), a rule (hr) or HTML (html).
     *
     * @param string $kind One of the kinds above, but table and list, which have their own method.
     * @param string $text Plain text, escaped once by the template; HTML for the kind html; ignored for hr.
     * @return array
     */
    public static function block(string $kind, string $text): array {
        return array_merge(array_fill_keys(self::KINDS, false), [$kind => ['text' => $text]]);
    }

    /**
     * Label and value pairs, as a borderless table TCPDF lays out on two columns.
     *
     * @param string[] $rows Plain-text label => value, already escaped or made by the core (HTML).
     * @return array A block.
     */
    public static function table(array $rows): array {
        $list = [];
        foreach ($rows as $label => $value) {
            $list[] = ['label' => (string) $label, 'valuehtml' => $value];
        }

        return array_merge(array_fill_keys(self::KINDS, false), ['table' => ['rows' => $list]]);
    }

    /**
     * A list block.
     *
     * @param array[] $items Each from plain_item(), or an attestation in force (plain false, signed: role, bodyhtml, text).
     * @return array A block.
     */
    public static function bullets(array $items): array {
        return array_merge(array_fill_keys(self::KINDS, false), ['list' => ['items' => array_values($items)]]);
    }

    /**
     * An item of a list: one plain text.
     *
     * @param string $text
     * @return array
     */
    public static function plain_item(string $text): array {
        return ['plain' => ['text' => $text], 'signed' => false];
    }

    /**
     * The HTML of blocks, drawn by the template.
     *
     * @param array[] $blocks
     * @return string HTML.
     */
    public static function draw(array $blocks): string {
        global $OUTPUT;

        return $OUTPUT->render_from_template('tool_vae/pdf_blocks', ['blocks' => $blocks]);
    }
}
