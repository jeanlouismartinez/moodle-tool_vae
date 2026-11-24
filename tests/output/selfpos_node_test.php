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

use tool_vae\local\competency_gateway;
use tool_vae\local\selfpos_manager;
use tool_vae\local\selfpos_tree;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/positioning_before.php');

/**
 * One node of the self-positioning, drawn by a piece (lot 7, MMR-520 ticket 4, wave 3a).
 *
 * WHAT IS ASSERTED. That the piece selfpos_node draws the same DOM as selfpos_screen::node() (with history() and line()) drew
 * before its migration (its code kept in the trait positioning_before), for each value of each parameter that changes the
 * drawing, each with a premise: a node that can be positioned on or not; the form given and the reader who writes, the
 * form given to a reader, no form; the word of the structure absent, an evaluation, a certification; declarations or none;
 * the lines of the record before; no line, one line, three lines (a withdrawal among them), a line restored from an archive;
 * a justification empty, the figure 0, in Markdown, with a script; the vaeiste and a reader. That the context holds plain
 * texts; that the template places HTML only under the keys named and renders its example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(selfpos_node::class)]
final class selfpos_node_test extends \advanced_testcase {
    use dom_canon;
    use positioning_before;

    /** @var \stdClass The vaeiste. */
    private $vaeiste;

    /** @var \stdClass Follows the vaeiste. */
    private $manager;

    /** @var \tool_vae_generator */
    private $generator;

    /** @var string A form, as the page gives it. */
    private const FORM = '<form class="tool-vae-selfpos-form" action="#" method="post"><p>Le formulaire.</p></form>';

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/selfposition.php'));
        $PAGE->set_context(\context_system::instance());

        $datagenerator = $this->getDataGenerator();
        $this->generator = $datagenerator->get_plugin_generator('tool_vae');
        $this->generator->create_source(['fichecode' => 'RNCP400', 'ficherang' => 'D1', 'shortname' => 'Domaine & "un"']);
        $this->generator->create_competency([
            'fichecode' => 'RNCP400', 'ficherang' => 'C11', 'shortname' => 'Noeud & "C11"', 'parent' => 'D1',
        ]);

        $system = \context_system::instance()->id;
        $this->vaeiste = $datagenerator->create_user();
        $this->manager = $datagenerator->create_user();
        $own = $datagenerator->create_role();
        foreach (['declare', 'viewown', 'positionself'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $own, $system, true);
        }
        set_role_contextlevels($own, [CONTEXT_USER]);
        role_assign($own, $this->vaeiste->id, \context_user::instance($this->vaeiste->id)->id);
        $follow = $datagenerator->create_role();
        assign_capability('tool/vae:viewassigned', CAP_ALLOW, $follow, $system, true);
        set_role_contextlevels($follow, [CONTEXT_USER]);
        role_assign($follow, $this->manager->id, \context_user::instance($this->vaeiste->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
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
     * The lines of the node C11, written by the vaeiste through the manager (and one restored, as a restore writes it).
     *
     * @return \stdClass[] Every line, the earliest first.
     */
    private function lines(): array {
        global $DB;
        $id = (int) $this->vaeiste->id;
        $write = fn(int $level, string $text, int $format = FORMAT_PLAIN) => $this->generator->create_selfpos([
            'userid' => $id, 'fichecode' => 'RNCP400', 'ficherang' => 'C11', 'level' => $level, 'justification' => $text,
            'format' => $format,
        ]);
        $write(1, '');
        $write(2, '0');
        $write(3, "**Trois** semaines\n\n- seul", FORMAT_MARKDOWN);
        $write(0, '<script>alert(1)</script> & "retrait"');
        $restored = $write(4, 'Restauree.');
        $DB->set_field('tool_vae_selfpos', 'importedat', 1760000000, ['id' => $restored->id]);
        $this->setUser($this->vaeiste);

        return (new selfpos_manager())->lines($id, 'RNCP400', 'C11');
    }

    /**
     * The states: name => [node rank, lines (all, one, none), regard, successor, canwrite, formhtml].
     *
     * @return array[]
     */
    private function states(): array {
        $all = $this->lines();
        $structure = fn(string $kind, string $status) => (object) ['kind' => $kind, 'status' => $status, 'time' => 1762000000];
        $none = (object) ['structure' => null, 'declarations' => 0, 'attested' => 0, 'pieces' => 0];
        $evaluated = (object) ['structure' => $structure('eval', 'sufficient'), 'declarations' => 2, 'attested' => 1,
            'pieces' => 3];
        $certified = (object) ['structure' => $structure('certif', 'certified'), 'declarations' => 0, 'attested' => 0,
            'pieces' => 0];
        $successor = [
            (object) ['fromcode' => 'RNCP90', 'fromrank' => 'C01', 'levellabel' => 'Je decouvre', 'time' => 1750000000],
            (object) ['fromcode' => 'RNCP90', 'fromrank' => 'C02', 'levellabel' => '0', 'time' => 1750000100],
        ];

        return [
            'the vaeiste writes, every line' => ['C11', $all, $evaluated, $successor, true, self::FORM],
            'the vaeiste writes, no form given' => ['C11', $all, $certified, [], true, ''],
            'a reader with a form given' => ['C11', array_slice($all, 0, 1), $none, $successor, false, self::FORM],
            'a reader, no line' => ['C11', [], $none, [], false, ''],
            'a node that cannot be positioned on' => ['AS-1-1', [], $none, [], true, self::FORM],
        ];
    }

    public function test_the_piece_is_a_named_template(): void {
        global $PAGE;
        $this->setUser($this->vaeiste);
        $node = selfpos_tree::node(selfpos_tree::for_record('RNCP400'), 'C11');
        $regard = (object) ['structure' => null, 'declarations' => 0, 'attested' => 0, 'pieces' => 0];
        $piece = new selfpos_node((int) $this->vaeiste->id, 'RNCP400', $node, [], $regard, [], true);

        $this->assertInstanceOf(\core\output\renderable::class, $piece);
        $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
        $this->assertSame('tool_vae/selfpos_node', $piece->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->setAdminUser();
        $gateway = new competency_gateway();
        $gateway->create_competency((int) $gateway->find_framework('RNCP400'), 'AS-1-1', 'Un code a tirets');
        $id = (int) $this->vaeiste->id;
        $states = $this->states();

        foreach (['the vaeiste' => $this->vaeiste, 'a reader' => $this->manager] as $who => $reader) {
            $this->setUser($reader);
            $tree = selfpos_tree::for_record('RNCP400');
            foreach ($states as $name => [$rank, $lines, $regard, $successor, $canwrite, $formhtml]) {
                $node = selfpos_tree::node($tree, $rank);
                $before = self::before_selfpos_node($id, 'RNCP400', $node, $lines, $regard, $successor, $canwrite, $formhtml);
                $after = $this->render(new selfpos_node($id, 'RNCP400', $node, $lines, $regard, $successor, $canwrite, $formhtml));
                $this->assertSame(self::canon($before), self::canon($after), "$who: $name");

                // Premises: what each parameter changes is drawn by the old code.
                $this->assertSame(
                    $node->positionable && $canwrite && $formhtml !== '',
                    str_contains($before, 'tool-vae-selfpos-form'),
                    "$who: $name: the form"
                );
                $explain = s(get_string('selfposexplainnotpositionable', 'tool_vae'));
                $this->assertSame(!$node->positionable, str_contains($before, $explain), "$who: $name");
                $nothing = s(get_string('selfposregardnone', 'tool_vae'));
                $this->assertSame($regard->structure === null, str_contains($before, $nothing), "$who: $name");
                $this->assertSame($regard->declarations > 0, str_contains($before, '<p class="small">'), "$who: $name");
                $this->assertSame(count($successor), substr_count($before, 'RNCP90'), "$who: $name");
                $this->assertSame($lines === [], str_contains($before, s(get_string('selfposnoline', 'tool_vae'))));
                $this->assertSame(count($lines) > 1, str_contains($before, s(get_string('selfposearlier', 'tool_vae'))));
            }
            $visible = $reader === $this->vaeiste ? 'selfposvisible' : 'selfposvisibleread';
            $this->assertStringContainsString(s(get_string($visible, 'tool_vae')), $before, $who);
        }

        // Premises on the lines: each justification is drawn as before.
        $this->setUser($this->vaeiste);
        [$rank, $lines, $regard, $successor] = $states['the vaeiste writes, every line'];
        $node = selfpos_tree::node(selfpos_tree::for_record('RNCP400'), $rank);
        $before = self::before_selfpos_node($id, 'RNCP400', $node, $lines, $regard, $successor, true, self::FORM);
        $this->assertSame(5, substr_count($before, '<div class="ms-3 mb-2">'), 'Five lines.');
        $this->assertSame(1, substr_count($before, s(get_string('selfposearlier', 'tool_vae'))), 'One heading for four.');
        $this->assertStringContainsString(s(get_string('selfposrestored', 'tool_vae')), $before, 'The restored line.');
        $this->assertStringContainsString('<strong>Trois</strong>', $before, 'Markdown.');
        $this->assertStringContainsString('<div>0</div>', $before, 'A justification that is 0.');
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $before);
        $this->assertSame(4, substr_count($before, '<div>'), 'Four justifications drawn; the empty one is not.');
        $this->assertStringContainsString(get_string('selfposwithdrawn', 'tool_vae'), $before);
        $this->assertStringContainsString(s(get_string('selfposregardline', 'tool_vae', (object) [
            'status' => get_string('evalstatussufficient', 'tool_vae'),
            'when' => userdate(1762000000, get_string('strftimedate', 'langconfig')),
        ])), $before);
        $after = $this->render(new selfpos_node($id, 'RNCP400', $node, $lines, $regard, $successor, true, self::FORM));
        $this->assertStringNotContainsString('<script', $after);
        $this->assertStringContainsString('Noeud &amp; &quot;C11&quot;', $after);
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->setUser($this->vaeiste);
        $node = selfpos_tree::node(selfpos_tree::for_record('RNCP400'), 'D1');
        $regard = (object) ['structure' => null, 'declarations' => 0, 'attested' => 0, 'pieces' => 0];

        $exported = (new selfpos_node((int) $this->vaeiste->id, 'RNCP400', $node, [], $regard, [], false, self::FORM))
            ->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertSame('Domaine & "un"', $exported['label']);
        $this->assertSame('RNCP400', $exported['fichecode']);
        $this->assertSame('', $exported['formhtml'], 'A reader gets no form.');
        $this->assertSame(['text' => get_string('selfposregardnone', 'tool_vae')], $exported['regardnone']);
        $this->assertNull($exported['regardline']);
        $this->assertSame(['none' => ['text' => get_string('selfposnoline', 'tool_vae')], 'lines' => []], $exported['history']);
    }

    public function test_the_template_places_html_only_under_the_keys_named_and_renders_its_example(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/selfpos_node.mustache');
        $this->assertStringContainsString('@template tool_vae/selfpos_node', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $placed);
        $this->assertSame(['disclaimerhtml', 'formhtml', 'justificationhtml'], array_values(array_unique($placed[1])));
        $this->assertSame(substr_count($source, '{{{'), count($placed[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/selfpos_node', $context);
        $this->assertStringContainsString('tool-vae-history', $html);
    }
}
