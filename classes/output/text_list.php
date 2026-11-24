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
 * A list of lines of plain text, as the writer of HTML of the core wrote it, a line break after the opening tag and after
 * each item (lot 7, MMR-520 ticket 4, wave 4c).
 *
 * Used by the refusal of an archive that numbers competencies otherwise than this site (dossier_importer): the list of
 * those competencies goes into the sentence of the language file that refuses the archive, which is shown as HTML. Each
 * line is given plain: the template escapes it, a record code and a label being what an administrator typed into the
 * framework, or what an import wrote from a file.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class text_list implements named_templatable, renderable {
    /** @var string[] The lines, plain text. */
    private array $lines;

    /**
     * A list.
     *
     * @param string[] $lines Plain text, not escaped, in their order.
     */
    public function __construct(array $lines) {
        $this->lines = array_values($lines);
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/text_list';
    }

    /**
     * The context of the template: each line, plain.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return ['lines' => array_map(fn($line) => ['text' => (string) $line], $this->lines)];
    }
}
