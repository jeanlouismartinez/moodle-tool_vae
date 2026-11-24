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
 * The candidates the reader follows, on the tab "Followed candidates" of organisation.php (lot 7, MMR-520 ticket 4, wave 4b).
 *
 * One line each, with the records they declared on, their plans and two counts. FOLLOWED: counts of work, plans as the core
 * lets the reader read them. Five columns and no more - no date, no total, no action on a dossier. A cell of plans never says
 * "none" over a list the reader's rights may have emptied. Above the table, the form that narrows the counts to a bloc the
 * reader is referent of, offered only with a choice, and the sentence that says the counts are narrowed. Was
 * organisation_screen::followed() and followed_filter().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class organisation_followed implements named_templatable, renderable {
    /** @var \stdClass What organisation::followed() gives. */
    private \stdClass $data;

    /** @var \moodle_url The address of the page: the filter by bloc sends its choice there. */
    private \moodle_url $action;

    /**
     * The tab of the followed candidates.
     *
     * @param \stdClass $data What organisation::followed() gives.
     * @param \moodle_url $action The address of the page: the filter by bloc sends its choice there.
     */
    public function __construct(\stdClass $data, \moodle_url $action) {
        $this->data = $data;
        $this->action = $action;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/organisation_followed';
    }

    /**
     * The context of the template: the box that says there is nobody, or the form of the filter and the table.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = $this->data;
        if (!$data->candidates) {
            return [
                'nonehtml' => $output->render(new organisation_notice(get_string('nocandidates', 'tool_vae'))),
                'filter' => null,
                'tablehtml' => '',
            ];
        }

        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-organisation tool-vae-followed';
        $table->caption = get_string('orgfollowedcaption', 'tool_vae');
        $table->captionhide = true;
        $table->head = [
            get_string('candidate', 'tool_vae'),
            get_string('orgcolfiches', 'tool_vae'),
            get_string('orgcolplans', 'tool_vae'),
            get_string('candidatedeclarations', 'tool_vae'),
            get_string('candidatepending', 'tool_vae'),
        ];
        foreach ($data->candidates as $candidate) {
            $name = new \html_table_cell(new cell_content([cell_content::link(
                new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidate->id]),
                $candidate->name
            )]));
            $name->header = true;
            $name->scope = 'row';
            $table->data[] = new \html_table_row([
                $name,
                organisation_cell::records($candidate->fiches),
                organisation_cell::followed_plans($candidate),
                (string) $candidate->declarations,
                (string) $candidate->pending,
            ]);
        }

        return [
            'nonehtml' => '',
            'filter' => $this->filter(),
            'tablehtml' => $output->render(new table($table)),
        ];
    }

    /**
     * The form that narrows the counts to a bloc, and the sentence that says they are narrowed; null without a choice.
     *
     * It carries the tab, so that the answer comes back to the same tab. The labels are given plain: the template escapes
     * them once, as the old code did by s() before the writer of the core, which does not escape the labels of a select.
     *
     * @return array|null
     */
    private function filter(): ?array {
        $data = $this->data;
        if (!$data->choices) {
            return null;
        }

        $options = [get_string('candidatesfilterall', 'tool_vae')];
        foreach ($data->choices as $key => $referent) {
            $options[$key] = $referent->fichecode . ' — ' . ($referent->ficherang === ''
                ? get_string('referentwhole', 'tool_vae')
                : get_string('referentblocrank', 'tool_vae', $referent->ficherang));
        }
        $items = [];
        foreach ($options as $value => $label) {
            // As the writer of the core compares them: the value and the choice as texts.
            $items[] = ['value' => (string) $value, 'label' => $label, 'selected' => (string) $value === (string) $data->chosen];
        }

        return [
            'action' => $this->action->out_omit_querystring(),
            'label' => get_string('candidatesfilter', 'tool_vae'),
            'options' => $items,
            'apply' => get_string('candidatesfilterapply', 'tool_vae'),
            'filtered' => $data->chosen !== ''
                ? ['label' => get_string('candidatesfiltered', 'tool_vae', $options[$data->chosen])]
                : null,
        ];
    }
}
