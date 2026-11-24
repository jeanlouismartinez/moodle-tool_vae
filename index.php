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
 * Administration overview.
 *
 * admin_externalpage_setup() is what actually protects this page: declaring it in
 * settings.php only governs the menu entry. Omitting the call leaves the page reachable
 * by anyone who guesses the URL.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_vae\local\competency_gateway;
use tool_vae\local\record_remover;
use tool_vae\local\source_frameworks;
use tool_vae\output\index_page;

admin_externalpage_setup('toolvae');

$renderer = $PAGE->get_renderer('tool_vae');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_vae'));

// The link to the setup comes before the check of the competencies, so that it stays visible when they are disabled (lot S1).
if (!competency_gateway::is_subsystem_enabled()) {
    // Acceptance criterion 10: an explanation the administrator can act on, never a core
    // exception surfacing in the interface.
    echo $renderer->render(index_page::disabled());
    echo $OUTPUT->footer();
    die();
}

$sources = $DB->get_records('tool_vae_source', null, 'fichecode ASC');

// The empty state is the one most often forgotten, and the one most visible in production: said by the page of output.
$sorted = null;
if ($sources) {
    // A record whose framework is gone from the core - deleted in tool_lp, or never created because its first
    // import failed - keeps its row and lists no competency anywhere. Said here, with the ways out: import it again,
    // or, when the file was imported by mistake and no candidate has left a trace on it, remove its row.
    $missing = source_frameworks::missing($sources);
    if ($missing) {
        $canremove = has_capability('tool/vae:import', context_system::instance());
        // Without the right to import there is no button and nothing to explain about one: every row is simply kept.
        $sorted = (new record_remover())->sort_by_refusal($canremove ? $missing : []);
        // Two causes keep a button away, and each is said for the rows it applies to: a framework of Moodle that carries the
        // code, and a trace of a candidate. Told one for the other, the manager looks for what is not there.
        $sorted = (object) [
            'kept' => array_values(array_diff($missing, $sorted->removable)),
            'framework' => $sorted->framework,
            'traces' => $sorted->traces,
            'removable' => $sorted->removable,
        ];
    }
}

// Two ways in, for whoever may import, and the first is the one to use: the records of France compétences from its daily
// export, with nothing to type but the numbers. The file of four columns stays, as a special case (a record prepared by
// hand). A GET opens a screen: no request goes out and nothing is written from here.
$canimport = has_capability('tool/vae:import', context_system::instance());

echo $renderer->render(index_page::records($sources, $sorted, $canimport));
echo $OUTPUT->footer();
