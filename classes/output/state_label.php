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
use tool_vae\local\presenter;

/**
 * The state of a declaration, written out, with a question waiting when there is one (T-40; lot 7, MMR-520 ticket 4).
 *
 * Text, never colour alone. DESCRIBES, NEVER JUDGES: the state (presenter::state()) says what was done to the declaration,
 * nothing of whether it is good enough. A question still waiting for an answer is said next to it, after a space, and not
 * in its place. Was presenter::state_label().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class state_label implements named_templatable, renderable {
    /** @var \stdClass[] Rows of tool_vae_attest. */
    private array $attestations;

    /** @var int How many questions still wait for an answer. */
    private int $openrequests;

    /**
     * The state of one declaration.
     *
     * @param \stdClass[] $attestations Rows of tool_vae_attest, oldest first.
     * @param int $openrequests How many questions still wait for an answer.
     */
    public function __construct(array $attestations, int $openrequests = 0) {
        $this->attestations = $attestations;
        $this->openrequests = $openrequests;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/state_label';
    }

    /**
     * The context of the template: the word of the state, and the word of a question waiting or false.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'state' => get_string('state:' . presenter::state($this->attestations), 'tool_vae'),
            'question' => $this->openrequests > 0 ? ['text' => get_string('state:questionopen', 'tool_vae')] : false,
        ];
    }
}
