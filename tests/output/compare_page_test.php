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

use tool_vae\local\text_diff;

/**
 * The page "Comparer deux versions" (compare.php) drawn by tool_vae\output\compare_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration, for every state: two
 * versions that differ (words added and removed), a version compared to itself (the notification of the core), two empty
 * versions, a section and a text that are the figure 0. The "before" is the code of the page as it was, kept here as the
 * expected value (and text_diff::html() as it was, kept in the trait forms_before since lot 7, wave 4c, which drew it in the
 * piece compare_diff). Also: that the comparison is placed in its block exactly as text_diff::html() wrote it, with no white space
 * around it (the block keeps the white space as written: a line break of the template would be drawn); that the context holds
 * plain texts; that a script in the section name or in the text comes out escaped; that the template places as HTML only the
 * keys named ...html and renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(compare_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(compare_diff::class)]
final class compare_page_test extends \advanced_testcase {
    use dom_canon;
    use forms_before;

    /** @var \moodle_url The history of the section. */
    private \moodle_url $history;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/compare.php', ['draftid' => 5, 'from' => 1, 'to' => 3]));
        $PAGE->set_context(\context_system::instance());
        $this->history = new \moodle_url('/admin/tool/vae/history.php', ['draftid' => 5]);
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param compare_page $page
     * @return string HTML.
     */
    private function render(compare_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The body of compare.php before its migration, below the heading (its code, kept as the expected value).
     *
     * @param string $label draft_manager::label() of the draft.
     * @param int $draftid
     * @param int $from
     * @param int $fromtime
     * @param int $to
     * @param int $totime
     * @param array[] $ops text_diff::diff().
     * @return string HTML.
     */
    private static function before(
        string $label,
        int $draftid,
        int $from,
        int $fromtime,
        int $to,
        int $totime,
        array $ops
    ): string {
        $counts = text_diff::counts($ops);
        $html = \html_writer::tag('p', s(get_string('faisabsectionline', 'tool_vae', $label)));
        $html .= \html_writer::tag('p', \html_writer::link(
            new \moodle_url('/admin/tool/vae/history.php', ['draftid' => $draftid]),
            get_string('historylink', 'tool_vae')
        ));
        $html .= \html_writer::tag('p', s(get_string('comparesummary', 'tool_vae', (object) [
            'from' => $from,
            'fromdate' => userdate($fromtime),
            'to' => $to,
            'todate' => userdate($totime),
            'summary' => get_string('diffsummary', 'tool_vae', (object) $counts),
        ])));
        if ($from === $to) {
            $html .= self::core()->notification(get_string('comparesame', 'tool_vae'), \core\output\notification::NOTIFY_INFO);
        }

        return $html . \html_writer::div(self::before_text_diff_html($ops), 'tool-vae-diff border rounded p-3', [
            'style' => 'white-space: pre-wrap;',
        ]);
    }

    /**
     * The cases of the page: label, from, from time, to, to time, ops.
     *
     * @return array[]
     */
    private static function cases(): array {
        return [
            'two versions that differ' => ['Experiences no. 2', 1, 1792411200, 3, 1792497600,
                text_diff::diff("Je prépare les repas.\nJe range.", "Je prépare les repas du soir.\nJe range la cuisine.")],
            'a version compared to itself' => ['Objectives', 2, 1792411200, 2, 1792411200,
                text_diff::diff('Le même texte.', 'Le même texte.')],
            'two empty versions' => ['Objectives', 1, 1792411200, 2, 1792411300, text_diff::diff('', '')],
            // A text that is the figure 0 was drawn before; a template that tests the text itself would drop it.
            'a section and a text that are 0' => ['0', 1, 1792411200, 2, 1792411300, text_diff::diff('0', '0')],
            'a text that becomes 0' => ['0', 1, 1792411200, 2, 1792411300, text_diff::diff('1', '0')],
        ];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = new compare_page('Objectives', $this->history, 1, 0, 2, 0, []);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/compare_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();

        foreach (self::cases() as $name => [$label, $from, $fromtime, $to, $totime, $ops]) {
            $expected = self::before($label, 5, $from, $fromtime, $to, $totime, $ops);
            $html = $this->render(new compare_page($label, $this->history, $from, $fromtime, $to, $totime, $ops));
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    public function test_the_comparison_is_placed_as_written_with_no_white_space_around_it(): void {
        $this->resetAfterTest();

        foreach (self::cases() as $name => [$label, $from, $fromtime, $to, $totime, $ops]) {
            $html = $this->render(new compare_page($label, $this->history, $from, $fromtime, $to, $totime, $ops));
            $this->assertStringContainsString(
                '<div class="tool-vae-diff border rounded p-3" style="white-space: pre-wrap;">' . self::before_text_diff_html($ops)
                    . '</div>',
                $html,
                $name
            );
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $output = $PAGE->get_renderer('tool_vae');
        $ops = text_diff::diff('Je prépare.', 'Je range.');

        $context = (new compare_page('Objectives', $this->history, 1, 1792411200, 3, 1792497600, $ops))
            ->export_for_template($output);
        $this->assertSame(get_string('faisabsectionline', 'tool_vae', 'Objectives'), $context['section']);
        $history = ['url' => $this->history->out(false), 'label' => get_string('historylink', 'tool_vae')];
        $this->assertSame($history, $context['history']);
        $this->assertSame(get_string('comparesummary', 'tool_vae', (object) [
            'from' => 1,
            'fromdate' => userdate(1792411200),
            'to' => 3,
            'todate' => userdate(1792497600),
            'summary' => get_string('diffsummary', 'tool_vae', (object) text_diff::counts($ops)),
        ]), $context['summary']);
        $this->assertFalse($context['samehtml']);
        $this->assertSame($output->render(new compare_diff($ops)), $context['diffhtml']);

        $same = (new compare_page('Objectives', $this->history, 2, 1792411200, 2, 1792411200, $ops))
            ->export_for_template($output);
        $this->assertStringContainsString(get_string('comparesame', 'tool_vae'), $same['samehtml']);
    }

    public function test_a_script_in_the_section_or_the_text_comes_out_escaped(): void {
        $this->resetAfterTest();
        $ops = text_diff::diff('a', 'a <script>alert(2)</script>');

        $html = $this->render(new compare_page('<script>alert(1)</script>', $this->history, 1, 0, 2, 0, $ops));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/compare_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/compare_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['samehtml', 'diffhtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/compare_page', $context);

        $this->assertStringContainsString('tool-vae-diff', $html);
    }
}
