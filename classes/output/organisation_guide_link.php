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
use tool_vae\local\role_guide;

/**
 * The link "Help" of a tab of the page organisation.php: to the guide of the role of that tab, for whoever may open that guide
 * (lot S5; lot 7, MMR-520 ticket 4, wave 4b).
 *
 * Drawn near the heading of the tab. The right is that of the tab itself (role_guide::can_open()), so a reader who sees the
 * tab sees its link; a tab with no guide, or a guide the reader may not open, draws nothing. Was
 * organisation_screen::guide_link().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class organisation_guide_link implements named_templatable, renderable {
    /** @var string An organisation::TAB_* key. */
    private string $tab;

    /**
     * The link of a tab.
     *
     * @param string $tab An organisation::TAB_* key.
     */
    public function __construct(string $tab) {
        $this->tab = $tab;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/organisation_guide_link';
    }

    /**
     * The context of the template: the link, or null when no link is due.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $role = role_guide::role_of_tab($this->tab);
        if ($role === null || !role_guide::can_open($role)) {
            return ['link' => null];
        }

        return ['link' => [
            'url' => role_guide::url($role)->out(false),
            'label' => get_string('guidelink_' . $role, 'tool_vae'),
        ]];
    }
}
