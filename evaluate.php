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
 * Evaluate one competency of a candidate: a status and a written reason (T-90, lot 3).
 *
 * THE RIGHT TO READ IS CHECKED BEFORE THE ACCOUNT IS LOOKED UP, and the target - record and rank - is checked against the
 * record the same way whoever asks: a rank that is not there is refused as a dossier one may not read is. A positioning of
 * the structure, never a validation.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\form\evaluation_form;
use tool_vae\local\access;
use tool_vae\local\evaluation_manager;
use tool_vae\local\positioning_rules;
use tool_vae\local\positioning_screen;
use tool_vae\output\positioning_page;

require_login();

$candidateid = required_param('userid', PARAM_INT);
$fichecode = required_param('fichecode', PARAM_ALPHANUMEXT);
$rank = optional_param('rank', '', PARAM_ALPHANUM);

$context = access::require_can_view($candidateid);
$candidate = core_user::get_user($candidateid, '*', MUST_EXIST);

$layout = positioning_rules::layout($fichecode);
if (!$layout || !isset($layout->leaves[$rank])) {
    throw new moodle_exception('errorpositioningdenied', 'tool_vae');
}
$label = $layout->leaves[$rank]->label;

$url = positioning_screen::url('evaluate', $candidateid, $fichecode, $rank);
$dossier = new moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$heading = evaluation_manager::can_write($candidateid) ? 'positioningevaluateheading' : 'positioningevaluatereadheading';
$PAGE->set_title(get_string($heading, 'tool_vae'));
$PAGE->set_heading(fullname($candidate));

$manager = new evaluation_manager();
$form = null;
$error = null;
if (evaluation_manager::can_write($candidateid)) {
    $form = new evaluation_form($url->out(false));
    if ($form->is_cancelled()) {
        redirect($dossier);
    }
    if ($data = $form->get_data()) {
        try {
            $manager->write($candidateid, $fichecode, $rank, (string) $data->status, (string) $data->reason);
        } catch (moodle_exception $e) {
            $error = $e->getMessage();
        }
        if ($error === null) {
            redirect($url, get_string('positioningrecorded', 'tool_vae'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    }
}

// Read only, before the page is drawn: the lines of the competency.
$rows = $manager->lines($candidateid, $fichecode, $rank);
$renderer = $PAGE->get_renderer('tool_vae');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($heading, 'tool_vae'));
echo $renderer->render(positioning_page::evaluate($fichecode, $label, $error, $form, $rows, $context, $dossier));
echo $OUTPUT->footer();
