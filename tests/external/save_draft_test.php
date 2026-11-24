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

namespace tool_vae\external;

use core_external\external_api;
use tool_vae\local\draft_manager;

/**
 * The external function tool_vae_save_draft, which saves the draft in progress of a text being written (ticket 5 of MMR-520).
 *
 * WHAT IS ASSERTED. What the page is answered and what is written, through the door the browser takes (the external function
 * called as core/ajax calls it, session key included) and through execute(): a new text is kept and the page is told its time,
 * the text of the latest version keeps no draft, a conflict between two tabs is ANSWERED (conflict true, the localised message,
 * the draft of the other tab untouched) and not thrown, and every refusal of a right stays an exception that writes nothing.
 * The logic of the drafts is proved by draft_manager_test, which is not rewritten here.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(save_draft::class)]
final class save_draft_test extends \advanced_testcase {
    /** @var string The name of the function, as the module tool_vae/autosave asks for it. */
    private const FUNCTION = 'tool_vae_save_draft';

    /** @var \stdClass The vaeiste: viewown and draftown in their own context. */
    private $vaeiste;

    /** @var \stdClass Another vaeiste, with the same rights in their own context. */
    private $othervaeiste;

    /** @var \stdClass Someone who holds no right of the plugin. */
    private $stranger;

    /**
     * Two vaeistes, a stranger, the record RNCP400 (D1 holding C11) and the preparation open.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // A work situation is opened in a delegated transaction (situation_manager): see situation_manager_test.
        $this->preventResetByRollback();
        \tool_vae\local\validation_catalog::reset();
        set_config('validationopen', 1, 'tool_vae');

        $datagenerator = $this->getDataGenerator();
        $generator = $datagenerator->get_plugin_generator('tool_vae');
        $generator->create_source(['fichecode' => 'RNCP400', 'ficherang' => 'D1', 'shortname' => 'Accompany the person']);
        $generator->create_competency([
            'fichecode' => 'RNCP400', 'ficherang' => 'C11', 'shortname' => 'Help with daily care', 'parent' => 'D1',
        ]);

        $this->vaeiste = $datagenerator->create_user(['firstname' => 'Camille', 'lastname' => 'Durand']);
        $this->othervaeiste = $datagenerator->create_user(['firstname' => 'Sacha', 'lastname' => 'Autre']);
        $this->stranger = $datagenerator->create_user();

        $system = \context_system::instance()->id;
        $own = $datagenerator->create_role();
        assign_capability('tool/vae:viewown', CAP_ALLOW, $own, $system, true);
        assign_capability('tool/vae:draftown', CAP_ALLOW, $own, $system, true);
        set_role_contextlevels($own, [CONTEXT_USER]);
        foreach ([$this->vaeiste, $this->othervaeiste] as $person) {
            role_assign($own, $person->id, \context_user::instance($person->id)->id);
        }
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * A section of the dossier de faisabilite of the vaeiste, as section.php opens it.
     *
     * @return \stdClass The row of tool_vae_draft.
     */
    private function section(): \stdClass {
        $this->setUser($this->vaeiste);

        return (new draft_manager())->section((int) $this->vaeiste->id, draft_manager::DOCTYPE_FAISABILITE, 'objectifs', 0);
    }

    /**
     * A work situation of the vaeiste, as situation.php writes its text: the row of its draft.
     *
     * @return \stdClass The row of tool_vae_draft.
     */
    private function situation_draft(): \stdClass {
        global $DB;

        $situation = $this->getDataGenerator()->get_plugin_generator('tool_vae')->create_situation([
            'userid' => (int) $this->vaeiste->id, 'fichecode' => 'RNCP400', 'text' => 'I led the night round.',
        ]);

        return $DB->get_record('tool_vae_draft', ['id' => $situation->draftid], '*', MUST_EXIST);
    }

    /**
     * Call the function as the browser does: through the core, which checks the session key, the parameters and the answer.
     *
     * @param array $args
     * @return array The answer of the core: error, and data or exception.
     */
    private static function call(array $args): array {
        // The core reads the session key from the request (require_sesskey()), as core tests do.
        $_POST['sesskey'] = sesskey();
        try {
            return external_api::call_external_function(self::FUNCTION, $args, true);
        } finally {
            unset($_POST['sesskey']);
        }
    }

    /**
     * The working text of a draft as the database holds it.
     *
     * @param int $draftid
     * @return array working and workingat.
     */
    private static function row(int $draftid): array {
        global $DB;

        $row = $DB->get_record('tool_vae_draft', ['id' => $draftid], 'working, workingat', MUST_EXIST);

        return [$row->working, $row->workingat];
    }

    public function test_the_function_is_declared_for_ajax_with_the_answer_the_module_reads(): void {
        $info = external_api::external_function_info(self::FUNCTION);

        $this->assertSame(save_draft::class, ltrim($info->classname, '\\'));
        $this->assertSame('execute', $info->methodname);
        $this->assertSame('tool_vae', $info->component);
        $this->assertSame('write', $info->type);
        $this->assertTrue($info->allowed_from_ajax);
        $this->assertTrue($info->loginrequired);
        $this->assertSame(['draftid', 'text', 'workingat'], array_keys($info->parameters_desc->keys));
        // What amd/src/autosave.js reads of the answer, nothing more.
        $this->assertSame(['workingat', 'message', 'conflict'], array_keys($info->returns_desc->keys));
        $this->assertSame(PARAM_INT, $info->returns_desc->keys['workingat']->type);
        $this->assertSame(PARAM_BOOL, $info->returns_desc->keys['conflict']->type);
    }

    public function test_a_new_text_is_kept_and_the_page_is_told_its_time(): void {
        $draft = $this->section();
        // Accents, quotes, a tag and a line break, as a vaeiste writes them: kept as typed (the text is free text).
        $text = "Je veux obtenir le diplôme d'aide-soignant « pour évoluer ».\n<b>Note</b> : \"rythme\" & habitudes.";

        $answer = self::call(['draftid' => (int) $draft->id, 'text' => $text, 'workingat' => 0]);

        $this->assertFalse($answer['error'], json_encode($answer['exception'] ?? null));
        [$working, $workingat] = self::row((int) $draft->id);
        $this->assertSame($text, $working);
        $this->assertSame((int) $workingat, $answer['data']['workingat']);
        $this->assertGreaterThan(0, $answer['data']['workingat']);
        $this->assertSame(get_string('draftautosaved', 'tool_vae', userdate((int) $workingat)), $answer['data']['message']);
        $this->assertFalse($answer['data']['conflict']);
    }

    public function test_a_text_of_the_greatest_length_is_kept_and_a_longer_one_is_refused(): void {
        $draft = $this->section();
        $longest = str_repeat('é', draft_manager::CONTENT_MAXLENGTH);

        $answer = save_draft::execute((int) $draft->id, $longest, 0);
        $this->assertFalse($answer['conflict']);
        $this->assertSame($longest, self::row((int) $draft->id)[0]);

        try {
            save_draft::execute((int) $draft->id, $longest . 'x', (int) $answer['workingat']);
            $this->fail('A text longer than the bound must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('errordrafttoolong', $e->errorcode);
        }
        $this->assertSame($longest, self::row((int) $draft->id)[0], 'The draft is untouched.');
    }

    public function test_the_text_of_the_latest_version_keeps_no_draft(): void {
        $draft = $this->section();
        (new draft_manager())->save_version((int) $draft->id, 'Texte un');

        $answer = self::call(['draftid' => (int) $draft->id, 'text' => 'Texte un', 'workingat' => 0]);

        $this->assertFalse($answer['error'], json_encode($answer['exception'] ?? null));
        $this->assertSame(0, $answer['data']['workingat']);
        $this->assertSame(get_string('draftautosavedsame', 'tool_vae'), $answer['data']['message']);
        $this->assertFalse($answer['data']['conflict']);
        $this->assertSame([null, null], self::row((int) $draft->id));
    }

    public function test_a_conflict_between_two_tabs_is_answered_and_the_other_tab_keeps_its_draft(): void {
        $draft = $this->section();
        // Two tabs opened with no draft in progress: both know 0. The first is answered.
        $first = self::call(['draftid' => (int) $draft->id, 'text' => 'Onglet A : deux heures de travail', 'workingat' => 0]);
        $this->assertFalse($first['error']);

        $second = self::call(['draftid' => (int) $draft->id, 'text' => 'Onglet B', 'workingat' => 0]);

        $this->assertFalse($second['error'], 'A conflict is an answer, not an exception.');
        $this->assertTrue($second['data']['conflict']);
        $this->assertSame(get_string('errordraftconflict', 'tool_vae'), $second['data']['message']);
        $this->assertSame(0, $second['data']['workingat'], 'The page keeps the time it knew.');
        $this->assertSame('Onglet A : deux heures de travail', self::row((int) $draft->id)[0]);

        // The first tab goes on with the time it was answered.
        $again = self::call([
            'draftid' => (int) $draft->id, 'text' => 'Onglet A, la suite', 'workingat' => $first['data']['workingat'],
        ]);
        $this->assertFalse($again['data']['conflict']);
        $this->assertSame('Onglet A, la suite', self::row((int) $draft->id)[0]);
    }

    public function test_a_negative_time_is_read_as_no_draft_known(): void {
        $draft = $this->section();

        // A page that knew no draft: the text is kept.
        $first = save_draft::execute((int) $draft->id, 'Premier jet', -5);
        $this->assertFalse($first['conflict']);
        $this->assertSame('Premier jet', self::row((int) $draft->id)[0]);

        // And -5 is compared as 0: a draft written since is not overwritten.
        $second = save_draft::execute((int) $draft->id, 'Autre onglet', -5);
        $this->assertTrue($second['conflict']);
        $this->assertSame(0, $second['workingat']);
        $this->assertSame('Premier jet', self::row((int) $draft->id)[0]);
    }

    public function test_a_draftid_that_is_no_number_is_refused_by_the_parameters(): void {
        $draft = $this->section();

        $answer = self::call(['draftid' => 'abc', 'text' => 'Texte', 'workingat' => 0]);
        $this->assertTrue($answer['error']);
        $this->assertSame('invalidparameter', $answer['exception']->errorcode);

        $this->expectException(\invalid_parameter_exception::class);
        external_api::validate_parameters(save_draft::execute_parameters(), [
            'draftid' => (int) $draft->id . ' OR 1=1', 'text' => 'Texte', 'workingat' => 0,
        ]);
    }

    /**
     * The refusals of a right: each one an exception, as before, and nothing written.
     *
     * @return array
     */
    public static function refusals(): array {
        return [
            'another vaeiste' => ['othervaeiste', 'errordraftrefused'],
            'a person with no right' => ['stranger', 'errordraftrefused'],
            'the site administrator' => ['admin', 'errordraftrefused'],
        ];
    }

    /**
     * Nobody but the vaeiste writes the draft of the vaeiste, the administrator included.
     *
     * @param string $who
     * @param string $errorcode
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusals')]
    public function test_nobody_else_writes_the_draft(string $who, string $errorcode): void {
        $draft = $this->section();
        save_draft::execute((int) $draft->id, 'Le texte du vaeiste', 0);
        $before = self::row((int) $draft->id);

        $this->setUser($who === 'admin' ? get_admin() : $this->{$who});
        $answer = self::call(['draftid' => (int) $draft->id, 'text' => 'Écrit par un autre', 'workingat' => (int) $before[1]]);

        $this->assertTrue($answer['error']);
        $this->assertSame($errorcode, $answer['exception']->errorcode);
        $this->assertSame($before, self::row((int) $draft->id), 'The draft is untouched.');
    }

    public function test_a_draft_that_does_not_exist_is_refused_as_one_not_ours(): void {
        $this->setUser($this->vaeiste);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('errordraftrefused', 'tool_vae'));
        save_draft::execute(987654, 'Texte', 0);
    }

    public function test_the_draft_of_a_situation_is_written_by_its_vaeiste_only_and_while_the_preparation_is_open(): void {
        $draft = $this->situation_draft();
        $this->setUser($this->vaeiste);
        $kept = save_draft::execute((int) $draft->id, 'I led the night round with a new colleague.', 0);
        $this->assertFalse($kept['conflict']);
        $before = self::row((int) $draft->id);

        // The situation of another.
        $this->setUser($this->othervaeiste);
        $answer = self::call(['draftid' => (int) $draft->id, 'text' => 'Pas la mienne', 'workingat' => (int) $before[1]]);
        $this->assertTrue($answer['error']);
        $this->assertSame('errordraftrefused', $answer['exception']->errorcode);

        // The preparation closed: a tab left open is refused.
        $this->setUser($this->vaeiste);
        set_config('validationopen', 0, 'tool_vae');
        $answer = self::call(['draftid' => (int) $draft->id, 'text' => 'Après la fermeture', 'workingat' => (int) $before[1]]);
        $this->assertTrue($answer['error']);
        $this->assertSame('errorvalidationclosed', $answer['exception']->errorcode);
        $this->assertSame($before, self::row((int) $draft->id), 'The draft is untouched.');
    }

    public function test_nobody_logged_in_is_refused_by_the_core(): void {
        $draft = $this->section();
        $this->setUser(null);

        $answer = external_api::call_external_function(self::FUNCTION, [
            'draftid' => (int) $draft->id, 'text' => 'Texte', 'workingat' => 0,
        ], true);

        $this->assertTrue($answer['error']);
        $this->assertSame('servicerequireslogin', $answer['exception']->errorcode);
        $this->assertSame([null, null], self::row((int) $draft->id));
    }
}
