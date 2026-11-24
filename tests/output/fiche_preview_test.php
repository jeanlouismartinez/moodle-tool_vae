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
require_once(__DIR__ . '/fiche_ai_before.php');

use tool_vae\local\fiche_refusal;

/**
 * The preview and the result of an import of records drawn by tool_vae\output\fiche_preview, fiche_cell and fiche_results.
 *
 * WHAT IS ASSERTED (lot 7, MMR-520 ticket 4). That each plan draws the same DOM as fiche_screen::preview() and
 * fiche_screen::results() drew before their migration (their code is kept in the trait fiche_ai_before): every state of a
 * record in Moodle, every refusal, every warning, both origins of the export, a held record (the note of the gone), nothing
 * ready, a record without title, every result; a text equal to "0" wherever a text comes from the export; a script wherever
 * a text comes from the export or from a typed number. Also: the contexts in clear, the keys placed as HTML, the examples.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(fiche_preview::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(fiche_cell::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(fiche_results::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\local\fiche_screen::class)]
final class fiche_preview_test extends \advanced_testcase {
    use dom_canon;
    use fiche_ai_before;

    /** @var int The day of the tests: 27 October 2026, noon, in the timezone of the reader. */
    private int $now;

    #[\Override]
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/import_fiches.php'));
        $PAGE->set_context(\context_system::instance());
        $this->now = make_timestamp(2026, 10, 27, 12, 0, 0, 99);
    }

    /**
     * A piece drawn by the renderer of the plugin.
     *
     * @param \core\output\renderable $piece
     * @return string HTML.
     */
    private function render(\core\output\renderable $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($piece);
    }

    /**
     * A record of a plan, as the orchestrator builds it.
     *
     * @param array $override
     * @return \stdClass
     */
    private static function record(array $override = []): \stdClass {
        return (object) ($override + [
            'number' => 'RNCP99001', 'type' => 'rncp', 'status' => 'ready', 'refusal' => null,
            'title' => 'Diplôme inventé d\'accompagnement', 'name' => 'Diplôme inventé d\'accompagnement',
            'active' => 'ACTIVE', 'enddate' => '31/08/2030', 'blocs' => 2,
            'rows' => [
                (object) ['rang' => 'BC01', 'intitule' => 'Premier bloc', 'description' => ''],
                (object) [
                    'rang' => 'BC02', 'intitule' => 'Bloc BC02',
                    'description' => 'Un libellé publié bien plus long que cent caractères',
                ],
            ],
            'long' => ['BC02'], 'markup' => [], 'state' => 'new', 'heldts' => null, 'declared' => 0,
            'forecast' => (object) ['created' => 2, 'updated' => 0, 'gone' => 0, 'diverged' => 0], 'older' => false,
        ]);
    }

    /**
     * A refused record.
     *
     * @param string $number
     * @param string $reason
     * @param array $params
     * @return \stdClass
     */
    private static function refused(string $number, string $reason, array $params = []): \stdClass {
        return self::record([
            'number' => $number, 'status' => 'refused',
            'refusal' => (object) ['reason' => $reason, 'params' => (object) ($params + ['number' => $number])],
        ]);
    }

    /**
     * A plan, as the orchestrator builds it.
     *
     * @param \stdClass[] $records
     * @param array $override
     * @return \stdClass
     */
    private function plan(array $records, array $override = []): \stdClass {
        return (object) ($override + [
            'version' => 1, 'userid' => 2, 'created' => $this->now, 'token' => str_repeat('a', 32), 'origin' => 'upload',
            'originname' => 'export-fiches-csv-2026-10-19.zip', 'bytes' => 9704180, 'sha256' => str_repeat('ab', 32),
            'exportdate' => '2026-10-19', 'exportts' => make_timestamp(2026, 10, 19, 0, 0, 0, 99),
            'innerdates' => (object) ['blocs' => '2026-10-18', 'standard' => '2026-10-18'], 'resource' => null,
            'numbers' => array_map(fn($record) => $record->number, $records), 'repeated' => [], 'records' => $records,
            'results' => null,
        ]);
    }

    /**
     * Every plan a preview can be given, by name.
     *
     * @return \stdClass[]
     */
    private function plans(): array {
        $held = make_timestamp(2026, 10, 1, 0, 0, 0, 99);
        $old = make_timestamp(2026, 9, 1, 0, 0, 0, 99);

        return [
            'one new record' => $this->plan([self::record()]),
            'every state in Moodle' => $this->plan([
                self::record(['number' => 'RNCP90001', 'state' => 'new']),
                self::record([
                    'number' => 'RNCP90002', 'state' => 'held', 'heldts' => $held, 'declared' => 3,
                    'forecast' => (object) ['created' => 1, 'updated' => 2, 'gone' => 3, 'diverged' => 4],
                ]),
                self::record([
                    'number' => 'RNCP90003', 'state' => 'held', 'heldts' => $held,
                    'forecast' => (object) ['created' => 0, 'updated' => 0, 'gone' => 0, 'diverged' => 0],
                ]),
                self::record(['number' => 'RNCP90004', 'state' => 'rebuild', 'heldts' => $held]),
                self::record(['number' => 'RNCP90005', 'state' => 'adopted', 'forecast' => null]),
            ]),
            'states and ends as published' => $this->plan([
                self::record(['number' => 'RNCP90006', 'active' => 'INACTIVE', 'enddate' => '31/12/2020']),
                self::record(['number' => 'RNCP90007', 'active' => 'SUSPENDUE', 'enddate' => 'bientôt']),
                self::record(['number' => 'RNCP90008', 'active' => null, 'enddate' => null]),
                self::record(['number' => 'RNCP90009', 'active' => '', 'enddate' => '27/10/2026']),
            ]),
            'older than what the site holds' => $this->plan([
                self::record([
                    'number' => 'RNCP90010', 'state' => 'held', 'heldts' => make_timestamp(2026, 11, 15, 0, 0, 0, 99),
                    'older' => true,
                ]),
            ]),
            'shortened, marked up, renamed, no title' => $this->plan([
                self::record(['number' => 'RNCP90011', 'long' => ['BC02', 'BC03'], 'markup' => ['BC01']]),
                self::record(['number' => 'RNCP90012', 'name' => 'RNCP90012', 'title' => "  Titre \n très   long  "]),
                self::record(['number' => 'RNCP90013', 'title' => '', 'name' => '', 'rows' => [], 'blocs' => 0, 'long' => []]),
            ]),
            'every refusal, nothing ready' => $this->plan([
                self::refused('RNCP00000', fiche_refusal::NOT_FOUND),
                self::refused('RNCP90014', fiche_refusal::NO_BLOC),
                self::refused('RNCP90015', fiche_refusal::NO_BLOC_ACTIVE),
                self::refused('RNCP90016', fiche_refusal::BAD_BLOC, ['codes' => 'BC9']),
            ]),
            'a refusal next to a ready record' => $this->plan([
                self::record(),
                self::refused('RNCP90017', fiche_refusal::NO_BLOC),
            ]),
            'every warning, fetched' => $this->plan([self::record()], [
                'origin' => 'fetch', 'originname' => 'export-fiches-csv-2026-10-01.zip',
                'exportts' => $old, 'innerdates' => (object) ['blocs' => '2026-08-01', 'standard' => '2026-07-31'],
                'repeated' => ['RNCP99001', 'RNCP99002'],
            ]),
            'an old export, uploaded' => $this->plan([self::record()], ['exportts' => $old,
                'innerdates' => (object) ['blocs' => '2026-09-01', 'standard' => '2026-09-01']]),
            'texts equal to 0' => $this->plan([
                self::record([
                    'number' => '0', 'title' => '0', 'name' => 'x', 'active' => '0', 'enddate' => '0',
                    'rows' => [(object) ['rang' => '0', 'intitule' => '0', 'description' => ''],
                        (object) ['rang' => 'BC02', 'intitule' => 'x', 'description' => '0']],
                    'long' => ['0'], 'markup' => ['0'],
                ]),
            ], ['originname' => '0', 'repeated' => ['0']]),
        ];
    }

    /**
     * Every plan with its results, by name.
     *
     * @return \stdClass[]
     */
    private function results(): array {
        return [
            'every result' => $this->plan([self::record()], ['results' => [
                (object) ['number' => 'RNCP99001', 'status' => 'imported', 'html' => '4 competencies added.'],
                (object) ['number' => 'RNCP99002', 'status' => 'failed', 'html' => 'The framework is not ours.'],
                (object) ['number' => 'RNCP99003', 'status' => 'older', 'html' => ''],
                (object) ['number' => 'RNCP99004', 'status' => 'refused', 'html' => 'No bloc &amp; more.'],
                (object) ['number' => 'RNCP99005', 'status' => 'notrun', 'html' => ''],
            ]]),
            'nothing imported' => $this->plan([self::record()], ['results' => [
                (object) ['number' => 'RNCP99004', 'status' => 'refused', 'html' => 'No bloc.'],
            ]]),
            'no result' => $this->plan([self::record()], ['results' => []]),
            'a number equal to 0' => $this->plan([self::record()], ['results' => [
                (object) ['number' => '0', 'status' => 'imported', 'html' => '0'],
                (object) ['number' => '0', 'status' => 'older', 'html' => ''],
            ]]),
        ];
    }

    public function test_the_pieces_are_named_templates(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');
        $plan = $this->plan([self::record()]);

        foreach (
            [
                'tool_vae/fiche_preview' => new fiche_preview($plan, $this->now),
                'tool_vae/fiche_results' => new fiche_results($plan),
                'tool_vae/fiche_cell' => fiche_cell::record('RNCP1', null),
            ] as $name => $piece
        ) {
            $this->assertInstanceOf(\core\output\renderable::class, $piece);
            $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
            $this->assertSame($name, $piece->get_template_name($output));
        }
    }

    public function test_each_preview_draws_the_dom_of_before(): void {
        foreach ($this->plans() as $name => $plan) {
            $this->assertSame(
                self::canon(self::before_fiche_preview($plan, $this->now)),
                self::canon($this->render(new fiche_preview($plan, $this->now))),
                $name
            );
        }
    }

    public function test_a_title_made_of_spaces_keeps_its_empty_line_as_before(): void {
        // Added by the pilot of lot 7: only the empty title has no line; a title of spaces alone, which the export may
        // hold, had an empty one, and no plan above told the two apart.
        $plan = $this->plan([self::record(['number' => 'RNCP90020', 'title' => " \n  ", 'name' => 'RNCP90020'])]);

        $before = self::before_fiche_preview($plan, $this->now);

        $this->assertSame(self::canon($before), self::canon($this->render(new fiche_preview($plan, $this->now))));
        $this->assertStringContainsString('<strong>RNCP90020</strong><div></div>', $before, 'Premise: the empty line.');
    }

    public function test_the_premises_of_the_plans_hold(): void {
        $plans = $this->plans();
        $draw = fn(string $name) => self::before_fiche_preview($plans[$name], $this->now);

        $this->assertStringContainsString('alert-danger', $draw('every refusal, nothing ready'));
        $this->assertStringNotContainsString('alert-danger', $draw('a refusal next to a ready record'));
        $this->assertSame(5, substr_count($draw('every warning, fetched'), 'alert-warning'), 'Stale, gap, dates, two repeated.');
        $this->assertSame(1, substr_count($draw('an old export, uploaded'), 'alert-warning'));
        $this->assertStringContainsString(get_string('fcimportgonenote', 'tool_vae'), $draw('every state in Moodle'));
        $this->assertStringNotContainsString(get_string('fcimportgonenote', 'tool_vae'), $draw('one new record'));
        $this->assertStringContainsString(
            get_string('fcimportnotetitle', 'tool_vae'),
            $draw('shortened, marked up, renamed, no title')
        );
        $this->assertStringContainsString(
            get_string('fcimportendregpast', 'tool_vae', '31/12/2020'),
            $draw('states and ends as published')
        );
        $this->assertStringContainsString(get_string('fcimportolderskip', 'tool_vae'), $draw('older than what the site holds'));
        $this->assertStringContainsString('<strong>0</strong>', $draw('texts equal to 0'));
        $this->assertStringContainsString('<div>0</div>', $draw('texts equal to 0'));
    }

    public function test_each_result_draws_the_dom_of_before(): void {
        foreach ($this->results() as $name => $plan) {
            $this->assertSame(
                self::canon(self::before_fiche_results($plan)),
                self::canon($this->render(new fiche_results($plan))),
                $name
            );
        }
        $this->assertStringContainsString('supersession.php', self::before_fiche_results($this->results()['every result']));
        $this->assertStringNotContainsString('supersession.php', self::before_fiche_results($this->results()['nothing imported']));
    }

    public function test_what_comes_from_the_export_leaves_escaped_as_before(): void {
        $script = '<script>alert(1)</script>';
        $hostile = self::record([
            'number' => $script, 'title' => $script, 'active' => $script, 'enddate' => $script,
            'rows' => [(object) ['rang' => $script, 'intitule' => $script, 'description' => ''],
                (object) ['rang' => 'BC02', 'intitule' => 'x', 'description' => $script]],
            'long' => [$script], 'markup' => [$script],
        ]);
        $refused = self::refused('RNCP99003', fiche_refusal::BAD_BLOC, ['codes' => $script]);
        $plan = $this->plan([$hostile, $refused], ['originname' => $script, 'repeated' => [$script]]);

        $html = $this->render(new fiche_preview($plan, $this->now));

        $this->assertStringNotContainsString('<script', $html);
        // The name of the file, the title, the state, the end, two ranks, two labels, the long and marked up ranks, the
        // number typed twice (its warning), the number of the record, the codes of the refusal: each once.
        $this->assertSame(12, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
        $this->assertSame(self::canon(self::before_fiche_preview($plan, $this->now)), self::canon($html));

        $results = $this->plan([self::record()], ['results' => [
            (object) ['number' => $script, 'status' => 'imported', 'html' => 'ok'],
            (object) ['number' => $script, 'status' => 'failed', 'html' => 'no'],
            (object) ['number' => $script, 'status' => 'older', 'html' => ''],
            (object) ['number' => $script, 'status' => 'refused', 'html' => 'no'],
            (object) ['number' => $script, 'status' => 'notrun', 'html' => ''],
        ]]);
        $html = $this->render(new fiche_results($results));
        $this->assertStringNotContainsString('<script', $html);
        $this->assertSame(5, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
        $this->assertSame(self::canon(self::before_fiche_results($results)), self::canon($html));
    }

    public function test_the_contexts_hold_plain_texts(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');
        // An export of the day before, whose files carry its day: the only warning is the number typed twice.
        $plan = $this->plan([self::record()], [
            'originname' => 'a<b>.zip', 'repeated' => ['R&D'], 'exportts' => make_timestamp(2026, 10, 26, 0, 0, 0, 99),
            'innerdates' => (object) ['blocs' => '2026-10-26', 'standard' => '2026-10-26'],
        ]);

        $context = (new fiche_preview($plan, $this->now))->export_for_template($output);

        $this->assertStringContainsString('a<b>.zip', $context['source']);
        $this->assertSame([['text' => get_string('fcimportwarnrepeated', 'tool_vae', 'R&D')]], $context['warnings']);
        $this->assertSame(get_string('fcimportcheck', 'tool_vae'), $context['check']);
        $this->assertFalse($context['none']);
        $this->assertFalse($context['gonenote']);
        $this->assertSame(get_string('fcimportvisible', 'tool_vae'), $context['visible']);
        $this->assertStringContainsString('<table', $context['tablehtml']);
        $this->assertStringContainsString('href="https://www.data.gouv.fr/datasets/', $context['provenancehtml']);

        $cell = fiche_cell::record('RNCP1', null)->export_for_template($output);
        $this->assertSame(['number' => 'RNCP1', 'title' => false], $cell['record']);
        $this->assertSame(
            ['classed' => ['name' => 'small'], 'text' => ['label' => 'a<b'], 'html' => false],
            fiche_cell::line('small', 'a<b')
        );
        $this->assertSame(['classed' => false, 'text' => false, 'html' => ['texthtml' => 'x']], fiche_cell::html('', 'x'));

        $results = (new fiche_results($this->results()['every result']))->export_for_template($output);
        $this->assertCount(5, $results['items']);
        $this->assertStringContainsString('supersession.php', $results['nexthtml']);
        $this->assertFalse((new fiche_results($this->results()['nothing imported']))->export_for_template($output)['nexthtml']);
    }

    public function test_only_the_keys_of_html_are_placed_as_they_are(): void {
        $expected = [
            'fiche_preview' => ['tablehtml', 'provenancehtml'],
            'fiche_cell' => ['texthtml'],
            'fiche_results' => ['texthtml', 'nexthtml'],
        ];
        foreach ($expected as $template => $keys) {
            $source = file_get_contents(__DIR__ . '/../../templates/' . $template . '.mustache');
            preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
            $this->assertSame($keys, array_values(array_unique($raw[1])), $template);
            $this->assertSame(substr_count($source, '{{{'), count($raw[1]), $template);
        }
    }

    public function test_the_templates_render_their_example_context(): void {
        foreach (['fiche_preview' => '<table', 'fiche_cell' => '<strong>', 'fiche_results' => '<ul'] as $template => $mark) {
            $source = file_get_contents(__DIR__ . '/../../templates/' . $template . '.mustache');
            $this->assertStringContainsString('@template tool_vae/' . $template, $source);
            $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches), $template);
            $context = json_decode($matches[1]);
            $this->assertIsObject($context, $template . ': ' . json_last_error_msg());

            $html = self::core()->render_from_template('tool_vae/' . $template, $context);

            $this->assertStringContainsString($mark, $html, $template);
        }
    }
}
