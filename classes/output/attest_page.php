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
use core\output\notification;
use core\output\renderable;
use core\output\renderer_base;
use tool_vae\form\attest_form;

/**
 * The page "Attester une déclaration" (attest.php), below its heading (lot 7, MMR-520 ticket 4).
 *
 * The declaration with the warning of its anchor (the only reading of the narrative by someone who did not write it), the
 * supporting pieces when there are some (the list shown is the list signed), then either the form of the core, or - the
 * declaration already signed - the notice that it is, the whole history of its attestations (an earlier one withdrawn
 * shows) and the way out the reader has: the button to withdraw, the button to ask the signatory to withdraw, the notice
 * that the signatory is gone, or nothing. The page checks the rights, reads the declaration, its pieces and the way out,
 * handles the form and redirects before; this class places them for the template tool_vae/attest_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attest_page implements named_templatable, renderable {
    /** @var string The reader may withdraw the attestation in force. */
    public const RECOURSE_WITHDRAW = 'withdraw';

    /** @var string The reader may ask the signatory to withdraw it. */
    public const RECOURSE_REQUEST = 'request';

    /** @var string The signatory is gone, and nobody can answer a request. */
    public const RECOURSE_GONE = 'gone';

    /** @var \stdClass A row of tool_vae_evidence. */
    private \stdClass $evidence;

    /** @var \context_user The vaeiste's context. */
    private \context_user $context;

    /** @var \stdClass[] Rows of tool_vae_piece, removed ones included. */
    private array $pieces;

    /** @var \stdClass|null The attestation in force, when the declaration is signed. */
    private ?\stdClass $standing;

    /** @var \stdClass[] Every attestation of the declaration, when it is signed. */
    private array $attestations;

    /** @var string|null One of the RECOURSE_ constants, or null. */
    private ?string $recourse;

    /** @var attest_form|null The form, when the declaration is not signed; drawn once. */
    private ?attest_form $form;

    /**
     * Use unsigned() or signed().
     *
     * @param \stdClass $evidence
     * @param \context_user $context
     * @param \stdClass[] $pieces
     * @param \stdClass|null $standing
     * @param \stdClass[] $attestations
     * @param string|null $recourse
     * @param attest_form|null $form
     */
    private function __construct(
        \stdClass $evidence,
        \context_user $context,
        array $pieces,
        ?\stdClass $standing,
        array $attestations,
        ?string $recourse,
        ?attest_form $form
    ) {
        $this->evidence = $evidence;
        $this->context = $context;
        $this->pieces = $pieces;
        $this->standing = $standing;
        $this->attestations = $attestations;
        $this->recourse = $recourse;
        $this->form = $form;
    }

    /**
     * The declaration not signed yet: the form.
     *
     * @param \stdClass $evidence A row of tool_vae_evidence.
     * @param \context_user $context The vaeiste's context.
     * @param \stdClass[] $pieces piece_manager::for_evidence(), removed ones included.
     * @param attest_form $form The form, built and read by the page.
     * @return self
     */
    public static function unsigned(\stdClass $evidence, \context_user $context, array $pieces, attest_form $form): self {
        return new self($evidence, $context, $pieces, null, [], null, $form);
    }

    /**
     * The declaration signed: the history and the way out, no form (attestation_manager refuses a second one anyway).
     *
     * @param \stdClass $evidence A row of tool_vae_evidence.
     * @param \context_user $context The vaeiste's context.
     * @param \stdClass[] $pieces piece_manager::for_evidence(), removed ones included.
     * @param \stdClass $standing attestation_manager::active_attestation().
     * @param \stdClass[] $attestations attestation_manager::attestations(): the whole history.
     * @param string|null $recourse One of the RECOURSE_ constants, as the page read it from attestation_manager, or null.
     * @return self
     */
    public static function signed(
        \stdClass $evidence,
        \context_user $context,
        array $pieces,
        \stdClass $standing,
        array $attestations,
        ?string $recourse
    ): self {
        return new self($evidence, $context, $pieces, $standing, $attestations, $recourse, null);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/attest_page';
    }

    /**
     * The context of the template: plain texts; HTML only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // Drawn in the order the page drew them: what a piece asks of the page (its JavaScript) comes in the same order.
        $declaration = $output->render(new attest_declaration($this->evidence, $this->context));

        $pieces = false;
        if ($this->pieces) {
            $pieces = [
                'headinghtml' => $output->heading(get_string('attestpiecesheading', 'tool_vae'), 3),
                'intro' => get_string('attestpiecesintro', 'tool_vae'),
                'listhtml' => $output->render(new pieces($this->pieces, $this->context, $this->standing)),
            ];
        }

        $signed = false;
        if ($this->standing) {
            $signed = [
                'noticehtml' => $output->notification(get_string('errorattestexists', 'tool_vae'), notification::NOTIFY_INFO),
                'attestationshtml' => $output->render(new attestations($this->attestations)),
                'recoursehtml' => $this->recourse($output),
            ];
        }

        return [
            'declarationhtml' => $declaration,
            'pieces' => $pieces,
            'signed' => $signed,
            'formhtml' => $this->form === null ? false : $this->form->render(),
        ];
    }

    /**
     * The way out of a signature in force: a button of the core, a notification of the core, or false.
     *
     * @param renderer_base $output
     * @return string|false
     */
    private function recourse(renderer_base $output) {
        switch ($this->recourse) {
            case self::RECOURSE_WITHDRAW:
                return $output->single_button(
                    new \moodle_url('/admin/tool/vae/withdraw.php', ['attestationid' => $this->standing->id]),
                    get_string('withdraw', 'tool_vae'),
                    'get'
                );
            case self::RECOURSE_REQUEST:
                // The same recourse as the dossier offers: a manager who may not withdraw asks the signatory to.
                return $output->single_button(
                    new \moodle_url('/admin/tool/vae/withdrawrequest.php', [
                        'attestationid' => $this->standing->id,
                        'action' => 'request',
                        'sesskey' => sesskey(),
                    ]),
                    get_string('requestwithdrawal', 'tool_vae'),
                    'post'
                );
            case self::RECOURSE_GONE:
                // As on the dossier: the signatory is gone (deleted, or suspended), and the reader is sent to the one person
                // who can still withdraw.
                return $output->notification(get_string('requestwithdrawalnosignatory', 'tool_vae'), notification::NOTIFY_INFO);
            default:
                return false;
        }
    }
}
