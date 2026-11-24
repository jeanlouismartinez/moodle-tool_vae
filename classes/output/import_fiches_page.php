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
 * The page "Importer des fiches de France compétences" (import_fiches.php), below its heading (lot 7, MMR-520 ticket 4).
 *
 * Five states, which exclude one another: the import is refused before anything is read (a notification of the core); the
 * result of a plan, with the way to try again what was not written and the button to go on; the result shown once because
 * it could not be kept beside its plan; the preview of a plan and the form of its confirmation; the numbers and the two ways
 * to get the export, with the failure of the last send and the link to take back the numbers already imported. The page
 * checks the rights, reads the forms, imports and redirects before; this class only places what it is given.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_fiches_page implements named_templatable, renderable {
    /** @var array What the state holds, by name. */
    private array $state;

    /**
     * Use unavailable(), results(), unsaved(), preview() or numbers().
     *
     * @param array $state
     */
    private function __construct(array $state) {
        $this->state = $state + [
            'notice' => null, 'results' => null, 'redo' => null, 'index' => null, 'preview' => null, 'wait' => false,
            'numbers' => null, 'form' => null, 'now' => null,
        ];
    }

    /**
     * The page when the import is refused before anything is read or requested.
     *
     * @param string $key The key of the string of the refusal.
     * @return self
     */
    public static function unavailable(string $key): self {
        return new self(['notice' => [get_string($key, 'tool_vae'), notification::NOTIFY_ERROR]]);
    }

    /**
     * The result of a plan.
     *
     * @param \stdClass $plan A plan with its results.
     * @param \moodle_url|null $redo Where the numbers not written go back to the form; null when every record was written.
     * @param \moodle_url $index Where the button to go on leads.
     * @return self
     */
    public static function results(\stdClass $plan, ?\moodle_url $redo, \moodle_url $index): self {
        return new self(['results' => $plan, 'redo' => $redo, 'index' => $index]);
    }

    /**
     * The result of a plan shown once: the records are written, but the result could not be kept beside the plan.
     *
     * @param \stdClass $applied The plan with its results.
     * @param \moodle_url $index Where the button to go on leads.
     * @return self
     */
    public static function unsaved(\stdClass $applied, \moodle_url $index): self {
        return new self([
            'notice' => [get_string('fcimportresultunsaved', 'tool_vae'), notification::NOTIFY_WARNING],
            'results' => $applied,
            'index' => $index,
        ]);
    }

    /**
     * The preview of a plan and the form of its confirmation.
     *
     * @param \stdClass $plan
     * @param bool $wait Whether something can be imported: the import then takes a while, which is said.
     * @param \moodleform $form The form of the confirmation, already read by the page.
     * @param int|null $now The time, for a test; else the clock of the core.
     * @return self
     */
    public static function preview(\stdClass $plan, bool $wait, \moodleform $form, ?int $now = null): self {
        return new self(['preview' => $plan, 'wait' => $wait, 'form' => $form, 'now' => $now]);
    }

    /**
     * The numbers and the two ways to get the export.
     *
     * @param string|null $failure Why the last send failed, HTML that is already safe (fiche_messages::html()); or null.
     * @param int $known How many numbers of records already imported the link takes back; 0 for no link.
     * @param int $knowntotal How many records are already imported.
     * @param \moodle_url $knownurl Where the link to take them back leads.
     * @param \moodleform $form The form of the numbers, already read by the page.
     * @return self
     */
    public static function numbers(?string $failure, int $known, int $knowntotal, \moodle_url $knownurl, \moodleform $form): self {
        return new self([
            'notice' => $failure === null ? null : [$failure, notification::NOTIFY_ERROR],
            'numbers' => ['known' => $known, 'total' => $knowntotal, 'url' => $knownurl],
            'form' => $form,
        ]);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/import_fiches_page';
    }

    /**
     * The context of the template: the texts in clear; the notification, the pieces, the button and the form as HTML.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $state = $this->state;

        $numbers = false;
        if ($state['numbers'] !== null) {
            $known = false;
            if ($state['numbers']['known'] > 0) {
                // More records than the form takes at a time: the link says how many it takes back, and of how many.
                $known = [
                    'url' => $state['numbers']['url']->out(false),
                    'label' => $state['numbers']['total'] > $state['numbers']['known']
                        ? get_string('fcimportexistingmany', 'tool_vae', (object) [
                            'count' => $state['numbers']['known'], 'total' => $state['numbers']['total'],
                        ])
                        : get_string('fcimportexisting', 'tool_vae'),
                ];
            }
            $numbers = [
                'intro' => get_string('fcimportintro', 'tool_vae'),
                'neutral' => get_string('fcimportneutral', 'tool_vae'),
                'known' => $known,
            ];
        }

        return [
            // The notification of the core does not escape its message: the strings are the plugin's, the failure is safe.
            'noticehtml' => $state['notice'] === null ? false : $output->notification($state['notice'][0], $state['notice'][1]),
            'resultshtml' => $state['results'] === null ? false : $output->render(new fiche_results($state['results'])),
            'redo' => $state['redo'] === null ? false : [
                'url' => $state['redo']->out(false),
                'label' => get_string('fcimportredo', 'tool_vae'),
            ],
            'continuehtml' => $state['index'] === null ? false : $output->continue_button($state['index']),
            'previewhtml' => $state['preview'] === null
                ? false
                : $output->render(new fiche_preview($state['preview'], $state['now'])),
            'wait' => $state['wait'] ? ['text' => get_string('fcimportwaitapply', 'tool_vae')] : false,
            'numbers' => $numbers,
            // A form of the core is drawn once per page: its identifiers must be unique.
            'formhtml' => $state['form'] === null ? false : $state['form']->render(),
        ];
    }
}
