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
 * The reflective journal: one's own entries, dated, private (T-14, lot 1).
 *
 * Like section.php, the page never takes the candidate from the request: the journal is $USER's, and nobody else's -
 * not the manager, not the administrator. An entry is written with section.php (document "reflexion"); this page lists
 * them, makes a new one and deletes one after confirmation.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\local\draft_manager;
use tool_vae\output\journal_page;

require_login();

$candidateid = (int) $USER->id;
draft_manager::require_can_write($candidateid);
// Closed, the journal can no longer be written in, but its author can still look at it and delete from it: what they wrote
// is theirs, and the administrator closing it must not lock their own words in (T-14).
$open = draft_manager::reflection_open();
$kept = ['candidateid' => $candidateid, 'doctype' => draft_manager::DOCTYPE_REFLEXION];
if (!$open && !$DB->record_exists('tool_vae_draft', $kept)) {
    throw new moodle_exception('errorreflexionclosed', 'tool_vae');
}

$url = new moodle_url('/admin/tool/vae/journal.php');
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($candidateid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('reflexiontitle', 'tool_vae'));
$PAGE->set_heading(fullname($USER));

$manager = new draft_manager();

// A new entry takes the first free number and opens its writing page.
if (optional_param('new', 0, PARAM_BOOL)) {
    require_sesskey();
    if (!$open) {
        throw new moodle_exception('errorreflexionclosed', 'tool_vae');
    }
    $slot = draft_manager::next_reflection_slot($candidateid);
    redirect(new moodle_url('/admin/tool/vae/section.php', [
        'doctype' => draft_manager::DOCTYPE_REFLEXION,
        'section' => draft_manager::REFLEXION_SECTION,
        'slot' => $slot,
    ]));
}

// A deletion asks first, and then removes the entry with all its versions.
$delete = optional_param('delete', 0, PARAM_INT);
if ($delete > 0) {
    $entry = $DB->get_record('tool_vae_draft', [
        'id' => $delete,
        'candidateid' => $candidateid,
        'doctype' => draft_manager::DOCTYPE_REFLEXION,
    ], '*', MUST_EXIST);
    if (optional_param('confirm', 0, PARAM_BOOL)) {
        require_sesskey();
        $manager->delete_entry((int) $entry->id);
        redirect($url, get_string('reflexiondeleted', 'tool_vae'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('reflexiondeleteconfirm', 'tool_vae', (int) $entry->slot),
        new moodle_url($url, ['delete' => $entry->id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $url
    );
    echo $OUTPUT->footer();
    die();
}

// The notes, the button to start an entry and the table of the entries: worded by the class of output.
$entries = draft_manager::reflection_entries($candidateid);
$renderer = $PAGE->get_renderer('tool_vae');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reflexiontitle', 'tool_vae'));
echo $renderer->render(new journal_page($open, $entries, $url));
echo $OUTPUT->footer();
