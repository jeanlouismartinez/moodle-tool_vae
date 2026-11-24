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

use tool_vae\form\remove_record_form;

/**
 * The page "Retirer la ligne d'une fiche" (remove_record.php) drawn by tool_vae\output\remove_record_page (lot 7, MMR-520).
 *
 * WHAT IS ASSERTED. That each state of the page draws, below its heading, the same DOM as before its migration: each refusal
 * (unknown record, framework still held by the core, traces left on the record), its value the figure 0 too, with the button
 * back to the list; and the form of the core, drawn once. The "before" is the code of the page as it was, kept here as the
 * expected value. Also: that the context holds the form or the refusal, that a script in the value of a refusal comes out
 * escaped, that the template places as HTML only the keys made by the core and renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(remove_record_page::class)]
final class remove_record_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $self;

    /** @var \moodle_url The list of the records. */
    private \moodle_url $index;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->self = new \moodle_url('/admin/tool/vae/remove_record.php', ['fichecode' => 'DEMOBLOCS']);
        $this->index = new \moodle_url('/admin/tool/vae/index.php');
        $PAGE->set_url($this->self);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param remove_record_page $page
     * @return string HTML.
     */
    private function render(remove_record_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The form of the page, as the page builds it, on a record with nothing attached.
     *
     * @return remove_record_form
     */
    private function form(): remove_record_form {
        $inventory = (object) [
            'fichecode' => 'DEMOBLOCS',
            'source' => (object) ['licence' => 'Licence Ouverte', 'attribution' => 'France compétences',
                'sourceupdated' => 1792411200, 'lastimport' => 0],
            'refs' => [(object) ['title' => 'Fiche', 'url' => 'https://example.com/fiche']],
            'referents' => 1,
            'passageids' => [],
            'marks' => 0,
            'successor' => '',
            'replaced' => [],
        ];

        return new remove_record_form($this->self->out(false), ['inventory' => $inventory]);
    }

    /**
     * The refusal of the page before its migration, below the heading (its code, kept as the expected value).
     *
     * @param \stdClass $refusal record_remover::refusal().
     * @param \moodle_url $index
     * @return string HTML.
     */
    private static function before(\stdClass $refusal, \moodle_url $index): string {
        return self::core()->notification(
            s(get_string($refusal->errorcode, 'tool_vae', $refusal->a)),
            \core\output\notification::NOTIFY_ERROR
        ) . self::core()->continue_button($index);
    }

    /**
     * The refusals record_remover::refusal() gives.
     *
     * @return \stdClass[]
     */
    private static function refusals(): array {
        return [
            'unknown' => (object) ['errorcode' => 'errorrefunknownfiche', 'a' => null],
            'framework' => (object) ['errorcode' => 'errorremoverecordframework', 'a' => 'DEMOBLOCS'],
            'traces' => (object) ['errorcode' => 'errorremoverecordtraces', 'a' => 'des déclarations'],
            // A value that is the figure 0 was drawn before.
            'framework 0' => (object) ['errorcode' => 'errorremoverecordframework', 'a' => '0'],
        ];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = remove_record_page::refused(self::refusals()['unknown'], $this->index);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/remove_record_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        foreach (self::refusals() as $name => $refusal) {
            $expected = self::before($refusal, $this->index);
            $html = $this->render(remove_record_page::refused($refusal, $this->index));
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }

        ob_start();
        $this->form()->display();
        $expected = ob_get_clean();
        $html = $this->render(remove_record_page::form($this->form()));
        $this->assertSame(1, substr_count($html, '<form'));
        $this->assertSame(self::canon($expected), self::canon($html), 'form');
    }

    public function test_the_context_holds_the_form_or_the_refusal(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $output = $PAGE->get_renderer('tool_vae');

        $form = remove_record_page::form($this->form())->export_for_template($output);
        $this->assertFalse($form['noticehtml']);
        $this->assertFalse($form['continuehtml']);
        $this->assertStringContainsString('_qf__tool_vae_form_remove_record_form', $form['formhtml']);

        $refused = remove_record_page::refused(self::refusals()['framework'], $this->index)->export_for_template($output);
        $this->assertStringContainsString(
            s(get_string('errorremoverecordframework', 'tool_vae', 'DEMOBLOCS')),
            $refused['noticehtml']
        );
        $this->assertStringContainsString('index.php', $refused['continuehtml']);
        $this->assertFalse($refused['formhtml']);
    }

    public function test_a_script_in_the_value_of_a_refusal_comes_out_escaped(): void {
        $this->resetAfterTest();
        $refusal = (object) ['errorcode' => 'errorremoverecordframework', 'a' => '<script>alert(1)</script>'];

        $html = $this->render(remove_record_page::refused($refusal, $this->index));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/remove_record_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/remove_record_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['noticehtml', 'continuehtml', 'formhtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/remove_record_page', $context);

        $this->assertStringContainsString('alert', $html);
    }
}
