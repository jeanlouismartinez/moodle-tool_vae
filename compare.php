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
 * Compare two versions of a section (T-81, lot 3).
 *
 * Read only. The two versions come from the request as numbers of THIS section: both are read
 * through draft_manager, which checks the right to read, so a number of another section cannot
 * be reached from here.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\local\draft_manager;
use tool_vae\local\feasibility_fields;
use tool_vae\local\text_diff;
use tool_vae\output\compare_page;

require_login();

$draftid = required_param('draftid', PARAM_INT);
$from = required_param('from', PARAM_INT);
$to = required_param('to', PARAM_INT);

$draft = $DB->get_record('tool_vae_draft', ['id' => $draftid]);
if (!$draft) {
    throw new moodle_exception('errordraftrefused', 'tool_vae');
}
$candidateid = (int) $draft->candidateid;
draft_manager::require_can_read_draft($draft);

$manager = new draft_manager();
$old = $manager->version($draftid, $from);
$new = $manager->version($draftid, $to);

$url = new moodle_url('/admin/tool/vae/compare.php', ['draftid' => $draftid, 'from' => $from, 'to' => $to]);
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($candidateid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('compareheading', 'tool_vae'));
$PAGE->set_heading(fullname(core_user::get_user($candidateid)));

$ops = text_diff::diff(
    feasibility_fields::readable((string) $draft->section, (string) $old->content),
    feasibility_fields::readable((string) $draft->section, (string) $new->content)
);

// One sentence first: which two versions, and how much moved; then the comparison, its white space kept as written. The
// class of output words them and places the block of the comparison drawn by the piece compare_diff.
$renderer = $PAGE->get_renderer('tool_vae');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('compareheading', 'tool_vae'));
echo $renderer->render(new compare_page(
    draft_manager::label($draft),
    new moodle_url('/admin/tool/vae/history.php', ['draftid' => $draftid]),
    $from,
    (int) $old->timecreated,
    $to,
    (int) $new->timecreated,
    $ops
));
echo $OUTPUT->footer();
