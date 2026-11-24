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
use tool_vae\form\supersession_form;

/**
 * The page "Fiches remplacées" (supersession.php), below its heading (T-30, lot 1; lot 7, MMR-520 ticket 4).
 *
 * What the page is for; the error of a declaration refused; then a notification when no record is imported, or the table
 * of the records (which record replaces which, what still holds the old one: counts, never a name), with, for a record that
 * has a successor, the link to its correspondences and the button that clears the successor; and below the table, the
 * heading and the form of the core that declares a successor, drawn once. The page checks the right, reads the records,
 * reads the form, declares or clears and redirects before; this class words them for the template tool_vae/supersession_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class supersession_page implements named_templatable, renderable {
    /** @var \stdClass[] supersession::overview(). */
    private array $rows;

    /** @var string|null The message of a declaration refused, or null. */
    private ?string $error;

    /** @var supersession_form The form of the page, drawn once, below the table. */
    private supersession_form $form;

    /** @var \moodle_url The address of the page, to which a successor is cleared. */
    private \moodle_url $self;

    /**
     * The page.
     *
     * @param \stdClass[] $rows supersession::overview().
     * @param string|null $error The message of a declaration refused, as text, or null.
     * @param supersession_form $form The form of the page, already read by the page.
     * @param \moodle_url $self The address of the page.
     */
    public function __construct(array $rows, ?string $error, supersession_form $form, \moodle_url $self) {
        $this->rows = $rows;
        $this->error = $error;
        $this->form = $form;
        $this->self = $self;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/supersession_page';
    }

    /**
     * The context of the template: plain texts, not escaped; HTML only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = [
            'intro' => get_string('supersessionintro', 'tool_vae'),
            // The notification of the core places its message as HTML: the message is escaped, as before.
            'errorhtml' => $this->error === null ? false : $output->notification(s($this->error), notification::NOTIFY_ERROR),
            'nonehtml' => false,
            'tablehtml' => false,
            'headinghtml' => false,
            'formhtml' => false,
        ];
        if (!$this->rows) {
            $context['nonehtml'] = $output->notification(get_string('supersessionnone', 'tool_vae'), notification::NOTIFY_INFO);

            return $context;
        }
        $context['tablehtml'] = $output->render(new table($this->table($output)));
        $context['headinghtml'] = $output->heading(get_string('supersessiondeclareheading', 'tool_vae'), 3);
        // A form of the core is drawn once per page: its identifiers must be unique.
        $context['formhtml'] = $this->form->render();

        return $context;
    }

    /**
     * A record as shown: its code, then its label as the core formats it (no label when the framework is gone from the
     * core: the code alone).
     *
     * @param string $code
     * @param string $label
     * @return array[] Parts of cell_content.
     */
    private static function record(string $code, string $label): array {
        if ($label === '') {
            return [cell_content::text($code)];
        }

        return [cell_content::text($code . ' — '), cell_content::html(format_string($label))];
    }

    /**
     * The table of the records.
     *
     * @param renderer_base $output
     * @return \html_table
     */
    private function table(renderer_base $output): \html_table {
        $table = new \html_table();
        $table->head = [
            get_string('supersessioncolfiche', 'tool_vae'),
            get_string('supersessioncolupdated', 'tool_vae'),
            get_string('supersessioncolsuccessor', 'tool_vae'),
            get_string('supersessioncoldeclarations', 'tool_vae'),
            get_string('supersessioncolcandidates', 'tool_vae'),
            get_string('supersessioncolcarried', 'tool_vae'),
            get_string('supersessioncolobsolete', 'tool_vae'),
            '',
        ];
        foreach ($this->rows as $row) {
            $successor = $row->successorcode === ''
                ? [cell_content::text(get_string('supersessionnosuccessor', 'tool_vae'))]
                : self::record($row->successorcode, $row->successorlabel);
            $action = $row->successorcode === '' ? '' : new cell_content([
                cell_content::link(
                    new \moodle_url('/admin/tool/vae/passage.php', ['fiche' => $row->fichecode]),
                    get_string('supersessionpassages', 'tool_vae')
                ),
                cell_content::html($output->single_button(
                    new \moodle_url($this->self, ['clear' => $row->fichecode]),
                    get_string('supersessionclear', 'tool_vae'),
                    'post'
                )),
            ]);
            $date = $row->sourceupdated ? userdate($row->sourceupdated, get_string('strftimedate', 'langconfig')) : '';
            $table->data[] = [
                new cell_content(self::record($row->fichecode, $row->label)),
                $date === '' ? '' : new cell_content([cell_content::text($date)]),
                new cell_content($successor),
                $row->declarations,
                $row->candidates,
                $row->carried,
                $row->gone,
                $action,
            ];
        }

        return $table;
    }
}
