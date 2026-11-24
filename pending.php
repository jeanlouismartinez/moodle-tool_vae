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
 * The declarations that still await an attestation, by candidate (T-22).
 *
 * For whoever may read every dossier (tool/vae:viewany): it puts side by side what they can already read
 * one by one. It says that declarations wait and for how long, never whether a dossier is good.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_vae\local\pending_report;
use tool_vae\output\pending_page;

admin_externalpage_setup('toolvae_pending');
require_capability('tool/vae:viewany', context_system::instance());

$days = min(pending_report::MAX_DAYS, max(0, optional_param('days', 0, PARAM_INT)));
$unreached = optional_param('unreached', 0, PARAM_BOOL);

$renderer = $PAGE->get_renderer('tool_vae');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pendingheading', 'tool_vae'));

$rows = pending_report::awaiting($days);
$groups = pending_report::by_candidate($rows);
$none = 'pendingnone';
$reach = [];
$users = [];
if ($groups) {
    // How many managers each of these candidates' declarations would be announced to: a number, never a name.
    $reach = pending_report::reachable(array_keys($groups));
    if ($unreached) {
        $groups = array_filter($groups, fn($candidateid) => $reach[$candidateid] === 0, ARRAY_FILTER_USE_KEY);
        $none = 'pendingnoneunreached';
    }
}
if ($groups) {
    $users = $DB->get_records_list('user', 'id', array_keys($groups));
}
echo $renderer->render(new pending_page($days, (bool) $unreached, $PAGE->url, $groups, $reach, $users, time(), $none));
echo $OUTPUT->footer();
