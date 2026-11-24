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
use tool_vae\form\declare_form;
use tool_vae\local\display_date;

/**
 * The page "Reporter une déclaration" (carry.php), below its heading (T-30, lot 3; lot 7, MMR-520 ticket 4).
 *
 * When the replacing record was hidden in Moodle between the page and the send, the message and the sentence that the text
 * is kept in the form; then what a carry is, the declaration as it stands on the old record, the correspondence decided by
 * the administrator (and their note), and the form on the new record. The page checks the right, reads the offer and the
 * competency of the old record, handles the form and redirects before; this class words them for the template
 * tool_vae/carry_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class carry_page implements named_templatable, renderable {
    /** @var string|null The message of a hidden record (hidden_frameworks), its title already escaped; null when none. */
    private ?string $refusal;

    /** @var \stdClass The declaration on the replaced record. */
    private \stdClass $evidence;

    /** @var \context The vaeiste's context. */
    private \context $context;

    /** @var \stdClass|null The competency of the old record, as the gateway reads it; null when it is no longer read. */
    private ?\stdClass $from;

    /** @var \stdClass carry_manager::require_offer(): passage, target, successor. */
    private \stdClass $offer;

    /** @var declare_form The form, drawn once. */
    private declare_form $form;

    /**
     * The page.
     *
     * @param string|null $refusal The message of a hidden record (hidden_frameworks::message() or the refusal of the carry),
     *        HTML-safe; null when the carry can be made.
     * @param \stdClass $evidence The declaration on the replaced record.
     * @param \context $context The vaeiste's context.
     * @param \stdClass|null $from The competency of the old record (idnumber, label); null when it is no longer read.
     * @param \stdClass $offer carry_manager::require_offer().
     * @param declare_form $form The form, built and read by the page.
     */
    public function __construct(
        ?string $refusal,
        \stdClass $evidence,
        \context $context,
        ?\stdClass $from,
        \stdClass $offer,
        declare_form $form
    ) {
        $this->refusal = $refusal;
        $this->evidence = $evidence;
        $this->context = $context;
        $this->from = $from;
        $this->offer = $offer;
        $this->form = $form;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/carry_page';
    }

    /**
     * The context of the template: plain texts; HTML only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $refusal = false;
        if ($this->refusal !== null) {
            $refusal = [
                // HTML-safe already (hidden_frameworks escapes the title it names): given to the notification as it is.
                'noticehtml' => $output->notification($this->refusal, notification::NOTIFY_ERROR),
                'kept' => get_string('carryhiddenkept', 'tool_vae'),
            ];
        }
        $evidence = $this->evidence;
        $offer = $this->offer;
        $fromcode = (string) ($this->from->idnumber ?? $evidence->fichecode . '-' . $evidence->ficherang);

        return [
            'refusal' => $refusal,
            'intro' => get_string('carryintro', 'tool_vae'),
            // The headings of the core do not escape their text: the codes are escaped here, as before.
            'originalhtml' => $output->heading(get_string('carryoriginal', 'tool_vae', s($evidence->fichecode)), 3),
            'declarationhtml' => $output->render(new declaration($evidence, $this->context)),
            'correspondenceheadinghtml' => $output->heading(get_string('carrycorrespondence', 'tool_vae'), 3),
            'correspondencehtml' => get_string('carrycorrespondencetext', 'tool_vae', (object) [
                'from' => self::show($fromcode, (string) ($this->from->label ?? '')),
                'to' => self::show((string) $offer->target->idnumber, (string) $offer->target->label),
                'when' => display_date::format((int) $offer->passage->decidedat, get_string('strftimedate', 'langconfig')),
            ]),
            'note' => trim((string) $offer->passage->note) === ''
                ? false
                : ['text' => get_string('carrynote', 'tool_vae', (string) $offer->passage->note)],
            'formheadinghtml' => $output->heading(get_string('carryformheading', 'tool_vae', s($offer->successor->fichecode)), 3),
            'formhtml' => $this->form->render(),
        ];
    }

    /**
     * A competency as the sentence of the correspondence names it: its code, escaped, and its label through format_string().
     *
     * @param string $idnumber
     * @param string $label
     * @return string HTML.
     */
    private static function show(string $idnumber, string $label): string {
        return s($idnumber) . ' — ' . format_string($label);
    }
}
