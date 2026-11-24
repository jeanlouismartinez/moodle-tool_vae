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

use tool_vae\local\ref_manager;

/**
 * The coverage of a dossier, the addresses of the rule of validation ahead of it, and what is outside the activity.
 *
 * WHAT IS ASSERTED (lot 7, MMR-520 ticket 4). That the pieces coverage, coverage_label and scope_box draw the same DOM as
 * presenter::coverage() and presenter::scope_box() drew before their migration (their code kept in the trait
 * presenter_before): no record; records with and without a source, with blocs (a bloc line, a competency set in under it, a
 * competency gone, a short name absent, a date or none), the addresses of the rule shown or not, with a day of check or
 * none; the marks outside the activity with a label or none, a note or none, read by the vaeiste (who is given the way to
 * change them) or not, the site open or closed; values that are the figure 0. That the context holds plain texts, that a
 * script in a label, a title or a note comes out escaped, that the templates place as HTML only their keys ...html and
 * render their examples.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(coverage::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(coverage_label::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(scope_box::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\local\presenter::class)]
final class coverage_test extends \advanced_testcase {
    use dom_canon;
    use presenter_before;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/dossier.php'));
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * A piece drawn by the renderer of the plugin for the web.
     *
     * @param \core\output\renderable $piece
     * @return string HTML.
     */
    private function render(\core\output\renderable $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($piece);
    }

    /**
     * A line of the coverage, as coverage::for_candidate() gives it.
     *
     * @param string $idnumber
     * @param array $fields
     * @return \stdClass
     */
    private static function row(string $idnumber, array $fields = []): \stdClass {
        return (object) ($fields + [
            'idnumber' => $idnumber,
            'shortname' => 'Libellé de ' . $idnumber,
            'gone' => false,
            'declarations' => 2,
            'attested' => 1,
            'lastattested' => 1781136000,
            'isbloc' => false,
            'bloc' => null,
            'blocdeclarations' => 0,
            'blocattested' => 0,
            'bloclastattested' => null,
        ]);
    }

    /**
     * The records of a coverage: one with a source and blocs, one without a source, one of figures 0.
     *
     * @return \stdClass[] Keyed by record code.
     */
    private static function fiches(): array {
        $source = (object) ['fichetype' => 'rs', 'sourceupdated' => 1781136000, 'attribution' => 'France compétences',
            'licence' => 'LO-2.0'];

        return [
            'RS00000' => (object) ['fichecode' => 'RS00000', 'source' => $source, 'hasblocs' => true, 'competencies' => [
                self::row('RS00000-BC01', ['isbloc' => true, 'blocdeclarations' => 3, 'blocattested' => 2,
                    'bloclastattested' => 1782172800, 'gone' => true]),
                self::row('RS00000-C02', ['bloc' => 'RS00000-BC01']),
                self::row('RS00000-C10', ['bloc' => 'RS00000-BC01', 'gone' => true, 'lastattested' => null, 'attested' => 0]),
                self::row('RS00000-Z01', ['shortname' => null]),
                self::row('RS00000-Z02', ['shortname' => '']),
            ]],
            'RNCP100' => (object) ['fichecode' => 'RNCP100', 'source' => null, 'hasblocs' => false, 'competencies' => [
                self::row('RNCP100-C01'),
            ]],
            '0' => (object) ['fichecode' => '0', 'source' => (object) ['fichetype' => 'rncp', 'sourceupdated' => 0,
                'attribution' => '0', 'licence' => '0'], 'hasblocs' => false, 'competencies' => [
                self::row('0', ['shortname' => '0', 'declarations' => 0, 'attested' => 0, 'lastattested' => 0]),
            ]],
        ];
    }

    /**
     * The records the addresses of the rule are given to: an address is kept only for a record imported on the site.
     */
    private function records(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_vae');
        $generator->create_source(['fichecode' => 'RS00000', 'fichetype' => 'rs']);
        $generator->create_source(['fichecode' => 'RNCP100', 'fichetype' => 'rncp']);
    }

    public function test_the_pieces_are_named_templates(): void {
        global $PAGE;
        $renderer = $PAGE->get_renderer('tool_vae');
        $pieces = [
            'tool_vae/coverage' => new coverage([]),
            'tool_vae/coverage_label' => new coverage_label('X', null, false, false),
            'tool_vae/scope_box' => new scope_box([], [], false, false),
        ];
        foreach ($pieces as $name => $piece) {
            $this->assertInstanceOf(\core\output\renderable::class, $piece);
            $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
            $this->assertSame($name, $piece->get_template_name($renderer));
        }
    }

    public function test_the_coverage_draws_the_dom_of_before(): void {
        $fiches = self::fiches();
        $this->records();
        ref_manager::add('RS00000', 'Règle du certificateur', 'https://example.org/regle', gmmktime(12, 0, 0, 10, 20, 2026));
        ref_manager::add('RS00000', 'Arrêté', 'https://exemple.test/arrete?a=1&b=2');
        ref_manager::add('RNCP100', '0', 'https://xn--exmple-cva.test/');

        foreach (['closed' => 0, 'open' => 1] as $name => $open) {
            set_config('refopen', $open, 'tool_vae');
            $html = $this->render(new coverage($fiches));
            $this->assertSame(self::canon(self::before_coverage($fiches)), self::canon($html), $name);
            foreach ($fiches as $code => $fiche) {
                $one = [$code => $fiche];
                $this->assertSame(self::canon(self::before_coverage($one)), self::canon($this->render(new coverage($one))), $code);
            }
        }
        $this->assertStringContainsString('tool-vae-rule-refs', $this->render(new coverage($fiches)), 'The premise: the box.');
        // The links of a record are joined as before, and the day follows its link after a space.
        $html = $this->render(new coverage($fiches));
        $this->assertMatchesRegularExpression('#</a>( [^<]+)? ; <a #', $html);
        $this->assertSame('', $this->render(new coverage([])), 'No record, nothing drawn.');
    }

    public function test_the_label_of_a_line_is_the_html_of_before(): void {
        $cases = [
            ['RS00000-C02', 'Aider au lever', false, false],
            ['RS00000-C02', null, true, false],
            ['RS00000-BC01', 'Accompagner', false, true],
            ['RS00000-BC01', '', true, true],
            ['0', '0', false, false],
        ];
        foreach ($cases as [$code, $short, $gone, $isbloc]) {
            $label = s($code);
            if ($short !== null) {
                $label .= ' — ' . s($short);
            }
            if ($gone) {
                $label .= \html_writer::div(s(get_string('coveragegone', 'tool_vae')), 'small');
            }
            if ($isbloc) {
                $label = \html_writer::tag('strong', $label) . ' '
                    . \html_writer::tag('span', s(get_string('coveragebloctag', 'tool_vae')), ['class' => 'small text-muted']);
            }
            $this->assertSame($label, $this->render(new coverage_label($code, $short, $gone, $isbloc)), $code);
        }
    }

    public function test_the_marks_outside_the_activity_draw_the_dom_of_before(): void {
        $marks = [
            'RS00000-C02' => (object) ['timecreated' => 1781136000, 'note' => 'Je ne fais pas de nuit.'],
            'RS00000-C10' => (object) ['timecreated' => 1781136000, 'note' => null],
            'RS00000-Z01' => (object) ['timecreated' => 1781136000, 'note' => ''],
            '0' => (object) ['timecreated' => 1781136000, 'note' => '0'],
        ];
        $labels = ['RS00000-C02' => 'Aider au lever', 'RS00000-C10' => '', '0' => '0'];
        foreach ([[], $marks] as $list) {
            foreach ([false, true] as $isown) {
                foreach ([false, true] as $open) {
                    $name = count($list) . ' marks, ' . ($isown ? 'own' : 'read') . ', ' . ($open ? 'open' : 'closed');
                    $this->assertSame(
                        self::canon(self::before_scope_box($list, $labels, $isown, $open)),
                        self::canon($this->render(new scope_box($list, $labels, $isown, $open))),
                        $name
                    );
                }
            }
        }
        $this->assertSame('', $this->render(new scope_box([], [], true, false)), 'Nothing for a vaeiste on a closed site.');
        $this->assertStringContainsString(' — Aider au lever ', $this->render(new scope_box($marks, $labels, false, false)));
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');

        $this->assertFalse((new scope_box([], [], false, true))->export_for_template($output)['show']);
        $marks = ['A&B' => (object) ['timecreated' => 1781136000, 'note' => 'x & y']];
        $exported = (new scope_box($marks, ['A&B' => 'L & M'], true, true))->export_for_template($output);
        $this->assertTrue($exported['show']);
        $this->assertSame('A&B', $exported['marks'][0]['key']);
        $this->assertSame(['text' => 'L & M'], $exported['marks'][0]['label']);
        $this->assertSame(['text' => 'x & y'], $exported['marks'][0]['note']);
        $this->assertSame((new \moodle_url('/admin/tool/vae/scope.php'))->out(false), $exported['manage']['url']);

        $label = (new coverage_label('A&B', 'C & D', true, true))->export_for_template($output);
        $this->assertSame('A&B', $label['code']);
        $this->assertSame(['text' => 'C & D'], $label['short']);
        $this->assertSame(['text' => get_string('coveragegone', 'tool_vae')], $label['gone']);
        $this->assertTrue($label['isbloc']);

        $this->assertFalse((new coverage([]))->export_for_template($output)['show']);
    }

    public function test_a_script_in_a_label_a_title_or_a_note_comes_out_escaped(): void {
        $script = '<script>alert(1)</script>';
        set_config('refopen', 1, 'tool_vae');
        $this->records();
        ref_manager::add('RNCP100', $script, 'https://example.org/x');
        $fiches = ['RNCP100' => (object) ['fichecode' => 'RNCP100', 'source' => null, 'hasblocs' => false, 'competencies' => [
            self::row('RNCP100-C01', ['shortname' => $script]),
        ]]];
        $marks = [$script => (object) ['timecreated' => 1781136000, 'note' => $script]];

        $html = $this->render(new coverage($fiches)) . $this->render(new scope_box($marks, [$script => $script], true, true));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_only_the_keys_of_html_are_placed_as_they_are(): void {
        $allowed = [
            'coverage' => ['headinghtml', 'titlehtml', 'provenancehtml', 'tablehtml'],
            'coverage_label' => [],
            'scope_box' => ['headinghtml'],
        ];
        foreach ($allowed as $name => $keys) {
            $source = file_get_contents(__DIR__ . '/../../templates/' . $name . '.mustache');
            preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
            $this->assertSame($keys, array_values(array_unique($raw[1])), $name);
            $this->assertSame(substr_count($source, '{{{'), count($raw[1]), $name);
        }
    }

    public function test_the_templates_render_their_example_context(): void {
        $expected = ['coverage' => 'tool-vae-coverage', 'coverage_label' => '<strong>', 'scope_box' => 'tool-vae-scope'];
        foreach ($expected as $name => $needle) {
            $file = __DIR__ . '/../../templates/' . $name . '.mustache';
            $this->assertFileExists($file);
            $source = file_get_contents($file);
            $this->assertStringContainsString('@template tool_vae/' . $name, $source);
            $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches), $name);
            $context = json_decode($matches[1]);
            $this->assertIsObject($context, $name . ' ' . json_last_error_msg());

            $this->assertStringContainsString($needle, self::core()->render_from_template('tool_vae/' . $name, $context), $name);
        }
    }
}
