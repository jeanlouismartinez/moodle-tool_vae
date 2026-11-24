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
 * Attach an imported certification record to a course (lot 8 bis).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_vae\local\competency_gateway;
use tool_vae\local\course_linker;
use tool_vae\output\course_link_page;

$courseid = required_param('courseid', PARAM_INT);
$fichecode = optional_param('fichecode', '', PARAM_ALPHANUMEXT);

$course = get_course($courseid);
require_login($course);

$context = course_linker::require_can_link($courseid);

$url = new moodle_url('/admin/tool/vae/course_link.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('linkrecord', 'tool_vae'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$renderer = $PAGE->get_renderer('tool_vae');

if (!competency_gateway::is_subsystem_enabled()) {
    echo $OUTPUT->header();
    echo $renderer->render(course_link_page::disabled());
    echo $OUTPUT->footer();
    die();
}

$linker = new course_linker();

if ($fichecode !== '') {
    require_sesskey();

    // A refusal the linker names - unknown record, framework gone or hidden in the core - is told on this
    // screen, where the manager is, and not by an error page. Only what this plugin names is: any other
    // exception - a database fault, a refusal of the core, a coding_exception - is a fault, and it is left to
    // say so, with its page, its trace and its line in the log. They are all moodle_exceptions, so it is
    // the module they carry that tells them apart; that of a coding_exception is "debug".
    try {
        $added = $linker->link($courseid, $fichecode);
    } catch (\moodle_exception $e) {
        if ($e->module !== 'tool_vae') {
            throw $e;
        }
        // The message was escaped where it was thrown, like the one of import.php.
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    $message = $added
        ? get_string('linkrecorddone', 'tool_vae', (object) ['added' => $added, 'code' => $fichecode])
        : get_string('linkrecordnothing', 'tool_vae', $fichecode);

    redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('linkrecord', 'tool_vae'));

$records = $linker->records($courseid);
echo $renderer->render($records ? course_link_page::records($records, $url) : course_link_page::none());
echo $OUTPUT->footer();
