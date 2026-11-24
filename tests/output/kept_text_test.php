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
 * The text typed, given back in fields that can be copied, when the declaration page has no form left (lot 7, MMR-520).
 *
 * WHAT IS ASSERTED. That the piece kept_text draws the same DOM as hidden_frameworks::kept_text() drew before its migration
 * (its code kept in the trait presenter_before) for the narrative alone, the detail alone, both, neither, a text made of
 * blanks, the figure 0; that the text of each field is placed EXACTLY as before, line breaks and a leading line break
 * included (a textarea keeps them, and the canonical DOM would not see a difference there); that a script typed comes out
 * escaped; that the template places nothing as HTML and renders its example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(kept_text::class)]
final class kept_text_test extends \advanced_testcase {
    use dom_canon;
    use presenter_before;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/declare.php'));
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The piece drawn by the renderer of the plugin for the web.
     *
     * @param kept_text $piece
     * @return string HTML.
     */
    private function render(kept_text $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($piece);
    }

    /**
     * The cases: narrative, detail.
     *
     * @return string[][]
     */
    private static function cases(): array {
        return [
            'both' => ["Accompagnement au lever.\nSecond ligne.", 'Unité protégée, équipe de nuit'],
            'the narrative alone' => ["\nUne ligne qui commence par un saut.\r\n\r\nEt une autre.  ", ''],
            'the detail alone' => ['   ', 'Un détail'],
            'neither' => ['', ''],
            'blanks' => ["  \n ", ' '],
            'the figure 0' => ['0', '0'],
            'an ampersand' => ['A & B', '<b>gras</b>'],
        ];
    }

    public function test_the_piece_is_a_named_template(): void {
        global $PAGE;
        $piece = new kept_text('', '');

        $this->assertInstanceOf(\core\output\renderable::class, $piece);
        $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
        $this->assertSame('tool_vae/kept_text', $piece->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_case_draws_the_dom_of_before(): void {
        foreach (self::cases() as $name => [$situation, $detail]) {
            $this->assertSame(
                self::canon(self::before_kept_text($situation, $detail)),
                self::canon($this->render(new kept_text($situation, $detail))),
                $name
            );
        }
        $this->assertSame('', $this->render(new kept_text('', '')));
        $this->assertSame('', $this->render(new kept_text("  \n ", ' ')));
    }

    public function test_the_text_of_a_field_is_placed_exactly_as_before(): void {
        foreach (self::cases() as $name => [$situation, $detail]) {
            foreach (['situation' => $situation, 'settingdetail' => $detail] as $text) {
                if (trim($text) === '') {
                    continue;
                }
                $html = $this->render(new kept_text($situation, $detail));
                $this->assertStringContainsString('>' . s($text) . '</textarea>', $html, $name);
            }
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $exported = (new kept_text('A & B', ''))->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertTrue($exported['show']);
        $this->assertSame(get_string('declarekeptintro', 'tool_vae'), $exported['intro']);
        $this->assertSame([[
            'id' => 'tool_vae_kept_situation',
            'label' => get_string('situation', 'tool_vae'),
            'rows' => 12,
            'text' => 'A & B',
        ]], $exported['fields']);
    }

    public function test_a_script_typed_comes_out_escaped(): void {
        $html = $this->render(new kept_text('Un texte </textarea><script>alert(1)</script>', '<script>alert(2)</script>'));

        $this->assertStringContainsString('Un texte &lt;/textarea&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_template_places_nothing_as_html_and_renders_its_example(): void {
        $file = __DIR__ . '/../../templates/kept_text.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringNotContainsString('{{{', $source);
        $this->assertStringContainsString('@template tool_vae/kept_text', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $this->assertStringContainsString('<textarea', self::core()->render_from_template('tool_vae/kept_text', $context));
    }
}
