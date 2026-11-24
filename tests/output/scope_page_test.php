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

use tool_vae\form\scope_form;
use tool_vae\local\display_date;

/**
 * The page "Hors de mon activité" (scope.php) drawn by tool_vae\output\scope_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration: the setting closed with
 * nothing said (the only state of the bench), and, in no instant of the tool of equivalence, marks in a table with their
 * buttons, the setting open with the form of the core, open with no record imported, an error of the last sending; the way
 * back to the dossier always last. The "before" is the code of the page as it was, kept here as the expected value. Also:
 * that the context holds plain texts, that a script in a note or a label comes out escaped, that the template renders its
 * example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(scope_page::class)]
final class scope_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    /** @var \moodle_url The dossier of the vaeiste. */
    private \moodle_url $dossier;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->url = new \moodle_url('/admin/tool/vae/scope.php');
        $this->dossier = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => 7]);
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * A form of the page, as the page builds it.
     *
     * @return scope_form
     */
    private function form(): scope_form {
        return new scope_form($this->url, ['options' => ['RNCP1-C1' => 'RNCP1-C1 — Accueillir']]);
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param scope_page $page
     * @return string HTML.
     */
    private function render(scope_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The page below its heading as scope.php drew it before its migration (its code, kept as the expected value).
     *
     * @param array $marks
     * @param array $labels
     * @param bool $open
     * @param bool $hasoptions
     * @param string|null $error
     * @param scope_form $form
     * @param \moodle_url $url
     * @param \moodle_url $dossier
     * @return string HTML.
     */
    private static function before(
        array $marks,
        array $labels,
        bool $open,
        bool $hasoptions,
        ?string $error,
        scope_form $form,
        \moodle_url $url,
        \moodle_url $dossier
    ): string {

        $out = \html_writer::tag('p', get_string('scopeintro', 'tool_vae'));
        $out .= \html_writer::tag('p', get_string('scopesentence', 'tool_vae'), ['class' => 'font-italic']);
        if (!$open) {
            $out .= self::core()->notification(get_string('scopeclosed', 'tool_vae'), \core\output\notification::NOTIFY_INFO);
        }
        if ($error !== null) {
            $out .= self::core()->notification(s($error), \core\output\notification::NOTIFY_ERROR);
        }
        if (!$marks) {
            $out .= \html_writer::tag('p', get_string('scopenone', 'tool_vae'));
        } else {
            $table = new \html_table();
            $table->head = [
                get_string('scopecompetency', 'tool_vae'),
                get_string('scopenote', 'tool_vae'),
                get_string('scopesaidon', 'tool_vae'),
                '',
            ];
            foreach ($marks as $key => $mark) {
                $table->data[] = [
                    s($key . (($labels[$key] ?? '') === '' ? '' : ' — ' . $labels[$key])),
                    $mark->note === null ? '' : s($mark->note),
                    s(display_date::format((int) $mark->timecreated, get_string('strftimedate', 'langconfig'))),
                    self::core()->single_button(
                        new \moodle_url($url, ['remove' => $mark->id]),
                        get_string('scoperemove', 'tool_vae'),
                        'post'
                    ),
                ];
            }
            $out .= \html_writer::table($table);
        }
        if ($open) {
            if (!$hasoptions) {
                $out .= self::core()->notification(
                    get_string('noframeworkimported', 'tool_vae'),
                    \core\output\notification::NOTIFY_INFO
                );
            } else {
                $out .= self::core()->heading(get_string('scopeaddheading', 'tool_vae'), 3);
                ob_start();
                $form->display();
                $out .= ob_get_clean();
            }
        }

        return $out . \html_writer::div(\html_writer::link($dossier, get_string('faisabbacktodossier', 'tool_vae')), 'mt-3');
    }

    /**
     * Two marks and the labels of their competencies, as the page reads them.
     *
     * @return array [marks, labels]
     */
    private static function marks(): array {
        $marks = [
            'RNCP1-C1' => (object) ['id' => 4, 'fichecode' => 'RNCP1', 'ficherang' => 'C1', 'note' => 'Pas dans mon poste',
                'timecreated' => 1792411200],
            'RNCP1-C2' => (object) ['id' => 5, 'fichecode' => 'RNCP1', 'ficherang' => 'C2', 'note' => null,
                'timecreated' => 1792497600],
        ];

        return [$marks, ['RNCP1-C1' => 'Accueillir']];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = new scope_page([], [], false, true, null, $this->form(), $this->url, $this->dossier);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/scope_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        [$marks, $labels] = self::marks();

        $cases = [
            'closed, nothing said' => [[], [], false, true, null],
            'closed, marks' => [$marks, $labels, false, true, null],
            'open, marks, form' => [$marks, $labels, true, true, null],
            'open, no record, an error' => [[], [], true, false, 'Compétence inconnue.'],
            'closed, an error' => [$marks, $labels, false, false, 'Compétence inconnue.'],
            // A note that is the figure 0 was drawn before; a template that tests the text itself would drop it.
            'closed, a note that is the figure 0' => [
                ['0-0' => (object) ['id' => 6, 'note' => '0', 'timecreated' => 1792411200]], ['0-0' => '0'], false, true, null,
            ],
        ];
        foreach ($cases as $name => [$m, $l, $open, $hasoptions, $error]) {
            $expected = self::before($m, $l, $open, $hasoptions, $error, $this->form(), $this->url, $this->dossier);
            $html = $this->render(new scope_page($m, $l, $open, $hasoptions, $error, $this->form(), $this->url, $this->dossier));
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$marks, $labels] = self::marks();

        $context = (new scope_page($marks, $labels, true, true, null, $this->form(), $this->url, $this->dossier))
            ->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertSame(get_string('scopeintro', 'tool_vae'), $context['intro']);
        $this->assertSame(get_string('scopesentence', 'tool_vae'), $context['sentence']);
        $this->assertFalse($context['closedhtml']);
        $this->assertFalse($context['errorhtml']);
        $this->assertFalse($context['none']);
        $this->assertStringContainsString('<table', $context['tablehtml']);
        $this->assertFalse($context['noframeworkhtml']);
        $this->assertStringContainsString(get_string('scopeaddheading', 'tool_vae'), $context['headinghtml']);
        $this->assertStringContainsString('_qf__tool_vae_form_scope_form', $context['formhtml']);
        $this->assertSame(
            ['url' => $this->dossier->out(false), 'label' => get_string('faisabbacktodossier', 'tool_vae')],
            $context['back']
        );

        $closed = (new scope_page([], [], false, true, null, $this->form(), $this->url, $this->dossier))
            ->export_for_template($PAGE->get_renderer('tool_vae'));
        $this->assertStringContainsString(get_string('scopeclosed', 'tool_vae'), $closed['closedhtml']);
        $this->assertSame(get_string('scopenone', 'tool_vae'), $closed['none']);
        $this->assertFalse($closed['tablehtml']);
        $this->assertFalse($closed['headinghtml']);
        $this->assertFalse($closed['formhtml']);
    }

    public function test_a_script_in_a_note_a_label_or_an_error_comes_out_escaped(): void {
        $this->resetAfterTest();
        $marks = ['RNCP1-C1' => (object) ['id' => 4, 'note' => '<script>alert(1)</script>', 'timecreated' => 1792411200]];

        $html = $this->render(new scope_page(
            $marks,
            ['RNCP1-C1' => '<script>alert(2)</script>'],
            false,
            true,
            '<script>alert(3)</script>',
            $this->form(),
            $this->url,
            $this->dossier
        ));

        foreach ([1, 2, 3] as $n) {
            $this->assertStringContainsString('&lt;script&gt;alert(' . $n . ')&lt;/script&gt;', $html);
        }
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/scope_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/scope_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(
            ['closedhtml', 'errorhtml', 'tablehtml', 'noframeworkhtml', 'headinghtml', 'formhtml'],
            array_values(array_unique($raw[1]))
        );
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/scope_page', $context);

        $this->assertStringContainsString('class="mt-3"', $html);
        $this->assertStringContainsString('class="font-italic"', $html);
    }
}
