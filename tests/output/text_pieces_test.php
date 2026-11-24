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
require_once(__DIR__ . '/forms_before.php');

use tool_vae\local\dossier_importer;
use tool_vae\local\exporter;
use tool_vae\local\text_diff;

/**
 * The HTML the exporter, the importer of a dossier, the notifier and the comparison of two versions wrote, drawn by
 * templates (lot 7, MMR-520 ticket 4, wave 4c).
 *
 * WHAT IS ASSERTED. That each piece writes, byte for byte, what the old code wrote (kept in the trait forms_before, copied
 * by script): the lines an export left out under their sentence (export_warnings), the list of the competencies an archive
 * numbers otherwise, as the refusal carries it (text_list, through dossier_importer::describe_held()), the body in HTML of
 * a message (message_body), the block of a comparison (compare_diff), white space at its edges included. Byte for byte and
 * not only in DOM: the list goes into a sentence of the language file, the body into a message, and the comparison into a
 * block that keeps white space. Also: the context, the escaping, the figure 0, the example contexts.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(export_warnings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(text_list::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(message_body::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(compare_diff::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(dossier_importer::class)]
final class text_pieces_test extends \advanced_testcase {
    use dom_canon;
    use forms_before;

    /** @var string Escaped once, and not twice: an ampersand, quotes and a tag. */
    private const ODD = 'Soins & "aide" <script>alert(1)</script>';

    /**
     * A piece drawn by the renderer of the plugin, as the code draws it.
     *
     * @param \core\output\renderable $piece
     * @return string HTML.
     */
    private static function draw(\core\output\renderable $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae')->render($piece);
    }

    /**
     * A warning as exporter::warnings() gives it.
     *
     * @param string $declaration
     * @param string|null $value
     * @param string $field
     * @return array
     */
    private static function warning(string $declaration, ?string $value, string $field = '/fiche/rang'): array {
        return ['declaration' => $declaration, 'fiche' => 'RNCP36832', 'file' => 'declarations/1.json', 'field' => $field,
            'value' => $value];
    }

    public function test_the_lines_left_out_are_written_as_before(): void {
        $many = [];
        for ($i = 1; $i <= exporter::WARNINGS_LISTED + 2; $i++) {
            $many[] = self::warning('RNCP36832-C' . $i, 'v' . $i);
        }
        $cases = [
            'one line, markup in the value and the lead' => [[self::warning('RNCP36832-<b>x', '<b>x')], self::ODD],
            'a value missing, a value empty, a field empty, a line of a file' => [[
                self::warning('RNCP36832-C1', null),
                self::warning('RNCP36832-C2', ''),
                self::warning('RNCP36832-C3', 'x', ''),
                self::warning('', 'x'),
            ], 'Lead'],
            'more lines than are listed' => [$many, 'Lead'],
            'no line, the figure 0' => [[], '0'],
            'a value and a lead that are the figure 0' => [[self::warning('0', '0')], '0'],
        ];
        foreach ($cases as $name => [$warnings, $lead]) {
            $this->assertSame(
                self::before_exporter_warnings_html($warnings, $lead),
                self::draw(new export_warnings($warnings, $lead)),
                $name
            );
        }
        $html = self::draw(new export_warnings($cases['one line, markup in the value and the lead'][0], self::ODD));
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertNotSame(s(self::ODD), s(s(self::ODD)));
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertSame(exporter::WARNINGS_LISTED + 1, substr_count(self::draw(new export_warnings($many, 'L')), '<li>'));
    }

    public function test_the_competencies_an_archive_numbers_otherwise_are_listed_as_before(): void {
        $this->assertSame(
            10,
            (new \ReflectionClassConstant(dossier_importer::class, 'HELD_LISTED'))->getValue(),
            'The trait writes the number of competencies listed as a figure.'
        );
        $this->assertSame(
            100,
            (new \ReflectionClassConstant(dossier_importer::class, 'HELD_LABEL_LENGTH'))->getValue(),
            'The trait writes the length of a label as a figure.'
        );
        $describe = new \ReflectionMethod(dossier_importer::class, 'describe_held');
        $held = fn(string $name, string $label): \stdClass => (object) ['name' => $name, 'label' => $label];
        $twelve = [];
        foreach ([10, 2, 1, 12, 3, 11, 4, 9, 5, 8, 6, 7] as $rank) {
            $twelve[] = $held('RNCP1-BC' . $rank, 'Bloc ' . $rank);
        }
        $cases = [
            'one, with its label' => [$held('RNCP36832-C03', 'Accompagner')],
            // The code alone is listed when there is no label: one with markup, an ampersand and quotes (mutant di-name-escaped).
            'one with no label, its code with markup' => [$held('RNCP36832-<b>&"x"', '   ')],
            'a label with markup, quotes and lines, too long' => [
                $held('RNCP36832-C04', self::ODD . "\n" . str_repeat('é', 120)),
                $held('RNCP36832-<i>', 'x'),
            ],
            'twelve, in the order of their codes, two not listed' => $twelve,
            'a code and a label that are the figure 0' => [$held('0', '0')],
        ];
        foreach ($cases as $name => $list) {
            $before = self::before_dossier_importer_describe_held($list);
            $after = $describe->invoke(null, $list);
            $this->assertSame($before->count, $after->count, $name);
            $this->assertSame($before->list, $after->list, $name);
        }
        $after = $describe->invoke(null, $twelve);
        $this->assertLessThan(strpos($after->list, 'BC10'), strpos($after->list, 'BC2 '), 'The natural order of the codes.');
        $this->assertStringContainsString(get_string('errorarchivecompetencyheldmore', 'tool_vae', 2), $after->list);
        $bare = $describe->invoke(null, $cases['one with no label, its code with markup'])->list;
        $this->assertStringContainsString('<li>' . s('RNCP36832-<b>&"x"') . '</li>', $bare, 'The code alone, escaped once.');
        $this->assertNotSame(s('RNCP36832-<b>&"x"'), s(s('RNCP36832-<b>&"x"')));
        $odd = $describe->invoke(null, $cases['a label with markup, quotes and lines, too long'])->list;
        $this->assertStringNotContainsString('<script', $odd);
        $this->assertStringNotContainsString('<i>', $odd);
        $this->assertStringNotContainsString('&amp;amp;', $odd);
    }

    public function test_the_body_of_a_message_is_written_as_before(): void {
        $dossier = '/admin/tool/vae/dossier.php';
        $cases = [
            'a sentence with markup and quotes' => [self::ODD, new \moodle_url($dossier, ['userid' => 5]), 'Open'],
            'an address with two parameters' => ['B', new \moodle_url($dossier, ['userid' => 5, 'x' => 'a&b']), 'Open & "read"'],
            'the figure 0' => ['0', new \moodle_url($dossier, ['userid' => 0]), '0'],
        ];
        foreach ($cases as $name => [$body, $url, $linkname]) {
            $this->assertSame(
                self::before_notifier_send_html($body, $url, $linkname),
                self::draw(new message_body($body, $url, $linkname)),
                $name
            );
        }
        $html = self::draw(new message_body(self::ODD, $cases['the figure 0'][1], '<script>alert(2)</script>'));
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $html);
    }

    public function test_the_block_of_a_comparison_is_written_as_before_white_space_at_its_edges_included(): void {
        $cases = [
            'two versions that differ' => text_diff::diff(
                "Je prépare les repas.\nJe range.",
                "Je prépare les repas du soir.\nJe range la cuisine."
            ),
            'a text that begins and ends with white space' => text_diff::diff("  début\n", "\n début  \n\n"),
            'nothing but white space' => text_diff::diff(' ', "\n"),
            'markup and quotes' => text_diff::diff('a', 'a ' . self::ODD),
            'two empty versions' => text_diff::diff('', ''),
            'the figure 0' => text_diff::diff('0', '0'),
            'a text that becomes 0' => text_diff::diff('1', '0'),
            'no operation' => [],
        ];
        foreach ($cases as $name => $ops) {
            $this->assertSame(
                '<div class="tool-vae-diff border rounded p-3" style="white-space: pre-wrap;">'
                    . self::before_text_diff_html($ops) . '</div>',
                self::draw(new compare_diff($ops)),
                $name
            );
        }
        $edges = self::draw(new compare_diff($cases['a text that begins and ends with white space']));
        $this->assertStringContainsString(
            s(get_string('diffremoved', 'tool_vae')) . ' </span>  </del>',
            $edges,
            'The white space of the first run, at the start of the text, is kept.'
        );
        $this->assertStringEndsWith("\n\n</ins></div>", $edges, 'The white space at the end of the text is kept.');
        $kinds = array_unique(array_column($cases['two versions that differ'], 'op'));
        $this->assertContains(text_diff::INSERT, $kinds);
        $this->assertContains(text_diff::EQUAL, $kinds);
        $this->assertContains(text_diff::DELETE, array_column(text_diff::diff('abc def', 'abc'), 'op'));
        $this->assertStringNotContainsString('<script', self::draw(new compare_diff($cases['markup and quotes'])));
    }

    public function test_the_contexts_hold_plain_texts(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');
        $warnings = [self::warning('RNCP36832-<b>x', '<b>x')];

        $this->assertSame(
            ['lead' => 'Lead <i>', 'lines' => [['text' => exporter::describe_warnings($warnings)[0]]]],
            (new export_warnings($warnings, 'Lead <i>'))->export_for_template($output)
        );
        $list = (new text_list(['x' => '<a>', 'y' => '0']))->export_for_template($output);
        $this->assertSame(['lines' => [['text' => '<a>'], ['text' => '0']]], $list);
        $url = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => 5, 'x' => 'a']);
        $body = (new message_body('<b>', $url, 'L'))->export_for_template($output);
        $this->assertSame(['body' => '<b>', 'url' => $url->out(false), 'linkname' => 'L'], $body);
        $diff = (new compare_diff(text_diff::diff('a', 'b')))->export_for_template($output);
        $this->assertSame(get_string('diffadded', 'tool_vae'), $diff['added']);
        $this->assertSame(get_string('diffremoved', 'tool_vae'), $diff['removed']);
        foreach ($diff['runs'] as $run) {
            $this->assertSame(1, (int) $run['equal'] + (int) $run['insert'] + (int) $run['remove'], 'One kind per run.');
        }
    }

    public function test_the_templates_render_their_example_context_and_place_nothing_raw(): void {
        foreach (['export_warnings', 'text_list', 'message_body', 'compare_diff'] as $name) {
            $file = __DIR__ . '/../../templates/' . $name . '.mustache';
            $this->assertFileExists($file);
            $source = file_get_contents($file);
            $this->assertStringContainsString('@template tool_vae/' . $name, $source);
            $this->assertStringNotContainsString('{{{', $source, $name);
            $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches), $name);
            $context = json_decode($matches[1]);
            $this->assertIsObject($context, $name . ' ' . json_last_error_msg());
            $this->assertNotSame('', self::core()->render_from_template('tool_vae/' . $name, $context), $name);
        }
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');
        $this->assertSame('tool_vae/export_warnings', (new export_warnings([], ''))->get_template_name($output));
        $this->assertSame('tool_vae/text_list', (new text_list([]))->get_template_name($output));
        $this->assertSame('tool_vae/message_body', (new message_body('', new \moodle_url('/'), ''))->get_template_name($output));
        $this->assertSame('tool_vae/compare_diff', (new compare_diff([]))->get_template_name($output));
    }
}
