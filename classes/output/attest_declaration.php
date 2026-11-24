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
use tool_vae\local\anchor;
use tool_vae\local\competency_gateway;

/**
 * The declaration as the screens that ask a manager to act on it draw it (lot 7, MMR-520 ticket 4).
 *
 * THE DECLARATION, AND BEFORE IT WHAT THE PLATFORM NO LONGER SAYS OF IT. The attestation screen is the only reading of the
 * narrative by someone who did not write it, and so the screen where the anchor of the declaration matters most: the
 * vaeiste can unlink the competency from the competencies of Moodle in two clicks, with nothing in this plugin involved,
 * and the screen would go on saying "Competency: ..." for a link the platform no longer holds. The warning is the
 * dossier's own (anchor::divergence() and tool_vae\output\anchor_warning), drawn BEFORE the declaration with nothing between
 * them, for the reader who has already taken the record at face value. It only says: the plugin prevents nothing (decision
 * of 0.6.0). Whoever signs knows what they sign. Drawn by the pages attest, piece, precision and withdraw. Was
 * attest_screen::declaration().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attest_declaration implements named_templatable, renderable {
    /** @var \stdClass A row of tool_vae_evidence. */
    private \stdClass $evidence;

    /** @var \context The vaeiste's user context, for format_text(). */
    private \context $context;

    /** @var competency_gateway|null The reader of the core; the real one when null. */
    private ?competency_gateway $gateway;

    /**
     * The declaration and its warning.
     *
     * @param \stdClass $evidence A row of tool_vae_evidence.
     * @param \context $context The vaeiste's user context, for format_text().
     * @param competency_gateway|null $gateway The reader of the core; the real one when null.
     */
    public function __construct(\stdClass $evidence, \context $context, ?competency_gateway $gateway = null) {
        $this->evidence = $evidence;
        $this->context = $context;
        $this->gateway = $gateway;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/attest_declaration';
    }

    /**
     * The context of the template: the warning (empty when the anchor holds) and the declaration, each drawn by its piece.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'warninghtml' => $output->render(new anchor_warning(
                $this->evidence,
                anchor::divergence($this->evidence, $this->gateway)
            )),
            'declarationhtml' => $output->render(new declaration($this->evidence, $this->context)),
        ];
    }
}
