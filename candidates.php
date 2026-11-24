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
 * The candidates the current user follows (lot 7 bis).
 *
 * This page takes no user id from the request: it only ever lists the reader's own followed
 * candidates, as \tool_vae\local\access decides them. Anyone logged in may open it;
 * someone who follows nobody sees an empty state, and nothing about anyone.
 *
 * WHAT IT SHOWS, AND WHAT IT DOES NOT. Per candidate: how many declarations, and how many
 * await an attestation. A count of work to do for the manager, never a measure of the
 * candidate - no coverage rate, no threshold, nothing a jury could read as an opinion.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\local\access;
use tool_vae\local\candidate_overview;
use tool_vae\local\competency_gateway;
use tool_vae\output\candidates_page;

require_login();

$PAGE->set_url(new moodle_url('/admin/tool/vae/candidates.php'));
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mycandidates', 'tool_vae'));
$PAGE->set_heading(get_string('mycandidates', 'tool_vae'));

$renderer = $PAGE->get_renderer('tool_vae');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mycandidates', 'tool_vae'));

$enabled = competency_gateway::is_subsystem_enabled();
$candidateids = $enabled ? access::followed_candidates() : [];
if (!$enabled) {
    $screen = candidates_page::disabled();
} else if (!$candidateids) {
    $screen = candidates_page::nobody();
} else {
    // The blocs this reader is referent of (T-23): they may narrow the counts to the declarations on one of them. The
    // choice is checked against the reader's own list, so a value from the address bar names nothing they are not referent
    // of. Narrowing changes what is counted, never who is listed nor what the reader may read.
    $narrowing = candidate_overview::narrowing((int) $USER->id, optional_param('bloc', '', PARAM_TEXT));

    // A declaration awaits when no attestation currently stands: never signed, or every signature withdrawn.
    $counts = candidate_overview::counts($candidateids, $narrowing->fichecode, $narrowing->ranks);

    $users = $DB->get_records_list('user', 'id', $candidateids, 'lastname ASC, firstname ASC', '*');

    // The link to the same candidates in the organisation is drawn only here: in the empty states it would lead to a refusal.
    $screen = candidates_page::followed($users, $counts, $narrowing, $PAGE->url);
}
echo $renderer->render($screen);

echo $OUTPUT->footer();
