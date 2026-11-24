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

use tool_vae\local\attestation_manager;
use tool_vae\local\competency_gateway;
use tool_vae\local\evidence_manager;
use tool_vae\local\exporter;
use tool_vae\local\identity_map;

/**
 * Criterion 8 - a deletion decided elsewhere leaves no orphaned narrative.
 *
 * TWO DOORS, NOT ONE. The candidate can delete their evidence record from tool_lp, and an
 * administrator can delete the account itself. The second does not pass through the first:
 * delete_user() removes the evidence records with a bulk delete and triggers no event about
 * them. Both doors are tested here, and a test that covered only the first would have passed
 * on a plugin that still lost a narrative every time an account was closed.
 *
 * WHAT THESE TESTS ASSERT. That the rows are GONE - not that the observer was called, not
 * which branch it took. An observer that runs and deletes nothing looks identical to one that
 * never ran, and only the state of the tables tells them apart.
 *
 * TWO CANDIDATES, ON PURPOSE. A purge written with one identifier too wide - by candidate
 * where it should be by evidence record, or with no condition at all - destroys everything and
 * passes every test that has only one subject to lose.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\local\purge::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\local\deletion_followup::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\local\plugin_schema::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\task\followup_task::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\task\check_changed_evidence::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\task\purge_deleted_account::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\task\purge_deleted_evidence::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\task\release_deleted_framework::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\task\reconcile_orphans::class)]
final class observer_test extends \advanced_testcase {
    /** @var \stdClass The candidate whose dossier is destroyed. */
    private $candidate;

    /** @var \stdClass A second candidate, who must lose nothing. */
    private $bystander;

    /** @var \stdClass The manager who attests. */
    private $attestant;

    /** @var \stdClass The first candidate's declaration. */
    private $claim;

    /** @var \stdClass A second declaration by the same candidate, on the same dossier. */
    private $otherclaim;

    /** @var \stdClass The second candidate's declaration. */
    private $bystanderclaim;

    /** @var int The competency everyone claims. */
    private $competencyid;

    /**
     * Two candidates, three declarations, one attestation.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        $this->resetAfterTest();

        // No preventResetByRollback() here, deliberately. The purge opens a transaction, but
        // nothing in this file asserts what survives a rollback - trap 4 is about tests of
        // atomicity - and the call costs a full database rebuild between tests.
        require_once($CFG->libdir . '/formslib.php');

        $generator = $this->getDataGenerator();
        $lpg = $generator->get_plugin_generator(competency_gateway::SUBSYSTEM);

        $framework = $lpg->create_framework(['shortname' => 'RNCP36832']);
        $competency = $lpg->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'idnumber' => 'RNCP36832-C03',
            'shortname' => 'Repérer les facteurs de risque de chute',
        ]);
        $this->competencyid = (int) $competency->get('id');
        $this->insert_source((int) $framework->get('id'));

        $this->candidate = $generator->create_user();
        $this->bystander = $generator->create_user();
        $this->attestant = $generator->create_user();

        $declarerole = $generator->create_role();
        assign_capability('tool/vae:declare', CAP_ALLOW, $declarerole, \context_system::instance()->id, true);
        role_assign($declarerole, $this->candidate->id, \context_user::instance($this->candidate->id)->id);
        role_assign($declarerole, $this->bystander->id, \context_user::instance($this->bystander->id)->id);

        $attestrole = $generator->create_role();
        assign_capability('tool/vae:attest', CAP_ALLOW, $attestrole, \context_system::instance()->id, true);
        // Attesting requires reading the dossier: the attestant follows the candidate.
        assign_capability('tool/vae:viewassigned', CAP_ALLOW, $attestrole, \context_system::instance()->id, true);
        role_assign($attestrole, $this->attestant->id, \context_user::instance($this->candidate->id)->id);

        accesslib_clear_all_caches_for_unit_testing();

        $this->claim = $this->declare_for($this->candidate);
        $this->otherclaim = $this->declare_for($this->candidate);
        $this->bystanderclaim = $this->declare_for($this->bystander);

        $this->setUser($this->attestant);
        (new attestation_manager())->attest((int) $this->claim->id, (object) [
            'attestantrole' => 'Cadre de santé, EHPAD Les Tilleuls',
            'statement' => 'J\'atteste avoir encadré cette personne sur la période déclarée.',
        ]);

        // A ULID that is NOT the candidate's, but carries the same number under another type.
        // Without it, a purge of the identity map written on itemid alone destroys this row
        // and no test notices - the mutation was run, and it survived the suite.
        identity_map::uuid_for('competency', (int) $this->candidate->id);
    }

    /**
     * Record the provenance of the imported framework.
     *
     * @param int $frameworkid
     */
    private function insert_source(int $frameworkid): void {
        global $DB;

        $DB->insert_record('tool_vae_source', (object) [
            'frameworkid' => $frameworkid,
            'fichecode' => 'RNCP36832',
            'fichetype' => 'rncp',
            'sourceurl' => 'https://www.data.gouv.fr/',
            'sourceupdated' => 1781136000,
            'licence' => 'LO-2.0',
            'attribution' => 'France compétences',
            'lastimport' => time(),
            'lastimportstatus' => 'ok',
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => 2,
        ]);
    }

    /**
     * One declaration, made in the first person as declarations always are.
     *
     * @param \stdClass $user
     * @return \stdClass
     */
    private function declare_for(\stdClass $user): \stdClass {
        $this->setUser($user);

        return (new evidence_manager())->declare_activity((int) $user->id, (object) [
            'competencyid' => $this->competencyid,
            'periodstart' => 1756684800,
            'periodend' => 1782172800,
            'settingtype' => 'ehpad',
            'settingdetail' => 'Unité protégée, équipe de nuit',
            'situation' => 'Accompagnement au lever et à la toilette, en binôme avec une aide-soignante.',
            'situationformat' => FORMAT_PLAIN,
        ]);
    }

    /**
     * Delete a core evidence record the way tool_lp does.
     *
     * Through the gateway, because MC-5 forbids naming the core anywhere else - the test file
     * included. It is the same function the AJAX web service calls - the one exposed under
     * userevidencemanageown, which tool_lp's evidence list uses - so the door tested here is
     * the door a candidate actually uses.
     *
     * The observer queues the purge; it is run right after, as cron would, unless the test asks for the state before.
     *
     * @param int $userevidenceid
     * @param bool $followup Whether to run the purge the observer queued.
     */
    private function delete_core_evidence(int $userevidenceid, bool $followup = true): void {
        (new competency_gateway())->delete_evidence($userevidenceid);
        if ($followup) {
            $this->run_followup(\tool_vae\task\purge_deleted_evidence::class);
        }
    }

    /**
     * Whether a claim of this plugin still exists.
     *
     * @param \stdClass $claim
     * @return bool
     */
    private function claim_exists(\stdClass $claim): bool {
        global $DB;

        return $DB->record_exists('tool_vae_evidence', ['id' => $claim->id]);
    }

    /**
     * Run an action with events redirected, then hand the core event it produced to the observer.
     *
     * WHY THIS DETOUR. phpunit_util::is_redirecting_events() short-circuits \core\event\base::
     * trigger(): the event goes into the sink and the dispatcher is never called, so NO OBSERVER
     * RUNS while a sink is open. A test that redirects events and then expects the observer's
     * effect proves nothing - it would pass word for word on a plugin that declares no observer
     * at all. A known trap of the test bench: redirecting events disables the observers.
     *
     * So the wiring and the trace are proved separately. The deletion tests above run with no
     * sink and prove that db/events.php reaches the observer; these tests replay the REAL core
     * event - taken from the sink, not built by hand - through the observer and check what it
     * writes to the log. Since ticket 3 of review MMR-520 the observer only queues a task: the
     * task it queued is run here, inside the sink, and the trace is the one the task leaves.
     *
     * @param callable $action What deletes.
     * @param string $coreclass The core event to replay.
     * @param string $method The observer method to replay it through.
     * @return \core\event\base[] Everything the sink saw, the follow-up's own events included.
     */
    private function replay_through_observer(callable $action, string $coreclass, string $method): array {
        $followups = [
            'user_evidence_deleted' => \tool_vae\task\purge_deleted_evidence::class,
            'user_deleted' => \tool_vae\task\purge_deleted_account::class,
        ];
        $sink = $this->redirectEvents();
        $action();

        foreach ($sink->get_events() as $event) {
            if ($event instanceof $coreclass) {
                call_user_func([observer::class, $method], $event);
            }
        }
        $this->run_followup($followups[$method]);

        $events = $sink->get_events();
        $sink->close();

        return $events;
    }

    // ----------------------------------------------------------------------
    // Door one - the core deletes one evidence record.

    /**
     * Criterion 8 - deleting the core record destroys the narrative that hung off it.
     */
    public function test_deleting_the_core_evidence_destroys_the_claim(): void {
        global $DB;

        $this->setUser($this->candidate);
        $this->delete_core_evidence((int) $this->claim->userevidenceid);

        $this->assertFalse($this->claim_exists($this->claim), 'The narrative outlived its evidence record.');
        $this->assertFalse(
            $DB->record_exists('tool_vae_attest', ['evidenceid' => $this->claim->id]),
            'The attestation outlived the declaration it stood on.'
        );
    }

    /**
     * The purge is bounded by the evidence record, not by the candidate.
     */
    public function test_deleting_one_evidence_record_leaves_the_rest_of_the_dossier(): void {
        $this->setUser($this->candidate);
        $this->delete_core_evidence((int) $this->claim->userevidenceid);

        $this->assertTrue($this->claim_exists($this->otherclaim), 'A second claim of the same candidate was destroyed.');
        $this->assertTrue($this->claim_exists($this->bystanderclaim), 'Another candidate lost their dossier.');
    }

    /**
     * The candidate deletes, in the core's screen, a declaration a manager had attested: the manager is told, and told no more
     * than the others are.
     *
     * The deletion is the door tool_lp opens to the candidate and nothing in it asks the signatory; the purge takes their
     * signature with the declaration. The message is queued for the signatory only, carries the candidate's name and the
     * reference of the competency, and not a word of what the candidate wrote.
     */
    public function test_deleting_the_core_evidence_queues_a_message_for_the_signatory(): void {
        $this->setUser($this->candidate);
        $this->delete_core_evidence((int) $this->claim->userevidenceid);

        $queued = $this->purged_messages();
        $this->assertCount(1, $queued, 'The signatory was not told that their signature was destroyed.');
        $data = reset($queued)->get_custom_data();
        $this->assertEquals($this->attestant->id, $data->recipientid);
        $this->assertEquals($this->candidate->id, $data->candidateid);
        $this->assertSame($this->claim->fichecode, $data->fichecode);
        $this->assertSame($this->claim->ficherang, $data->ficherang);
        foreach ((array) $data as $value) {
            $this->assertStringNotContainsString('Accompagnement', (string) $value, 'A task carries identifiers, not text.');
        }

        $sink = $this->redirectMessages();
        $this->assertTrue(notifier::deliver($data));
        $messages = $sink->get_messages_by_component_and_type('tool_vae', notifier::DECLARATION_PURGED);
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($this->attestant->id, $message->useridto);
        $this->assertEquals(\core_user::NOREPLY_USER, $message->useridfrom);
        $this->assertStringContainsString(fullname($this->candidate), $message->fullmessage);
        $this->assertStringContainsString($this->claim->fichecode . '-' . $this->claim->ficherang, $message->fullmessage);
        foreach ([$message->subject, $message->fullmessage, $message->fullmessagehtml, $message->smallmessage] as $text) {
            $this->assertStringNotContainsString('Accompagnement', $text, 'The narrative left the dossier in a message.');
            $this->assertStringNotContainsString('Unité protégée', $text, 'The setting detail left the dossier in a message.');
            $this->assertStringNotContainsString('Cadre de santé', $text, 'What the signatory wrote came back in a message.');
        }
    }

    /**
     * A declaration nobody signed has nobody to tell, and neither has one whose signature was already withdrawn.
     */
    public function test_a_declaration_with_no_signature_in_force_tells_nobody(): void {
        global $DB;

        $this->setUser($this->candidate);
        $this->delete_core_evidence((int) $this->otherclaim->userevidenceid);
        $this->assertSame([], $this->purged_messages(), 'A declaration with no signature queued a message.');

        $attestationid = (int) $DB->get_field('tool_vae_attest', 'id', ['evidenceid' => $this->claim->id], MUST_EXIST);
        $this->setAdminUser();
        (new attestation_manager())->withdraw($attestationid, 'erreur');
        $this->setUser($this->candidate);
        $this->delete_core_evidence((int) $this->claim->userevidenceid);
        $this->assertSame([], $this->purged_messages(), 'A signature that was no longer in force was announced as destroyed.');
    }

    /**
     * The purge that catches up with a deletion nobody observed says it too.
     *
     * The scheduled task reaches the purge through the same method as the observer: the signatory is told by whichever
     * finds the orphan first.
     */
    public function test_the_reconciliation_tells_the_signatory_too(): void {
        global $DB;

        $DB->delete_records('competency_userevidence', ['id' => $this->claim->userevidenceid]);

        \tool_vae\local\purge::orphans();

        $queued = $this->purged_messages();
        $this->assertCount(1, $queued);
        $this->assertEquals($this->attestant->id, reset($queued)->get_custom_data()->recipientid);
    }

    /**
     * The messages about a destroyed declaration waiting in the queue.
     *
     * @return \core\task\adhoc_task[]
     */
    private function purged_messages(): array {
        return array_values(array_filter(
            \core\task\manager::get_adhoc_tasks(\tool_vae\task\send_notification::class),
            fn($task) => $task->get_custom_data()->provider === notifier::DECLARATION_PURGED
        ));
    }

    /**
     * The messages of one kind waiting in the queue.
     *
     * @param string $provider What the task carries: 'attestationserased' is the message to the candidate about attestations
     *        an erasure withdrew, 'declarationsawaitagain' the one to the managers who follow them.
     * @return \core\task\adhoc_task[]
     */
    private function queued_messages(string $provider): array {
        return array_values(array_filter(
            \core\task\manager::get_adhoc_tasks(\tool_vae\task\send_notification::class),
            fn($task) => $task->get_custom_data()->provider === $provider
        ));
    }

    /**
     * The purge leaves a trace, and the trace carries identifiers and counts only.
     */
    public function test_the_purge_is_logged_with_identifiers_only(): void {
        $this->setUser($this->candidate);

        $events = $this->replay_through_observer(
            function () {
                $this->delete_core_evidence((int) $this->claim->userevidenceid);
            },
            '\\core\\event\\competency_user_evidence_deleted',
            'user_evidence_deleted'
        );

        $purges = array_values(array_filter($events, function ($event) {
            return $event instanceof \tool_vae\event\evidence_purged;
        }));

        $this->assertCount(1, $purges, 'The purge went unrecorded.');
        $this->assertEquals($this->claim->id, $purges[0]->objectid);
        $this->assertEquals($this->candidate->id, $purges[0]->relateduserid);
        $this->assertSame(1, $purges[0]->other['attestations'], 'The count is taken before the deletion or not at all.');
        foreach ($purges[0]->other as $value) {
            $this->assertIsInt($value);
        }
    }

    /**
     * A core record this plugin knows nothing about is deleted without incident.
     */
    public function test_deleting_an_unrelated_evidence_record_changes_nothing(): void {
        global $DB;

        $this->setUser($this->candidate);
        $lpg = $this->getDataGenerator()->get_plugin_generator(competency_gateway::SUBSYSTEM);
        $foreign = $lpg->create_user_evidence(['userid' => $this->candidate->id]);

        $before = $DB->count_records('tool_vae_evidence');
        $this->delete_core_evidence((int) $foreign->get('id'));

        $this->assertEquals($before, $DB->count_records('tool_vae_evidence'));
    }

    // ----------------------------------------------------------------------
    // Door two - the account itself is deleted.

    /**
     * Criterion 8, second door - deleting the account destroys the whole dossier.
     */
    public function test_deleting_the_account_destroys_the_dossier(): void {
        global $DB;

        $this->delete_account($this->candidate);

        $this->assertFalse($this->claim_exists($this->claim), 'A narrative outlived its owner.');
        $this->assertFalse($this->claim_exists($this->otherclaim), 'A second narrative outlived its owner.');
        $this->assertEquals(
            0,
            $DB->count_records('tool_vae_attest', ['evidenceid' => $this->claim->id]),
            'An attestation outlived the dossier it stood on.'
        );
        $this->assertFalse(
            $DB->record_exists('tool_vae_identity', ['itemtype' => 'user', 'itemid' => $this->candidate->id]),
            'The identity map still names the deleted account.'
        );
    }

    /**
     * Deleting one account leaves every other dossier standing.
     */
    public function test_deleting_an_account_leaves_the_other_candidate_alone(): void {
        $this->delete_account($this->candidate);

        $this->assertTrue($this->claim_exists($this->bystanderclaim), 'Another candidate lost their dossier.');
    }

    /**
     * Deleting the account takes the register's rows for their archives, and no one else's.
     *
     * Decision of 12 October 2026: the erasure removed them, the account's deletion left them
     * naming a ULID nothing leads to any more. Both rows are produced by the manager, so a purge
     * written on the producer instead of the candidate's ULID deletes nothing and fails here.
     */
    public function test_deleting_the_account_removes_their_rows_of_the_register(): void {
        global $DB;

        $gone = $this->register_archive_of($this->candidate);
        $kept = $this->register_archive_of($this->bystander);

        $this->delete_account($this->candidate);

        $this->assertFalse(
            $DB->record_exists(exporter::REGISTER, ['candidateuuid' => $gone]),
            'The register still vouches for an archive of the deleted account.'
        );
        $this->assertTrue(
            $DB->record_exists(exporter::REGISTER, ['candidateuuid' => $kept]),
            'Another candidate lost the register\'s row for their archive.'
        );
    }

    /**
     * Deleting the manager's account leaves the attestations they gave.
     *
     * Decision of 28 August 2026: for this observer, closing an account is not a request for
     * erasure. Emptying those fields would degrade the dossier of every candidate the manager
     * ever signed for. Erasure has its own door, the privacy provider - and the next test shows
     * that Moodle itself files a request at that door when the account is deleted.
     */
    public function test_deleting_the_attestant_account_leaves_the_signature(): void {
        global $DB;

        $this->delete_account($this->attestant);

        $this->assertTrue($this->claim_exists($this->claim), 'The candidate lost their declaration.');
        $attestation = $DB->get_record('tool_vae_attest', ['evidenceid' => $this->claim->id], '*', MUST_EXIST);
        $this->assertEquals($this->attestant->id, $attestation->attestantid);
        $this->assertNotEmpty($attestation->statement);
    }

    /**
     * What a Moodle left at its defaults does after the deletion of a signatory's account, from the deletion to the cron.
     *
     * The two defaults are pinned, so that the test does not depend on the installation: tool_dataprivacy files an erasure
     * request in the name of every deleted account, and leaves it awaiting the data protection officer's approval. Deleting the
     * account strips nothing; approving the request strips the four fields of every signature the account gave and marks it
     * withdrawn, and the declaration of the candidate stays. The text the dossier shows for that withdrawal must not say the
     * signatory asked for anything: nobody did, the administrator deleted the account and the officer approved a routine request.
     */
    public function test_the_erasure_request_moodle_files_for_a_deleted_signatory_strips_the_signature_once_approved(): void {
        global $DB;

        set_config('automaticdeletionrequests', 1, 'tool_dataprivacy');
        set_config('automaticdatadeletionapproval', 0, 'tool_dataprivacy');

        $this->delete_account($this->attestant);
        $this->assertSame([], $this->queued_messages('attestationserased'), 'Told at the deletion, while the signature stands.');

        $requests = \tool_dataprivacy\api::get_data_requests(
            (int) $this->attestant->id,
            [\tool_dataprivacy\api::DATAREQUEST_STATUS_AWAITING_APPROVAL],
            [\tool_dataprivacy\api::DATAREQUEST_TYPE_DELETE]
        );
        $this->assertCount(1, $requests, 'Moodle files an erasure request when an account is deleted.');
        $attestation = $DB->get_record('tool_vae_attest', ['evidenceid' => $this->claim->id], '*', MUST_EXIST);
        $this->assertEquals($this->attestant->id, $attestation->attestantid, 'Until the request is approved, the signature stays.');

        $this->setAdminUser();
        ob_start();
        \tool_dataprivacy\api::approve_data_request((int) reset($requests)->get('id'));
        $this->runAdhocTasks('\tool_dataprivacy\task\process_data_request_task');
        ob_end_clean();

        $attestation = $DB->get_record('tool_vae_attest', ['evidenceid' => $this->claim->id], '*', MUST_EXIST);
        $this->assertNull($attestation->attestantid);
        $this->assertNull($attestation->attestantrole);
        $this->assertNull($attestation->statement);
        $this->assertNull($attestation->attestedat);
        $this->assertNotNull($attestation->withdrawnat);
        $this->assertSame('rgpd', $attestation->withdrawnreason);
        $this->assertTrue($this->claim_exists($this->claim), 'The declaration of the candidate stays.');
        $this->assertTrue($this->claim_exists($this->bystanderclaim), 'Another candidate lost their dossier.');

        // The approval is what withdrew the signature, and the candidate is told of it, once, with the declaration it concerns
        // and nothing else; the bystander, whose dossier was not touched, is not.
        $erased = $this->queued_messages('attestationserased');
        $this->assertCount(1, $erased, 'The candidate was not told that the attestation of their declaration was withdrawn.');
        $data = $erased[0]->get_custom_data();
        $this->assertSame((int) $this->candidate->id, (int) $data->recipientid);
        $this->assertSame((int) $this->candidate->id, (int) $data->candidateid);
        $this->assertSame([(int) $this->claim->id], array_map('intval', (array) $data->evidenceids));
    }

    /**
     * The reason the dossier shows for an erased signature does not say the signatory asked for it.
     *
     * The reason 'rgpd' is written by the privacy provider, which runs for an erasure the person asked for AND for the request
     * Moodle files by itself when their account is deleted, approved by the officer. In the second case nobody on the dossier's
     * side asked for anything; the text says what happened to the signature and why, in both languages.
     */
    public function test_the_reason_shown_for_an_erased_signature_does_not_say_the_signatory_asked(): void {
        // The reason, and the sentence of the dossier that carries it (screen, PDF and copies): on 27 October 2026 the sentence
        // was completed to say the attestation is no longer valid, and it must not say who asked either.
        // Every language present here: the package of the plugin ships English only, the repository English and French.
        require_once(__DIR__ . '/fixtures/lang_files.php');
        foreach (\tool_vae\fixtures\lang_files::languages() as $lang) {
            // Read from the plugin's own file: the French pack is not installed in the test site, and the string manager
            // then answers in English whatever language is asked.
            require_once(__DIR__ . '/fixtures/lang_files.php');
            $string = \tool_vae\fixtures\lang_files::strings($lang);
            $strings = $string;
            foreach (['withdrawreason:rgpd', 'attestationwithdrawnon'] as $key) {
                $this->assertDoesNotMatchRegularExpression(
                    '/requested by the signatory|demandé par le signataire/u',
                    $strings[$key],
                    "lang/$lang says, in $key, that the signatory asked, and the request may be Moodle's own."
                );
            }
        }
    }

    /**
     * Deleting the account of a signatory takes down the request that was addressed to them.
     *
     * The signature stays - the previous test - and only the signatory answers a request: accepts it by withdrawing, or
     * refuses. With the signatory gone, a request left open would show every colleague, for good, a request awaiting someone
     * who no longer exists, and forbid a new one. The deletion of an unrelated account touches nothing.
     */
    public function test_deleting_the_signatory_account_clears_the_request_made_to_them(): void {
        global $DB;

        $colleague = $this->getDataGenerator()->create_user();
        $DB->execute(
            'UPDATE {tool_vae_attest} SET withdrawreqby = :by, withdrawreqat = :at WHERE evidenceid = :evidenceid',
            ['by' => $colleague->id, 'at' => time(), 'evidenceid' => $this->claim->id]
        );

        $this->delete_account($this->getDataGenerator()->create_user());
        $this->assertEquals(
            $colleague->id,
            $DB->get_field('tool_vae_attest', 'withdrawreqby', ['evidenceid' => $this->claim->id]),
            'The deletion of an unrelated account cleared a request.'
        );

        $this->delete_account($this->attestant);

        $attestation = $DB->get_record('tool_vae_attest', ['evidenceid' => $this->claim->id], '*', MUST_EXIST);
        $this->assertNull($attestation->withdrawreqby, 'The request still waits for a signatory who is gone.');
        $this->assertNull($attestation->withdrawreqat);
        $this->assertEquals($this->attestant->id, $attestation->attestantid, 'The signature is not the request\'s to take.');
        $this->assertNull($attestation->withdrawnat);
    }

    /**
     * Deleting the account of a manager who asked for a withdrawal takes their request with it.
     *
     * The attestation is the signatory's and stays. Left in place, the request would show
     * the signatory "a colleague asks you to withdraw" for ever, and - since one request is open
     * at a time - nobody else could renew it.
     */
    public function test_deleting_the_requester_account_clears_the_request_and_keeps_the_signature(): void {
        global $DB;

        $requester = $this->getDataGenerator()->create_user();
        $DB->execute(
            'UPDATE {tool_vae_attest} SET withdrawreqby = :by, withdrawreqat = :at WHERE evidenceid = :evidenceid',
            ['by' => $requester->id, 'at' => time(), 'evidenceid' => $this->claim->id]
        );

        $this->delete_account($requester);

        $attestation = $DB->get_record('tool_vae_attest', ['evidenceid' => $this->claim->id], '*', MUST_EXIST);
        $this->assertNull($attestation->withdrawreqby);
        $this->assertNull($attestation->withdrawreqat);
        $this->assertEquals($this->attestant->id, $attestation->attestantid);
        $this->assertNull($attestation->withdrawnat);
    }

    /**
     * The purge of a dossier leaves a trace carrying counts, and only integers.
     */
    public function test_the_dossier_purge_is_logged_with_counts_only(): void {
        $events = $this->replay_through_observer(
            function () {
                $this->delete_account($this->candidate);
            },
            '\\core\\event\\user_deleted',
            'user_deleted'
        );

        $purges = array_values(array_filter($events, function ($event) {
            return $event instanceof \tool_vae\event\dossier_purged;
        }));

        $this->assertCount(1, $purges, 'The purge went unrecorded.');
        $this->assertEquals($this->candidate->id, $purges[0]->relateduserid);
        $this->assertSame(2, $purges[0]->other['declarations']);
        $this->assertSame(1, $purges[0]->other['attestations']);
        foreach ($purges[0]->other as $value) {
            $this->assertIsInt($value);
        }
    }

    /**
     * An account holding no dossier is deleted without a purge being announced.
     */
    public function test_deleting_an_account_with_no_dossier_announces_nothing(): void {
        $stranger = $this->getDataGenerator()->create_user();

        $events = $this->replay_through_observer(
            function () use ($stranger) {
                $this->delete_account($stranger);
            },
            '\\core\\event\\user_deleted',
            'user_deleted'
        );

        $purges = array_filter($events, function ($event) {
            return $event instanceof \tool_vae\event\dossier_purged;
        });

        $this->assertCount(0, $purges, 'A purge was announced for a dossier that never existed.');
    }

    /**
     * The trace is filed against the candidate's own context, not the site.
     *
     * The context decides who sees the line in the log report. Logged at system level it would
     * be visible to anyone holding moodle/site:viewreports, which is not the same audience.
     */
    public function test_the_purge_is_logged_in_the_candidates_context(): void {
        $this->setUser($this->candidate);

        $events = $this->replay_through_observer(
            function () {
                $this->delete_core_evidence((int) $this->claim->userevidenceid);
            },
            '\\core\\event\\competency_user_evidence_deleted',
            'user_evidence_deleted'
        );

        $purges = array_values(array_filter($events, function ($event) {
            return $event instanceof \tool_vae\event\evidence_purged;
        }));

        $this->assertCount(1, $purges);
        $this->assertEquals(\context_user::instance($this->candidate->id)->id, $purges[0]->contextid);
    }

    /**
     * The dossier purge is filed in the candidate's context too, deleted account or not.
     */
    public function test_the_dossier_purge_is_logged_in_the_candidates_context(): void {
        $expected = \context_user::instance($this->candidate->id)->id;

        $events = $this->replay_through_observer(
            function () {
                $this->delete_account($this->candidate);
            },
            '\\core\\event\\user_deleted',
            'user_deleted'
        );

        $purges = array_values(array_filter($events, function ($event) {
            return $event instanceof \tool_vae\event\dossier_purged;
        }));

        $this->assertCount(1, $purges);
        $this->assertEquals($expected, $purges[0]->contextid);
    }

    /**
     * The identity map is purged by type AND number, never by number alone.
     *
     * A competency and a user can carry the same integer. A purge written on itemid alone
     * destroys a mapping that belongs to something else entirely, and the dossier tests above
     * would not notice: they only look at claims and signatures.
     */
    public function test_purging_a_dossier_leaves_identifiers_of_other_kinds(): void {
        global $DB;

        $this->delete_account($this->candidate);

        $this->assertTrue(
            $DB->record_exists('tool_vae_identity', [
                'itemtype' => 'competency',
                'itemid' => $this->candidate->id,
            ]),
            'The purge of a candidate destroyed an identifier that was not theirs.'
        );
    }

    /**
     * A row of the register (lot 8 quater): an archive of this candidate, produced by the manager.
     *
     * @param \stdClass $user The candidate.
     * @return string Their ULID, which the row names them by.
     */
    private function register_archive_of(\stdClass $user): string {
        global $DB;

        $personuuid = identity_map::uuid_for(identity_map::TYPE_USER, (int) $user->id);
        $DB->insert_record(exporter::REGISTER, (object) [
            'archiveuuid' => identity_map::generate_ulid(),
            'sha256' => hash('sha256', 'manifeste'),
            'candidateuuid' => $personuuid,
            'userid' => $this->attestant->id,
            'timecreated' => time(),
        ]);

        return $personuuid;
    }

    /**
     * T-78: a claim left behind by a purge that did not finish is removed by the reconciliation.
     *
     * The core record is deleted by a direct write: no event, so no observer, which is exactly the
     * state a failed or interrupted purge leaves. The other claims, and the other candidate's, stay.
     */
    public function test_the_reconciliation_removes_a_claim_whose_core_record_is_gone(): void {
        global $DB;

        $DB->delete_records('competency_userevidence', ['id' => $this->claim->userevidenceid]);
        $this->assertTrue($DB->record_exists('tool_vae_evidence', ['id' => $this->claim->id]), 'The orphan is there.');

        $removed = \tool_vae\local\purge::orphans();

        $this->assertCount(1, $removed);
        $this->assertEquals($this->claim->id, reset($removed)->id);
        $this->assertEquals(1, reset($removed)->attestations, 'Its attestation went with it.');
        $this->assertFalse($DB->record_exists('tool_vae_evidence', ['id' => $this->claim->id]));
        $this->assertSame(0, $DB->count_records('tool_vae_attest', ['evidenceid' => $this->claim->id]));
        $this->assertTrue($DB->record_exists('tool_vae_evidence', ['id' => $this->otherclaim->id]));
        $this->assertTrue($DB->record_exists('tool_vae_evidence', ['id' => $this->bystanderclaim->id]));
        $this->assertSame([], \tool_vae\local\purge::orphans(), 'A second run finds nothing.');
    }

    /**
     * T-78: the scheduled task removes the orphans and leaves the trace the observer leaves.
     */
    public function test_the_reconciliation_task_removes_orphans_and_leaves_an_event(): void {
        global $DB;

        $DB->delete_records('competency_userevidence', ['id' => $this->otherclaim->userevidenceid]);
        $sink = $this->redirectEvents();

        ob_start();
        (new \tool_vae\task\reconcile_orphans())->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('1 orphaned declaration(s)', $output);
        $this->assertFalse($DB->record_exists('tool_vae_evidence', ['id' => $this->otherclaim->id]));
        $purged = array_filter($sink->get_events(), fn($e) => $e instanceof \tool_vae\event\evidence_purged);
        $this->assertCount(1, $purged);
    }

    // ----------------------------------------------------------------------
    // Door three - a framework of competencies is deleted in tool_lp.

    /**
     * The provenance row of a deleted framework keeps its place, loses its framework, and says why.
     *
     * Deleted through the gateway, which is the core's own door: it refuses a framework somebody has declared on, and it
     * raises the event the observer reads - a direct write on the tables would test neither. The framework is a second
     * record with no declaration on it, since the one of setUp() cannot be deleted. The first record is the bystander: an
     * update with no condition on the framework would pass a test that has one record only.
     */
    public function test_a_deleted_framework_leaves_its_provenance_row_with_no_framework(): void {
        global $DB;

        $lpg = $this->getDataGenerator()->get_plugin_generator(competency_gateway::SUBSYSTEM);
        $framework = $lpg->create_framework(['shortname' => 'RS00001', 'idnumber' => 'RS00001']);
        $competency = $lpg->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'idnumber' => 'RS00001-C01',
            'shortname' => 'A competency of the second record',
        ]);
        $now = time();
        $secondid = (int) $DB->insert_record('tool_vae_source', (object) [
            'frameworkid' => (int) $framework->get('id'),
            'fichecode' => 'RS00001',
            'fichetype' => 'rs',
            'sourceurl' => 'https://www.data.gouv.fr/',
            'sourceupdated' => 1781136000,
            'licence' => 'LO-2.0',
            'attribution' => 'France compétences',
            'lastimport' => $now,
            'lastimportstatus' => 'ok',
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => 2,
        ]);
        $first = $DB->get_record('tool_vae_source', ['fichecode' => 'RNCP36832'], '*', MUST_EXIST);
        $marks = [$secondid => (int) $competency->get('id'), (int) $first->id => $this->competencyid];
        foreach ($marks as $sourceid => $competencyid) {
            $DB->insert_record('tool_vae_obsolete', (object) [
                'competencyid' => $competencyid,
                'sourceid' => $sourceid,
                'detectedat' => $now,
                'kind' => 'missing',
            ]);
        }

        $this->setAdminUser();
        $this->assertTrue((new competency_gateway())->delete_framework((int) $framework->get('id')));
        // The observer queued the release; until cron runs it, the row is as it was.
        $this->assertSame('ok', $DB->get_field('tool_vae_source', 'lastimportstatus', ['id' => $secondid]));
        $this->run_followup(\tool_vae\task\release_deleted_framework::class);

        $second = $DB->get_record('tool_vae_source', ['id' => $secondid], '*', MUST_EXIST);
        $this->assertSame('RS00001', $second->fichecode, 'The provenance of the record went with its framework.');
        $this->assertSame('France compétences', $second->attribution);
        $this->assertSame(1781136000, (int) $second->sourceupdated);
        $this->assertSame(0, (int) $second->frameworkid, 'The row still points at a framework that is gone.');
        $this->assertSame('deleted', $second->lastimportstatus);
        $this->assertSame((int) get_admin()->id, (int) $second->usermodified, 'The administrator who deleted it is not named.');
        $this->assertSame(
            0,
            $DB->count_records('tool_vae_obsolete', ['sourceid' => $secondid]),
            'A mark of departure outlived the competencies it names.'
        );
        $this->assertSame(['RS00001'], \tool_vae\local\source_frameworks::missing([$second]), 'The screen does not say it.');

        $untouched = $DB->get_record('tool_vae_source', ['id' => $first->id], '*', MUST_EXIST);
        $this->assertSame((int) $first->frameworkid, (int) $untouched->frameworkid, 'Another record lost its framework.');
        $this->assertSame('ok', $untouched->lastimportstatus);
        $this->assertSame(1, $DB->count_records('tool_vae_obsolete', ['sourceid' => $first->id]), 'Another record lost its marks.');
        $this->assertSame([], \tool_vae\local\source_frameworks::missing([$untouched]));
    }

    // ----------------------------------------------------------------------
    // Ticket 3 of review MMR-520 - the observers only queue; the work is done by ad hoc tasks, from cron.

    /**
     * How many rows each table of this plugin holds.
     *
     * @return int[] Table name => rows.
     */
    private function plugin_rows(): array {
        global $DB;

        $counts = [];
        foreach ($DB->get_tables(false) as $table) {
            if (str_starts_with($table, 'tool_vae_')) {
                $counts[$table] = $DB->count_records($table);
            }
        }
        ksort($counts);

        return $counts;
    }

    /**
     * The follow-up tasks of one class waiting in the queue.
     *
     * @param string $class
     * @return \core\task\adhoc_task[]
     */
    private function followups(string $class): array {
        return array_values(\core\task\manager::get_adhoc_tasks($class));
    }

    /**
     * Run what is queued, as cron would, and keep what the tasks print out of the test's output.
     *
     * Every queued task runs: the follow-up of the deletion, and the messages already waiting, caught by a sink so that
     * nothing is sent.
     *
     * AS A CRON PROCESS, NOT AS THE REQUEST THAT DELETED (lot 9 of review MMR-520). The account is deleted by a request - the
     * administrator's page - and the queue is run later by cron, another PHP process, which starts with empty caches of
     * rights: so the caches of accesslib are emptied first, once. Not between two tasks: \core\cron::run_inner_adhoc_task()
     * does not empty them either (read in public/lib/classes/cron.php of Moodle 5.3). Without this, on MySQL and MariaDB -
     * where PHPUnit opens no transaction, so the messages of setUp() wait in the queue - a manager's rights read before the
     * deletion answered after it, and the core's messaging, which reads the database, called debugging() on a message to a
     * manager who no longer held the right (lot 8, section 8).
     *
     * @return string What the tasks printed.
     */
    private function run_queued_tasks(): string {
        accesslib_clear_all_caches_for_unit_testing();
        $sink = $this->redirectMessages();
        ob_start();
        $this->run_all_adhoc_tasks();
        $output = ob_get_clean();
        $sink->close();

        return $output;
    }

    /**
     * Each of the four observers queues exactly one task, carrying identifiers only, and writes nothing of this plugin.
     *
     * The rows of every table of the plugin are counted before and after each core action: an observer that still did
     * part of the work - a request cleared, a provenance row rewritten - would change one count.
     */
    public function test_each_observer_queues_one_task_with_identifiers_and_writes_nothing(): void {
        $gateway = new competency_gateway();
        $lpg = $this->getDataGenerator()->get_plugin_generator(competency_gateway::SUBSYSTEM);
        $framework = $lpg->create_framework(['shortname' => 'RS00001', 'idnumber' => 'RS00001']);
        $frameworkid = (int) $framework->get('id');

        $actions = [
            \tool_vae\task\check_changed_evidence::class => [
                function () use ($gateway) {
                    $this->setUser($this->candidate);
                    $gateway->update_evidence((int) $this->claim->userevidenceid, ['name' => 'Renamed in tool_lp']);
                },
                ['userevidenceid' => (int) $this->claim->userevidenceid],
            ],
            \tool_vae\task\purge_deleted_evidence::class => [
                function () use ($gateway) {
                    $this->setUser($this->candidate);
                    $gateway->delete_evidence((int) $this->claim->userevidenceid);
                },
                ['userevidenceid' => (int) $this->claim->userevidenceid],
            ],
            \tool_vae\task\purge_deleted_account::class => [
                function () {
                    $this->delete_account($this->bystander, false);
                },
                ['userid' => (int) $this->bystander->id],
            ],
            \tool_vae\task\release_deleted_framework::class => [
                function () use ($gateway, $frameworkid) {
                    $this->setAdminUser();
                    $this->assertTrue($gateway->delete_framework($frameworkid));
                },
                ['frameworkid' => $frameworkid, 'userid' => (int) get_admin()->id],
            ],
        ];

        foreach ($actions as $class => [$action, $expected]) {
            $before = $this->plugin_rows();
            $action();
            $this->assertSame($before, $this->plugin_rows(), "The observer behind $class wrote to a table of the plugin.");

            $tasks = $this->followups($class);
            $this->assertCount(1, $tasks, "One $class expected in the queue.");
            $data = (array) $tasks[0]->get_custom_data();
            $this->assertSame($expected, array_map('intval', $data), "$class carries other data than its identifiers.");
            foreach ($data as $value) {
                $this->assertIsInt($value, "$class carries something that is not an identifier.");
            }
            $this->assertNull($tasks[0]->get_userid(), 'A task tied to an account cannot run once the account is deleted.');
        }
    }

    /**
     * The same event twice queues the task once: the queue does not fill with duplicates of one deletion.
     */
    public function test_the_same_event_twice_queues_one_task(): void {
        $sink = $this->redirectEvents();
        $this->delete_account($this->candidate, false);
        $this->setUser($this->bystander);
        $this->delete_core_evidence((int) $this->bystanderclaim->userevidenceid, false);
        $events = $sink->get_events();
        $sink->close();

        foreach ($events as $event) {
            if ($event instanceof \core\event\user_deleted) {
                observer::user_deleted($event);
                observer::user_deleted($event);
            }
            if ($event instanceof \core\event\competency_user_evidence_deleted) {
                observer::user_evidence_deleted($event);
                observer::user_evidence_deleted($event);
            }
        }

        $this->assertCount(1, $this->followups(\tool_vae\task\purge_deleted_account::class));
        $this->assertCount(1, $this->followups(\tool_vae\task\purge_deleted_evidence::class));
    }

    /**
     * Until cron runs the task, the dossier is still there; once it has, it is gone as it always went.
     */
    public function test_the_dossier_stays_until_the_task_runs_and_then_goes(): void {
        global $DB;

        $this->delete_account($this->candidate, false);

        $this->assertTrue($this->claim_exists($this->claim), 'The purge ran during the deletion: the observer is not light.');
        $this->assertTrue($DB->record_exists('tool_vae_identity', ['itemtype' => 'user', 'itemid' => $this->candidate->id]));

        $output = $this->run_queued_tasks();

        $this->assertStringContainsString('tool_vae: dossier of the deleted account ' . $this->candidate->id, $output);
        $this->assertFalse($this->claim_exists($this->claim), 'A narrative outlived its owner.');
        $this->assertFalse($this->claim_exists($this->otherclaim), 'A second narrative outlived its owner.');
        $this->assertSame(0, $DB->count_records('tool_vae_attest', ['evidenceid' => $this->claim->id]));
        $this->assertFalse($DB->record_exists('tool_vae_identity', ['itemtype' => 'user', 'itemid' => $this->candidate->id]));
        $this->assertTrue($this->claim_exists($this->bystanderclaim), 'Another candidate lost their dossier.');
        $this->assertSame([], $this->followups(\tool_vae\task\purge_deleted_account::class), 'The task did not complete.');
    }

    /**
     * A task run a second time - a duplicate, or a retry after the work was done - finds nothing and announces nothing.
     */
    public function test_a_second_run_of_each_task_finds_nothing(): void {
        global $DB;

        $this->delete_account($this->candidate, false);
        $this->run_queued_tasks();
        $after = $this->plugin_rows();

        $tasks = [
            new \tool_vae\task\purge_deleted_account(),
            new \tool_vae\task\purge_deleted_evidence(),
            new \tool_vae\task\check_changed_evidence(),
            new \tool_vae\task\release_deleted_framework(),
        ];
        $tasks[0]->set_custom_data(['userid' => (int) $this->candidate->id]);
        $tasks[1]->set_custom_data(['userevidenceid' => (int) $this->claim->userevidenceid]);
        $tasks[2]->set_custom_data(['userevidenceid' => (int) $this->claim->userevidenceid]);
        $tasks[3]->set_custom_data(['frameworkid' => 987654, 'userid' => (int) get_admin()->id]);

        $sink = $this->redirectEvents();
        ob_start();
        foreach ($tasks as $task) {
            $task->execute();
        }
        ob_end_clean();
        $events = array_filter($sink->get_events(), fn($e) => str_starts_with(get_class($e), 'tool_vae\\'));
        $sink->close();

        $this->assertSame([], array_values($events), 'A second run announced a purge that had already happened.');
        $this->assertSame($after, $this->plugin_rows(), 'A second run changed something.');
        $this->assertFalse($DB->record_exists('tool_vae_evidence', ['candidateid' => $this->candidate->id]));
    }

    /**
     * A task that finds the schema of the plugin incomplete stops without an error, says so, and touches nothing.
     *
     * The observers are notified during installation and upgrade, before the schema exists; the task, which may run in
     * that window on a site where cron runs alongside an upgrade, asks first. The guard is tested through the list of
     * tables each task needs, with one table that does not exist.
     */
    public function test_a_task_that_finds_the_schema_incomplete_stops_without_an_error(): void {
        $before = $this->plugin_rows();

        $task = new class extends \tool_vae\task\purge_deleted_account {
            /**
             * One table of the list does not exist.
             *
             * @return string[]
             */
            protected function required_tables(): array {
                return ['tool_vae_evidence', 'tool_vae_notinstalled'];
            }
        };
        $task->set_custom_data(['userid' => (int) $this->candidate->id]);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('tables of the plugin are not all installed', $output);
        $this->assertSame($before, $this->plugin_rows(), 'The task wrote with a schema it had found incomplete.');
        $this->assertTrue($this->claim_exists($this->claim));
    }

    /**
     * A purge that fails throws out of the task, which the task manager then keeps and retries.
     *
     * The failure is a real one, in the database: a trigger refuses any deletion from a table purge::dossier() always
     * deletes from. The task is run by the core's own cron runner, which catches the failure and records it on the task:
     * the row stays in the queue with a fail delay. The purge stood in its transaction: nothing of the dossier is lost
     * halfway. Once the cause is gone, the same task is run again and the dossier goes.
     */
    public function test_a_task_that_fails_throws_and_stays_in_the_queue_to_be_retried(): void {
        global $DB;

        if ($DB->get_dbfamily() !== 'postgres') {
            $this->markTestSkipped('The failure is provoked by a PostgreSQL trigger.');
        }
        $this->preventResetByRollback();

        $this->delete_account($this->candidate, false);
        $table = $DB->get_prefix() . 'tool_vae_scope';
        // Written through change_database_structure(): execute() refuses the semicolons of a function body.
        $DB->change_database_structure([
            "CREATE FUNCTION tool_vae_l4_refuse() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'refused by the test'; END; \$\$"
                . " LANGUAGE plpgsql",
            "CREATE TRIGGER tool_vae_l4_refuse BEFORE DELETE ON $table FOR EACH STATEMENT EXECUTE FUNCTION tool_vae_l4_refuse()",
        ]);
        $tasks = $this->followups(\tool_vae\task\purge_deleted_account::class);
        $this->assertCount(1, $tasks);
        $taskid = (int) $tasks[0]->get_id();
        try {
            // The core's runner of one task, as the cron and the task screen run it: it takes the locks and records the result.
            ob_start();
            \core\cron::run_adhoc_task($taskid);
            $output = ob_get_clean();

            $this->assertStringContainsString('Adhoc task failed: tool_vae\task\purge_deleted_account', $output);
            $this->assertStringContainsString('refused by the test', $output);
            $row = $DB->get_record('task_adhoc', ['id' => $taskid]);
            $this->assertNotFalse($row, 'A failed task left the queue: nothing would retry it.');
            $this->assertGreaterThan(0, (int) $row->faildelay, 'The failure is not recorded on the task.');
            $this->assertTrue($this->claim_exists($this->claim), 'A purge that failed was left half done.');
            $this->assertTrue($DB->record_exists('tool_vae_identity', ['itemtype' => 'user', 'itemid' => $this->candidate->id]));
        } finally {
            $DB->change_database_structure([
                "DROP TRIGGER IF EXISTS tool_vae_l4_refuse ON $table",
                'DROP FUNCTION IF EXISTS tool_vae_l4_refuse()',
            ]);
        }

        ob_start();
        \core\cron::run_adhoc_task($taskid);
        $output = ob_get_clean();

        $this->assertStringContainsString('Adhoc task complete: tool_vae\task\purge_deleted_account', $output);
        $this->assertFalse($DB->record_exists('task_adhoc', ['id' => $taskid]));
        $this->assertFalse($this->claim_exists($this->claim), 'The retry did not purge the dossier.');
    }

    /**
     * An account whose user context the core has already cleaned up: the purge is logged in the system context.
     *
     * delete_user() keeps the user context; the core's context clean-up task deletes it later. When cron reaches the purge
     * after that, the trace falls back to the system context, and never recreates the context of a deleted account. The
     * task runs by the core's cron runner, as the cron user: the trace names who ran it, and the account it is about.
     */
    public function test_a_deleted_account_whose_context_is_gone_is_logged_in_the_system_context(): void {
        global $DB;

        $this->delete_account($this->candidate, false);
        $contextid = (int) \context_user::instance((int) $this->candidate->id)->id;
        $this->assertTrue($DB->record_exists('context', ['id' => $contextid]), 'delete_user() keeps the user context.');

        \context_helper::cleanup_instances();
        $this->assertFalse($DB->record_exists('context', ['id' => $contextid]), 'The core did not clean up the context.');

        $tasks = $this->followups(\tool_vae\task\purge_deleted_account::class);
        $this->assertCount(1, $tasks);
        $sink = $this->redirectEvents();
        ob_start();
        \core\cron::run_adhoc_task((int) $tasks[0]->get_id());
        ob_end_clean();
        $purges = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof \tool_vae\event\dossier_purged));
        $sink->close();

        $this->assertCount(1, $purges);
        $this->assertSame((int) \context_system::instance()->id, (int) $purges[0]->contextid);
        $this->assertSame((int) $this->candidate->id, (int) $purges[0]->relateduserid);
        $this->assertSame((int) get_admin()->id, (int) $purges[0]->userid, 'The cron user is the one who ran it.');
        $this->assertSame(2, $purges[0]->other['declarations']);
        $this->assertFalse($DB->record_exists('context', ['id' => $contextid]), 'The context of a deleted account came back.');
        $this->assertFalse($this->claim_exists($this->claim));
    }

    // ----------------------------------------------------------------------
    // The scheduled catch-up: what the task of a deletion did, for an account whose task never ran.

    /**
     * Delete an account and lose the task that should have followed: the state the catch-up exists for.
     *
     * @param \stdClass $user
     */
    private function delete_account_without_its_task(\stdClass $user): void {
        global $DB;

        $this->delete_account($user, false);
        $DB->delete_records('task_adhoc', ['classname' => '\\' . \tool_vae\task\purge_deleted_account::class]);
        $this->assertSame([], $this->followups(\tool_vae\task\purge_deleted_account::class));
    }

    /**
     * Run the scheduled catch-up and return what it printed.
     *
     * @return string
     */
    private function reconcile(): string {
        ob_start();
        (new \tool_vae\task\reconcile_orphans())->execute();

        return ob_get_clean();
    }

    /**
     * The catch-up removes the pieces opened, the referents, the authorisations of an account whose task never ran -
     * deleted, or no longer in the user table at all - and leaves those of a living account.
     */
    public function test_the_catch_up_removes_the_traces_of_an_account_whose_task_never_ran(): void {
        global $DB;

        $living = $this->getDataGenerator()->create_user();
        $absent = 987654;
        $now = time();
        foreach ([$this->attestant->id, $absent, $living->id] as $userid) {
            $DB->insert_record('tool_vae_piece_open', (object) ['pieceid' => 1, 'userid' => $userid, 'timeopened' => $now]);
            $DB->insert_record('tool_vae_referent', (object) [
                'fichecode' => 'RNCP36832', 'ficherang' => 'C03', 'userid' => $userid, 'timecreated' => $now,
                'createdby' => $living->id,
            ]);
            $DB->insert_record('tool_vae_booklet_auth', (object) [
                'managerid' => $userid, 'grantedby' => $living->id, 'status' => 'granted', 'timecreated' => $now,
            ]);
        }
        $this->delete_account_without_its_task($this->attestant);

        $output = $this->reconcile();

        $columns = ['tool_vae_piece_open' => 'userid', 'tool_vae_referent' => 'userid', 'tool_vae_booklet_auth' => 'managerid'];
        foreach ($columns as $table => $column) {
            $this->assertSame(
                [(int) $living->id],
                array_map('intval', $DB->get_fieldset_select($table, $column, '1 = 1')),
                "$table still holds a line of an account that is gone, or lost one of a living account."
            );
        }
        $this->assertStringContainsString('traces of deleted accounts', $output);
        $this->assertSame('', $this->reconcile_changes_nothing(), 'A second run of the catch-up changed something.');
    }

    /**
     * The catch-up takes the name of an account that is gone off the lines it wrote, and leaves the lines.
     */
    public function test_the_catch_up_takes_the_name_of_an_account_whose_task_never_ran_off_what_it_wrote(): void {
        global $DB;

        $living = $this->getDataGenerator()->create_user();
        $now = time();
        $lines = [];
        foreach ([$this->attestant->id, $living->id] as $writer) {
            $lines[$writer] = [
                $DB->insert_record('tool_vae_referent', (object) [
                    'fichecode' => 'RNCP36832', 'ficherang' => 'C0' . count($lines), 'userid' => $living->id,
                    'timecreated' => $now, 'createdby' => $writer,
                ]),
                $DB->insert_record('tool_vae_booklet_auth', (object) [
                    'managerid' => $living->id, 'grantedby' => $writer, 'status' => 'granted', 'timecreated' => $now,
                ]),
            ];
        }
        $this->delete_account_without_its_task($this->attestant);

        $this->reconcile();

        [$referent, $authorisation] = $lines[$this->attestant->id];
        $this->assertSame(0, (int) $DB->get_field('tool_vae_referent', 'createdby', ['id' => $referent], MUST_EXIST));
        $this->assertNull($DB->get_field('tool_vae_booklet_auth', 'grantedby', ['id' => $authorisation], MUST_EXIST));
        [$referent, $authorisation] = $lines[$living->id];
        $this->assertSame((int) $living->id, (int) $DB->get_field('tool_vae_referent', 'createdby', ['id' => $referent]));
        $this->assertSame((int) $living->id, (int) $DB->get_field('tool_vae_booklet_auth', 'grantedby', ['id' => $authorisation]));
        $this->assertSame('', $this->reconcile_changes_nothing());
    }

    /**
     * The catch-up clears the requests to withdraw made by, or addressed to, an account whose task never ran, and keeps
     * the signature; the request of a living requester to a living signatory stays.
     */
    public function test_the_catch_up_clears_the_requests_of_an_account_whose_task_never_ran(): void {
        global $DB;

        $requester = $this->getDataGenerator()->create_user();
        $leaver = $this->getDataGenerator()->create_user();
        $signatory = $this->getDataGenerator()->create_user();
        $now = time();
        // A signature of a living signatory, written directly: who may attest is not what this test is about.
        $sign = function (int $evidenceid) use ($DB, $signatory, $now) {
            $DB->insert_record('tool_vae_attest', (object) [
                'uuid' => identity_map::generate_ulid(), 'evidenceid' => $evidenceid, 'attestantid' => $signatory->id,
                'attestantrole' => 'Cadre de santé', 'statement' => 'J\'atteste.', 'attestedat' => $now,
                'timecreated' => $now, 'timemodified' => $now, 'usermodified' => $signatory->id,
            ]);
        };
        $sign((int) $this->otherclaim->id);
        $sign((int) $this->bystanderclaim->id);
        $ask = function (int $evidenceid, int $by) use ($DB, $now) {
            $DB->execute(
                'UPDATE {tool_vae_attest} SET withdrawreqby = :by, withdrawreqat = :at WHERE evidenceid = :evidenceid',
                ['by' => $by, 'at' => $now, 'evidenceid' => $evidenceid]
            );
        };
        // Addressed to a signatory who will be gone; made by a requester who will be gone; between two living people.
        $ask((int) $this->claim->id, (int) $requester->id);
        $ask((int) $this->otherclaim->id, (int) $leaver->id);
        $ask((int) $this->bystanderclaim->id, (int) $requester->id);

        $this->delete_account_without_its_task($this->attestant);
        $this->delete_account_without_its_task($leaver);
        $this->reconcile();

        $signatories = [(int) $this->claim->id => (int) $this->attestant->id, (int) $this->otherclaim->id => (int) $signatory->id];
        foreach ($signatories as $evidenceid => $signatoryid) {
            $attestation = $DB->get_record('tool_vae_attest', ['evidenceid' => $evidenceid], '*', MUST_EXIST);
            $this->assertNull($attestation->withdrawreqby, 'A request nobody can answer, or nobody asks any more, stayed.');
            $this->assertNull($attestation->withdrawreqat);
            $this->assertSame($signatoryid, (int) $attestation->attestantid, 'The signature is not the request\'s to take.');
            $this->assertNull($attestation->withdrawnat);
        }
        $living = $DB->get_record('tool_vae_attest', ['evidenceid' => $this->bystanderclaim->id], '*', MUST_EXIST);
        $this->assertSame((int) $requester->id, (int) $living->withdrawreqby, 'The request of two living people was cleared.');
        $this->assertSame($now, (int) $living->withdrawreqat);
        $this->assertSame('', $this->reconcile_changes_nothing());
    }

    /**
     * What a second run of the catch-up changes in the tables of this plugin: nothing, said as an empty string.
     *
     * @return string The tables whose count changed, or an empty string.
     */
    private function reconcile_changes_nothing(): string {
        $before = $this->plugin_rows();
        $this->reconcile();
        $after = $this->plugin_rows();

        return implode(', ', array_keys(array_diff_assoc($after, $before)));
    }

    /**
     * The catch-up gives the provenance row of a framework the core no longer holds what the observer gave it.
     *
     * The framework is removed by a direct write: no event, so no task, the state an observer that never ran leaves.
     */
    public function test_the_catch_up_releases_the_provenance_row_of_a_framework_that_is_gone(): void {
        global $DB;

        $lpg = $this->getDataGenerator()->get_plugin_generator(competency_gateway::SUBSYSTEM);
        $framework = $lpg->create_framework(['shortname' => 'RS00001', 'idnumber' => 'RS00001']);
        $now = time();
        $secondid = (int) $DB->insert_record('tool_vae_source', (object) [
            'frameworkid' => (int) $framework->get('id'), 'fichecode' => 'RS00001', 'fichetype' => 'rs',
            'licence' => 'LO-2.0', 'attribution' => 'France compétences', 'lastimportstatus' => 'ok',
            'timecreated' => $now, 'timemodified' => $now, 'usermodified' => 2,
        ]);
        $first = $DB->get_record('tool_vae_source', ['fichecode' => 'RNCP36832'], '*', MUST_EXIST);
        foreach ([$secondid, (int) $first->id] as $sourceid) {
            $DB->insert_record('tool_vae_obsolete', (object) [
                'competencyid' => $this->competencyid, 'sourceid' => $sourceid, 'detectedat' => $now, 'kind' => 'missing',
            ]);
        }
        $DB->delete_records('competency_framework', ['id' => $framework->get('id')]);

        $output = $this->reconcile();

        $second = $DB->get_record('tool_vae_source', ['id' => $secondid], '*', MUST_EXIST);
        $this->assertSame(0, (int) $second->frameworkid);
        $this->assertSame('deleted', $second->lastimportstatus);
        $this->assertSame(0, (int) $second->usermodified, 'Nobody known deleted it: no account is named.');
        $this->assertSame(0, $DB->count_records('tool_vae_obsolete', ['sourceid' => $secondid]));
        $untouched = $DB->get_record('tool_vae_source', ['id' => $first->id], '*', MUST_EXIST);
        $this->assertSame((int) $first->frameworkid, (int) $untouched->frameworkid, 'Another record lost its framework.');
        $this->assertSame('ok', $untouched->lastimportstatus);
        $this->assertSame(1, $DB->count_records('tool_vae_obsolete', ['sourceid' => $first->id]));
        $this->assertStringContainsString('1 provenance row(s) of a deleted framework released', $output);
        $this->assertStringContainsString('0 provenance row(s) of a deleted framework released', $this->reconcile());
    }

    /**
     * Delete an account the way an administrator does.
     *
     * The observer queues the purge; it is run right after, as cron would, unless the test asks for the state before.
     *
     * @param \stdClass $user
     * @param bool $followup Whether to run the purge the observer queued.
     */
    private function delete_account(\stdClass $user, bool $followup = true): void {
        global $DB;

        $this->setAdminUser();
        delete_user($DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST));
        if ($followup) {
            $this->run_followup(\tool_vae\task\purge_deleted_account::class);
        }
    }

    /**
     * Run the follow-ups of one class waiting in the queue, as cron would, and only them.
     *
     * Since ticket 3 of review MMR-520 the observer only queues the follow-up of a deletion; the tests that assert its effect
     * run it here, at the point where the effect is expected. Only that class runs: the messages already queued stay in the
     * queue for the tests that count them.
     *
     * @param string $class A task class of this plugin.
     */
    private function run_followup(string $class): void {
        ob_start();
        $this->runAdhocTasks($class);
        ob_end_clean();
    }
}
