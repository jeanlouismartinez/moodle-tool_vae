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

namespace tool_vae\local;

use tool_vae\event\dossier_purged;
use tool_vae\event\evidence_diverged;
use tool_vae\event\evidence_purged;

/**
 * What this plugin does about a deletion, or a change, decided outside it - the work the observers used to do in line.
 *
 * Since ticket 3 of review MMR-520, the observers (\tool_vae\observer) only queue an ad hoc task; the task calls this class
 * from cron, and so does the scheduled task that catches up with a follow-up that never ran (\tool_vae\task\reconcile_orphans):
 * one writing of each rule, for both. Nothing of WHAT is purged, cleared or announced changed with the move; only WHEN: at the
 * next run of cron instead of inside the request that deleted.
 *
 * THE CONTEXT OF A TRACE. An event of this class is filed against the user context of the person it is about, read with
 * IGNORE_MISSING and never created for a deleted account: delete_user() keeps that context (it deletes its content), but the
 * core's context clean-up task deletes it later, and context_user::instance() only recreates a missing context for an account
 * that is not deleted. When it is gone, the trace falls back to the system context, as the catch-up always did. Logged at
 * system level it is visible to holders of moodle/site:viewreports, which is a wider audience: the fallback is for the case
 * where there is no other choice.
 *
 * WHO A TRACE NAMES. An event raised from cron is filed under the cron user (the main administrator, see \core\cron::setup_user()),
 * not under the account that deleted: the observer runs in that request, the task does not. The person the trace is about
 * is its related user, as before.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deletion_followup {
    /**
     * The core deleted one evidence record: destroy the claim that hung off it, and leave one trace per claim.
     *
     * WHY THIS IS NEEDED AT ALL. The candidate can destroy their own evidence record from tool_lp, through an AJAX web service
     * this plugin never sees. The core removes its own rows and stops there. The narrative - free text about care work, held
     * in this plugin's table - would survive, anchored to a record that no longer exists: invisible in every interface, and out
     * of reach of any later purge. That is criterion 8.
     *
     * @param int $userevidenceid The deleted core record.
     * @return \stdClass[] The claims removed; none when there were none, or when they were already removed.
     */
    public static function evidence_deleted(int $userevidenceid): array {
        $records = purge::by_userevidence($userevidenceid);

        foreach ($records as $record) {
            evidence_purged::create([
                'context' => self::context_of_user((int) $record->candidateid),
                'objectid' => (int) $record->id,
                'relateduserid' => (int) $record->candidateid,
                'other' => [
                    'userevidenceid' => $userevidenceid,
                    'attestations' => (int) ($record->attestations ?? 0),
                ],
            ])->trigger();
        }

        return $records;
    }

    /**
     * The core record a claim hangs off was changed: say so, and change nothing.
     *
     * The candidate can rename their own evidence record from tool_lp, under a capability the authenticated-user archetype
     * carries natively. Nothing in this plugin grants it and nothing here can take it away - so this method does not try. It
     * compares the label the core now holds with the one this plugin would generate, and leaves a trace when they differ. The
     * dossier tells its reader the same thing, computed on sight.
     *
     * Unlinking a competency raises NO event at all, so it cannot be caught here; the dossier catches it, which is why the
     * dossier - not this method - is where the guarantee lives.
     *
     * THE TRACE REPEATS, AND THAT IS NOT A BUG WORTH FIXING HERE. Once an anchor has diverged, every later edit of that record
     * - a url, a description - logs the divergence again, because this method compares a state and knows nothing of what it
     * logged before. Measured in adversarial review: three unrelated edits, three lines. Suppressing them would mean reading
     * the log to decide whether to write to it. The first line is the date the divergence began, which is what the trace is
     * for; the rest are noise in a log that already carries every core event on the site. Since the comparison runs from cron,
     * it compares the state of the record at that moment; two edits between two runs of cron are compared once (the second
     * event finds the first task still waiting and queues nothing).
     *
     * @param int $userevidenceid The changed core record.
     * @return int How many divergences were logged.
     */
    public static function evidence_changed(int $userevidenceid): int {
        global $DB;

        $logged = 0;
        foreach ($DB->get_records('tool_vae_evidence', ['userevidenceid' => $userevidenceid]) as $record) {
            if (anchor::divergence($record) === null) {
                continue;
            }

            evidence_diverged::create([
                'context' => self::context_of_user((int) $record->candidateid),
                'objectid' => (int) $record->id,
                'relateduserid' => (int) $record->candidateid,
                'other' => ['userevidenceid' => $userevidenceid],
            ])->trigger();
            $logged++;
        }

        return $logged;
    }

    /**
     * An account was deleted: destroy the dossier it owned, and the traces of the person in this plugin.
     *
     * WHY ITS OWN HANDLER. Deleting an ACCOUNT does not go through the door of evidence_deleted(). delete_user() calls the
     * competency subsystem's own user hook, which removes the evidence records with a bulk delete and triggers NO event at all
     * - verified against the source and measured on a running site on 28 August 2026: core row gone, plugin row and
     * attestation still there. A follow-up of the single-record deletion is therefore not enough, and believing otherwise would
     * have left the wider hole open behind a control that looked complete.
     *
     * Only the candidate's side. A manager whose account is deleted keeps their signature on the dossiers they attested: for
     * THIS handler, deleting an account is not a request for erasure, and emptying those fields would degrade the file of every
     * candidate they ever signed for, none of whom asked for anything. Erasure has its own door, the privacy provider. Decision
     * of 28 August 2026.
     *
     * WHAT THE PLATFORM DOES NEXT, AND IT IS NOT NOTHING. On a Moodle left at its defaults, tool_dataprivacy observes the same
     * event and files an erasure request in the name of the deleted account (automaticdeletionrequests is on), left awaiting
     * approval (automaticdatadeletionapproval is off). When the site's data protection officer approves it, the privacy
     * provider runs for that account and strips every signature it gave: four fields emptied, the attestation marked
     * withdrawn. So the signature outlives the deletion of the account and goes with the approval of the request, and the
     * person who approves should know it - docs/T-84-conformite-rgpd.md says so, and tests/observer_test.php runs the whole
     * chain. Each candidate whose declarations lose an attestation then receives one message, and the managers who follow them
     * one too (tests/erased_signatory_notice_test.php): approving sends emails.
     *
     * ONE EXCEPTION, decided on 12 October 2026 (lot 8 quater). A signature carried in from an archive names this person by
     * their ULID alone, and the purge deletes the map entry that ties the two. Left in place, it could never be erased
     * afterwards: the privacy provider finds such a signature only through that entry. So the purge strips it, and refreshes
     * the backups of the dossiers it stood in. A signature given here, by account, still stays.
     *
     * AND THE CANDIDATES ARE TOLD (decision of 27 October 2026). The attestation withdrawn here is withdrawn without the
     * approval of anybody, so purge::dossier() hands the candidates whose declarations lost one to
     * notifier::attestations_erased() as soon as the strip is recorded - before the backups are redone. The event of an
     * ordinary withdrawal is not raised: this runs under the cron user, who withdrew nothing.
     *
     * WHAT THIS ACCOUNT SENT TO THE REFORMULATION AID (T-70) IS NOT ERASED HERE: the core keeps the text and the answer in its
     * own tables, and the deletion request Moodle files at each deletion of an account empties them once a person approves it.
     * One door for everything the core keeps, decided on 27 October 2026 (tests/ai_erasure_left_to_moodle_test.php). That door
     * is shut on a site that switched the automatic requests off, and on a site that defined the purpose and the category of
     * the site in the data registry: the core then never empties what is bound to the site. The confirmation screen of the aid
     * says so (ai_assistant::moodle_erases_the_texts()).
     *
     * @param int $userid The deleted account.
     * @return \stdClass[] The declarations removed with the dossier, each carrying an `attestations` count.
     */
    public static function account_deleted(int $userid): array {
        self::clear_traces('= :userid', ['userid' => $userid]);

        $records = purge::dossier($userid);
        if (!$records) {
            return [];
        }

        $attestations = 0;
        foreach ($records as $record) {
            $attestations += (int) ($record->attestations ?? 0);
        }

        dossier_purged::create([
            'context' => self::context_of_user($userid),
            'relateduserid' => $userid,
            'other' => [
                'declarations' => count($records),
                'attestations' => $attestations,
            ],
        ])->trigger();

        return $records;
    }

    /**
     * The catch-up of the traces account_deleted() clears, for every account that is gone - deleted, or no longer in the user
     * table - when its task never ran: it did not exist yet at the deletion, or gave up after failing too often.
     *
     * One statement per table and column, the condition on the accounts written once, with no loop over the users. The rest
     * of what account_deleted() does has its own catch-up in the same scheduled task: the claims whose core record is gone
     * (purge::orphans()), the rest of the dossier and the identity map (purge::orphan_sections()), the backups
     * (backup::discard_orphans(), document_copies::discard_orphans()).
     */
    public static function clear_traces_of_gone_accounts(): void {
        self::clear_traces('NOT IN (SELECT id FROM {user} WHERE deleted = 0)', []);
    }

    /**
     * Clear what this plugin keeps of the accounts a condition designates.
     *
     * @param string $accounts The condition on a column that holds an account id, written after the column: `= :userid` for
     *        one account, a NOT IN subquery for every account that is gone. Its parameters are named.
     * @param array $params The parameters of the condition.
     */
    private static function clear_traces(string $accounts, array $params): void {
        global $DB;

        // A request this person had open is theirs and goes with them: the signatory is asked nothing on behalf of an account
        // that no longer exists.
        $DB->execute(
            "UPDATE {tool_vae_attest} SET withdrawreqby = NULL, withdrawreqat = NULL WHERE withdrawreqby $accounts",
            $params
        );

        // And the other way round: a request addressed TO this person, whose signature stays. Only the signatory answers a
        // request - accepts it by withdrawing, or refuses - and that signatory is gone. Left in place it would show every
        // colleague, for good, "a request is awaiting the signatory", and forbid a new one. The signature is not touched; the
        // request that nobody can answer falls.
        $DB->execute(
            "UPDATE {tool_vae_attest} SET withdrawreqby = NULL, withdrawreqat = NULL
              WHERE attestantid $accounts AND withdrawreqat IS NOT NULL",
            $params
        );

        // The pieces this account opened (T-13, lot 2): a trace of a person, not worth keeping for an account that is gone.
        $DB->delete_records_select('tool_vae_piece_open', "userid $accounts", $params);

        // The blocs this account was referent of (T-23), and the trace of who named them: the rule goes with the person. Zero
        // is what an anonymous line already holds: never rewritten.
        $DB->delete_records_select('tool_vae_referent', "userid $accounts", $params);
        $DB->execute("UPDATE {tool_vae_referent} SET createdby = 0 WHERE createdby <> 0 AND createdby $accounts", $params);

        // The authorization to produce the archive of the structure: the lines about this manager go with the account, and
        // who wrote a line leaves it (the line stays, without the name).
        $DB->delete_records_select('tool_vae_booklet_auth', "managerid $accounts", $params);
        $DB->execute("UPDATE {tool_vae_booklet_auth} SET grantedby = NULL WHERE grantedby $accounts", $params);
    }

    /**
     * A framework of competencies was deleted in tool_lp: the record that was imported into it has none any more.
     *
     * The core allows it as long as nobody has declared on the framework - it refuses once a user competency, a course or a
     * plan holds one of its competencies - so no declaration is lost here, and none is touched. What is left behind is this
     * plugin's own row of provenance, which still pointed at a framework that does not exist: the screens had to guess, from
     * an id that leads nowhere, what had happened.
     *
     * WHY THE ROW IS KEPT, WITH NO FRAMEWORK, AND NOT DELETED. It is the licence, the attribution, the publication date and,
     * when an administrator declared one, the successor of the record (T-30) - and the declarations of a record are found by
     * its code, which this row owns. Its framework id becomes 0, which is what a first import that never got a framework
     * already writes, and every reader of the plugin treats it as "no framework" (competency_gateway answers nothing for it,
     * index.php warns, the import creates a new framework and takes the row back). Its status says why, in a word no code
     * branches on: the table of the records shows anything but `ok` as a failed import, which is acceptable for a record that
     * cannot be used until it is imported again, and index.php says what to do.
     *
     * THE MARKS OF A DEPARTURE GO WITH THE FRAMEWORK. A competency that had left the record, or that tool_lp had altered, is
     * noted in tool_vae_obsolete against the row; the competencies named there were deleted with the framework, and a mark that
     * points at nothing would be counted in the table of the records for good. A re-import writes them again if they are
     * still true.
     *
     * @param int $frameworkid The deleted framework.
     * @param int $actorid Who deleted it, named on the row; 0 when nobody is known (the catch-up).
     * @return int How many provenance rows were released.
     */
    public static function framework_deleted(int $frameworkid, int $actorid): int {
        global $DB;

        // Never "the records with no framework": an id of zero is what a source that never got one holds.
        if ($frameworkid <= 0) {
            return 0;
        }

        $released = 0;
        foreach ($DB->get_records('tool_vae_source', ['frameworkid' => $frameworkid]) as $source) {
            $DB->delete_records('tool_vae_obsolete', ['sourceid' => (int) $source->id]);
            $DB->update_record('tool_vae_source', (object) [
                'id' => (int) $source->id,
                'frameworkid' => 0,
                'lastimportstatus' => 'deleted',
                'timemodified' => time(),
                'usermodified' => $actorid,
            ]);
            $released++;
        }

        return $released;
    }

    /**
     * The catch-up of framework_deleted(), for every provenance row whose framework the core no longer holds and whose task
     * never ran: the framework deleted before this follow-up existed, or by a direct write that raised no event.
     *
     * The core is asked through the gateway (MC-5). Who deleted the framework is not known: the row names nobody (0).
     *
     * @param competency_gateway|null $gateway Only passed by tests.
     * @return int How many provenance rows were released.
     */
    public static function release_gone_frameworks(?competency_gateway $gateway = null): int {
        global $DB;

        $gateway = $gateway ?? new competency_gateway();

        $frameworkids = array_map('intval', $DB->get_fieldset_select(
            'tool_vae_source',
            'DISTINCT frameworkid',
            'frameworkid > 0'
        ));
        $existing = $gateway->existing_framework_ids($frameworkids);

        $released = 0;
        foreach (array_diff($frameworkids, $existing) as $frameworkid) {
            $released += self::framework_deleted($frameworkid, 0);
        }

        return $released;
    }

    /**
     * The context a trace about a person is filed against: their user context while it exists, the system context after.
     *
     * Read with IGNORE_MISSING: context_user::instance() does not recreate the context of a deleted account (it only creates
     * a missing context for an account with deleted = 0), and it answers false, not null, when there is none - hence the
     * short ternary.
     *
     * @param int $userid
     * @return \context
     */
    public static function context_of_user(int $userid): \context {
        return \context_user::instance($userid, IGNORE_MISSING) ?: \context_system::instance();
    }
}
