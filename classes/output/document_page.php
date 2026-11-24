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
 * The one page document.php draws, between its header and its footer (T-83; lot 7, MMR-520 ticket 4).
 *
 * document.php sends a file (a Word or an OpenDocument copy of the dossier); the only page it draws says that the sub-system
 * of competencies is off, by a notification of the core, for the template tool_vae/document_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class document_page implements named_templatable, renderable {
    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/document_page';
    }

    /**
     * The context of the template: the notification of the core.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'noticehtml' => $output->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                notification::NOTIFY_ERROR
            ),
        ];
    }
}
