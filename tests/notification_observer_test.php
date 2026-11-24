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
use tool_vae\local\deposit_manager;
use tool_vae\local\draft_manager;
use tool_vae\local\evidence_manager;
use tool_vae\local\importer;
use tool_vae\local\opinion_manager;
use tool_vae\local\piece_manager;
use tool_vae\local\request_manager;

/**
 * Each callback of a notification event queues ONE ad hoc task and reads nothing in the database (lot 9, ticket 3 of review
 * MMR-520: "Heavy Logic in Event Observer Callback").
 *
 * THE EVENTS ARE REAL. Each one is raised by the code of the plugin that raises it in production - a declaration, an
 * attestation, a deposit, an import that loses a competency - while the events are caught by redirectEvents(), so that no
 * observer runs on its own. The callback is then the one db/events.php names for the event, called alone on that event.
 *
 * WHAT IS MEASURED. $DB->perf_get_reads() before and after the call of the callback alone: the number of queries the DML
 * layer of Moodle started as reads (SQL_QUERY_SELECT and the auxiliary queries, such as the columns of a table) in this
 * process during the call. Two caches a site keeps from one request to the next are warmed first - the information on the
 * plugin, which \core\task\manager::queue_adhoc_task() asks, and the columns of task_adhoc, which the insert asks - and the
 * test proves that queuing a task alone then reads nothing: whatever the call reads is the callback's own. The tasks are
 * counted in task_adhoc before and after: exactly one more.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\notification_observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\task\queue_notifications::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\task\send_notification::class)]
final class notification_observer_test extends \advanced_testcase {
    /** @var \stdClass The vaeiste whose dossier the events are about. */
    private $candidate;

    /** @var \stdClass Follows the vaeiste, may attest, read deposits, write an opinion and annotate. */
    private $manager;

    /** @var int The role of the manager, in the vaeiste's context. */
    private $managerrole;

    /** @var int The role of the vaeiste, on their own context. */
    private $candidaterole;

    /** @var int The competency of the record seeded in setUp(). */
    private $competencyid;

    /**
     * A record, a vaeiste, and a manager who follows them the way the README sets it up.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/formslib.php');

        $generator = $this->getDataGenerator();
        $generator->get_plugin_generator('tool_vae')->create_source(['fichecode' => 'RNCP36832', 'ficherang' => 'C03']);
        $this->competencyid = (int) $DB->get_field('competency', 'id', ['idnumber' => 'RNCP36832-C03'], MUST_EXIST);

        $system = \context_system::instance()->id;
        $this->candidaterole = $generator->create_role();
        foreach (['declare', 'viewown', 'draftown'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $this->candidaterole, $system, true);
        }
        $this->candidate = $generator->create_user(['firstname' => 'Camille', 'lastname' => 'Martin']);
        role_assign($this->candidaterole, $this->candidate->id, \context_user::instance($this->candidate->id)->id);

        $this->managerrole = $generator->create_role();
        foreach (['viewassigned', 'attest', 'readdeposit', 'writeopinion', 'annotate', 'evaluate'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $this->managerrole, $system, true);
        }
        set_role_contextlevels($this->managerrole, [CONTEXT_USER]);
        $this->manager = $generator->create_user(['firstname' => 'Nadia', 'lastname' => 'Benali']);
        role_assign($this->managerrole, $this->manager->id, \context_user::instance($this->candidate->id)->id);

        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * The fourteen events of db/events.php that tell someone something, by the name of their class.
     *
     * @return array[]
     */
    public static function events(): array {
        $names = [
            'evidence_declared', 'piece_added', 'deposit_created', 'evaluation_recorded', 'certification_recorded',
            'opinion_created', 'request_created', 'evidence_attested', 'attestation_withdrawn', 'withdrawal_requested',
            'withdrawal_refused', 'competency_left_record', 'note_recorded', 'consent_changed',
        ];

        return array_combine($names, array_map(fn(string $name): array => [$name], $names));
    }

    /**
     * The callback is called alone on a real event: one task more, and not one reading of the database.
     *
     * @param string $name The class of the event, in tool_vae\event.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('events')]
    public function test_a_callback_queues_one_task_and_reads_nothing(string $name): void {
        global $DB;

        $event = $this->raise($name);
        [$class, $method] = self::callback_of($name);

        // The two caches a site keeps between requests, then the proof that queuing alone reads nothing.
        \core_plugin_manager::instance()->get_plugin_info('tool_vae');
        $DB->get_columns('task_adhoc');
        $reads = $DB->perf_get_reads();
        $probe = \core\task\manager::queue_adhoc_task(new task\send_notification());
        $this->assertSame(0, $DB->perf_get_reads() - $reads, 'Queuing a task alone reads: the measure would not be the callback.');
        $DB->delete_records('task_adhoc', ['id' => $probe]);

        $tasks = $DB->count_records('task_adhoc');
        $reads = $DB->perf_get_reads();
        $class::$method($event);
        $read = $DB->perf_get_reads() - $reads;

        $this->assertSame(0, $read, "$class::$method() read the database $read time(s) in the request.");
        $this->assertSame($tasks + 1, $DB->count_records('task_adhoc'), "$class::$method() did not queue exactly one task.");
    }

    /**
     * The callback db/events.php names for an event of this plugin.
     *
     * @param string $name The class of the event, in tool_vae\event.
     * @return string[] The class and the method.
     */
    private static function callback_of(string $name): array {
        global $CFG;

        $observers = [];
        require($CFG->dirroot . '/admin/tool/vae/db/events.php');
        $found = array_values(array_filter($observers, fn(array $o): bool => $o['eventname'] === '\tool_vae\event\\' . $name));
        self::assertCount(1, $found, "db/events.php does not name one callback for $name.");

        return explode('::', ltrim($found[0]['callback'], '\\'));
    }

    /**
     * Raise one event the way the plugin raises it, observers held back, and return it.
     *
     * @param string $name The class of the event, in tool_vae\event.
     * @return \core\event\base
     */
    private function raise(string $name): \core\event\base {
        $sink = $this->redirectEvents();
        $this->{'act_' . $name}();
        $class = '\tool_vae\event\\' . $name;
        $events = array_values(array_filter($sink->get_events(), fn($event): bool => $event instanceof $class));
        $sink->close();
        $this->assertNotEmpty($events, "The plugin raised no $name.");

        return end($events);
    }

    /**
     * One declaration by the vaeiste.
     *
     * @return \stdClass
     */
    private function declare(): \stdClass {
        $this->setUser($this->candidate);

        return (new evidence_manager())->declare_activity((int) $this->candidate->id, (object) [
            'competencyid' => $this->competencyid,
            'periodstart' => 1756684800,
            'periodend' => 1782172800,
            'settingtype' => 'ehpad',
            'settingdetail' => 'Unité Les Glycines',
            'situation' => 'Toilette au lever.',
            'situationformat' => FORMAT_PLAIN,
        ]);
    }

    /**
     * Attest a declaration as the manager.
     *
     * @param \stdClass $claim
     * @return \stdClass The attestation.
     */
    private function attest(\stdClass $claim): \stdClass {
        $this->setUser($this->manager);

        return (new attestation_manager())->attest((int) $claim->id, (object) [
            'attestantrole' => 'Cadre de santé',
            'statement' => 'Je soussigné atteste avoir encadré cette personne.',
        ]);
    }

    /**
     * A colleague of the manager, who follows the vaeiste and may attest, asks the manager to withdraw their signature.
     *
     * @return \stdClass The attestation.
     */
    private function colleague_asks(): \stdClass {
        $colleague = $this->getDataGenerator()->create_user(['firstname' => 'Hugo', 'lastname' => 'Marchand']);
        role_assign($this->managerrole, $colleague->id, \context_user::instance($this->candidate->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $attestation = $this->attest($this->declare());
        $this->setUser($colleague);
        (new attestation_manager())->request_withdrawal((int) $attestation->id);

        return $attestation;
    }

    /**
     * Write one section of the dossier de faisabilité and deposit it, as the vaeiste.
     *
     * @return \stdClass The deposit.
     */
    private function deposit(): \stdClass {
        $this->setUser($this->candidate);
        $drafts = new draft_manager();
        $draft = $drafts->section((int) $this->candidate->id, draft_manager::DOCTYPE_FAISABILITE, 'objectifs');
        $drafts->save_version((int) $draft->id, 'Un objectif.');

        return (new deposit_manager())->deposit((int) $this->candidate->id);
    }

    /**
     * The record RNCP100 (a bloc BC01 holding C01), declared on by the vaeiste: what a positioning or an annotation stands on.
     */
    private function ground(): void {
        $plugin = $this->getDataGenerator()->get_plugin_generator('tool_vae');
        $plugin->create_source(['fichecode' => 'RNCP100', 'ficherang' => 'BC01', 'shortname' => 'Bloc 1']);
        $plugin->create_competency([
            'fichecode' => 'RNCP100', 'ficherang' => 'C01', 'shortname' => 'Competence 1', 'bloc' => 'BC01',
        ]);
        $plugin->create_declaration([
            'userid' => $this->candidate->id, 'fichecode' => 'RNCP100', 'ficherang' => 'C01', 'situation' => 'Une situation.',
        ]);
    }

    /**
     * An import of an invented record.
     *
     * @param array $rows rank => label
     */
    private function record(array $rows): void {
        $this->setAdminUser();
        $lines = [];
        foreach ($rows as $rank => $label) {
            $lines[] = (object) ['rang' => $rank, 'intitule' => $label, 'description' => ''];
        }
        (new importer())->import((object) [
            'fichecode' => 'RS00000', 'fichetype' => 'rs', 'name' => 'Certification inventée pour les tests',
            'sourceurl' => 'https://www.data.gouv.fr/', 'sourceupdated' => strtotime('2026-06-01'),
            'licence' => 'Licence Ouverte 2.0', 'attribution' => 'Personne - jeu d\'essai',
        ], $lines);
    }

    // What raises each event, as the plugin raises it.

    /**
     * A declaration.
     */
    protected function act_evidence_declared(): void {
        $this->declare();
    }

    /**
     * A supporting piece added to a declaration that carries a signature.
     */
    protected function act_piece_added(): void {
        set_config('piecesopen', 1, 'tool_vae');
        set_config('piecenoantivirus', 1, 'tool_vae');
        $claim = $this->declare();
        $this->attest($claim);
        $this->setUser($this->candidate);
        (new piece_manager())->add_link((int) $claim->id, 'diploma', '', 'https://exemple.test/a');
    }

    /**
     * A deposit of the dossier de faisabilité.
     */
    protected function act_deposit_created(): void {
        $this->deposit();
    }

    /**
     * An evaluation of a competency.
     */
    protected function act_evaluation_recorded(): void {
        $this->ground();
        $this->setUser($this->manager);
        (new local\evaluation_manager())->write((int) $this->candidate->id, 'RNCP100', 'C01', 'inproof', 'Constaté.');
    }

    /**
     * A certification of a bloc.
     */
    protected function act_certification_recorded(): void {
        $this->ground();
        $generator = $this->getDataGenerator();
        $system = \context_system::instance()->id;
        $direction = $generator->create_user(['firstname' => 'Dana', 'lastname' => 'Direction']);
        $role = $generator->create_role();
        foreach (['tool/vae:viewany', 'tool/vae:certify'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $role, $system, true);
        }
        role_assign($role, $direction->id, $system);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($direction);
        (new local\certification_manager())->write(
            (int) $this->candidate->id,
            'RNCP100',
            'BC01',
            'certified',
            'Motif de la direction.',
            userdate(time(), '%Y-%m-%d', 99, false),
            'Alerte.'
        );
    }

    /**
     * An opinion on a deposit.
     */
    protected function act_opinion_created(): void {
        $deposit = $this->deposit();
        $this->setUser($this->manager);
        (new opinion_manager())->write((int) $deposit->id, 'Un avis de lecture.');
    }

    /**
     * A request for precision on a declaration.
     */
    protected function act_request_created(): void {
        $claim = $this->declare();
        $this->setUser($this->manager);
        (new request_manager())->ask((int) $claim->id, 'Pouvez-vous préciser la période ?');
    }

    /**
     * An attestation.
     */
    protected function act_evidence_attested(): void {
        $this->attest($this->declare());
    }

    /**
     * A withdrawal of an attestation, by the administrator.
     */
    protected function act_attestation_withdrawn(): void {
        $attestation = $this->attest($this->declare());
        $this->setAdminUser();
        (new attestation_manager())->withdraw((int) $attestation->id, 'erreur');
    }

    /**
     * A request to withdraw, by a colleague.
     */
    protected function act_withdrawal_requested(): void {
        $this->colleague_asks();
    }

    /**
     * A refusal of a request to withdraw, by the signatory.
     */
    protected function act_withdrawal_refused(): void {
        $attestation = $this->colleague_asks();
        $this->setUser($this->manager);
        (new attestation_manager())->refuse_withdrawal((int) $attestation->id);
    }

    /**
     * A competency declared against leaves its record at the next import.
     */
    protected function act_competency_left_record(): void {
        global $DB;

        $this->record(['BC01' => 'Accompagner la personne', 'BC02' => 'Repérer les évolutions']);
        $this->competencyid = (int) $DB->get_field('competency', 'id', ['idnumber' => 'RS00000-BC01'], MUST_EXIST);
        $this->declare();
        $this->record(['BC02' => 'Repérer les évolutions']);
    }

    /**
     * An annotation by the manager the vaeiste consented to.
     */
    protected function act_note_recorded(): void {
        $situation = $this->situation();
        $this->getDataGenerator()->get_plugin_generator('tool_vae')->create_note([
            'authorid' => $this->manager->id, 'userid' => $this->candidate->id, 'fichecode' => 'RNCP100',
            'slot' => (int) $situation->slot, 'text' => 'Une annotation.',
        ]);
    }

    /**
     * A consent of the vaeiste to the manager.
     */
    protected function act_consent_changed(): void {
        $this->ground();
        $this->getDataGenerator()->get_plugin_generator('tool_vae')->create_consent([
            'userid' => $this->candidate->id, 'managerid' => $this->manager->id,
        ]);
    }

    /**
     * What an annotation stands on (lot 1b): the preparation open, a consent, and one work situation.
     *
     * @return \stdClass The situation.
     */
    private function situation(): \stdClass {
        local\validation_catalog::reset();
        set_config('validationopen', 1, 'tool_vae');
        $this->ground();
        $plugin = $this->getDataGenerator()->get_plugin_generator('tool_vae');
        $plugin->create_consent(['userid' => $this->candidate->id, 'managerid' => $this->manager->id]);

        return $plugin->create_situation([
            'userid' => $this->candidate->id, 'fichecode' => 'RNCP100', 'title' => 'Une situation', 'tags' => 'C01',
            'text' => 'Le texte de la situation.',
        ]);
    }
}
