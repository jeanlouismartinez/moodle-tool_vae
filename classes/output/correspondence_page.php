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
use tool_vae\local\correspondence_manager;

/**
 * The page of the correspondences of a replaced record (passage.php), below its heading (T-30, lot 2; lot 7, MMR-520).
 *
 * What a correspondence is, the way back to the replaced records, the error of a decision refused, and the table of the
 * competencies of the replaced record: each with its declarations, the suggestion of the plugin and why, the decision taken
 * (by whom, when, with its note), and the form that decides or takes back (tool_vae\output\correspondence_decision_form). The page
 * checks the right, reads the competencies, decides and redirects before; this class words them for the template
 * tool_vae/correspondence_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class correspondence_page implements named_templatable, renderable {
    /** @var \stdClass[] correspondence_manager::overview(), keyed by competency id. */
    private array $rows;

    /** @var \stdClass[] correspondence_manager::targets(), keyed by competency id. */
    private array $targets;

    /** @var string|null The message of a decision refused, or null. */
    private ?string $error;

    /** @var \moodle_url The address of the page, to which a decision is sent. */
    private \moodle_url $self;

    /** @var \moodle_url The page of the replaced records. */
    private \moodle_url $back;

    /**
     * The page.
     *
     * @param \stdClass[] $rows correspondence_manager::overview().
     * @param \stdClass[] $targets correspondence_manager::targets().
     * @param string|null $error The message of a decision refused, as text, or null.
     * @param \moodle_url $self The address of the page.
     * @param \moodle_url $back The page of the replaced records.
     */
    public function __construct(array $rows, array $targets, ?string $error, \moodle_url $self, \moodle_url $back) {
        $this->rows = $rows;
        $this->targets = $targets;
        $this->error = $error;
        $this->self = $self;
        $this->back = $back;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/correspondence_page';
    }

    /**
     * The context of the template: plain texts and addresses, not escaped; HTML only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'intro' => get_string('passageintro', 'tool_vae'),
            'back' => ['url' => $this->back->out(false), 'label' => get_string('passageback', 'tool_vae')],
            // The notification of the core places its message as HTML: the message is escaped, as before.
            'errorhtml' => $this->error === null ? false : $output->notification(s($this->error), notification::NOTIFY_ERROR),
            'tablehtml' => $output->render(new table($this->table())),
        ];
    }

    /**
     * A competency as shown: its code, then its label as the core formats it.
     *
     * @param \stdClass $competency
     * @return array[] Parts of cell_content.
     */
    private static function show(\stdClass $competency): array {
        return [
            cell_content::text($competency->idnumber . ' — '),
            cell_content::html(format_string($competency->label ?? $competency->shortname)),
        ];
    }

    /**
     * The table of the competencies: competency, declarations, suggestion, decision, the form.
     *
     * @return \html_table
     */
    private function table(): \html_table {
        $options = [['value' => '', 'text' => get_string('passagenoequivalent', 'tool_vae'), 'labelhtml' => '']];
        foreach ($this->targets as $id => $target) {
            $options[] = [
                'value' => (string) $id,
                'text' => $target->idnumber . ' — ',
                'labelhtml' => format_string($target->label ?? $target->shortname),
            ];
        }

        $table = new \html_table();
        $table->head = [
            get_string('passagecolfrom', 'tool_vae'),
            get_string('passagecoldeclarations', 'tool_vae'),
            get_string('passagecolproposed', 'tool_vae'),
            get_string('passagecoldecision', 'tool_vae'),
            get_string('passagecoldecide', 'tool_vae'),
        ];
        foreach ($this->rows as $id => $row) {
            if ($row->proposed === null) {
                $proposed = [cell_content::text(get_string('passagenoproposal', 'tool_vae'))];
            } else {
                $proposed = array_merge(self::show($row->proposed), [cell_content::text(' (' . get_string(
                    $row->reason === correspondence_manager::REASON_RANK ? 'passagereasonrank' : 'passagereasonlabel',
                    'tool_vae'
                ) . ')')]);
            }

            if ($row->decision === null) {
                $decision = [cell_content::text(get_string('passagenodecision', 'tool_vae'))];
            } else {
                $decision = $row->target ? self::show($row->target)
                    : [cell_content::text(get_string('passagetargetgone', 'tool_vae'))];
                $decision[] = cell_content::div('small text-muted', get_string('passagedecidedby', 'tool_vae', (object) [
                    'who' => $row->decidername === '' ? get_string('passagenobody', 'tool_vae') : $row->decidername,
                    'when' => userdate((int) $row->decision->decidedat, get_string('strftimedatefullshort', 'langconfig')),
                ]));
                if ($row->decision->note !== '') {
                    $decision[] = cell_content::div('small', $row->decision->note);
                }
            }

            $selected = $row->decision ? (int) $row->decision->tocompetencyid : ($row->proposed ? (int) $row->proposed->id : '');
            $table->data[] = [
                new cell_content(self::show($row->competency)),
                $row->declarations,
                new cell_content($proposed),
                new cell_content($decision),
                new correspondence_decision_form(
                    $this->self,
                    (int) $id,
                    (string) $row->competency->idnumber,
                    $options,
                    (string) $selected,
                    $row->decision !== null
                ),
            ];
        }

        return $table;
    }
}
