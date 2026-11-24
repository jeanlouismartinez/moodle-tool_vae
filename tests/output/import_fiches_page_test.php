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

use tool_vae\form\fiche_confirm_form;
use tool_vae\form\fiche_import_form;

/**
 * The page "Importer des fiches de France compétences" (import_fiches.php) drawn by tool_vae\output\import_fiches_page.
 *
 * WHAT IS ASSERTED (lot 7, MMR-520 ticket 4). That each state of the page draws, below its heading, the same DOM as before
 * its migration: the refusal before anything is read, the result (with and without the way to try again), the result not
 * kept, the preview (with and without the word on the time it takes) and the numbers (with and without a failure, with no
 * link, the link to take back all the numbers or some of them). Only the form of the numbers is in an instant of the tool
 * of equivalence; the "before" of every state is the code of the page as it was, kept here. Also: the contexts, the keys
 * placed as HTML, the example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(import_fiches_page::class)]
final class import_fiches_page_test extends \advanced_testcase {
    use dom_canon;
    use fiche_ai_before;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $self;

    /** @var \moodle_url Where the button to go on leads. */
    private \moodle_url $index;

    /** @var int The day of the tests. */
    private int $now;

    #[\Override]
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->self = new \moodle_url('/admin/tool/vae/import_fiches.php');
        $this->index = new \moodle_url('/admin/tool/vae/index.php');
        $PAGE->set_url($this->self);
        $PAGE->set_context(\context_system::instance());
        $this->now = make_timestamp(2026, 10, 27, 12, 0, 0, 99);
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param import_fiches_page $page
     * @return string HTML.
     */
    private function render(import_fiches_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * What a form prints.
     *
     * @param \moodleform $form
     * @return string
     */
    private static function display(\moodleform $form): string {
        ob_start();
        $form->display();

        return ob_get_clean();
    }

    /**
     * A plan with one record, and its results when given.
     *
     * @param array|null $results
     * @return \stdClass
     */
    private function plan(?array $results = null): \stdClass {
        $record = (object) [
            'number' => 'RNCP99001', 'type' => 'rncp', 'status' => 'ready', 'refusal' => null,
            'title' => 'Diplôme inventé', 'name' => 'Diplôme inventé', 'active' => 'ACTIVE', 'enddate' => '31/08/2030',
            'blocs' => 1, 'rows' => [(object) ['rang' => 'BC01', 'intitule' => 'Premier bloc', 'description' => '']],
            'long' => [], 'markup' => [], 'state' => 'new', 'heldts' => null, 'declared' => 0,
            'forecast' => (object) ['created' => 1, 'updated' => 0, 'gone' => 0, 'diverged' => 0], 'older' => false,
        ];

        return (object) [
            'version' => 1, 'userid' => 2, 'created' => $this->now, 'token' => str_repeat('a', 32), 'origin' => 'upload',
            'originname' => 'export.zip', 'bytes' => 9704180, 'sha256' => str_repeat('ab', 32), 'exportdate' => '2026-10-19',
            'exportts' => make_timestamp(2026, 10, 19, 0, 0, 0, 99),
            'innerdates' => (object) ['blocs' => '2026-10-18', 'standard' => '2026-10-18'], 'resource' => null,
            'numbers' => ['RNCP99001'], 'repeated' => [], 'records' => [$record], 'results' => $results,
        ];
    }

    /**
     * The form of the confirmation, as the page builds it.
     *
     * @param int $importable
     * @param int $older
     * @return fiche_confirm_form
     */
    private function confirm(int $importable, int $older): fiche_confirm_form {
        $here = new \moodle_url($this->self, ['token' => str_repeat('a', 32)]);

        return new fiche_confirm_form($here->out(false), [
            'token' => str_repeat('a', 32), 'importable' => $importable, 'older' => $older,
        ]);
    }

    /**
     * The form of the numbers, as the page builds it.
     *
     * @return fiche_import_form
     */
    private function numbers(): fiche_import_form {
        return new fiche_import_form($this->self->out(false), ['maxbytes' => get_max_upload_file_size()]);
    }

    /**
     * The state "numbers" of import_fiches.php before its migration, below the heading.
     *
     * @param string|null $failure
     * @param string[] $known
     * @param int $knowntotal
     * @return string
     */
    private function before_numbers(?string $failure, array $known, int $knowntotal): string {
        $out = '';
        if ($failure !== null) {
            $out .= self::core()->notification($failure, \core\output\notification::NOTIFY_ERROR);
        }
        $out .= \html_writer::tag('p', get_string('fcimportintro', 'tool_vae'));
        $out .= \html_writer::tag('p', get_string('fcimportneutral', 'tool_vae'), ['class' => 'small']);
        if ($known) {
            $label = $knowntotal > count($known)
                ? get_string('fcimportexistingmany', 'tool_vae', (object) ['count' => count($known), 'total' => $knowntotal])
                : get_string('fcimportexisting', 'tool_vae');
            $out .= \html_writer::tag('p', \html_writer::link(new \moodle_url($this->self, ['known' => 1]), $label));
        }

        return $out . self::display($this->numbers());
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;

        $page = import_fiches_page::unavailable('errorcompetenciesdisabled');

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/import_fiches_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_the_refusal_and_the_results_draw_the_dom_of_before(): void {
        $notify = \core\output\notification::class;
        foreach (['errorcompetenciesdisabled', 'errorimportnocompetencymanage'] as $key) {
            $this->assertSame(
                self::canon(self::core()->notification(get_string($key, 'tool_vae'), $notify::NOTIFY_ERROR)),
                self::canon($this->render(import_fiches_page::unavailable($key))),
                $key
            );
        }

        $token = str_repeat('a', 32);
        $some = $this->plan([
            (object) ['number' => 'RNCP99001', 'status' => 'imported', 'html' => '1 competency added.'],
            (object) ['number' => 'RNCP99002', 'status' => 'refused', 'html' => 'No bloc.'],
        ]);
        $all = $this->plan([(object) ['number' => 'RNCP99001', 'status' => 'imported', 'html' => '1 competency added.']]);
        foreach (['some untouched' => $some, 'all written' => $all] as $name => $plan) {
            // The page as it was: the result, the way to try again when a record was not written, the button.
            $before = self::before_fiche_results($plan);
            $untouched = array_filter($plan->results, fn($result) => $result->status !== 'imported');
            if ($untouched) {
                $before .= \html_writer::tag('p', \html_writer::link(
                    new \moodle_url($this->self, ['redo' => $token]),
                    get_string('fcimportredo', 'tool_vae')
                ));
            }
            $before .= self::core()->continue_button($this->index);

            $redo = $untouched ? new \moodle_url($this->self, ['redo' => $token]) : null;
            $html = $this->render(import_fiches_page::results($plan, $redo, $this->index));
            $this->assertSame(self::canon($before), self::canon($html), $name);
        }
        $this->assertStringContainsString('redo=', $this->render(import_fiches_page::results(
            $some,
            new \moodle_url($this->self, ['redo' => $token]),
            $this->index
        )), 'Premise: the way to try again is drawn.');

        $before = self::core()->notification(get_string('fcimportresultunsaved', 'tool_vae'), $notify::NOTIFY_WARNING)
            . self::before_fiche_results($all) . self::core()->continue_button($this->index);
        $this->assertSame(self::canon($before), self::canon($this->render(import_fiches_page::unsaved($all, $this->index))));
    }

    public function test_the_preview_draws_the_dom_of_before(): void {
        foreach ([[1, 0], [0, 1], [0, 0]] as [$importable, $older]) {
            $plan = $this->plan();
            // The page as it was: the preview, the word on the time it takes when something can be imported, the form.
            $before = self::before_fiche_preview($plan, $this->now);
            if ($importable || $older) {
                $before .= \html_writer::tag('p', get_string('fcimportwaitapply', 'tool_vae'), ['class' => 'small']);
            }
            $before .= self::display($this->confirm($importable, $older));

            $html = $this->render(import_fiches_page::preview(
                $plan,
                (bool) ($importable || $older),
                $this->confirm($importable, $older),
                $this->now
            ));

            $this->assertSame(self::canon($before), self::canon($html), "$importable/$older");
        }
    }

    public function test_the_numbers_draw_the_dom_of_before(): void {
        $failure = 'The export could not be read: <b>x</b> &amp; y.';
        $cases = [
            'nothing known' => [null, [], 0],
            'all known' => [null, ['RNCP1', 'RNCP2'], 2],
            'some known' => [$failure, ['RNCP1', 'RNCP2'], 250],
            'a failure' => [$failure, [], 0],
        ];
        foreach ($cases as $name => [$fail, $known, $total]) {
            $html = $this->render(import_fiches_page::numbers(
                $fail,
                count($known),
                $total,
                new \moodle_url($this->self, ['known' => 1]),
                $this->numbers()
            ));
            $this->assertSame(1, preg_match_all('/filepickerhidden/', $html), $name);
            $this->assertSame(
                self::canon(self::drafts($this->before_numbers($fail, $known, $total))),
                self::canon(self::drafts($html)),
                $name
            );
        }
        $many = get_string('fcimportexistingmany', 'tool_vae', (object) ['count' => 2, 'total' => 250]);
        $this->assertStringContainsString(s($many), $this->before_numbers(null, ['RNCP1', 'RNCP2'], 250), 'Premise.');
    }

    public function test_the_context_holds_plain_texts_and_html_of_the_core(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');

        $numbers = import_fiches_page::numbers(null, 3, 3, new \moodle_url($this->self, ['known' => 1]), $this->numbers())
            ->export_for_template($output);
        $this->assertFalse($numbers['noticehtml']);
        $this->assertSame(get_string('fcimportintro', 'tool_vae'), $numbers['numbers']['intro']);
        $this->assertSame(get_string('fcimportexisting', 'tool_vae'), $numbers['numbers']['known']['label']);
        $this->assertSame($this->self->out(false) . '?known=1', $numbers['numbers']['known']['url']);
        $this->assertStringContainsString('_qf__tool_vae_form_fiche_import_form', $numbers['formhtml']);
        $this->assertFalse($numbers['resultshtml']);

        $none = import_fiches_page::numbers(null, 0, 0, $this->self, $this->numbers())->export_for_template($output);
        $this->assertFalse($none['numbers']['known']);

        $results = import_fiches_page::results($this->plan([]), null, $this->index)->export_for_template($output);
        $this->assertFalse($results['redo']);
        $this->assertStringContainsString('tool-vae-fcimport-results', $results['resultshtml']);
        $this->assertStringContainsString('index.php', $results['continuehtml']);
        $this->assertFalse($results['formhtml']);
    }

    public function test_only_the_html_of_the_core_and_of_the_pieces_is_placed_as_it_is(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/import_fiches_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(
            ['noticehtml', 'resultshtml', 'continuehtml', 'previewhtml', 'formhtml'],
            array_values(array_unique($raw[1]))
        );
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/import_fiches_page.mustache');
        $this->assertStringContainsString('@template tool_vae/import_fiches_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/import_fiches_page', $context);

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('known=1', $html);
    }
}
