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

use tool_vae\form\withdraw_form;

/**
 * The page "Retirer une attestation" (withdraw.php) drawn by tool_vae\output\withdraw_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration (its code kept here as
 * the expected value, the screens it called kept in the trait presenter_before): the declaration, the attestation that
 * is withdrawn (in force, imported, or with a statement of several lines), and the form of the core drawn once. Also: that
 * the context holds the pieces of the page, that the template places as HTML only its keys ...html and renders its
 * example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(withdraw_page::class)]
final class withdraw_page_test extends \advanced_testcase {
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
        $this->url = new \moodle_url('/admin/tool/vae/withdraw.php', ['attestationid' => 9]);
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin for the web.
     *
     * @param withdraw_page $page
     * @return string HTML.
     */
    private function render(withdraw_page $page): string {
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
            'settingtype' => 'ehpad', 'settingdetail' => 'Un détail', 'situation' => 'Une situation.',
            'situationformat' => FORMAT_PLAIN, 'corelabel' => null];
    }

    /**
     * The form of the page, as the page builds it.
     *
     * @return withdraw_form
     */
    private function form(): withdraw_form {
        $form = new withdraw_form($this->url->out(false));
        $form->set_data(['attestationid' => 9]);

        return $form;
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $attestation = (object) ['id' => 9, 'withdrawnat' => null, 'attestantrole' => 'R', 'statement' => 'S',
            'attestedat' => 1781136000, 'importedat' => null];
        $page = new withdraw_page(self::evidence(), \context_system::instance(), $attestation, $this->form());

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/withdraw_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $cases = [
            'in force' => ['statement' => 'Je confirme.', 'importedat' => null],
            'imported' => ['statement' => 'Je confirme.', 'importedat' => 1782172800],
            'several lines' => ['statement' => "Je confirme.\nDeux lignes.", 'importedat' => null],
            'the figure 0' => ['statement' => '0', 'importedat' => null],
        ];
        foreach ($cases as $name => $fields) {
            $attestation = (object) ($fields + ['id' => 9, 'withdrawnat' => null, 'attestantrole' => 'Directrice',
                'attestedat' => 1781136000]);
            ob_start();
            $this->form()->display();
            $expected = self::before_attest_declaration(self::evidence(), \context_system::instance())
                . self::before_attestations([$attestation->id => $attestation]) . ob_get_clean();

            $html = $this->render(new withdraw_page(self::evidence(), \context_system::instance(), $attestation, $this->form()));
            $this->assertSame(1, substr_count($html, '<form'), $name);
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    /**
     * The warning of the anchor is the one the old page drew above the declaration (it called attest_screen, not presenter).
     *
     * The declaration of the other cases holds its anchor, so that a page drawing the declaration alone would pass them.
     */
    public function test_a_declaration_that_left_its_anchor_is_warned_of_as_before(): void {
        $context = \context_system::instance();
        // The label recorded at the declaration is no longer the one the core holds: the anchor was renamed.
        $evidence = self::evidence();
        $evidence->corelabel = 'Un autre libellé';
        $attestation = (object) ['id' => 9, 'withdrawnat' => null, 'attestantrole' => 'Directrice',
            'statement' => 'Je confirme.', 'attestedat' => 1781136000, 'importedat' => null];
        $this->assertNotSame(
            self::canon(self::before_declaration($evidence, $context)),
            self::canon(self::before_attest_declaration($evidence, $context)),
            'The premise: the old screen warned of this declaration.'
        );

        ob_start();
        $this->form()->display();
        $expected = self::before_attest_declaration($evidence, $context)
            . self::before_attestations([$attestation->id => $attestation]) . ob_get_clean();

        $html = $this->render(new withdraw_page($evidence, $context, $attestation, $this->form()));
        $this->assertStringContainsString('alert-warning', $html);
        $this->assertSame(self::canon($expected), self::canon($html));
    }

    public function test_the_context_holds_the_pieces_of_the_page(): void {
        global $PAGE;
        $attestation = (object) ['id' => 9, 'withdrawnat' => null, 'attestantrole' => 'R', 'statement' => 'S',
            'attestedat' => 1781136000, 'importedat' => null];

        $context = (new withdraw_page(self::evidence(), \context_system::instance(), $attestation, $this->form()))
            ->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertStringContainsString('tool-vae-claim', $context['declarationhtml']);
        $this->assertStringContainsString('tool-vae-attestations', $context['attestationshtml']);
        $this->assertStringContainsString('<form', $context['formhtml']);
    }

    public function test_only_the_keys_of_html_are_placed_as_they_are(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/withdraw_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['declarationhtml', 'attestationshtml', 'formhtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
    }

    public function test_the_template_renders_its_example_context(): void {
        $file = __DIR__ . '/../../templates/withdraw_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/withdraw_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $this->assertStringContainsString('tool-vae-claim', self::core()->render_from_template('tool_vae/withdraw_page', $context));
    }
}
