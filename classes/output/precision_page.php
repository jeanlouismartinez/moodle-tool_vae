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
use tool_vae\form\precision_form;

/**
 * The page "Demandes de précision" (precision.php), below its heading (T-20; lot 7, MMR-520 ticket 4).
 *
 * What a request is, the declaration with the warning of its anchor, the requests (each question with its author and its
 * time, then the answer and its time, or the word that it waits), then the one form for whoever may write now, or the
 * notice that a declaration attested takes no question, and the way back to the dossier. The question and the answer are
 * text typed by a person: plain text through format_text(), without the filters, as before. The page checks the right,
 * reads the requests, chooses the form, handles it and redirects before; this class words them for the template
 * tool_vae/precision_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class precision_page implements named_templatable, renderable {
    /** @var \stdClass A row of tool_vae_evidence. */
    private \stdClass $evidence;

    /** @var \context The vaeiste's context. */
    private \context $context;

    /** @var \stdClass[] request_manager::for_evidence(). */
    private array $requests;

    /** @var precision_form|null The form, for whoever may write now; drawn once. */
    private ?precision_form $form;

    /** @var bool Whether the notice that the declaration is attested is drawn in place of a form. */
    private bool $attested;

    /** @var \moodle_url The address of the dossier. */
    private \moodle_url $dossier;

    /**
     * The page.
     *
     * @param \stdClass $evidence A row of tool_vae_evidence.
     * @param \context $context The vaeiste's context, for format_text().
     * @param \stdClass[] $requests request_manager::for_evidence(), each with authorname.
     * @param precision_form|null $form The form, built and read by the page; null when nobody may write now.
     * @param bool $attested Whether the reader would ask but the declaration is attested: the notice, in place of a form.
     * @param \moodle_url $dossier The address of the dossier.
     */
    public function __construct(
        \stdClass $evidence,
        \context $context,
        array $requests,
        ?precision_form $form,
        bool $attested,
        \moodle_url $dossier
    ) {
        $this->evidence = $evidence;
        $this->context = $context;
        $this->requests = $requests;
        $this->form = $form;
        $this->attested = $attested;
        $this->dossier = $dossier;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/precision_page';
    }

    /**
     * The context of the template: plain texts and the address of the dossier; HTML only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // Drawn in the order the page drew them: what a piece asks of the page (its JavaScript) comes in the same order.
        $declaration = $output->render(new attest_declaration($this->evidence, $this->context));
        $date = get_string('strftimedatetime', 'langconfig');

        $requests = [];
        foreach ($this->requests as $request) {
            $author = $request->authorname !== '' ? $request->authorname : get_string('opinionformerauthor', 'tool_vae');
            $requests[] = [
                'questionheading' => get_string('precisionquestionheading', 'tool_vae'),
                'meta' => $author . ' - ' . userdate($request->timecreated, $date),
                'questionhtml' => format_text($request->question, FORMAT_PLAIN, ['context' => $this->context, 'filter' => false]),
                'waiting' => $request->answer === null ? get_string('precisionwaiting', 'tool_vae') : false,
                'answer' => $request->answer === null ? false : [
                    'heading' => get_string('precisionanswerheading', 'tool_vae'),
                    'date' => userdate($request->timeanswered, $date),
                    'answerhtml' => format_text($request->answer, FORMAT_PLAIN, ['context' => $this->context, 'filter' => false]),
                ],
            ];
        }

        return [
            'intro' => get_string('precisionintro', 'tool_vae'),
            'declarationhtml' => $declaration,
            'headinghtml' => $output->heading(get_string('precisionrequests', 'tool_vae'), 3),
            'none' => $this->requests ? false : get_string('precisionnone', 'tool_vae'),
            'requests' => $requests,
            'formhtml' => $this->form === null ? false : $this->form->render(),
            'attestedhtml' => $this->form === null && $this->attested
                ? $output->notification(get_string('errorrequestattested', 'tool_vae'), notification::NOTIFY_INFO)
                : false,
            'back' => ['url' => $this->dossier->out(false), 'label' => get_string('precisionbacktodossier', 'tool_vae')],
        ];
    }
}
