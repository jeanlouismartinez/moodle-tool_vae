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
use tool_vae\local\feasibility_fields;
use tool_vae\local\text_diff;

/**
 * The page of the versions of a section (history.php), below its heading (T-81, lot 3; lot 7, MMR-520 ticket 4).
 *
 * The section the versions are of, the way back to its screen, then "no version yet", or the form that compares two
 * versions (a table, a line per version, the latest first) and one small form per version that can be restored, outside the
 * comparison form: a form cannot hold another. The table never offers to delete a version: the history is the point (D-9).
 * The page checks the rights, restores and redirects before, and reads the draft, its versions and the names of their
 * authors; this class only words and places them.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_page implements named_templatable, renderable {
    /** @var string The line that names the section or the work situation. */
    private string $line;

    /** @var \moodle_url|null The way back to the screen of the section, or none. */
    private ?\moodle_url $back;

    /** @var bool Whether the draft is the text of a work situation. */
    private bool $situation;

    /** @var \stdClass The row of the draft. */
    private \stdClass $draft;

    /** @var \stdClass[] The versions, by number. */
    private array $versions;

    /** @var string[] The name of the author of each version, by number. */
    private array $authors;

    /** @var bool Whether the reader may restore a version. */
    private bool $canwrite;

    /** @var \moodle_url This page. */
    private \moodle_url $url;

    /**
     * The versions of a draft.
     *
     * @param string $line The line that names the section or the work situation.
     * @param \moodle_url|null $back The way back to the screen of the section or of the work situation; null for none.
     * @param bool $situation Whether the draft is the text of a work situation (the words of the way back).
     * @param \stdClass $draft The row of the draft.
     * @param \stdClass[] $versions The versions, by number, as draft_manager::versions() gives them.
     * @param string[] $authors The name of the author of each version, by number, read by the page.
     * @param bool $canwrite Whether the reader may restore a version.
     * @param \moodle_url $url This page.
     */
    public function __construct(
        string $line,
        ?\moodle_url $back,
        bool $situation,
        \stdClass $draft,
        array $versions,
        array $authors,
        bool $canwrite,
        \moodle_url $url
    ) {
        $this->line = $line;
        $this->back = $back;
        $this->situation = $situation;
        $this->draft = $draft;
        $this->versions = $versions;
        $this->authors = $authors;
        $this->canwrite = $canwrite;
        $this->url = $url;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/history_page';
    }

    /**
     * The context of the template: the texts in clear; the notification and the table as HTML.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = [
            'line' => $this->line,
            'back' => $this->back === null ? false : [
                'url' => $this->back->out(false),
                'label' => get_string($this->situation ? 'situationbacktosituation' : 'historyback', 'tool_vae'),
            ],
            'emptyhtml' => false,
            'compare' => false,
            'restores' => [],
        ];
        if (!$this->versions) {
            $context['emptyhtml'] = $output->notification(get_string('historyempty', 'tool_vae'), notification::NOTIFY_INFO);

            return $context;
        }

        $draftid = (int) $this->draft->id;
        $numbers = array_keys($this->versions);
        $latest = max($numbers);

        $context['compare'] = [
            'action' => (new \moodle_url('/admin/tool/vae/compare.php'))->out(false),
            'draftid' => $draftid,
            'tablehtml' => $output->render(new table($this->table($draftid, $latest))),
            'label' => get_string('historycompare', 'tool_vae'),
        ];

        // One small form per restoration, outside the comparison form: a form cannot hold another.
        if ($this->canwrite) {
            foreach ($numbers as $number) {
                if ($number === $latest) {
                    continue;
                }
                $context['restores'][] = [
                    'action' => $this->url->out(false),
                    'id' => 'tool-vae-restore-' . $number,
                    'sesskey' => sesskey(),
                    'number' => $number,
                    'workingat' => (int) ($this->draft->workingat ?? 0),
                ];
            }
        }

        return $context;
    }

    /**
     * The table of the versions, the latest first.
     *
     * @param int $draftid
     * @param int $latest The number of the latest version.
     * @return \html_table
     */
    private function table(int $draftid, int $latest): \html_table {
        $section = (string) $this->draft->section;

        $table = new \html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [
            get_string('historyfrom', 'tool_vae'),
            get_string('historyto', 'tool_vae'),
            get_string('historyversion', 'tool_vae'),
            get_string('historydate', 'tool_vae'),
            get_string('historyauthor', 'tool_vae'),
            get_string('historynote', 'tool_vae'),
            get_string('historysize', 'tool_vae'),
            get_string('historychanges', 'tool_vae'),
            '',
        ];

        $sorted = $this->versions;
        krsort($sorted);
        foreach ($sorted as $number => $version) {
            $previous = $this->versions[$number - 1] ?? null;
            $changes = '';
            if ($previous !== null) {
                $counts = text_diff::counts(text_diff::diff(
                    feasibility_fields::readable($section, (string) $previous->content),
                    feasibility_fields::readable($section, (string) $version->content)
                ));
                $changes = get_string('diffsummary', 'tool_vae', (object) $counts);
            }

            $label = get_string('historyversion', 'tool_vae') . ' ' . $number;
            $restore = '';
            if ($this->canwrite && $number !== $latest) {
                $restore = history_cell::restore($number, get_string('historyrestore', 'tool_vae'));
            }

            $note = (string) $version->note;
            if ($version->restoredfrom !== null) {
                $note = trim(get_string('historyrestoredfrom', 'tool_vae', (int) $version->restoredfrom) . ' ' . $note);
            }

            $table->data[] = [
                history_cell::radio(
                    'from',
                    $number,
                    get_string('historyfrom', 'tool_vae') . ': ' . $label,
                    $number === ($latest - 1 > 0 ? $latest - 1 : $latest)
                ),
                history_cell::radio('to', $number, get_string('historyto', 'tool_vae') . ': ' . $label, $number === $latest),
                new cell_content([cell_content::link(
                    new \moodle_url('/admin/tool/vae/compare.php', ['draftid' => $draftid, 'from' => $number, 'to' => $number]),
                    $label
                )]),
                new cell_content([cell_content::text(userdate((int) $version->timecreated))]),
                new cell_content([cell_content::text((string) ($this->authors[$number] ?? ''))]),
                new cell_content([cell_content::text($note)]),
                \core_text::strlen(feasibility_fields::readable($section, (string) $version->content)),
                new cell_content([cell_content::text($changes)]),
                $restore,
            ];
        }

        return $table;
    }
}
