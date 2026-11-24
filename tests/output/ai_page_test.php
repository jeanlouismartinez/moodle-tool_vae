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
require_once(__DIR__ . '/fiche_ai_before.php');

use tool_vae\local\ai_assistant;

/**
 * The page of the reformulation aid (ai.php) drawn by tool_vae\output\ai_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That each screen draws, below the heading of the page, the same DOM as ai_screens drew before its
 * migration (its code is kept in the trait fiche_ai_before), and as the page drew around it: the policy; the confirmation
 * with a text to send, an empty text, a text too long, a text of the greatest length, the fields carried, the warning that
 * what was confirmed changed; the result with a proposal and without one. ai.php asks for the session key before anything,
 * so the tool of equivalence shows its refusal only: these screens are proved here, and played by tests/behat/ai.feature.
 * Also: a script and a text equal to "0" in every text given, the context, the keys placed as HTML, the example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ai_page::class)]
final class ai_page_test extends \advanced_testcase {
    use dom_canon;
    use fiche_ai_before;

    /** @var array What the page passes the screens. */
    private const PARAMS = ['source' => 'section', 'doctype' => 'faisabilite', 'section' => 'objectifs', 'slot' => 0];

    #[\Override]
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/ai.php', self::PARAMS));
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param ai_page $page
     * @return string HTML.
     */
    private function render(ai_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The way back of the screens.
     *
     * @return \moodle_url
     */
    private function back(): \moodle_url {
        return new \moodle_url('/admin/tool/vae/section.php', ['section' => 'objectifs', 'slot' => 0]);
    }

    /**
     * The confirmation of ai.php before its migration: the warning the page wrote before the screen, then the screen.
     *
     * @param array $params
     * @param string $text
     * @param string $provider
     * @param string $backwords
     * @param array $carried
     * @param string|null $again
     * @return string
     */
    private function before_confirm(
        array $params,
        string $text,
        string $provider,
        string $backwords,
        array $carried,
        ?string $again
    ): string {
        $out = '';
        if ($again !== null) {
            $out .= self::core()->notification(s($again), \core\output\notification::NOTIFY_WARNING);
        }

        return $out . self::before_ai_confirm($params, $text, $provider, $this->back(), $backwords, $carried);
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;

        $page = ai_page::policy(self::PARAMS, 'x', $this->back(), 'Retour');

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/ai_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_screen_draws_the_dom_of_before(): void {
        $carried = ['confirm' => 'abc123', 'mark' => 'def456'];
        $max = ai_assistant::max_chars();
        $texts = [
            'a text' => "Mon texte.\nDeuxième ligne.",
            'an empty text' => '',
            'a text too long' => str_repeat('a', $max + 1),
            'a text of the greatest length' => str_repeat('a', $max),
            'a text that begins with a line break' => "\nMon texte.",
        ];
        foreach ($texts as $name => $text) {
            foreach ([null, 'The provider changed.'] as $again) {
                $this->assertSame(
                    self::canon($this->before_confirm(self::PARAMS, $text, 'Fournisseur d\'essai', 'Retour', $carried, $again)),
                    self::canon($this->render(ai_page::confirm(
                        self::PARAMS,
                        $text,
                        'Fournisseur d\'essai',
                        $this->back(),
                        'Retour',
                        $carried,
                        $again
                    ))),
                    "confirm, $name, " . ($again ?? 'first')
                );
            }
            $this->assertSame(
                self::canon(self::before_ai_policy(self::PARAMS, $text, $this->back(), 'Retour')),
                self::canon($this->render(ai_page::policy(self::PARAMS, $text, $this->back(), 'Retour'))),
                "policy, $name"
            );
            $this->assertSame(
                self::canon(self::before_ai_result('Une autre formulation.', null, $text, $this->back(), 'Retour')),
                self::canon($this->render(ai_page::result('Une autre formulation.', null, $text, $this->back(), 'Retour'))),
                "result, $name"
            );
            $this->assertSame(
                self::canon(self::before_ai_result(null, 'No proposal could be obtained.', $text, $this->back(), 'Retour')),
                self::canon($this->render(ai_page::result(null, 'No proposal could be obtained.', $text, $this->back(), 'Retour'))),
                "no proposal, $name"
            );
        }
        // Premises: the cases draw what they are named for.
        $this->assertStringContainsString('alert-danger', self::before_ai_confirm(self::PARAMS, '', 'F', $this->back(), 'R'));
        $long = self::before_ai_confirm(self::PARAMS, str_repeat('a', $max + 1), 'F', $this->back(), 'R');
        $this->assertStringContainsString(s(get_string('errorailength', 'tool_vae', $max)), $long);
        $this->assertStringNotContainsString('type="submit"', $long);
        $this->assertStringContainsString('alert-warning', $this->before_confirm(self::PARAMS, 'a', 'F', 'R', [], 'x'));
    }

    public function test_the_texts_of_the_page_stay_exactly_as_they_were(): void {
        // The fields are textareas: their text is placed exactly, a line break first included.
        $text = "\nMon texte.\n  Deuxième ligne.  ";
        $html = $this->render(ai_page::result('  Proposition.' . "\n", null, $text, $this->back(), 'Retour'));
        $this->assertStringContainsString('class="form-control mb-2">' . s($text) . '</textarea>', $html);
        $this->assertStringContainsString('class="form-control mb-2">' . s('  Proposition.' . "\n") . '</textarea>', $html);
        $html = $this->render(ai_page::confirm(self::PARAMS, $text, 'F', $this->back(), 'Retour'));
        $this->assertStringContainsString('class="form-control mb-2" name="text">' . s($text) . '</textarea>', $html);
        // A field and its label touch, and so do the hidden fields: nothing is drawn between them.
        $this->assertStringContainsString('</label><textarea', $html);
        $this->assertStringContainsString('value="send"><label', $html);
        $this->assertStringContainsString('</textarea><label', $this->render(ai_page::result('P', null, 'T', $this->back(), 'R')));
    }

    public function test_a_script_or_a_zero_in_any_text_is_drawn_as_before(): void {
        $carried = ['confirm' => 'abc', 'mark' => 'def'];
        foreach (['<script>alert(1)</script>', '0'] as $value) {
            $params = ['source' => $value, 'section' => $value];
            $cases = [
                'confirm' => [
                    $this->before_confirm($params, $value, $value, $value, ['mark' => $value], $value),
                    ai_page::confirm($params, $value, $value, $this->back(), $value, ['mark' => $value], $value),
                ],
                'policy' => [
                    self::before_ai_policy($params, $value, $this->back(), $value),
                    ai_page::policy($params, $value, $this->back(), $value),
                ],
                'result' => [
                    self::before_ai_result($value, null, $value, $this->back(), $value),
                    ai_page::result($value, null, $value, $this->back(), $value),
                ],
                'no proposal' => [
                    self::before_ai_result(null, $value, $value, $this->back(), $value),
                    ai_page::result(null, $value, $value, $this->back(), $value),
                ],
            ];
            foreach ($cases as $name => [$before, $page]) {
                $html = $this->render($page);
                $this->assertSame(self::canon($before), self::canon($html), "$name, $value");
                $this->assertStringNotContainsString('<script>', $html, $name);
            }
        }
        // Each text given carries its script out escaped: the text (field), the provider (twice), the way out, the warning,
        // the place (twice, hidden), the carried mark.
        $script = '<script>alert(1)</script>';
        $html = $this->render(ai_page::confirm(
            ['source' => $script, 'section' => $script],
            $script,
            $script,
            $this->back(),
            $script,
            ['confirm' => 'abc', 'mark' => $script],
            $script
        ));
        $this->assertSame(1, substr_count($html, '>&lt;script&gt;alert(1)&lt;/script&gt;</textarea>'));
        $this->assertSame(3, substr_count($html, 'value="&lt;script&gt;alert(1)&lt;/script&gt;"'), 'The places and the mark.');
        $this->assertStringContainsString('name="mark" value="def"', $this->render(ai_page::confirm(
            self::PARAMS,
            'a',
            'b',
            $this->back(),
            'R',
            $carried
        )));
        $this->assertSame(8, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
        $error = $this->render(ai_page::result(null, $script, 'a', $this->back(), 'R'));
        $this->assertSame(1, substr_count($error, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'The error, in a notification.');
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');

        $confirm = ai_page::confirm(self::PARAMS, 'a<b', 'P&Q', $this->back(), 'R<', ['mark' => 'm'])
            ->export_for_template($output);
        $this->assertFalse($confirm['againhtml']);
        $this->assertSame(get_string('aiconfirmwhere', 'tool_vae', 'P&Q'), $confirm['confirm']['where']);
        $this->assertSame(['label' => get_string('aiconfirmsend', 'tool_vae', 'P&Q')], $confirm['form']['button']);
        $this->assertSame('a<b', $confirm['form']['field']['text']);
        $this->assertSame('R<', $confirm['form']['backwords']);
        $this->assertSame($this->back()->out(false), $confirm['form']['backurl']);
        $this->assertSame(
            ['sesskey', 'source', 'doctype', 'section', 'slot', 'mark', 'step'],
            array_column($confirm['form']['hidden'], 'name')
        );
        $this->assertSame('0', $confirm['form']['hidden'][4]['value']);
        $this->assertFalse($confirm['proposal']);

        $result = ai_page::result('p', null, 't', $this->back(), 'R')->export_for_template($output);
        $this->assertFalse($result['form']);
        $this->assertSame('p', $result['proposal']['field']['text']);
        $this->assertSame('tool-vae-ai-text', $result['original']['field']['id']);
    }

    public function test_only_the_html_of_the_core_is_placed_as_it_is(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/ai_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(
            ['againhtml', 'errorhtml', 'headinghtml', 'policyhtml', 'problemhtml'],
            array_values(array_unique($raw[1]))
        );
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/ai_page.mustache');
        $this->assertStringContainsString('@template tool_vae/ai_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/ai_page', $context);

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('type="submit"', $html);
    }
}
