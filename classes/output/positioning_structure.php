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
use tool_vae\local\structure_archive;

/**
 * The archive of the structure in the dossier: the one in place, for whoever may read it, and the button that produces a new
 * one, for whoever may produce it. A document of its own, apart from the dossiers of the vaeiste.
 *
 * Was the private positioning_screen::structure_block() (lot 7, MMR-520 ticket 4). Nothing when the reader may do neither:
 * drawn() says so, for the section that holds it.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class positioning_structure implements named_templatable, renderable {
    /** @var int The candidate. */
    private int $candidateid;

    /**
     * The block of a candidate.
     *
     * @param int $candidateid
     */
    public function __construct(int $candidateid) {
        $this->candidateid = $candidateid;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/positioning_structure';
    }

    /**
     * Whether the block draws anything for the current reader.
     *
     * @return bool
     */
    public function drawn(): bool {
        $parts = $this->parts();

        return $parts['current'] !== null || $parts['issue'] !== null;
    }

    /**
     * The context of the template: the archive in place and the way to download it, the way to produce a new one.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return $this->parts();
    }

    /**
     * What the reader may see of the archive and do with it.
     *
     * @return array current and issue, each an object or null.
     */
    private function parts(): array {
        $current = structure_archive::current($this->candidateid);
        $shown = null;
        if ($current && structure_archive::can_read($this->candidateid)) {
            $shown = [
                'text' => get_string('structurecurrent', 'tool_vae', (object) [
                    'date' => userdate((int) $current->get_timecreated()),
                    'id' => substr($current->get_filename(), -30, 26),
                ]),
                'url' => \moodle_url::make_pluginfile_url(
                    $current->get_contextid(),
                    'tool_vae',
                    structure_archive::FILEAREA,
                    $current->get_itemid(),
                    $current->get_filepath(),
                    $current->get_filename(),
                    true
                )->out(false),
                'label' => get_string('structuredownload', 'tool_vae'),
            ];
        }
        $issue = null;
        if (structure_archive::can_issue($this->candidateid)) {
            $issue = [
                'url' => (new \moodle_url('/admin/tool/vae/structure.php', ['userid' => $this->candidateid]))->out(false),
                'label' => get_string('structureissue', 'tool_vae'),
            ];
        }

        return ['current' => $shown, 'issue' => $issue];
    }
}
