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
 * The supporting pieces of one declaration: attach a file or a link, read the list, remove one (T-13, lot 1).
 *
 * Like precision.php, the page takes the declaration's id and derives the candidate from it. The candidate adds and removes;
 * whoever may read the dossier reads the list, and opens a piece only with the right to (lot 2). A declaration that does not
 * exist is refused word for word as one the reader may not read.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\form\piece_file_form;
use tool_vae\form\piece_link_form;
use tool_vae\local\access;
use tool_vae\local\attestation_manager;
use tool_vae\local\competency_gateway;
use tool_vae\local\draft_upload;
use tool_vae\local\piece_manager;
use tool_vae\output\piece_page;

require_login();

$evidenceid = required_param('evidenceid', PARAM_INT);

$evidence = $DB->get_record('tool_vae_evidence', ['id' => $evidenceid], '*', IGNORE_MISSING);
// The candidate, who adds and removes; or whoever may read the dossier, who only reads the list (T-13, lot 2). A
// declaration that does not exist is refused as one that is not theirs.
if (!$evidence) {
    throw new moodle_exception('errorpiecedenied', 'tool_vae');
}
$candidateid = (int) $evidence->candidateid;
$iscandidate = $candidateid === (int) $USER->id;
if (!$iscandidate && !access::can_view($candidateid)) {
    throw new moodle_exception('errorpiecedenied', 'tool_vae');
}
$context = context_user::instance($candidateid);
if ($iscandidate) {
    require_capability('tool/vae:viewown', $context);
}
if (!competency_gateway::is_subsystem_enabled()) {
    throw new moodle_exception('errorcompetenciesdisabled', 'tool_vae');
}

$url = new moodle_url('/admin/tool/vae/piece.php', ['evidenceid' => $evidenceid]);
$dossier = new moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pieceheading', 'tool_vae'));
$PAGE->set_heading(fullname($iscandidate ? $USER : core_user::get_user($candidateid, '*', MUST_EXIST)));

$manager = new piece_manager();
$settings = piece_manager::settings();
// What PHP lets through is the other limit: the smaller of the two is the one said and applied.
$maxbytes = min($settings->maxbytes, get_max_upload_file_size());
$notify = \core\output\notification::class;

// Removing: a POST with the session key, always possible, open or closed. The declaration is checked before anything goes.
$remove = optional_param('remove', 0, PARAM_INT);
if ($remove && $iscandidate && data_submitted()) {
    require_sesskey();
    $error = null;
    try {
        $manager->remove($remove, $evidenceid);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
    redirect(
        $url,
        $error ?? get_string('pieceremoved', 'tool_vae'),
        null,
        $error === null ? $notify::NOTIFY_SUCCESS : $notify::NOTIFY_ERROR
    );
}

$canadd = $settings->open && $iscandidate;
$fileform = $linkform = null;
if ($canadd) {
    $fileform = new piece_file_form($url->out(false), ['evidenceid' => $evidenceid, 'maxbytes' => $maxbytes]);
    $linkform = new piece_link_form($url->out(false), ['evidenceid' => $evidenceid]);

    if ($fileform->is_cancelled() || $linkform->is_cancelled()) {
        redirect($dossier);
    }
    // The file is in a draft area of the vaeiste, put there by the file picker of the core (draft_upload says how). Its size
    // is read BEFORE the form gives its data: the file picker takes out a file over its limit then, without a word.
    $toolarge = $fileform->is_submitted()
        && draft_upload::larger_than(optional_param('piecefile', 0, PARAM_INT), $maxbytes);
    if ($data = $fileform->get_data()) {
        $error = null;
        $itemid = (int) $data->piecefile;
        $copy = null;
        try {
            if ($toolarge) {
                throw new moodle_exception('errorpiecetoolarge', 'tool_vae', '', display_size($maxbytes));
            }
            $copy = draft_upload::piece($itemid, $maxbytes);
            // The name the area holds is passed as before, and never kept (piece_manager::add_file()).
            $manager->add_file($evidenceid, (string) $data->filenature, (string) $data->filetitle, $copy->path, $copy->name);
        } catch (moodle_exception $e) {
            $error = $e->getMessage();
        } finally {
            // Taken, kept or refused: nothing of the vaeiste stays in the draft area, nor in the copy.
            draft_upload::discard($itemid, $copy);
        }
        redirect(
            $url,
            $error ?? get_string('pieceaddedfile', 'tool_vae'),
            null,
            $error === null ? $notify::NOTIFY_SUCCESS : $notify::NOTIFY_ERROR
        );
    }
    if ($data = $linkform->get_data()) {
        $error = null;
        $warnings = [];
        try {
            $row = $manager->add_link($evidenceid, (string) $data->linknature, (string) $data->linktitle, (string) $data->url);
            $warnings = array_map(fn($key) => get_string($key, 'tool_vae'), $row->warnings);
        } catch (moodle_exception $e) {
            $error = $e->getMessage();
        }
        $message = $error ?? trim(get_string('pieceaddedlink', 'tool_vae') . ' ' . implode(' ', $warnings));
        $level = $error !== null ? $notify::NOTIFY_ERROR : ($warnings ? $notify::NOTIFY_WARNING : $notify::NOTIFY_SUCCESS);
        redirect($url, $message, null, $level);
    }
}

// The list: one piece of output for the candidate, for the manager who attests and for whoever only reads
// (tool_vae\output\pieces). Only the candidate gets a button to remove, in the same place as always. The forms are drawn
// while the site takes pieces; otherwise the candidate is told it does not.
$pieces = piece_manager::for_evidence($evidenceid);
$standing = attestation_manager::active_attestation($evidenceid);

$renderer = $PAGE->get_renderer('tool_vae');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pieceheading', 'tool_vae'));
echo $renderer->render(new piece_page(
    $evidence,
    $context,
    $pieces,
    $standing,
    $iscandidate ? $url : null,
    $fileform,
    $linkform,
    !$canadd && $iscandidate,
    $dossier
));
echo $OUTPUT->footer();
