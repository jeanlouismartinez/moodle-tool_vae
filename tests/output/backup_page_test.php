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
 * The page "Sauvegarder dans Moodle" (backup.php) drawn by tool_vae\output\backup_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That each state of the page draws the same DOM as before its migration: the sub-system of competencies
 * off and a dossier with no declaration (in no instant of the tool of equivalence), and the sentence above the confirmation
 * of the core (the state of the bench; the confirmation itself stays in the page). The "before" is the code of the page as it
 * was, kept here as the expected value. Also: that the context holds plain texts, that the template places as HTML only the
 * keys made by the core, that the template renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(backup_page::class)]
final class backup_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The dossier of the vaeiste. */
    private \moodle_url $dossier;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->dossier = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => 7]);
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/backup.php', ['userid' => 7]));
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param backup_page $page
     * @return string HTML.
     */
    private function render(backup_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * What backup.php drew, between the heading (or the header) and the confirmation (or the footer), before its migration.
     *
     * @param string $state disabled, nodeclaration or intro.
     * @param \moodle_url $dossierurl
     * @return string HTML.
     */
    private static function before(string $state, \moodle_url $dossierurl): string {
        if ($state === 'disabled') {
            return self::core()->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                \core\output\notification::NOTIFY_ERROR
            );
        }
        if ($state === 'nodeclaration') {
            return self::core()->notification(
                get_string('errorexportempty', 'tool_vae'),
                \core\output\notification::NOTIFY_INFO
            ) . self::core()->continue_button($dossierurl);
        }

        return \html_writer::tag('p', s(get_string('backupdossierintro', 'tool_vae')));
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = backup_page::intro();

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/backup_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();

        $cases = [
            'disabled' => backup_page::disabled(),
            'nodeclaration' => backup_page::nodeclaration($this->dossier),
            'intro' => backup_page::intro(),
        ];
        foreach ($cases as $state => $page) {
            $this->assertSame(self::canon(self::before($state, $this->dossier)), self::canon($this->render($page)), $state);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $output = $PAGE->get_renderer('tool_vae');

        $intro = backup_page::intro()->export_for_template($output);
        $this->assertSame(get_string('backupdossierintro', 'tool_vae'), $intro['intro']);
        $this->assertFalse($intro['noticehtml']);
        $this->assertFalse($intro['continuehtml']);

        $empty = backup_page::nodeclaration($this->dossier)->export_for_template($output);
        $this->assertFalse($empty['intro']);
        $this->assertStringContainsString(get_string('errorexportempty', 'tool_vae'), $empty['noticehtml']);
        $this->assertStringContainsString('name="userid" value="7"', $empty['continuehtml']);

        $disabled = backup_page::disabled()->export_for_template($output);
        $this->assertStringContainsString(get_string('errorcompetenciesdisabled', 'tool_vae'), $disabled['noticehtml']);
        $this->assertFalse($disabled['continuehtml']);
    }

    public function test_only_the_html_of_the_core_is_placed_as_it_is(): void {
        $this->resetAfterTest();
        // No text of the page comes from the data: the only values placed unescaped are made by the core.
        $source = file_get_contents(__DIR__ . '/../../templates/backup_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['noticehtml', 'continuehtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/backup_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/backup_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/backup_page', $context);

        $this->assertStringContainsString('<p>', $html);
    }
}
