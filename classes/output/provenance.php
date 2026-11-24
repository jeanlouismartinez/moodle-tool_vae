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
 * The provenance line of a certification record: attribution, licence, publication date (lot 7, MMR-520 ticket 4).
 *
 * THE PROVENANCE LINE IS AN OBLIGATION, NOT A COURTESY. The Licence Ouverte 2.0 requires the source, the attribution and the
 * publication date to be stated wherever the data is presented, and forbids misleading anyone about them: the administration
 * screen, the coverage of the dossier and the view of the organisation, which print the published labels. One place for it.
 * A record whose first import failed holds no published version yet, and a date of zero would announce 1 January 1970 as the
 * day France compétences published it: it is said to be pending. Was presenter::provenance().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provenance implements named_templatable, renderable {
    /** @var \stdClass A row of tool_vae_source. */
    private \stdClass $source;

    /**
     * The line of a record.
     *
     * @param \stdClass $source A row of tool_vae_source: attribution, licence, sourceupdated.
     */
    public function __construct(\stdClass $source) {
        $this->source = $source;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/provenance';
    }

    /**
     * The context of the template: the sentence, as plain text.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $source = $this->source;

        return [
            'text' => empty($source->sourceupdated)
                ? get_string('sourceprovenancepending', 'tool_vae', (object) [
                    'attribution' => $source->attribution,
                    'licence' => $source->licence,
                ])
                : get_string('sourceprovenance', 'tool_vae', (object) [
                    'attribution' => $source->attribution,
                    'licence' => $source->licence,
                    'updated' => display_date::format((int) $source->sourceupdated, get_string('strftimedate', 'langconfig')),
                ]),
        ];
    }
}
