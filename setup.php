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
 * Getting started: the steps of putting the plugin into service, in the order of the installation guide.
 *
 * The page writes nothing and reads no parameter: it has no action, hence no session key. Each step says its state in
 * words; a link is shown only to a reader who may open the screen it leads to (tool_vae\output\setup_page).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_vae\local\setup_checklist;
use tool_vae\output\setup_page;

admin_externalpage_setup('toolvae_setup');
$renderer = $PAGE->get_renderer('tool_vae');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('setupheading', 'tool_vae'));
echo $renderer->render(new setup_page(setup_checklist::steps()));
echo $OUTPUT->footer();
