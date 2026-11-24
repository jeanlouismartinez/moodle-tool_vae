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
 * One declaration of the page dossier.php, in its block (lot 7, MMR-520 ticket 4, wave 4b).
 *
 * In the order the page drew it: the warning of an anchor the core no longer holds, the state, the notes of a carry-over,
 * the declaration, the attestations under their heading, the chronology, then what the reader may do on the attestation
 * (one way at most, chosen by the page: answer a request, withdraw, be told the signatory is gone or a request waits, ask
 * for a withdrawal, attest), the carry-over, the link of the questions and the link of the pieces with those the signature
 * does not cover. The page builds each piece with its arguments of before, chooses the way and gives the addresses: every
 * condition and every read stays in the page; the words are the class's. Was the body of the loop of dossier.php.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dossier_claim implements named_templatable, renderable {
    /** @var renderable[] The pieces drawn before the ways, in their order: warning, state, notes, declaration. */
    private array $head;

    /** @var renderable[] The attestations and the chronology. */
    private array $tail;

    /** @var array|null The way on the attestation, made by yours(), withdraw(), nosignatory(), pending(), request(), attest(). */
    private ?array $act;

    /** @var \moodle_url|null The carry-over, or null. */
    private ?\moodle_url $carry;

    /** @var \moodle_url The questions on the declaration. */
    private \moodle_url $precision;

    /** @var int The questions still waiting. */
    private int $waiting;

    /** @var \moodle_url|null The pieces, or null when their link is not drawn. */
    private ?\moodle_url $pieces;

    /** @var int The pieces standing. */
    private int $count;

    /** @var int The pieces the signature standing does not cover. */
    private int $late;

    /**
     * The block of a declaration.
     *
     * @param anchor_warning $warning
     * @param state_label $state Drawn in a paragraph.
     * @param carry_notes $notes
     * @param declaration $declaration
     * @param attestations $attestations
     * @param chronology $chronology
     * @param array|null $act The way on the attestation, or null for none.
     * @param \moodle_url|null $carry The address of the carry-over, or null when it is not offered.
     * @param \moodle_url $precision The address of the questions.
     * @param int $waiting How many questions wait.
     * @param \moodle_url|null $pieces The address of the pieces, or null when the link is not drawn.
     * @param int $count How many pieces stand.
     * @param int $late How many the signature standing does not cover.
     */
    public function __construct(
        anchor_warning $warning,
        state_label $state,
        carry_notes $notes,
        declaration $declaration,
        attestations $attestations,
        chronology $chronology,
        ?array $act,
        ?\moodle_url $carry,
        \moodle_url $precision,
        int $waiting,
        ?\moodle_url $pieces,
        int $count,
        int $late
    ) {
        $this->head = [$warning, $state, $notes, $declaration];
        $this->tail = [$attestations, $chronology];
        $this->act = $act;
        $this->carry = $carry;
        $this->precision = $precision;
        $this->waiting = $waiting;
        $this->pieces = $pieces;
        $this->count = $count;
        $this->late = $late;
    }

    /**
     * A colleague asked this signatory to withdraw: the warning and two buttons, accept or refuse.
     *
     * @param \moodle_url $accept The withdrawal (GET).
     * @param \moodle_url $refuse The refusal (POST, with the session key in the address as before).
     * @return array
     */
    public static function yours(\moodle_url $accept, \moodle_url $refuse): array {
        return ['kind' => 'yours', 'accept' => $accept, 'refuse' => $refuse];
    }

    /**
     * The button that withdraws the attestation.
     *
     * @param \moodle_url $url
     * @return array
     */
    public static function withdraw(\moodle_url $url): array {
        return ['kind' => 'withdraw', 'url' => $url];
    }

    /**
     * The sentence that sends the reader to the administrator: the signatory asked is gone.
     *
     * @return array
     */
    public static function nosignatory(): array {
        return ['kind' => 'nosignatory'];
    }

    /**
     * The sentence that says a request of withdrawal waits.
     *
     * @return array
     */
    public static function pending(): array {
        return ['kind' => 'pending'];
    }

    /**
     * The button that asks the signatory to withdraw (POST).
     *
     * @param \moodle_url $url
     * @return array
     */
    public static function request(\moodle_url $url): array {
        return ['kind' => 'request', 'url' => $url];
    }

    /**
     * The button that attests.
     *
     * @param \moodle_url $url
     * @param string $label Its words, as presenter::attest_label() gives them.
     * @return array
     */
    public static function attest(\moodle_url $url, string $label): array {
        return ['kind' => 'attest', 'url' => $url, 'label' => $label];
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/dossier_claim';
    }

    /**
     * The context of the template: each piece drawn by its renderer, the ways drawn by the core, the two links.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        [$warning, $state, $notes, $declaration] = $this->head;
        [$attestations, $chronology] = $this->tail;
        $acts = [];
        foreach ($this->acts($output) as $html) {
            $acts[] = ['acthtml' => $html];
        }
        if ($this->carry !== null) {
            $acts[] = ['acthtml' => $output->single_button($this->carry, get_string('carrylink', 'tool_vae'), 'get')];
        }
        $waiting = $this->waiting;
        $count = $this->count;

        return [
            'warninghtml' => $output->render($warning),
            'statehtml' => $output->render($state),
            'noteshtml' => $output->render($notes),
            'declarationhtml' => $output->render($declaration),
            'heading' => get_string('attestation', 'tool_vae'),
            'attestationshtml' => $output->render($attestations),
            'chronologyhtml' => $output->render($chronology),
            'acts' => $acts,
            'precision' => [
                'url' => $this->precision->out(false),
                'label' => get_string($waiting ? 'precisionlinkwaiting' : 'precisionlink', 'tool_vae', $waiting),
            ],
            'pieces' => $this->pieces === null ? null : [
                'url' => $this->pieces->out(false),
                'label' => get_string($count ? 'pieceslinkcount' : 'pieceslink', 'tool_vae', $count),
                'late' => $this->late ? ['label' => get_string('pieceslate', 'tool_vae', $this->late)] : null,
            ],
        ];
    }

    /**
     * The way on the attestation, drawn by the core: notifications and buttons, in their order.
     *
     * @param renderer_base $output
     * @return string[] HTML.
     */
    private function acts(renderer_base $output): array {
        $act = $this->act;
        if ($act === null) {
            return [];
        }
        switch ($act['kind']) {
            case 'yours':
                return [
                    $output->notification(
                        get_string('requestwithdrawalyours', 'tool_vae'),
                        \core\output\notification::NOTIFY_WARNING
                    ),
                    $output->single_button($act['accept'], get_string('acceptwithdrawal', 'tool_vae'), 'get'),
                    $output->single_button($act['refuse'], get_string('refusewithdrawal', 'tool_vae'), 'post'),
                ];
            case 'withdraw':
                return [$output->single_button($act['url'], get_string('withdraw', 'tool_vae'), 'get')];
            case 'nosignatory':
                return [$output->notification(
                    get_string('requestwithdrawalnosignatory', 'tool_vae'),
                    \core\output\notification::NOTIFY_INFO
                )];
            case 'pending':
                return [$output->notification(
                    get_string('requestwithdrawalpending', 'tool_vae'),
                    \core\output\notification::NOTIFY_INFO
                )];
            case 'request':
                return [$output->single_button($act['url'], get_string('requestwithdrawal', 'tool_vae'), 'post')];
            case 'attest':
                return [$output->single_button($act['url'], $act['label'], 'get')];
        }

        throw new \coding_exception('tool_vae\output\dossier_claim does not know the way ' . $act['kind']);
    }
}
