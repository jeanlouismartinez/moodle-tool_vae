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
use tool_vae\local\display_date;
use tool_vae\local\pending_report;

/**
 * The page of the declarations awaiting an attestation (pending.php), below its heading (T-22; lot 7, MMR-520 ticket 4).
 *
 * The sentence that says what the list is, the form of the reader's choice (a number of days, only the vaeistes nobody can
 * be told of), then one sentence when nothing is left, or the table, the oldest first. It says that declarations wait and
 * for how long, never whether a dossier is good. The page reads the declarations; this class words them for the template
 * tool_vae/pending_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pending_page implements named_templatable, renderable {
    /** @var int The number of days asked for. */
    private int $days;

    /** @var bool Whether only the vaeistes nobody can be told of are asked for. */
    private bool $unreached;

    /** @var \moodle_url The address of the page, the target of the form. */
    private \moodle_url $action;

    /** @var array[] candidateid => ['count' => int, 'oldest' => int], after the choice of the reader. */
    private array $groups;

    /** @var int[] candidateid => how many managers would be told. */
    private array $reach;

    /** @var \stdClass[] The vaeistes, keyed by id. */
    private array $users;

    /** @var int The time of the reading. */
    private int $now;

    /** @var string The key of the sentence drawn when no vaeiste is left. */
    private string $none;

    /**
     * The page.
     *
     * @param int $days The number of days asked for.
     * @param bool $unreached Whether the box "nobody can be told" is ticked.
     * @param \moodle_url $action The address of the page; the form is sent to it without its parameters.
     * @param array[] $groups pending_report::by_candidate(), after the choice of the reader.
     * @param int[] $reach pending_report::reachable(), for each vaeiste of $groups.
     * @param \stdClass[] $users The vaeistes of $groups, keyed by id; one gone from the site is named by its id.
     * @param int $now The time of the reading.
     * @param string $none The key of the sentence when $groups is empty: pendingnone, or pendingnoneunreached when the box
     *     emptied the list.
     */
    public function __construct(
        int $days,
        bool $unreached,
        \moodle_url $action,
        array $groups,
        array $reach,
        array $users,
        int $now,
        string $none
    ) {
        $this->days = $days;
        $this->unreached = $unreached;
        $this->action = $action;
        $this->groups = $groups;
        $this->reach = $reach;
        $this->users = $users;
        $this->now = $now;
        $this->none = $none;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/pending_page';
    }

    /**
     * The context of the template: plain texts and numbers, not escaped; HTML only in tablehtml.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'intro' => get_string('pendingintro', 'tool_vae'),
            'form' => [
                'action' => $this->action->out_omit_querystring(),
                'dayslabel' => get_string('pendingdays', 'tool_vae'),
                'days' => $this->days,
                'max' => pending_report::MAX_DAYS,
                'unreached' => $this->unreached,
                'unreachedlabel' => get_string('pendingunreached', 'tool_vae'),
                'show' => get_string('pendingshow', 'tool_vae'),
            ],
            'none' => $this->groups ? false : get_string($this->none, 'tool_vae'),
            'tablehtml' => $this->groups ? $output->render(new table($this->table())) : false,
        ];
    }

    /**
     * The table, the oldest first: what has waited longest comes first.
     *
     * @return \html_table
     */
    private function table(): \html_table {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-pending';
        // The legend a screen reader announces before the table, drawn for nobody else.
        $table->caption = get_string('pendingcaption', 'tool_vae');
        $table->captionhide = true;
        $table->head = [
            get_string('pendingcolcandidate', 'tool_vae'),
            get_string('pendingcolcount', 'tool_vae'),
            get_string('pendingcololdest', 'tool_vae'),
            get_string('pendingcoldays', 'tool_vae'),
            get_string('pendingcolreach', 'tool_vae'),
        ];
        $groups = $this->groups;
        uasort($groups, fn($a, $b) => $a['oldest'] <=> $b['oldest']);
        foreach ($groups as $candidateid => $group) {
            $user = $this->users[$candidateid] ?? null;
            $name = $user ? fullname($user) : (string) $candidateid;
            $table->data[] = [
                new cell_content([cell_content::link(
                    new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]),
                    $name
                )]),
                (int) $group['count'],
                new cell_content([cell_content::text(
                    display_date::format((int) $group['oldest'], get_string('strftimedate', 'langconfig'))
                )]),
                (int) floor(($this->now - $group['oldest']) / DAYSECS),
                (int) $this->reach[$candidateid],
            ];
        }

        return $table;
    }
}
