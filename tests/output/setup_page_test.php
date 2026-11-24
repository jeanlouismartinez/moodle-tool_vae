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

use tool_vae\local\setup_checklist;

/**
 * The rendering of the page "Mise en route" (lot S1), through the output API of the core (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That a link is shown only to a reader who may open the screen it leads to, and that any other reader
 * reads the name of the screen and the right it asks, in plain text; that each state is written in words; that the page
 * gives no total, no count of the steps done and no colour of success; that the sentences of a step are escaped, a script
 * among them; that the context given to the template holds plain texts, not HTML; that the page is drawn by the renderer of
 * the plugin and the template tool_vae/setup_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(setup_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(renderer::class)]
final class setup_page_test extends \advanced_testcase {
    /**
     * A step made by hand, as setup_checklist::steps() describes it.
     *
     * @param string $key
     * @param string $state
     * @param string[] $details
     * @return \stdClass
     */
    private function made_step(string $key, string $state, array $details = []): \stdClass {
        return (object) [
            'key' => $key,
            'state' => $state,
            'details' => $details,
            'guide' => [4],
            'url' => null,
            'capability' => null,
        ];
    }

    /**
     * The page drawn as setup.php draws it: by the renderer of the plugin.
     *
     * @param \stdClass[] $steps
     * @return string HTML.
     */
    private function render(array $steps): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae')->render(new setup_page($steps));
    }

    public function test_the_renderer_of_the_plugin_is_the_class_of_the_output_api(): void {
        global $PAGE;

        $renderer = $PAGE->get_renderer('tool_vae');

        $this->assertInstanceOf(renderer::class, $renderer);
        $this->assertInstanceOf(\core\output\plugin_renderer_base::class, $renderer);
        $page = new setup_page([]);
        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/setup_page', $page->get_template_name($renderer));
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $step = $this->made_step('cron', setup_checklist::TODO, ['<script>alert(1)</script>']);

        $context = (new setup_page([$step]))->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertSame(get_string('setupintro', 'tool_vae'), $context['intro']);
        $this->assertSame(get_string('setupfooter', 'tool_vae'), $context['footer']);
        $this->assertCount(1, $context['steps']);
        $this->assertSame([
            'key' => 'cron',
            'title' => get_string('setupstep_cron', 'tool_vae'),
            'state' => get_string('setupstate_todo', 'tool_vae'),
            'details' => ['<script>alert(1)</script>'],
            'guide' => get_string('setupguide', 'tool_vae', 4),
            'link' => false,
            'needs' => false,
        ], $context['steps'][0]);
    }

    public function test_a_script_in_a_detail_comes_out_escaped(): void {
        $this->resetAfterTest();

        $html = $this->render([$this->made_step('competencies', setup_checklist::TODO, ['<script>alert(1)</script>'])]);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_administrator_gets_every_link(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('piecesopen', 1, 'tool_vae');

        $steps = setup_checklist::steps();
        $html = $this->render($steps);

        $this->assertSame(7, substr_count($html, '<a '));
        foreach (
            ['optionalsubsystems', 'scheduledtasks.php', 'roles_install.php', 'organisation.php', 'import_fiches.php',
                'policysettings', 'manageantiviruses'] as $target
        ) {
            $this->assertMatchesRegularExpression('~<a [^>]*href="[^"]*' . preg_quote($target, '~') . '~', $html, $target);
        }
        foreach ($steps as $step) {
            if ($step->url === null) {
                continue;
            }
            $needs = s(get_string('setupneeds', 'tool_vae', (object) [
                'screen' => get_string('setuplink_' . $step->key, 'tool_vae'),
                'capability' => $step->capability,
            ]));
            $this->assertStringNotContainsString($needs, $html, $step->key);
        }
    }

    public function test_a_reader_who_may_only_import_gets_one_link_and_the_names_of_the_other_screens(): void {
        $this->resetAfterTest();
        $reader = $this->getDataGenerator()->create_user();
        $roleid = create_role('Importateur', 'importateur_essai', '');
        $systemid = \context_system::instance()->id;
        assign_capability('tool/vae:import', CAP_ALLOW, $roleid, $systemid, true);
        role_assign($roleid, $reader->id, $systemid);
        $this->setUser($reader);

        $html = $this->render(setup_checklist::steps());

        $this->assertSame(1, substr_count($html, '<a '));
        $this->assertMatchesRegularExpression('~<a [^>]*href="[^"]*import_fiches\.php~', $html);
        $this->assertStringContainsString('moodle/site:config', $html);
        $this->assertStringContainsString('moodle/role:manage', $html);
        $this->assertStringContainsString('tool/vae:viewany', $html);
        $outsidelinks = preg_replace('~<a .*?</a>~s', '', $html);
        $this->assertStringContainsString(get_string('setuplink_roles', 'tool_vae'), $outsidelinks);
    }

    public function test_each_state_is_written_in_words(): void {
        $this->resetAfterTest();

        $html = $this->render([
            $this->made_step('competencies', setup_checklist::DONE),
            $this->made_step('cron', setup_checklist::TODO),
            $this->made_step('assignments', setup_checklist::CHECK),
        ]);

        $this->assertStringContainsString(get_string('setupstate_done', 'tool_vae'), $html);
        $this->assertStringContainsString(get_string('setupstate_todo', 'tool_vae'), $html);
        $this->assertStringContainsString(get_string('setupstate_check', 'tool_vae'), $html);
        $this->assertSame(3, substr_count($html, 'class="tool-vae-setup-state"'));
    }

    public function test_the_screen_gives_no_total_and_no_verdict(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('piecesopen', 1, 'tool_vae');
        $steps = setup_checklist::steps();
        foreach ($steps as $step) {
            $step->state = setup_checklist::DONE;
        }

        $html = $this->render($steps);

        $this->assertStringNotContainsString('%', $html);
        $this->assertStringNotContainsString('alert-success', $html);
        $this->assertStringNotContainsString('text-success', $html);
        $this->assertStringNotContainsString('badge-success', $html);
        $this->assertStringNotContainsString('bg-success', $html);
        $this->assertDoesNotMatchRegularExpression('/\d+\s*(\/|sur|of|out of)\s*\d+/', strip_tags($html));
    }

    public function test_a_step_of_two_parts_names_both_parts_of_the_guide(): void {
        $this->resetAfterTest();
        $step = $this->made_step('assignments', setup_checklist::CHECK);
        $step->guide = [6, 7];

        $html = $this->render([$step, $this->made_step('cron', setup_checklist::TODO)]);

        $this->assertStringContainsString(s(get_string('setupguides', 'tool_vae', (object) ['first' => 6, 'last' => 7])), $html);
        $this->assertStringContainsString(s(get_string('setupguide', 'tool_vae', 4)), $html);
        $this->assertStringNotContainsString('6, 7', $html);
    }

    public function test_a_screen_without_a_right_is_not_linked(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $step = $this->made_step('cron', setup_checklist::TODO);
        $step->url = new \moodle_url('/admin/tool/task/scheduledtasks.php');

        $html = $this->render([$step]);

        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringNotContainsString('scheduledtasks.php', $html);
    }

    public function test_a_detail_is_escaped(): void {
        $this->resetAfterTest();

        $html = $this->render([$this->made_step('competencies', setup_checklist::TODO, ['<b>x</b>'])]);

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/setup_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/setup_page', $source);
        $this->assertStringNotContainsString('{{{', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*\})\s*\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = $OUTPUT->render_from_template('tool_vae/setup_page', $context);

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('class="tool-vae-setup"', $html);
        $this->assertStringContainsString('class="tool-vae-setup-state"', $html);
    }
}
