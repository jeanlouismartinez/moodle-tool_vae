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
use tool_vae\local\consent_manager;

/**
 * The page "Accord pour annoter" (consent.php), below its heading (lot 1b; lot 7, MMR-520 ticket 4).
 *
 * What the consent is; the people who may receive it, or hold it still, with the state of each and, for the vaeiste only, a
 * button to give it or take it back (or one sentence when there is nobody); the dated history, a line restored from an
 * archive said so; the way back to the dossier. Nothing is computed: no total, no rate. The page checks the right, reads the
 * lines, the people and their names, sends and redirects before; this class words them for the template
 * tool_vae/consent_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class consent_page implements named_templatable, renderable {
    /** @var int[] The people to show, in the order of their ids. */
    private array $people;

    /** @var \stdClass[] consent_manager::current(): the line that stands, by person. */
    private array $current;

    /** @var int[] consent_manager::eligible(): who may receive the consent. */
    private array $eligible;

    /** @var bool Whether the reader is the vaeiste, who gives and takes back. */
    private bool $canmanage;

    /** @var \stdClass[] consent_manager::history(): every line, in the order written. */
    private array $history;

    /** @var string[] The name of each person, by user id; 0 for a line that names nobody. */
    private array $names;

    /** @var \moodle_url The address of the page, to which a consent is sent. */
    private \moodle_url $url;

    /** @var \moodle_url The dossier of the vaeiste. */
    private \moodle_url $dossier;

    /**
     * The page.
     *
     * @param int[] $people Who may receive the consent and who holds it still, in the order of their ids.
     * @param \stdClass[] $current consent_manager::current().
     * @param int[] $eligible consent_manager::eligible(); empty for a reader who is not the vaeiste.
     * @param bool $canmanage consent_manager::can_manage().
     * @param \stdClass[] $history consent_manager::history().
     * @param string[] $names The name of each person of $people and of $history, as text ("unknown user" for one gone), by
     *     user id; 0 for a line that names nobody.
     * @param \moodle_url $url The address of the page.
     * @param \moodle_url $dossier The dossier of the vaeiste.
     */
    public function __construct(
        array $people,
        array $current,
        array $eligible,
        bool $canmanage,
        array $history,
        array $names,
        \moodle_url $url,
        \moodle_url $dossier
    ) {
        $this->people = $people;
        $this->current = $current;
        $this->eligible = $eligible;
        $this->canmanage = $canmanage;
        $this->history = $history;
        $this->names = $names;
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
        return 'tool_vae/consent_page';
    }

    /**
     * The context of the template: plain texts and addresses, not escaped; HTML only in the keys ...html (tables).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'intro' => get_string('consentintro', 'tool_vae'),
            'none' => $this->people ? false : get_string('consentnone', 'tool_vae'),
            'tablehtml' => $this->people ? $output->render(new table($this->people_table($output))) : false,
            'history' => $this->history ? [
                'label' => get_string('consenthistory', 'tool_vae'),
                'tablehtml' => $output->render(new table($this->history_table())),
            ] : false,
            'back' => ['url' => $this->dossier->out(false), 'label' => get_string('selfposbackdossier', 'tool_vae')],
        ];
    }

    /**
     * The name of a person, as text.
     *
     * @param int|null $userid
     * @return string
     */
    private function name(?int $userid): string {
        return $this->names[$userid ?? 0] ?? get_string('unknownuser');
    }

    /**
     * The table of the people, with the state of each and, for the vaeiste, the button to give or take back.
     *
     * @param renderer_base $output
     * @return \html_table
     */
    private function people_table(renderer_base $output): \html_table {
        $table = new \html_table();
        $table->head = [get_string('fullnameuser'), get_string('status')];
        if ($this->canmanage) {
            $table->head[] = '';
        }
        foreach ($this->people as $personid) {
            $state = isset($this->current[$personid]) ? (string) $this->current[$personid]->state : '';
            $label = $state === consent_manager::GIVEN ? 'consentgiven'
                : ($state === consent_manager::WITHDRAWN ? 'consentwithdrawn' : 'consentnone');
            $row = [
                new cell_content([cell_content::text($this->name($personid))]),
                new cell_content([cell_content::text(get_string($label, 'tool_vae'))]),
            ];
            if ($this->canmanage) {
                $button = '';
                if ($state === consent_manager::GIVEN) {
                    $button = $output->single_button(
                        new \moodle_url($this->url, ['action' => 'withdraw', 'managerid' => $personid]),
                        get_string('consentwithdraw', 'tool_vae'),
                        'post'
                    );
                } else if (in_array($personid, $this->eligible, true)) {
                    $button = $output->single_button(
                        new \moodle_url($this->url, ['action' => 'give', 'managerid' => $personid]),
                        get_string('consentgive', 'tool_vae'),
                        'post'
                    );
                }
                $row[] = $button;
            }
            $table->data[] = $row;
        }

        return $table;
    }

    /**
     * The table of the history: date, person (or "restored"), state.
     *
     * @return \html_table
     */
    private function history_table(): \html_table {
        $table = new \html_table();
        $table->head = [get_string('date'), get_string('fullnameuser'), get_string('status')];
        foreach ($this->history as $line) {
            $table->data[] = [
                new cell_content([cell_content::text(
                    userdate((int) $line->timecreated, get_string('strftimedatetime', 'langconfig'))
                )]),
                new cell_content([cell_content::text($line->restored
                    ? get_string('consentrestored', 'tool_vae')
                    : $this->name($line->managerid ? (int) $line->managerid : null))]),
                new cell_content([cell_content::text(
                    get_string($line->state === consent_manager::GIVEN ? 'consentgiven' : 'consentwithdrawn', 'tool_vae')
                )]),
            ];
        }

        return $table;
    }
}
