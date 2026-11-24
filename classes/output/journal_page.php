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
use tool_vae\local\draft_manager;

/**
 * The page "Journal réflexif" (journal.php), below its heading (T-14, lot 1; lot 7, MMR-520 ticket 4).
 *
 * The two notes (the journal is private; name nobody in it), what the journal is, the notice when it is closed; the button
 * to start an entry while it is open and not full (or the notification that it is full); one sentence when there is no
 * entry, or the table of the entries, each with the link to open it (while the journal is open) and the link to delete it;
 * the way back to the dossier. The confirmation of a deletion is the core's, drawn by the page itself. The page checks the
 * right and reads the entries before; this class words them for the template tool_vae/journal_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class journal_page implements named_templatable, renderable {
    /** @var bool draft_manager::reflection_open(): whether the journal can be written in. */
    private bool $open;

    /** @var \stdClass[] draft_manager::reflection_entries(). */
    private array $entries;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    /**
     * The page.
     *
     * @param bool $open draft_manager::reflection_open().
     * @param \stdClass[] $entries draft_manager::reflection_entries().
     * @param \moodle_url $url The address of the page.
     */
    public function __construct(bool $open, array $entries, \moodle_url $url) {
        $this->open = $open;
        $this->entries = $entries;
        $this->url = $url;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/journal_page';
    }

    /**
     * The context of the template: plain texts and addresses, not escaped; HTML only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // Nothing to start while it is closed.
        $start = false;
        if ($this->open && count($this->entries) < draft_manager::REFLEXION_MAX) {
            $start = $output->single_button(
                new \moodle_url($this->url, ['new' => 1, 'sesskey' => sesskey()]),
                get_string('reflexionnew', 'tool_vae'),
                'post'
            );
        } else if ($this->open) {
            $start = $output->notification(
                get_string('errorreflexionfull', 'tool_vae', draft_manager::REFLEXION_MAX),
                notification::NOTIFY_INFO
            );
        }

        return [
            'private' => get_string('reflexionprivate', 'tool_vae'),
            'consigne' => get_string('reflexionconsigne', 'tool_vae'),
            'intro' => get_string('reflexionintro', 'tool_vae'),
            'closedhtml' => $this->open ? false : $output->notification(
                get_string('reflexionclosednote', 'tool_vae'),
                notification::NOTIFY_WARNING
            ),
            'starthtml' => $start,
            'none' => $this->entries ? false : get_string('reflexionnone', 'tool_vae'),
            'tablehtml' => $this->entries ? $output->render(new table($this->table())) : false,
            'back' => [
                'url' => (new \moodle_url('/admin/tool/vae/dossier.php'))->out(false),
                'label' => get_string('reflexionbacktodossier', 'tool_vae'),
            ],
        ];
    }

    /**
     * The table of the entries: entry, written, state, actions.
     *
     * @return \html_table
     */
    private function table(): \html_table {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-journal';
        $table->head = [
            get_string('reflexioncolentry', 'tool_vae'),
            get_string('reflexioncolwritten', 'tool_vae'),
            get_string('reflexioncolstate', 'tool_vae'),
            get_string('faisabcolactions', 'tool_vae'),
        ];
        foreach ($this->entries as $entry) {
            $edit = new \moodle_url('/admin/tool/vae/section.php', [
                'doctype' => draft_manager::DOCTYPE_REFLEXION,
                'section' => draft_manager::REFLEXION_SECTION,
                'slot' => (int) $entry->slot,
            ]);
            $state = (int) $entry->currentversion > 0
                ? get_string('sectioncurrentversion', 'tool_vae', (int) $entry->currentversion)
                : get_string('sectionnoversion', 'tool_vae');
            $actions = [];
            if ($this->open) {
                // Closed, the journal can no longer be written in: only the link to delete stays.
                $actions[] = cell_content::link($edit, get_string('reflexionopen', 'tool_vae'));
                $actions[] = cell_content::text(' ');
            }
            $actions[] = cell_content::link(
                new \moodle_url($this->url, ['delete' => (int) $entry->id]),
                get_string('reflexiondelete', 'tool_vae')
            );
            $table->data[] = [
                new cell_content([cell_content::text(draft_manager::label($entry))]),
                new cell_content([cell_content::text(
                    userdate((int) $entry->timecreated, get_string('strftimedatetime', 'langconfig'))
                )]),
                new cell_content([cell_content::text($state)]),
                new cell_content($actions),
            ];
        }

        return $table;
    }
}
