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
 * Deletions decided outside this plugin, the handlers that catch up with them, and the messages
 * that tell the right people what happened in it.
 *
 * The event names are literal strings on purpose. This file is read by the event manager at
 * install time and on every cache rebuild, including before this plugin's classes can be
 * autoloaded; a class constant here would be a dependency at the worst possible moment.
 *
 * THE FOUR CATCH-UP OBSERVERS ARE DECLARED INTERNAL, AND THE WORD IS WRITTEN OUT ON
 * PURPOSE. Moodle's default is `internal => true` - \core\event\manager::add_observers() sets
 * it when the key is absent - so omitting it does not make an observer external, it makes it
 * internal while looking like a decision nobody took. Declared, it can be read and argued with.
 *
 * WHAT THEY DO: THEY ONLY QUEUE (ticket 3 of review MMR-520). Each of the four queues one ad hoc
 * task carrying identifiers, and does nothing else; the purge, the comparison or the release of a
 * provenance row runs from cron, at its next run. Internal is still the right choice for that: an
 * internal observer runs inside the transaction of whoever caused the deletion, when there is one,
 * so the task is queued with the deletion and rolled back with it if the deletion does not stand.
 * On the two paths the core actually takes for an account and an evidence record there is no
 * surrounding transaction at all (verified in the source of Moodle 5.3: delete_user() opens none):
 * the deletion stands at once, the task stays in the queue, and the dossier stays until the task
 * has run. The fourth, a framework deleted in tool_lp, is raised by the core AFTER the commit of
 * the deletion (api::delete_framework()), and joins no transaction whatever it declares.
 *
 * NOT A GUARANTEE THAT THE DELETION FAILS WITH THE PURGE. The event manager turns every exception
 * an observer throws into a debugging() call, so nothing an observer does can make a deletion
 * fail. The guarantee is elsewhere: a task that fails throws, shows in the task logs and is run
 * again by the task manager; the scheduled task reconcile_orphans catches up with what a follow-up
 * that never ran would have removed. See \tool_vae\observer and \tool_vae\local\deletion_followup.
 *
 * THE FOURTEEN NOTIFICATION OBSERVERS ARE EXTERNAL, AND FOR THE OPPOSITE REASON (lot 7 ter). A
 * purge must belong to the act it reacts to; a message must not. Run after the commit, they
 * announce only what the site holds: the import of a certification record raises its departures
 * inside its own transaction, and an import that rolls back takes them with it, unheard. Each of
 * them reads nothing and queues ONE ad hoc task (ticket 3 of review MMR-520, lot 9): the message
 * itself when the event names its one recipient; otherwise one task for the event, which finds
 * the recipients at the first run of cron. The sending happens in cron. They catch whatever they
 * throw themselves, an \Error included, which the event manager would let through into a request
 * whose act is already committed. See \tool_vae\notification_observer and \tool_vae\notifier.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\competency_user_evidence_deleted',
        'callback' => '\tool_vae\observer::user_evidence_deleted',
        'internal' => true,
    ],
    [
        'eventname' => '\core\event\competency_user_evidence_updated',
        'callback' => '\tool_vae\observer::user_evidence_updated',
        'internal' => true,
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => '\tool_vae\observer::user_deleted',
        'internal' => true,
    ],
    [
        'eventname' => '\core\event\competency_framework_deleted',
        'callback' => '\tool_vae\observer::framework_deleted',
        'internal' => true,
    ],
    [
        'eventname' => '\tool_vae\event\evidence_declared',
        'callback' => '\tool_vae\notification_observer::evidence_declared',
        'internal' => false,
    ],
    [
        'eventname' => '\tool_vae\event\piece_added',
        'callback' => '\tool_vae\notification_observer::piece_added',
        'internal' => false,
    ],
    [
        'eventname' => '\tool_vae\event\deposit_created',
        'callback' => '\tool_vae\notification_observer::deposit_created',
        'internal' => false,
    ],
    [
        'eventname' => '\tool_vae\event\evaluation_recorded',
        'callback' => '\tool_vae\notification_observer::evaluation_recorded',
        'internal' => false,
    ],
    [
        'eventname' => '\tool_vae\event\certification_recorded',
        'callback' => '\tool_vae\notification_observer::certification_recorded',
        'internal' => false,
    ],
    [
        'eventname' => '\tool_vae\event\opinion_created',
        'callback' => '\tool_vae\notification_observer::opinion_created',
        'internal' => false,
    ],
    [
        'eventname' => '\tool_vae\event\request_created',
        'callback' => '\tool_vae\notification_observer::request_created',
        'internal' => false,
    ],
    [
        'eventname' => '\tool_vae\event\evidence_attested',
        'callback' => '\tool_vae\notification_observer::evidence_attested',
        'internal' => false,
    ],
    [
        'eventname' => '\tool_vae\event\attestation_withdrawn',
        'callback' => '\tool_vae\notification_observer::attestation_withdrawn',
        'internal' => false,
    ],
    [
        'eventname' => '\tool_vae\event\withdrawal_requested',
        'callback' => '\tool_vae\notification_observer::withdrawal_requested',
        'internal' => false,
    ],
    [
        'eventname' => '\tool_vae\event\withdrawal_refused',
        'callback' => '\tool_vae\notification_observer::withdrawal_refused',
        'internal' => false,
    ],
    [
        'eventname' => '\tool_vae\event\competency_left_record',
        'callback' => '\tool_vae\notification_observer::competency_left_record',
        'internal' => false,
    ],
    // Lot 1b: an annotation is told to the vaeiste, a consent to the person it names; a withdrawal tells nobody.
    [
        'eventname' => '\tool_vae\event\note_recorded',
        'callback' => '\tool_vae\notification_observer::note_recorded',
        'internal' => false,
    ],
    [
        'eventname' => '\tool_vae\event\consent_changed',
        'callback' => '\tool_vae\notification_observer::consent_changed',
        'internal' => false,
    ],
];
