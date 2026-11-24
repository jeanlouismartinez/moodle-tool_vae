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
use tool_vae\local\piece_manager;
use tool_vae\local\presenter;

/**
 * The supporting pieces of a declaration, as a list (T-13, lot 2; lot 7, MMR-520 ticket 4).
 *
 * One list for the vaeiste, for the manager who attests and for whoever only reads the dossier. Everything free is escaped;
 * an address is shown whole, as text, and opened only by a click that leaves the platform. A piece the reader may not open
 * is listed and not offered (its title, a free field, is not shown either). Against the signature standing on the
 * declaration: a piece the signature did not cover is marked as added after it, and a piece it covered that was removed
 * since is said so. For a reader who is not the vaeiste, whether they opened a piece is said - opening is not reading, and
 * the line says so once. The vaeiste is given, on the page of the pieces, a button to remove each piece that is there. What
 * is read of the reader and of the signature comes from presenter::pieces_reading(). Was presenter::pieces().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pieces implements named_templatable, renderable {
    /** @var \stdClass[] Rows of tool_vae_piece, removed ones included. */
    private array $pieces;

    /** @var \context_user The vaeiste's context. */
    private \context_user $context;

    /** @var \stdClass|null The attestation in force, if any. */
    private ?\stdClass $standing;

    /** @var \moodle_url|null The page that removes a piece (piece.php), for the vaeiste; none for anybody else. */
    private ?\moodle_url $remove;

    /**
     * The list.
     *
     * @param \stdClass[] $pieces Rows of tool_vae_piece, removed ones included.
     * @param \context_user $context The vaeiste's context.
     * @param \stdClass|null $standing The attestation in force, if any.
     * @param \moodle_url|null $remove The address of piece.php, for the vaeiste: each piece there gets a button that posts
     *        to it with remove=<its id>. None for anybody else.
     */
    public function __construct(
        array $pieces,
        \context_user $context,
        ?\stdClass $standing = null,
        ?\moodle_url $remove = null
    ) {
        $this->pieces = $pieces;
        $this->context = $context;
        $this->standing = $standing;
        $this->remove = $remove;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/pieces';
    }

    /**
     * The context of the template: texts and addresses in clear; HTML only in actionhtml (a button of the core).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        if (!$this->pieces) {
            return ['none' => get_string('piecenone', 'tool_vae'), 'haspieces' => false, 'pieces' => [], 'count' => false];
        }

        $reading = presenter::pieces_reading($this->pieces, $this->context, $this->standing);
        $date = get_string('strftimedatetime', 'langconfig');

        $items = [];
        foreach ($this->pieces as $piece) {
            $details = [];
            if ($piece->kind === piece_manager::KIND_FILE) {
                $details[] = strtoupper((string) $piece->ext) . ', ' . display_size((int) $piece->filesize);
                $details[] = get_string('piecedigest', 'tool_vae') . ' ' . $piece->sha256;
            }
            $details[] = userdate($piece->timecreated, $date);

            $item = [
                'nature' => get_string('piecenature:' . $piece->nature, 'tool_vae'),
                // The title is a free field: shown to whoever may open the piece, not to a reader who may only see it exists.
                'title' => $piece->title !== '' && piece_manager::may_open($piece) ? ['text' => (string) $piece->title] : false,
                'details' => implode(' - ', $details),
                'removed' => false,
                'active' => false,
            ];
            if ($piece->timeremoved !== null) {
                $item['removed'] = [
                    'text' => get_string('pieceremovedon', 'tool_vae', userdate($piece->timeremoved, $date)),
                    'after' => $reading->covered !== null && array_key_exists((int) $piece->id, $reading->covered)
                        ? ['text' => get_string('pieceremovedaftersignature', 'tool_vae')]
                        : false,
                ];
            } else {
                $item['active'] = [
                    'after' => $reading->covered !== null && !array_key_exists((int) $piece->id, $reading->covered)
                        ? ['text' => get_string(
                            'piecesaftersignature',
                            'tool_vae',
                            userdate((int) $this->standing->attestedat, $date)
                        )]
                        : false,
                ] + $this->access($piece, $reading->reader, $reading->opened) + [
                    'actionhtml' => $this->remove === null ? false : $output->single_button(
                        new \moodle_url($this->remove, ['remove' => (int) $piece->id]),
                        get_string('pieceremove', 'tool_vae'),
                        'post'
                    ),
                ];
            }
            $items[] = $item;
        }

        $count = false;
        if ($reading->reader) {
            $active = array_filter($this->pieces, fn($piece) => $piece->timeremoved === null
                && $piece->kind === piece_manager::KIND_FILE && piece_manager::may_open($piece));
            $count = ['text' => get_string('piecesopenedcount', 'tool_vae', (object) [
                'opened' => count(array_filter($active, fn($piece) => isset($reading->opened[(int) $piece->id]))),
                'total' => count($active),
            ])];
        }

        return ['none' => false, 'haspieces' => true, 'pieces' => $items, 'count' => $count];
    }

    /**
     * How a reader gets at a piece: the link or the file to open, or the word that they may not.
     *
     * @param \stdClass $piece
     * @param bool $reader Whether the reader is somebody else than the vaeiste.
     * @param bool[] $opened Piece id => true for those this reader opened.
     * @return array listonly, link, file and opened: each false or what the template draws.
     */
    private function access(\stdClass $piece, bool $reader, array $opened): array {
        $access = ['listonly' => false, 'link' => false, 'file' => false, 'opened' => false];
        if (!piece_manager::may_open($piece)) {
            $access['listonly'] = ['text' => get_string('piecelistonly', 'tool_vae')];

            return $access;
        }

        if ($piece->kind === piece_manager::KIND_LINK) {
            // The whole address, as text, and its host as the network reads it; the click is the reader's.
            $access['link'] = [
                'url' => (string) $piece->url,
                'host' => piece_manager::display_host($piece->url),
                'open' => get_string('piecelinkopen', 'tool_vae'),
            ];
        } else {
            $access['file'] = [
                'url' => \moodle_url::make_pluginfile_url(
                    $this->context->id,
                    'tool_vae',
                    piece_manager::FILEAREA,
                    (int) $piece->id,
                    '/',
                    $piece->uuid . '.' . $piece->ext,
                    true
                )->out(false),
                'open' => get_string('pieceopenfile', 'tool_vae'),
            ];
        }
        // A link is not fetched by the server: its opening is not known, and is not claimed.
        if ($reader && $piece->kind === piece_manager::KIND_FILE) {
            $access['opened'] = [
                'text' => get_string(isset($opened[(int) $piece->id]) ? 'pieceopenedyes' : 'pieceopenedno', 'tool_vae'),
            ];
        }

        return $access;
    }
}
