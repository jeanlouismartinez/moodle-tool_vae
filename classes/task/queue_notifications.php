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

use tool_vae\notifier;

/**
 * Find, from cron, who is to be told of one event, and queue one send_notification per recipient (ticket 3 of review
 * MMR-520).
 *
 * The callbacks of a declaration, a deposit, a piece added, a withdrawal and a departure (\tool_vae\notification_observer)
 * read nothing in the request that raised the event: they queue this task with the identifiers of the event, and nothing
 * else - the kind of event, then ids, and for a deposit the code of the document deposited. See
 * \tool_vae\notifier::queue_recipients().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_notifications extends \core\task\adhoc_task {
    /**
     * Queue the task that will find the recipients of an event from cron.
     *
     * Not with duplicate detection: two withdrawals of the same declaration before cron runs were two messages before this
     * task existed, and still are.
     *
     * @param string $kind A provider of \tool_vae\notifier that queue_recipients() knows.
     * @param array $identifiers What the event says: ids, and for a deposit the code of the document.
     */
    public static function queue(string $kind, array $identifiers): void {
        $task = new self();
        $task->set_custom_data((object) (['kind' => $kind] + $identifiers));

        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Name shown in the task logs.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskqueuenotifications', 'tool_vae');
    }

    /**
     * Queue the messages of the event.
     */
    public function execute() {
        notifier::queue_recipients($this->get_custom_data());
    }
}
