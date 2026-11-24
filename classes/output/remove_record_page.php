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
use tool_vae\form\remove_record_form;

/**
 * The page "Retirer la ligne d'une fiche" (remove_record.php), below its heading (lot 7, MMR-520 ticket 4).
 *
 * Two states, which exclude one another: the removal is refused (unknown record, framework still held by the core, traces
 * left on the record: a notification of the core, said on the page rather than as an exception, and the button back to the
 * list), or the form of the core that lists what leaves with the row and asks for a confirmation, drawn once. The page checks
 * the rights, reads the refusal and the form, removes and redirects before; this class only places them for the template
 * tool_vae/remove_record_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class remove_record_page implements named_templatable, renderable {
    /** @var \stdClass|null record_remover::refusal(): errorcode, a; or null. */
    private ?\stdClass $refusal;

    /** @var \moodle_url|null The list of the records, to go back to. */
    private ?\moodle_url $index;

    /** @var remove_record_form|null The form of the page, drawn once. */
    private ?remove_record_form $form;

    /**
     * Use refused() or form().
     *
     * @param \stdClass|null $refusal
     * @param \moodle_url|null $index
     * @param remove_record_form|null $form
     */
    private function __construct(?\stdClass $refusal, ?\moodle_url $index, ?remove_record_form $form) {
        $this->refusal = $refusal;
        $this->index = $index;
        $this->form = $form;
    }

    /**
     * The page when the removal is refused.
     *
     * @param \stdClass $refusal record_remover::refusal(): the key of the string and its value.
     * @param \moodle_url $index The list of the records.
     * @return self
     */
    public static function refused(\stdClass $refusal, \moodle_url $index): self {
        return new self($refusal, $index, null);
    }

    /**
     * The page with its form.
     *
     * @param remove_record_form $form The form of the page, already read by the page.
     * @return self
     */
    public static function form(remove_record_form $form): self {
        return new self(null, null, $form);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/remove_record_page';
    }

    /**
     * The context of the template: HTML of the core only, in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        if ($this->refusal !== null) {
            return [
                // The notification of the core places its message as HTML: the message is escaped, as before.
                'noticehtml' => $output->notification(
                    s(get_string($this->refusal->errorcode, 'tool_vae', $this->refusal->a)),
                    notification::NOTIFY_ERROR
                ),
                'continuehtml' => $output->continue_button($this->index),
                'formhtml' => false,
            ];
        }

        return [
            'noticehtml' => false,
            'continuehtml' => false,
            // A form of the core is drawn once per page: its identifiers must be unique.
            'formhtml' => $this->form->render(),
        ];
    }
}
