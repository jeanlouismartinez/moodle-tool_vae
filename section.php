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
 * Write one section of one's own document (T-81, lot 3).
 *
 * Like declare.php, the page never takes the candidate from the request: the writer is the
 * candidate, and the subject is $USER. What the request names is the section.
 *
 * The draft in progress is saved on its own every few seconds by the external function tool_vae_save_draft
 * (module tool_vae/autosave); a version is only written when the candidate presses the button.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\form\fields_form;
use tool_vae\form\section_form;
use tool_vae\local\ai_assistant;
use tool_vae\local\draft_manager;
use tool_vae\local\feasibility_catalog;
use tool_vae\local\feasibility_fields;
use tool_vae\local\validation_catalog;
use tool_vae\output\section_page;

require_login();

$doctype = optional_param('doctype', draft_manager::DOCTYPE_FAISABILITE, PARAM_ALPHANUMEXT);
$sectioncode = required_param('section', PARAM_ALPHANUMEXT);
$slot = optional_param('slot', 0, PARAM_INT);
// The record, for the preparation of the validation dossier (T-11); empty for the dossier de faisabilité.
$fichecode = optional_param('fiche', '', PARAM_ALPHANUMEXT);

$candidateid = (int) $USER->id;
draft_manager::require_can_write($candidateid);

// A work situation (lot 1b) is written from its own screen, through situation_manager, which keeps with each version the
// snapshot of its title, period, setting and tags: this page never opens its draft, whatever draft_manager would say.
if ($doctype === draft_manager::DOCTYPE_VALIDATION && $sectioncode === validation_catalog::SITUATION_SECTION) {
    throw new moodle_exception('errordraftsection', 'tool_vae');
}

// A section of the dossier de faisabilité that the catalogue lists but does not open (none today).
if (
    $doctype === draft_manager::DOCTYPE_FAISABILITE
        && feasibility_catalog::valid_address($sectioncode, $slot)
        && !feasibility_catalog::is_open($sectioncode)
) {
    throw new moodle_exception('errordraftsection', 'tool_vae');
}

$isfaisabilite = $doctype === draft_manager::DOCTYPE_FAISABILITE;
$kind = $isfaisabilite ? feasibility_catalog::kind($sectioncode) : null;

// The contact details are read from the account, never kept here: no section row, no version
// (T-10, lot 3, MC-10). The page shows them and says where to change them.
if ($kind === feasibility_catalog::KIND_ACCOUNT) {
    $PAGE->set_url(new moodle_url('/admin/tool/vae/section.php', ['section' => $sectioncode]));
    $PAGE->set_context(context_user::instance($candidateid));
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title(feasibility_catalog::label($sectioncode, 0));
    $PAGE->set_heading(fullname($USER));

    $shown = feasibility_fields::account_values($sectioncode, $USER);
    $renderer = $PAGE->get_renderer('tool_vae');

    echo $OUTPUT->header();
    echo $OUTPUT->heading(feasibility_catalog::label($sectioncode, 0));
    echo $renderer->render(section_page::account($sectioncode, $shown, (int) $USER->id));
    echo $OUTPUT->footer();
    die();
}

$manager = new draft_manager();
$draft = $manager->section($candidateid, $doctype, $sectioncode, $slot, $fichecode);

$isvalidation = $doctype === draft_manager::DOCTYPE_VALIDATION;
$isreflexion = $doctype === draft_manager::DOCTYPE_REFLEXION;
$urlparams = ['doctype' => $doctype, 'section' => $sectioncode, 'slot' => $slot];
if ($isvalidation) {
    $urlparams['fiche'] = $fichecode;
}
$url = new moodle_url('/admin/tool/vae/section.php', $urlparams);
// Where "back" goes: the list of the document this section belongs to.
if ($isreflexion) {
    $backurl = new moodle_url('/admin/tool/vae/journal.php');
    $backwords = get_string('reflexionbacktojournal', 'tool_vae');
} else {
    $backurl = $isvalidation
        ? new moodle_url('/admin/tool/vae/validation.php', ['fiche' => $fichecode])
        : new moodle_url('/admin/tool/vae/faisabilite.php');
    $backwords = get_string($isvalidation ? 'validationbacktofiche' : 'faisabbacktolist', 'tool_vae');
}
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($candidateid));
$PAGE->set_pagelayout('standard');
$sectionlabel = $doctype === draft_manager::DOCTYPE_FAISABILITE
    ? feasibility_catalog::label($sectioncode, $slot)
    : ($isvalidation ? validation_catalog::label($fichecode, $sectioncode) : draft_manager::label($draft));
$PAGE->set_title($sectionlabel);
$PAGE->set_heading(fullname($USER));
$renderer = $PAGE->get_renderer('tool_vae');

$history = new moodle_url('/admin/tool/vae/history.php', ['draftid' => $draft->id]);

// Separate fields (identity, status): a form of typed fields, saved whole as a version. There is
// no draft in progress for them - the form is short, and half a form is not a text to keep.
if ($kind === feasibility_catalog::KIND_FIELDS) {
    $fieldsform = new fields_form($url->out(false), ['section' => $sectioncode]);

    if ($fieldsform->is_cancelled()) {
        redirect(new moodle_url('/admin/tool/vae/faisabilite.php'));
    }

    if ($data = $fieldsform->get_data()) {
        $content = feasibility_fields::encode($sectioncode, fields_form::values($sectioncode, $data));
        $version = $manager->save_version((int) $draft->id, $content, $data->note ?? null);
        if ($version === null) {
            redirect($url, get_string('draftunchanged', 'tool_vae'), null, \core\output\notification::NOTIFY_INFO);
        }
        redirect(
            $url,
            get_string('draftsaved', 'tool_vae', (int) $version->number),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $kept = [];
    if ((int) $draft->currentversion > 0) {
        $kept = feasibility_fields::decode(
            $sectioncode,
            (string) $manager->version((int) $draft->id, (int) $draft->currentversion)->content
        );
    }
    $fieldsform->set_data(['draftid' => (int) $draft->id] + fields_form::data_from_values($sectioncode, $kept));

    echo $OUTPUT->header();
    echo $OUTPUT->heading($sectionlabel);
    echo $renderer->render(section_page::fields($sectioncode, $draft, $history, $fieldsform));
    echo $OUTPUT->footer();
    die();
}

// The aid to reword (T-70): offered only when it is open, theirs to use and a provider can answer, and never in the private
// journal. For anyone else the form is as it was.
$aiurl = null;
if (!$isreflexion && (new ai_assistant())->offered($candidateid)) {
    $aiurl = new moodle_url('/admin/tool/vae/ai.php', ['source' => 'section'] + $urlparams);
}
$form = new section_form($url->out(false), ['aiurl' => $aiurl]);

if ($form->is_cancelled()) {
    redirect($history);
}

// A version written from a page that shows an older text than the draft another tab kept: refused, and said (the same check as
// the autosave's, with the `workingat` the page was loaded with or last received). The page is drawn again with the text the
// writer was saving still in the form, and without the autosave, which would otherwise start again from the fresh time.
$conflict = false;
if ($data = $form->get_data()) {
    $version = null;
    try {
        $version = $manager->save_version(
            (int) $draft->id,
            (string) $data->text,
            $data->note ?? null,
            (int) ($data->workingat ?? 0)
        );
    } catch (moodle_exception $e) {
        if ($e->errorcode !== 'errordraftconflict') {
            throw $e;
        }
        $conflict = true;
    }
    if (!$conflict) {
        if ($version === null) {
            redirect($url, get_string('draftunchanged', 'tool_vae'), null, \core\output\notification::NOTIFY_INFO);
        }
        redirect(
            $url,
            get_string('draftsaved', 'tool_vae', (int) $version->number),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// What the field starts with: the draft in progress when there is one, else the latest version.
$text = (string) $draft->working;
if ($draft->working === null && (int) $draft->currentversion > 0) {
    $text = (string) $manager->version((int) $draft->id, (int) $draft->currentversion)->content;
}
$form->set_data([
    'draftid' => (int) $draft->id,
    'workingat' => (int) ($draft->workingat ?? 0),
    'text' => $text,
]);

// Autosave: every twenty seconds, when the text has changed. Without JavaScript the button remains.
// WHAT THE SCRIPT KEEPS TRUE. The form carries the time of the draft the page knows (the field workingat), and the button that
// saves a version sends it: the script writes there each time the autosave is answered, or the writer's own autosave would look
// like another tab to the button. It stops at the submission of the form - a tick that left just before the click lands after
// the version, and draft_manager::save_working() is what makes that harmless - except for the button of the aid, which opens
// another tab and leaves this page to go on. Nothing starts on a page drawn after a conflict: it must be reloaded.
// The script is the module amd/src/autosave.js, shared with the work situations: here on the field id_text, sparing a
// submission to a new tab.
if (!$conflict) {
    $PAGE->requires->js_call_amd('tool_vae/autosave', 'init', [
        \tool_vae\local\situation_screen::autosave_config((int) $draft->id, (int) ($draft->workingat ?? 0), 'id_text', true),
    ]);
}

echo $OUTPUT->header();
// Escaped: the name of a bloc of the preparation comes from the record, whose labels are not ours (T-31).
echo $OUTPUT->heading(s($sectionlabel));
echo $renderer->render(section_page::text(
    $doctype,
    $sectioncode,
    $fichecode,
    $candidateid,
    $draft,
    $conflict,
    $url,
    $history,
    $backurl,
    $backwords,
    $form
));
echo $OUTPUT->footer();
