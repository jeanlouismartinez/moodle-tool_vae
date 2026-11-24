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

/**
 * The page of a rank (T-90, lot 3): certify a bloc (certify.php) or evaluate a competency (evaluate.php).
 *
 * A positioning of the structure, never a validation. The two pages draw the same thing: the sentence that says so, the
 * record and the label of the rank, the error of a send, the competencies of the bloc that are not yet sufficient (certify
 * only), the form for whoever may write, then the history of the rank and the way back to the dossier. Hence one class with
 * a named constructor for each page (lot 7, MMR-520 ticket 4).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class positioning_page implements named_templatable, renderable {
    /** @var string certif or eval. */
    private string $kind;

    /** @var string The record. */
    private string $fichecode;

    /** @var string The label of the rank. */
    private string $label;

    /** @var string|null The message of a send that was refused. */
    private ?string $error;

    /** @var string[] Rank => label of each competency of the bloc that is not yet sufficient (certify only). */
    private array $lacking;

    /** @var \moodleform|null The form, for whoever may write. */
    private ?\moodleform $form;

    /** @var \stdClass[] The lines of the rank, the earliest first. */
    private array $rows;

    /** @var \context The context of the candidate. */
    private \context $context;

    /** @var \moodle_url The dossier of the candidate. */
    private \moodle_url $dossier;

    /**
     * The page.
     *
     * @param string $kind certif or eval.
     * @param string $fichecode
     * @param string $label
     * @param string|null $error
     * @param string[] $lacking
     * @param \moodleform|null $form
     * @param \stdClass[] $rows
     * @param \context $context
     * @param \moodle_url $dossier
     */
    private function __construct(
        string $kind,
        string $fichecode,
        string $label,
        ?string $error,
        array $lacking,
        ?\moodleform $form,
        array $rows,
        \context $context,
        \moodle_url $dossier
    ) {
        $this->kind = $kind;
        $this->fichecode = $fichecode;
        $this->label = $label;
        $this->error = $error;
        $this->lacking = $lacking;
        $this->form = $form;
        $this->rows = $rows;
        $this->context = $context;
        $this->dossier = $dossier;
    }

    /**
     * The page that certifies a bloc (certify.php).
     *
     * @param string $fichecode The record.
     * @param string $label The label of the bloc, or the words for the whole of a record with no bloc.
     * @param string|null $error The message of a send that was refused.
     * @param string[] $lacking Rank => label of each competency of the bloc that is not yet sufficient.
     * @param \moodleform|null $form The form, for whoever may certify.
     * @param \stdClass[] $rows The lines of the bloc, the earliest first.
     * @param \context $context The context of the candidate.
     * @param \moodle_url $dossier The dossier of the candidate.
     * @return self
     */
    public static function certify(
        string $fichecode,
        string $label,
        ?string $error,
        array $lacking,
        ?\moodleform $form,
        array $rows,
        \context $context,
        \moodle_url $dossier
    ): self {
        return new self('certif', $fichecode, $label, $error, $lacking, $form, $rows, $context, $dossier);
    }

    /**
     * The page that evaluates a competency (evaluate.php).
     *
     * @param string $fichecode The record.
     * @param string $label The label of the competency.
     * @param string|null $error The message of a send that was refused.
     * @param \moodleform|null $form The form, for whoever may evaluate.
     * @param \stdClass[] $rows The lines of the competency, the earliest first.
     * @param \context $context The context of the candidate.
     * @param \moodle_url $dossier The dossier of the candidate.
     * @return self
     */
    public static function evaluate(
        string $fichecode,
        string $label,
        ?string $error,
        ?\moodleform $form,
        array $rows,
        \context $context,
        \moodle_url $dossier
    ): self {
        return new self('eval', $fichecode, $label, $error, [], $form, $rows, $context, $dossier);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/positioning_page';
    }

    /**
     * The context of the template: the words in plain text, the notifications, the form, the heading and the history drawn.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // A notification of the core does not escape its message: the error and the lacks are escaped here, as before.
        $errorhtml = $this->error === null ? ''
            : $output->notification(s($this->error), \core\output\notification::NOTIFY_ERROR);
        $alerthtml = !$this->lacking ? '' : $output->notification(
            s(get_string('positioningalert', 'tool_vae', implode(', ', $this->lacking))),
            \core\output\notification::NOTIFY_WARNING
        );
        $form = null;
        if ($this->form) {
            $intro = $this->kind === 'certif' ? 'positioningcertifyintro' : 'positioningevaluateintro';
            $form = ['intro' => get_string($intro, 'tool_vae'), 'formhtml' => $this->form->render()];
        }

        return [
            'disclaimer' => get_string('positioningdisclaimer', 'tool_vae'),
            'fichecode' => $this->fichecode,
            'label' => $this->label,
            'errorhtml' => $errorhtml,
            'alerthtml' => $alerthtml,
            'form' => $form,
            'historyheadinghtml' => $output->heading(get_string('positioninghistory', 'tool_vae'), 3),
            'historyhtml' => $output->render(new positioning_history($this->rows, $this->context, $this->kind)),
            'backurl' => $this->dossier->out(false),
            'back' => get_string('positioningback', 'tool_vae'),
        ];
    }
}
