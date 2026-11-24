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
 * Replaced certification records: which record replaces which, and what still holds the old one (T-30, lot 1).
 *
 * admin_externalpage_setup() protects this page; the same right is asked again where the declaration is written
 * (supersession::declare_successor()). The page counts declarations and candidates and names nobody.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_vae\form\supersession_form;
use tool_vae\local\supersession;
use tool_vae\output\supersession_page;

admin_externalpage_setup('toolvae_supersession');

$self = new moodle_url('/admin/tool/vae/supersession.php');
$rows = supersession::overview();
$options = [];
foreach ($rows as $row) {
    // No label when the framework of the record is gone from the core: the code alone, not "CODE — ".
    $options[$row->fichecode] = $row->fichecode . ($row->label === '' ? '' : ' — ' . format_string($row->label));
}

$message = null;
$error = null;

$clear = optional_param('clear', '', PARAM_ALPHANUMEXT);
if ($clear !== '') {
    require_sesskey();
    try {
        supersession::clear_successor($clear);
        redirect($self, get_string('supersessioncleared', 'tool_vae'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

$form = new supersession_form($self, ['options' => $options]);
if ($data = $form->get_data()) {
    try {
        supersession::declare_successor((string) $data->fichecode, (string) $data->successorcode);
        redirect($self, get_string('supersessionsaved', 'tool_vae'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

// The table of the records, and below it the form, drawn only when there is a record: worded by the class of output.
$renderer = $PAGE->get_renderer('tool_vae');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('supersessionheading', 'tool_vae'));
echo $renderer->render(new supersession_page($rows, $error, $form, $self));
echo $OUTPUT->footer();
