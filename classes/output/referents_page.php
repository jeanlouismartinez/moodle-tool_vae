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
use tool_vae\form\referent_form;

/**
 * The page of the referents of the blocs (referents.php), below its heading (T-23; lot 7, MMR-520 ticket 4).
 *
 * What a referent is (a pointer for the messages, not a right), the error of the last sending, then a notification when no
 * record is imported, or the table of the records with their referents and a button to take each back, the heading and the
 * form of the core to name one. The page reads the records, sends and redirects before; this class words what it read for
 * the template tool_vae/referents_page and draws the form once.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class referents_page implements named_templatable, renderable {
    /** @var \stdClass[] supersession::overview(), keyed by record. */
    private array $rows;

    /** @var array[] referent_manager::for_fiches(), keyed by record. */
    private array $referents;

    /** @var \stdClass[] The people named referent, keyed by id. */
    private array $users;

    /** @var string|null The error of the last sending, in words. */
    private ?string $error;

    /** @var referent_form The form to name a referent, drawn once. */
    private referent_form $form;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $self;

    /**
     * The page.
     *
     * @param \stdClass[] $rows supersession::overview(): fichecode, label.
     * @param array[] $referents referent_manager::for_fiches() of these records: id, ficherang, userid; empty without rows.
     * @param \stdClass[] $users The people named referent, keyed by id; one gone from the site is not named.
     * @param string|null $error The error of the last sending, or null.
     * @param referent_form $form The form of the page, already read by the page.
     * @param \moodle_url $self The address of the page, to which a referent is taken back.
     */
    public function __construct(
        array $rows,
        array $referents,
        array $users,
        ?string $error,
        referent_form $form,
        \moodle_url $self
    ) {
        $this->rows = $rows;
        $this->referents = $referents;
        $this->users = $users;
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
        return 'tool_vae/referents_page';
    }

    /**
     * The context of the template: plain texts, not escaped; HTML of the core only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = [
            'intro' => get_string('referentintro', 'tool_vae'),
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
        $context['headinghtml'] = $output->heading(get_string('referentaddheading', 'tool_vae'), 3);
        // A form of the core is drawn once per page: its identifiers must be unique.
        $context['formhtml'] = $this->form->render();

        return $context;
    }

    /**
     * The table of the records, one line per referent, the record named on its first line only.
     *
     * @param renderer_base $output
     * @return \html_table
     */
    private function table(renderer_base $output): \html_table {
        $table = new \html_table();
        $table->head = [
            get_string('supersessioncolfiche', 'tool_vae'),
            get_string('referentbloc', 'tool_vae'),
            get_string('referentuser', 'tool_vae'),
            '',
        ];
        foreach ($this->rows as $row) {
            $name = new cell_content([
                cell_content::text($row->fichecode . ' — '),
                cell_content::html(format_string($row->label)),
            ]);
            if (empty($this->referents[$row->fichecode])) {
                $table->data[] = [$name, '', new cell_content([cell_content::text(get_string('referentnone', 'tool_vae'))]), ''];
                continue;
            }
            foreach ($this->referents[$row->fichecode] as $referent) {
                $person = $this->users[$referent->userid] ?? null;
                $table->data[] = [
                    $name,
                    new cell_content([cell_content::text($referent->ficherang === ''
                        ? get_string('referentwhole', 'tool_vae')
                        : get_string('referentblocrank', 'tool_vae', $referent->ficherang))]),
                    $person ? new cell_content([cell_content::text(fullname($person) . ' (' . $person->username . ')')]) : '',
                    $output->single_button(
                        new \moodle_url($this->self, ['remove' => $referent->id]),
                        get_string('referentremove', 'tool_vae'),
                        'post'
                    ),
                ];
                $name = '';
            }
        }

        return $table;
    }
}
