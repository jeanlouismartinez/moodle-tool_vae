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
 * The page "Mes vaeistes" (candidates.php), below its heading (lot 7, MMR-520 ticket 4).
 *
 * Three states, which exclude one another: the sub-system of competencies is off (a notification of the core), the reader
 * follows nobody (a notification), or the followed vaeistes in a table with, for a referent of blocs, the choice of one of
 * them. Per vaeiste: how many declarations, and how many await an attestation; a count of work to do, never a measure of the
 * vaeiste. The page reads the people and the counts; this class only words them for the template tool_vae/candidates_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class candidates_page implements named_templatable, renderable {
    /** @var array|null The notification drawn instead of the list: [key of the string, type], or null. */
    private ?array $notice;

    /** @var \stdClass[] The followed vaeistes, in the order of their names. */
    private array $users;

    /** @var \stdClass[] Keyed by user id: declarations and pending. */
    private array $counts;

    /** @var \stdClass|null The choice of blocs, as candidate_overview::narrowing() gives it. */
    private ?\stdClass $narrowing;

    /** @var \moodle_url|null The address of the page, the target of the choice. */
    private ?\moodle_url $action;

    /**
     * Use disabled(), nobody() or followed().
     *
     * @param array|null $notice
     * @param array $users
     * @param array $counts
     * @param \stdClass|null $narrowing
     * @param \moodle_url|null $action
     */
    private function __construct(
        ?array $notice,
        array $users = [],
        array $counts = [],
        ?\stdClass $narrowing = null,
        ?\moodle_url $action = null
    ) {
        $this->notice = $notice;
        $this->users = $users;
        $this->counts = $counts;
        $this->narrowing = $narrowing;
        $this->action = $action;
    }

    /**
     * The page when the sub-system of competencies is off.
     *
     * @return self
     */
    public static function disabled(): self {
        return new self(['errorcompetenciesdisabled', notification::NOTIFY_ERROR]);
    }

    /**
     * The page of a reader who follows nobody.
     *
     * @return self
     */
    public static function nobody(): self {
        return new self(['nocandidates', notification::NOTIFY_INFO]);
    }

    /**
     * The page of the followed vaeistes.
     *
     * @param \stdClass[] $users The followed vaeistes, records of the user table, in the order of their names.
     * @param \stdClass[] $counts candidate_overview::counts(), keyed by user id.
     * @param \stdClass $narrowing candidate_overview::narrowing(): choices and chosen.
     * @param \moodle_url $action The address of the page; the choice is sent to it without its parameters.
     * @return self
     */
    public static function followed(array $users, array $counts, \stdClass $narrowing, \moodle_url $action): self {
        return new self(null, $users, $counts, $narrowing, $action);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/candidates_page';
    }

    /**
     * The context of the template: plain texts and addresses, not escaped; HTML only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        if ($this->notice !== null) {
            [$key, $type] = $this->notice;

            return [
                'noticehtml' => $output->notification(get_string($key, 'tool_vae'), $type),
                'filter' => false,
                'filtered' => false,
                'tablehtml' => false,
                'followed' => false,
            ];
        }
        [$filter, $filtered] = $this->filter();

        return [
            'noticehtml' => false,
            'filter' => $filter,
            'filtered' => $filtered,
            'tablehtml' => $output->render(new table($this->table())),
            // The same vaeistes with their records and learning plans: a link, not an action on a dossier.
            'followed' => [
                'url' => (new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => 'followed']))->out(false),
                'label' => get_string('orgfollowedlink', 'tool_vae'),
            ],
        ];
    }

    /**
     * The choice of a bloc, for a referent of blocs, and the sentence that says the counts are narrowed to one.
     *
     * The first option, "every bloc", has the value 0 and is never marked chosen, as before: an empty choice selects nothing.
     *
     * @return array [false|array the form, false|string the sentence]
     */
    private function filter(): array {
        $choices = $this->narrowing->choices;
        $chosen = (string) $this->narrowing->chosen;
        if (!$choices) {
            return [false, false];
        }
        $labels = [get_string('candidatesfilterall', 'tool_vae')];
        foreach ($choices as $key => $referent) {
            $labels[$key] = $referent->fichecode . ' — ' . ($referent->ficherang === ''
                ? get_string('referentwhole', 'tool_vae')
                : get_string('referentblocrank', 'tool_vae', $referent->ficherang));
        }
        $options = [];
        foreach ($labels as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => $label, 'selected' => (string) $value === $chosen];
        }
        $form = [
            'action' => $this->action->out_omit_querystring(),
            'label' => get_string('candidatesfilter', 'tool_vae'),
            'options' => $options,
            'apply' => get_string('candidatesfilterapply', 'tool_vae'),
        ];

        return [$form, $chosen === '' ? false : get_string('candidatesfiltered', 'tool_vae', $labels[$chosen])];
    }

    /**
     * The table of the followed vaeistes: a link to each dossier, then the two counts.
     *
     * @return \html_table
     */
    private function table(): \html_table {
        $table = new \html_table();
        $table->head = [
            get_string('candidate', 'tool_vae'),
            get_string('candidatedeclarations', 'tool_vae'),
            get_string('candidatepending', 'tool_vae'),
        ];
        $table->attributes['class'] = 'generaltable tool-vae-candidates';
        foreach ($this->users as $user) {
            $count = $this->counts[$user->id] ?? null;
            $table->data[] = [
                new cell_content([cell_content::link(
                    new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $user->id]),
                    fullname($user)
                )]),
                (int) ($count->declarations ?? 0),
                (int) ($count->pending ?? 0),
            ];
        }

        return $table;
    }
}
