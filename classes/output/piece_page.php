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
use tool_vae\form\piece_file_form;
use tool_vae\form\piece_link_form;

/**
 * The page "Pièces justificatives" (piece.php), below its heading (T-13, lots 1 and 2; lot 7, MMR-520 ticket 4).
 *
 * What a piece is and its limits, the declaration with the warning of its anchor, the list of its pieces (the same list for
 * the vaeiste, for the manager who attests and for whoever only reads; only the vaeiste gets a button to remove, in the
 * same place as always), then the two forms of the core while the site takes pieces, or the notice to the vaeiste that it
 * does not, and the way back to the dossier. The page checks the rights, reads the declaration, its pieces and the
 * signature, handles the forms and redirects before; this class places them for the template tool_vae/piece_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class piece_page implements named_templatable, renderable {
    /** @var \stdClass A row of tool_vae_evidence. */
    private \stdClass $evidence;

    /** @var \context_user The vaeiste's context. */
    private \context_user $context;

    /** @var \stdClass[] Rows of tool_vae_piece, removed ones included. */
    private array $pieces;

    /** @var \stdClass|null The attestation in force, if any. */
    private ?\stdClass $standing;

    /** @var \moodle_url|null The address of the page, for the vaeiste's buttons to remove; null for anybody else. */
    private ?\moodle_url $remove;

    /** @var piece_file_form|null The form to attach a file, while the site takes pieces; drawn once. */
    private ?piece_file_form $fileform;

    /** @var piece_link_form|null The form to give a link, while the site takes pieces; drawn once. */
    private ?piece_link_form $linkform;

    /** @var bool Whether the vaeiste is told that the site takes no piece. */
    private bool $closed;

    /** @var \moodle_url The address of the dossier. */
    private \moodle_url $dossier;

    /**
     * The page.
     *
     * @param \stdClass $evidence A row of tool_vae_evidence.
     * @param \context_user $context The vaeiste's context.
     * @param \stdClass[] $pieces piece_manager::for_evidence(), removed ones included.
     * @param \stdClass|null $standing attestation_manager::active_attestation().
     * @param \moodle_url|null $remove The address of the page, for the vaeiste; null for anybody else.
     * @param piece_file_form|null $fileform The form to attach a file, while the site takes pieces.
     * @param piece_link_form|null $linkform The form to give a link, while the site takes pieces.
     * @param bool $closed Whether the vaeiste is told that the site takes no piece.
     * @param \moodle_url $dossier The address of the dossier.
     */
    public function __construct(
        \stdClass $evidence,
        \context_user $context,
        array $pieces,
        ?\stdClass $standing,
        ?\moodle_url $remove,
        ?piece_file_form $fileform,
        ?piece_link_form $linkform,
        bool $closed,
        \moodle_url $dossier
    ) {
        $this->evidence = $evidence;
        $this->context = $context;
        $this->pieces = $pieces;
        $this->standing = $standing;
        $this->remove = $remove;
        $this->fileform = $fileform;
        $this->linkform = $linkform;
        $this->closed = $closed;
        $this->dossier = $dossier;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/piece_page';
    }

    /**
     * The context of the template: plain texts and the address of the dossier; HTML only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // Drawn in the order the page drew them: what a piece asks of the page (its JavaScript) comes in the same order.
        $declaration = $output->render(new attest_declaration($this->evidence, $this->context));
        $pieces = $output->render(new pieces($this->pieces, $this->context, $this->standing, $this->remove));

        return [
            'intro' => get_string('pieceintro', 'tool_vae'),
            'limits' => get_string('piecelimits', 'tool_vae'),
            'declarationhtml' => $declaration,
            'pieceshtml' => $pieces,
            'filehtml' => $this->fileform === null ? false : $this->fileform->render(),
            'linkhtml' => $this->linkform === null ? false : $this->linkform->render(),
            'closedhtml' => $this->closed
                ? $output->notification(get_string('piececlosed', 'tool_vae'), notification::NOTIFY_INFO)
                : false,
            'back' => ['url' => $this->dossier->out(false), 'label' => get_string('piecebacktodossier', 'tool_vae')],
        ];
    }
}
