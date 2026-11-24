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
 * The page "Rattacher une fiche au cours" (course_link.php) drawn by tool_vae\output\course_link_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That each state of the page draws the same DOM as before its migration: the sub-system of competencies
 * off (below the header, no heading), no record imported (below the heading), and the records (below the heading) with each
 * kind of action: a framework hidden in the core (the reason), a record all in the course (nothing), a record to attach (a
 * button of the core); a record with no date; a code and a type that are the figure 0. The "before" is the code of the page as
 * it was, kept here as the expected value. Also: that the context holds plain texts, that a script in a code comes out
 * escaped, that the template places as HTML only the keys named ...html and renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(course_link_page::class)]
final class course_link_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->url = new \moodle_url('/admin/tool/vae/course_link.php', ['courseid' => 7]);
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param course_link_page $page
     * @return string HTML.
     */
    private function render(course_link_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The body of course_link.php before its migration, after the heading (its code, kept as the expected value).
     *
     * @param \stdClass[] $records course_linker::records().
     * @param \moodle_url $url
     * @return string HTML.
     */
    private static function before(array $records, \moodle_url $url): string {
        if (!$records) {
            return self::core()->notification(get_string('linkrecordnone', 'tool_vae'), \core\output\notification::NOTIFY_INFO);
        }
        $html = \html_writer::tag('p', s(get_string('linkrecordintro', 'tool_vae')));
        $table = new \html_table();
        $table->head = [
            get_string('linkrecordcode', 'tool_vae'),
            get_string('linkrecordupdated', 'tool_vae'),
            get_string('linkrecordincourse', 'tool_vae'),
            '',
        ];
        foreach ($records as $record) {
            $complete = $record->total > 0 && $record->incourse >= $record->total;
            if ($record->hidden) {
                $action = \html_writer::tag('span', s(get_string('linkrecordhidden', 'tool_vae')), ['class' => 'small text-muted']);
            } else {
                $action = $complete ? '' : self::core()->single_button(
                    new \moodle_url($url, ['fichecode' => $record->fichecode]),
                    get_string('linkrecordbutton', 'tool_vae'),
                    'post'
                );
            }
            $table->data[] = [
                s($record->fichecode) . ' (' . s(\core_text::strtoupper($record->fichetype)) . ')',
                $record->sourceupdated ? \tool_vae\local\display_date::format(
                    (int) $record->sourceupdated,
                    get_string('strftimedate', 'langconfig')
                ) : '-',
                s(get_string('linkrecordcount', 'tool_vae', $record)),
                $action,
            ];
        }

        return $html . \html_writer::table($table);
    }

    /**
     * A record as course_linker::records() gives it.
     *
     * @param string $code
     * @param string $type
     * @param int|null $updated
     * @param int $total
     * @param int $incourse
     * @param bool $hidden
     * @return \stdClass
     */
    private static function record(string $code, string $type, ?int $updated, int $total, int $incourse, bool $hidden): \stdClass {
        return (object) ['fichecode' => $code, 'fichetype' => $type, 'sourceupdated' => $updated, 'total' => $total,
            'incourse' => $incourse, 'hidden' => $hidden];
    }

    /**
     * Four records: hidden, all in the course, to attach, to attach with no date and no competency.
     *
     * @return \stdClass[]
     */
    private static function records(): array {
        return [
            self::record('DEMO', 'rncp', 1792411200, 4, 0, true),
            self::record('DEMOBLOCS', 'rncp', 1792411200, 3, 3, false),
            self::record('RS5000', 'rs', 1792497600, 5, 2, false),
            self::record('VIDE', 'rs', null, 0, 0, false),
        ];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = course_link_page::disabled();

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/course_link_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();

        $expected = self::core()->notification(
            get_string('errorcompetenciesdisabled', 'tool_vae'),
            \core\output\notification::NOTIFY_ERROR
        );
        $this->assertSame(self::canon($expected), self::canon($this->render(course_link_page::disabled())), 'disabled');

        $cases = [
            'no record' => [],
            'records' => self::records(),
            // A code that is the figure 0 was drawn before; a template that tests the text itself would drop it.
            'a code and a type that are 0' => [
                self::record('0', '0', 1792411200, 1, 0, false),
                self::record('0', '0', 0, 0, 0, true),
            ],
        ];
        foreach ($cases as $name => $records) {
            $expected = self::before($records, $this->url);
            $page = $records ? course_link_page::records($records, $this->url) : course_link_page::none();
            $this->assertSame(self::canon($expected), self::canon($this->render($page)), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $output = $PAGE->get_renderer('tool_vae');

        $context = course_link_page::records(self::records(), $this->url)->export_for_template($output);
        $this->assertFalse($context['noticehtml']);
        $this->assertSame(get_string('linkrecordintro', 'tool_vae'), $context['intro']);
        $this->assertStringContainsString('DEMOBLOCS (RNCP)', $context['tablehtml']);
        $this->assertStringContainsString(get_string('linkrecordhidden', 'tool_vae'), $context['tablehtml']);

        $none = course_link_page::none()->export_for_template($output);
        $this->assertStringContainsString(get_string('linkrecordnone', 'tool_vae'), $none['noticehtml']);
        $this->assertFalse($none['intro']);
        $this->assertFalse($none['tablehtml']);

        $off = course_link_page::disabled()->export_for_template($output);
        $this->assertStringContainsString(get_string('errorcompetenciesdisabled', 'tool_vae'), $off['noticehtml']);
        $this->assertFalse($off['intro']);
        $this->assertFalse($off['tablehtml']);
    }

    public function test_a_script_in_a_code_comes_out_escaped(): void {
        $this->resetAfterTest();
        $records = [self::record('<script>alert(1)</script>', '<script>alert(2)</script>', null, 1, 0, false)];

        $html = $this->render(course_link_page::records($records, $this->url));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;SCRIPT&gt;ALERT(2)&lt;/SCRIPT&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/course_link_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/course_link_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['noticehtml', 'tablehtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/course_link_page', $context);

        $this->assertStringContainsString('<p>', $html);
    }
}
