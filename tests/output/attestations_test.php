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

/**
 * What the dossier says of the acts on a declaration: its attestations, its state, the legend, its chronology, its carries.
 *
 * WHAT IS ASSERTED (lot 7, MMR-520 ticket 4). That the pieces attestations, state_label, state_legend, chronology and
 * carry_notes draw the same DOM as presenter::attestations(), state_label(), state_legend(), chronology_html() and
 * carry_notes() drew before their migration (their code kept in the trait presenter_before): no attestation, one in force
 * with a statement of several lines (each kind of line break), one imported, one withdrawn with and without one in force
 * after it; each state, with and without a question waiting; a carry each way, decided by a named person or by nobody.
 * That the text of the attestations is the same, line breaks included, as the tests of the dossier read it; that a
 * statement of several lines and a figure 0 are kept; that a script in a role, a statement or a name comes out escaped;
 * that the templates place nothing as HTML and render their examples.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(attestations::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(state_label::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(state_legend::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(chronology::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(carry_notes::class)]
final class attestations_test extends \advanced_testcase {
    use dom_canon;
    use presenter_before;

    /** @var string[] The templates of this test. */
    private const TEMPLATES = ['attestations', 'state_label', 'state_legend', 'chronology', 'carry_notes'];

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/dossier.php'));
        $PAGE->set_context(\context_system::instance());
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
     * An attestation row, as much of it as the pieces read.
     *
     * @param int $attestedat
     * @param int|null $withdrawnat
     * @param array $fields
     * @return \stdClass
     */
    private static function attestation(int $attestedat, ?int $withdrawnat = null, array $fields = []): \stdClass {
        return (object) ($fields + [
            'attestedat' => $attestedat,
            'timecreated' => $attestedat,
            'withdrawnat' => $withdrawnat,
            'withdrawnreason' => $withdrawnat === null ? null : 'rgpd',
            'attestantrole' => 'Directrice',
            'statement' => 'Je confirme.',
            'importedat' => null,
        ]);
    }

    /**
     * The lists of attestations of the cases.
     *
     * @return array[] By name.
     */
    private static function lists(): array {
        return [
            'none' => [],
            'in force, several lines' => [7 => self::attestation(1781136000, null, [
                'statement' => "Première ligne.\nDeuxième.\r\nTroisième.\rQuatrième.\n\rCinquième.\n\nAprès une ligne vide.",
            ])],
            'imported' => [3 => self::attestation(1781136000, null, ['importedat' => 1782172800])],
            'withdrawn, nothing after' => [4 => self::attestation(1781136000, 1782172800)],
            'withdrawn, then in force' => [4 => self::attestation(1781136000, 1782172800), 9 => self::attestation(1783000000)],
            'two withdrawn' => [
                4 => self::attestation(1781136000, 1782172800),
                5 => self::attestation(1783000000, 1784000000, ['withdrawnreason' => 'erreur']),
            ],
            'the figure 0' => [0 => self::attestation(1781136000, null, ['attestantrole' => '0', 'statement' => '0'])],
        ];
    }

    public function test_the_pieces_are_named_templates(): void {
        global $PAGE;
        $renderer = $PAGE->get_renderer('tool_vae');
        $pieces = [
            'tool_vae/attestations' => new attestations([]),
            'tool_vae/state_label' => new state_label([]),
            'tool_vae/state_legend' => new state_legend(),
            'tool_vae/chronology' => new chronology((object) ['timecreated' => 100], [], []),
            'tool_vae/carry_notes' => new carry_notes((object) ['carriedto' => null, 'carriedfrom' => null]),
        ];
        foreach ($pieces as $name => $piece) {
            $this->assertInstanceOf(\core\output\renderable::class, $piece);
            $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
            $this->assertSame($name, $piece->get_template_name($renderer));
        }
    }

    public function test_the_attestations_draw_the_dom_and_the_text_of_before(): void {
        foreach (self::lists() as $name => $list) {
            $expected = self::before_attestations($list);
            $html = $this->render(new attestations($list));
            $this->assertSame(self::canon($expected), self::canon($html), $name);
            // The tests of the dossier read it as text, line breaks included: the same text.
            $this->assertSame(html_entity_decode(strip_tags($expected)), html_entity_decode(strip_tags($html)), $name);
        }
    }

    public function test_the_lines_of_a_statement_are_kept_as_nl2br_kept_them(): void {
        $list = self::lists()['in force, several lines'];

        $html = $this->render(new attestations($list));

        // The breaks of the statement stay where nl2br() put them, before each end of line, the end of line kept.
        $this->assertStringContainsString(
            "Première ligne.<br>\nDeuxième.<br>\r\nTroisième.<br>\rQuatrième.<br>\n\rCinquième.",
            $html
        );
        $this->assertStringContainsString("Cinquième.<br>\n<br>\nAprès une ligne vide.", $html);
    }

    public function test_the_state_and_the_legend_draw_the_dom_of_before(): void {
        foreach (self::lists() as $name => $list) {
            foreach ([0, 1, 3] as $open) {
                $this->assertSame(
                    self::canon(self::before_state_label($list, $open)),
                    self::canon($this->render(new state_label($list, $open))),
                    "$name, $open open"
                );
            }
        }
        // Between the two words, the space of before: it is drawn.
        $this->assertStringContainsString('</span> <span', $this->render(new state_label([], 2)));
        $this->assertSame(self::canon(self::before_state_legend()), self::canon($this->render(new state_legend())));
        $this->assertSame(
            html_entity_decode(strip_tags(self::before_state_legend())),
            html_entity_decode(strip_tags($this->render(new state_legend())))
        );
    }

    public function test_the_chronology_draws_the_dom_of_before(): void {
        $evidence = (object) ['timecreated' => 1781136000];
        $requests = [
            (object) ['timecreated' => 1781200000, 'timeanswered' => 1781300000],
            (object) ['timecreated' => 1781400000, 'timeanswered' => null],
        ];
        $carries = [['time' => 1781500000, 'act' => 'carriedto'], ['time' => 1781600000, 'act' => 'carriedfrom']];
        foreach (self::lists() as $name => $list) {
            foreach (['no carry' => [], 'carries' => $carries] as $with => $acts) {
                $expected = self::before_chronology_html($evidence, $list, $requests, $acts);
                $html = $this->render(new chronology($evidence, $list, $requests, $acts));
                $this->assertSame(self::canon($expected), self::canon($html), "$name, $with");
                $this->assertSame(html_entity_decode(strip_tags($expected)), html_entity_decode(strip_tags($html)), "$name, $with");
            }
        }
    }

    public function test_the_carries_draw_the_dom_of_before(): void {
        $row = fn(string $record, int $decidedat, string $name) => (object) [
            'record' => $record, 'carriedat' => 1781136000, 'decidedat' => $decidedat, 'decidername' => $name,
        ];
        $cases = [
            'none' => (object) ['carriedto' => null, 'carriedfrom' => null],
            'to, by a person' => (object) ['carriedto' => $row('RS6000', 1781000000, 'Hugo Bernard'), 'carriedfrom' => null],
            'from, by nobody' => (object) ['carriedto' => null, 'carriedfrom' => $row('RS5000', 1781000000, '')],
            'both, undecided' => (object) ['carriedto' => $row('RS6000', 0, ''), 'carriedfrom' => $row('RS5000', 0, 'X')],
            'the figure 0' => (object) ['carriedto' => $row('0', 1781000000, '0'), 'carriedfrom' => null],
            'an ampersand' => (object) ['carriedto' => $row('A&B', 1781000000, 'Jean & Marie'), 'carriedfrom' => null],
        ];
        foreach ($cases as $name => $notes) {
            $this->assertSame(
                self::canon(self::before_carry_notes($notes)),
                self::canon($this->render(new carry_notes($notes))),
                $name
            );
        }
        $this->assertSame('', $this->render(new carry_notes($cases['none'])));
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');

        $none = (new attestations([]))->export_for_template($output);
        $this->assertSame(get_string('noattestationyet', 'tool_vae'), $none['none']);
        $this->assertFalse($none['hasitems']);
        $list = (new attestations([1 => self::attestation(1781136000, null, ['attestantrole' => 'A & B'])]))
            ->export_for_template($output);
        $this->assertFalse($list['none']);
        $this->assertSame('A & B', $list['items'][0]['signed']['role']);
        $this->assertSame([['br' => false, 'text' => 'Je confirme.']], $list['items'][0]['signed']['lines']);
        $this->assertFalse($list['items'][0]['withdrawn']);

        $state = (new state_label([], 1))->export_for_template($output);
        $this->assertSame(get_string('state:declared', 'tool_vae'), $state['state']);
        $this->assertSame(['text' => get_string('state:questionopen', 'tool_vae')], $state['question']);

        $carry = (new carry_notes((object) ['carriedto' => (object) ['record' => 'A & B', 'carriedat' => 1781136000,
            'decidedat' => 0, 'decidername' => ''], 'carriedfrom' => null]))->export_for_template($output);
        $this->assertStringContainsString('A & B', $carry['notes'][0]['text']);
    }

    public function test_a_script_in_a_role_a_statement_or_a_name_comes_out_escaped(): void {
        $script = '<script>alert(1)</script>';
        $html = $this->render(new attestations([1 => self::attestation(1781136000, null, [
            'attestantrole' => $script,
            'statement' => $script . "\n" . $script,
        ])]));
        $html .= $this->render(new carry_notes((object) ['carriedto' => (object) ['record' => $script, 'carriedat' => 1,
            'decidedat' => 1, 'decidername' => $script], 'carriedfrom' => null]));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_templates_place_nothing_as_html_and_render_their_example(): void {
        foreach (self::TEMPLATES as $name) {
            $file = __DIR__ . '/../../templates/' . $name . '.mustache';
            $this->assertFileExists($file);
            $source = file_get_contents($file);
            $this->assertStringNotContainsString('{{{', $source, $name);
            $this->assertStringContainsString('@template tool_vae/' . $name, $source);
            $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches), $name);
            $context = json_decode($matches[1]);
            $this->assertIsObject($context, $name . ' ' . json_last_error_msg());

            $this->assertNotSame('', self::core()->render_from_template('tool_vae/' . $name, $context), $name);
        }
    }
}
