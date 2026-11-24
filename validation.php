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
 * The preparation of the validation dossier, record by record, bloc by bloc (T-11, lot 1, D-15).
 *
 * NOT THE OFFICIAL DOSSIER, and the page says so first. For writing, like section.php, the page never takes the candidate
 * from the request: the writer is the candidate. A reader names the vaeiste (userid) and reads, never writes (D-1b-G): who
 * may view the dossier reads the latest version of each section and, apart, the draft in progress (D-1b-H); the site
 * administrator is refused. It creates nothing - a section is created when it is opened to be written - and
 * it says what is there, never whether it is enough (D-1): no count, no bar, no "complete".
 *
 * Beside the "describe" section of a bloc, the candidate's own declarations on that bloc, read only: what they have
 * already said and had attested. They are referred to, never copied (D-15).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\local\access;
use tool_vae\local\draft_manager;
use tool_vae\local\validation_catalog;
use tool_vae\local\validation_status;
use tool_vae\output\validation_page;

require_login();

$candidateid = (int) optional_param('userid', $USER->id, PARAM_INT);
// A reader who is not the author (D-1b-G): whoever may view the dossier reads the preparation, open or closed, and writes
// nothing. The author writes, and only while the preparation is open.
$reading = $candidateid !== (int) $USER->id;
if ($reading) {
    access::require_can_view($candidateid);
    if (!draft_manager::can_read_doctype($candidateid, draft_manager::DOCTYPE_VALIDATION)) {
        throw new moodle_exception('errordraftrefused', 'tool_vae');
    }
} else {
    draft_manager::require_can_write($candidateid);
    if (!draft_manager::validation_open()) {
        throw new moodle_exception('errorvalidationclosed', 'tool_vae');
    }
}
$context = context_user::instance($candidateid);
$candidate = core_user::get_user($candidateid, '*', MUST_EXIST);
// The parameters every link of this page carries: the reader stays on the dossier they read.
$base = $reading ? ['userid' => $candidateid] : [];

$fichecode = optional_param('fiche', '', PARAM_ALPHANUMEXT);

$url = new moodle_url('/admin/tool/vae/validation.php', $base + ($fichecode === '' ? [] : ['fiche' => $fichecode]));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('validationtitle', 'tool_vae'));
$PAGE->set_heading(fullname($candidate));

// The records this candidate declared on: the preparation follows them.
$codes = $DB->get_fieldset_sql(
    'SELECT DISTINCT fichecode FROM {tool_vae_evidence} WHERE candidateid = :c ORDER BY fichecode',
    ['c' => $candidateid]
);

echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('tool_vae');
echo $OUTPUT->heading($reading
    ? s(get_string('validationreadheading', 'tool_vae', fullname($candidate)))
    : get_string('validationtitle', 'tool_vae'));
// Who wrote this, for whom, and what it is not; the same words on every screen, the PDF, the copy and the archive (MC-17).
echo $renderer->render(validation_page::head($reading));

if ($fichecode === '') {
    echo $renderer->render(validation_page::records($reading, $candidateid, $codes));
    echo $OUTPUT->footer();
    die();
}

// A record the candidate did not declare on, or one this site does not hold, is not offered.
$structure = in_array($fichecode, $codes, true) ? validation_catalog::structure($fichecode) : null;
if ($structure === null) {
    throw new moodle_exception('errordraftsection', 'tool_vae');
}

// The sections already written, by section code.
$written = [];
foreach (
    $DB->get_records('tool_vae_draft', [
        'candidateid' => $candidateid, 'doctype' => draft_manager::DOCTYPE_VALIDATION, 'fichecode' => $fichecode,
    ]) as $row
) {
    $written[$row->section] = $row;
}

// Where each section stands, in words: never a verdict, only whether something is there. The rule - what a draft in progress
// is, whose date the version carries - is validation_status's; the list of the dossier de faisabilité follows it in its own words.
$status = validation_status::for_record($candidateid, $fichecode);

// The declarations of the candidate on each bloc (on the whole record when it is drawn with no bloc), read only.
$claims = [];
$blocs = $structure->byblocs ? $structure->blocs : ['' => (object) ['competencyids' => []]];
foreach ($blocs as $rank => $bloc) {
    $params = ['c' => $candidateid, 'f' => $fichecode];
    $where = 'candidateid = :c AND fichecode = :f';
    if ($bloc->competencyids) {
        [$insql, $inparams] = $DB->get_in_or_equal($bloc->competencyids, SQL_PARAMS_NAMED);
        $where .= " AND competencyid $insql";
        $params += $inparams;
    }
    $claims[(string) $rank] = $DB->get_records_select('tool_vae_evidence', $where, $params, 'timecreated ASC, id ASC');
}

echo $renderer->render(validation_page::record($reading, $candidateid, $fichecode, $structure, $written, $status, $claims));
echo $OUTPUT->footer();
