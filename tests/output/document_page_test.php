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
 * The one page document.php draws, drawn by tool_vae\output\document_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. document.php sends a file; the only page it draws says that the sub-system of competencies is off. That
 * page draws the same DOM as before its migration (in no instant of the tool of equivalence: the page asks the key of the
 * session first). The "before" is the code of the page as it was, kept here as the expected value. Also: that the template
 * places as HTML only the notification of the core, that it renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(document_page::class)]
final class document_page_test extends \advanced_testcase {
    use dom_canon;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/document.php', ['userid' => 7, 'format' => 'docx']));
        $PAGE->set_context(\context_system::instance());
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = new document_page();

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/document_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_the_page_draws_the_dom_of_before(): void {
        global $PAGE;
        $this->resetAfterTest();

        $expected = self::core()->notification(
            get_string('errorcompetenciesdisabled', 'tool_vae'),
            \core\output\notification::NOTIFY_ERROR
        );
        $html = $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render(new document_page());

        $this->assertSame(self::canon($expected), self::canon($html));
    }

    public function test_the_context_holds_the_notification(): void {
        global $PAGE;
        $this->resetAfterTest();

        $context = (new document_page())->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertSame(['noticehtml'], array_keys($context));
        $this->assertStringContainsString(get_string('errorcompetenciesdisabled', 'tool_vae'), $context['noticehtml']);
    }

    public function test_only_the_html_of_the_core_is_placed_as_it_is(): void {
        $this->resetAfterTest();
        // No text of the page comes from the data: the only value, placed unescaped, is made by the core.
        $source = file_get_contents(__DIR__ . '/../../templates/document_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['noticehtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/document_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/document_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/document_page', $context);

        $this->assertStringContainsString('alert', $html);
    }
}
