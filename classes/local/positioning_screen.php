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
 * What the screens of the positionings of the structure read (T-90, lot 3): the records, who signed a line, the addresses.
 *
 * THE EVALUATIONS OF THE COMPETENCIES AND THE CERTIFICATIONS OF THE BLOCS, NEVER VALIDATIONS. NO HTML HERE ANY MORE (lot 7,
 * MMR-520 ticket 4): the section of the dossier, a line and the history of a rank are drawn by the pieces
 * tool_vae\output\positioning_section, positioning_line and positioning_history and their templates. What is left draws
 * nothing: the records a candidate declared on and the name of who wrote a line, which go to the database and so stay out of
 * classes/output, and the address of the page of a rank. What is read here is plain text, never HTML: the templates escape it.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class positioning_screen {
    /**
     * The codes of the records the candidate declared on, in order.
     *
     * @param int $candidateid
     * @return string[]
     */
    public static function codes(int $candidateid): array {
        global $DB;

        return $DB->get_fieldset_sql(
            'SELECT DISTINCT fichecode FROM {tool_vae_evidence} WHERE candidateid = :c ORDER BY fichecode ASC',
            ['c' => $candidateid]
        );
    }

    /**
     * Who wrote a line and when, in words; no identifier.
     *
     * @param \stdClass $row
     * @return string Plain text, not escaped.
     */
    public static function signature(\stdClass $row): string {
        $when = userdate((int) $row->timecreated, get_string('strftimedatetime', 'langconfig'));
        if (!empty($row->importedat)) {
            return $when . ' - ' . get_string('positioningrestored', 'tool_vae');
        }
        $who = '';
        if (!empty($row->authorid)) {
            $author = \core_user::get_user((int) $row->authorid);
            $who = $author && empty($author->deleted) ? fullname($author) : get_string('positioningauthorgone', 'tool_vae');
        } else {
            $who = get_string('positioningauthorgone', 'tool_vae');
        }

        return $who . ', ' . $when;
    }

    /**
     * The address of the page of a rank.
     *
     * @param string $page evaluate or certify.
     * @param int $candidateid
     * @param string $code
     * @param string $rank
     * @return \moodle_url
     */
    public static function url(string $page, int $candidateid, string $code, string $rank): \moodle_url {
        return new \moodle_url('/admin/tool/vae/' . $page . '.php', [
            'userid' => $candidateid, 'fichecode' => $code, 'rank' => $rank,
        ]);
    }
}
