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
 * The page of the VAE roles (roles_install.php), below its heading, when no role is asked for (lot 7, MMR-520 ticket 4).
 *
 * What the page does (nothing is created by opening it), then the base presets and the optional ones, each group under
 * its heading, the optional ones with their warning: per preset its name and description, the rights it grants, and either
 * a button to ask for it or the sentence that it exists (a role that exists is never touched). The page reads whether each
 * role exists; this class words the presets for the template tool_vae/roles_install_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class roles_install_page implements named_templatable, renderable {
    /** @var \stdClass[] role_installer::presets(), keyed by shortname. */
    private array $presets;

    /** @var bool[] Shortname => whether the role exists. */
    private array $exists;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    /**
     * The page.
     *
     * @param \stdClass[] $presets role_installer::presets(): shortname, name, description, rights, optional.
     * @param bool[] $exists Shortname => role_installer::exists(), for each preset.
     * @param \moodle_url $url The address of the page, to which a role is asked for.
     */
    public function __construct(array $presets, array $exists, \moodle_url $url) {
        $this->presets = $presets;
        $this->exists = $exists;
        $this->url = $url;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/roles_install_page';
    }

    /**
     * The context of the template: plain texts, not escaped; HTML of the core only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $groups = [];
        foreach ([false, true] as $optional) {
            $subset = array_filter($this->presets, fn($preset) => $preset->optional === $optional);
            if (!$subset) {
                continue;
            }
            $groups[] = [
                'headinghtml' => $output->heading(get_string($optional ? 'rolesoptional' : 'rolesbase', 'tool_vae'), 3),
                'warning' => $optional ? get_string('rolesoptionalwarning', 'tool_vae') : false,
                'tablehtml' => $output->render(new table($this->table($subset, $output))),
            ];
        }

        return ['intro' => get_string('rolesintro', 'tool_vae'), 'groups' => $groups];
    }

    /**
     * The table of a group of presets.
     *
     * @param \stdClass[] $subset
     * @param renderer_base $output
     * @return \html_table
     */
    private function table(array $subset, renderer_base $output): \html_table {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-roles';
        $table->head = [
            get_string('rolescolrole', 'tool_vae'),
            get_string('rolescolrights', 'tool_vae'),
            get_string('rolescolstate', 'tool_vae'),
        ];
        foreach ($subset as $preset) {
            $rights = implode(', ', array_map(fn($capability) => get_capability_string($capability), $preset->rights));
            if (!empty($this->exists[$preset->shortname])) {
                $state = new cell_content([cell_content::span('small text-muted', get_string('rolesexists', 'tool_vae'))]);
            } else {
                $state = $output->single_button(
                    new \moodle_url($this->url, ['shortname' => $preset->shortname]),
                    get_string('rolescreatenamed', 'tool_vae', $preset->name),
                    'get'
                );
            }
            $table->data[] = [
                new cell_content([
                    cell_content::text((string) $preset->name),
                    cell_content::div('small text-muted', (string) $preset->description),
                ]),
                new cell_content([cell_content::text($rights)]),
                $state,
            ];
        }

        return $table;
    }
}
