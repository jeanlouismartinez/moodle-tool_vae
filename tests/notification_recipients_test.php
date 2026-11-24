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
use tool_vae\local\piece_manager;
use tool_vae\local\referent_manager;
use tool_vae\task\queue_notifications;
use tool_vae\task\send_notification;

/**
 * The messages queued for each event whose recipients are looked for, once the first run of cron has found them: the same
 * recipients, the same providers, the same data as before lot 9 (ticket 3 of review MMR-520).
 *
 * WRITTEN TO RUN ON BOTH TREES. Before lot 9 the notifier found the recipients in the request, except for a withdrawal and a
 * departure; after it, the callbacks only queue, and a task queue_notifications finds them from cron. This test runs the acts
 * with the observers live (preventResetByRollback(): on PostgreSQL an external observer waits for a commit), then runs the
 * tasks queue_notifications as cron would - there are some on both trees - and reads every send_notification left in the
 * queue, written with the names of the people and the labels of the rows instead of their ids. The lists below were the
 * same on the code before lot 9 and on the code after it: that is the proof.
 *
 * THE CAST. A record RS00000 with two blocs and a competency out of any bloc; the vaeiste Camille; three managers who follow
 * her and may attest - alice, the referent of the bloc BC01, bruno and chloe; denis, who follows her and may read her deposits
 * but not attest; emma, who may attest anywhere and follows nobody. alice may also read deposits.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\notifier::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\task\queue_notifications::class)]
final class notification_recipients_test extends \advanced_testcase {
    /** @var \stdClass[] The people, by name. */
    private $people = [];

    /** @var string[] Labels of ids met in the tasks, by kind of id then id. */
    private $labels = [];

    /**
     * The cast of the class comment.
     */
    protected function setUp(): void {
        parent::setUp();
        global $CFG;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        require_once($CFG->libdir . '/formslib.php');
        $this->setAdminUser();

        $this->import("rang,intitule,description,bloc\nBC01,Accompagner,,\nC01,Aider au lever,,BC01\nBC02,Travailler en équipe,,\n"
            . "C02,Transmettre,,BC02\nZ01,Hors bloc,,\n");

        $generator = $this->getDataGenerator();
        $system = \context_system::instance()->id;
        $role = function (array $capabilities, ?array $levels = [CONTEXT_USER]) use ($generator, $system): int {
            $roleid = $generator->create_role();
            foreach ($capabilities as $capability) {
                assign_capability('tool/vae:' . $capability, CAP_ALLOW, $roleid, $system, true);
            }
            if ($levels !== null) {
                set_role_contextlevels($roleid, $levels);
            }

            return $roleid;
        };

        foreach (['camille', 'camille2', 'alice', 'bruno', 'chloe', 'denis', 'emma'] as $name) {
            $this->people[$name] = $generator->create_user(['username' => $name, 'firstname' => ucfirst($name)]);
            $this->labels['user'][(int) $this->people[$name]->id] = $name;
        }
        $vaeiste = $role(['declare', 'viewown', 'draftown']);
        foreach (['camille', 'camille2'] as $name) {
            role_assign($vaeiste, $this->people[$name]->id, \context_user::instance($this->people[$name]->id)->id);
        }
        $camille = \context_user::instance($this->people['camille']->id)->id;
        $manager = $role(['viewassigned', 'attest']);
        foreach (['alice', 'bruno', 'chloe'] as $name) {
            role_assign($manager, $this->people[$name]->id, $camille);
        }
        $reader = $role(['viewassigned', 'readdeposit']);
        role_assign($reader, $this->people['denis']->id, $camille);
        role_assign($role(['readdeposit']), $this->people['alice']->id, $camille);
        role_assign($role(['attest'], null), $this->people['emma']->id, $system);
        accesslib_clear_all_caches_for_unit_testing();

        referent_manager::add('RS00000', 'BC01', (int) $this->people['alice']->id);
    }

    /**
     * Import the record RS00000 from the rows of a CSV, as the administrator.
     *
     * @param string $csv
     */
    private function import(string $csv): void {
        $this->setAdminUser();
        (new importer())->import((object) [
            'fichecode' => 'RS00000', 'fichetype' => 'rs', 'name' => 'Fiche RS00000',
            'sourceurl' => 'https://www.data.gouv.fr/', 'sourceupdated' => strtotime('2026-06-01'),
            'licence' => 'Licence Ouverte 2.0', 'attribution' => 'Personne',
        ], importer::rows_from_csv($csv));
    }

    /**
     * A declaration by a vaeiste on a competency of RS00000, labelled for the tasks.
     *
     * @param string $who
     * @param string $rank
     * @return \stdClass
     */
    private function declare(string $who, string $rank): \stdClass {
        global $DB;

        $this->setUser($this->people[$who]);
        $competency = (int) $DB->get_field('competency', 'id', ['idnumber' => 'RS00000-' . $rank], MUST_EXIST);
        $this->labels['competency'][$competency] = $rank;
        $claim = (new evidence_manager())->declare_activity((int) $this->people[$who]->id, (object) [
            'competencyid' => $competency, 'periodstart' => 1756684800, 'periodend' => 1782777600,
            'settingtype' => 'ehpad', 'situation' => 'Une situation.', 'situationformat' => FORMAT_PLAIN,
        ]);
        $this->labels['evidence'][(int) $claim->id] = "$who-$rank";

        return $claim;
    }

    /**
     * Attest a declaration.
     *
     * @param string $who
     * @param \stdClass $claim
     * @return \stdClass
     */
    private function attest(string $who, \stdClass $claim): \stdClass {
        $this->setUser($this->people[$who]);

        return (new attestation_manager())->attest((int) $claim->id, (object) [
            'attestantrole' => 'Cadre de santé', 'statement' => 'Je soussigné atteste.',
        ]);
    }

    /**
     * Empty the queue: what is read after is what the act under test queued.
     */
    private function forget_the_queue(): void {
        global $DB;

        $DB->delete_records('task_adhoc');
    }

    /**
     * Run the tasks that find recipients, as cron would, then the messages left in the queue, labelled and sorted.
     *
     * @return string[] One line per send_notification: its data, ids replaced by labels.
     */
    private function messages_after_cron(): array {
        ob_start();
        $this->runAdhocTasks(queue_notifications::class);
        $output = ob_get_clean();
        $this->assertSame('', $output, 'Finding the recipients printed something.');

        $lines = [];
        foreach (\core\task\manager::get_adhoc_tasks(send_notification::class) as $task) {
            $data = (array) $task->get_custom_data();
            $line = [];
            foreach ($data as $key => $value) {
                $line[] = "$key=" . $this->label($key, $value);
            }
            if ($task->get_next_run_time() > time() + HOURSECS) {
                $line[] = 'waits=' . ($task->get_next_run_time() - time() > DAYSECS - MINSECS ? 'a day' : 'less than a day');
            }
            sort($line);
            $lines[] = implode(' ', $line);
        }
        sort($lines);

        return $lines;
    }

    /**
     * An id of a task written as the label of what it designates.
     *
     * @param string $key
     * @param mixed $value
     * @return string
     */
    private function label(string $key, $value): string {
        global $DB;

        $kinds = ['recipientid' => 'user', 'candidateid' => 'user', 'requesterid' => 'user', 'evidenceid' => 'evidence',
            'competencyid' => 'competency'];
        if (isset($kinds[$key])) {
            return $this->labels[$kinds[$key]][(int) $value] ?? "unknown $kinds[$key] $value";
        }
        if ($key === 'pieceid') {
            return $DB->record_exists('tool_vae_piece', ['id' => (int) $value]) ? 'the piece' : "unknown piece $value";
        }
        if ($key === 'requestedat') {
            $known = $DB->record_exists('tool_vae_attest', ['withdrawreqat' => (int) $value]);

            return $known ? 'the moment of the request' : "$value";
        }
        if ($key === 'markid') {
            return $DB->record_exists('tool_vae_obsolete', ['id' => (int) $value]) ? 'the mark' : "unknown mark $value";
        }
        if ($key === 'sourceid') {
            return $DB->record_exists('tool_vae_source', ['id' => (int) $value, 'fichecode' => 'RS00000']) ? 'RS00000' : "$value";
        }

        return (string) $value;
    }

    /**
     * A declaration on a competency of a bloc that has a referent is told to the referent among those who may attest.
     */
    public function test_a_declaration_on_a_bloc_with_a_referent(): void {
        $this->forget_the_queue();
        $this->declare('camille', 'C01');

        $this->assertSame([
            'candidateid=camille evidenceid=camille-C01 provider=declarationpending recipientid=alice',
        ], $this->messages_after_cron());
    }

    /**
     * A declaration out of any bloc is told to every follower who may attest, and to nobody else.
     */
    public function test_a_declaration_out_of_any_bloc(): void {
        $this->forget_the_queue();
        $this->declare('camille', 'Z01');

        $this->assertSame([
            'candidateid=camille evidenceid=camille-Z01 provider=declarationpending recipientid=alice',
            'candidateid=camille evidenceid=camille-Z01 provider=declarationpending recipientid=bruno',
            'candidateid=camille evidenceid=camille-Z01 provider=declarationpending recipientid=chloe',
        ], $this->messages_after_cron());
    }

    /**
     * A deposit is told to the followers who may read deposits.
     */
    public function test_a_deposit(): void {
        $this->setUser($this->people['camille']);
        $drafts = new draft_manager();
        $draft = $drafts->section((int) $this->people['camille']->id, draft_manager::DOCTYPE_FAISABILITE, 'objectifs');
        $drafts->save_version((int) $draft->id, 'Un objectif.');
        $this->forget_the_queue();
        (new deposit_manager())->deposit((int) $this->people['camille']->id);

        $this->assertSame([
            'candidateid=camille doctype=faisabilite number=1 provider=depositmade recipientid=alice',
            'candidateid=camille doctype=faisabilite number=1 provider=depositmade recipientid=denis',
        ], $this->messages_after_cron());
    }

    /**
     * A piece added after a signature is told to the followers who may attest, routed to the bloc's referents; a piece added
     * to a declaration that carries no signature is told to nobody.
     */
    public function test_a_piece_added_with_and_without_a_signature(): void {
        set_config('piecesopen', 1, 'tool_vae');
        set_config('piecenoantivirus', 1, 'tool_vae');
        $signed = $this->declare('camille', 'C02');
        $this->attest('bruno', $signed);
        $unsigned = $this->declare('camille', 'C01');

        $this->forget_the_queue();
        $this->setUser($this->people['camille']);
        (new piece_manager())->add_link((int) $unsigned->id, 'diploma', '', 'https://exemple.test/a');
        $this->assertSame([], $this->messages_after_cron(), 'A piece on a declaration that carries no signature was told.');

        // Running a task sets up the user of cron, as cron does: the vaeiste acts again.
        $this->setUser($this->people['camille']);
        (new piece_manager())->add_link((int) $signed->id, 'diploma', '', 'https://exemple.test/b');
        $this->assertSame([
            'candidateid=camille evidenceid=camille-C02 pieceid=the piece provider=pieceafterattest recipientid=alice',
            'candidateid=camille evidenceid=camille-C02 pieceid=the piece provider=pieceafterattest recipientid=bruno',
            'candidateid=camille evidenceid=camille-C02 pieceid=the piece provider=pieceafterattest recipientid=chloe',
        ], $this->messages_after_cron());
    }

    /**
     * A request to withdraw is told to the signatory, tied to the requester and to the moment of the request.
     */
    public function test_a_request_to_withdraw(): void {
        $attestation = $this->attest('alice', $this->declare('camille', 'C02'));
        $this->forget_the_queue();
        $this->setUser($this->people['bruno']);
        (new attestation_manager())->request_withdrawal((int) $attestation->id);

        $this->assertSame([
            'candidateid=camille evidenceid=camille-C02 provider=withdrawalrequested recipientid=alice '
                . 'requestedat=the moment of the request requesterid=bruno',
        ], $this->messages_after_cron());
    }

    /**
     * A withdrawal is told to the vaeiste, and the declaration that awaits again to every follower who may attest.
     */
    public function test_a_withdrawal(): void {
        $attestation = $this->attest('alice', $this->declare('camille', 'C01'));
        $this->forget_the_queue();
        $this->setAdminUser();
        (new attestation_manager())->withdraw((int) $attestation->id, 'erreur');

        $this->assertSame([
            'candidateid=camille evidenceid=camille-C01 provider=attestationwithdrawn recipientid=camille',
            'candidateid=camille evidenceid=camille-C01 provider=declarationpending recipientid=alice',
            'candidateid=camille evidenceid=camille-C01 provider=declarationpending recipientid=bruno',
            'candidateid=camille evidenceid=camille-C01 provider=declarationpending recipientid=chloe',
        ], $this->messages_after_cron());
    }

    /**
     * A departure is told once to each vaeiste who declared against the competency, after a day.
     */
    public function test_a_departure(): void {
        $this->declare('camille', 'C02');
        $this->declare('camille', 'C02');
        $this->declare('camille2', 'C02');
        $this->forget_the_queue();
        $this->import("rang,intitule,description,bloc\nBC01,Accompagner,,\nC01,Aider au lever,,BC01\nBC02,Travailler en équipe,,\n"
            . "Z01,Hors bloc,,\n");

        $this->assertSame([
            'candidateid=camille competencyid=C02 markid=the mark provider=competencyleftrecord recipientid=camille '
                . 'sourceid=RS00000 waits=a day',
            'candidateid=camille2 competencyid=C02 markid=the mark provider=competencyleftrecord recipientid=camille2 '
                . 'sourceid=RS00000 waits=a day',
        ], $this->messages_after_cron());
    }
}
