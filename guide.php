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

/**
 * The guide of one page for a role (lot S5): the vaeiste, the manager or the direction.
 *
 * The guide of a role is shown to whoever may open the tab of that role on the organisation page, and refused to anybody
 * else with the refusal of that tab (role_guide::require_open()); a role the page does not know is refused, never replaced
 * by another. The refusal comes before anything of the page is set, so that it is the core's own error page. The page
 * takes no user id, and reads and writes no data of a person: its text is the language strings of the plugin.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\local\role_guide;
use tool_vae\output\guide_page;

require_login();

$role = role_guide::named(required_param('role', PARAM_ALPHA));
role_guide::require_open($role);

$title = get_string('guide' . $role . '_title', 'tool_vae');
$PAGE->set_url(role_guide::url($role));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title);
$PAGE->set_heading($title);
// The link "VAE" of the primary navigation is the active entry, as on the organisation page the guide belongs to (lot S3).
$PAGE->set_primary_active_tab(\tool_vae\hook_callbacks::PRIMARY_NODE_KEY);
$renderer = $PAGE->get_renderer('tool_vae');

echo $OUTPUT->header();
echo $renderer->render(new guide_page($role));
echo $OUTPUT->footer();
