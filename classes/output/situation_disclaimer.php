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
 * The two sentences every screen of the work situations carries (lot 1b): who reads it, and that nothing here is attested.
 * Was situation_screen::disclaimer() (lot 7, MMR-520 ticket 4, wave 4a).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class situation_disclaimer implements named_templatable, renderable {
    /** @var int The vaeiste whose screen it is. */
    private int $candidateid;

    /**
     * The sentences.
     *
     * @param int $candidateid The vaeiste whose screen it is.
     */
    public function __construct(int $candidateid) {
        $this->candidateid = $candidateid;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/situation_disclaimer';
    }

    /**
     * The context of the template: the vaeiste reads that the screen is theirs, anyone else that it is the vaeiste's.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $USER;

        $own = (int) $USER->id === $this->candidateid;

        return [
            'text' => get_string($own ? 'selfposvisible' : 'selfposvisibleread', 'tool_vae') . ' '
                . get_string('situationnotattested', 'tool_vae'),
        ];
    }
}
