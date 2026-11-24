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
 * What the page organisation.php reads to draw itself, and writes no HTML of (T-90).
 *
 * The HTML of the page is drawn by tool_vae\output\organisation_page and its pieces (organisation_pathway,
 * organisation_followed, organisation_site, organisation_cell, organisation_notice, organisation_guide_link) since lot 7
 * (MMR-520 ticket 4, wave 4b). What stays here reads and decides nothing about who may read: the tabs the page gives to the
 * core, and whether a person holds a piece on their pathway.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class organisation_screen {
    /**
     * The tabs of the page, in the order given: the keys the reader may open (organisation::tabs()).
     *
     * Each tab keeps its link when it is the open one: the core draws a selected tab without an address unless it is told
     * to, and a reader on one tab would have no link to follow back to it.
     *
     * @param string[] $keys organisation::TAB_* keys.
     * @return \tabobject[]
     */
    public static function tabs(array $keys): array {
        $tabs = [];
        foreach ($keys as $key) {
            $tabs[] = new \tabobject(
                $key,
                new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => $key]),
                get_string('orgtab' . $key, 'tool_vae'),
                '',
                true
            );
        }

        return $tabs;
    }

    /**
     * Whether the person holds a piece on any line of their pathway.
     *
     * @param \stdClass $pathway What organisation::for_candidate() gives.
     * @return bool
     */
    public static function holds_pieces(\stdClass $pathway): bool {
        foreach ($pathway->declared as $record) {
            foreach ($record->competencies as $row) {
                if ($row->pieces > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
