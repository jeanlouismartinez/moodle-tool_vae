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
 * The deposits of a dossier de faisabilité: make one, list them, read one (T-21, lot 2).
 *
 * THE RIGHT IS CHECKED BEFORE THE ACCOUNT IS LOOKED UP, as dossier.php does: an id nobody may read
 * answers as an id that is no account. The candidate deposits - and only the candidate, for their
 * own; whoever may read the deposits reads the list and each deposit, never a draft or a version.
 * The separate fields of a deposit (identity, status) are shown only to whoever may read them.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\form\deposit_form;
use tool_vae\form\opinion_form;
use tool_vae\local\deposit_manager;
use tool_vae\local\draft_manager;
use tool_vae\local\opinion_manager;
use tool_vae\output\deposit_page;

require_login();

// Cast: the default is the session's own id, a string, and the page compares identities with ===.
$candidateid = (int) optional_param('userid', $USER->id, PARAM_INT);
$number = optional_param('number', 0, PARAM_INT);
$doctype = optional_param('doctype', draft_manager::DOCTYPE_FAISABILITE, PARAM_ALPHANUMEXT);

deposit_manager::require_can_read($candidateid);
$candidate = core_user::get_user($candidateid, '*', MUST_EXIST);
if (!in_array($doctype, [draft_manager::DOCTYPE_FAISABILITE, draft_manager::DOCTYPE_VALIDATION], true)) {
    throw new moodle_exception('errordepositmissing', 'tool_vae');
}
// The preparation of the validation dossier (T-11, lot 3): read as it was deposited, never evaluated.
$isvalidation = $doctype === draft_manager::DOCTYPE_VALIDATION;
$headingkey = $isvalidation ? 'validationdepositheading' : 'depositheading';

$params = ['userid' => $candidateid];
if ($isvalidation) {
    $params['doctype'] = $doctype;
}
$url = new moodle_url('/admin/tool/vae/deposit.php', $params);
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($candidateid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string($headingkey, 'tool_vae'));
$PAGE->set_heading(fullname($candidate));

$manager = new deposit_manager();
$own = $candidateid === (int) $USER->id;
$form = null;
if ($own && deposit_manager::can_deposit($candidateid) && (!$isvalidation || draft_manager::validation_open())) {
    $form = new deposit_form($url->out(false), ['validation' => $isvalidation]);
    if ($form->is_cancelled()) {
        redirect(new moodle_url($isvalidation ? '/admin/tool/vae/validation.php' : '/admin/tool/vae/faisabilite.php'));
    }
    if ($data = $form->get_data()) {
        $error = null;
        try {
            $row = $manager->deposit($candidateid, $data->note ?? null, !empty($data->engagement), $doctype);
        } catch (moodle_exception $e) {
            $error = $e->getMessage();
        }
        if ($error !== null) {
            redirect($url, $error, null, \core\output\notification::NOTIFY_ERROR);
        }
        redirect(
            $url,
            get_string('depositdone', 'tool_vae', (int) $row->number),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// The opinion of a manager on one deposit (T-10, lot 5b): written once, never rewritten.
$opinionform = null;
if ($number > 0 && !$isvalidation && opinion_manager::can_write($candidateid)) {
    $opinionurl = new moodle_url($url, ['number' => $number]);
    $opiniondeposit = $manager->get($candidateid, $number);
    $opinionform = new opinion_form($opinionurl->out(false));
    if ($opinionform->is_cancelled()) {
        redirect($opinionurl);
    }
    if ($opiniondata = $opinionform->get_data()) {
        $error = null;
        try {
            (new opinion_manager())->write((int) $opiniondeposit->id, (string) $opiniondata->opinion);
        } catch (moodle_exception $e) {
            $error = $e->getMessage();
        }
        redirect(
            $opinionurl,
            $error ?? get_string('opiniondone', 'tool_vae'),
            null,
            $error === null ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('tool_vae');

if ($number > 0) {
    // One deposit, as it was fixed.
    $deposit = $manager->get($candidateid, $number, $doctype);
    $withfields = deposit_manager::can_read_fields($candidateid);

    echo $OUTPUT->heading(get_string('depositviewheading', 'tool_vae', (object) [
        'number' => (int) $deposit->number,
        'date' => userdate($deposit->timecreated, get_string('strftimedatetime', 'langconfig')),
    ]));
    if ($isvalidation) {
        // The preparation of the validation dossier: its sections, read as they were deposited, never evaluated.
        echo $renderer->render(deposit_page::validation($deposit, $url, $candidateid));
        echo $OUTPUT->footer();
        die();
    }

    // The opinions written on this deposit, then the form of whoever may add one.
    $opinions = (new opinion_manager())->for_deposit($deposit);
    echo $renderer->render(deposit_page::deposit($deposit, $withfields, $opinions, $opinionform, $url, $candidateid));
    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->heading(get_string($headingkey, 'tool_vae'));
$deposits = $manager->deposits($candidateid, $doctype);
echo $renderer->render(deposit_page::listing($isvalidation, $form, $deposits, $url, $own, $candidateid));
echo $OUTPUT->footer();
