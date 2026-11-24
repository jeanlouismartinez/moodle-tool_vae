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
 * The guide of one role (lot S5), below the heading of the page.
 *
 * The HTML is written by the template tool_vae/guide_page; this class gives it the plain texts of role_guide::guide(), which
 * the template escapes, then the sentence on the functions the administrator may open and the way back to the tab of the
 * role. It checks no right: guide.php refuses before anything is drawn (role_guide::require_open()).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class guide_page implements named_templatable, renderable {
    /** @var string A role that has a guide (role_guide::ROLES). */
    private string $role;

    /**
     * The guide of a role.
     *
     * @param string $role A role that has a guide.
     */
    public function __construct(string $role) {
        $this->role = $role;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/guide_page';
    }

    /**
     * The context of the template: plain texts, not escaped (the template escapes them).
     *
     * @param renderer_base $output
     * @return array
     * @throws \moodle_exception guideunknown, for a role that has no guide.
     */
    public function export_for_template(renderer_base $output): array {
        $guide = role_guide::guide($this->role);
        $sections = [];
        foreach ($guide->sections as $section) {
            $sections[] = [
                'id' => 'tool-vae-guide-' . $section->key,
                'title' => $section->title,
                'texts' => $section->texts,
                'hassteps' => $section->steps !== [],
                'steps' => $section->steps,
                'notes' => $section->notes,
                'haswords' => $section->words !== [],
                'words' => array_map(fn($word) => ['word' => $word->word, 'definition' => $word->definition], $section->words),
                // The url is given unescaped: the template escapes it once.
                'link' => $section->link === null ? false : [
                    'url' => $section->link->url->out(false),
                    'label' => $section->link->label,
                ],
            ];
        }
        $tab = role_guide::ROLES[$this->role];

        return [
            'intro' => $guide->intro,
            'sections' => $sections,
            'more' => get_string('guidemore', 'tool_vae'),
            'back' => [
                'url' => (new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => $tab]))->out(false),
                'label' => get_string('guideback', 'tool_vae', get_string('orgtab' . $tab, 'tool_vae')),
            ],
        ];
    }
}
