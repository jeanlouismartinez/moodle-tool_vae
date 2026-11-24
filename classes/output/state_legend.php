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
use tool_vae\local\presenter;

/**
 * The legend of the states of a declaration, once, above the declarations of the dossier (T-40; lot 7, MMR-520 ticket 4).
 *
 * A fold: each state (presenter::STATES), then the question waiting, with what it means. Was presenter::state_legend().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class state_legend implements named_templatable, renderable {
    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/state_legend';
    }

    /**
     * The context of the template: the heading of the fold and each state with its meaning.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $items = [];
        foreach (array_merge(presenter::STATES, ['questionopen']) as $state) {
            $items[] = [
                'state' => get_string('state:' . $state, 'tool_vae'),
                'help' => get_string('state:' . $state . '_help', 'tool_vae'),
            ];
        }

        return ['heading' => get_string('statelegendheading', 'tool_vae'), 'items' => $items];
    }
}
