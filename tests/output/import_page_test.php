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

use tool_vae\form\import_form;

/**
 * The page "Importer une fiche" (import.php) drawn by tool_vae\output\import_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That each state of the page draws, below its heading, the same DOM as before its migration: the
 * sub-system of competencies off and the core's right to manage frameworks missing (in no instant of the tool of
 * equivalence), and the form of the core (the state of the bench), drawn once. The "before" is the code of the page as it
 * was, kept here as the expected value. Also: that the context holds the form, that the template places as HTML only the keys
 * made by the core, that the template renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(import_page::class)]
final class import_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->url = new \moodle_url('/admin/tool/vae/import.php');
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param import_page $page
     * @return string HTML.
     */
    private function render(import_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * A form of the page, as the page builds it.
     *
     * @return import_form
     */
    private function form(): import_form {
        return new import_form($this->url->out(false));
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = import_page::refused('errorcompetenciesdisabled');

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/import_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        foreach (['errorcompetenciesdisabled', 'errorimportnocompetencymanage'] as $key) {
            $expected = self::core()->notification(get_string($key, 'tool_vae'), \core\output\notification::NOTIFY_ERROR);
            $this->assertSame(self::canon($expected), self::canon($this->render(import_page::refused($key))), $key);
        }

        ob_start();
        $this->form()->display();
        $expected = ob_get_clean();
        $html = $this->render(import_page::form($this->form()));
        $this->assertSame(1, preg_match_all('/filepickerhidden/', $html));
        $this->assertSame(self::canon(self::drafts($expected)), self::canon(self::drafts($html)), 'form');
    }

    public function test_the_context_holds_the_form_or_the_refusal(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $output = $PAGE->get_renderer('tool_vae');

        $form = import_page::form($this->form())->export_for_template($output);
        $this->assertFalse($form['noticehtml']);
        $this->assertStringContainsString('_qf__tool_vae_form_import_form', $form['formhtml']);

        $refused = import_page::refused('errorimportnocompetencymanage')->export_for_template($output);
        $this->assertStringContainsString(get_string('errorimportnocompetencymanage', 'tool_vae'), $refused['noticehtml']);
        $this->assertFalse($refused['formhtml']);
    }

    public function test_only_the_html_of_the_core_is_placed_as_it_is(): void {
        $this->resetAfterTest();
        // No text of the page comes from the data: the only values placed unescaped are made by the core.
        $source = file_get_contents(__DIR__ . '/../../templates/import_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['noticehtml', 'formhtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/import_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/import_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/import_page', $context);

        $this->assertStringContainsString('<form', $html);
    }
}
