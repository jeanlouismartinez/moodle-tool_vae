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
use tool_vae\local\plugin_schema;

/**
 * A framework of competencies was deleted in tool_lp: release the provenance row of the record imported into it, from cron.
 *
 * Custom data: `frameworkid`, the deleted framework, and `userid`, the account that deleted it, named on the row as the
 * observer named it. See \tool_vae\local\deletion_followup::framework_deleted().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class release_deleted_framework extends followup_task {
    /**
     * Name shown in the task logs.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskreleasedeletedframework', 'tool_vae');
    }

    /**
     * The two tables of the records.
     *
     * @return string[]
     */
    protected function required_tables(): array {
        return plugin_schema::RECORD_TABLES;
    }

    /**
     * Release the provenance rows of the deleted framework.
     *
     * @param \stdClass $data
     */
    protected function follow_up(\stdClass $data): void {
        $frameworkid = (int) $data->frameworkid;
        $released = deletion_followup::framework_deleted($frameworkid, (int) $data->userid);

        mtrace('tool_vae: ' . $released . ' provenance row(s) of the deleted framework ' . $frameworkid . ' released.');
    }
}
