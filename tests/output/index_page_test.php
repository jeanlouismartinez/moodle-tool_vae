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
 * The administration overview (index.php) drawn by tool_vae\output\index_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration (its code kept here as
 * the expected value, the table of the records kept in the trait presenter_before), for every state: the competencies
 * disabled (the link to the setup and the error, nothing else); no record imported, with and without the right to import
 * (the two buttons); records, some of which have lost their framework: kept for a framework of Moodle that carries the
 * code, kept for a trace of a vaeiste, or removable (the warning, and the table with one button per record); codes that
 * are the figure 0. Also: that the context holds plain texts, that a code with a script comes out escaped in the table of
 * the removable records, that the template places as HTML only its keys ...html and renders its example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(index_page::class)]
final class index_page_test extends \advanced_testcase {
    use dom_canon;
    use presenter_before;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/index.php'));
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin for the web.
     *
     * @param index_page $page
     * @return string HTML.
     */
    private function render(index_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The body of index.php before its migration, below its heading (its code, kept as the expected value).
     *
     * @param bool $enabled Whether the competencies are enabled.
     * @param \stdClass[] $sources
     * @param \stdClass|null $missing kept, framework, traces, removable: as the page sorted them; null when none is missing.
     * @param bool $canimport
     * @return string HTML.
     */
    private static function before(bool $enabled, array $sources, ?\stdClass $missing, bool $canimport): string {
        $output = self::core();
        $html = \html_writer::tag(
            'p',
            \html_writer::link(new \moodle_url('/admin/tool/vae/setup.php'), get_string('setupheading', 'tool_vae'))
        );
        if (!$enabled) {
            return $html . $output->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                \core\output\notification::NOTIFY_ERROR
            );
        }
        if (!$sources) {
            $html .= $output->notification(get_string('noframeworkimported', 'tool_vae'), \core\output\notification::NOTIFY_INFO);
        } else {
            if ($missing) {
                $removable = $missing->removable;
                $kept = $missing->kept;
                if ($kept) {
                    $warning = get_string('sourcenoframework', 'tool_vae', s(implode(', ', $kept)));
                    if ($missing->framework) {
                        $warning .= ' ' . get_string('sourcenoframeworkheld', 'tool_vae', s(implode(', ', $missing->framework)));
                    }
                    if ($missing->traces) {
                        $warning .= ' ' . get_string('sourcenoframeworktraced', 'tool_vae', s(implode(', ', $missing->traces)));
                    }
                    $html .= $output->notification($warning, \core\output\notification::NOTIFY_WARNING);
                }
                if ($removable) {
                    $html .= $output->notification(
                        get_string('sourcenoframeworkremovable', 'tool_vae', s(implode(', ', $removable))),
                        \core\output\notification::NOTIFY_WARNING
                    );
                    $table = new \html_table();
                    $table->attributes['class'] = 'generaltable tool-vae-removable';
                    $table->head = [get_string('sourcecode', 'tool_vae'), get_string('actions')];
                    foreach ($removable as $code) {
                        $table->data[] = [
                            \html_writer::tag('strong', s($code)),
                            $output->single_button(
                                new \moodle_url('/admin/tool/vae/remove_record.php', ['fichecode' => $code]),
                                get_string('removerecordbutton', 'tool_vae'),
                                'get'
                            ),
                        ];
                    }
                    $html .= \html_writer::table($table);
                }
            }
            $html .= self::before_sources($sources);
        }
        if ($canimport) {
            $html .= \html_writer::div(
                $output->render(new \core\output\single_button(
                    new \moodle_url('/admin/tool/vae/import_fiches.php'),
                    get_string('fcimportheading', 'tool_vae'),
                    'get',
                    \core\output\single_button::BUTTON_PRIMARY
                ))
                . $output->single_button(
                    new \moodle_url('/admin/tool/vae/import.php'),
                    get_string('import', 'tool_vae'),
                    'get'
                ),
                'd-flex flex-wrap gap-2 tool-vae-import-buttons'
            );
        }

        return $html;
    }

    /**
     * The records of the table.
     *
     * @return \stdClass[]
     */
    private function sources(): array {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_vae');
        $generator->create_source(['fichecode' => 'RNCP36832', 'fichetype' => 'rncp', 'name' => 'Diplôme aide-soignant']);
        $generator->create_source(['fichecode' => 'RS5000', 'fichetype' => 'rs', 'name' => 'Lève-personne']);

        return $DB->get_records('tool_vae_source', null, 'fichecode ASC');
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $page = index_page::disabled();

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/index_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->assertSame(self::canon(self::before(false, [], null, true)), self::canon($this->render(index_page::disabled())));

        $sources = $this->sources();
        $missing = [
            'none missing' => null,
            'kept for a framework' => (object) ['kept' => ['RS1', 'RS2'], 'framework' => ['RS1'], 'traces' => [],
                'removable' => []],
            'kept for a trace' => (object) ['kept' => ['RS2'], 'framework' => [], 'traces' => ['RS2'], 'removable' => []],
            'kept and removable' => (object) ['kept' => ['RS1', 'RS2'], 'framework' => ['RS1'], 'traces' => ['RS2'],
                'removable' => ['RS3', 'RS4']],
            'removable, the figure 0' => (object) ['kept' => [], 'framework' => [], 'traces' => [], 'removable' => ['0']],
        ];
        foreach ([true, false] as $canimport) {
            $this->assertSame(
                self::canon(self::before(true, [], null, $canimport)),
                self::canon($this->render(index_page::records([], null, $canimport))),
                'no record, ' . ($canimport ? 'may import' : 'may not')
            );
            foreach ($missing as $name => $sorted) {
                $this->assertSame(
                    self::canon(self::before(true, $sources, $sorted, $canimport)),
                    self::canon($this->render(index_page::records($sources, $sorted, $canimport))),
                    $name . ', ' . ($canimport ? 'may import' : 'may not')
                );
            }
        }
    }

    public function test_the_two_buttons_sit_side_by_side_with_nothing_between(): void {
        $html = $this->render(index_page::records([], null, true));

        $this->assertMatchesRegularExpression('#<div class="d-flex flex-wrap gap-2 tool-vae-import-buttons"><div#', $html);
        $this->assertMatchesRegularExpression('#</div><div class="singlebutton"#', $html);
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');

        $disabled = index_page::disabled()->export_for_template($output);
        $this->assertSame([
            'url' => (new \moodle_url('/admin/tool/vae/setup.php'))->out(false),
            'label' => get_string('setupheading', 'tool_vae'),
        ], $disabled['setup']);
        $this->assertStringContainsString(get_string('errorcompetenciesdisabled', 'tool_vae'), $disabled['errorhtml']);
        $this->assertFalse($disabled['sourceshtml']);
        $this->assertFalse($disabled['buttons']);

        $none = index_page::records([], null, false)->export_for_template($output);
        $this->assertFalse($none['errorhtml']);
        $this->assertStringContainsString(get_string('noframeworkimported', 'tool_vae'), $none['nonehtml']);
        $this->assertFalse($none['buttons']);
    }

    public function test_a_code_with_a_script_comes_out_escaped(): void {
        $missing = (object) ['kept' => [], 'framework' => [], 'traces' => [], 'removable' => ['<script>alert(1)</script>']];

        $html = $this->render(index_page::records($this->sources(), $missing, false));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_only_the_keys_of_html_are_placed_as_they_are(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/index_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['errorhtml', 'nonehtml', 'warninghtml', 'removablehtml', 'tablehtml', 'sourceshtml', 'primaryhtml',
            'buttonhtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $file = __DIR__ . '/../../templates/index_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/index_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/index_page', $context);
        $this->assertStringContainsString('tool-vae-import-buttons', $html);
    }
}
