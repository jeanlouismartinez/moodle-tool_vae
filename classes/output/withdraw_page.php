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
use tool_vae\form\withdraw_form;

/**
 * The page "Retirer une attestation" (withdraw.php), below its heading (lot 7, MMR-520 ticket 4).
 *
 * The declaration with the warning of its anchor, the attestation that is withdrawn, then the form of the core: the person
 * withdrawing sees what survives. The page checks the right, reads the attestation, handles the form and redirects before;
 * this class places them for the template tool_vae/withdraw_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class withdraw_page implements named_templatable, renderable {
    /** @var \stdClass A row of tool_vae_evidence. */
    private \stdClass $evidence;

    /** @var \context The vaeiste's context. */
    private \context $context;

    /** @var \stdClass The attestation withdrawn, a row of tool_vae_attest. */
    private \stdClass $attestation;

    /** @var withdraw_form The form, drawn once. */
    private withdraw_form $form;

    /**
     * The page.
     *
     * @param \stdClass $evidence A row of tool_vae_evidence.
     * @param \context $context The vaeiste's context.
     * @param \stdClass $attestation The attestation withdrawn, a row of tool_vae_attest.
     * @param withdraw_form $form The form, built and read by the page.
     */
    public function __construct(\stdClass $evidence, \context $context, \stdClass $attestation, withdraw_form $form) {
        $this->evidence = $evidence;
        $this->context = $context;
        $this->attestation = $attestation;
        $this->form = $form;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/withdraw_page';
    }

    /**
     * The context of the template: the three parts, each HTML of its own piece or of the core.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // Drawn in the order the page drew them: what a piece asks of the page (its JavaScript) comes in the same order.
        $declaration = $output->render(new attest_declaration($this->evidence, $this->context));
        $attestations = $output->render(new attestations([$this->attestation->id => $this->attestation]));

        return [
            'declarationhtml' => $declaration,
            'attestationshtml' => $attestations,
            'formhtml' => $this->form->render(),
        ];
    }
}
