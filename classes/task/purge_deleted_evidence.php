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

namespace tool_vae\task;

use tool_vae\local\deletion_followup;

/**
 * The core deleted one evidence record: destroy the claims that hung off it, from cron.
 *
 * Custom data: `userevidenceid`, the deleted core record. See \tool_vae\local\deletion_followup::evidence_deleted().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_deleted_evidence extends followup_task {
    /**
     * Name shown in the task logs.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskpurgedeletedevidence', 'tool_vae');
    }

    /**
     * Purge the claims of the deleted record.
     *
     * @param \stdClass $data
     */
    protected function follow_up(\stdClass $data): void {
        $userevidenceid = (int) $data->userevidenceid;
        $records = deletion_followup::evidence_deleted($userevidenceid);

        mtrace('tool_vae: ' . count($records) . ' declaration(s) of the deleted evidence record ' . $userevidenceid . ' removed.');
    }
}
