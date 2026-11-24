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

use tool_vae\form\certification_form;
use tool_vae\form\evaluation_form;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/positioning_before.php');

/**
 * The pages certify.php and evaluate.php, under their heading (lot 7, MMR-520 ticket 4, wave 3a).
 *
 * WHAT IS ASSERTED. That positioning_page::certify() and ::evaluate() draw the same DOM as the two pages drew before their
 * migration (their code from under the heading to the footer, kept here in before()), with and without the form, with and
 * without the error of a send, with and without the competencies that are not yet sufficient (certify), with no line and
 * with lines, each state with a premise; the figure 0 and special characters; that the error and the lacks are escaped
 * before the notification of the core, which does not escape (a script comes out escaped); that the template places HTML
 * only under the keys named and renders its example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(positioning_page::class)]
final class positioning_page_test extends \advanced_testcase {
    use dom_canon;
    use positioning_before;

    /** @var \context The context of the candidate. */
    private $context;

    /** @var \moodle_url The dossier. */
    private $dossier;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/certify.php'));
        $PAGE->set_context(\context_system::instance());
        $candidate = $this->getDataGenerator()->create_user();
        $this->context = \context_user::instance($candidate->id);
        $this->dossier = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidate->id]);
    }

    /**
     * The two pages before lot 7, from under the heading to the footer (certify.php and evaluate.php, the same code but the
     * kind, the sentence above the form and the alert of certify.php).
     *
     * @param string $kind certif or eval.
     * @param string $fichecode
     * @param string $label
     * @param string|null $error
     * @param array $lacking What $manager->alert() gave (certify.php only).
     * @param \moodleform|null $form
     * @param array $rows What $manager->lines() gave.
     * @return string
     */
    private function before(
        string $kind,
        string $fichecode,
        string $label,
        ?string $error,
        array $lacking,
        ?\moodleform $form,
        array $rows
    ): string {
        $context = $this->context;
        $dossier = $this->dossier;
        $out = '';
        $out .= \html_writer::tag('p', s(get_string('positioningdisclaimer', 'tool_vae')), ['class' => 'text-muted small']);
        $out .= \html_writer::tag('p', \html_writer::tag('strong', s($fichecode)) . ' - ' . s($label));
        if ($error !== null) {
            $out .= self::core()->notification(s($error), \core\output\notification::NOTIFY_ERROR);
        }
        if ($kind === 'certif') {
            if ($lacking) {
                $out .= self::core()->notification(
                    s(get_string('positioningalert', 'tool_vae', implode(', ', $lacking))),
                    \core\output\notification::NOTIFY_WARNING
                );
            }
        }
        if ($form) {
            $intro = $kind === 'certif' ? 'positioningcertifyintro' : 'positioningevaluateintro';
            $out .= \html_writer::tag('p', s(get_string($intro, 'tool_vae')));
            $out .= $form->render();
        }
        $out .= self::core()->heading(get_string('positioninghistory', 'tool_vae'), 3);
        $out .= self::before_pos_history($rows, $context, $kind);
        $out .= \html_writer::tag('p', \html_writer::link($dossier, get_string('positioningback', 'tool_vae')));

        return $out;
    }

    /**
     * The page drawn by the renderer of the plugin for the web.
     *
     * @param positioning_page $page
     * @return string
     */
    private function render(positioning_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * A form of the kind, new each time (a form is drawn once).
     *
     * @param string $kind
     * @return \moodleform
     */
    private static function form(string $kind): \moodleform {
        $url = '/admin/tool/vae/' . ($kind === 'certif' ? 'certify' : 'evaluate') . '.php';

        return $kind === 'certif' ? new certification_form($url) : new evaluation_form($url);
    }

    /**
     * A line of the history.
     *
     * @param string $status
     * @param int $time
     * @return \stdClass
     */
    private static function row(string $status, int $time): \stdClass {
        return (object) [
            'status' => $status, 'reason' => 'Motif ' . $time, 'authorid' => null, 'importedat' => null,
            'timecreated' => $time, 'decisiondate' => '2026-11-04', 'alertreason' => null, 'validuntil' => null,
        ];
    }

    /**
     * The states: name => [kind, fichecode, label, error, lacking, has a form, rows].
     *
     * @return array[]
     */
    private static function states(): array {
        $certified = [self::row('certified', 1762000000), self::row('withdrawn', 1762000100)];
        $evaluated = [self::row('inproof', 1762000000)];

        return [
            'certify: the direction, everything' => ['certif', 'RNCP100', 'Bloc 1', 'Une erreur & une autre.',
                ['C01' => 'Aide au repas', 'C02' => 'Aide & soins'], true, $certified],
            'certify: a reader, nothing' => ['certif', 'RS200', 'The whole record', null, [], false, []],
            'certify: the direction, no lack, no error' => ['certif', 'RNCP100', 'Bloc 2', null, [], true, $certified],
            'certify: a reader with lacks' => ['certif', 'RNCP100', 'Bloc 1', null, ['C01' => 'Aide'], false, $certified],
            'evaluate: the manager, an error' => ['eval', 'RNCP100', 'Competence C01', 'Refus.', [], true, $evaluated],
            'evaluate: a reader' => ['eval', 'RNCP100', 'Competence C01', null, [], false, $evaluated],
            'evaluate: no line' => ['eval', 'RS200', 'Une competence', null, [], true, []],
            'the figure 0' => ['certif', '0', '0', '0', ['C01' => '0'], true, []],
        ];
    }

    /**
     * The page of a state, by its named constructor.
     *
     * @param array $state
     * @return positioning_page
     */
    private function page(array $state): positioning_page {
        [$kind, $code, $label, $error, $lacking, $hasform, $rows] = $state;
        $form = $hasform ? self::form($kind) : null;

        return $kind === 'certif'
            ? positioning_page::certify($code, $label, $error, $lacking, $form, $rows, $this->context, $this->dossier)
            : positioning_page::evaluate($code, $label, $error, $form, $rows, $this->context, $this->dossier);
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $page = positioning_page::evaluate('RNCP100', 'C01', null, null, [], $this->context, $this->dossier);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/positioning_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        foreach (self::states() as $name => $state) {
            [$kind, $code, $label, $error, $lacking, $hasform, $rows] = $state;
            $before = $this->before($kind, $code, $label, $error, $lacking, $hasform ? self::form($kind) : null, $rows);
            $after = $this->render($this->page($state));

            $this->assertSame(self::drafts(self::canon($before)), self::drafts(self::canon($after)), $name);
            // Premises: what the state changes is drawn by the old code.
            $this->assertSame($error !== null, str_contains($before, 'alert-danger'), $name . ': the error');
            $this->assertSame($kind === 'certif' && $lacking !== [], str_contains($before, 'alert-warning'), $name . ': the lacks');
            $this->assertSame($hasform, str_contains($before, '<form'), $name . ': the form');
            $intro = s(get_string($kind === 'certif' ? 'positioningcertifyintro' : 'positioningevaluateintro', 'tool_vae'));
            $this->assertSame($hasform, str_contains($before, $intro), $name . ': the sentence above the form');
            $this->assertSame($rows === [], str_contains($before, s(get_string('positioningnoline', 'tool_vae'))), $name);
        }
    }

    public function test_the_kind_of_the_history_follows_the_page(): void {
        $rows = [self::row('certified', 1762000000)];
        $certify = $this->render(positioning_page::certify('R', 'B', null, [], null, $rows, $this->context, $this->dossier));
        $rows = [self::row('inproof', 1762000000)];
        $evaluate = $this->render(positioning_page::evaluate('R', 'C', null, null, $rows, $this->context, $this->dossier));

        $this->assertStringContainsString(s(get_string('certifstatuscertified', 'tool_vae')), $certify);
        $this->assertStringContainsString(s(get_string('positioningcertifiedon', 'tool_vae', '2026-11-04')), $certify);
        $this->assertStringContainsString(s(get_string('evalstatusinproof', 'tool_vae')), $evaluate);
        $this->assertStringNotContainsString(s(get_string('positioningcertifiedon', 'tool_vae', '2026-11-04')), $evaluate);
    }

    public function test_a_script_in_the_error_or_a_lack_comes_out_escaped(): void {
        $label = '<script>alert(1)</script>';
        $error = '<script>alert(2)</script>';
        $lacking = ['C01' => '<script>alert(3)</script>'];
        $page = positioning_page::certify('RNCP100', $label, $error, $lacking, null, [], $this->context, $this->dossier);
        $before = $this->before('certif', 'RNCP100', $label, $error, $lacking, null, []);

        $after = $this->render($page);

        $this->assertSame(self::canon($before), self::canon($after));
        $this->assertSame(3, substr_count($after, '&lt;script&gt;alert('));
        $this->assertStringNotContainsString('<script', $after);
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $page = positioning_page::evaluate('RNCP100', 'A & B', null, null, [], $this->context, $this->dossier);

        $exported = $page->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertSame('A & B', $exported['label']);
        $this->assertSame('RNCP100', $exported['fichecode']);
        $this->assertSame(get_string('positioningdisclaimer', 'tool_vae'), $exported['disclaimer']);
        $this->assertSame($this->dossier->out(false), $exported['backurl']);
        $this->assertSame('', $exported['errorhtml']);
        $this->assertSame('', $exported['alerthtml']);
        $this->assertNull($exported['form']);
    }

    public function test_the_template_places_html_only_under_the_keys_named_and_renders_its_example(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/positioning_page.mustache');
        $this->assertStringContainsString('@template tool_vae/positioning_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $placed);
        $this->assertSame(
            ['errorhtml', 'alerthtml', 'formhtml', 'historyheadinghtml', 'historyhtml'],
            array_values(array_unique($placed[1]))
        );
        $this->assertSame(substr_count($source, '{{{'), count($placed[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/positioning_page', $context);
        $this->assertStringContainsString('text-muted small', $html);
    }
}
