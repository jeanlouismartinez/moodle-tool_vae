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

use tool_vae\task\check_changed_evidence;
use tool_vae\task\purge_deleted_account;
use tool_vae\task\purge_deleted_evidence;
use tool_vae\task\release_deleted_framework;

/**
 * Deletions and changes decided outside this plugin: each is followed up by an ad hoc task, queued here and run from cron.
 *
 * WHY OBSERVERS AT ALL. The candidate can destroy their own evidence record from tool_lp, the administrator can delete the
 * account or a framework of competencies, and none of these goes through this plugin. Without a follow-up, the narrative -
 * free text about care work, held in this plugin's tables - would survive the record it hung off, invisible in every
 * interface and out of reach of any later purge. That is criterion 8. What each follow-up does, and why, is written once in
 * \tool_vae\local\deletion_followup, which the scheduled catch-up (\tool_vae\task\reconcile_orphans) calls too.
 *
 * WHY THIS CLASS ONLY QUEUES (ticket 3 of review MMR-520). An observer runs in line, in the request of whoever deleted;
 * it keeps the minimum of the event - identifiers - and leaves the work to a task. No database call and no loop is written
 * here, and tests/observer_guard_test.php reads the tokens of this file to keep it so.
 *
 * WHAT AN OBSERVER CANNOT DO. \core\event\manager catches every \Exception an observer throws and turns it into a
 * debugging() call - not every \Throwable: an \Error goes straight through and aborts the core call that was running. So a
 * failure here is either silent or brutal, and never the clean refusal one would want. Nothing in this class may depend on
 * an exception reaching the caller; queuing is one insert, and the work that could fail is no longer done here.
 *
 * THE RESIDUAL RISK, NAMED. delete_user() and the core's deletion of an evidence record open no transaction (verified in the
 * source of Moodle 5.3): the account or the record is gone when the task is queued, and the dossier stays until the next
 * run of cron takes the task; how long that is depends on how often the site runs cron. A purge that fails throws out of the
 * task: the failure shows in the task logs, and the task manager runs the task again, with a growing delay. A task that
 * never ran at all - queued before this version, or lost - is caught up by the scheduled task reconcile_orphans, which
 * removes what such an account or record left behind.
 *
 * ORDER OF OPERATIONS. The four observers are declared internal - see db/events.php for why - so when a transaction does
 * surround the deletion, the task is queued inside it, and is rolled back with it if the deletion does not stand.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * The core deleted one evidence record: queue the purge of the claims that hung off it.
     *
     * @param \core\event\base $event Carries the deleted record's id as objectid.
     */
    public static function user_evidence_deleted(\core\event\base $event): void {
        purge_deleted_evidence::queue(['userevidenceid' => (int) $event->objectid]);
    }

    /**
     * The core record a claim hangs off was changed: queue the comparison of its label.
     *
     * @param \core\event\base $event Carries the changed record's id as objectid.
     */
    public static function user_evidence_updated(\core\event\base $event): void {
        check_changed_evidence::queue(['userevidenceid' => (int) $event->objectid]);
    }

    /**
     * An account was deleted: queue the purge of the dossier it owned.
     *
     * Its id only: the task is not tied to the account (the core refuses to run a task as a deleted account).
     *
     * @param \core\event\base $event Carries the deleted account's id as objectid.
     */
    public static function user_deleted(\core\event\base $event): void {
        purge_deleted_account::queue(['userid' => (int) $event->objectid]);
    }

    /**
     * A framework of competencies was deleted in tool_lp: queue the release of the provenance row of its record.
     *
     * The account that deleted it travels with the framework: the row names who changed it, as it always did.
     *
     * @param \core\event\competency_framework_deleted $event Carries the deleted framework's id as objectid.
     */
    public static function framework_deleted(\core\event\competency_framework_deleted $event): void {
        release_deleted_framework::queue(['frameworkid' => (int) $event->objectid, 'userid' => (int) $event->userid]);
    }
}
