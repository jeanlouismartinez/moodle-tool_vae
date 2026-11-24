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
use tool_vae\local\feasibility_catalog;
use tool_vae\local\feasibility_overview;

/**
 * The page "Dossier de faisabilité" (faisabilite.php), below its heading (T-10, lot 1; lot 7, MMR-520 ticket 4).
 *
 * What the page is for; the table of the sections, each place with where it stands, when it was last written and the links
 * to write it (or read it, for what comes from the account) and to its history, a section to come said so, a repeated
 * section with the link to add a place; the button to the deposits; the way back to the dossier. It says what is there,
 * never whether it is enough (D-1). The page reads the overview before; this class words it for the template
 * tool_vae/feasibility_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class feasibility_page implements named_templatable, renderable {
    /** @var array[] feasibility_overview::for_candidate(). */
    private array $overview;

    /** @var int The vaeiste, who is the reader. */
    private int $candidateid;

    /**
     * The page.
     *
     * @param array[] $overview feasibility_overview::for_candidate().
     * @param int $candidateid The vaeiste, who is the reader.
     */
    public function __construct(array $overview, int $candidateid) {
        $this->overview = $overview;
        $this->candidateid = $candidateid;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/feasibility_page';
    }

    /**
     * The context of the template: plain texts and addresses, not escaped; HTML only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'intro' => get_string('faisabintro', 'tool_vae'),
            'tablehtml' => $output->render(new table($this->table())),
            'buttonhtml' => $output->single_button(
                new \moodle_url('/admin/tool/vae/deposit.php'),
                get_string('depositheading', 'tool_vae'),
                'get'
            ),
            'back' => [
                'url' => (new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $this->candidateid]))->out(false),
                'label' => get_string('faisabbacktodossier', 'tool_vae'),
            ],
        ];
    }

    /**
     * The words that say where a place stands, from what feasibility_overview returns for it.
     *
     * @param array $place
     * @return string
     */
    private static function state(array $place): string {
        switch ($place['state']) {
            case feasibility_overview::STATE_DRAFT:
                return get_string('faisabstatedraft', 'tool_vae');
            case feasibility_overview::STATE_VERSION:
                return get_string('faisabstateversion', 'tool_vae', $place['version']);
            case feasibility_overview::STATE_VERSION_DRAFT:
                return get_string('faisabstateversiondraft', 'tool_vae', $place['version']);
            case feasibility_overview::STATE_ACCOUNT:
                return get_string('faisabstateaccount', 'tool_vae');
            default:
                return get_string('faisabstateempty', 'tool_vae');
        }
    }

    /**
     * The table of the sections: section, state, last written, actions.
     *
     * @return \html_table
     */
    private function table(): \html_table {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-faisabilite';
        $table->caption = get_string('faisabcaption', 'tool_vae');
        $table->captionhide = true;
        $table->head = [
            get_string('faisabcolsection', 'tool_vae'),
            get_string('faisabcolstate', 'tool_vae'),
            get_string('faisabcollast', 'tool_vae'),
            get_string('faisabcolactions', 'tool_vae'),
        ];
        $table->colclasses = ['', '', '', ''];
        $text = fn(string $text): cell_content => new cell_content([cell_content::text($text)]);

        foreach ($this->overview as $code => $entry) {
            $label = get_string('faisabsection:' . $code, 'tool_vae');

            if (!$entry['open']) {
                $table->data[] = [$text($label), $text(get_string('faisabstateupcoming', 'tool_vae')), '', ''];
                continue;
            }

            foreach ($entry['places'] as $slot => $place) {
                $writeurl = new \moodle_url('/admin/tool/vae/section.php', ['section' => $code, 'slot' => $slot]);
                $name = feasibility_catalog::label($code, $slot);

                $actions = [cell_content::link($writeurl, get_string(
                    $place['state'] === feasibility_overview::STATE_ACCOUNT ? 'faisabview' : 'faisabwrite',
                    'tool_vae',
                    $name
                ))];
                if ($place['draftid']) {
                    // The two links stand apart by a middle dot, as before.
                    $actions[] = cell_content::text(' · ');
                    $actions[] = cell_content::link(
                        new \moodle_url('/admin/tool/vae/history.php', ['draftid' => $place['draftid']]),
                        get_string('faisabhistory', 'tool_vae', $name)
                    );
                }

                $table->data[] = [
                    $text($name),
                    $text(self::state($place)),
                    $place['modified'] ? $text(userdate($place['modified'], get_string('strftimedatetimeshort'))) : '',
                    new cell_content($actions),
                ];
            }

            if ($entry['repeated']) {
                if ($entry['places'] === []) {
                    $table->data[] = [$text($label), $text(get_string('faisabstateempty', 'tool_vae')), '', ''];
                }
                if ($entry['nextslot'] !== null) {
                    $table->data[] = [
                        '',
                        '',
                        '',
                        new cell_content([cell_content::link(
                            new \moodle_url('/admin/tool/vae/section.php', ['section' => $code, 'slot' => $entry['nextslot']]),
                            get_string('faisabadd', 'tool_vae', $label)
                        )]),
                    ];
                }
            }
        }

        return $table;
    }
}
