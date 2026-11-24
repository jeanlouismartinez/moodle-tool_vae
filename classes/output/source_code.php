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
 * The code of a certification record in bold, with its provenance line under it when it has one (lot 7, MMR-520 ticket 4).
 *
 * What a cell of the tables of the administration screen holds (the records, the records that can be removed): put in a
 * cell of an html_table given to tool_vae\output\table, which draws it by the renderer of the plugin. The two parts touch,
 * with nothing between them, as they did when the screen wrote them by hand.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class source_code implements named_templatable, renderable {
    /** @var string The code of the record. */
    private string $code;

    /** @var provenance|null Its provenance line, or none. */
    private ?provenance $provenance;

    /**
     * The code of a record.
     *
     * @param string $code Plain text.
     * @param provenance|null $provenance Its provenance line, drawn under it; none when null.
     */
    public function __construct(string $code, ?provenance $provenance = null) {
        $this->code = $code;
        $this->provenance = $provenance;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/source_code';
    }

    /**
     * The context of the template: the code in clear; the provenance line as HTML of its own template, or false.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'code' => $this->code,
            'provenancehtml' => $this->provenance === null ? false : $output->render($this->provenance),
        ];
    }
}
