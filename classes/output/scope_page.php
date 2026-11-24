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
use tool_vae\form\scope_form;
use tool_vae\local\display_date;

/**
 * The page "Hors de mon activité" (scope.php), below its heading (T-42; lot 7, MMR-520 ticket 4).
 *
 * What a mark is (the vaeiste's word about their activity, which exempts from nothing), whether the function is open, the
 * error of the last sending, the marks already said with a button to take each back, then, when the function is open, the
 * heading and the form of the core to say one (or a notification when no record is imported), and the way back to the
 * dossier. The page reads the marks, sends and redirects before; this class words them for the template
 * tool_vae/scope_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope_page implements named_templatable, renderable {
    /** @var \stdClass[] scope_manager::for_candidate(), keyed by "code-rank". */
    private array $marks;

    /** @var string[] scope_manager::labels(), keyed by "code-rank". */
    private array $labels;

    /** @var bool Whether the function is open on the site (setting scopeopen). */
    private bool $open;

    /** @var bool Whether a competency can be offered in the form (a record is imported). */
    private bool $hasoptions;

    /** @var string|null The error of the last sending, in words. */
    private ?string $error;

    /** @var scope_form The form to say a mark, drawn once. */
    private scope_form $form;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    /** @var \moodle_url The dossier of the vaeiste. */
    private \moodle_url $dossier;

    /**
     * The page.
     *
     * @param \stdClass[] $marks scope_manager::for_candidate(): id, note (or null), timecreated.
     * @param string[] $labels scope_manager::labels() of these marks; empty without marks.
     * @param bool $open Whether the setting scopeopen is on.
     * @param bool $hasoptions Whether the form has a competency to offer.
     * @param string|null $error The error of the last sending, or null.
     * @param scope_form $form The form of the page, already read by the page.
     * @param \moodle_url $url The address of the page, to which a mark is taken back.
     * @param \moodle_url $dossier The dossier of the vaeiste.
     */
    public function __construct(
        array $marks,
        array $labels,
        bool $open,
        bool $hasoptions,
        ?string $error,
        scope_form $form,
        \moodle_url $url,
        \moodle_url $dossier
    ) {
        $this->marks = $marks;
        $this->labels = $labels;
        $this->open = $open;
        $this->hasoptions = $hasoptions;
        $this->error = $error;
        $this->form = $form;
        $this->url = $url;
        $this->dossier = $dossier;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/scope_page';
    }

    /**
     * The context of the template: plain texts and addresses, not escaped; HTML of the core only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = [
            'intro' => get_string('scopeintro', 'tool_vae'),
            'sentence' => get_string('scopesentence', 'tool_vae'),
            'closedhtml' => $this->open ? false : $output->notification(
                get_string('scopeclosed', 'tool_vae'),
                notification::NOTIFY_INFO
            ),
            'errorhtml' => $this->error === null ? false : $output->notification(s($this->error), notification::NOTIFY_ERROR),
            'none' => $this->marks ? false : get_string('scopenone', 'tool_vae'),
            'tablehtml' => $this->marks ? $output->render(new table($this->table($output))) : false,
            'noframeworkhtml' => false,
            'headinghtml' => false,
            'formhtml' => false,
            'back' => ['url' => $this->dossier->out(false), 'label' => get_string('faisabbacktodossier', 'tool_vae')],
        ];
        if ($this->open && !$this->hasoptions) {
            $context['noframeworkhtml'] = $output->notification(
                get_string('noframeworkimported', 'tool_vae'),
                notification::NOTIFY_INFO
            );
        } else if ($this->open) {
            $context['headinghtml'] = $output->heading(get_string('scopeaddheading', 'tool_vae'), 3);
            // A form of the core is drawn once per page: its identifiers must be unique.
            $context['formhtml'] = $this->form->render();
        }

        return $context;
    }

    /**
     * The table of the marks, each with the button to take it back.
     *
     * @param renderer_base $output
     * @return \html_table
     */
    private function table(renderer_base $output): \html_table {
        $table = new \html_table();
        $table->head = [
            get_string('scopecompetency', 'tool_vae'),
            get_string('scopenote', 'tool_vae'),
            get_string('scopesaidon', 'tool_vae'),
            '',
        ];
        foreach ($this->marks as $key => $mark) {
            $table->data[] = [
                new cell_content([cell_content::text($key . (($this->labels[$key] ?? '') === ''
                    ? '' : ' — ' . $this->labels[$key]))]),
                $mark->note === null ? '' : new cell_content([cell_content::text((string) $mark->note)]),
                new cell_content([cell_content::text(
                    display_date::format((int) $mark->timecreated, get_string('strftimedate', 'langconfig'))
                )]),
                $output->single_button(
                    new \moodle_url($this->url, ['remove' => $mark->id]),
                    get_string('scoperemove', 'tool_vae'),
                    'post'
                ),
            ];
        }

        return $table;
    }
}
