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
use tool_vae\local\display_date;

/**
 * What leaves with the row of a record that is removed, as a list, in the form that confirms the removal (lot 7, MMR-520
 * ticket 4, wave 4c).
 *
 * Was remove_record_form::leaves(), same parameter. Everything is written as text: the titles and the addresses were typed
 * by an administrator, the licence and the attribution come from a file, and none of it is markup. Every kind is listed,
 * with its count, so that an absence reads as an absence.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class remove_record_leaves implements named_templatable, renderable {
    /** @var \stdClass What record_remover::inventory() gives. */
    private \stdClass $inventory;

    /**
     * The list of what leaves.
     *
     * @param \stdClass $inventory What record_remover::inventory() gives.
     */
    public function __construct(\stdClass $inventory) {
        $this->inventory = $inventory;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/remove_record_leaves';
    }

    /**
     * The context of the template: the sentence that opens the list, and each line, plain; the addresses under their line.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $inventory = $this->inventory;
        $source = $inventory->source;
        $none = get_string('removerecordnone', 'tool_vae');
        $nodate = get_string('removerecordnodate', 'tool_vae');
        $dateformat = get_string('strftimedate', 'langconfig');
        $date = fn($time): string => empty($time) ? $nodate : display_date::format((int) $time, $dateformat);

        $addresses = [];
        foreach ($inventory->refs as $ref) {
            $addresses[] = ['title' => (string) $ref->title, 'url' => (string) $ref->url];
        }

        $line = fn(string $text): array => ['text' => $text, 'refs' => null];
        $items = [
            $line(get_string('removerecordlicence', 'tool_vae', (object) [
                'licence' => $source->licence,
                'attribution' => $source->attribution,
            ])),
            $line(get_string('removerecorddates', 'tool_vae', (object) [
                'updated' => $date($source->sourceupdated),
                'lastimport' => $date($source->lastimport),
            ])),
            [
                'text' => get_string('removerecordrefs', 'tool_vae', count($inventory->refs)),
                'refs' => $addresses === [] ? null : ['addresses' => $addresses],
            ],
            $line(get_string('removerecordreferents', 'tool_vae', (int) $inventory->referents)),
            $line(get_string('removerecordpassages', 'tool_vae', count($inventory->passageids))),
            $line(get_string('removerecordmarks', 'tool_vae', (int) $inventory->marks)),
            $line(get_string(
                'removerecordsuccessor',
                'tool_vae',
                $inventory->successor === '' ? get_string('supersessionnosuccessor', 'tool_vae') : $inventory->successor
            )),
            $line(get_string(
                'removerecordreplaced',
                'tool_vae',
                $inventory->replaced ? implode(', ', $inventory->replaced) : $none
            )),
        ];

        return ['leaves' => get_string('removerecordleaves', 'tool_vae'), 'items' => $items];
    }
}
