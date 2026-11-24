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

namespace tool_vae;

use tool_vae\local\organisation;

/**
 * The listeners of the hooks of the core (db/hooks.php).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /** @var string The key of the node "VAE" in the primary navigation; organisation.php asks the core to highlight it. */
    public const PRIMARY_NODE_KEY = 'toolvae';

    /**
     * Add the link "VAE" to the primary navigation, for whoever may open a tab of the organisation page (lot S3).
     *
     * Called on every page of the site, for every person. It says nothing while the site or the plugin is installed or
     * upgraded, when the capabilities it asks may not exist yet; the rule itself is organisation::entry_url(). A fault in
     * it must not take every page of the site down with it: it is reported to developers and the link is left out.
     *
     * @param \core\hook\navigation\primary_extend $hook
     * @return void
     */
    public static function extend_primary_navigation(\core\hook\navigation\primary_extend $hook): void {
        global $CFG;

        if (during_initial_install() || !empty($CFG->upgraderunning)) {
            return;
        }

        try {
            if (empty(get_config('tool_vae', 'version'))) {
                // The code is there, the plugin is not installed yet.
                return;
            }
            $url = organisation::entry_url();
            if ($url === null) {
                return;
            }
            $hook->get_primaryview()->add(
                get_string('navlink', 'tool_vae'),
                $url,
                \navigation_node::TYPE_CUSTOM,
                null,
                self::PRIMARY_NODE_KEY
            );
        } catch (\Throwable $e) {
            debugging('tool_vae: the link of the primary navigation was left out: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
