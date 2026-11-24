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
 * The referents of the blocs of the imported records (T-23, lots 1 and 2).
 *
 * admin_externalpage_setup() protects this page; the same right is asked again where a referent is written
 * (referent_manager). A referent is a pointer for the messages, not a right: the page says so.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_vae\form\referent_form;
use tool_vae\local\referent_manager;
use tool_vae\local\supersession;
use tool_vae\output\referents_page;

admin_externalpage_setup('toolvae_referents');

$self = new moodle_url('/admin/tool/vae/referents.php');
$rows = supersession::overview();
$error = null;

// What each choice stands for: a record, and a bloc of it or the whole record.
[$options, $parts] = referent_manager::choices($rows);

$remove = optional_param('remove', 0, PARAM_INT);
if ($remove > 0) {
    require_sesskey();
    try {
        referent_manager::remove($remove);
        redirect($self, get_string('referentremoved', 'tool_vae'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

$form = new referent_form($self, ['options' => $options]);
if ($data = $form->get_data()) {
    try {
        [$fichecode, $ficherang] = $parts[(string) $data->bloc] ?? ['', ''];
        $user = $DB->get_record('user', ['username' => (string) $data->username, 'mnethostid' => $CFG->mnet_localhost_id]);
        if (!$user) {
            throw new moodle_exception('errorreferentuser', 'tool_vae');
        }
        referent_manager::add($fichecode, $ficherang, (int) $user->id);
        redirect($self, get_string('referentsaved', 'tool_vae'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

$renderer = $PAGE->get_renderer('tool_vae');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('referentheading', 'tool_vae'));

$referents = [];
$users = [];
if ($rows) {
    $referents = referent_manager::for_fiches(array_keys($rows));
    $userids = [];
    foreach ($referents as $list) {
        $userids = array_merge($userids, array_column($list, 'userid'));
    }
    $users = $userids ? $DB->get_records_list('user', 'id', array_unique($userids)) : [];
}
echo $renderer->render(new referents_page($rows, $referents, $users, $error, $form, $self));

echo $OUTPUT->footer();
