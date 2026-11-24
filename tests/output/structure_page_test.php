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

use tool_vae\local\structure_archive;

/**
 * The page "Archive de la structure" (structure.php) drawn by tool_vae\output\structure_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That each state of the page draws, below its heading, the same DOM as before its migration: the
 * sub-system of competencies off, the production refused (its message), the archive produced (the answer to a sending: in no
 * instant of the tool of equivalence), and the sentences above the confirmation of the core, with and without an archive to
 * replace. The "before" is the code of the page as it was, kept here as the expected value. Also: that the context holds plain
 * texts, that a script in a refusal comes out escaped, that the template renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(structure_page::class)]
final class structure_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The dossier of the vaeiste. */
    private \moodle_url $dossier;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->dossier = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => 7]);
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/structure.php', ['userid' => 7]));
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param structure_page $page
     * @return string HTML.
     */
    private function render(structure_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * An archive of the structure in the area of the plugin.
     *
     * @return \stored_file
     */
    private static function archive(): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'tool_vae',
            'filearea' => structure_archive::FILEAREA,
            'itemid' => 7,
            'filepath' => '/',
            'filename' => 'structure-01JC3Z5Q8R9S0T1V2W3X4Y5Z6A.zip',
        ], 'archive');
    }

    /**
     * The address structure.php gave to download the archive.
     *
     * @param \stored_file $file
     * @return \moodle_url
     */
    private static function download(\stored_file $file): \moodle_url {
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'tool_vae',
            structure_archive::FILEAREA,
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            true
        );
    }

    /**
     * What structure.php drew below its heading, before its migration (up to the confirmation of the core, or the footer).
     *
     * @param string $state disabled, failed, done or intro.
     * @param array $args The message (failed), the registered archive and the file (done), whether one is replaced (intro).
     * @param \moodle_url $dossierurl
     * @return string HTML.
     */
    private static function before(string $state, array $args, \moodle_url $dossierurl): string {
        if ($state === 'disabled') {
            return self::core()->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                \core\output\notification::NOTIFY_ERROR
            ) . self::core()->continue_button($dossierurl);
        }
        if ($state === 'failed') {
            return self::core()->notification(s($args[0]), \core\output\notification::NOTIFY_ERROR)
                . self::core()->continue_button($dossierurl);
        }
        if ($state === 'done') {
            [$registered, $file] = $args;
            $out = self::core()->notification(
                get_string('structuredone', 'tool_vae', (object) [
                    'archive' => $registered->archiveuuid,
                    'sha256' => $registered->sha256,
                ]),
                \core\output\notification::NOTIFY_SUCCESS
            );
            $out .= \html_writer::link(
                self::download($file),
                get_string('structuredownload', 'tool_vae'),
                ['class' => 'btn btn-primary']
            );

            return $out . self::core()->continue_button($dossierurl);
        }
        $out = \html_writer::tag('p', s(get_string('structureintro', 'tool_vae')));
        $out .= self::core()->notification(get_string('structurenotice', 'tool_vae'), \core\output\notification::NOTIFY_INFO);
        if ($args[0]) {
            $out .= self::core()->notification(
                get_string('structurereplaces', 'tool_vae'),
                \core\output\notification::NOTIFY_WARNING
            );
        }

        return $out;
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = structure_page::intro(false);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/structure_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        $file = self::archive();
        $registered = (object) ['archiveuuid' => '01JC3Z5Q8R9S0T1V2W3X4Y5Z6A', 'sha256' => str_repeat('cd', 32)];
        $zero = (object) ['archiveuuid' => '0', 'sha256' => '0'];

        $cases = [
            'disabled' => [structure_page::disabled($this->dossier), 'disabled', []],
            'failed' => [structure_page::failed('Aucun positionnement.', $this->dossier), 'failed', ['Aucun positionnement.']],
            // A message, a value that are the figure 0 were drawn before; a template that tested them would drop them.
            'failed, 0' => [structure_page::failed('0', $this->dossier), 'failed', ['0']],
            'done' => [structure_page::done($registered, $file, $this->dossier), 'done', [$registered, $file]],
            'done, 0' => [structure_page::done($zero, $file, $this->dossier), 'done', [$zero, $file]],
            'intro, nothing to replace' => [structure_page::intro(false), 'intro', [false]],
            'intro, an archive to replace' => [structure_page::intro(true), 'intro', [true]],
        ];
        foreach ($cases as $name => [$page, $state, $args]) {
            $expected = self::before($state, $args, $this->dossier);
            $this->assertSame(self::canon($expected), self::canon($this->render($page)), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $output = $PAGE->get_renderer('tool_vae');
        $file = self::archive();

        $intro = structure_page::intro(true)->export_for_template($output);
        $this->assertSame(get_string('structureintro', 'tool_vae'), $intro['intro']);
        $this->assertStringContainsString(get_string('structurenotice', 'tool_vae'), $intro['noticehtml']);
        $this->assertStringContainsString(get_string('structurereplaces', 'tool_vae'), $intro['replaceshtml']);
        $this->assertFalse($intro['download']);
        $this->assertFalse($intro['continuehtml']);
        $this->assertFalse(structure_page::intro(false)->export_for_template($output)['replaceshtml']);

        $done = structure_page::done((object) ['archiveuuid' => 'ARCHIVE-ONE', 'sha256' => 'DIGEST-ONE'], $file, $this->dossier)
            ->export_for_template($output);
        $this->assertFalse($done['intro']);
        $this->assertStringContainsString('ARCHIVE-ONE', $done['noticehtml']);
        $this->assertSame(
            ['url' => self::download($file)->out(false), 'label' => get_string('structuredownload', 'tool_vae')],
            $done['download']
        );
        $this->assertStringContainsString('name="userid" value="7"', $done['continuehtml']);
    }

    public function test_a_script_in_a_refusal_comes_out_escaped(): void {
        $this->resetAfterTest();

        $html = $this->render(structure_page::failed('<script>alert(1)</script>', $this->dossier));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/structure_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/structure_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['noticehtml', 'replaceshtml', 'continuehtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/structure_page', $context);

        $this->assertStringContainsString('class="btn btn-primary"', $html);
    }
}
