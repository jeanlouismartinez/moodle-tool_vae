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
use core\output\notification;
use core\output\renderable;
use core\output\renderer_base;

/**
 * The warning a claim carries when its anchor no longer says what this plugin wrote (lot 7, MMR-520 ticket 4).
 *
 * A notification of the core, so that it inherits the core's markup, its colours in every theme, and its behaviour with a
 * screen reader - a warning nobody can hear is not a warning. Drawn BEFORE the claim: a reader who has already taken the
 * certification record at face value has read the wrong thing. Nothing at all when there is nothing to say. Was
 * presenter::anchor_warning().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class anchor_warning implements named_templatable, renderable {
    /** @var \stdClass A tool_vae_evidence row. */
    private \stdClass $evidence;

    /** @var string|null One of the constants of anchor, or null. */
    private ?string $divergence;

    /**
     * The warning of one claim.
     *
     * @param \stdClass $evidence A tool_vae_evidence row.
     * @param string|null $divergence One of the constants of anchor (anchor::divergence()), or null.
     */
    public function __construct(\stdClass $evidence, ?string $divergence) {
        $this->evidence = $evidence;
        $this->divergence = $divergence;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/anchor_warning';
    }

    /**
     * The context of the template: the notification of the core, or false.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        if ($this->divergence === null) {
            return ['noticehtml' => false];
        }

        // The notification of the core does not escape its message: the code of the record is escaped here, as before.
        $message = get_string('anchor' . $this->divergence, 'tool_vae', (object) [
            'record' => s($this->evidence->fichecode . '-' . $this->evidence->ficherang),
        ]);

        return ['noticehtml' => $output->notification($message, notification::NOTIFY_WARNING)];
    }
}
