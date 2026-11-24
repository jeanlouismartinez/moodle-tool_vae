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
require_once(__DIR__ . '/presenter_before.php');

use tool_vae\form\attest_form;
use tool_vae\local\attestation_manager;
use tool_vae\local\identity_map;
use tool_vae\local\piece_manager;

/**
 * The page "Attester une déclaration" (attest.php) drawn by tool_vae\output\attest_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration (its code kept here as
 * the expected value, the screens it called kept in the trait presenter_before), for every state: not signed, with no piece
 * and with pieces (the form of the core, drawn once); signed, with the whole history of the attestations, and each way out:
 * the button to withdraw, the button to ask the signatory to withdraw, the notice that the signatory is gone, or nothing.
 * The tool of equivalence shows only the first state (no attestation stands on its data set). Also: that the context
 * holds the pieces of the page, that the template places as HTML only its keys ...html and renders its example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(attest_page::class)]
final class attest_page_test extends \advanced_testcase {
    use dom_canon;
    use presenter_before;

    /** @var \stdClass The vaeiste. */
    private \stdClass $candidate;

    /** @var \stdClass The manager who attests. */
    private \stdClass $manager;

    /** @var int The declaration. */
    private int $evidenceid;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    protected function setUp(): void {
        global $CFG, $DB, $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        require_once($CFG->libdir . '/formslib.php');

        $generator = $this->getDataGenerator();
        $system = \context_system::instance()->id;
        $this->candidate = $generator->create_user(['firstname' => 'Camille', 'lastname' => 'Durand']);
        $this->manager = $generator->create_user();
        $context = \context_user::instance($this->candidate->id)->id;
        $own = $generator->create_role();
        assign_capability('tool/vae:declare', CAP_ALLOW, $own, $system, true);
        assign_capability('tool/vae:viewown', CAP_ALLOW, $own, $system, true);
        role_assign($own, $this->candidate->id, $context);
        $attest = $generator->create_role();
        assign_capability('tool/vae:viewassigned', CAP_ALLOW, $attest, $system, true);
        assign_capability('tool/vae:attest', CAP_ALLOW, $attest, $system, true);
        set_role_contextlevels($attest, [CONTEXT_USER]);
        role_assign($attest, $this->manager->id, $context);
        accesslib_clear_all_caches_for_unit_testing();

        $now = time();
        $this->evidenceid = (int) $DB->insert_record('tool_vae_evidence', (object) [
            'uuid' => identity_map::generate_ulid(),
            'userevidenceid' => 1,
            'candidateid' => (int) $this->candidate->id,
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
            'usermodified' => (int) $this->candidate->id,
        ]);
        set_config('piecesopen', 1, 'tool_vae');
        set_config('piecenoantivirus', 1, 'tool_vae');
        $this->redirectMessages();

        $this->url = new \moodle_url('/admin/tool/vae/attest.php', ['evidenceid' => $this->evidenceid]);
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_user::instance($this->candidate->id));
    }

    /**
     * The page drawn by the renderer of the plugin for the web.
     *
     * @param attest_page $page
     * @return string HTML.
     */
    private function render(attest_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The evidence row.
     *
     * @return \stdClass
     */
    private function evidence(): \stdClass {
        global $DB;

        return $DB->get_record('tool_vae_evidence', ['id' => $this->evidenceid], '*', MUST_EXIST);
    }

    /**
     * The form of the page, as the page builds it.
     *
     * @param \stdClass[] $pieces
     * @return attest_form
     */
    private function form(array $pieces): attest_form {
        $active = array_values(array_filter($pieces, fn($piece) => $piece->timeremoved === null));
        $form = new attest_form($this->url->out(false), ['haspieces' => (bool) $active]);
        $form->set_data(['evidenceid' => $this->evidenceid, 'piecesseen' => piece_manager::list_token($active)]);

        return $form;
    }

    /**
     * An attestation recorded by the manager.
     *
     * @return \stdClass The row.
     */
    private function attest(): \stdClass {
        $this->setUser($this->manager);
        $record = (new attestation_manager())->attest($this->evidenceid, (object) [
            'attestantrole' => 'Directeur',
            'statement' => "Je confirme.\nSur deux lignes.",
            'piecesseen' => piece_manager::list_token(piece_manager::active_for_evidence($this->evidenceid)),
            'piecesread' => 1,
        ]);
        \core\task\manager::clear_static_caches();

        return $record;
    }

    /**
     * The body of attest.php before its migration, below its heading (its code, kept as the expected value).
     *
     * The way out of a signature in force is the one the page chose by the same tests as before (attestation_manager).
     *
     * @param \stdClass $evidence
     * @param \stdClass[] $pieces
     * @param \stdClass|false|null $standing
     * @param string|null $recourse withdraw, request, gone or null.
     * @param string $formhtml The form, as display() printed it.
     * @return string HTML.
     */
    private function before(\stdClass $evidence, array $pieces, $standing, ?string $recourse, string $formhtml): string {
        $output = self::core();
        $context = \context_user::instance($this->candidate->id);
        $html = self::before_attest_declaration($evidence, $context);
        if ($pieces) {
            $html .= $output->heading(get_string('attestpiecesheading', 'tool_vae'), 3);
            $html .= \html_writer::tag('p', s(get_string('attestpiecesintro', 'tool_vae')));
            $html .= self::before_pieces($pieces, $context, $standing ?: null);
        }
        if ($standing) {
            $html .= $output->notification(get_string('errorattestexists', 'tool_vae'), \core\output\notification::NOTIFY_INFO);
            $html .= self::before_attestations(attestation_manager::attestations($this->evidenceid));
            if ($recourse === 'withdraw') {
                $html .= $output->single_button(
                    new \moodle_url('/admin/tool/vae/withdraw.php', ['attestationid' => $standing->id]),
                    get_string('withdraw', 'tool_vae'),
                    'get'
                );
            } else if ($recourse === 'request') {
                $html .= $output->single_button(
                    new \moodle_url('/admin/tool/vae/withdrawrequest.php', [
                        'attestationid' => $standing->id,
                        'action' => 'request',
                        'sesskey' => sesskey(),
                    ]),
                    get_string('requestwithdrawal', 'tool_vae'),
                    'post'
                );
            } else if ($recourse === 'gone') {
                $html .= $output->notification(
                    get_string('requestwithdrawalnosignatory', 'tool_vae'),
                    \core\output\notification::NOTIFY_INFO
                );
            }
        } else {
            $html .= $formhtml;
        }

        return $html;
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $page = attest_page::unsigned($this->evidence(), \context_user::instance($this->candidate->id), [], $this->form([]));

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/attest_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_not_signed_draws_the_dom_of_before(): void {
        $context = \context_user::instance($this->candidate->id);
        $this->setUser($this->manager);
        foreach (['no piece' => false, 'pieces' => true] as $name => $withpieces) {
            if ($withpieces) {
                $this->setUser($this->candidate);
                (new piece_manager())->add_link($this->evidenceid, 'diploma', 'Un diplôme', 'https://exemple.test/a');
                $this->setUser($this->manager);
            }
            $pieces = piece_manager::for_evidence($this->evidenceid);
            ob_start();
            $this->form($pieces)->display();
            $formhtml = ob_get_clean();

            $expected = $this->before($this->evidence(), $pieces, null, null, $formhtml);
            $html = $this->render(attest_page::unsigned($this->evidence(), $context, $pieces, $this->form($pieces)));
            $this->assertSame(1, substr_count($html, '<form'), $name);
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    public function test_signed_draws_the_dom_of_before_with_each_way_out(): void {
        $context = \context_user::instance($this->candidate->id);
        $this->setUser($this->candidate);
        (new piece_manager())->add_link($this->evidenceid, 'diploma', '', 'https://exemple.test/a');
        $first = $this->attest();
        $this->setAdminUser();
        (new attestation_manager())->withdraw((int) $first->id, 'erreur');
        $this->attest();
        // A piece added after the signature in force: the list says so, and can only say it with the signature the page gives.
        $this->setUser($this->candidate);
        (new piece_manager())->add_link($this->evidenceid, 'diploma', 'Ajoutée ensuite', 'https://exemple.test/b');
        $standing = attestation_manager::active_attestation($this->evidenceid);
        $pieces = piece_manager::for_evidence($this->evidenceid);
        $this->setUser($this->manager);
        $this->assertStringContainsString(
            'alert alert-warning py-1',
            $this->before($this->evidence(), $pieces, $standing, null, ''),
            'The premise: the old page said that a piece was added after the signature.'
        );

        foreach (['withdraw', 'request', 'gone', null] as $recourse) {
            $expected = $this->before($this->evidence(), $pieces, $standing, $recourse, '');
            $page = attest_page::signed(
                $this->evidence(),
                $context,
                $pieces,
                $standing,
                attestation_manager::attestations($this->evidenceid),
                $recourse
            );
            $html = $this->render($page);
            $this->assertSame(0, substr_count($html, '<form') - ($recourse === null || $recourse === 'gone' ? 0 : 1));
            $this->assertSame(self::canon($expected), self::canon($html), $recourse ?? 'none');
        }
    }

    /**
     * attest.php itself, played as the site plays it, for the user now set.
     *
     * @return string The page.
     */
    private function page(): string {
        // The page is a script of the site: it reads these as globals.
        global $CFG, $DB, $OUTPUT, $PAGE, $SITE, $USER;

        $_GET = ['evidenceid' => $this->evidenceid];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $PAGE = new \moodle_page();
        ob_start();
        try {
            require_once(__DIR__ . '/../fixtures/plugin_pages.php');
            \tool_vae\fixtures\plugin_pages::run('attest.php');
        } finally {
            $out = ob_get_clean();
            $_GET = [];
        }

        return $out;
    }

    /**
     * The page chooses the way out of a signature in force as it did (the tool of equivalence never shows a signature).
     *
     * The administrator may withdraw; another manager who follows the vaeiste asks the signatory to; the same manager, the
     * signatory suspended, is told nobody can answer; the signatory, who may not withdraw their own here, is given nothing.
     */
    public function test_the_page_chooses_the_way_out_of_a_signature_as_before(): void {
        global $DB;
        $other = $this->getDataGenerator()->create_user();
        $role = $DB->get_field_sql('SELECT roleid FROM {role_assignments} WHERE userid = ?', [$this->manager->id]);
        role_assign((int) $role, $other->id, \context_user::instance($this->candidate->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $standing = $this->attest();

        $this->setAdminUser();
        $html = $this->page();
        $this->assertStringContainsString('/admin/tool/vae/withdraw.php', $html);
        $this->assertStringNotContainsString('withdrawrequest.php', $html);
        // The whole history of the attestations, which the page reads and gives to the class, not only the way out.
        $this->assertStringContainsString('tool-vae-attestations', $html);
        $this->assertStringContainsString('Directeur', $html);

        $this->setUser($other);
        $html = $this->page();
        $this->assertStringContainsString('withdrawrequest.php', $html);
        $this->assertStringContainsString(get_string('requestwithdrawal', 'tool_vae'), $html);
        $this->assertStringNotContainsString('/admin/tool/vae/withdraw.php', $html);

        $this->setUser($this->manager);
        $html = $this->page();
        $this->assertStringContainsString(get_string('errorattestexists', 'tool_vae'), $html);
        $this->assertStringNotContainsString('withdraw.php', $html);
        $this->assertStringNotContainsString(get_string('requestwithdrawalnosignatory', 'tool_vae'), $html);

        $DB->set_field('user', 'suspended', 1, ['id' => $standing->attestantid]);
        \core_user::reset_internal_users();
        $this->setUser($other);
        $html = $this->page();
        $this->assertStringContainsString(get_string('requestwithdrawalnosignatory', 'tool_vae'), $html);
        $this->assertStringNotContainsString('withdraw.php', $html);
    }

    public function test_the_context_holds_the_pieces_of_the_page(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');
        $context = \context_user::instance($this->candidate->id);

        $unsigned = attest_page::unsigned($this->evidence(), $context, [], $this->form([]))->export_for_template($output);
        $this->assertStringContainsString('tool-vae-claim', $unsigned['declarationhtml']);
        $this->assertFalse($unsigned['pieces']);
        $this->assertFalse($unsigned['signed']);
        $this->assertStringContainsString('<form', $unsigned['formhtml']);

        $this->setUser($this->candidate);
        (new piece_manager())->add_link($this->evidenceid, 'diploma', '', 'https://exemple.test/a');
        $standing = $this->attest();
        $pieces = piece_manager::for_evidence($this->evidenceid);
        $signed = attest_page::signed($this->evidence(), $context, $pieces, $standing, [$standing->id => $standing], null)
            ->export_for_template($output);
        $this->assertSame(get_string('attestpiecesintro', 'tool_vae'), $signed['pieces']['intro']);
        $this->assertStringContainsString('tool-vae-pieces', $signed['pieces']['listhtml']);
        $this->assertStringContainsString(get_string('errorattestexists', 'tool_vae'), $signed['signed']['noticehtml']);
        $this->assertFalse($signed['signed']['recoursehtml']);
        $this->assertFalse($signed['formhtml']);
    }

    public function test_only_the_keys_of_html_are_placed_as_they_are(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/attest_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(
            ['declarationhtml', 'headinghtml', 'listhtml', 'noticehtml', 'attestationshtml', 'recoursehtml', 'formhtml'],
            array_values(array_unique($raw[1]))
        );
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $file = __DIR__ . '/../../templates/attest_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/attest_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $this->assertStringContainsString('tool-vae-claim', self::core()->render_from_template('tool_vae/attest_page', $context));
    }
}
