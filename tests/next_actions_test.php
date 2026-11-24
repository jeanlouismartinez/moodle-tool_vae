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

use tool_vae\local\access;
use tool_vae\local\candidate_overview;
use tool_vae\local\draft_manager;
use tool_vae\local\identity_map;
use tool_vae\local\next_actions;
use tool_vae\local\selfpos_manager;

/**
 * What the vaeiste can do now, as the banner of the tab "Mon parcours" lists it (lot S2).
 *
 * WHAT IS ASSERTED. That each action is offered only when the reader holds its right AND its rule is true: declaring while
 * there is no declaration, answering an unanswered request (with their number), resuming a feasibility draft whose working
 * text is not yet a version, positioning oneself while no level stands; that a missing right removes the action although its
 * rule is true; that nothing is offered for somebody else than the reader; that reading the actions writes nothing.
 *
 * And what the manager can do now, as the banner of the tab "Vaeistes suivis" lists it: the declarations of the followed
 * vaeistes awaiting an attestation, when the reader may attest them; the answers to the requests the reader asked, on
 * declarations not yet attested; the latest feasibility deposits without any opinion, when the reader may write one. Each
 * line carries a number and a link to the object waiting, never a name; a line whose number is zero is not given; the
 * number of queries does not grow with the followed; reading writes nothing.
 *
 * The rows are inserted directly: the rules under test read the tables, they are not about how a row is made.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(next_actions::class)]
final class next_actions_test extends \advanced_testcase {
    /** @var string[] The rights of a vaeiste who may do every action. */
    private const ALL = ['tool/vae:declare', 'tool/vae:viewown', 'tool/vae:draftown', 'tool/vae:positionself'];

    /** @var string[] The rights of a manager who may do every action, held in the context of each vaeiste followed. */
    private const MANAGER = ['tool/vae:viewassigned', 'tool/vae:attest', 'tool/vae:readdeposit', 'tool/vae:writeopinion'];

    /** @var \stdClass The vaeiste. */
    private $vaeiste;

    /** @var \stdClass|null The manager, once as_manager() has made them. */
    private $manager = null;

    /** @var int The role of the manager in the context of each vaeiste they follow. */
    private $managerrole = 0;

    /** @var int The last anchor given to a declaration: the column is unique. */
    private $anchors = 0;

    /**
     * The vaeiste, with no right yet.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->vaeiste = $this->getDataGenerator()->create_user();
    }

    /**
     * Give the vaeiste these rights in their own user context, and act as them.
     *
     * @param string[] $capabilities
     */
    private function as_vaeiste(array $capabilities = self::ALL): void {
        $generator = $this->getDataGenerator();
        $role = $generator->create_role();
        $system = \context_system::instance()->id;
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $role, $system, true);
        }
        set_role_contextlevels($role, [CONTEXT_USER]);
        role_assign($role, $this->vaeiste->id, \context_user::instance($this->vaeiste->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->vaeiste);
    }

    /**
     * The keys of the actions offered to the vaeiste, in their order.
     *
     * @return string[]
     */
    private function keys(): array {
        return array_column(next_actions::for_vaeiste((int) $this->vaeiste->id), 'key');
    }

    /**
     * The action of this key, or null when it is not offered.
     *
     * @param string $key
     * @return \stdClass|null
     */
    private function action(string $key): ?\stdClass {
        foreach (next_actions::for_vaeiste((int) $this->vaeiste->id) as $action) {
            if ($action->key === $key) {
                return $action;
            }
        }

        return null;
    }

    /**
     * A declaration of the vaeiste.
     *
     * @return int Its id.
     */
    private function declaration(): int {
        global $DB;

        $now = time();

        return (int) $DB->insert_record('tool_vae_evidence', (object) [
            'uuid' => identity_map::generate_ulid(),
            'userevidenceid' => ++$this->anchors,
            'candidateid' => (int) $this->vaeiste->id,
            'periodstart' => $now - 1000,
            'periodend' => $now,
            'settingtype' => 'ehpad',
            'situationformat' => FORMAT_PLAIN,
            'situation' => 'Une situation.',
            'fichecode' => 'RNCP36832',
            'ficherang' => 'C03',
            'ficheupdated' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => (int) $this->vaeiste->id,
        ]);
    }

    /**
     * A request for precision on a declaration.
     *
     * @param int $evidenceid
     * @param string|null $answer Null while unanswered.
     */
    private function request(int $evidenceid, ?string $answer): void {
        global $DB;

        $now = time();
        $DB->insert_record('tool_vae_request', (object) [
            'uuid' => identity_map::generate_ulid(),
            'evidenceid' => $evidenceid,
            'authorid' => null,
            'question' => 'Une question.',
            'questionhash' => hash('sha256', 'Une question.'),
            'timecreated' => $now,
            'answer' => $answer,
            'answerhash' => $answer === null ? null : hash('sha256', $answer),
            'timeanswered' => $answer === null ? null : $now,
        ]);
    }

    /**
     * A section of a document of the vaeiste.
     *
     * @param string $doctype
     * @param string|null $working The text in progress; null when nothing is in progress.
     * @param string $section
     */
    private function draft(string $doctype, ?string $working, string $section = 'parcours'): void {
        global $DB;

        $now = time();
        $DB->insert_record('tool_vae_draft', (object) [
            'uuid' => identity_map::generate_ulid(),
            'candidateid' => (int) $this->vaeiste->id,
            'doctype' => $doctype,
            'section' => $section,
            'slot' => 0,
            'fichecode' => '',
            'working' => $working,
            'workingat' => $working === null ? null : $now,
            'currentversion' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => (int) $this->vaeiste->id,
        ]);
    }

    /**
     * A line of self-positioning of the vaeiste.
     *
     * @param string $fichecode
     * @param string $ficherang
     * @param int $level
     * @param int $timecreated
     */
    private function selfpos(string $fichecode, string $ficherang, int $level, int $timecreated): void {
        global $DB;

        $DB->insert_record(selfpos_manager::TABLE, (object) [
            'uuid' => identity_map::generate_ulid(),
            'candidateid' => (int) $this->vaeiste->id,
            'fichecode' => $fichecode,
            'ficherang' => $ficherang,
            'level' => $level,
            'levellabel' => 'Niveau ' . $level,
            'justification' => '',
            'justificationformat' => FORMAT_PLAIN,
            'linehash' => hash('sha256', $fichecode . $ficherang . $level . $timecreated),
            'importedat' => null,
            'timecreated' => $timecreated,
        ]);
    }

    public function test_a_vaeiste_without_declaration_is_offered_to_declare(): void {
        $this->as_vaeiste();

        $actions = next_actions::for_vaeiste((int) $this->vaeiste->id);

        $this->assertSame([next_actions::DECLARE, next_actions::SELFPOSITION], array_column($actions, 'key'));
        $this->assertSame(0, $actions[0]->count);
        $this->assertInstanceOf(\moodle_url::class, $actions[0]->url);
        $this->assertSame('/admin/tool/vae/declare.php', $actions[0]->url->out_as_local_url(false));
    }

    public function test_declare_is_not_offered_without_the_right_or_after_a_declaration(): void {
        $this->as_vaeiste(['tool/vae:viewown', 'tool/vae:draftown', 'tool/vae:positionself']);
        $this->assertNotContains(next_actions::DECLARE, $this->keys());

        $this->as_vaeiste();
        $this->assertContains(next_actions::DECLARE, $this->keys());
        $this->declaration();
        $this->assertNotContains(next_actions::DECLARE, $this->keys());
    }

    public function test_declare_is_not_offered_when_the_competencies_cannot_be_viewed(): void {
        global $CFG;

        $this->as_vaeiste();
        $this->assertContains(next_actions::DECLARE, $this->keys());

        unassign_capability('moodle/competency:competencyview', $CFG->defaultuserroleid, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertNotContains(next_actions::DECLARE, $this->keys());
    }

    public function test_an_unanswered_request_is_offered_with_its_count(): void {
        $this->as_vaeiste();
        $first = $this->declaration();
        $this->request($first, null);

        $action = $this->action(next_actions::ANSWER);
        $this->assertNotNull($action);
        $this->assertSame(1, $action->count);
        $this->assertSame(
            '/admin/tool/vae/precision.php?evidenceid=' . $first,
            $action->url->out_as_local_url(false)
        );

        $second = $this->declaration();
        $this->request($second, null);

        $action = $this->action(next_actions::ANSWER);
        $this->assertSame(2, $action->count);
        $this->assertSame(
            '/admin/tool/vae/dossier.php?userid=' . $this->vaeiste->id,
            $action->url->out_as_local_url(false)
        );
        $this->assertSame([next_actions::ANSWER, next_actions::SELFPOSITION], $this->keys());
    }

    public function test_a_request_with_an_answer_is_not_offered(): void {
        $this->as_vaeiste();
        $evidenceid = $this->declaration();
        $this->request($evidenceid, 'Ma réponse.');
        // An empty string only comes from a row written by hand: it is an answer all the same, never NULL.
        $this->request($this->declaration(), '');

        $this->assertNotContains(next_actions::ANSWER, $this->keys());
    }

    public function test_answer_is_not_offered_without_the_right_to_read_ones_dossier(): void {
        $this->as_vaeiste(['tool/vae:declare', 'tool/vae:draftown', 'tool/vae:positionself']);
        $this->request($this->declaration(), null);

        $this->assertNotContains(next_actions::ANSWER, $this->keys());
    }

    public function test_a_draft_in_progress_is_offered_to_resume(): void {
        $this->as_vaeiste();
        $this->draft(draft_manager::DOCTYPE_FAISABILITE, 'Un texte en cours.');

        $action = $this->action(next_actions::DRAFT);
        $this->assertNotNull($action);
        $this->assertSame(0, $action->count);
        $this->assertSame('/admin/tool/vae/faisabilite.php', $action->url->out_as_local_url(false));
        $this->assertSame([next_actions::DECLARE, next_actions::DRAFT, next_actions::SELFPOSITION], $this->keys());
    }

    public function test_a_draft_without_working_text_is_not_offered(): void {
        $this->as_vaeiste();
        $this->draft(draft_manager::DOCTYPE_FAISABILITE, null, 'parcours');
        $this->draft(draft_manager::DOCTYPE_FAISABILITE, '', 'motivation');
        // A text in progress in another document is not the feasibility dossier.
        $this->draft(draft_manager::DOCTYPE_VALIDATION, 'Un texte en cours.', 'parcours');

        $this->assertNotContains(next_actions::DRAFT, $this->keys());
    }

    public function test_draft_is_not_offered_without_the_right_to_write_it(): void {
        $this->as_vaeiste(['tool/vae:declare', 'tool/vae:viewown', 'tool/vae:positionself']);
        $this->draft(draft_manager::DOCTYPE_FAISABILITE, 'Un texte en cours.');

        $this->assertNotContains(next_actions::DRAFT, $this->keys());
    }

    public function test_selfposition_is_offered_until_a_level_stands(): void {
        $this->as_vaeiste();
        $this->declaration();

        $action = $this->action(next_actions::SELFPOSITION);
        $this->assertNotNull($action);
        $this->assertSame(0, $action->count);
        $this->assertSame(
            '/admin/tool/vae/selfposition.php?userid=' . $this->vaeiste->id,
            $action->url->out_as_local_url(false)
        );

        $this->selfpos('RNCP100', 'BC01', 2, 1000);
        $this->assertNotContains(next_actions::SELFPOSITION, $this->keys());
    }

    public function test_selfposition_is_offered_again_when_every_level_is_withdrawn(): void {
        $this->as_vaeiste();
        $this->declaration();
        $this->selfpos('RNCP100', 'BC01', 2, 1000);
        $this->selfpos('RNCP100', '', 3, 1000);
        $this->selfpos('RNCP100', 'BC01', selfpos_manager::WITHDRAWN, 2000);
        $this->assertNotContains(next_actions::SELFPOSITION, $this->keys());

        $this->selfpos('RNCP100', '', selfpos_manager::WITHDRAWN, 2000);
        $this->assertContains(next_actions::SELFPOSITION, $this->keys());

        // The same rank in another record is another node.
        $this->selfpos('RNCP400', 'BC01', 1, 3000);
        $this->assertNotContains(next_actions::SELFPOSITION, $this->keys());
    }

    public function test_the_latest_line_of_a_node_stands_whatever_the_other_nodes_do(): void {
        $this->as_vaeiste();
        $this->declaration();
        $this->selfpos('RNCP100', 'BC01', 2, 1000);

        // A later withdrawal on the same rank of ANOTHER record does not withdraw the level of this one.
        $this->selfpos('RNCP400', 'BC01', selfpos_manager::WITHDRAWN, 3000);
        $this->assertNotContains(next_actions::SELFPOSITION, $this->keys());

        // Withdrawn, then positioned again on the same node: the later line stands.
        $this->selfpos('RNCP100', 'BC01', selfpos_manager::WITHDRAWN, 4000);
        $this->assertContains(next_actions::SELFPOSITION, $this->keys());
        $this->selfpos('RNCP100', 'BC01', 1, 5000);
        $this->assertNotContains(next_actions::SELFPOSITION, $this->keys());
    }

    public function test_selfposition_is_not_offered_without_the_right_to_position_oneself(): void {
        $this->as_vaeiste(['tool/vae:declare', 'tool/vae:viewown', 'tool/vae:draftown']);

        $this->assertNotContains(next_actions::SELFPOSITION, $this->keys());
    }

    public function test_nothing_is_offered_for_somebody_else_than_the_reader(): void {
        $this->as_vaeiste();
        $this->request($this->declaration(), null);
        $this->draft(draft_manager::DOCTYPE_FAISABILITE, 'Un texte en cours.');
        $this->assertNotSame([], $this->keys());

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame([], next_actions::for_vaeiste((int) $this->vaeiste->id));

        $this->setAdminUser();
        $this->assertSame([], next_actions::for_vaeiste((int) $this->vaeiste->id));
    }

    public function test_nothing_is_written(): void {
        global $DB;

        $this->as_vaeiste();
        $this->request($this->declaration(), null);
        $this->draft(draft_manager::DOCTYPE_FAISABILITE, 'Un texte en cours.');
        $this->selfpos('RNCP100', 'BC01', selfpos_manager::WITHDRAWN, 1000);

        $before = $DB->perf_get_writes();
        next_actions::for_vaeiste((int) $this->vaeiste->id);
        $this->assertSame($before, $DB->perf_get_writes());
    }

    /**
     * Make a manager who will hold these rights in the context of each vaeiste they follow, and act as them.
     *
     * @param string[] $capabilities
     */
    private function as_manager(array $capabilities = self::MANAGER): void {
        $generator = $this->getDataGenerator();
        $this->manager = $generator->create_user();
        $this->managerrole = $generator->create_role();
        $system = \context_system::instance()->id;
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $this->managerrole, $system, true);
        }
        set_role_contextlevels($this->managerrole, [CONTEXT_USER]);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->manager);
    }

    /**
     * A new vaeiste the manager follows: the role of the manager is assigned in the vaeiste's own context.
     *
     * @param array $fields Fields of the account, a name for instance.
     * @return int Their id.
     */
    private function followed(array $fields = []): int {
        $user = $this->getDataGenerator()->create_user($fields);
        role_assign($this->managerrole, $this->manager->id, \context_user::instance($user->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        return (int) $user->id;
    }

    /**
     * A declaration of a vaeiste.
     *
     * @param int $candidateid
     * @return int Its id.
     */
    private function declaration_of(int $candidateid): int {
        global $DB;

        $now = time();

        return (int) $DB->insert_record('tool_vae_evidence', (object) [
            'uuid' => identity_map::generate_ulid(),
            'userevidenceid' => ++$this->anchors,
            'candidateid' => $candidateid,
            'periodstart' => $now - 1000,
            'periodend' => $now,
            'settingtype' => 'ehpad',
            'situationformat' => FORMAT_PLAIN,
            'situation' => 'Une situation.',
            'fichecode' => 'RNCP36832',
            'ficherang' => 'C03',
            'ficheupdated' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => $candidateid,
        ]);
    }

    /**
     * A request for precision somebody asked on a declaration.
     *
     * @param int $evidenceid
     * @param int $authorid Who asked.
     * @param string|null $answer Null while unanswered.
     * @param int $timecreated When it was asked.
     */
    private function asked(int $evidenceid, int $authorid, ?string $answer, int $timecreated = 1000): void {
        global $DB;

        $DB->insert_record('tool_vae_request', (object) [
            'uuid' => identity_map::generate_ulid(),
            'evidenceid' => $evidenceid,
            'authorid' => $authorid,
            'question' => 'Une question.',
            'questionhash' => hash('sha256', 'Une question.'),
            'timecreated' => $timecreated,
            'answer' => $answer,
            'answerhash' => $answer === null ? null : hash('sha256', $answer),
            'timeanswered' => $answer === null ? null : $timecreated + 10,
        ]);
    }

    /**
     * An attestation of a declaration.
     *
     * @param int $evidenceid
     * @param int|null $withdrawnat When it was withdrawn; null while it stands.
     */
    private function attestation(int $evidenceid, ?int $withdrawnat = null): void {
        global $DB;

        $now = time();
        $DB->insert_record('tool_vae_attest', (object) [
            'uuid' => identity_map::generate_ulid(),
            'evidenceid' => $evidenceid,
            'attestantid' => (int) $this->manager->id,
            'attestantrole' => 'Cadre de santé',
            'statement' => 'J\'atteste.',
            'attestedat' => $now,
            'withdrawnat' => $withdrawnat,
            'withdrawnreason' => $withdrawnat === null ? null : 'erreur',
            'timecreated' => $now,
            'timemodified' => $now,
            'usermodified' => (int) $this->manager->id,
        ]);
    }

    /**
     * A deposit of a vaeiste.
     *
     * @param int $candidateid
     * @param int $number 1, 2, 3 per vaeiste and document.
     * @param int $timecreated
     * @param string $doctype
     * @return int Its id.
     */
    private function deposit_of(
        int $candidateid,
        int $number,
        int $timecreated = 1000,
        string $doctype = draft_manager::DOCTYPE_FAISABILITE
    ): int {
        global $DB;

        return (int) $DB->insert_record('tool_vae_deposit', (object) [
            'uuid' => identity_map::generate_ulid(),
            'candidateid' => $candidateid,
            'doctype' => $doctype,
            'number' => $number,
            'content' => '{}',
            'contenthash' => hash('sha256', '{}'),
            'note' => null,
            'timecreated' => $timecreated,
        ]);
    }

    /**
     * An opinion on a deposit.
     *
     * @param int $depositid
     * @param int $authorid Who wrote it.
     */
    private function opinion_on(int $depositid, int $authorid): void {
        global $DB;

        $DB->insert_record('tool_vae_opinion', (object) [
            'uuid' => identity_map::generate_ulid(),
            'depositid' => $depositid,
            'authorid' => $authorid,
            'content' => 'Un avis.',
            'contenthash' => hash('sha256', 'Un avis.'),
            'timecreated' => time(),
        ]);
    }

    /**
     * The line of this key among the lines of the manager, or null when it is not given.
     *
     * @param \stdClass[] $lines
     * @param string $key
     * @return \stdClass|null
     */
    private function line(array $lines, string $key): ?\stdClass {
        foreach ($lines as $line) {
            if ($line->key === $key) {
                return $line;
            }
        }

        return null;
    }

    public function test_a_manager_sees_the_declarations_awaiting_an_attestation_of_the_followed_only(): void {
        $this->as_manager();
        $first = $this->followed();
        $second = $this->followed();
        $stranger = (int) $this->getDataGenerator()->create_user()->id;

        $this->declaration_of($first);
        $this->attestation($this->declaration_of($first));
        // A withdrawn attestation leaves the declaration awaiting again.
        $this->attestation($this->declaration_of($first), time());
        $this->declaration_of($second);
        // A vaeiste the reader does not follow, slipped into the list, is not counted.
        $this->declaration_of($stranger);

        $lines = next_actions::for_manager([$first, $second, $stranger]);

        $this->assertSame([next_actions::ATTEST], array_column($lines, 'key'));
        $this->assertSame(3, $lines[0]->count);
    }

    public function test_attest_is_absent_without_the_right_to_attest(): void {
        $this->as_manager(['tool/vae:viewassigned', 'tool/vae:readdeposit', 'tool/vae:writeopinion']);
        $followed = $this->followed();
        $this->declaration_of($followed);

        // The rule is true: the declaration awaits an attestation; the right is missing.
        $this->assertSame(1, candidate_overview::counts([$followed])[$followed]->pending);
        $this->assertSame([], next_actions::for_manager([$followed]));
    }

    public function test_answered_requests_count_only_the_ones_the_reader_asked(): void {
        $this->as_manager();
        $followed = $this->followed();
        $other = (int) $this->getDataGenerator()->create_user()->id;

        $this->asked($this->declaration_of($followed), (int) $this->manager->id, 'Ma réponse.');
        // An empty string only comes from a row written by hand: it is an answer all the same, never NULL.
        $this->asked($this->declaration_of($followed), (int) $this->manager->id, '');
        $this->asked($this->declaration_of($followed), (int) $this->manager->id, null);
        $this->asked($this->declaration_of($followed), $other, 'Une réponse à une autre personne.');

        $line = $this->line(next_actions::for_manager([$followed]), next_actions::ANSWERS);
        $this->assertNotNull($line);
        $this->assertSame(2, $line->count);
    }

    public function test_an_answered_request_on_an_attested_declaration_is_not_counted(): void {
        $this->as_manager();
        $followed = $this->followed();

        $attested = $this->declaration_of($followed);
        $this->asked($attested, (int) $this->manager->id, 'Ma réponse.');
        $this->attestation($attested);
        $this->assertNull($this->line(next_actions::for_manager([$followed]), next_actions::ANSWERS));

        // Once its attestation is withdrawn, the declaration is not attested any more: the answer is to read again.
        $withdrawn = $this->declaration_of($followed);
        $this->asked($withdrawn, (int) $this->manager->id, 'Ma réponse.');
        $this->attestation($withdrawn, time());

        $line = $this->line(next_actions::for_manager([$followed]), next_actions::ANSWERS);
        $this->assertNotNull($line);
        $this->assertSame(1, $line->count);
    }

    public function test_answers_is_absent_for_a_vaeiste_no_longer_followed(): void {
        $this->as_manager();
        $left = $this->followed();
        $kept = $this->followed();
        $this->asked($this->declaration_of($left), (int) $this->manager->id, 'Ma réponse.');

        $this->assertSame([$left, $kept], access::followed_candidates());
        $this->assertNotNull($this->line(next_actions::for_manager([$left, $kept]), next_actions::ANSWERS));

        role_unassign($this->managerrole, (int) $this->manager->id, \context_user::instance($left)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertSame([$kept], access::followed_candidates());
        // The list is handed over as it is: the class itself refuses the vaeiste the reader may not read any more.
        $this->assertSame([], next_actions::for_manager([$left]));
        $this->assertSame([], next_actions::for_manager([$left, $kept]));
    }

    public function test_a_vaeiste_slipped_into_the_list_gives_nothing_on_any_line(): void {
        $this->as_manager();
        $followed = $this->followed();
        $stranger = (int) $this->getDataGenerator()->create_user()->id;

        $this->asked($this->declaration_of($stranger), (int) $this->manager->id, 'Ma réponse.');
        $this->deposit_of($stranger, 1);
        $this->declaration_of($stranger);

        $this->assertSame([], next_actions::for_manager([$stranger]));
        $this->assertSame([], next_actions::for_manager([$followed, $stranger]));
    }

    public function test_the_own_dossier_of_the_reader_is_never_counted(): void {
        // A manager who also holds the rights over their own dossier: attest and viewown in their own context.
        $this->as_manager(array_merge(self::MANAGER, ['tool/vae:viewown']));
        $followed = $this->followed();
        $self = (int) $this->manager->id;
        role_assign($this->managerrole, $self, \context_user::instance($self)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->declaration_of($followed);
        $own = $this->declaration_of($self);
        $this->asked($own, $self, 'Ma réponse.');
        $this->deposit_of($self, 1);

        // The rights say yes to the own dossier: only the exclusion of the reader keeps it out of the numbers.
        $this->assertTrue(access::can_attest($self));

        $lines = next_actions::for_manager([$self, $followed]);

        $this->assertSame([next_actions::ATTEST], array_column($lines, 'key'));
        $this->assertSame(1, $lines[0]->count);
        $this->assertSame([], next_actions::for_manager([$self]));
    }

    public function test_deposits_without_any_opinion_are_counted(): void {
        $this->as_manager();
        $first = $this->followed();
        $second = $this->followed();
        $this->deposit_of($first, 1);
        $this->deposit_of($second, 1);

        $lines = next_actions::for_manager([$first, $second]);

        $this->assertSame([next_actions::OPINIONS], array_column($lines, 'key'));
        $this->assertSame(2, $lines[0]->count);
    }

    public function test_a_deposit_with_an_opinion_of_another_manager_is_not_counted(): void {
        $this->as_manager();
        $followed = $this->followed();
        $depositid = $this->deposit_of($followed, 1);
        $this->assertNotNull($this->line(next_actions::for_manager([$followed]), next_actions::OPINIONS));

        $this->opinion_on($depositid, (int) $this->getDataGenerator()->create_user()->id);

        $this->assertSame([], next_actions::for_manager([$followed]));
    }

    public function test_an_older_deposit_without_opinion_is_not_counted(): void {
        $this->as_manager();
        $followed = $this->followed();
        $this->deposit_of($followed, 1, 1000);
        $latest = $this->deposit_of($followed, 2, 2000);
        $this->opinion_on($latest, (int) $this->manager->id);

        $this->assertSame([], next_actions::for_manager([$followed]));
    }

    public function test_an_older_deposit_with_an_opinion_does_not_hide_a_latest_one_without(): void {
        $this->as_manager();
        $followed = $this->followed();
        $older = $this->deposit_of($followed, 1, 1000);
        $this->opinion_on($older, (int) $this->manager->id);
        $this->deposit_of($followed, 2, 2000);

        $line = $this->line(next_actions::for_manager([$followed]), next_actions::OPINIONS);
        $this->assertNotNull($line);
        $this->assertSame(1, $line->count);
    }

    public function test_a_validation_deposit_is_never_counted(): void {
        $this->as_manager();
        $followed = $this->followed();
        $this->deposit_of($followed, 1, 1000, draft_manager::DOCTYPE_VALIDATION);
        $this->assertSame([], next_actions::for_manager([$followed]));

        // A validation deposit is not counted after a feasibility one with an opinion either.
        $this->opinion_on($this->deposit_of($followed, 1, 2000), (int) $this->manager->id);
        $this->deposit_of($followed, 2, 3000, draft_manager::DOCTYPE_VALIDATION);
        $this->assertSame([], next_actions::for_manager([$followed]));

        // Nor does it hide the latest feasibility deposit, which stays the latest of its document.
        $second = $this->followed();
        $this->deposit_of($second, 1, 4000);
        $this->deposit_of($second, 2, 5000, draft_manager::DOCTYPE_VALIDATION);
        $line = $this->line(next_actions::for_manager([$followed, $second]), next_actions::OPINIONS);
        $this->assertNotNull($line);
        $this->assertSame(1, $line->count);
        $this->assertSame('/admin/tool/vae/deposit.php?userid=' . $second, $line->url->out_as_local_url(false));
    }

    public function test_opinions_is_absent_without_the_right_to_write_one(): void {
        $withoutwrite = ['tool/vae:viewassigned', 'tool/vae:attest', 'tool/vae:readdeposit'];
        $withoutread = ['tool/vae:viewassigned', 'tool/vae:attest', 'tool/vae:writeopinion'];
        foreach ([$withoutwrite, $withoutread] as $capabilities) {
            $this->as_manager($capabilities);
            $followed = $this->followed();
            $this->deposit_of($followed, 1);

            $this->assertSame([], next_actions::for_manager([$followed]));
        }
    }

    public function test_each_link_leads_to_the_object_waiting(): void {
        $this->as_manager();
        $first = $this->followed();
        $second = $this->followed();
        $older = $this->declaration_of($first);
        $newer = $this->declaration_of($second);
        // The link leads to the request asked first, whatever the order of the declarations.
        $this->asked($newer, (int) $this->manager->id, 'Ma réponse.', 1000);
        $this->asked($older, (int) $this->manager->id, 'Ma réponse.', 2000);
        // The link leads to the deposit made first, whatever the order of the rows.
        $this->deposit_of($first, 1, 3000);
        $this->deposit_of($second, 1, 2000);

        $lines = next_actions::for_manager([$first, $second]);

        $this->assertSame(
            [next_actions::ATTEST, next_actions::ANSWERS, next_actions::OPINIONS],
            array_column($lines, 'key')
        );
        $this->assertSame([2, 2, 2], array_column($lines, 'count'));
        $this->assertSame('/admin/tool/vae/candidates.php', $lines[0]->url->out_as_local_url(false));
        $this->assertSame('/admin/tool/vae/precision.php?evidenceid=' . $newer, $lines[1]->url->out_as_local_url(false));
        $this->assertSame('/admin/tool/vae/deposit.php?userid=' . $second, $lines[2]->url->out_as_local_url(false));
    }

    public function test_nothing_awaiting_gives_an_empty_list(): void {
        $this->as_manager();
        $followed = $this->followed();

        $this->assertSame([], next_actions::for_manager([]));
        $this->assertSame([], next_actions::for_manager([$followed]));

        // A declaration attested after its answer was read, a deposit with an opinion: nothing awaits.
        $evidenceid = $this->declaration_of($followed);
        $this->asked($evidenceid, (int) $this->manager->id, 'Ma réponse.');
        $this->attestation($evidenceid);
        $this->opinion_on($this->deposit_of($followed, 1), (int) $this->manager->id);

        $this->assertSame([], next_actions::for_manager([$followed]));
    }

    public function test_the_number_of_queries_does_not_grow_with_the_followed(): void {
        global $DB;

        $this->as_manager();
        $ids = [];
        for ($i = 0; $i < 20; $i++) {
            $id = $this->followed();
            $this->asked($this->declaration_of($id), (int) $this->manager->id, 'Ma réponse.', 1000 + $i);
            $this->deposit_of($id, 1, 1000 + $i);
            $ids[] = $id;
        }
        // The contexts and the rights of the twenty are loaded once, as the page has them after reading the followed.
        $this->assertCount(3, next_actions::for_manager($ids));

        $before = $DB->perf_get_reads();
        $two = next_actions::for_manager(array_slice($ids, 0, 2));
        $readstwo = $DB->perf_get_reads() - $before;

        $before = $DB->perf_get_reads();
        $twenty = next_actions::for_manager($ids);
        $readstwenty = $DB->perf_get_reads() - $before;

        $this->assertSame([2, 2, 2], array_column($two, 'count'));
        $this->assertSame([20, 20, 20], array_column($twenty, 'count'));
        $this->assertSame($readstwo, $readstwenty);
    }

    public function test_no_name_of_a_vaeiste_is_carried(): void {
        $this->as_manager();
        $names = [
            ['firstname' => 'Zéphyrine', 'lastname' => 'Quintanilla'],
            ['firstname' => 'Anatole', 'lastname' => 'Vercingétorix'],
        ];
        $ids = [];
        foreach ($names as $name) {
            $id = $this->followed($name);
            $this->asked($this->declaration_of($id), (int) $this->manager->id, 'Ma réponse.');
            $this->deposit_of($id, 1);
            $ids[] = $id;
        }

        $lines = next_actions::for_manager($ids);
        $this->assertCount(3, $lines);
        foreach ($lines as $line) {
            // Nothing else than these three is carried, and the whole line, as given, holds no name.
            $this->assertSame(['key', 'count', 'url'], array_keys(get_object_vars($line)));
            $json = json_encode($line, JSON_UNESCAPED_UNICODE) . ' ' . $line->url->out(false);
            foreach ($names as $name) {
                $this->assertStringNotContainsString($name['firstname'], $json);
                $this->assertStringNotContainsString($name['lastname'], $json);
            }
        }
    }

    public function test_for_manager_writes_nothing(): void {
        global $DB;

        $this->as_manager();
        $followed = $this->followed();
        $this->asked($this->declaration_of($followed), (int) $this->manager->id, 'Ma réponse.');
        $this->deposit_of($followed, 1);

        $before = $DB->perf_get_writes();
        $this->assertCount(3, next_actions::for_manager([$followed]));
        $this->assertSame($before, $DB->perf_get_writes());
    }
}
