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
 * The text typed, given back in fields that can be read and copied, for the page that has no form to give it back in.
 *
 * That is the case when every record has been hidden in Moodle since the form of the declaration was opened: no
 * competency can be chosen, so there is no form. The page says so, and gives the text back instead of letting it vanish
 * (hidden_frameworks::sent() reads what the request holds). Nothing when nothing was typed. Was
 * hidden_frameworks::kept_text() (lot 7, MMR-520 ticket 4).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class kept_text implements named_templatable, renderable {
    /** @var string The narrative. */
    private string $situation;

    /** @var string The detail on the setting. */
    private string $settingdetail;

    /**
     * The text kept.
     *
     * @param string $situation The narrative.
     * @param string $settingdetail The detail on the setting.
     */
    public function __construct(string $situation, string $settingdetail = '') {
        $this->situation = $situation;
        $this->settingdetail = $settingdetail;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/kept_text';
    }

    /**
     * Whether anything was typed, and so is given back (lot 7, wave 3b: declare_page asks it, never the drawing).
     *
     * @return bool
     */
    public function drawn(): bool {
        return trim($this->situation) !== '' || trim($this->settingdetail) !== '';
    }

    /**
     * The context of the template: each field typed, its text as it was typed (not escaped).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $fields = [];
        foreach (['situation' => $this->situation, 'settingdetail' => $this->settingdetail] as $field => $text) {
            if (trim($text) === '') {
                continue;
            }
            $fields[] = [
                'id' => 'tool_vae_kept_' . $field,
                // The writer of the core trimmed the label it wrote: so is it here.
                'label' => trim(get_string($field, 'tool_vae')),
                'rows' => $field === 'situation' ? 12 : 2,
                'text' => $text,
            ];
        }

        return [
            'show' => $fields !== [],
            'intro' => get_string('declarekeptintro', 'tool_vae'),
            'fields' => $fields,
        ];
    }
}
