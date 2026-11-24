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
 * The page of the VAE roles (roles_install.php) when one role is asked for, above the confirmation (lot 7).
 *
 * What will be created, and with which rights, each named in words and by its capability; nothing existing is changed. The
 * confirmation itself is the one of the core ($OUTPUT->confirm()), drawn by the page below this one, as before. This class
 * words the preset for the template tool_vae/roles_install_confirm_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class roles_install_confirm_page implements named_templatable, renderable {
    /** @var \stdClass The preset asked for. */
    private \stdClass $preset;

    /**
     * The page.
     *
     * @param \stdClass $preset A preset of role_installer::presets(): name, rights.
     */
    public function __construct(\stdClass $preset) {
        $this->preset = $preset;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/roles_install_confirm_page';
    }

    /**
     * The context of the template: plain texts, not escaped.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'what' => get_string('rolesconfirmwhat', 'tool_vae', $this->preset->name),
            'rights' => array_values(array_map(
                fn($capability) => get_capability_string($capability) . ' (' . $capability . ')',
                $this->preset->rights
            )),
        ];
    }
}
