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
use tool_vae\form\ref_form;
use tool_vae\local\display_date;

/**
 * The page of the addresses of the rules of validation (refs.php), below its heading (T-41; lot 7, MMR-520 ticket 4).
 *
 * What is kept (an address and a short title, never the text of a rule), whether the addresses are shown to the vaeistes,
 * the error of the last sending, then a notification when no record is imported, or the table of the records with their
 * addresses in full, as text, and a button to remove each, the heading and the form of the core to add one. The page reads
 * the records, sends and redirects before; this class words what it read for the template tool_vae/refs_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class refs_page implements named_templatable, renderable {
    /** @var \stdClass[] supersession::overview(), keyed by record. */
    private array $rows;

    /** @var array[] ref_manager::for_fiches(), keyed by record. */
    private array $refs;

    /** @var bool Whether the addresses are shown to the vaeistes (setting refopen). */
    private bool $open;

    /** @var string|null The error of the last sending, in words. */
    private ?string $error;

    /** @var ref_form The form to add an address, drawn once. */
    private ref_form $form;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $self;

    /**
     * The page.
     *
     * @param \stdClass[] $rows supersession::overview(): fichecode, label.
     * @param array[] $refs ref_manager::for_fiches() of these records: id, title, url, checkedat (or null).
     * @param bool $open Whether the setting refopen is on.
     * @param string|null $error The error of the last sending, or null.
     * @param ref_form $form The form of the page, already read by the page.
     * @param \moodle_url $self The address of the page, to which an address is removed.
     */
    public function __construct(array $rows, array $refs, bool $open, ?string $error, ref_form $form, \moodle_url $self) {
        $this->rows = $rows;
        $this->refs = $refs;
        $this->open = $open;
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
        return 'tool_vae/refs_page';
    }

    /**
     * The context of the template: plain texts, not escaped; HTML of the core only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = [
            'intro' => get_string('refintro', 'tool_vae'),
            'closedhtml' => $this->open ? false : $output->notification(
                get_string('refclosed', 'tool_vae'),
                notification::NOTIFY_INFO
            ),
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
        $context['headinghtml'] = $output->heading(get_string('refaddheading', 'tool_vae'), 3);
        // A form of the core is drawn once per page: its identifiers must be unique.
        $context['formhtml'] = $this->form->render();

        return $context;
    }

    /**
     * The table of the records, one line per address, the record named on its first line only.
     *
     * @param renderer_base $output
     * @return \html_table
     */
    private function table(renderer_base $output): \html_table {
        $table = new \html_table();
        $table->head = [
            get_string('supersessioncolfiche', 'tool_vae'),
            get_string('reftitle', 'tool_vae'),
            get_string('refurl', 'tool_vae'),
            get_string('refchecked', 'tool_vae'),
            '',
        ];
        foreach ($this->rows as $row) {
            $name = new cell_content([
                cell_content::text($row->fichecode . ' — '),
                cell_content::html(format_string($row->label)),
            ]);
            if (empty($this->refs[$row->fichecode])) {
                $table->data[] = [$name, new cell_content([cell_content::text(get_string('refnone', 'tool_vae'))]), '', '', ''];
                continue;
            }
            foreach ($this->refs[$row->fichecode] as $ref) {
                $checked = $ref->checkedat === null
                    ? get_string('refnotchecked', 'tool_vae')
                    : display_date::format($ref->checkedat, get_string('strftimedate', 'langconfig'), 'UTC');
                $table->data[] = [
                    $name,
                    new cell_content([cell_content::text((string) $ref->title)]),
                    // The address in full, as text: nothing hides where a click goes. Not a link here, the administrator
                    // checks it where it is shown to the vaeistes.
                    new cell_content([cell_content::text((string) $ref->url)]),
                    new cell_content([cell_content::text($checked)]),
                    $output->single_button(
                        new \moodle_url($this->self, ['remove' => $ref->id]),
                        get_string('refremove', 'tool_vae'),
                        'post'
                    ),
                ];
                $name = '';
            }
        }

        return $table;
    }
}
