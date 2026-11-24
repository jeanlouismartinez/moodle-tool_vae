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
 * A cell of the table of the preview of an import of records (lot 7, MMR-520 ticket 4).
 *
 * What a cell of tool_vae\output\fiche_preview holds: the number of a record in bold and its title under it (the header of
 * the row), or the blocs of the export folded away, or lines one under the other, each a div with or without a class. Put
 * in a cell of an html_table given to tool_vae\output\table, which draws it by the renderer of the plugin. The parts touch,
 * with nothing between them, as they did when the screen wrote them by hand.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fiche_cell implements named_templatable, renderable {
    /** @var array The context of the template. */
    private array $context;

    /**
     * Use record() or lines().
     *
     * @param array $context
     */
    private function __construct(array $context) {
        $this->context = $context + ['record' => false, 'blocs' => false, 'lines' => []];
    }

    /**
     * The header of the row of a record: its number in bold, its title under it when it has one.
     *
     * @param string $number Plain text.
     * @param string|null $title Plain text, or null for none.
     * @return self
     */
    public static function record(string $number, ?string $title): self {
        return new self(['record' => ['number' => $number, 'title' => $title === null ? false : ['label' => $title]]]);
    }

    /**
     * Lines one under the other, after the blocs of the export when they are given.
     *
     * @param array|null $blocs ['summary' => plain text, 'rows' => [['rang' => ..., 'label' => ...]]], or null for none.
     * @param array[] $lines Made by line() and html().
     * @return self
     */
    public static function lines(?array $blocs, array $lines): self {
        return new self(['blocs' => $blocs ?? false, 'lines' => $lines]);
    }

    /**
     * A line of plain text.
     *
     * @param string $class The class of its div, empty for none.
     * @param string $text Plain text.
     * @return array
     */
    public static function line(string $class, string $text): array {
        return ['classed' => $class === '' ? false : ['name' => $class], 'text' => ['label' => $text], 'html' => false];
    }

    /**
     * A line of HTML that is already safe: a sentence of a refusal (fiche_messages::html(), whose parameters are escaped).
     *
     * @param string $class The class of its div, empty for none.
     * @param string $html
     * @return array
     */
    public static function html(string $class, string $html): array {
        return ['classed' => $class === '' ? false : ['name' => $class], 'text' => false, 'html' => ['texthtml' => $html]];
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/fiche_cell';
    }

    /**
     * The context of the template: texts in clear, every optional part in an object (a text equal to "0" is drawn).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return $this->context;
    }
}
