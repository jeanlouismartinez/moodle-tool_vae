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

use tool_vae\form\supersession_form;

/**
 * The page "Fiches remplacées" (supersession.php) drawn by tool_vae\output\supersession_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration, for every state: no record
 * (a notification of the core, no form); records with and without a label, a date, a successor (with or without a label, the
 * link to the correspondences and the button to clear it), counts; the error of a declaration refused; codes and labels that
 * are the figure 0; the heading and the form of the core below the table, drawn once. The "before" is the code of the page as
 * it was, kept here as the expected value. Also: that the context holds the parts, that a script in a code or an error comes
 * out escaped, that the template places as HTML only the keys named ...html and renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(supersession_page::class)]
final class supersession_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $self;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->self = new \moodle_url('/admin/tool/vae/supersession.php');
        $PAGE->set_url($this->self);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param supersession_page $page
     * @return string HTML.
     */
    private function render(supersession_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The form of the page, as the page builds it.
     *
     * @param \stdClass[] $rows
     * @return supersession_form
     */
    private function form(array $rows): supersession_form {
        $options = [];
        foreach ($rows as $row) {
            $options[$row->fichecode] = $row->fichecode . ($row->label === '' ? '' : ' — ' . format_string($row->label));
        }

        return new supersession_form($this->self, ['options' => $options]);
    }

    /**
     * The body of supersession.php before its migration, below the heading (its code, kept as the expected value).
     *
     * @param \stdClass[] $rows supersession::overview().
     * @param string|null $error
     * @param supersession_form $form
     * @param \moodle_url $self
     * @return string HTML.
     */
    private static function before(array $rows, ?string $error, supersession_form $form, \moodle_url $self): string {
        $output = self::core();
        $html = \html_writer::tag('p', get_string('supersessionintro', 'tool_vae'));
        if ($error !== null) {
            $html .= $output->notification(s($error), \core\output\notification::NOTIFY_ERROR);
        }
        if (!$rows) {
            return $html . $output->notification(
                get_string('supersessionnone', 'tool_vae'),
                \core\output\notification::NOTIFY_INFO
            );
        }
        $table = new \html_table();
        $table->head = [
            get_string('supersessioncolfiche', 'tool_vae'),
            get_string('supersessioncolupdated', 'tool_vae'),
            get_string('supersessioncolsuccessor', 'tool_vae'),
            get_string('supersessioncoldeclarations', 'tool_vae'),
            get_string('supersessioncolcandidates', 'tool_vae'),
            get_string('supersessioncolcarried', 'tool_vae'),
            get_string('supersessioncolobsolete', 'tool_vae'),
            '',
        ];
        foreach ($rows as $row) {
            $successor = $row->successorcode === ''
                ? get_string('supersessionnosuccessor', 'tool_vae')
                : s($row->successorcode) . ($row->successorlabel === '' ? '' : ' — ' . format_string($row->successorlabel));
            $action = $row->successorcode === '' ? '' : \html_writer::link(
                new \moodle_url('/admin/tool/vae/passage.php', ['fiche' => $row->fichecode]),
                get_string('supersessionpassages', 'tool_vae')
            ) . $output->single_button(
                new \moodle_url($self, ['clear' => $row->fichecode]),
                get_string('supersessionclear', 'tool_vae'),
                'post'
            );
            $table->data[] = [
                s($row->fichecode) . ($row->label === '' ? '' : ' — ' . format_string($row->label)),
                $row->sourceupdated ? userdate($row->sourceupdated, get_string('strftimedate', 'langconfig')) : '',
                $successor,
                $row->declarations,
                $row->candidates,
                $row->carried,
                $row->gone,
                $action,
            ];
        }
        $html .= \html_writer::table($table);
        $html .= $output->heading(get_string('supersessiondeclareheading', 'tool_vae'), 3);
        ob_start();
        $form->display();

        return $html . ob_get_clean();
    }

    /**
     * A row of supersession::overview().
     *
     * @param string $code
     * @param string $label
     * @param int|null $updated
     * @param string $successor
     * @param string $successorlabel
     * @param int[] $counts declarations, candidates, carried, gone.
     * @return \stdClass
     */
    private static function row(
        string $code,
        string $label,
        ?int $updated,
        string $successor,
        string $successorlabel,
        array $counts
    ): \stdClass {
        [$declarations, $candidates, $carried, $gone] = $counts;

        return (object) ['fichecode' => $code, 'label' => $label, 'sourceupdated' => $updated, 'successorcode' => $successor,
            'successorlabel' => $successorlabel, 'declarations' => $declarations, 'candidates' => $candidates,
            'carried' => $carried, 'gone' => $gone];
    }

    /**
     * Records with every kind of line.
     *
     * @return \stdClass[]
     */
    private static function rows(): array {
        return [
            self::row('DEMO-GRAND-AGE', 'Grand âge', 1792411200, 'DEMO-GRAND-AGE2', 'Grand âge (v2)', [4, 2, 1, 0]),
            self::row('DEMO-GRAND-AGE2', 'Grand âge (v2)', null, '', '', [0, 0, 0, 0]),
            self::row('RS5000', '', 1792497600, 'RS6000', '', [1, 1, 0, 1]),
        ];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = new supersession_page([], null, $this->form([]), $this->self);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/supersession_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        // Codes and labels that are the figure 0 were drawn before; a template that tests the text itself would drop them.
        $zeros = [self::row('0', '0', 0, '0', '0', [0, 0, 0, 0])];

        $cases = [
            'no record' => [[], null],
            'no record, an error' => [[], 'Refusée'],
            'records' => [self::rows(), null],
            'records, an error' => [self::rows(), 'La fiche ne peut pas se remplacer elle-même.'],
            'values that are 0' => [$zeros, '0'],
        ];
        foreach ($cases as $name => [$rows, $error]) {
            $expected = self::before($rows, $error, $this->form($rows), $this->self);
            $html = $this->render(new supersession_page($rows, $error, $this->form($rows), $this->self));
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    public function test_the_context_holds_the_parts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $output = $PAGE->get_renderer('tool_vae');

        $context = (new supersession_page(self::rows(), null, $this->form(self::rows()), $this->self))
            ->export_for_template($output);
        $this->assertSame(get_string('supersessionintro', 'tool_vae'), $context['intro']);
        $this->assertFalse($context['errorhtml']);
        $this->assertFalse($context['nonehtml']);
        $this->assertStringContainsString('passage.php?fiche=DEMO-GRAND-AGE', $context['tablehtml']);
        $this->assertStringContainsString(get_string('supersessiondeclareheading', 'tool_vae'), $context['headinghtml']);
        $this->assertStringContainsString('_qf__tool_vae_form_supersession_form', $context['formhtml']);

        $none = (new supersession_page([], 'Refusée', $this->form([]), $this->self))->export_for_template($output);
        $this->assertStringContainsString('Refusée', $none['errorhtml']);
        $this->assertStringContainsString(get_string('supersessionnone', 'tool_vae'), $none['nonehtml']);
        $this->assertFalse($none['tablehtml']);
        $this->assertFalse($none['headinghtml']);
        $this->assertFalse($none['formhtml']);
    }

    public function test_a_script_in_a_code_or_an_error_comes_out_escaped(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $rows = [self::row('<script>alert(1)</script>', '', null, '<script>alert(2)</script>', '', [0, 0, 0, 0])];

        $html = $this->render(new supersession_page($rows, '<script>alert(3)</script>', $this->form([]), $this->self));

        foreach ([1, 2, 3] as $n) {
            $this->assertStringContainsString('&lt;script&gt;alert(' . $n . ')&lt;/script&gt;', $html);
        }
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/supersession_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/supersession_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['errorhtml', 'nonehtml', 'tablehtml', 'headinghtml', 'formhtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/supersession_page', $context);

        $this->assertStringContainsString('<p>', $html);
    }
}
