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

use tool_vae\local\draft_manager;

/**
 * The page "Journal réflexif" (journal.php) drawn by tool_vae\output\journal_page (lot 7, MMR-520 ticket 4).
 *
 * The tool of equivalence cannot show this page: on its data set every account is refused before the page is drawn (no
 * journal entry, the journal closed). So EVERY state is proved here. WHAT IS ASSERTED. That the page draws, below its
 * heading, the same DOM as before its migration, for every state: the journal open with no entry (the button to start one),
 * with entries (each with its link to open it and its link to delete it, a version kept or none), full (a notification of
 * the core in place of the button); the journal closed with entries (the notice, no button, the link to delete only) and with
 * none; an entry whose number and version are the figure 0. The "before" is the code of the page as it was, kept here as the
 * expected value. Also: that the context holds plain texts and addresses, that the template places as HTML only the keys
 * named ...html and renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(journal_page::class)]
final class journal_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->url = new \moodle_url('/admin/tool/vae/journal.php');
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param journal_page $page
     * @return string HTML.
     */
    private function render(journal_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The body of journal.php before its migration, below the heading (its code, kept as the expected value).
     *
     * @param bool $open draft_manager::reflection_open().
     * @param \stdClass[] $entries draft_manager::reflection_entries().
     * @param \moodle_url $url
     * @return string HTML.
     */
    private static function before(bool $open, array $entries, \moodle_url $url): string {
        $output = self::core();
        $html = \html_writer::div(s(get_string('reflexionprivate', 'tool_vae')), 'alert alert-info tool-vae-labelling', [
            'role' => 'note',
        ]);
        $html .= \html_writer::div(s(get_string('reflexionconsigne', 'tool_vae')), 'alert alert-warning tool-vae-anonymise', [
            'role' => 'note',
        ]);
        $html .= \html_writer::tag('p', s(get_string('reflexionintro', 'tool_vae')));
        if (!$open) {
            $html .= $output->notification(
                get_string('reflexionclosednote', 'tool_vae'),
                \core\output\notification::NOTIFY_WARNING
            );
        }

        if (!$open) {
            $html .= '';
        } else if (count($entries) < draft_manager::REFLEXION_MAX) {
            $html .= $output->single_button(
                new \moodle_url($url, ['new' => 1, 'sesskey' => sesskey()]),
                get_string('reflexionnew', 'tool_vae'),
                'post'
            );
        } else {
            $html .= $output->notification(
                get_string('errorreflexionfull', 'tool_vae', draft_manager::REFLEXION_MAX),
                \core\output\notification::NOTIFY_INFO
            );
        }

        if (!$entries) {
            $html .= \html_writer::tag('p', s(get_string('reflexionnone', 'tool_vae')));
        } else {
            $table = new \html_table();
            $table->attributes['class'] = 'generaltable tool-vae-journal';
            $table->head = [
                get_string('reflexioncolentry', 'tool_vae'),
                get_string('reflexioncolwritten', 'tool_vae'),
                get_string('reflexioncolstate', 'tool_vae'),
                get_string('faisabcolactions', 'tool_vae'),
            ];
            foreach ($entries as $entry) {
                $edit = new \moodle_url('/admin/tool/vae/section.php', [
                    'doctype' => draft_manager::DOCTYPE_REFLEXION,
                    'section' => draft_manager::REFLEXION_SECTION,
                    'slot' => (int) $entry->slot,
                ]);
                $state = (int) $entry->currentversion > 0
                    ? get_string('sectioncurrentversion', 'tool_vae', (int) $entry->currentversion)
                    : get_string('sectionnoversion', 'tool_vae');
                $table->data[] = [
                    s(draft_manager::label($entry)),
                    s(userdate((int) $entry->timecreated, get_string('strftimedatetime', 'langconfig'))),
                    s($state),
                    ($open ? \html_writer::link($edit, get_string('reflexionopen', 'tool_vae')) . ' ' : '')
                        . \html_writer::link(
                            new \moodle_url($url, ['delete' => (int) $entry->id]),
                            get_string('reflexiondelete', 'tool_vae')
                        ),
                ];
            }
            $html .= \html_writer::table($table);
        }

        return $html . \html_writer::tag('p', \html_writer::link(
            new \moodle_url('/admin/tool/vae/dossier.php'),
            get_string('reflexionbacktodossier', 'tool_vae')
        ));
    }

    /**
     * An entry of the journal, as draft_manager::reflection_entries() gives it.
     *
     * @param int $id
     * @param int $slot
     * @param int $version
     * @return \stdClass
     */
    private static function entry(int $id, int $slot, int $version): \stdClass {
        return (object) ['id' => $id, 'candidateid' => 7, 'doctype' => draft_manager::DOCTYPE_REFLEXION, 'fichecode' => '',
            'section' => draft_manager::REFLEXION_SECTION, 'slot' => $slot, 'currentversion' => $version,
            'timecreated' => 1792411200 + $id];
    }

    /**
     * The states of the page: open, entries.
     *
     * @return array[]
     */
    private static function cases(): array {
        $entries = [1 => self::entry(21, 1, 2), 2 => self::entry(22, 2, 0)];
        $full = [];
        for ($slot = 1; $slot <= draft_manager::REFLEXION_MAX; $slot++) {
            $full[$slot] = self::entry(100 + $slot, $slot, $slot % 3);
        }

        return [
            'open, no entry' => [true, []],
            'open, entries' => [true, $entries],
            'open, full' => [true, $full],
            'closed, entries' => [false, $entries],
            'closed, no entry' => [false, []],
            // A number and a version that are the figure 0 were drawn before.
            'an entry numbered 0' => [true, [0 => self::entry(0, 0, 0)]],
        ];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = new journal_page(true, [], $this->url);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/journal_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();

        foreach (self::cases() as $name => [$open, $entries]) {
            $expected = self::before($open, $entries, $this->url);
            $html = $this->render(new journal_page($open, $entries, $this->url));
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $output = $PAGE->get_renderer('tool_vae');
        $entries = [1 => self::entry(21, 1, 2)];

        $context = (new journal_page(true, $entries, $this->url))->export_for_template($output);
        $this->assertSame(get_string('reflexionprivate', 'tool_vae'), $context['private']);
        $this->assertSame(get_string('reflexionconsigne', 'tool_vae'), $context['consigne']);
        $this->assertSame(get_string('reflexionintro', 'tool_vae'), $context['intro']);
        $this->assertFalse($context['closedhtml']);
        $this->assertStringContainsString(get_string('reflexionnew', 'tool_vae'), $context['starthtml']);
        $this->assertFalse($context['none']);
        $this->assertStringContainsString('tool-vae-journal', $context['tablehtml']);
        $this->assertSame([
            'url' => (new \moodle_url('/admin/tool/vae/dossier.php'))->out(false),
            'label' => get_string('reflexionbacktodossier', 'tool_vae'),
        ], $context['back']);

        $closed = (new journal_page(false, [], $this->url))->export_for_template($output);
        $this->assertStringContainsString(get_string('reflexionclosednote', 'tool_vae'), $closed['closedhtml']);
        $this->assertFalse($closed['starthtml']);
        $this->assertSame(get_string('reflexionnone', 'tool_vae'), $closed['none']);
        $this->assertFalse($closed['tablehtml']);
    }

    public function test_only_the_html_of_the_core_and_of_the_table_is_placed_as_it_is(): void {
        $this->resetAfterTest();
        // Every text of the page is a string of the language file, a number or a date: the cells pass through cell_content.
        $source = file_get_contents(__DIR__ . '/../../templates/journal_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['closedhtml', 'starthtml', 'tablehtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/journal_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/journal_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/journal_page', $context);

        $this->assertStringContainsString('role="note"', $html);
    }
}
