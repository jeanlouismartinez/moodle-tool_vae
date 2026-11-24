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

use tool_vae\form\piece_file_form;
use tool_vae\form\piece_link_form;
use tool_vae\local\attestation_manager;
use tool_vae\local\identity_map;
use tool_vae\local\piece_manager;

/**
 * The page "Pièces justificatives" (piece.php) drawn by tool_vae\output\piece_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration (its code kept here as
 * the expected value, the screens it called kept in the trait presenter_before), for: the vaeiste on a site open to
 * pieces (the list with a button to remove each piece, the two forms of the core, each drawn once); the vaeiste on a site
 * closed to pieces (the notice); a manager who reads (no button, no form, no notice); a declaration with no piece. Also:
 * that the context holds plain texts and the address of the dossier, that the template places as HTML only its keys
 * ...html and renders its example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(piece_page::class)]
final class piece_page_test extends \advanced_testcase {
    use dom_canon;
    use presenter_before;

    /** @var \stdClass The vaeiste. */
    private \stdClass $candidate;

    /** @var \stdClass A manager who follows the vaeiste. */
    private \stdClass $manager;

    /** @var int The declaration. */
    private int $evidenceid;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    /** @var \moodle_url The address of the dossier. */
    private \moodle_url $dossier;

    protected function setUp(): void {
        global $CFG, $DB, $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        require_once($CFG->libdir . '/formslib.php');

        $generator = $this->getDataGenerator();
        $system = \context_system::instance()->id;
        $this->candidate = $generator->create_user();
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

        $this->url = new \moodle_url('/admin/tool/vae/piece.php', ['evidenceid' => $this->evidenceid]);
        $this->dossier = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $this->candidate->id]);
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_user::instance($this->candidate->id));
    }

    /**
     * The page drawn by the renderer of the plugin for the web.
     *
     * @param piece_page $page
     * @return string HTML.
     */
    private function render(piece_page $page): string {
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
     * The two forms of the page, as the page builds them.
     *
     * @return array [piece_file_form, piece_link_form]
     */
    private function forms(): array {
        return [
            new piece_file_form($this->url->out(false), ['evidenceid' => $this->evidenceid, 'maxbytes' => 1048576]),
            new piece_link_form($this->url->out(false), ['evidenceid' => $this->evidenceid]),
        ];
    }

    /**
     * The body of piece.php before its migration, below its heading (its code, kept as the expected value).
     *
     * @param bool $iscandidate
     * @param bool $canadd
     * @return string HTML.
     */
    private function before(bool $iscandidate, bool $canadd): string {
        $output = self::core();
        $context = \context_user::instance($this->candidate->id);
        $html = \html_writer::tag('p', s(get_string('pieceintro', 'tool_vae')));
        $html .= \html_writer::div(s(get_string('piecelimits', 'tool_vae')), 'alert alert-warning');
        $html .= self::before_attest_declaration($this->evidence(), $context);
        $pieces = piece_manager::for_evidence($this->evidenceid);
        $standing = attestation_manager::active_attestation($this->evidenceid);
        $html .= self::before_pieces(
            $pieces,
            $context,
            $standing,
            $iscandidate
                ? fn($piece) => $output->single_button(
                    new \moodle_url($this->url, ['remove' => (int) $piece->id]),
                    get_string('pieceremove', 'tool_vae'),
                    'post'
                )
                : null
        );
        if ($canadd) {
            [$fileform, $linkform] = $this->forms();
            ob_start();
            $fileform->display();
            $linkform->display();
            $html .= ob_get_clean();
        } else if ($iscandidate) {
            $html .= $output->notification(get_string('piececlosed', 'tool_vae'), \core\output\notification::NOTIFY_INFO);
        }

        return $html . \html_writer::tag('p', \html_writer::link($this->dossier, get_string('piecebacktodossier', 'tool_vae')));
    }

    /**
     * The page as piece.php now builds it.
     *
     * @param bool $iscandidate
     * @param bool $canadd
     * @return piece_page
     */
    private function page(bool $iscandidate, bool $canadd): piece_page {
        [$fileform, $linkform] = $canadd ? $this->forms() : [null, null];

        return new piece_page(
            $this->evidence(),
            \context_user::instance($this->candidate->id),
            piece_manager::for_evidence($this->evidenceid),
            attestation_manager::active_attestation($this->evidenceid),
            $iscandidate ? $this->url : null,
            $fileform,
            $linkform,
            !$canadd && $iscandidate,
            $this->dossier
        );
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $page = $this->page(false, false);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/piece_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        foreach (['no piece' => false, 'two pieces' => true] as $name => $withpieces) {
            if ($withpieces) {
                $this->setUser($this->candidate);
                (new piece_manager())->add_link($this->evidenceid, 'diploma', 'Un diplôme', 'https://exemple.test/a');
                $path = make_request_directory() . '/upload';
                file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
                (new piece_manager())->add_file($this->evidenceid, 'contract', '', $path, 'x.pdf');
            }
            $states = [
                'the vaeiste, open' => [$this->candidate, true, true],
                'the vaeiste, closed' => [$this->candidate, true, false],
                'a manager' => [$this->manager, false, false],
            ];
            foreach ($states as $state => [$user, $iscandidate, $canadd]) {
                $this->setUser($user);
                $expected = $this->before($iscandidate, $canadd);
                $html = $this->render($this->page($iscandidate, $canadd));
                $this->assertSame(self::canon(self::drafts($expected)), self::canon(self::drafts($html)), "$name, $state");
            }
        }
    }

    public function test_each_form_is_drawn_once(): void {
        $this->setUser($this->candidate);

        $html = $this->render($this->page(true, true));

        $this->assertSame(1, substr_count($html, '_qf__tool_vae_form_piece_file_form'));
        $this->assertSame(1, substr_count($html, '_qf__tool_vae_form_piece_link_form'));
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->setUser($this->candidate);
        $output = $PAGE->get_renderer('tool_vae');

        $closed = $this->page(true, false)->export_for_template($output);
        $this->assertSame(get_string('pieceintro', 'tool_vae'), $closed['intro']);
        $this->assertSame(get_string('piecelimits', 'tool_vae'), $closed['limits']);
        $this->assertStringContainsString('tool-vae-claim', $closed['declarationhtml']);
        $this->assertStringContainsString(get_string('piecenone', 'tool_vae'), $closed['pieceshtml']);
        $this->assertFalse($closed['filehtml']);
        $this->assertFalse($closed['linkhtml']);
        $this->assertStringContainsString(get_string('piececlosed', 'tool_vae'), $closed['closedhtml']);
        $back = ['url' => $this->dossier->out(false), 'label' => get_string('piecebacktodossier', 'tool_vae')];
        $this->assertSame($back, $closed['back']);

        $open = $this->page(true, true)->export_for_template($output);
        $this->assertStringContainsString('<form', $open['filehtml']);
        $this->assertStringContainsString('<form', $open['linkhtml']);
        $this->assertFalse($open['closedhtml']);
    }

    public function test_only_the_keys_of_html_are_placed_as_they_are(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/piece_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(
            ['declarationhtml', 'pieceshtml', 'filehtml', 'linkhtml', 'closedhtml'],
            array_values(array_unique($raw[1]))
        );
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $file = __DIR__ . '/../../templates/piece_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/piece_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $this->assertStringContainsString('alert-warning', self::core()->render_from_template('tool_vae/piece_page', $context));
    }
}
