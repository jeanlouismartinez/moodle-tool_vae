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
use tool_vae\form\dossier_import_form;

/**
 * The page "Réimporter un dossier" (import_dossier.php), between its header or heading and the footer (lot 8; lot 7, MMR-520).
 *
 * Two states, which exclude one another: the sub-system of competencies is off (a notification of the core, below the
 * header), or, below the heading, what the page is for, the notice that a withdrawal does not reach an archive, and the form
 * of the core, drawn once. The page checks the right, reads the form, imports and redirects before; this class words the
 * state for the template tool_vae/import_dossier_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_dossier_page implements named_templatable, renderable {
    /** @var dossier_import_form|null The form of the page, drawn once; null when the sub-system is off. */
    private ?dossier_import_form $form;

    /**
     * Use disabled() or form().
     *
     * @param dossier_import_form|null $form
     */
    private function __construct(?dossier_import_form $form) {
        $this->form = $form;
    }

    /**
     * The page when the sub-system of competencies is off.
     *
     * @return self
     */
    public static function disabled(): self {
        return new self(null);
    }

    /**
     * The page with its form.
     *
     * @param dossier_import_form $form The form of the page, already read by the page.
     * @return self
     */
    public static function form(dossier_import_form $form): self {
        return new self($form);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/import_dossier_page';
    }

    /**
     * The context of the template: plain texts, not escaped; HTML of the core only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        if ($this->form === null) {
            return [
                'intro' => false,
                'noticehtml' => $output->notification(
                    get_string('errorcompetenciesdisabled', 'tool_vae'),
                    notification::NOTIFY_ERROR
                ),
                'formhtml' => false,
            ];
        }

        return [
            'intro' => get_string('importdossierintro', 'tool_vae'),
            'noticehtml' => $output->notification(
                get_string('importdossierwithdrawnotice', 'tool_vae'),
                notification::NOTIFY_WARNING
            ),
            // A form of the core is drawn once per page: its identifiers must be unique.
            'formhtml' => $this->form->render(),
        ];
    }
}
