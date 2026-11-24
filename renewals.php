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
 * The certifications whose date has passed (T-90, plan 7).
 *
 * For whoever may read every dossier AND certify: it puts side by side what they can already read one by one. It says that
 * a date given by the management has passed, counted on the day of the reading - never whether a dossier is good: no total,
 * no colour, no ranking of people, the earliest last day first. Nothing is written, sent or stored.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_vae\local\certification_manager;
use tool_vae\local\renewal_report;
use tool_vae\output\renewals_page;

admin_externalpage_setup('toolvae_renewals');
$system = context_system::instance();
require_capability('tool/vae:viewany', $system);
require_capability('tool/vae:certify', $system);

$today = certification_manager::today();

$renderer = $PAGE->get_renderer('tool_vae');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('renewalsheading', 'tool_vae'));
// Nobody certifies themselves, and reading one's own dossier is another right: no link is offered for one's own line.
echo $renderer->render(new renewals_page($today, renewal_report::rows($today), (int) $USER->id));
echo $OUTPUT->footer();
