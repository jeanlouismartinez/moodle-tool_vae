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

use tool_vae\form\dossier_import_form;

/**
 * The page "Réimporter un dossier" (import_dossier.php) drawn by tool_vae\output\import_dossier_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That each state of the page draws the same DOM as before its migration: the sub-system of competencies
 * off (in no instant of the tool of equivalence), and, below the heading, what the page is for, the notice that a withdrawal
 * does not reach an archive and the form of the core, drawn once (the state of the bench). The "before" is the code of the
 * page as it was, kept here as the expected value. Also: that the context holds plain texts, that the template places as HTML
 * only the keys made by the core, that the template renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(import_dossier_page::class)]
final class import_dossier_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->url = new \moodle_url('/admin/tool/vae/import_dossier.php', ['userid' => 7]);
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param import_dossier_page $page
     * @return string HTML.
     */
    private function render(import_dossier_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * A form of the page, as the page builds it.
     *
     * @return dossier_import_form
     */
    private function form(): dossier_import_form {
        $form = new dossier_import_form($this->url->out(false));
        $form->set_data(['userid' => 7]);

        return $form;
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = import_dossier_page::disabled();

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/import_dossier_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $expected = self::core()->notification(
            get_string('errorcompetenciesdisabled', 'tool_vae'),
            \core\output\notification::NOTIFY_ERROR
        );
        $this->assertSame(self::canon($expected), self::canon($this->render(import_dossier_page::disabled())), 'disabled');

        $expected = \html_writer::tag('p', s(get_string('importdossierintro', 'tool_vae')));
        $expected .= self::core()->notification(
            get_string('importdossierwithdrawnotice', 'tool_vae'),
            \core\output\notification::NOTIFY_WARNING
        );
        ob_start();
        $this->form()->display();
        $expected .= ob_get_clean();
        $html = $this->render(import_dossier_page::form($this->form()));
        $this->assertSame(1, preg_match_all('/filepickerhidden/', $html));
        $this->assertSame(self::canon(self::drafts($expected)), self::canon(self::drafts($html)), 'form');
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $output = $PAGE->get_renderer('tool_vae');

        $form = import_dossier_page::form($this->form())->export_for_template($output);
        $this->assertSame(get_string('importdossierintro', 'tool_vae'), $form['intro']);
        $this->assertStringContainsString(get_string('importdossierwithdrawnotice', 'tool_vae'), $form['noticehtml']);
        $this->assertStringContainsString('_qf__tool_vae_form_dossier_import_form', $form['formhtml']);

        $disabled = import_dossier_page::disabled()->export_for_template($output);
        $this->assertFalse($disabled['intro']);
        $this->assertStringContainsString(get_string('errorcompetenciesdisabled', 'tool_vae'), $disabled['noticehtml']);
        $this->assertFalse($disabled['formhtml']);
    }

    public function test_only_the_html_of_the_core_is_placed_as_it_is(): void {
        $this->resetAfterTest();
        // No text of the page comes from the data: the only values placed unescaped are made by the core.
        $source = file_get_contents(__DIR__ . '/../../templates/import_dossier_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['noticehtml', 'formhtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/import_dossier_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/import_dossier_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/import_dossier_page', $context);

        $this->assertStringContainsString('<p>', $html);
    }
}
