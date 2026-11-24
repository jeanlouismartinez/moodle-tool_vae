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

namespace tool_vae\output;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/organisation_before.php');
require_once(__DIR__ . '/pages_before.php');

/**
 * The page dossier.php, played as the site plays it (tests/fixtures/plugin_pages.php), draws under its heading the DOM the
 * old page drew (lot 7, MMR-520 ticket 4, wave 4b): the old code is kept in the trait pages_before and run for the same
 * reader on the same data, compared in canonical DOM. The states are those the tool of equivalence does not serve: the
 * competencies switched off; every way on an attestation (the request to the signatory, the request that waits, the
 * signatory who is gone, the button that asks, the button that withdraws, the button that attests); the pieces added after
 * the signature; the questions that wait; no declaration.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(dossier_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(dossier_claim::class)]
final class dossier_page_test extends \advanced_testcase {
    use dom_canon;
    use organisation_before;
    use pages_before;

    /** @var \stdClass The vaeiste. */
    private $vaeiste;

    /** @var \stdClass The manager who signs. */
    private $signatory;

    /** @var \stdClass Another manager who follows and may attest. */
    private $colleague;

    /** @var \stdClass[] Two declarations of the vaeiste: 'signed' (attested by the signatory) and 'open'. */
    private $claims = [];

    /** @var \stdClass The attestation of the declaration 'signed'. */
    private $attestation;

    /**
     * A record of two competencies, the vaeiste with two declarations, two managers; one declaration attested by the first,
     * with a piece added after the signature and a question that waits; the pieces, the preparation and the journal open.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        set_config('piecesopen', 1, 'tool_vae');
        set_config('validationopen', 1, 'tool_vae');
        // The function of the marks open, so that the box is drawn for the vaeiste and for a reader with what each may do
        // (added after the mutants dpt-order and dos-mayscope stayed green).
        set_config('scopeopen', 1, 'tool_vae');
        $generator = $this->getDataGenerator();
        $plugin = $generator->get_plugin_generator('tool_vae');
        $plugin->create_source(['fichecode' => 'RNCP100', 'ficherang' => 'C01', 'shortname' => 'Aider']);
        $plugin->create_competency(['fichecode' => 'RNCP100', 'ficherang' => 'C02', 'shortname' => 'Soigner']);
        $system = \context_system::instance()->id;
        $own = $generator->create_role();
        foreach (['declare', 'viewown', 'export', 'draftown', 'positionself'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $own, $system, true);
        }
        $follow = $generator->create_role();
        foreach (['viewassigned', 'attest'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $follow, $system, true);
        }
        set_role_contextlevels($follow, [CONTEXT_USER]);
        $this->vaeiste = $generator->create_user(['firstname' => 'Camille', 'lastname' => 'Martin']);
        $this->signatory = $generator->create_user(['firstname' => 'Nadia', 'lastname' => 'Benali']);
        $this->colleague = $generator->create_user(['firstname' => 'Hugo', 'lastname' => 'Marchand']);
        $usercontext = \context_user::instance($this->vaeiste->id)->id;
        role_assign($own, $this->vaeiste->id, $usercontext);
        role_assign($follow, $this->signatory->id, $usercontext);
        role_assign($follow, $this->colleague->id, $usercontext);
        accesslib_clear_all_caches_for_unit_testing();

        foreach (['signed' => 'C01', 'open' => 'C02'] as $key => $rank) {
            $this->claims[$key] = $plugin->create_declaration([
                'userid' => $this->vaeiste->id, 'fichecode' => 'RNCP100', 'ficherang' => $rank, 'situation' => 'Au lever ' . $key,
            ]);
        }
        $this->attestation = $plugin->create_attestation([
            'evidenceid' => $this->claims['signed']->id, 'userid' => $this->signatory->id,
        ]);
        $plugin->create_piece([
            'evidenceid' => $this->claims['signed']->id, 'nature' => 'other', 'title' => 'Après', 'url' => 'https://example.org/a',
        ]);
        $DB->delete_records('task_adhoc');
    }

    /**
     * The page, played as the site plays it, for the current user.
     *
     * @param int $userid The dossier asked.
     * @return string
     */
    private function page(int $userid): string {
        // The page is a script of the site: it reads these as globals.
        global $CFG, $DB, $OUTPUT, $PAGE, $SITE, $USER;

        $_GET = ['userid' => $userid];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $PAGE = new \moodle_page();
        ob_start();
        try {
            require_once(__DIR__ . '/../fixtures/plugin_pages.php');
            \tool_vae\fixtures\plugin_pages::run('dossier.php');
        } finally {
            $out = ob_get_clean();
            $_GET = [];
        }

        return $out;
    }

    /**
     * The new page played, then the old code under the heading run for the same reader: the old block ends the new page.
     *
     * @param \stdClass $reader
     * @param string $name
     * @return string The old HTML, for the premises.
     */
    private function same(\stdClass $reader, string $name): string {
        $this->setUser($reader);
        $id = (int) $this->vaeiste->id;
        $new = $this->page($id);
        $old = self::before_dossier_page($id, \context_user::instance($id));
        $this->assertNotSame('', trim($old), $name);
        // Under PHPUnit the page draws its header and heading as text (the renderer of the command line): what follows the
        // heading is the page under its heading, compared whole.
        $heading = '-->' . get_string('mydossier', 'tool_vae');
        $this->assertSame(1, substr_count($new, $heading), $name . ': premise, one heading.');
        $under = substr($new, strpos($new, $heading) + strlen($heading));
        $this->assertSame(self::canon($old), self::canon($under), $name);
        $this->assertSame(substr_count($old, 'singlebutton'), substr_count($new, 'singlebutton'), $name . ': buttons.');

        return $old;
    }

    public function test_the_signatory_asked_to_withdraw_gets_the_dom_of_before(): void {
        global $DB;

        $DB->set_field('tool_vae_attest', 'withdrawreqat', time(), ['id' => $this->attestation->id]);
        $old = $this->same($this->signatory, 'the signatory asked');
        $this->assertStringContainsString(get_string('requestwithdrawalyours', 'tool_vae'), $old, 'Premise.');
        $this->assertStringContainsString('name="action" value="refuse"', $old, 'Premise: the refusal, posted.');
        $this->assertStringContainsString('withdraw.php', $old, 'Premise: accept.');
        $this->assertStringContainsString('attest.php', $old, 'Premise: the other declaration may be attested.');
    }

    public function test_a_colleague_while_a_request_waits_gets_the_dom_of_before(): void {
        global $DB;

        $DB->set_field('tool_vae_attest', 'withdrawreqat', time(), ['id' => $this->attestation->id]);
        $old = $this->same($this->colleague, 'a request waits');
        $this->assertStringContainsString(get_string('requestwithdrawalpending', 'tool_vae'), $old, 'Premise.');

        $DB->set_field('user', 'suspended', 1, ['id' => $this->signatory->id]);
        \core_user::reset_internal_users();
        $old = $this->same($this->colleague, 'the signatory is gone');
        $this->assertStringContainsString(get_string('requestwithdrawalnosignatory', 'tool_vae'), $old, 'Premise.');
        $this->assertStringNotContainsString(get_string('requestwithdrawalpending', 'tool_vae'), $old, 'Premise.');
    }

    public function test_a_colleague_may_ask_and_the_administrator_may_withdraw_as_before(): void {
        $old = $this->same($this->colleague, 'a colleague asks');
        $this->assertStringContainsString('name="action" value="request"', $old, 'Premise: the request, posted.');
        $this->assertStringContainsString(s(get_string('pieceslate', 'tool_vae', 1)), $old, 'Premise: the piece after.');

        $old = $this->same(get_admin(), 'the administrator');
        $this->assertStringContainsString(get_string('withdraw', 'tool_vae'), $old, 'Premise.');
        $this->assertStringContainsString('import_dossier.php', $old, 'Premise: the import.');
    }

    public function test_the_vaeiste_gets_the_dom_of_before_with_a_question_waiting_and_without_a_declaration(): void {
        global $DB;

        $this->setUser($this->colleague);
        (new \tool_vae\local\request_manager())->ask((int) $this->claims['open']->id, 'Pourquoi ?');
        // The function of the marks is open (setUp): the box is drawn for the vaeiste, after the coverage (added after the
        // mutant dpt-order stayed green: with the function closed and no mark the box draws nothing, its order not seen).
        // The anchor of the declaration "open" renamed in the core since it was made (added after the mutant of page
        // dos-warning, which the tool cannot see: no declaration of the instance has left its anchor).
        $DB->set_field('tool_vae_evidence', 'corelabel', 'Ancien nom', ['id' => $this->claims['open']->id]);
        $old = $this->same($this->vaeiste, 'the vaeiste');
        $this->assertStringContainsString('faisabilite.php', $old, 'Premise: the buttons of the vaeiste.');
        $this->assertStringContainsString('was renamed outside this tool', $old, 'Premise: the anchor renamed.');
        $this->assertLessThan(strpos($old, 'tool-vae-scope'), strpos($old, 'tool-vae-coverage'), 'Premise: coverage, then marks.');
        $this->assertStringContainsString(get_string('precisionlinkwaiting', 'tool_vae', 1), $old, 'Premise: a question waits.');

        $DB->delete_records('tool_vae_evidence');
        $old = $this->same($this->vaeiste, 'no declaration');
        $this->assertStringContainsString(get_string('nodeclarationyet', 'tool_vae'), $old, 'Premise.');
    }

    public function test_a_reader_who_may_read_and_not_attest_gets_the_dom_of_before(): void {
        global $DB;

        // Added by the pilot of wave 4b after his mutants of page stayed green: no state had a reader who reads without the
        // right to attest, nor a mark of the vaeiste read by somebody else, nor a reader whose rights on the dossier differ
        // from those on their own account (the arguments that name the vaeiste: marks, export, backup, deposits).
        $generator = $this->getDataGenerator();
        $system = \context_system::instance()->id;
        $site = $generator->create_role();
        foreach (['tool/vae:viewany', 'tool/vae:export', 'tool/vae:importdossier'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $site, $system, true);
        }
        $near = $generator->create_role();
        foreach (['tool/vae:readdeposit', 'moodle/competency:userevidencemanage'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $near, $system, true);
        }
        $reader = $generator->create_user(['firstname' => 'Diane', 'lastname' => 'Morel']);
        role_assign($site, $reader->id, $system);
        role_assign($near, $reader->id, \context_user::instance($this->vaeiste->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $id = (int) $this->vaeiste->id;
        $this->setUser($this->vaeiste);
        \tool_vae\local\scope_manager::mark($id, 'RNCP100', 'C02', 'Hors de mon poste');
        $marks = \tool_vae\local\scope_manager::for_candidate($id);
        $this->assertNotSame([], array_filter(\tool_vae\local\scope_manager::labels($marks)), 'Premise: the mark has a label.');

        $old = $this->same($reader, 'a reader who may not attest');
        $this->assertStringContainsString('Hors de mon poste', $old, 'Premise: the mark of the vaeiste, read by another.');
        foreach (['export.php', 'backup.php', 'deposit.php', 'import_dossier.php'] as $page) {
            $this->assertStringContainsString($page, $old, 'Premise: the button ' . $page);
        }
        $this->assertStringNotContainsString('attest.php', $old, 'Premise: no right to attest.');
        $this->assertFalse(\tool_vae\local\access::can_export((int) $reader->id), 'Premise: not on their own account.');
        $this->assertFalse(\tool_vae\local\backup::can_backup((int) $reader->id), 'Premise: not on their own account.');
        $this->assertFalse(\tool_vae\local\deposit_manager::can_read((int) $reader->id), 'Premise: not on their own account.');

        // A request to withdraw waits: said to whoever may attest, to nobody else.
        $DB->set_field('tool_vae_attest', 'withdrawreqat', time(), ['id' => $this->attestation->id]);
        $old = $this->same($reader, 'a request waits, the reader may not attest');
        $this->assertStringNotContainsString(get_string('requestwithdrawalpending', 'tool_vae'), $old, 'Premise.');

        // The signatory gone: said to whoever may attest, to nobody else.
        $DB->set_field('tool_vae_attest', 'withdrawreqat', null, ['id' => $this->attestation->id]);
        $DB->set_field('user', 'suspended', 1, ['id' => $this->signatory->id]);
        \core_user::reset_internal_users();
        $old = $this->same($reader, 'the signatory is gone, the reader may not attest');
        $this->assertStringNotContainsString(get_string('requestwithdrawalnosignatory', 'tool_vae'), $old, 'Premise.');
    }

    public function test_a_colleague_gets_the_dom_of_before_with_a_piece_signed_a_deposit_and_a_signatory_erased(): void {
        global $DB;

        // Added by the pilot of wave 4b: the only piece of the states was put after the signature, no deposit of the
        // preparation existed, and every attestation had its signatory.
        $plugin = $this->getDataGenerator()->get_plugin_generator('tool_vae');
        // A piece put BEFORE the signature of the declaration "open": the signature covers it, it is not a piece "after".
        $plugin->create_piece([
            'evidenceid' => $this->claims['open']->id, 'nature' => 'other', 'title' => 'Avant', 'url' => 'https://example.org/b',
        ]);
        // Signed by the manager itself: the generator does not say which pieces the signatory was shown.
        $this->setUser($this->signatory);
        $open = (int) $this->claims['open']->id;
        $second = (new \tool_vae\local\attestation_manager())->attest($open, (object) [
            'attestantrole' => 'Cadre de santé',
            'statement' => 'Vu au lever, sur la période dite.',
            'piecesseen' => \tool_vae\local\piece_manager::list_token(\tool_vae\local\piece_manager::active_for_evidence($open)),
            'piecesread' => 1,
        ]);
        $this->assertCount(1, \tool_vae\local\piece_manager::covered_by((int) $second->id), 'Premise: the piece is covered.');
        // A deposit of the preparation of the validation: its button is for whoever may read deposits, not for a colleague.
        $DB->insert_record('tool_vae_deposit', (object) [
            'uuid' => \tool_vae\local\identity_map::generate_ulid(),
            'candidateid' => $this->vaeiste->id,
            'doctype' => \tool_vae\local\draft_manager::DOCTYPE_VALIDATION,
            'number' => 1,
            'content' => '{}',
            'contenthash' => hash('sha256', '{}'),
            'timecreated' => time(),
        ]);

        $old = $this->same($this->colleague, 'a piece signed, a deposit');
        $this->assertSame(1, substr_count($old, 'badge bg-warning text-dark'), 'Premise: one declaration has a piece after.');
        $this->assertStringNotContainsString('deposit.php', $old, 'Premise: the colleague may not read deposits.');

        // The signatory erased (the account deleted, the attestation kept without a name): nobody to ask, and no sentence.
        $DB->set_field('tool_vae_attest', 'attestantid', null, ['id' => $this->attestation->id]);
        $old = $this->same($this->colleague, 'the signatory erased');
        $this->assertStringNotContainsString(get_string('requestwithdrawalnosignatory', 'tool_vae'), $old, 'Premise.');
        $this->assertSame(1, substr_count($old, 'name="action" value="request"'), 'Premise: the other signature may be asked.');
    }

    public function test_the_vaeiste_gets_the_dom_of_before_whatever_the_site_opens_and_when_writing_is_taken_back(): void {
        global $DB;

        // Added by the pilot of wave 4b: every state had the journal closed, the preparation open and a vaeiste who may
        // write, so no term of the conditions of these buttons was ever false alone.
        $id = (int) $this->vaeiste->id;
        // The journal open with nothing written, the preparation of the validation closed.
        set_config('reflectionopen', 1, 'tool_vae');
        set_config('validationopen', 0, 'tool_vae');
        $old = $this->same($this->vaeiste, 'journal open, validation closed');
        $this->assertStringContainsString('journal.php', $old, 'Premise: the journal is open.');
        $this->assertStringNotContainsString('validation.php', $old, 'Premise: the preparation is closed.');

        // The journal closed again, a text kept: the button stays.
        set_config('reflectionopen', 0, 'tool_vae');
        set_config('validationopen', 1, 'tool_vae');
        $DB->insert_record('tool_vae_draft', (object) [
            'uuid' => \tool_vae\local\identity_map::generate_ulid(),
            'candidateid' => $id,
            'doctype' => \tool_vae\local\draft_manager::DOCTYPE_REFLEXION,
            'section' => 'journal',
            'slot' => 0,
            'fichecode' => '',
            'currentversion' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => $id,
        ]);
        $old = $this->same($this->vaeiste, 'journal closed, a text kept');
        $this->assertStringContainsString('journal.php', $old, 'Premise: the journal kept.');
        $this->assertStringContainsString('validation.php', $old, 'Premise: the preparation is open.');
        $this->assertStringContainsString('faisabilite.php', $old, 'Premise: the vaeiste may write.');

        // The right to write taken back, a work situation written before: nothing to write, nothing "to read" of one's own.
        $this->getDataGenerator()->get_plugin_generator('tool_vae')->create_situation([
            'userid' => $id, 'fichecode' => 'RNCP100', 'title' => 'Le lever',
        ]);
        set_config('reflectionopen', 1, 'tool_vae');
        $role = $this->getDataGenerator()->create_role();
        assign_capability('tool/vae:draftown', CAP_PROHIBIT, $role, \context_system::instance()->id, true);
        role_assign($role, $id, \context_user::instance($id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $old = $this->same($this->vaeiste, 'writing taken back');
        foreach (['faisabilite.php', 'journal.php', 'validation.php'] as $page) {
            $this->assertStringNotContainsString($page, $old, 'Premise: no button ' . $page);
        }
        foreach (['situationslink', 'situationsreadlink'] as $key) {
            $this->assertStringNotContainsString(get_string($key, 'tool_vae'), $old, 'Premise: no button ' . $key);
        }
        $this->assertTrue($DB->record_exists(\tool_vae\local\situation_manager::TABLE, ['candidateid' => $id]), 'Premise.');
    }

    public function test_the_competencies_switched_off_draw_the_dom_of_before(): void {
        // The page ends this way with die(), which a test cannot play: the class is compared to the old notification of the
        // core, and the page itself is played by Behat (see the report of wave 4b).
        global $PAGE;

        $new = $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render(dossier_page::disabled());
        $old = self::core()->notification(
            get_string('errorcompetenciesdisabled', 'tool_vae'),
            \core\output\notification::NOTIFY_ERROR
        );
        $this->assertSame(self::canon($old), self::canon($new));
        $this->assertStringContainsString('alert-danger', $new);
    }

    public function test_the_templates_place_raw_only_the_keys_named_html(): void {
        global $CFG;

        foreach (['dossier_page', 'dossier_claim'] as $name) {
            $text = file_get_contents($CFG->dirroot . '/admin/tool/vae/templates/' . $name . '.mustache');
            preg_match_all('/\{\{\{(\w+)\}\}\}/', $text, $raw);
            $this->assertNotEmpty($raw[1]);
            foreach ($raw[1] as $key) {
                $this->assertStringEndsWith('html', $key, $name);
            }
        }
    }
}
