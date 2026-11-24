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

namespace tool_vae;

use tool_vae\form\reminder_pointers;
use tool_vae\local\situation_screen;

/**
 * What PHP hands to the JavaScript modules of the plugin (lot Q4).
 *
 * WHAT IS ASSERTED. The scripts that were written into the page by js_init_code are AMD modules: the page asks for the module
 * by its name and passes it what the old script received as JSON, nothing more. The autosave of a work situation names its
 * field `id_content` and does not spare a submission to a new tab; the autosave of a section of the feasibility dossier names
 * `id_text` and spares it - the two differences the two scripts had. The reminders go as the map field => reminder ids. The
 * arguments stay under the length past which the core warns (1024 characters), measured on the real form that carries the
 * most reminders. What the modules do with them is played in a browser by tests/behat/javascript_modules.feature.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(situation_screen::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(reminder_pointers::class)]
final class javascript_modules_test extends \advanced_testcase {
    /** @var int The length of the arguments past which js_call_amd() writes a debugging message. */
    private const CORE_LIMIT = 1024;

    /**
     * The calls of a module as the page will make them, read in the code the page writes at its end.
     *
     * @param string $module The full name of the module, tool_vae/<name>.
     * @return array For each call, the arguments string as written, and the first argument decoded.
     */
    private static function calls(string $module): array {
        global $PAGE;

        $end = $PAGE->requires->get_end_code();
        $pattern = '~require\(\[\'' . preg_quote($module, '~') . '\'\], function\(amd\) \{amd\.init\((.*?)\); '
            . 'M\.util\.js_complete\(\'' . preg_quote($module, '~') . '\'\);\}\);~s';
        preg_match_all($pattern, $end, $matches);
        $calls = [];
        foreach ($matches[1] as $arguments) {
            $calls[] = ['raw' => $arguments, 'value' => json_decode($arguments, true)];
        }

        return $calls;
    }

    /**
     * The autosave of a work situation asks for tool_vae/autosave with the configuration of the old script, on id_content.
     */
    public function test_the_autosave_of_a_situation_asks_for_the_module_with_its_configuration(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        situation_screen::autosave(42, 1700000000);

        $calls = self::calls('tool_vae/autosave');
        $this->assertCount(1, $calls);
        // No address and no session key any more: the module calls the external function tool_vae_save_draft through
        // core/ajax, which adds the session key itself (ticket 5 of MMR-520).
        $this->assertSame([
            'draftid' => 42,
            'workingat' => 1700000000,
            'failed' => get_string('draftautosaveerror', 'tool_vae'),
            'areaid' => 'id_content',
            'sparenewtab' => false,
        ], $calls[0]['value']);
        $this->assertLessThan(self::CORE_LIMIT, strlen($calls[0]['raw']));
        // The script is no longer written into the page.
        $this->assertStringNotContainsString('tool-vae-autosave', $PAGE->requires->get_end_code());
    }

    /**
     * The autosave of a section of the feasibility dossier: the same configuration, on id_text, sparing a submission to a new
     * tab (the button of the aid, which leaves this page going on).
     */
    public function test_the_autosave_of_a_section_names_its_field_and_spares_the_new_tab(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame([
            'draftid' => 7,
            'workingat' => 0,
            'failed' => get_string('draftautosaveerror', 'tool_vae'),
            'areaid' => 'id_text',
            'sparenewtab' => true,
        ], situation_screen::autosave_config(7, 0, 'id_text', true));
    }

    /**
     * The confirmation of the aid asks for tool_vae/submit_once on its own form, and the module still disables the button.
     *
     * Read in the sources, since ai.php is a page and not a function: the page asks for the module with the selector of the
     * forms posted to ai.php; the form of the confirmation is posted there and carries the submit input the module looks for;
     * the module, in its source and in its built file, listens to the submission and disables that input. That a second click
     * sends nothing is NOT played in a browser: the scenario of javascript_modules.feature clicks once.
     */
    public function test_the_confirmation_of_the_aid_asks_for_the_module_that_disables_its_button(): void {
        $root = dirname(__DIR__);

        $page = file_get_contents($root . '/ai.php');
        $this->assertStringContainsString(
            "js_call_amd('tool_vae/submit_once', 'init', [\"form[action\$='ai.php']\"])",
            $page,
            'The page of the confirmation asks for the module, on the forms posted to ai.php.'
        );
        $this->assertStringNotContainsString('js_init_code', $page, 'The script is no longer written into the page.');

        // Lot 7: the screens of the aid are drawn by tool_vae\output\ai_page and its template.
        $template = file_get_contents($root . '/templates/ai_page.mustache');
        $this->assertStringContainsString('<input type="submit"', $template, 'The form carries the input the module disables.');
        $screens = file_get_contents($root . '/classes/output/ai_page.php');
        $this->assertStringContainsString("'/admin/tool/vae/ai.php'", $screens, 'The form is posted to ai.php.');

        $source = file_get_contents($root . '/amd/src/submit_once.js');
        $this->assertStringContainsString('addEventListener("submit"', $source);
        $this->assertStringContainsString('querySelector("input[type=submit]")', $source);
        $this->assertStringContainsString('b.disabled = true', $source);

        $built = file_get_contents($root . '/amd/build/submit_once.min.js');
        $this->assertStringContainsString('addEventListener("submit"', $built);
        $this->assertStringContainsString('querySelector("input[type=submit]")', $built);
        $this->assertStringContainsString('b.disabled=!0', $built);
    }

    /**
     * The reminders go to tool_vae/reminder_pointers as the map field => reminder ids, one call per form.
     */
    public function test_the_reminders_go_to_the_module_as_the_map_of_each_field(): void {
        $this->resetAfterTest();

        reminder_pointers::apply(['id_title' => ['tool_vae_anonymise'], 'id_setting' => ['tool_vae_anonymise']]);
        reminder_pointers::apply(['id_notetext' => ['tool_vae_anonymise_note']]);

        $calls = self::calls('tool_vae/reminder_pointers');
        $this->assertCount(2, $calls);
        $this->assertSame(['id_title' => ['tool_vae_anonymise'], 'id_setting' => ['tool_vae_anonymise']], $calls[0]['value']);
        $this->assertSame(['id_notetext' => ['tool_vae_anonymise_note']], $calls[1]['value']);
    }

    /**
     * The form with the most reminders (the declaration: two fields, two reminders each) stays under the core limit.
     */
    public function test_the_largest_map_of_reminders_stays_under_the_limit_of_the_core(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        (new \tool_vae\form\declare_form(null, ['competencies' => []]))->render();

        $calls = self::calls('tool_vae/reminder_pointers');
        $this->assertCount(1, $calls);
        $this->assertCount(2, $calls[0]['value']);
        $this->assertLessThan(self::CORE_LIMIT, strlen($calls[0]['raw']), 'Arguments: ' . $calls[0]['raw']);
    }
}
