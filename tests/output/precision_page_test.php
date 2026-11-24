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

use tool_vae\form\precision_form;

/**
 * The page "Demandes de précision" (precision.php) drawn by tool_vae\output\precision_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration (its code kept here as
 * the expected value, the declaration kept in the trait presenter_before), for: no request and the form to ask; a question
 * waiting and the form to answer; questions answered, one of an author who is gone; no form and the notice that the
 * declaration is attested; no form and no notice; texts that are the figure 0. The tool of equivalence shows no request
 * (none on its data set). Also: that the context holds plain texts, that a script in a question, an answer or a name
 * comes out escaped, that the template places as HTML only its keys ...html and renders its example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(precision_page::class)]
final class precision_page_test extends \advanced_testcase {
    use dom_canon;
    use presenter_before;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    /** @var \moodle_url The address of the dossier. */
    private \moodle_url $dossier;

    protected function setUp(): void {
        global $CFG, $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->libdir . '/formslib.php');
        $this->setAdminUser();
        $this->url = new \moodle_url('/admin/tool/vae/precision.php', ['evidenceid' => 5]);
        $this->dossier = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => 3]);
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin for the web.
     *
     * @param precision_page $page
     * @return string HTML.
     */
    private function render(precision_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * A declaration, as the page reads it.
     *
     * @return \stdClass
     */
    private static function evidence(): \stdClass {
        return (object) ['id' => 5, 'userevidenceid' => 0, 'competencyid' => null, 'fichecode' => 'RS5000',
            'ficherang' => 'C01', 'ficheupdated' => 1781136000, 'periodstart' => 1756684800, 'periodend' => 1782172800,
            'settingtype' => 'ehpad', 'settingdetail' => '', 'situation' => 'Une situation.', 'situationformat' => FORMAT_PLAIN,
            'corelabel' => null];
    }

    /**
     * A request, as request_manager::for_evidence() gives it.
     *
     * @param int $id
     * @param string|null $answer
     * @param string $author
     * @param string $question
     * @return \stdClass
     */
    private static function request(int $id, ?string $answer, string $author = 'Hugo Bernard', string $question = ''): \stdClass {
        return (object) ['id' => $id, 'question' => $question !== '' ? $question : "Question $id ?\nSur deux lignes.",
            'answer' => $answer, 'authorname' => $author, 'timecreated' => 1781136000 + $id,
            'timeanswered' => $answer === null ? null : 1781200000 + $id];
    }

    /**
     * The body of precision.php before its migration, below its heading (its code, kept as the expected value).
     *
     * @param \stdClass[] $requests
     * @param string|null $formhtml The form, as display() printed it, or null when there is none.
     * @param bool $attested Whether the notice of an attested declaration is drawn in place of the form.
     * @return string HTML.
     */
    private function before(array $requests, ?string $formhtml, bool $attested): string {
        $output = self::core();
        $context = \context_system::instance();
        $html = \html_writer::tag('p', s(get_string('precisionintro', 'tool_vae')));
        $html .= self::before_attest_declaration(self::evidence(), $context);
        $html .= $output->heading(get_string('precisionrequests', 'tool_vae'), 3);
        if (!$requests) {
            $html .= \html_writer::tag('p', s(get_string('precisionnone', 'tool_vae')));
        }
        foreach ($requests as $request) {
            $author = $request->authorname !== '' ? $request->authorname : get_string('opinionformerauthor', 'tool_vae');
            $html .= \html_writer::tag('h4', s(get_string('precisionquestionheading', 'tool_vae')), ['class' => 'h5']);
            $html .= \html_writer::tag('p', s($author . ' - ' . userdate(
                $request->timecreated,
                get_string('strftimedatetime', 'langconfig')
            )), ['class' => 'text-muted small']);
            $html .= format_text($request->question, FORMAT_PLAIN, ['context' => $context, 'filter' => false]);

            if ($request->answer === null) {
                $html .= \html_writer::tag('p', s(get_string('precisionwaiting', 'tool_vae')), ['class' => 'text-muted']);
                continue;
            }
            $html .= \html_writer::tag('h4', s(get_string('precisionanswerheading', 'tool_vae')), ['class' => 'h5']);
            $html .= \html_writer::tag('p', s(userdate(
                $request->timeanswered,
                get_string('strftimedatetime', 'langconfig')
            )), ['class' => 'text-muted small']);
            $html .= format_text($request->answer, FORMAT_PLAIN, ['context' => $context, 'filter' => false]);
        }

        if ($formhtml !== null) {
            $html .= $formhtml;
        } else if ($attested) {
            $html .= $output->notification(get_string('errorrequestattested', 'tool_vae'), \core\output\notification::NOTIFY_INFO);
        }

        return $html . \html_writer::tag('p', \html_writer::link($this->dossier, get_string('precisionbacktodossier', 'tool_vae')));
    }

    /**
     * The form of the page, as the page builds it.
     *
     * @param bool $answering
     * @param int $requestid
     * @return precision_form
     */
    private function form(bool $answering, int $requestid = 0): precision_form {
        return new precision_form($this->url->out(false), ['answering' => $answering, 'requestid' => $requestid]);
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $page = new precision_page(self::evidence(), \context_system::instance(), [], null, false, $this->dossier);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/precision_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $answered = [1 => self::request(1, "Réponse.\nSur deux lignes."), 2 => self::request(2, 'Oui.', '')];
        $cases = [
            'no request, the form to ask' => [[], [false, 0], false],
            'a question waiting, the form to answer' => [$answered + [3 => self::request(3, null)], [true, 3], false],
            'answered, attested' => [$answered, null, true],
            'answered, nothing to do' => [$answered, null, false],
            'the figure 0' => [[0 => self::request(0, '0', '0', '0')], null, false],
        ];
        foreach ($cases as $name => [$requests, $form, $attested]) {
            $formhtml = null;
            if ($form !== null) {
                ob_start();
                $this->form(...$form)->display();
                $formhtml = ob_get_clean();
            }
            $expected = $this->before($requests, $formhtml, $attested);
            $page = new precision_page(
                self::evidence(),
                \context_system::instance(),
                $requests,
                $form === null ? null : $this->form(...$form),
                $attested,
                $this->dossier
            );
            $this->assertSame(self::canon($expected), self::canon($this->render($page)), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $requests = [1 => self::request(1, 'Oui.', 'A & B'), 2 => self::request(2, null)];
        $page = new precision_page(self::evidence(), \context_system::instance(), $requests, null, true, $this->dossier);

        $context = $page->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertSame(get_string('precisionintro', 'tool_vae'), $context['intro']);
        $this->assertFalse($context['none']);
        $this->assertStringStartsWith('A & B - ', $context['requests'][0]['meta']);
        $this->assertFalse($context['requests'][0]['waiting']);
        $this->assertSame(get_string('precisionanswerheading', 'tool_vae'), $context['requests'][0]['answer']['heading']);
        $this->assertSame(get_string('precisionwaiting', 'tool_vae'), $context['requests'][1]['waiting']);
        $this->assertFalse($context['requests'][1]['answer']);
        $this->assertFalse($context['formhtml']);
        $this->assertStringContainsString(get_string('errorrequestattested', 'tool_vae'), $context['attestedhtml']);
        $this->assertSame($this->dossier->out(false), $context['back']['url']);
    }

    public function test_a_script_in_a_question_an_answer_or_a_name_comes_out_escaped(): void {
        $script = '<script>alert(1)</script>';
        $requests = [1 => self::request(1, $script, $script, $script)];

        $page = new precision_page(self::evidence(), \context_system::instance(), $requests, null, false, $this->dossier);
        $html = $this->render($page);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_only_the_keys_of_html_are_placed_as_they_are(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/precision_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $keys = ['declarationhtml', 'headinghtml', 'questionhtml', 'answerhtml', 'formhtml', 'attestedhtml'];
        $this->assertSame($keys, array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $file = __DIR__ . '/../../templates/precision_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/precision_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $this->assertStringContainsString('h5', self::core()->render_from_template('tool_vae/precision_page', $context));
    }
}
