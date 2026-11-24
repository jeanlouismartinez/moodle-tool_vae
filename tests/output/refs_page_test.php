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

use tool_vae\form\ref_form;
use tool_vae\local\display_date;

/**
 * The page of the addresses of the rules of validation (refs.php) drawn by tool_vae\output\refs_page (lot 7).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration: the setting open or
 * closed, an error of the last sending, no record imported (the last two in no instant of the tool of equivalence), a table
 * of records with and without addresses, checked or not, with its buttons, then the heading and the form of the core. The
 * "before" is the code of the page as it was, kept here as the expected value. Also: that the context holds plain texts,
 * that a script in a title, an address or an error comes out escaped, that the template renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(refs_page::class)]
final class refs_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $self;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->self = new \moodle_url('/admin/tool/vae/refs.php');
        $PAGE->set_url($this->self);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * A form of the page, as the page builds it.
     *
     * @return ref_form
     */
    private function form(): ref_form {
        return new ref_form($this->self, ['options' => ['DEMO' => 'DEMO — Démo']]);
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param refs_page $page
     * @return string HTML.
     */
    private function render(refs_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The page below its heading as refs.php drew it before its migration (its code, kept as the expected value).
     *
     * @param array $rows
     * @param array $refs
     * @param bool $open
     * @param string|null $error
     * @param ref_form $form
     * @param \moodle_url $self
     * @return string HTML.
     */
    private static function before(
        array $rows,
        array $refs,
        bool $open,
        ?string $error,
        ref_form $form,
        \moodle_url $self
    ): string {

        $out = \html_writer::tag('p', get_string('refintro', 'tool_vae'));
        if (!$open) {
            $out .= self::core()->notification(get_string('refclosed', 'tool_vae'), \core\output\notification::NOTIFY_INFO);
        }
        if ($error !== null) {
            $out .= self::core()->notification(s($error), \core\output\notification::NOTIFY_ERROR);
        }
        if (!$rows) {
            return $out . self::core()->notification(
                get_string('supersessionnone', 'tool_vae'),
                \core\output\notification::NOTIFY_INFO
            );
        }
        $table = new \html_table();
        $table->head = [
            get_string('supersessioncolfiche', 'tool_vae'),
            get_string('reftitle', 'tool_vae'),
            get_string('refurl', 'tool_vae'),
            get_string('refchecked', 'tool_vae'),
            '',
        ];
        foreach ($rows as $row) {
            $name = s($row->fichecode) . ' — ' . format_string($row->label);
            if (empty($refs[$row->fichecode])) {
                $table->data[] = [$name, get_string('refnone', 'tool_vae'), '', '', ''];
                continue;
            }
            foreach ($refs[$row->fichecode] as $ref) {
                $table->data[] = [
                    $name,
                    s($ref->title),
                    s($ref->url),
                    $ref->checkedat === null ? get_string('refnotchecked', 'tool_vae') : s(display_date::format(
                        $ref->checkedat,
                        get_string('strftimedate', 'langconfig'),
                        'UTC'
                    )),
                    self::core()->single_button(
                        new \moodle_url($self, ['remove' => $ref->id]),
                        get_string('refremove', 'tool_vae'),
                        'post'
                    ),
                ];
                $name = '';
            }
        }
        $out .= \html_writer::table($table);
        $out .= self::core()->heading(get_string('refaddheading', 'tool_vae'), 3);
        ob_start();
        $form->display();

        return $out . ob_get_clean();
    }

    /**
     * Records and their addresses, as the page reads them.
     *
     * @return array [rows, refs]
     */
    private static function data(): array {
        $rows = [
            'DEMO' => (object) ['fichecode' => 'DEMO', 'label' => 'Démo'],
            'DEMOBLOCS' => (object) ['fichecode' => 'DEMOBLOCS', 'label' => 'Demo blocs'],
        ];
        $refs = ['DEMOBLOCS' => [
            (object) ['id' => 2, 'title' => 'Fiche du répertoire', 'url' => 'https://www.francecompetences.fr/rncp/40692/',
                'checkedat' => 1792411200],
            (object) ['id' => 3, 'title' => 'Arrêté', 'url' => 'https://www.legifrance.gouv.fr/arrete?a=1&b=2',
                'checkedat' => null],
        ]];

        return [$rows, $refs];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = new refs_page([], [], true, null, $this->form(), $this->self);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/refs_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        [$rows, $refs] = self::data();

        $cases = [
            'none, open' => [[], [], true, null],
            'none, closed, an error' => [[], [], false, 'Adresse refusée.'],
            'table, closed' => [$rows, $refs, false, null],
            'table, open, an error' => [$rows, $refs, true, 'Adresse refusée.'],
        ];
        foreach ($cases as $name => [$r, $ref, $open, $error]) {
            $expected = self::before($r, $ref, $open, $error, $this->form(), $this->self);
            $html = $this->render(new refs_page($r, $ref, $open, $error, $this->form(), $this->self));
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$rows, $refs] = self::data();

        $context = (new refs_page($rows, $refs, false, 'Erreur', $this->form(), $this->self))
            ->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertSame(get_string('refintro', 'tool_vae'), $context['intro']);
        $this->assertStringContainsString(get_string('refclosed', 'tool_vae'), $context['closedhtml']);
        $this->assertStringContainsString('Erreur', $context['errorhtml']);
        $this->assertFalse($context['nonehtml']);
        $this->assertStringContainsString('<table', $context['tablehtml']);
        $this->assertStringContainsString(get_string('refaddheading', 'tool_vae'), $context['headinghtml']);
        $this->assertStringContainsString('_qf__tool_vae_form_ref_form', $context['formhtml']);

        $none = (new refs_page([], [], true, null, $this->form(), $this->self))
            ->export_for_template($PAGE->get_renderer('tool_vae'));
        $this->assertFalse($none['closedhtml']);
        $this->assertFalse($none['errorhtml']);
        $this->assertStringContainsString(get_string('supersessionnone', 'tool_vae'), $none['nonehtml']);
        $this->assertFalse($none['tablehtml']);
        $this->assertFalse($none['headinghtml']);
        $this->assertFalse($none['formhtml']);
    }

    public function test_a_script_in_a_title_an_address_or_an_error_comes_out_escaped(): void {
        $this->resetAfterTest();
        $rows = ['DEMO' => (object) ['fichecode' => 'DEMO', 'label' => 'Démo']];
        $refs = ['DEMO' => [(object) ['id' => 2, 'title' => '<script>alert(1)</script>',
            'url' => 'https://x.fr/<script>alert(2)</script>', 'checkedat' => null]]];

        $html = $this->render(new refs_page($rows, $refs, true, '<script>alert(3)</script>', $this->form(), $this->self));

        foreach ([1, 2, 3] as $n) {
            $this->assertStringContainsString('&lt;script&gt;alert(' . $n . ')&lt;/script&gt;', $html);
        }
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/refs_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/refs_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(
            ['closedhtml', 'errorhtml', 'nonehtml', 'tablehtml', 'headinghtml', 'formhtml'],
            array_values(array_unique($raw[1]))
        );
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/refs_page', $context);

        $this->assertStringContainsString('<p>', $html);
    }
}
