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
 * The beginning of the digest of a deposit, as code (lot 7, MMR-520 ticket 4).
 *
 * What a cell of the table of the deposits holds (tool_vae\output\deposit_page): put in a cell of an html_table given to
 * tool_vae\output\table, which draws it by the renderer of the plugin.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deposit_digest implements named_templatable, renderable {
    /** @var string The digest, or its beginning. */
    private string $digest;

    /**
     * The digest of a deposit.
     *
     * @param string $digest Plain text.
     */
    public function __construct(string $digest) {
        $this->digest = $digest;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/deposit_digest';
    }

    /**
     * The context of the template: the digest in clear.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return ['digest' => $this->digest];
    }
}
