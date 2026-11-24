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

/**
 * The piece tool_vae\output\cell_content: what a cell of a table holds, drawn by a template (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That each part draws what the pages drew with the writer of the core (a text escaped once, a link, a
 * span or a block with its classes, HTML of the core placed as it is), side by side with no space between them; that a
 * script in a text, a label or an address comes out escaped; that a part of an unknown kind fails in words; that the context
 * holds plain texts; that the template renders its example context and places as HTML only the key html.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cell_content::class)]
final class cell_content_test extends \advanced_testcase {
    use dom_canon;

    /**
     * The parts drawn by the renderer of the plugin.
     *
     * @param array[] $parts
     * @return string HTML.
     */
    private function render(array $parts): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render(new cell_content($parts));
    }

    public function test_the_piece_is_a_named_template(): void {
        global $PAGE;

        $piece = new cell_content([]);

        $this->assertInstanceOf(\core\output\renderable::class, $piece);
        $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
        $this->assertSame('tool_vae/cell_content', $piece->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_part_draws_what_the_writer_of_the_core_drew(): void {
        $this->resetAfterTest();
        $url = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => 3, 'fichecode' => 'RNCP1']);
        $button = self::core()->single_button(new \moodle_url('/admin/tool/vae/refs.php', ['remove' => 2]), 'Retirer', 'post');

        $cases = [
            [[cell_content::text("L'été & co")], s("L'été & co")],
            [[cell_content::link($url, 'Camille Durand')], \html_writer::link($url, s('Camille Durand'))],
            [[cell_content::span('small text-muted', 'Lecture seule')],
                \html_writer::tag('span', s('Lecture seule'), ['class' => 'small text-muted'])],
            [[cell_content::text('Vaeiste'), cell_content::div('small text-muted', 'Prépare son dossier.')],
                s('Vaeiste') . \html_writer::div(s('Prépare son dossier.'), 'small text-muted')],
            [[cell_content::text('DEMO — '), cell_content::html(format_string('Démo <b>x</b>'))],
                s('DEMO') . ' — ' . format_string('Démo <b>x</b>')],
            [[cell_content::html($button)], $button],
        ];
        foreach ($cases as $i => [$parts, $expected]) {
            $html = $this->render($parts);
            $this->assertSame(self::canon($expected), self::canon($html), 'case ' . $i);
        }
        // Side by side: no space is drawn between two parts.
        $this->assertSame('a<span class="b">c</span>', $this->render([cell_content::text('a'), cell_content::span('b', 'c')]));
    }

    public function test_a_script_comes_out_escaped(): void {
        $this->resetAfterTest();
        $script = '<script>alert(1)</script>';

        $html = $this->render([
            cell_content::text($script),
            cell_content::link(new \moodle_url('/admin/tool/vae/dossier.php', ['q' => $script]), $script),
            cell_content::span('small', $script),
            cell_content::div('small', $script),
        ]);

        $this->assertSame(4, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_a_text_or_an_html_equal_to_zero_is_drawn(): void {
        $this->resetAfterTest();

        // Seen on 20/11/2026: Mustache holds the text "0" for empty, and a section that tested the text itself drew nothing
        // for it, where the writer of the core drew the figure (a note, a title or a code that is "0").
        $this->assertSame('0', trim($this->render([cell_content::text('0')])));
        $this->assertSame('0', trim($this->render([cell_content::html('0')])));
        $this->assertSame(
            'a0b0c',
            trim($this->render([
                cell_content::text('a'),
                cell_content::text('0'),
                cell_content::text('b'),
                cell_content::html('0'),
                cell_content::text('c'),
            ]))
        );
        $this->assertSame('', trim($this->render([cell_content::text(''), cell_content::html('')])));
    }

    public function test_a_part_of_an_unknown_kind_fails_in_words(): void {
        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('tool_vae\output\cell_content');

        new cell_content([['image' => 'x']]);
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;

        $context = (new cell_content([
            cell_content::text('<b>a</b>'),
            cell_content::link(new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => 3, 'x' => 1]), 'b'),
        ]))->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertSame([
            'parts' => [
                ['text' => ['label' => '<b>a</b>'], 'link' => false, 'span' => false, 'div' => false, 'html' => false],
                ['text' => false, 'link' => [
                    'url' => (new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => 3, 'x' => 1]))->out(false),
                    'label' => 'b',
                ], 'span' => false, 'div' => false, 'html' => false],
            ],
        ], $context);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/cell_content.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/cell_content', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['texthtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/cell_content', $context);

        $this->assertStringContainsString('<a href=', $html);
        $this->assertStringContainsString('<span class=', $html);
    }
}
