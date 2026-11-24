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
 * Produce the archive of the accompanying structure of a vaeiste: their positionings, in a document of their own.
 *
 * Asked by the direction, or by a manager the direction authorized, for a vaeiste they follow. The page confirms, then produces;
 * the new archive replaces the one in place. The right is checked before the account is looked up, so that a refusal names no one.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\local\competency_gateway;
use tool_vae\local\structure_archive;
use tool_vae\output\structure_page;

require_login();

$candidateid = (int) required_param('userid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

if (!structure_archive::can_issue($candidateid)) {
    throw new \required_capability_exception(
        context_system::instance(),
        'tool/vae:issuestructure',
        'nopermissions',
        ''
    );
}
$candidate = core_user::get_user($candidateid, '*', MUST_EXIST);

$url = new moodle_url('/admin/tool/vae/structure.php', ['userid' => $candidateid]);
$dossierurl = new moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]);
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($candidateid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('structureheading', 'tool_vae'));
$PAGE->set_heading(fullname($candidate));
$renderer = $PAGE->get_renderer('tool_vae');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('structureheading', 'tool_vae'));

if (!competency_gateway::is_subsystem_enabled()) {
    echo $renderer->render(structure_page::disabled($dossierurl));
    echo $OUTPUT->footer();
    die();
}

if ($confirm) {
    require_sesskey();

    try {
        $file = structure_archive::issue($candidateid);
    } catch (\moodle_exception $e) {
        echo $renderer->render(structure_page::failed($e->getMessage(), $dossierurl));
        echo $OUTPUT->footer();
        die();
    }
    $registered = \tool_vae\local\exporter::registered($file);
    echo $renderer->render(structure_page::done($registered, $file, $dossierurl));
    echo $OUTPUT->footer();
    die();
}

echo $renderer->render(structure_page::intro((bool) structure_archive::current($candidateid)));
echo $OUTPUT->confirm(
    get_string('structureconfirm', 'tool_vae'),
    new moodle_url($url, ['confirm' => 1, 'sesskey' => sesskey()]),
    $dossierurl
);
echo $OUTPUT->footer();
