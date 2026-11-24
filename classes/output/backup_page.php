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
 * The page "Sauvegarder dans Moodle" (backup.php), between its header or heading and the footer (lot 8 bis; lot 7, MMR-520).
 *
 * Three states, which exclude one another: the sub-system of competencies is off (a notification of the core, below the
 * header); the dossier holds no declaration (a notification and the way back to the dossier, below the heading); or what a
 * backup is, above the confirmation of the core, which the page draws itself. The page checks the rights, reads the dossier,
 * sends and redirects before; this class words the state for the template tool_vae/backup_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_page implements named_templatable, renderable {
    /** @var array|null The notification drawn instead of the sentence: [key of the string, type], or null. */
    private ?array $notice;

    /** @var \moodle_url|null The dossier, when the page offers the way back to it. */
    private ?\moodle_url $dossier;

    /**
     * Use disabled(), nodeclaration() or intro().
     *
     * @param array|null $notice
     * @param \moodle_url|null $dossier
     */
    private function __construct(?array $notice, ?\moodle_url $dossier = null) {
        $this->notice = $notice;
        $this->dossier = $dossier;
    }

    /**
     * The page when the sub-system of competencies is off.
     *
     * @return self
     */
    public static function disabled(): self {
        return new self(['errorcompetenciesdisabled', notification::NOTIFY_ERROR]);
    }

    /**
     * The page of a dossier that holds no declaration: there is nothing to back up.
     *
     * @param \moodle_url $dossier The dossier of the vaeiste, to go back to.
     * @return self
     */
    public static function nodeclaration(\moodle_url $dossier): self {
        return new self(['errorexportempty', notification::NOTIFY_INFO], $dossier);
    }

    /**
     * What a backup is, above the confirmation of the core.
     *
     * @return self
     */
    public static function intro(): self {
        return new self(null);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/backup_page';
    }

    /**
     * The context of the template: plain texts, not escaped; HTML of the core only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        if ($this->notice === null) {
            return [
                'noticehtml' => false,
                'intro' => get_string('backupdossierintro', 'tool_vae'),
                'continuehtml' => false,
            ];
        }
        [$key, $type] = $this->notice;

        return [
            'noticehtml' => $output->notification(get_string($key, 'tool_vae'), $type),
            'intro' => false,
            'continuehtml' => $this->dossier === null ? false : $output->continue_button($this->dossier),
        ];
    }
}
