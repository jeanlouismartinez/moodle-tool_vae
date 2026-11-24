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
 * The addresses where a certifier publishes its rule of validation, record by record (T-41, lot 1).
 *
 * admin_externalpage_setup() protects this page; the same right is asked again where an address is written
 * (ref_manager). The page names nobody: the addresses are public.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_vae\form\ref_form;
use tool_vae\local\ref_manager;
use tool_vae\local\supersession;
use tool_vae\output\refs_page;

admin_externalpage_setup('toolvae_refs');

$self = new moodle_url('/admin/tool/vae/refs.php');
$rows = supersession::overview();
$options = [];
foreach ($rows as $row) {
    $options[$row->fichecode] = $row->fichecode . ' — ' . format_string($row->label);
}

$error = null;

$remove = optional_param('remove', 0, PARAM_INT);
if ($remove > 0) {
    require_sesskey();
    try {
        ref_manager::remove($remove);
        redirect($self, get_string('refremoved', 'tool_vae'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

$form = new ref_form($self, ['options' => $options]);
if ($data = $form->get_data()) {
    try {
        // The picker gives midnight in the administrator's timezone. What is kept is the calendar day they picked, at noon
        // UTC, and it is shown in UTC: a reader in another timezone sees the same day.
        $checked = null;
        if (!empty($data->checkedat)) {
            $day = usergetdate((int) $data->checkedat);
            $checked = gmmktime(12, 0, 0, $day['mon'], $day['mday'], $day['year']);
        }
        [, $warnings] = ref_manager::add(
            (string) $data->fichecode,
            (string) $data->title,
            (string) $data->url,
            $checked
        );
        $message = get_string('refsaved', 'tool_vae');
        foreach ($warnings as $warning) {
            $message .= ' ' . get_string($warning, 'tool_vae');
        }
        redirect($self, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

$renderer = $PAGE->get_renderer('tool_vae');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('refheading', 'tool_vae'));

$open = !empty(get_config('tool_vae', 'refopen'));
$refs = $rows ? ref_manager::for_fiches(array_keys($rows)) : [];
echo $renderer->render(new refs_page($rows, $refs, $open, $error, $form, $self));

echo $OUTPUT->footer();
