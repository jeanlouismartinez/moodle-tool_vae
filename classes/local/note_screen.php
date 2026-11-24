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

namespace tool_vae\local;

/**
 * The readings of the annotations (lot 1b) that the pieces of classes/output/ draw: the name of the author.
 *
 * The HTML of the annotations - the block of a target, the annotations of the deleted situations, the lines - is drawn by
 * the pieces tool_vae\output\notes_block, notes_orphans and note_lines (lot 7, MMR-520 ticket 4, wave 4a).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class note_screen {
    /**
     * The name of the author, as text: the account's name; "author of the archive, not named" for a restored line, which
     * names no account of this site (D-1b-L); "author erased" when the line names nobody at all, or an account that is gone.
     *
     * @param \stdClass $row
     * @return string
     */
    public static function author(\stdClass $row): string {
        if ($row->authorid === null) {
            return get_string($row->authoruuid === null ? 'noteauthorgone' : 'noteauthorarchive', 'tool_vae');
        }
        $author = \core_user::get_user((int) $row->authorid);

        return $author && empty($author->deleted) ? fullname($author) : get_string('noteauthorgone', 'tool_vae');
    }
}
