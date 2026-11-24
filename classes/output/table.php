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
 * A table of the core (html_table) drawn by the template tool_vae/table, with the DOM the core writes for it (lot 7).
 *
 * The core has no template for an html_table: its table() method of the HTML writer of the core (lib/classes/output, lines
 * 528-760 in Moodle 5.3) writes it. This piece reads the same html_table and gives the template what that method would
 * write, attribute by attribute: the default class "generaltable table table-hover" when none is given, the classes
 * "header c<n>", "cell c<n>", "lastcol", "lastrow", the classes of the columns, the empty style="" of every heading and
 * cell, scope="col" on a heading that has a text, an empty heading drawn as a td, scope="row" on a cell that is the header
 * of its row, an empty body over every column when there is no row, the caption (hidden from the eyes with captionhide),
 * the wrapper "table-responsive". tests/output/table_test.php compares the two, DOM against DOM, on tables built as the
 * pages of the plugin build them.
 *
 * As in an html_table, a heading, the caption and the text of a cell are HTML: the core places them as they are, and so
 * does the template. Whoever fills the table escapes what it puts in, as before. A cell may also hold a renderable of the
 * plugin (cell_content): it is drawn here by the renderer, so that a page gives texts and links, never HTML of its own.
 *
 * What the piece does NOT draw, and refuses in words (coding_exception) instead of ignoring: the properties align, size,
 * wrap, headspan, rowclasses, tablealign (any value that is not empty), width, summary, cellpadding, cellspacing and class
 * (any value that is not null), a heading given as an html_table_cell, a divider row ('hr'), a row that is neither an array
 * nor an html_table_row, a cell that holds an object other than a renderable, an attribute whose value is not a plain value.
 * None of them is used by the plugin (git grep "html_table" on 19/11/2026).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class table implements named_templatable, renderable {
    /** @var string[] Properties drawn by the core when they are not empty, which this piece does not draw. */
    private const NOT_EMPTY = ['align', 'size', 'wrap', 'headspan', 'rowclasses', 'tablealign'];

    /** @var string[] Properties drawn by the core when they are not null, which this piece does not draw. */
    private const NOT_NULL = ['width', 'summary', 'cellpadding', 'cellspacing', 'class'];

    /** @var \html_table The table, as the page built it. */
    private \html_table $table;

    /**
     * The piece of a table.
     *
     * @param \html_table $table Built as for the writer of the core; never changed here.
     * @throws \coding_exception When the table uses a property this piece does not draw.
     */
    public function __construct(\html_table $table) {
        self::check($table);
        $this->table = $table;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/table';
    }

    /**
     * The context of the template: what the writer of the core would write, attribute by attribute.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $table = $this->table;
        $attributes = (array) $table->attributes;
        if (empty($attributes['class'])) {
            $attributes['class'] = 'generaltable table table-hover';
        }
        $attributes['class'] = trim($attributes['class']);
        $attributes = array_merge($attributes, ['id' => $table->id]);

        $caption = false;
        if (!empty($table->caption)) {
            $caption = [
                'attributes' => self::attributes($table->captionhide ? ['class' => 'visually-hidden'] : []),
                'texthtml' => (string) $table->caption,
            ];
        }

        $head = [];
        if (!empty($table->head)) {
            $keys = array_keys($table->head);
            $lastkey = end($keys);
            foreach ($table->head as $key => $text) {
                $isth = (string) $text !== '';
                $class = ' header c' . $key;
                if ($key == $lastkey) {
                    $class .= ' lastcol';
                }
                if (isset($table->colclasses[$key])) {
                    $class .= ' ' . $table->colclasses[$key];
                }
                $cell = ['class' => trim($class), 'style' => '', 'colspan' => null];
                if ($isth) {
                    $cell['scope'] = 'col';
                }
                $head[] = ['isth' => $isth, 'attributes' => self::attributes($cell), 'texthtml' => (string) $text];
            }
        }

        $rows = [];
        if (!empty($table->data)) {
            $keys = array_keys($table->data);
            $lastrowkey = end($keys);
            foreach ($table->data as $key => $row) {
                $rows[] = $this->row($row, $key == $lastrowkey, $output);
            }
        }

        return [
            'responsive' => (bool) $table->responsive,
            'attributes' => self::attributes($attributes),
            'caption' => $caption,
            'hashead' => $head !== [],
            'head' => $head,
            // For a valid table, the core draws an empty body over every column when there is a heading and no row.
            'emptybody' => $head !== [] && $rows === [] ? ['colspan' => count($table->head)] : false,
            'hasbody' => $rows !== [],
            'rows' => $rows,
        ];
    }

    /**
     * One row of the body, its cells numbered by their keys, as the core numbers them.
     *
     * @param array|\html_table_row $row
     * @param bool $last Whether it is the last row of the table.
     * @param renderer_base $output
     * @return array
     */
    private function row($row, bool $last, renderer_base $output): array {
        if ($row instanceof \html_table_row) {
            $cells = $row->cells;
            $attributes = (array) $row->attributes;
            $id = $row->id;
            $style = $row->style;
        } else {
            // An array of cells: the core makes a new row of them, numbered from 0.
            $cells = array_values($row);
            $attributes = ['class' => ''];
            $id = null;
            $style = null;
        }
        $attributes['class'] = ($attributes['class'] ?? '') . ($last ? ' lastrow' : '');
        $attributes['class'] = trim($attributes['class']);
        $attributes = array_merge($attributes, ['id' => $id, 'style' => $style]);

        $keys = array_keys($cells);
        $lastkey = end($keys);
        $out = [];
        foreach ($cells as $key => $cell) {
            if (!($cell instanceof \html_table_cell)) {
                $cell = new \html_table_cell($cell);
            }
            $scope = $cell->scope;
            if ($cell->header === true && empty($scope)) {
                $scope = 'row';
            }
            $class = (string) ($cell->attributes['class'] ?? '');
            if (isset($this->table->colclasses[$key])) {
                $class .= ' ' . $this->table->colclasses[$key];
            }
            $class .= ' cell c' . $key;
            if ($key == $lastkey) {
                $class .= ' lastcol';
            }
            $cellattributes = (array) $cell->attributes;
            $cellattributes['class'] = trim($class);
            $cellattributes = array_merge($cellattributes, [
                'style' => '' . $cell->style,
                'colspan' => $cell->colspan,
                'rowspan' => $cell->rowspan,
                'id' => $cell->id,
                'abbr' => $cell->abbr,
                'scope' => $scope,
            ]);
            $out[] = [
                'isth' => $cell->header === true,
                'attributes' => self::attributes($cellattributes),
                'texthtml' => self::text($cell->text, $output),
            ];
        }

        return ['attributes' => self::attributes($attributes), 'cells' => $out];
    }

    /**
     * The HTML of a cell: its text as the core places it, or a renderable of the plugin drawn by the renderer.
     *
     * @param mixed $text
     * @param renderer_base $output
     * @return string HTML.
     */
    private static function text($text, renderer_base $output): string {
        if ($text instanceof renderable) {
            return $output->render($text);
        }

        return (string) $text;
    }

    /**
     * Attributes as the template writes them: in their order, a null value left out, every value as text.
     *
     * @param array $attributes Name => value.
     * @return array[] Each {name, value}.
     */
    private static function attributes(array $attributes): array {
        $out = [];
        foreach ($attributes as $name => $value) {
            if ($value === null) {
                continue;
            }
            $out[] = ['name' => (string) $name, 'value' => (string) $value];
        }

        return $out;
    }

    /**
     * Refuse, in words, a table that uses what this piece does not draw.
     *
     * @param \html_table $table
     * @throws \coding_exception
     */
    private static function check(\html_table $table): void {
        $refuse = function (string $what): void {
            throw new \coding_exception('tool_vae\output\table does not draw ' . $what
                . ': draw this table another way, or teach the piece and its test to draw it.');
        };
        foreach (self::NOT_EMPTY as $name) {
            if (!empty($table->$name)) {
                $refuse('the property ' . $name . ' of html_table');
            }
        }
        foreach (self::NOT_NULL as $name) {
            if ($table->$name !== null) {
                $refuse('the property ' . $name . ' of html_table');
            }
        }
        foreach ((array) $table->attributes as $value) {
            if ($value !== null && !is_scalar($value)) {
                $refuse('an attribute whose value is not a plain value');
            }
        }
        foreach ((array) $table->head as $heading) {
            if (!is_scalar($heading) && $heading !== null) {
                $refuse('a heading given as an html_table_cell or another object (head)');
            }
        }
        foreach ((array) $table->data as $row) {
            if ($row === 'hr') {
                $refuse('a divider row (hr)');
            }
            if (!is_array($row) && !($row instanceof \html_table_row)) {
                $refuse('a row that is neither an array nor an html_table_row');
            }
            $cells = $row instanceof \html_table_row ? $row->cells : $row;
            if ($row instanceof \html_table_row) {
                self::check_attributes((array) $row->attributes, $refuse);
            }
            foreach ($cells as $cell) {
                $text = $cell instanceof \html_table_cell ? $cell->text : $cell;
                if (is_object($text) && !($text instanceof renderable)) {
                    $refuse('a cell that holds an object other than a renderable');
                }
                if ($cell instanceof \html_table_cell) {
                    self::check_attributes((array) $cell->attributes, $refuse);
                }
            }
        }
    }

    /**
     * Refuse an attribute of a row or a cell whose value is not a plain value.
     *
     * @param array $attributes
     * @param callable $refuse
     */
    private static function check_attributes(array $attributes, callable $refuse): void {
        foreach ($attributes as $value) {
            if ($value !== null && !is_scalar($value)) {
                $refuse('an attribute whose value is not a plain value');
            }
        }
    }
}
