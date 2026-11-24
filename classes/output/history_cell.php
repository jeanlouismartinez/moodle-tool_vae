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
 * A field of a cell of the table of the versions of a section (lot 7, MMR-520 ticket 4).
 *
 * What a cell of tool_vae\output\history_page holds: the radio button that chooses a version to compare, or the button
 * that restores a version (it submits the small form of that version, outside the table: a form cannot hold another). Put in
 * a cell of an html_table given to tool_vae\output\table, which draws it by the renderer of the plugin.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_cell implements named_templatable, renderable {
    /** @var array The context of the template. */
    private array $context;

    /**
     * Use radio() or restore().
     *
     * @param array $context
     */
    private function __construct(array $context) {
        $this->context = $context + ['radio' => false, 'restore' => false];
    }

    /**
     * A radio button that chooses a version.
     *
     * @param string $name from or to.
     * @param int $number The number of the version.
     * @param string $label What a reader of a screen hears.
     * @param bool $checked Whether it is chosen when the page opens.
     * @return self
     */
    public static function radio(string $name, int $number, string $label, bool $checked): self {
        return new self(['radio' => ['name' => $name, 'value' => $number, 'label' => $label, 'checked' => $checked]]);
    }

    /**
     * The button that restores a version.
     *
     * @param int $number The number of the version.
     * @param string $label The words of the button.
     * @return self
     */
    public static function restore(int $number, string $label): self {
        return new self(['restore' => ['form' => 'tool-vae-restore-' . $number, 'label' => $label]]);
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/history_cell';
    }

    /**
     * The context of the template: the texts in clear.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return $this->context;
    }
}
