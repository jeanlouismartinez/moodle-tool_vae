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

use tool_vae\local\plugin_schema;

/**
 * The follow-up of a deletion or a change decided outside this plugin, run from cron (ticket 3 of review MMR-520).
 *
 * An observer queues one of these and does nothing else; see \tool_vae\observer. The task carries identifiers and nothing
 * else - the id of a core record, of an account, of a framework - like send_notification: the task table is not a place the
 * narrative, or even a sentence about it, has any business being. It is queued with duplicate detection
 * (\core\task\manager::queue_adhoc_task($task, true)): the same event twice, before cron runs, queues it once.
 *
 * THE SCHEMA IS ASKED FIRST. An observer is notified during installation and upgrade, before the schema exists; the task
 * then finds a schema that may be incomplete. It stops without an error and says so in the cron log: there is nothing of
 * this plugin to clean up in tables that are not there.
 *
 * A FAILURE IS NOT SWALLOWED. Whatever the work throws leaves execute(): the task manager records the failure on the task,
 * shows it in the task logs, and runs it again later with a growing delay. Every follow-up finds nothing the second time:
 * a task run again after it did its work, or a duplicate, is harmless. And the scheduled task reconcile_orphans catches up
 * with what a task that never ran would have done.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class followup_task extends \core\task\adhoc_task {
    /**
     * Queue one follow-up, unless the same one already waits.
     *
     * @param int[] $identifiers What the task needs, identifiers only.
     */
    public static function queue(array $identifiers): void {
        $task = new static();
        $task->set_custom_data(array_map('intval', $identifiers));
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Check the schema, then do the work.
     */
    public function execute() {
        if (!plugin_schema::has_tables($this->required_tables())) {
            mtrace('tool_vae: ' . static::class . ' skipped: the tables of the plugin are not all installed yet.');

            return;
        }

        $this->follow_up($this->get_custom_data());
    }

    /**
     * The tables the work writes to.
     *
     * @return string[]
     */
    protected function required_tables(): array {
        return plugin_schema::DECLARATION_TABLES;
    }

    /**
     * Do the work.
     *
     * @param \stdClass $data The identifiers the observer queued.
     */
    abstract protected function follow_up(\stdClass $data): void;
}
