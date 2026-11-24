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
use tool_vae\form\import_form;

/**
 * The page "Importer une fiche" (import.php), below its heading (lot 7, MMR-520 ticket 4).
 *
 * Two states, which exclude one another: the import is refused before the form (the sub-system of competencies is off, or
 * the reader lacks the core's right to manage frameworks: a notification of the core), or the form of the core, drawn once.
 * The page checks the rights, reads the form, imports and redirects before; this class only places the refusal or the form
 * for the template tool_vae/import_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_page implements named_templatable, renderable {
    /** @var string|null The key of the string of the refusal, or null. */
    private ?string $refusal;

    /** @var import_form|null The form of the page, drawn once. */
    private ?import_form $form;

    /**
     * Use refused() or form().
     *
     * @param string|null $refusal
     * @param import_form|null $form
     */
    private function __construct(?string $refusal, ?import_form $form) {
        $this->refusal = $refusal;
        $this->form = $form;
    }

    /**
     * The page when the import is refused before the form.
     *
     * @param string $key The key of the string of the refusal (errorcompetenciesdisabled, errorimportnocompetencymanage).
     * @return self
     */
    public static function refused(string $key): self {
        return new self($key, null);
    }

    /**
     * The page with its form.
     *
     * @param import_form $form The form of the page, already read by the page.
     * @return self
     */
    public static function form(import_form $form): self {
        return new self(null, $form);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/import_page';
    }

    /**
     * The context of the template: HTML of the core only, in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'noticehtml' => $this->refusal === null ? false : $output->notification(
                get_string($this->refusal, 'tool_vae'),
                notification::NOTIFY_ERROR
            ),
            // A form of the core is drawn once per page: its identifiers must be unique.
            'formhtml' => $this->form === null ? false : $this->form->render(),
        ];
    }
}
