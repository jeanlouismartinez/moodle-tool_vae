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

use tool_vae\local\attestation_manager;
use tool_vae\local\identity_map;
use tool_vae\local\piece_manager;

/**
 * The supporting pieces of a declaration, as a list (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the piece tool_vae\output\pieces draws the same DOM as presenter::pieces() drew before its
 * migration (its code kept in the trait presenter_before), for: no piece; a link and a file read by the vaeiste, with the
 * button to remove each (the remove address of piece.php, as the page gave it in a function); the same read by a manager
 * who opened the file and by one who did not; a reader who may only see that a piece exists; a signature in force with a
 * piece added after it and a piece it covered removed since; a title that is the figure 0. Also that the context holds
 * plain texts, that a script in a title or an address comes out escaped, that the template places as HTML only its keys
 * ...html and renders its example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(pieces::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\local\presenter::class)]
final class pieces_test extends \advanced_testcase {
    use dom_canon;
    use presenter_before;

    /** @var \stdClass The vaeiste. */
    private \stdClass $candidate;

    /** @var \stdClass A manager who follows the vaeiste and may attest. */
    private \stdClass $manager;

    /** @var \stdClass A reader who may only read the dossier. */
    private \stdClass $reader;

    /** @var int The declaration. */
    private int $evidenceid;

    protected function setUp(): void {
        global $DB, $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/piece.php'));
        $PAGE->set_context(\context_system::instance());

        $generator = $this->getDataGenerator();
        $system = \context_system::instance()->id;
        $this->candidate = $generator->create_user(['firstname' => 'Camille', 'lastname' => 'Durand']);
        $this->manager = $generator->create_user();
        $this->reader = $generator->create_user();
        $context = \context_user::instance($this->candidate->id)->id;

        $own = $generator->create_role();
        assign_capability('tool/vae:declare', CAP_ALLOW, $own, $system, true);
        assign_capability('tool/vae:viewown', CAP_ALLOW, $own, $system, true);
        role_assign($own, $this->candidate->id, $context);
        $read = $generator->create_role();
        assign_capability('tool/vae:viewassigned', CAP_ALLOW, $read, $system, true);
        set_role_contextlevels($read, [CONTEXT_USER]);
        role_assign($read, $this->reader->id, $context);
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
    }

    /**
     * A piece drawn by the renderer of the plugin for the web.
     *
     * @param \core\output\renderable $piece
     * @return string HTML.
     */
    private function render(\core\output\renderable $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($piece);
    }

    /**
     * A link added by the vaeiste.
     *
     * @param string $title
     * @param string $url
     * @return \stdClass
     */
    private function link(string $title = '', string $url = 'https://exemple.test/a'): \stdClass {
        $this->setUser($this->candidate);

        return (new piece_manager())->add_link($this->evidenceid, 'diploma', $title, $url);
    }

    /**
     * A file added by the vaeiste.
     *
     * @param string $title
     * @return \stdClass
     */
    private function file(string $title = ''): \stdClass {
        $this->setUser($this->candidate);
        $path = make_request_directory() . '/upload';
        file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");

        return (new piece_manager())->add_file($this->evidenceid, 'contract', $title, $path, 'x.pdf');
    }

    /**
     * An attestation recorded by the manager on the pieces there now.
     */
    private function attest(): void {
        $this->setUser($this->manager);
        (new attestation_manager())->attest($this->evidenceid, (object) [
            'attestantrole' => 'Directeur',
            'statement' => 'Je confirme.',
            'piecesseen' => piece_manager::list_token(piece_manager::active_for_evidence($this->evidenceid)),
            'piecesread' => 1,
        ]);
        \core\task\manager::clear_static_caches();
    }

    /**
     * The old list and the new one, for the user now set, compared in canonical form.
     *
     * @param string $name
     * @param \moodle_url|null $remove The address the buttons to remove go to (piece.php for the vaeiste), or none.
     */
    private function assert_same_dom(string $name, ?\moodle_url $remove = null): void {
        $context = \context_user::instance($this->candidate->id);
        $pieces = piece_manager::for_evidence($this->evidenceid);
        $standing = attestation_manager::active_attestation($this->evidenceid);
        $actions = $remove === null ? null : fn($piece) => self::core()->single_button(
            new \moodle_url($remove, ['remove' => (int) $piece->id]),
            get_string('pieceremove', 'tool_vae'),
            'post'
        );

        $expected = self::before_pieces($pieces, $context, $standing ?: null, $actions);
        $html = $this->render(new pieces($pieces, $context, $standing ?: null, $remove));
        $this->assertSame(self::canon($expected), self::canon($html), $name);
    }

    public function test_the_piece_is_a_named_template(): void {
        global $PAGE;
        $piece = new pieces([], \context_user::instance($this->candidate->id));

        $this->assertInstanceOf(\core\output\renderable::class, $piece);
        $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
        $this->assertSame('tool_vae/pieces', $piece->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_no_piece_draws_the_dom_of_before(): void {
        $this->setUser($this->candidate);
        $this->assert_same_dom('no piece');
        $this->setUser($this->manager);
        $this->assert_same_dom('no piece, read by a manager');
    }

    public function test_each_reader_gets_the_dom_of_before(): void {
        $file = $this->file('Un contrat');
        $this->link('Un diplôme');
        $remove = new \moodle_url('/admin/tool/vae/piece.php', ['evidenceid' => $this->evidenceid]);

        $this->setUser($this->candidate);
        $this->assert_same_dom('the vaeiste, with the buttons to remove', $remove);
        $this->assert_same_dom('the vaeiste, without buttons');

        $this->setUser($this->manager);
        $this->assert_same_dom('a manager who opened nothing');
        piece_manager::open((int) $file->id, (int) \context_user::instance($this->candidate->id)->id);
        $this->assert_same_dom('a manager who opened the file');

        $this->setUser($this->reader);
        $this->assert_same_dom('a reader who may only see the list');
    }

    public function test_a_signature_in_force_draws_the_dom_of_before(): void {
        $covered = $this->link('Avant', 'https://exemple.test/avant');
        $this->file();
        $this->attest();
        $this->link('Après', 'https://exemple.test/apres');
        $this->setUser($this->candidate);
        (new piece_manager())->remove((int) $covered->id);
        $remove = new \moodle_url('/admin/tool/vae/piece.php', ['evidenceid' => $this->evidenceid]);

        $this->setUser($this->candidate);
        $this->assert_same_dom('the vaeiste', $remove);
        $this->setUser($this->manager);
        $this->assert_same_dom('the manager');
        $html = $this->render(new pieces(
            piece_manager::for_evidence($this->evidenceid),
            \context_user::instance($this->candidate->id),
            attestation_manager::active_attestation($this->evidenceid)
        ));
        $this->assertStringContainsString('removed since', $html, 'The premise: a covered piece was removed.');
        $this->assertStringContainsString('Added after the signature of', $html, 'The premise: a piece came after.');
    }

    public function test_a_title_that_is_the_figure_0_is_drawn_as_before(): void {
        $this->link('0');
        $this->file('0');
        $this->setUser($this->candidate);
        $this->assert_same_dom('the figure 0');
        $this->assertStringContainsString(' - 0<', $this->render(new pieces(
            piece_manager::for_evidence($this->evidenceid),
            \context_user::instance($this->candidate->id)
        )));
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');
        $this->link('A & B', 'https://exemple.test/a?x=1&y=2');
        $this->setUser($this->candidate);
        $context = \context_user::instance($this->candidate->id);

        $none = (new pieces([], $context))->export_for_template($output);
        $this->assertSame(get_string('piecenone', 'tool_vae'), $none['none']);
        $this->assertFalse($none['haspieces']);

        $exported = (new pieces(piece_manager::for_evidence($this->evidenceid), $context))->export_for_template($output);
        $this->assertFalse($exported['none']);
        $this->assertTrue($exported['haspieces']);
        $piece = $exported['pieces'][0];
        $this->assertSame(get_string('piecenature:diploma', 'tool_vae'), $piece['nature']);
        $this->assertSame(['text' => 'A & B'], $piece['title']);
        $this->assertFalse($piece['removed']);
        $this->assertSame('https://exemple.test/a?x=1&y=2', $piece['active']['link']['url']);
        $this->assertFalse($piece['active']['file']);
        $this->assertFalse($piece['active']['actionhtml']);
        $this->assertFalse($exported['count'], 'The vaeiste is not told what they opened.');
    }

    public function test_a_script_in_a_title_or_an_address_comes_out_escaped(): void {
        $this->link('<script>alert(1)</script>', 'https://exemple.test/<script>alert(2)</script>');
        $this->setUser($this->manager);

        $context = \context_user::instance($this->candidate->id);
        $html = $this->render(new pieces(piece_manager::for_evidence($this->evidenceid), $context));

        // The title is cleaned of its tags when it is recorded (piece_manager); the address is kept whole, and escaped.
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_only_the_keys_of_html_are_placed_as_they_are(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/pieces.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['actionhtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $file = __DIR__ . '/../../templates/pieces.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/pieces', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $this->assertStringContainsString('tool-vae-pieces', self::core()->render_from_template('tool_vae/pieces', $context));
    }
}
