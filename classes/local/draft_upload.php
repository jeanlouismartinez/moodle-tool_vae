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
 * A file given through the file picker of the core: read from the draft area of the person, copied out, then taken away.
 *
 * THE WAY OF THE CORE (review MMR-520). The three uploads of the plugin (a piece of a declaration, a piece of a work situation,
 * the export of France compétences) use the filepicker element of the forms. The upload repository of the core receives the
 * file, cleans its name (PARAM_FILE), scans it with the antivirus of the site and puts it in a draft area of the person who
 * chose it (component user, area draft, in their user context); the form then sends only the number of that area. Nothing of
 * the plugin reads the request itself any more.
 *
 * WHAT THE CORE CHECKS, AND WHAT IT DOES NOT. The size and the types the browser asks the repository to check are sent by the
 * browser itself; the element checks again, on the server, the extension of the name (validateSubmitValue()) and takes out,
 * without a word, a file over its limit when the form gives its data (exportValue()). The TYPE READ ON THE CONTENT, the size
 * held to the settings of the plugin, the quota, the macros and the antivirus refusal without an antivirus stay the checks of
 * piece_manager::check_file(), and the zip is read by fiche_archive: they are given the copy made here.
 *
 * WHY A COPY, AND WHERE. piece_manager and fiche_archive read a path (size, digest, a PDF read by windows, ZipArchive); the
 * content of a stored file has no path that every file system of Moodle gives. The copy goes in the directory of the request,
 * which the core removes at the end of the request whatever happens, and discard() removes it at once.
 *
 * NOTHING IS LEFT BEHIND. Once the page has taken the file, discard() empties the draft area: the name the person gave the file
 * is in the row of the draft file, and the plugin keeps no name. A draft area the person never sends is cleared by the cron of
 * the core after four days (file_storage::cron()).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class draft_upload {
    /**
     * The file of a draft area of the CURRENT user: the newest one, as the filepicker element keeps it.
     *
     * The area is read in the user context of the person signed in, as the forms of the core read it: a number of an area that
     * belongs to someone else gives nothing.
     *
     * @param int $itemid The number of the draft area the form sent.
     * @return \stored_file|null Null when the area holds no file.
     */
    public static function file(int $itemid): ?\stored_file {
        if ($itemid <= 0) {
            return null;
        }
        $files = get_file_storage()->get_area_files(self::context()->id, 'user', 'draft', $itemid, 'id DESC', false);

        return $files ? reset($files) : null;
    }

    /**
     * Whether the draft area holds a file larger than this.
     *
     * Read BEFORE the form gives its data: the filepicker element takes out, without a word, a file over its own limit then,
     * and the page would only say that no file was chosen.
     *
     * @param int $itemid
     * @param int $maxbytes
     * @return bool
     */
    public static function larger_than(int $itemid, int $maxbytes): bool {
        $file = self::file($itemid);

        return $file !== null && $file->get_filesize() > $maxbytes;
    }

    /**
     * Copy the content of a file of the draft area to a file of the request.
     *
     * @param \stored_file $file
     * @return \stdClass path (the copy, under a name of its own), name (the name the area holds, as the core cleaned it: never
     *         to be kept), size.
     * @throws \moodle_exception errorpiecefile when the content cannot be copied.
     */
    public static function copy(\stored_file $file): \stdClass {
        $path = make_request_directory() . '/upload';
        if (!$file->copy_content_to($path)) {
            @unlink($path);
            throw new \moodle_exception('errorpiecefile', 'tool_vae');
        }

        return (object) ['path' => $path, 'name' => (string) $file->get_filename(), 'size' => (int) $file->get_filesize()];
    }

    /**
     * The file of a piece, refused in the words the pages of the pieces always used.
     *
     * @param int $itemid
     * @param int $maxbytes The limit said on the page.
     * @return \stdClass As copy() gives it.
     * @throws \moodle_exception errorpiecenofile, errorpiecetoolarge, errorpiecefile
     */
    public static function piece(int $itemid, int $maxbytes): \stdClass {
        $file = self::file($itemid);
        if ($file === null) {
            throw new \moodle_exception('errorpiecenofile', 'tool_vae');
        }
        if ($file->get_filesize() > $maxbytes) {
            throw new \moodle_exception('errorpiecetoolarge', 'tool_vae', '', display_size($maxbytes));
        }

        return self::copy($file);
    }

    /**
     * Empty the draft area of the current user, and remove the copy.
     *
     * @param int $itemid
     * @param \stdClass|null $copy What copy() gave, when it gave something.
     */
    public static function discard(int $itemid, ?\stdClass $copy = null): void {
        if ($copy !== null && is_file($copy->path)) {
            unlink($copy->path);
        }
        if ($itemid > 0) {
            get_file_storage()->delete_area_files(self::context()->id, 'user', 'draft', $itemid);
        }
    }

    /**
     * The user context of the person signed in, where the core keeps their draft areas.
     *
     * @return \context_user
     */
    private static function context(): \context_user {
        global $USER;

        return \context_user::instance((int) $USER->id);
    }
}
