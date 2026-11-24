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

use tool_vae\local\exporter;

/**
 * The page "Exporter le dossier" (export.php) drawn by tool_vae\output\export_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That each state of the page draws the same DOM as before its migration: the sub-system of competencies
 * off, a dossier with no declaration, the archive produced (with and without the lines the schema refuses, for the vaeiste
 * and for another person: the answer to a sending, in no instant of the tool of equivalence), and the sentences above the
 * confirmation of the core, with and without the archive in place. The "before" is the code of the page as it was, kept here
 * as the expected value. Also: that the context holds plain texts, that a script in a refused value comes out escaped, that
 * the template renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(export_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(export_warnings::class)]
final class export_page_test extends \advanced_testcase {
    use dom_canon;
    use forms_before;

    /** @var \moodle_url The dossier of the vaeiste. */
    private \moodle_url $dossier;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->dossier = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => 7]);
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/export.php', ['userid' => 7]));
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param export_page $page
     * @return string HTML.
     */
    private function render(export_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * An archive in the area of the plugin, as the exporter stores one.
     *
     * @param string $id The 26 characters of the identifier in the name of the file.
     * @return \stored_file
     */
    private static function archive(string $id = '01JC3Z5Q8R9S0T1V2W3X4Y5Z6A'): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'tool_vae',
            'filearea' => exporter::FILEAREA,
            'itemid' => 7,
            'filepath' => '/',
            'filename' => 'dossier-' . $id . '.zip',
            'timecreated' => 1792411200,
        ], 'archive');
    }

    /**
     * The address export.php gave to download a file of the archive.
     *
     * @param \stored_file $file
     * @return \moodle_url
     */
    private static function download(\stored_file $file): \moodle_url {
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'tool_vae',
            exporter::FILEAREA,
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            true
        );
    }

    /**
     * What export.php drew after it produced the archive, below its heading, before its migration.
     *
     * @param bool $own
     * @param \stdClass $registered
     * @param array $warnings
     * @param \stored_file $file
     * @param \moodle_url $dossierurl
     * @return string HTML.
     */
    private static function before_done(
        bool $own,
        \stdClass $registered,
        array $warnings,
        \stored_file $file,
        \moodle_url $dossierurl
    ): string {
        $out = self::core()->notification(
            get_string($own ? 'exportdonekept' : 'exportdone', 'tool_vae', (object) [
                'archive' => $registered->archiveuuid,
                'sha256' => $registered->sha256,
            ]),
            \core\output\notification::NOTIFY_SUCCESS
        );
        if ($warnings) {
            $out .= self::core()->notification(
                self::before_exporter_warnings_html($warnings, get_string('exportwarning', 'tool_vae')),
                \core\output\notification::NOTIFY_WARNING
            );
        }
        $out .= \html_writer::link(self::download($file), get_string('exportdownload', 'tool_vae'), ['class' => 'btn btn-primary']);

        return $out . self::core()->continue_button($dossierurl);
    }

    /**
     * What export.php drew between its heading and the confirmation of the core, before its migration.
     *
     * @param bool $own
     * @param \stored_file|null $kept
     * @return string HTML.
     */
    private static function before_intro(bool $own, ?\stored_file $kept): string {
        $intro = $own ? get_string('exportintro', 'tool_vae') : get_string('exportintroother', 'tool_vae');
        $out = \html_writer::tag('p', s($intro));
        if ($kept) {
            $out .= self::core()->notification(
                s(get_string('exportkeptcurrent', 'tool_vae', (object) [
                    'date' => userdate((int) $kept->get_timecreated()),
                    'id' => substr($kept->get_filename(), -30, 26),
                ])) . ' ' . \html_writer::link(self::download($kept), get_string('exportdownload', 'tool_vae')),
                \core\output\notification::NOTIFY_INFO
            );
        }

        return $out . self::core()->notification(
            get_string('exportwithdrawalnotice', 'tool_vae'),
            \core\output\notification::NOTIFY_WARNING
        );
    }

    /**
     * Lines the schema refused, as exporter::warnings() gives them.
     *
     * @param string $value The value of the first line.
     * @return array[]
     */
    private static function warnings(string $value = 'Accueil'): array {
        return [
            ['declaration' => 'D-1', 'fiche' => 'RNCP1', 'file' => 'declarations.json', 'field' => 'titre', 'value' => $value],
            ['declaration' => '', 'fiche' => 'RNCP1', 'file' => 'fiche.json', 'field' => '', 'value' => null],
        ];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = export_page::disabled();

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/export_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        $file = self::archive();
        $registered = (object) ['archiveuuid' => '01JC3Z5Q8R9S0T1V2W3X4Y5Z6A', 'sha256' => str_repeat('ab', 32)];
        // Values that are the figure 0 were drawn before; a template that tested them would drop them.
        $zero = (object) ['archiveuuid' => '0', 'sha256' => '0'];

        $cases = [
            'disabled' => [
                export_page::disabled(),
                self::core()->notification(
                    get_string('errorcompetenciesdisabled', 'tool_vae'),
                    \core\output\notification::NOTIFY_ERROR
                ),
            ],
            'no declaration' => [
                export_page::nodeclaration($this->dossier),
                self::core()->notification(get_string('errorexportempty', 'tool_vae'), \core\output\notification::NOTIFY_INFO)
                    . self::core()->continue_button($this->dossier),
            ],
            'done, own, no warning' => [
                export_page::done(true, $registered, [], $file, $this->dossier),
                self::before_done(true, $registered, [], $file, $this->dossier),
            ],
            'done, other, warnings' => [
                export_page::done(false, $registered, self::warnings(), $file, $this->dossier),
                self::before_done(false, $registered, self::warnings(), $file, $this->dossier),
            ],
            'done, values that are 0' => [
                export_page::done(false, $zero, self::warnings('0'), $file, $this->dossier),
                self::before_done(false, $zero, self::warnings('0'), $file, $this->dossier),
            ],
            'intro, own, nothing kept' => [export_page::intro(true, null), self::before_intro(true, null)],
            'intro, own, an archive kept' => [export_page::intro(true, $file), self::before_intro(true, $file)],
            'intro, other' => [export_page::intro(false, null), self::before_intro(false, null)],
        ];
        foreach ($cases as $name => [$page, $expected]) {
            $this->assertSame(self::canon($expected), self::canon($this->render($page)), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $output = $PAGE->get_renderer('tool_vae');
        $file = self::archive();
        $registered = (object) ['archiveuuid' => 'ARCHIVE-ONE', 'sha256' => 'DIGEST-ONE'];

        $done = export_page::done(true, $registered, [], $file, $this->dossier)->export_for_template($output);
        $this->assertStringContainsString('ARCHIVE-ONE', $done['noticehtml']);
        $this->assertFalse($done['warninghtml']);
        $this->assertFalse($done['intro']);
        $this->assertSame(
            ['url' => self::download($file)->out(false), 'label' => get_string('exportdownload', 'tool_vae')],
            $done['download']
        );
        $this->assertStringContainsString('name="userid" value="7"', $done['continuehtml']);

        $intro = export_page::intro(false, null)->export_for_template($output);
        $this->assertSame(get_string('exportintroother', 'tool_vae'), $intro['intro']);
        $this->assertFalse($intro['keptnoticehtml']);
        $this->assertStringContainsString(get_string('exportwithdrawalnotice', 'tool_vae'), $intro['withdrawalhtml']);
        $this->assertFalse($intro['download']);
        $this->assertFalse($intro['continuehtml']);

        $kept = export_page::intro(true, $file)->export_for_template($output);
        $this->assertSame(get_string('exportintro', 'tool_vae'), $kept['intro']);
        $this->assertStringContainsString('01JC3Z5Q8R9S0T1V2W3X4Y5Z6A', $kept['keptnoticehtml']);
    }

    public function test_a_script_in_a_refused_value_comes_out_escaped(): void {
        $this->resetAfterTest();
        $registered = (object) ['archiveuuid' => 'ARCHIVE-ONE', 'sha256' => 'DIGEST-ONE'];

        $html = $this->render(export_page::done(
            false,
            $registered,
            self::warnings('<script>alert(1)</script>'),
            self::archive(),
            $this->dossier
        ));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/export_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/export_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(
            ['noticehtml', 'warninghtml', 'keptnoticehtml', 'withdrawalhtml', 'continuehtml'],
            array_values(array_unique($raw[1]))
        );
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/export_page', $context);

        $this->assertStringContainsString('class="btn btn-primary"', $html);
    }
}
