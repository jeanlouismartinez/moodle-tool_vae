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
 * One work situation of the vaeiste (lot 1b): its title, period, setting and tags, its text with versions, its pieces.
 *
 * WHO. The right to read is checked at the door (access::require_can_view(), then situation_manager::find(), which refuses
 * whoever may not read the preparation - the site administrator included, MC-16). The vaeiste alone writes, through
 * situation_manager and spiece_manager, each send a POST with the session key. A reader gets no form: the CURRENT title,
 * period, setting and tags (D-1b-I), the latest version, then the draft in progress said to be one (D-1b-H), the pieces.
 * Under them, the annotations: a manager the vaeiste consented to annotates (note_manager::can_write()), never in the text.
 *
 * WHEN. The preparation closed (validationopen) closes every door of the situation - details, version, deletion - and the
 * page says so and gives back the text that was being sent; the situation stays readable. Adding a piece follows piecesopen
 * alone, as every piece does; detaching and removing a piece work closed.
 *
 * THE FILE of a piece comes through the file picker of the core, as on piece.php: the form sends the number of a draft area of
 * the vaeiste, and the page reads the file there (draft_upload says how), then empties the area.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\form\note_form;
use tool_vae\form\situation_details_form;
use tool_vae\form\situation_form;
use tool_vae\form\spiece_file_form;
use tool_vae\local\access;
use tool_vae\local\draft_manager;
use tool_vae\local\draft_upload;
use tool_vae\local\hidden_frameworks;
use tool_vae\local\note_manager;
use tool_vae\local\piece_manager;
use tool_vae\local\situation_manager;
use tool_vae\local\situation_screen;
use tool_vae\local\spiece_manager;

require_login();

$candidateid = optional_param('userid', (int) $USER->id, PARAM_INT);
$fichecode = required_param('fiche', PARAM_ALPHANUMEXT);
$slot = required_param('slot', PARAM_INT);

$context = access::require_can_view($candidateid);
$manager = new situation_manager();
$situation = $manager->find($candidateid, $fichecode, $slot);
// A free slot, or a record whose code differs in case, is refused as a situation one may not read is: nothing says which
// slots are taken.
if ($situation === null || (string) $situation->fichecode !== $fichecode) {
    throw new moodle_exception('errordraftrefused', 'tool_vae');
}
$situationid = (int) $situation->id;
$candidate = core_user::get_user($candidateid, '*', MUST_EXIST);
$canwrite = situation_manager::can_write($candidateid);
$open = draft_manager::validation_open();
// A record hidden in Moodle since the situation was written: the page is in reading, with the message of the record, as
// situations.php has it (D-1b-F: what is not offered is not written on from here).
$frameworks = new hidden_frameworks();
$source = $DB->get_record('tool_vae_source', ['fichecode' => $fichecode]);
$hiddenmessage = $source && $frameworks->is_hidden($source) ? $frameworks->message($source) : null;
$mayact = $canwrite && $hiddenmessage === null;
$editing = $mayact && $open;
$settings = piece_manager::settings();
// What PHP lets through is the other limit: the smaller of the two is the one said and applied.
$maxbytes = min($settings->maxbytes, get_max_upload_file_size());
$canadd = $mayact && $settings->open;
$notify = \core\output\notification::class;

$url = situation_screen::url($candidateid, $fichecode, $slot);
$listurl = situation_screen::url($candidateid, $fichecode);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('situationheading', 'tool_vae', situation_screen::title($situation)));
$PAGE->set_heading(fullname($candidate));
$renderer = $PAGE->get_renderer('tool_vae');

$pieces = new spiece_manager();

// Attaching a file: a form of the core with its file picker, built whenever the vaeiste may act, so that a file sent after
// the pieces were closed is refused in words by spiece_manager, as before; drawn only when adding is open. The file is in a
// draft area of the vaeiste (draft_upload); its size is read BEFORE the form gives its data, since the file picker takes out
// a file over its limit then, without a word.
$fileform = $mayact ? new spiece_file_form($url->out(false), ['maxbytes' => $maxbytes], 'post', '', [
    'class' => 'mb-4 tool-vae-spiece-file',
]) : null;
if ($fileform !== null) {
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
            // The name of the file is never asked for: the piece is stored under an identifier.
            $pieces->add_file($situationid, (string) $data->filenature, (string) $data->filetitle, $copy->path, (string) (
                $data->filepart ?? ''
            ));
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
            $error !== null ? $notify::NOTIFY_ERROR : $notify::NOTIFY_SUCCESS
        );
    }
}

// The annotations (lot 1b): the author takes back their own annotation, even once the consent is withdrawn; a POST with the
// session key, the manager checking the authorship again. Whoever may read the page may send it: the manager refuses another,
// and an annotation of another vaeiste or of another target than this situation.
$notes = new note_manager();
if (data_submitted() && optional_param('action', '', PARAM_ALPHA) === 'withdrawnote') {
    require_sesskey();
    $error = null;
    try {
        $notes->withdraw(required_param('note', PARAM_INT), [
            'candidateid' => $candidateid,
            'target' => note_manager::TARGET_SITUATION,
            'targetuuid' => (string) $situation->uuid,
            'fichecode' => $fichecode,
            'slot' => $slot,
            'ficherang' => '',
        ]);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
    redirect($url, $error, null, $error !== null ? $notify::NOTIFY_ERROR : $notify::NOTIFY_SUCCESS);
}

// The acts of the vaeiste on the situation and its pieces: a POST with the session key, each one through its manager, which
// checks the right and the opening again.
$action = $mayact && data_submitted() ? optional_param('action', '', PARAM_ALPHA) : '';
$confirmdelete = false;
if ($action !== '') {
    require_sesskey();
    $error = null;
    $message = null;
    $level = $notify::NOTIFY_SUCCESS;
    $target = $url;
    try {
        switch ($action) {
            case 'delete':
                // Closed, the deletion is not even asked: its button is not drawn, and the manager would refuse it.
                if (!$open) {
                    throw new moodle_exception('errorvalidationclosed', 'tool_vae');
                }
                if (!optional_param('confirm', 0, PARAM_BOOL)) {
                    $confirmdelete = true;
                    break;
                }
                $manager->delete($situationid);
                $message = get_string('situationdeleted', 'tool_vae');
                $target = $listurl;
                break;
            case 'detach':
                $pieces->detach($situationid, required_param('piece', PARAM_INT));
                break;
            case 'remove':
                $pieces->remove(required_param('piece', PARAM_INT));
                $message = get_string('pieceremoved', 'tool_vae');
                break;
            case 'attach':
                // The part concerned is free text ("Page 3 <-> 4" would lose its arrow under PARAM_TEXT): checked by
                // spiece_manager::clean_part() (width, no control character) and escaped wherever it is shown.
                $pieces->attach($situationid, required_param('piece', PARAM_INT), optional_param('part', '', PARAM_RAW));
                break;
            case 'link':
                $row = $pieces->add_link(
                    $situationid,
                    optional_param('nature', '', PARAM_ALPHA),
                    optional_param('title', '', PARAM_TEXT),
                    // Not PARAM_URL: it empties https://fr.wikipedia.org/wiki/Établissement, an address the plugin accepts.
                    // piece_manager::validate_url() checks it (http or https, a host, no space, no control character).
                    optional_param('url', '', PARAM_RAW_TRIMMED),
                    // Free text, checked by spiece_manager::clean_part() and escaped where it is shown.
                    optional_param('part', '', PARAM_RAW)
                );
                $warnings = array_map(fn($key) => get_string($key, 'tool_vae'), $row->warnings);
                $message = trim(get_string('pieceaddedlink', 'tool_vae') . ' ' . implode(' ', $warnings));
                $level = $warnings ? $notify::NOTIFY_WARNING : $notify::NOTIFY_SUCCESS;
                break;
        }
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
    if (!$confirmdelete && in_array($action, ['delete', 'detach', 'remove', 'attach', 'link'], true)) {
        redirect($error !== null ? $url : $target, $error ?? $message, null, $error !== null ? $notify::NOTIFY_ERROR : $level);
    }
}

// Annotating: whoever may annotate now - following, the right, the consent, read again at each send (note_manager::can_write());
// never the vaeiste, never the site administrator. The form library checks the session key.
$canannotate = note_manager::can_write($candidateid);
$noteform = null;
$noteerror = null;
if ($canannotate && !$confirmdelete) {
    $noteform = new note_form($url->out(false));
    if ($data = $noteform->get_data()) {
        try {
            $notes->write(
                $candidateid,
                note_manager::TARGET_SITUATION,
                $fichecode,
                $slot,
                '',
                (string) ($data->notetext ?? ''),
                empty($data->markdown) ? FORMAT_PLAIN : FORMAT_MARKDOWN
            );
        } catch (moodle_exception $e) {
            $noteerror = $e->getMessage();
        }
        if ($noteerror === null) {
            redirect($url);
        }
    }
}

$draft = $DB->get_record('tool_vae_draft', ['id' => (int) $situation->draftid], '*', MUST_EXIST);
$versions = $manager->versions($situationid);
$latest = $versions ? reset($versions) : null;

$error = null;
$conflict = false;
$detailsform = null;
$textform = null;
if ($editing && !$confirmdelete) {
    // The boxes of the panel say the tags as they were sent when the form comes back with an error, else as they stand.
    $sentdetails = optional_param('_qf__tool_vae_form_situation_details_form', 0, PARAM_INT) && data_submitted();
    $checked = $sentdetails ? optional_param_array('tags', [], PARAM_ALPHANUM) : $situation->tags;
    $detailsform = new situation_details_form($url->out(false), [
        'panel' => new \tool_vae\output\whereami_tree(
            $candidateid,
            $fichecode,
            ['boxes' => true, 'checked' => $checked, 'title' => '']
        ),
    ]);
    if ($data = $detailsform->get_data()) {
        try {
            $manager->save_details(
                $situationid,
                (string) $data->title,
                empty($data->periodstart) ? null : (int) $data->periodstart,
                empty($data->periodend) ? null : (int) $data->periodend,
                (string) ($data->setting ?? ''),
                optional_param_array('tags', [], PARAM_ALPHANUM)
            );
        } catch (moodle_exception $e) {
            $error = $e->getMessage();
        }
        if ($error === null) {
            redirect($url, get_string('situationdetailssaved', 'tool_vae'), null, $notify::NOTIFY_SUCCESS);
        }
    }
    $detailsform->set_data([
        'title' => (string) $situation->title,
        'periodstart' => (int) ($situation->periodstart ?? 0),
        'periodend' => (int) ($situation->periodend ?? 0),
        'setting' => (string) $situation->setting,
    ]);

    // A version from a page that shows an older text than the draft another tab kept is refused and said, as in section.php.
    $textform = new situation_form($url->out(false));
    if ($data = $textform->get_data()) {
        $version = null;
        try {
            $version = $manager->save_version($situationid, (string) $data->content, (int) ($data->workingat ?? 0));
        } catch (moodle_exception $e) {
            if ($e->errorcode === 'errordraftconflict') {
                $conflict = true;
            } else {
                $error = $e->getMessage();
            }
        }
        if (!$conflict && $error === null) {
            if ($version === null) {
                redirect($url, get_string('draftunchanged', 'tool_vae'), null, $notify::NOTIFY_INFO);
            }
            redirect($url, get_string('draftsaved', 'tool_vae', (int) $version->number), null, $notify::NOTIFY_SUCCESS);
        }
    }
    // What the field starts with: the draft in progress when there is one, else the latest version.
    $text = $draft->working !== null ? (string) $draft->working : ($latest !== null ? (string) $latest->content : '');
    $textform->set_data(['workingat' => (int) ($draft->workingat ?? 0), 'content' => $text]);
    if (!$conflict) {
        situation_screen::autosave((int) $draft->id, (int) ($draft->workingat ?? 0));
    }
}

$history = new moodle_url('/admin/tool/vae/history.php', ['draftid' => (int) $draft->id]);

echo $OUTPUT->header();
echo $OUTPUT->heading(s(situation_screen::title($situation)));

if ($confirmdelete) {
    echo $OUTPUT->confirm(
        get_string('situationdeleteconfirm', 'tool_vae'),
        new single_button(
            new moodle_url($url, ['action' => 'delete', 'confirm' => 1]),
            get_string('situationdelete', 'tool_vae'),
            'post'
        ),
        new single_button($url, get_string('cancel'), 'get')
    );
    echo $OUTPUT->footer();
} else {
    // The pieces: the vaeiste detaches a piece from this situation, or removes it everywhere; open or closed. Adding one
    // follows piecesopen: the pieces of the vaeiste on the record not removed and not linked here may be attached.
    $links = $pieces->for_situation($situationid);
    $attachable = [];
    if ($canadd) {
        $linked = array_map(fn($link) => (int) $link->pieceid, $links);
        $attachable = array_filter(
            $pieces->for_candidate($candidateid, $fichecode),
            fn($piece) => $piece->timeremoved === null && !in_array((int) $piece->id, $linked, true)
        );
    }
    // The code of the record, the sentences, the error, the conflict; the record hidden or the preparation closed under the
    // vaeiste, said, with what they were sending given back, never lost; the forms or the reading; the versions, the pieces,
    // the annotations under the text (signed and dated, apart from the vaeiste's own voice), "delete" and the way back.
    echo $renderer->render(\tool_vae\output\situation_page::situation(
        $situation,
        $candidateid,
        $context,
        $error,
        $conflict,
        $url,
        $hiddenmessage,
        $canwrite,
        $open,
        situation_screen::kept_text(),
        $editing,
        $detailsform,
        $textform,
        $latest,
        $draft,
        $history,
        new \tool_vae\output\situation_pieces($links, $context, $mayact ? $url : null),
        $mayact,
        $canadd ? new \tool_vae\output\situation_piece_forms($url, $attachable, $fileform) : null,
        $noteerror,
        $notes->for_target($candidateid, note_manager::TARGET_SITUATION, $fichecode, $slot, ''),
        $canannotate,
        $noteform,
        $listurl
    ));
    echo $OUTPUT->footer();
}
