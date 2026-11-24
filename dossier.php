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
 * A candidate's dossier.
 *
 * This page takes a user id from the request, so it is exactly the shape that leaks when
 * ownership is inferred from an inherited capability. \tool_vae\local\access
 * compares identities in PHP before deciding which capability applies.
 *
 * WHAT THIS PAGE IS AT LOT 4, AND WHAT IT IS NOT. It lists the claims and their
 * attestations, and it carries the links without which attesting and withdrawal would
 * exist in the code and nowhere a person can reach. That is all it is meant to be. The
 * restitution screen proper - grouping by competency, the accessible presentation, the
 * keyboard and screen-reader pass - is lot 9, and nothing here should be mistaken for it.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\local\access;
use tool_vae\local\anchor;
use tool_vae\local\attestation_manager;
use tool_vae\local\backup;
use tool_vae\local\carry_manager;
use tool_vae\local\competency_gateway;
use tool_vae\local\consent_manager;
use tool_vae\local\coverage;
use tool_vae\local\deposit_manager;
use tool_vae\local\dossier_importer;
use tool_vae\local\draft_manager;
use tool_vae\local\presenter;
use tool_vae\local\request_manager;
use tool_vae\local\scope_manager;
use tool_vae\local\situation_manager;
use tool_vae\local\situation_screen;

require_login();

// Cast: the default is the session's own id, a string, and the page compares identities with ===.
$candidateid = (int) optional_param('userid', $USER->id, PARAM_INT);

// THE RIGHT TO READ IS CHECKED BEFORE THE ACCOUNT IS LOOKED UP. The other way round, an id nobody
// holds answered `record not found` where an existing one answered `nopermissions`, and anyone
// could tell which ids are accounts. access refuses an id that is no account exactly as a dossier
// one may not read, so past the check the account exists.
$context = access::require_can_view($candidateid);
$candidate = core_user::get_user($candidateid, '*', MUST_EXIST);

$url = new moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mydossier', 'tool_vae'));
$PAGE->set_heading(fullname($candidate));

echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('tool_vae');
echo $OUTPUT->heading(get_string('mydossier', 'tool_vae'));

if (!competency_gateway::is_subsystem_enabled()) {
    echo $renderer->render(\tool_vae\output\dossier_page::disabled());
    echo $OUTPUT->footer();
    die();
}

$isown = ($candidateid === (int) $USER->id);

// Whether this reader may attest. Never true on one's own dossier, whatever the roles
// say: the check that matters is in attestation_manager, this one only decides what to draw.
$mayattest = !$isown && access::can_attest($candidateid);

$claims = $DB->get_records('tool_vae_evidence', ['candidateid' => $candidateid], 'timecreated ASC, id ASC');

// The coverage is the candidate's alone, on their own dossier - decided with the project owner on
// 12 October 2026. Read by a manager or the direction, the same counts become a gauge on a person,
// which is the verdict this plugin refuses to give. A session of "log in as" makes $USER the candidate while the
// reader is somebody else: it is refused here as it is on the pathway page (decision of 3 November 2026).
$coverage = null;
if ($isown && $claims && !\core\session\manager::is_loggedinas()) {
    $coverage = new \tool_vae\output\coverage(coverage::for_candidate($candidateid));
}

// What the candidate says is outside their activity (T-42): read by whoever reads the dossier, apart from the coverage.
$marks = scope_manager::for_candidate($candidateid);
$mayscope = $isown && has_capability('tool/vae:declare', $context);
$scope = new \tool_vae\output\scope_box(
    $marks,
    scope_manager::labels($marks),
    $mayscope,
    !empty(get_config('tool_vae', 'scopeopen'))
);

// A block per declaration; none: the class says there is none yet, and draws the legend of the states above the blocks.
$blocks = [];
if ($claims) {
    $requests = new request_manager();
    $carries = new carry_manager();
    foreach ($claims as $claim) {
        // Computed on sight, not read from a flag: the candidate can change the core record
        // at any moment through tool_lp, unlinking raises no event at all, and a dossier that
        // asserted a link the platform no longer holds would mislead the one person it exists
        // to inform. Three queries per claim - measured, not estimated; it was five before the
        // core record stopped being read twice - on a page that shows a handful.
        $warning = new \tool_vae\output\anchor_warning($claim, anchor::divergence($claim));

        $attestations = attestation_manager::attestations((int) $claim->id);
        $asked = $requests->for_evidence($claim);
        $state = new \tool_vae\output\state_label(
            $attestations,
            request_manager::count_open((int) $claim->id)
        );

        $carrynotes = $carries->notes($claim);
        $notes = new \tool_vae\output\carry_notes($carrynotes);
        $declaration = new \tool_vae\output\declaration($claim, $context);

        $history = new \tool_vae\output\attestations($attestations);
        $carryacts = [];
        foreach (['carriedto', 'carriedfrom'] as $direction) {
            if ($carrynotes->$direction) {
                $carryacts[] = ['time' => (int) $carrynotes->$direction->carriedat, 'act' => $direction];
            }
        }
        $chronology = new \tool_vae\output\chronology($claim, $attestations, $asked, $carryacts);

        $standing = attestation_manager::active_attestation((int) $claim->id);

        // The way on the attestation, one at most, drawn by the class.
        $act = null;
        if ($standing) {
            // Drawn for whoever the rule of attestation_manager::require_can_withdraw() names: the
            // site administrator, a holder of tool/vae:withdrawany, or - when the site setting is on
            // - the signatory for their own attestation. Not tied to the right to attest:
            // a coordinator may withdraw without ever signing.
            $requestopen = ($standing->withdrawreqat ?? null) !== null;
            $issignatory = $standing->attestantid !== null && (int) $standing->attestantid === (int) $USER->id;

            if (!$isown && $issignatory && $requestopen) {
                // A colleague asked this signatory to withdraw: two buttons, accept or refuse.
                $act = \tool_vae\output\dossier_claim::yours(
                    new moodle_url('/admin/tool/vae/withdraw.php', ['attestationid' => $standing->id]),
                    new moodle_url('/admin/tool/vae/withdrawrequest.php', [
                        'attestationid' => $standing->id,
                        'action' => 'refuse',
                        'sesskey' => sesskey(),
                    ])
                );
            } else if (!$isown && attestation_manager::may_withdraw($standing, $candidateid)) {
                $act = \tool_vae\output\dossier_claim::withdraw(
                    new moodle_url('/admin/tool/vae/withdraw.php', ['attestationid' => $standing->id])
                );
            } else if (
                !$isown && $mayattest && !$issignatory && $standing->attestantid !== null
                    && !attestation_manager::signatory_reachable($standing)
            ) {
                // WHY BEFORE THE PENDING SENTENCE: a request to a signatory who is gone (deleted, or suspended after the
                // request was made) waits for nobody. The reader is sent to the one person who can still withdraw, the
                // administrator, instead of a button that is no longer offered or a sentence that promises an answer.
                $act = \tool_vae\output\dossier_claim::nosignatory();
            } else if (!$isown && $requestopen && $mayattest) {
                $act = \tool_vae\output\dossier_claim::pending();
            } else if (!$isown && attestation_manager::may_request_withdrawal($standing, $candidateid)) {
                $act = \tool_vae\output\dossier_claim::request(
                    new moodle_url('/admin/tool/vae/withdrawrequest.php', [
                        'attestationid' => $standing->id,
                        'action' => 'request',
                        'sesskey' => sesskey(),
                    ])
                );
            }
        } else if ($mayattest) {
            $act = \tool_vae\output\dossier_claim::attest(
                new moodle_url('/admin/tool/vae/attest.php', ['evidenceid' => $claim->id]),
                presenter::attest_label($attestations)
            );
        }

        // A declaration on a replaced record, with a correspondence decided: its owner may carry it over (T-30,
        // lot 3). Never offered to anyone else, never done by itself.
        $carry = null;
        if ($isown && $carries->offer($claim)) {
            $carry = new moodle_url('/admin/tool/vae/carry.php', ['evidenceid' => $claim->id]);
        }

        // The questions and answers on this declaration (T-20): a link for whoever reads, with how many
        // still wait. Never a state of the declaration and never a reason it may not be attested.
        $waiting = request_manager::count_open((int) $claim->id);
        $precision = new moodle_url('/admin/tool/vae/precision.php', ['evidenceid' => $claim->id]);

        // The supporting pieces (T-13): a link for whoever reads the dossier when a piece is there, and for the candidate
        // also when adding is open.
        $active = \tool_vae\local\piece_manager::active_for_evidence((int) $claim->id);
        $pieces = count($active);
        $piecesurl = null;
        $late = 0;
        if ($pieces || ($isown && \tool_vae\local\piece_manager::is_open())) {
            // Those the signature standing does not cover: said where the reader works, whether or not the message arrived.
            $standing = attestation_manager::active_attestation((int) $claim->id);
            if ($standing !== null && $pieces) {
                $covered = \tool_vae\local\piece_manager::covered_by((int) $standing->id);
                $late = count(array_filter($active, fn($piece) => !array_key_exists((int) $piece->id, $covered)));
            }
            $piecesurl = new moodle_url('/admin/tool/vae/piece.php', ['evidenceid' => $claim->id]);
        }

        $blocks[] = new \tool_vae\output\dossier_claim(
            $warning,
            $state,
            $notes,
            $declaration,
            $history,
            $chronology,
            $act,
            $carry,
            $precision,
            $waiting,
            $piecesurl,
            $pieces,
            $late
        );
    }
}

// The positionings of the structure (T-90, lot 3): the evaluation of each competency and the certification of each bloc, on the
// tree of the records the candidate declared on. Read by whoever reads the dossier; never a validation, which the jury alone gives.
$positioning = new \tool_vae\output\positioning_section($candidateid, $context);

// The buttons, each by its address under its own condition, null when it is not offered; the class draws them in this order.
$buttons = [];

if ($isown) {
    $buttons['declare'] = new moodle_url('/admin/tool/vae/declare.php');
    // The pathway of the candidate (T-90): theirs alone, so only on their own dossier and not in a session of "log in as".
    if (!\core\session\manager::is_loggedinas()) {
        $buttons['pathway'] = new moodle_url('/admin/tool/vae/organisation.php');
    }
}

if ($claims && access::can_export($candidateid)) {
    $buttons['export'] = new moodle_url('/admin/tool/vae/export.php', ['userid' => $candidateid]);
}

// The sections of the dossier de faisabilité (T-10): one's own, written by the candidate alone.
if ($candidateid === (int) $USER->id && draft_manager::can_write($candidateid)) {
    $buttons['faisabilite'] = new moodle_url('/admin/tool/vae/faisabilite.php');
}

// The preparation of the validation dossier (T-11): one's own, not the official dossier of the certifier.
if (
    $candidateid === (int) $USER->id && $claims && draft_manager::can_write($candidateid)
        && draft_manager::validation_open()
) {
    $buttons['validation'] = new moodle_url('/admin/tool/vae/validation.php');
}

// The same preparation, read by whoever may view the dossier (D-1b-G), once the vaeiste has begun it.
if (
    $candidateid !== (int) $USER->id
        && draft_manager::can_read_doctype($candidateid, draft_manager::DOCTYPE_VALIDATION)
        && $DB->record_exists('tool_vae_draft', ['candidateid' => $candidateid, 'doctype' => draft_manager::DOCTYPE_VALIDATION])
) {
    $buttons['validationread'] = new moodle_url('/admin/tool/vae/validation.php', ['userid' => $candidateid]);
}

// The work situations (lot 1b): the vaeiste's own, to write; read by whoever may read the preparation, once there is one.
if ($candidateid === (int) $USER->id && situation_manager::can_write($candidateid)) {
    $buttons['situations'] = situation_screen::url($candidateid);
} else if (
    $candidateid !== (int) $USER->id
        && draft_manager::can_read_doctype($candidateid, draft_manager::DOCTYPE_VALIDATION)
        && $DB->record_exists(situation_manager::TABLE, ['candidateid' => $candidateid])
) {
    $buttons['situationsread'] = situation_screen::url($candidateid);
}

// The consent to annotations (lot 1b): the vaeiste's own, to give or take back, before any line; and whoever reads the
// preparation (the same rule as situations.php, whose door is can_read_doctype). The administrator does not (MC-16).
if (
    consent_manager::can_manage($candidateid)
        || draft_manager::can_read_doctype($candidateid, draft_manager::DOCTYPE_VALIDATION)
) {
    $buttons['consent'] = new moodle_url('/admin/tool/vae/consent.php', ['userid' => $candidateid]);
}

// The deposits of the dossier de faisabilité (T-21): the candidate's own, and whoever may read them.
if (deposit_manager::can_read($candidateid)) {
    $buttons['deposit'] = new moodle_url('/admin/tool/vae/deposit.php', ['userid' => $candidateid]);
}

// The reflective journal (T-14): the candidate's own, nobody else's.
$journalkept = ['candidateid' => $candidateid, 'doctype' => draft_manager::DOCTYPE_REFLEXION];
if (
    $candidateid === (int) $USER->id && draft_manager::can_write($candidateid)
        && (draft_manager::reflection_open() || $DB->record_exists('tool_vae_draft', $journalkept))
) {
    $buttons['journal'] = new moodle_url('/admin/tool/vae/journal.php');
}

// The deposits of the preparation of the validation dossier (T-11, lot 3): read as deposited, by whoever may read deposits.
$validationdeposits = ['candidateid' => $candidateid, 'doctype' => draft_manager::DOCTYPE_VALIDATION];
if (deposit_manager::can_read($candidateid) && $DB->record_exists('tool_vae_deposit', $validationdeposits)) {
    $buttons['validationdeposit'] = new moodle_url('/admin/tool/vae/deposit.php', [
        'userid' => $candidateid,
        'doctype' => draft_manager::DOCTYPE_VALIDATION,
    ]);
}

// The editable copies (T-83): the archive's right, since they leave the platform just as it does.
// Posted: the button then carries the session key that document.php requires, and the key stays out of the address.
if ($claims && access::can_export($candidateid)) {
    foreach (['docx', 'odt'] as $format) {
        $buttons[$format] = new moodle_url('/admin/tool/vae/document.php', ['userid' => $candidateid, 'format' => $format]);
    }
}

if ($claims && backup::can_backup($candidateid)) {
    $buttons['backup'] = new moodle_url('/admin/tool/vae/backup.php', ['userid' => $candidateid]);
}

if (dossier_importer::can_import()) {
    $buttons['import'] = new moodle_url('/admin/tool/vae/import_dossier.php', ['userid' => $candidateid]);
}

echo $renderer->render(\tool_vae\output\dossier_page::dossier($coverage, $scope, $blocks, $positioning, $buttons));
echo $OUTPUT->footer();
