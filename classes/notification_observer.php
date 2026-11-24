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

namespace tool_vae;

use tool_vae\event\attestation_withdrawn;
use tool_vae\event\certification_recorded;
use tool_vae\event\competency_left_record;
use tool_vae\event\consent_changed;
use tool_vae\event\deposit_created;
use tool_vae\event\evaluation_recorded;
use tool_vae\event\evidence_attested;
use tool_vae\event\evidence_declared;
use tool_vae\event\note_recorded;
use tool_vae\event\opinion_created;
use tool_vae\event\piece_added;
use tool_vae\event\request_created;
use tool_vae\event\withdrawal_refused;
use tool_vae\event\withdrawal_requested;
use tool_vae\local\consent_manager;
use tool_vae\local\draft_manager;
use tool_vae\local\note_manager;
use tool_vae\task\queue_notifications;
use tool_vae\task\send_notification;

/**
 * The events of this plugin that someone must be told of: each callback queues one ad hoc task, and does nothing else.
 *
 * WHY THIS CLASS ONLY QUEUES (ticket 3 of review MMR-520, lot 9). An observer runs in line, in the request that raised the
 * event; it keeps the identifiers the event carries - objectid, relateduserid, userid, contextinstanceid, other - and
 * leaves every reading of the database to a task run from cron. No database call and no loop is written in this file, and
 * tests/observer_guard_test.php reads it to keep it so, as it reads classes/observer.php.
 *
 * TWO KINDS OF CALLBACK, ONE TASK EACH.
 *   - The message has one recipient, and the event names them (the vaeiste a line is about, the manager a consent names,
 *     the signatory a request is put to, the manager who asked): the callback queues that message itself, a task
 *     send_notification, exactly as the notifier queued it before.
 *   - The recipients must be looked for - the managers who follow the vaeiste and may attest or read deposits, the
 *     referents of the bloc, an attestation in force, the vaeistes who declared against a competency: the callback queues
 *     a task queue_notifications with the identifiers of the event, and that task finds the recipients at the first run of
 *     cron after the event (\tool_vae\notifier::queue_recipients()), then queues one send_notification for each, with the
 *     same recipients, the same messages and the same checks as when the request did it. That is the one difference: the
 *     recipients are those of the moment cron runs, no longer those of the moment of the request.
 * Either way the message itself is composed and sent from cron, by \tool_vae\notifier::deliver(), which asks every question
 * again.
 *
 * WHAT A CALLBACK CANNOT DO. These observers are external - see db/events.php - so they run after the commit of the act
 * they announce. \core\event\manager turns an \Exception an observer throws into a debugging() call but lets an \Error
 * through, into a request whose act is already committed: every callback catches whatever it throws, and says so to a
 * developer. Queuing is one insert; a failure there is not expected, and is not worth an act.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_observer {
    /**
     * A vaeiste declared: the managers who follow them and may attest are found from cron.
     *
     * @param evidence_declared $event
     */
    public static function evidence_declared(evidence_declared $event): void {
        try {
            queue_notifications::queue(notifier::DECLARATION_PENDING, [
                'candidateid' => (int) $event->relateduserid,
                'evidenceid' => (int) $event->objectid,
            ]);
        } catch (\Throwable $e) {
            self::not_queued(notifier::DECLARATION_PENDING, $e);
        }
    }

    /**
     * A vaeiste deposited a document: the managers who follow them and may read deposits are found from cron (T-21).
     *
     * @param deposit_created $event
     */
    public static function deposit_created(deposit_created $event): void {
        try {
            queue_notifications::queue(notifier::DEPOSIT_MADE, [
                'candidateid' => (int) $event->relateduserid,
                'number' => (int) $event->other['number'],
                'doctype' => (string) ($event->other['doctype'] ?? draft_manager::DOCTYPE_FAISABILITE),
            ]);
        } catch (\Throwable $e) {
            self::not_queued(notifier::DEPOSIT_MADE, $e);
        }
    }

    /**
     * A supporting piece was added: whether the declaration carries a signature, and who is to be told, is found from cron
     * (T-13, lot 2).
     *
     * @param piece_added $event
     */
    public static function piece_added(piece_added $event): void {
        try {
            queue_notifications::queue(notifier::PIECE_AFTER_ATTEST, [
                'candidateid' => (int) $event->relateduserid,
                'evidenceid' => (int) $event->other['evidenceid'],
                'pieceid' => (int) $event->objectid,
            ]);
        } catch (\Throwable $e) {
            self::not_queued(notifier::PIECE_AFTER_ATTEST, $e);
        }
    }

    /**
     * A manager wrote an opinion on a deposit: the message to the vaeiste, the number of the deposit and nothing of it.
     *
     * @param opinion_created $event
     */
    public static function opinion_created(opinion_created $event): void {
        try {
            $candidateid = (int) $event->relateduserid;
            send_notification::queue(notifier::OPINION_MADE, $candidateid, $candidateid, [
                'number' => (int) $event->other['number'],
            ]);
        } catch (\Throwable $e) {
            self::not_queued(notifier::OPINION_MADE, $e);
        }
    }

    /**
     * An evaluation was written: the message to the vaeiste, that a line was added, nothing else (T-84).
     *
     * @param evaluation_recorded $event
     */
    public static function evaluation_recorded(evaluation_recorded $event): void {
        self::positioning_written((int) $event->relateduserid);
    }

    /**
     * A certification was written: the message to the vaeiste, the same way.
     *
     * @param certification_recorded $event
     */
    public static function certification_recorded(certification_recorded $event): void {
        self::positioning_written((int) $event->relateduserid);
    }

    /**
     * An annotation was written: the message to the vaeiste (lot 1b). A withdrawal tells nobody, as the event says.
     *
     * @param note_recorded $event
     */
    public static function note_recorded(note_recorded $event): void {
        try {
            if (($event->other['state'] ?? null) !== note_manager::ACTIVE) {
                return;
            }
            $candidateid = (int) $event->relateduserid;
            send_notification::queue(notifier::NOTE_ADDED, $candidateid, $candidateid, ['noteid' => (int) $event->objectid]);
        } catch (\Throwable $e) {
            self::not_queued(notifier::NOTE_ADDED, $e);
        }
    }

    /**
     * The vaeiste consented to a person annotating: the message to that person (lot 1b). A withdrawal tells nobody.
     *
     * @param consent_changed $event
     */
    public static function consent_changed(consent_changed $event): void {
        try {
            if (($event->other['state'] ?? null) !== consent_manager::GIVEN) {
                return;
            }
            // The manager is the person the event is about; the vaeiste is the owner of the context it is raised in.
            send_notification::queue(
                notifier::CONSENT_GIVEN,
                (int) $event->relateduserid,
                (int) $event->contextinstanceid,
                ['consentid' => (int) $event->objectid]
            );
        } catch (\Throwable $e) {
            self::not_queued(notifier::CONSENT_GIVEN, $e);
        }
    }

    /**
     * A manager asked for a precision on a declaration: the message to the vaeiste, neither the author nor the question.
     *
     * @param request_created $event
     */
    public static function request_created(request_created $event): void {
        try {
            $candidateid = (int) $event->relateduserid;
            send_notification::queue(notifier::PRECISION_ASKED, $candidateid, $candidateid, [
                'requestid' => (int) $event->objectid,
            ]);
        } catch (\Throwable $e) {
            self::not_queued(notifier::PRECISION_ASKED, $e);
        }
    }

    /**
     * A declaration was attested: the message to its vaeiste.
     *
     * @param evidence_attested $event
     */
    public static function evidence_attested(evidence_attested $event): void {
        try {
            $candidateid = (int) $event->relateduserid;
            send_notification::queue(notifier::EVIDENCE_ATTESTED, $candidateid, $candidateid, [
                'evidenceid' => (int) $event->other['evidenceid'],
            ]);
        } catch (\Throwable $e) {
            self::not_queued(notifier::EVIDENCE_ATTESTED, $e);
        }
    }

    /**
     * An attestation was withdrawn: the vaeiste, and the managers who follow them and may attest, are found from cron.
     *
     * @param attestation_withdrawn $event
     */
    public static function attestation_withdrawn(attestation_withdrawn $event): void {
        try {
            queue_notifications::queue(notifier::ATTESTATION_WITHDRAWN, [
                'candidateid' => (int) $event->relateduserid,
                'evidenceid' => (int) $event->other['evidenceid'],
            ]);
        } catch (\Throwable $e) {
            self::not_queued(notifier::ATTESTATION_WITHDRAWN, $e);
        }
    }

    /**
     * A manager asked a signatory to withdraw their attestation: the message to the signatory, whom the event names.
     *
     * Tied to THIS request - who asked, and the moment written on the attestation, which the event carries - so that a
     * message still waiting after a refusal is not delivered under the next request.
     *
     * @param withdrawal_requested $event
     */
    public static function withdrawal_requested(withdrawal_requested $event): void {
        try {
            send_notification::queue(
                notifier::WITHDRAWAL_REQUESTED,
                (int) $event->relateduserid,
                (int) $event->other['candidateid'],
                [
                    'evidenceid' => (int) $event->other['evidenceid'],
                    'requesterid' => (int) $event->userid,
                    'requestedat' => (int) $event->other['requestedat'],
                ]
            );
        } catch (\Throwable $e) {
            self::not_queued(notifier::WITHDRAWAL_REQUESTED, $e);
        }
    }

    /**
     * A signatory refused a request to withdraw: the message to the manager who asked, whom the event names.
     *
     * @param withdrawal_refused $event
     */
    public static function withdrawal_refused(withdrawal_refused $event): void {
        try {
            // The related user is the manager who asked; the vaeiste is in other.
            send_notification::queue(
                notifier::WITHDRAWAL_REFUSED,
                (int) $event->relateduserid,
                (int) $event->other['candidateid'],
                ['evidenceid' => (int) $event->other['evidenceid']]
            );
        } catch (\Throwable $e) {
            self::not_queued(notifier::WITHDRAWAL_REFUSED, $e);
        }
    }

    /**
     * A competency left its certification record: the vaeistes who declared against it are found from cron, and told after
     * the grace period, counted from the departure, if it still stands.
     *
     * @param competency_left_record $event
     */
    public static function competency_left_record(competency_left_record $event): void {
        try {
            queue_notifications::queue(notifier::COMPETENCY_LEFT_RECORD, [
                'competencyid' => (int) $event->other['competencyid'],
                'sourceid' => (int) $event->other['sourceid'],
                // The mark of THIS departure, so that the notifier can tell it from a later one.
                'markid' => (int) $event->objectid,
                // The grace period counts from the departure, not from the run of cron that finds the vaeistes.
                'runat' => time() + notifier::DEPARTURE_GRACE,
            ]);
        } catch (\Throwable $e) {
            self::not_queued(notifier::COMPETENCY_LEFT_RECORD, $e);
        }
    }

    /**
     * Queue the message of a positioning line to the person it is about.
     *
     * @param int $candidateid
     */
    private static function positioning_written(int $candidateid): void {
        try {
            send_notification::queue(notifier::POSITIONING_RECORDED, $candidateid, $candidateid);
        } catch (\Throwable $e) {
            self::not_queued(notifier::POSITIONING_RECORDED, $e);
        }
    }

    /**
     * Leave a trace of a notification that could not even be queued. See the class header.
     *
     * @param string $provider
     * @param \Throwable $e
     */
    private static function not_queued(string $provider, \Throwable $e): void {
        debugging("tool_vae: the '$provider' notification could not be queued: " . $e->getMessage(), DEBUG_DEVELOPER);
    }
}
