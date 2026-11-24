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
 * The versions of a section, and the way to compare or restore them (T-81, lot 3).
 *
 * Read by the candidate, the site administrator and whoever holds tool/vae:draftview; written
 * (restoring) by the candidate alone. The table never offers to delete a version: the history is
 * the point (D-9).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\local\draft_manager;
use tool_vae\local\situation_manager;
use tool_vae\local\situation_screen;
use tool_vae\output\history_page;

require_login();

$draftid = required_param('draftid', PARAM_INT);

$draft = $DB->get_record('tool_vae_draft', ['id' => $draftid]);
if (!$draft) {
    throw new moodle_exception('errordraftrefused', 'tool_vae');
}
$candidateid = (int) $draft->candidateid;
draft_manager::require_can_read_draft($draft);
$canwrite = draft_manager::can_write($candidateid);
// The text of a work situation (lot 1b): read here as any section, restored through situation_manager alone, so that the
// new version takes the snapshot of the title, period, setting and tags.
$issituation = draft_manager::is_situation_draft($draft);

$manager = new draft_manager();
$url = new moodle_url('/admin/tool/vae/history.php', ['draftid' => $draftid]);

// Restoring: POST, session key, and the candidate alone. A restoration empties the draft in progress, as a version does: the
// form carries the `workingat` this page was drawn with, and a draft written since from another tab is not wiped by it.
if (optional_param('action', '', PARAM_ALPHA) === 'restore') {
    require_sesskey();
    $number = required_param('number', PARAM_INT);
    $workingat = optional_param('workingat', 0, PARAM_INT);
    try {
        if ($issituation) {
            $situations = new situation_manager();
            $situation = $situations->find_by_draft($draftid);
            if ($situation === null) {
                throw new moodle_exception('errordraftsection', 'tool_vae');
            }
            $new = $situations->restore((int) $situation->id, $number, $workingat);
        } else {
            $new = $manager->restore($draftid, $number, $workingat);
        }
    } catch (moodle_exception $e) {
        if ($e->errorcode !== 'errordraftconflict') {
            throw $e;
        }
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    if ($new === null) {
        redirect($url, get_string('draftunchanged', 'tool_vae'), null, \core\output\notification::NOTIFY_INFO);
    }
    redirect(
        $url,
        get_string('historyrestored', 'tool_vae', (object) ['new' => (int) $new->number, 'old' => $number]),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// A work situation is named by its CURRENT title - the row of tool_vae_situation, or "Work situation n" while it has none
// (situation_screen::title(), D-1b-I) - never by the fixed name of its draft; its history is the history of the situation
// (T15, point Z2). A draft left without its row reads as a section.
$situation = $issituation ? (new situation_manager())->find_by_draft($draftid) : null;
$heading = get_string($situation === null ? 'historyheading' : 'historysituationheading', 'tool_vae');
$line = $situation === null
    ? get_string('faisabsectionline', 'tool_vae', draft_manager::label($draft))
    : get_string('situationheading', 'tool_vae', situation_screen::title($situation));

$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($candidateid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($heading);
$PAGE->set_heading(fullname(core_user::get_user($candidateid)));

$versions = $manager->versions($draftid);

// The name of the author of each version, read here: an account that is gone reads as such.
$authors = [];
foreach ($versions as $number => $version) {
    $user = $version->authorid !== null ? core_user::get_user((int) $version->authorid) : false;
    $authors[$number] = $user ? fullname($user) : get_string('historyauthorerased', 'tool_vae');
}

// The screen of a work situation is its own (lot 1b), not section.php: the way back leads there, for the vaeiste and for a
// reader alike.
$back = null;
if ($issituation) {
    $back = situation_screen::url($candidateid, (string) $draft->fichecode, (int) $draft->slot);
} else if ($canwrite) {
    $back = new moodle_url('/admin/tool/vae/section.php', [
        'doctype' => $draft->doctype, 'section' => $draft->section, 'slot' => (int) $draft->slot,
    ] + ($draft->doctype === draft_manager::DOCTYPE_VALIDATION ? ['fiche' => $draft->fichecode] : []));
}

echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('tool_vae');
echo $OUTPUT->heading($heading);
echo $renderer->render(new history_page($line, $back, $issituation, $draft, $versions, $authors, $canwrite, $url));
echo $OUTPUT->footer();
