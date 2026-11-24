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

use tool_vae\local\feasibility_catalog;
use tool_vae\local\feasibility_overview;

/**
 * The page "Dossier de faisabilité" (faisabilite.php) drawn by tool_vae\output\feasibility_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration, for every kind of line: a
 * section to come, a single section in each state (empty, draft, version, version and a newer draft, read from the account),
 * a repeated section with its places and the link to add one, a repeated section with no place yet, a version that is the
 * figure 0; and the button to the deposits and the way back to the dossier. The "before" is the code of the page as it was,
 * kept here as the expected value. Also: that the context holds plain texts and addresses, that the template places as HTML
 * only the keys named ...html (no text of the page comes from what a person typed) and renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(feasibility_page::class)]
final class feasibility_page_test extends \advanced_testcase {
    use dom_canon;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/faisabilite.php'));
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param feasibility_page $page
     * @return string HTML.
     */
    private function render(feasibility_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The body of faisabilite.php before its migration, below the heading (its code, kept as the expected value).
     *
     * @param array[] $overview feasibility_overview::for_candidate().
     * @param int $candidateid
     * @return string HTML.
     */
    private static function before(array $overview, int $candidateid): string {
        $statewords = function (array $place): string {
            switch ($place['state']) {
                case feasibility_overview::STATE_DRAFT:
                    return get_string('faisabstatedraft', 'tool_vae');
                case feasibility_overview::STATE_VERSION:
                    return get_string('faisabstateversion', 'tool_vae', $place['version']);
                case feasibility_overview::STATE_VERSION_DRAFT:
                    return get_string('faisabstateversiondraft', 'tool_vae', $place['version']);
                case feasibility_overview::STATE_ACCOUNT:
                    return get_string('faisabstateaccount', 'tool_vae');
                default:
                    return get_string('faisabstateempty', 'tool_vae');
            }
        };

        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-faisabilite';
        $table->caption = get_string('faisabcaption', 'tool_vae');
        $table->captionhide = true;
        $table->head = [
            get_string('faisabcolsection', 'tool_vae'),
            get_string('faisabcolstate', 'tool_vae'),
            get_string('faisabcollast', 'tool_vae'),
            get_string('faisabcolactions', 'tool_vae'),
        ];
        $table->colclasses = ['', '', '', ''];

        foreach ($overview as $code => $entry) {
            $label = get_string('faisabsection:' . $code, 'tool_vae');

            if (!$entry['open']) {
                $table->data[] = [s($label), s(get_string('faisabstateupcoming', 'tool_vae')), '', ''];
                continue;
            }

            foreach ($entry['places'] as $slot => $place) {
                $writeurl = new \moodle_url('/admin/tool/vae/section.php', ['section' => $code, 'slot' => $slot]);
                $name = feasibility_catalog::label($code, $slot);

                $actions = [\html_writer::link($writeurl, get_string(
                    $place['state'] === feasibility_overview::STATE_ACCOUNT ? 'faisabview' : 'faisabwrite',
                    'tool_vae',
                    $name
                ))];
                if ($place['draftid']) {
                    $actions[] = \html_writer::link(
                        new \moodle_url('/admin/tool/vae/history.php', ['draftid' => $place['draftid']]),
                        get_string('faisabhistory', 'tool_vae', $name)
                    );
                }

                $table->data[] = [
                    s($name),
                    s($statewords($place)),
                    $place['modified'] ? s(userdate($place['modified'], get_string('strftimedatetimeshort'))) : '',
                    implode(' · ', $actions),
                ];
            }

            if ($entry['repeated']) {
                if ($entry['places'] === []) {
                    $table->data[] = [s($label), s(get_string('faisabstateempty', 'tool_vae')), '', ''];
                }
                if ($entry['nextslot'] !== null) {
                    $table->data[] = [
                        '',
                        '',
                        '',
                        \html_writer::link(
                            new \moodle_url('/admin/tool/vae/section.php', ['section' => $code, 'slot' => $entry['nextslot']]),
                            get_string('faisabadd', 'tool_vae', $label)
                        ),
                    ];
                }
            }
        }

        return \html_writer::tag('p', s(get_string('faisabintro', 'tool_vae')))
            . \html_writer::table($table)
            . self::core()->single_button(
                new \moodle_url('/admin/tool/vae/deposit.php'),
                get_string('depositheading', 'tool_vae'),
                'get'
            )
            . \html_writer::tag('p', \html_writer::link(
                new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]),
                get_string('faisabbacktodossier', 'tool_vae')
            ));
    }

    /**
     * A place as feasibility_overview gives it.
     *
     * @param int $draftid
     * @param string $state
     * @param int $version
     * @param int $modified
     * @return array
     */
    private static function place(int $draftid, string $state, int $version, int $modified): array {
        return ['draftid' => $draftid, 'state' => $state, 'version' => $version, 'modified' => $modified];
    }

    /**
     * An overview with every kind of line.
     *
     * @return array[]
     */
    private static function overview(): array {
        $section = fn(bool $open, bool $repeated, array $places, ?int $next) => ['kind' => 'text', 'open' => $open,
            'repeated' => $repeated, 'max' => $repeated ? 6 : 0, 'places' => $places, 'nextslot' => $next];

        return [
            'certification' => $section(false, false, [], null),
            'prerequis' => $section(true, false, [0 => self::place(0, feasibility_overview::STATE_EMPTY, 0, 0)], null),
            'identite' => $section(true, false, [0 => self::place(3, feasibility_overview::STATE_DRAFT, 0, 1792411200)], null),
            'contact' => $section(true, false, [0 => self::place(0, feasibility_overview::STATE_ACCOUNT, 0, 0)], null),
            'statut' => $section(true, false, [0 => self::place(4, feasibility_overview::STATE_VERSION, 2, 1792411300)], null),
            'objectifs' => $section(true, false, [0 => self::place(5, feasibility_overview::STATE_VERSION_DRAFT, 3, 1)], null),
            'experiences' => $section(true, true, [
                1 => self::place(6, feasibility_overview::STATE_VERSION, 1, 1792411500),
                2 => self::place(7, feasibility_overview::STATE_DRAFT, 0, 1792411600),
            ], 3),
            'blocs' => $section(true, true, [], 1),
            // A version that is the figure 0 was drawn before; a template that tests the text itself would drop it.
            'parcours' => $section(true, false, [0 => self::place(8, feasibility_overview::STATE_VERSION, 0, 1792411700)], null),
            'engagement' => $section(true, true, [1 => self::place(9, feasibility_overview::STATE_EMPTY, 0, 0)], null),
        ];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = new feasibility_page([], 7);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/feasibility_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();

        foreach (['every kind of line' => self::overview(), 'no section' => []] as $name => $overview) {
            $expected = self::before($overview, 7);
            $html = $this->render(new feasibility_page($overview, 7));
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $output = $PAGE->get_renderer('tool_vae');

        $context = (new feasibility_page(self::overview(), 7))->export_for_template($output);
        $this->assertSame(get_string('faisabintro', 'tool_vae'), $context['intro']);
        $this->assertStringContainsString('tool-vae-faisabilite', $context['tablehtml']);
        $this->assertStringContainsString(' · ', $context['tablehtml']);
        $this->assertStringContainsString('deposit.php', $context['buttonhtml']);
        $this->assertSame([
            'url' => (new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => 7]))->out(false),
            'label' => get_string('faisabbacktodossier', 'tool_vae'),
        ], $context['back']);
    }

    public function test_only_the_html_of_the_core_and_of_the_table_is_placed_as_it_is(): void {
        $this->resetAfterTest();
        // Every text of the page is a string of the language file or a date: the cells pass through cell_content.
        $source = file_get_contents(__DIR__ . '/../../templates/feasibility_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['tablehtml', 'buttonhtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/feasibility_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/feasibility_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/feasibility_page', $context);

        $this->assertStringContainsString('<table', $html);
    }
}
