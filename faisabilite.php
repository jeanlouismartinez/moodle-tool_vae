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
 * The sections of one's own dossier de faisabilité, and where each stands (T-10, lot 1).
 *
 * Like section.php, the page never takes the candidate from the request: the writer is the
 * candidate, and the subject is $USER. It creates nothing - a section is created when it is opened
 * to be written - and it says what is there, never whether it is enough (D-1).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\local\draft_manager;
use tool_vae\local\feasibility_overview;
use tool_vae\output\feasibility_page;

require_login();

$candidateid = (int) $USER->id;
draft_manager::require_can_write($candidateid);

$url = new moodle_url('/admin/tool/vae/faisabilite.php');
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($candidateid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('faisabheading', 'tool_vae'));
$PAGE->set_heading(fullname($USER));

$overview = feasibility_overview::for_candidate($candidateid);

// Where each section stands, the button to the deposits and the way back: worded by the class of output.
$renderer = $PAGE->get_renderer('tool_vae');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('faisabheading', 'tool_vae'));
echo $renderer->render(new feasibility_page($overview, $candidateid));
echo $OUTPUT->footer();
