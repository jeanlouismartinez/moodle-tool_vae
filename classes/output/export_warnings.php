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
use tool_vae\local\exporter;

/**
 * The lines an export or a backup left out of its archive, under the sentence that opens the notice (lot 7, MMR-520
 * ticket 4, wave 4c).
 *
 * Was exporter::warnings_html(), same parameters. THE ONE PLACE WHERE THE LINES GO INTO HTML, for the export page and the
 * backup page alike: a value is whatever an administrator typed in the screen of the competencies, so the opening sentence
 * and every line are escaped by the template, and the pages do nothing of the kind themselves. The lines are those of
 * exporter::describe_warnings(): plain text, in the language of the page. The pages give what this piece draws to a
 * notification of the core.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class export_warnings implements named_templatable, renderable {
    /** @var array[] As exporter::warnings() returns them. */
    private array $warnings;

    /** @var string The sentence that opens the notice, plain text. */
    private string $lead;

    /**
     * The notice of the lines left out.
     *
     * @param array[] $warnings As exporter::warnings() returns them.
     * @param string $lead The sentence that opens the notice (exportwarning, backupdossierwarning), not escaped.
     */
    public function __construct(array $warnings, string $lead) {
        $this->warnings = $warnings;
        $this->lead = $lead;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/export_warnings';
    }

    /**
     * The context of the template: the opening sentence and each line, plain.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $lines = [];
        foreach (exporter::describe_warnings($this->warnings) as $line) {
            $lines[] = ['text' => $line];
        }

        return ['lead' => $this->lead, 'lines' => $lines];
    }
}
