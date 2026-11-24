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
 * An account was deleted: destroy the dossier it owned and the traces of the person, from cron.
 *
 * Custom data: `userid`, the deleted account. Not the task's own user id: a task tied to an account is refused by the core
 * once that account is deleted. See \tool_vae\local\deletion_followup::account_deleted() for what goes and what stays.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_deleted_account extends followup_task {
    /**
     * Name shown in the task logs.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskpurgedeletedaccount', 'tool_vae');
    }

    /**
     * Purge the dossier of the deleted account.
     *
     * @param \stdClass $data
     */
    protected function follow_up(\stdClass $data): void {
        $userid = (int) $data->userid;
        $records = deletion_followup::account_deleted($userid);

        mtrace('tool_vae: dossier of the deleted account ' . $userid . ' purged, ' . count($records) . ' declaration(s) removed.');
    }
}
