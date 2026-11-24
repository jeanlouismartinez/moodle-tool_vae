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
use tool_vae\local\spiece_manager;

/**
 * The pieces of a situation (lot 1b): one line per link that stands, with the part concerned; the piece opened by whoever
 * may (the whole address, its host and the link, or the file); for the vaeiste who may act, the buttons of the core to detach
 * the piece from this situation and, while it stands, to remove it everywhere.
 *
 * Was situation_screen::pieces() and piece_access() (lot 7, MMR-520 ticket 4, wave 4a). The page gave a function that drew
 * the buttons; it gives the address of the page instead, or null for a reader (the form of wave 2a for a button).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class situation_pieces implements named_templatable, renderable {
    /** @var \stdClass[] The links, as spiece_manager::for_situation() gives them, each with `piece`. */
    private array $links;

    /** @var \context_user The vaeiste's context. */
    private \context_user $context;

    /** @var \moodle_url|null The page the buttons are sent to; null draws none. */
    private ?\moodle_url $url;

    /**
     * The pieces.
     *
     * @param \stdClass[] $links As spiece_manager::for_situation() gives them, each with `piece`.
     * @param \context_user $context The vaeiste's context.
     * @param \moodle_url|null $url The page of the situation, to which the vaeiste who may act sends "detach" and "remove";
     *        null for a reader: no button.
     */
    public function __construct(array $links, \context_user $context, ?\moodle_url $url) {
        $this->links = $links;
        $this->context = $context;
        $this->url = $url;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/situation_pieces';
    }

    /**
     * The context of the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $date = get_string('strftimedatetime', 'langconfig');
        $items = [];
        foreach ($this->links as $link) {
            $piece = $link->piece;
            $mayopen = spiece_manager::may_open($piece);
            $details = [];
            if ($piece->kind === piece_manager::KIND_FILE && $piece->timeremoved === null) {
                $details[] = strtoupper((string) $piece->ext) . ', ' . display_size((int) $piece->filesize);
            }
            $details[] = userdate((int) $piece->timecreated, $date);
            $access = $piece->timeremoved === null && $mayopen;
            $items[] = [
                'nature' => get_string('piecenature:' . $piece->nature, 'tool_vae'),
                'title' => (string) $piece->title !== '' && $mayopen ? ['text' => (string) $piece->title] : null,
                'part' => (string) $link->part !== ''
                    ? ['label' => get_string('spiecepart', 'tool_vae'), 'text' => (string) $link->part] : null,
                'details' => implode(' - ', $details),
                'removed' => $piece->timeremoved !== null ? ['text' => get_string('spieceremoved', 'tool_vae')] : null,
                'link' => $access && $piece->kind === piece_manager::KIND_LINK ? [
                    // The whole address, as text, and its host as the network reads it; the click is the reader's.
                    'url' => (string) $piece->url,
                    'host' => piece_manager::display_host((string) $piece->url),
                    'open' => get_string('piecelinkopen', 'tool_vae'),
                ] : null,
                'file' => $access && $piece->kind !== piece_manager::KIND_LINK ? [
                    'url' => \moodle_url::make_pluginfile_url(
                        $this->context->id,
                        'tool_vae',
                        spiece_manager::FILEAREA,
                        (int) $piece->id,
                        '/',
                        $piece->uuid . '.' . $piece->ext,
                        true
                    )->out(false),
                    'open' => get_string('pieceopenfile', 'tool_vae'),
                ] : null,
                'actionshtml' => $this->actions($output, $link),
            ];
        }

        return [
            'heading' => get_string('spiecesheading', 'tool_vae'),
            'none' => $this->links ? null : ['text' => get_string('piecenone', 'tool_vae')],
            'items' => $items,
        ];
    }

    /**
     * What the vaeiste may do with a link: detach the piece from this situation; remove it everywhere while it stands.
     *
     * @param renderer_base $output
     * @param \stdClass $link
     * @return string The buttons of the core, drawn by its renderer; '' for a reader.
     */
    private function actions(renderer_base $output, \stdClass $link): string {
        if ($this->url === null) {
            return '';
        }
        $out = $output->single_button(
            new \moodle_url($this->url, ['action' => 'detach', 'piece' => (int) $link->pieceid]),
            get_string('spiecedetach', 'tool_vae'),
            'post'
        );
        if ($link->piece->timeremoved === null) {
            $out .= $output->single_button(
                new \moodle_url($this->url, ['action' => 'remove', 'piece' => (int) $link->pieceid]),
                get_string('pieceremove', 'tool_vae'),
                'post'
            );
        }

        return $out;
    }
}
