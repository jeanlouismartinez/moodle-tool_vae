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
use tool_vae\local\selfpos_screen;

/**
 * The records the vaeiste may position themselves on (lot 1a), and the ones that are hidden, said why.
 *
 * Was selfpos_screen::records() (lot 7, MMR-520 ticket 4).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class selfpos_records implements named_templatable, renderable {
    /** @var int The vaeiste. */
    private int $candidateid;

    /** @var \stdClass[] The rows of tool_vae_source that are offered, by code. */
    private array $sources;

    /** @var string[] Code => the message of a record that is hidden. */
    private array $hidden;

    /**
     * The records.
     *
     * @param int $candidateid
     * @param \stdClass[] $sources The rows of tool_vae_source that are offered, by code.
     * @param string[] $hidden Code => the message of a record that is hidden, as hidden_frameworks::message() gives it (its
     *        title already escaped).
     */
    public function __construct(int $candidateid, array $sources, array $hidden) {
        $this->candidateid = $candidateid;
        $this->sources = $sources;
        $this->hidden = $hidden;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/selfpos_records';
    }

    /**
     * The context of the template: the sentences, the hidden records, then the list or the sentence that there is none.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $hidden = [];
        foreach ($this->hidden as $message) {
            // Already escaped by hidden_frameworks::message(): a second pass would show entities in the title.
            $hidden[] = ['messagehtml' => $message];
        }
        $items = [];
        foreach ($this->sources as $source) {
            $items[] = [
                'url' => selfpos_screen::url($this->candidateid, (string) $source->fichecode)->out(false),
                'code' => (string) $source->fichecode,
            ];
        }

        return [
            'disclaimerhtml' => $output->render(new selfpos_disclaimer($this->candidateid)),
            'hidden' => $hidden,
            'none' => $this->sources ? null : ['text' => get_string('selfposnorecord', 'tool_vae')],
            'list' => $this->sources ? ['choose' => get_string('selfposchoose', 'tool_vae'), 'items' => $items] : null,
        ];
    }
}
