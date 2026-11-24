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
 * The page of the managers authorized to produce the archive of the structure (booklet_authorize.php), below its heading
 * (lot 7, MMR-520 ticket 4).
 *
 * What an authorization is; then one sentence when nobody may be authorized, or the table of the managers with the state
 * of each and the button that authorizes or takes back (tool_vae\output\booklet_authorize_form). The page checks the rights,
 * sends and redirects, reads the managers, their names and whether each stands before; this class words them for the
 * template tool_vae/booklet_authorize_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class booklet_authorize_page implements named_templatable, renderable {
    /** @var array[] The managers, each {id, name, stands}, in the order read. */
    private array $managers;

    /** @var \moodle_url The address of the page, to which a decision is sent. */
    private \moodle_url $url;

    /**
     * The page.
     *
     * @param array[] $managers Each ['id' => int, 'name' => string (as text), 'stands' => bool], as the page read them.
     * @param \moodle_url $url The address of the page.
     */
    public function __construct(array $managers, \moodle_url $url) {
        $this->managers = $managers;
        $this->url = $url;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/booklet_authorize_page';
    }

    /**
     * The context of the template: plain texts, not escaped; HTML only in the key tablehtml.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'intro' => get_string('bookletauthintro', 'tool_vae'),
            'none' => $this->managers ? false : get_string('bookletauthnone', 'tool_vae'),
            'tablehtml' => $this->managers ? $output->render(new table($this->table())) : false,
        ];
    }

    /**
     * The table of the managers: name, state, the button.
     *
     * @return \html_table
     */
    private function table(): \html_table {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-bookletauth';
        $table->head = [
            get_string('bookletauthcolmanager', 'tool_vae'),
            get_string('bookletauthcolstatus', 'tool_vae'),
            get_string('bookletauthcolaction', 'tool_vae'),
        ];
        foreach ($this->managers as $manager) {
            $stands = (bool) $manager['stands'];
            $table->data[] = [
                new cell_content([cell_content::text((string) $manager['name'])]),
                new cell_content([cell_content::text(
                    get_string($stands ? 'bookletauthstands' : 'bookletauthnotyet', 'tool_vae')
                )]),
                new booklet_authorize_form($this->url, (int) $manager['id'], $stands),
            ];
        }

        return $table;
    }
}
