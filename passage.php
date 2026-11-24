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
 * The correspondences between the competencies of a replaced record and those of its successor (T-30, lot 2).
 *
 * The plugin suggests, a person decides one competency at a time, with a note. Nothing is moved by a decision.
 * admin_externalpage_setup() protects this page; correspondence_manager asks the right again where it writes.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_vae\local\correspondence_manager;
use tool_vae\output\correspondence_page;

$fiche = required_param('fiche', PARAM_ALPHANUMEXT);
$self = new moodle_url('/admin/tool/vae/passage.php', ['fiche' => $fiche]);

admin_externalpage_setup('toolvae_supersession', '', null, $self);

$manager = new correspondence_manager();
$back = new moodle_url('/admin/tool/vae/supersession.php');

try {
    $rows = $manager->overview($fiche);
    $targets = $manager->targets($fiche);
} catch (moodle_exception $e) {
    redirect($back, s($e->getMessage()), null, \core\output\notification::NOTIFY_ERROR);
}

$error = null;
$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    $fromid = required_param('fromid', PARAM_INT);
    try {
        $toid = optional_param('toid', 0, PARAM_INT);
        if ($action === 'decide' && $toid > 0) {
            $manager->decide($fromid, $toid, optional_param('note', '', PARAM_TEXT));
            redirect($self, get_string('passagesaved', 'tool_vae'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'undo' || $action === 'decide') {
            // The list entry "No equivalent decided" is the same as taking a decision back.
            $manager->undo($fromid);
            redirect($self, get_string('passageundone', 'tool_vae'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

// The table of the competencies and the form of each: worded by the class of output.
$renderer = $PAGE->get_renderer('tool_vae');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('passageheading', 'tool_vae', s($fiche)));
echo $renderer->render(new correspondence_page($rows, $targets, $error, $self, $back));
echo $OUTPUT->footer();
