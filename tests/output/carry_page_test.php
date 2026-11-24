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

use tool_vae\form\declare_form;
use tool_vae\local\display_date;

/**
 * The page "Reporter une déclaration" (carry.php) drawn by tool_vae\output\carry_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration (its code kept here as
 * the expected value, the declaration kept in the trait presenter_before), for: a carry that can be made; a carry refused
 * because the replacing record was hidden since the form was sent (the message, the sentence that the text is kept); a
 * note of the administrator or none; a competency of the old record that is no longer read; labels and codes that are
 * the figure 0. The form of the core is drawn once. Also: that the context holds plain texts, that a script in a note or
 * a code comes out escaped, that the template places as HTML only its keys ...html and renders its example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(carry_page::class)]
final class carry_page_test extends \advanced_testcase {
    use dom_canon;
    use presenter_before;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    protected function setUp(): void {
        global $CFG, $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->libdir . '/formslib.php');
        $this->setAdminUser();
        $this->url = new \moodle_url('/admin/tool/vae/carry.php', ['evidenceid' => 5]);
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin for the web.
     *
     * @param carry_page $page
     * @return string HTML.
     */
    private function render(carry_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * A declaration on the replaced record, as the page reads it.
     *
     * @param string $fichecode
     * @return \stdClass
     */
    private static function evidence(string $fichecode = 'RS5000'): \stdClass {
        return (object) ['id' => 5, 'userevidenceid' => 0, 'competencyid' => null, 'fichecode' => $fichecode,
            'ficherang' => 'C01', 'ficheupdated' => 1781136000, 'periodstart' => 1756684800, 'periodend' => 1782172800,
            'settingtype' => 'ehpad', 'settingdetail' => 'Un détail', 'situation' => 'Une situation.',
            'situationformat' => FORMAT_PLAIN, 'corelabel' => null];
    }

    /**
     * The offer, as carry_manager::require_offer() gives it.
     *
     * @param string $note
     * @param string $code
     * @return \stdClass
     */
    private static function offer(string $note, string $code = 'RS6000'): \stdClass {
        return (object) [
            'passage' => (object) ['decidedat' => 1781136000, 'note' => $note],
            'target' => (object) ['id' => 42, 'idnumber' => $code . '-C01', 'label' => 'Aider au <lever> & au coucher'],
            'successor' => (object) ['fichecode' => $code],
            'hidden' => false,
        ];
    }

    /**
     * The form of the page, as the page builds it.
     *
     * @param \stdClass $offer
     * @return declare_form
     */
    private function form(\stdClass $offer): declare_form {
        $form = new declare_form($this->url->out(false), [
            'competencies' => [(int) $offer->target->id => $offer->target],
            'submitlabel' => get_string('carrysubmit', 'tool_vae'),
        ]);
        $form->set_data((object) ['periodstart' => 1756684800, 'periodend' => 1782172800, 'settingtype' => 'ehpad',
            'settingdetail' => 'Un détail', 'situation' => 'Une situation.', 'userid' => 3]);

        return $form;
    }

    /**
     * The body of carry.php before its migration, below its heading (its code, kept as the expected value).
     *
     * @param string|null $refusal
     * @param \stdClass $evidence
     * @param \stdClass|null $from
     * @param \stdClass $offer
     * @param string $formhtml
     * @return string HTML.
     */
    private static function before(
        ?string $refusal,
        \stdClass $evidence,
        ?\stdClass $from,
        \stdClass $offer,
        string $formhtml
    ): string {
        $output = self::core();
        $context = \context_system::instance();
        $show = function (string $idnumber, string $label): string {
            return s($idnumber) . ' — ' . format_string($label);
        };
        $html = '';
        if ($refusal !== null) {
            $html .= $output->notification($refusal, \core\output\notification::NOTIFY_ERROR);
            $html .= \html_writer::tag('p', get_string('carryhiddenkept', 'tool_vae'));
        }
        $html .= \html_writer::tag('p', get_string('carryintro', 'tool_vae'));
        $html .= $output->heading(get_string('carryoriginal', 'tool_vae', s($evidence->fichecode)), 3);
        $html .= self::before_declaration($evidence, $context);
        $html .= $output->heading(get_string('carrycorrespondence', 'tool_vae'), 3);
        $fromcode = (string) ($from->idnumber ?? $evidence->fichecode . '-' . $evidence->ficherang);
        $html .= \html_writer::tag('p', get_string('carrycorrespondencetext', 'tool_vae', (object) [
            'from' => $show($fromcode, (string) ($from->label ?? '')),
            'to' => $show((string) $offer->target->idnumber, (string) $offer->target->label),
            'when' => display_date::format((int) $offer->passage->decidedat, get_string('strftimedate', 'langconfig')),
        ]));
        if (trim((string) $offer->passage->note) !== '') {
            $html .= \html_writer::tag('p', get_string('carrynote', 'tool_vae', s($offer->passage->note)));
        }
        $html .= $output->heading(get_string('carryformheading', 'tool_vae', s($offer->successor->fichecode)), 3);

        return $html . $formhtml;
    }

    /**
     * The cases: refusal, evidence, competency of the old record, offer.
     *
     * @return array[]
     */
    private static function cases(): array {
        $from = (object) ['idnumber' => 'RS5000-C01', 'label' => 'Aider au lever'];

        return [
            'a carry to make' => [null, self::evidence(), $from, self::offer('Même geste, autre libellé.')],
            'refused, hidden since' => ['La fiche « RS6000 » est cachée.', self::evidence(), $from, self::offer('')],
            'no note, a blank one' => [null, self::evidence(), $from, self::offer("  \n ")],
            'the old competency no longer read' => [null, self::evidence(), null, self::offer('Note')],
            'the figure 0' => [null, self::evidence('0'), (object) ['idnumber' => '0', 'label' => '0'], self::offer('0', '0')],
        ];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $offer = self::offer('');
        $page = new carry_page(null, self::evidence(), \context_system::instance(), null, $offer, $this->form($offer));

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/carry_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        foreach (self::cases() as $name => [$refusal, $evidence, $from, $offer]) {
            ob_start();
            $this->form($offer)->display();
            $formhtml = ob_get_clean();

            $expected = self::before($refusal, $evidence, $from, $offer, $formhtml);
            $page = new carry_page($refusal, $evidence, \context_system::instance(), $from, $offer, $this->form($offer));
            $html = $this->render($page);
            $this->assertSame(1, substr_count($html, '<form'), $name);
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $offer = self::offer('Une note & un mot');
        $page = new carry_page('Refus', self::evidence(), \context_system::instance(), null, $offer, $this->form($offer));

        $context = $page->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertStringContainsString('Refus', $context['refusal']['noticehtml']);
        $this->assertSame(get_string('carryhiddenkept', 'tool_vae'), $context['refusal']['kept']);
        $this->assertSame(get_string('carryintro', 'tool_vae'), $context['intro']);
        $this->assertSame(['text' => get_string('carrynote', 'tool_vae', 'Une note & un mot')], $context['note']);
        // The label passes through format_string(), as before: its tags are taken out, its ampersand escaped.
        $this->assertStringContainsString(format_string('Aider au <lever> & au coucher'), $context['correspondencehtml']);
        $this->assertStringContainsString('&amp;', $context['correspondencehtml']);
        $this->assertStringContainsString('<form', $context['formhtml']);
    }

    public function test_a_script_in_a_note_or_a_code_comes_out_escaped(): void {
        $script = '<script>alert(1)</script>';
        $offer = self::offer($script, $script);
        $evidence = self::evidence($script);

        $html = $this->render(new carry_page(null, $evidence, \context_system::instance(), null, $offer, $this->form($offer)));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_only_the_keys_of_html_are_placed_as_they_are(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/carry_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['noticehtml', 'originalhtml', 'declarationhtml', 'correspondenceheadinghtml', 'correspondencehtml',
            'formheadinghtml', 'formhtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $file = __DIR__ . '/../../templates/carry_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/carry_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $this->assertStringContainsString('tool-vae-claim', self::core()->render_from_template('tool_vae/carry_page', $context));
    }
}
