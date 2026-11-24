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
 * One notification, sent from cron rather than from the request that caused it.
 *
 * One task per recipient, carrying identifiers and nothing else - a provider name, user ids, the
 * id of a declaration, or of a competency, its record and the mark of its departure, or the ids of the
 * declarations an erasure left without an attestation. No text:
 * the task table is not a place the narrative, or even a sentence about it, has any business
 * being.
 *
 * A task that throws is retried by the task manager and shows in the task logs; a message that
 * is no longer due - the recipient suspended, a right removed, a departure undone - is not sent,
 * and the log says so. See \tool_vae\notifier::deliver().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_notification extends \core\task\adhoc_task {
    /**
     * Queue one notification for one recipient.
     *
     * @param string $provider A provider of \tool_vae\notifier.
     * @param int $recipientid
     * @param int $candidateid Whose dossier the message is about.
     * @param array $about Identifiers of what the message is about. Identifiers only.
     * @param int $runat When to send it; as soon as cron runs when 0.
     */
    public static function queue(string $provider, int $recipientid, int $candidateid, array $about = [], int $runat = 0): void {
        $task = new self();
        $task->set_custom_data((object) ([
            'provider' => $provider,
            'recipientid' => $recipientid,
            'candidateid' => $candidateid,
        ] + $about));

        if ($runat > 0) {
            $task->set_next_run_time($runat);
        }

        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Name shown in the task logs.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksendnotification', 'tool_vae');
    }

    /**
     * Send the notification, if it is still due.
     */
    public function execute() {
        $data = $this->get_custom_data();

        if (!notifier::deliver($data)) {
            mtrace(
                "tool_vae: '{$data->provider}' notification to user " . (int) $data->recipientid
                    . ' not sent: no longer due, or refused by the messaging subsystem.'
            );
        }
    }
}
