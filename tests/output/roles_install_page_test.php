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

use tool_vae\local\role_installer;

/**
 * The page of the VAE roles (roles_install.php) drawn by tool_vae\output\roles_install_page and, for the confirmation of one
 * role, tool_vae\output\roles_install_confirm_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration: the presets shipped with
 * the plugin, the base ones then the optional ones with their warning, each with its button or, for a role that exists
 * (in no instant of the tool of equivalence), its sentence; and above the confirmation of the core, what will be created
 * with which rights (in no instant either: the tool asks for no preset by its short name). The "before" is the code of the
 * page as it was, kept here as the expected value. Also: that the contexts hold plain texts, that a script in a name or a
 * description comes out escaped, that the two templates render their example contexts.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(roles_install_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(roles_install_confirm_page::class)]
final class roles_install_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    protected function setUp(): void {
        parent::setUp();
        $this->url = new \moodle_url('/admin/tool/vae/roles_install.php');
    }

    /**
     * A page drawn by the renderer of the plugin.
     *
     * @param \core\output\renderable $page
     * @return string HTML.
     */
    private function render(\core\output\renderable $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The list below the heading as roles_install.php drew it before its migration (its code, kept as the expected value).
     *
     * @param \stdClass[] $presets
     * @param bool[] $exists Shortname => whether the role exists.
     * @param \moodle_url $url
     * @return string HTML.
     */
    private static function before_list(array $presets, array $exists, \moodle_url $url): string {

        $out = \html_writer::tag('p', s(get_string('rolesintro', 'tool_vae')));
        foreach ([false, true] as $optional) {
            $subset = array_filter($presets, fn($preset) => $preset->optional === $optional);
            if (!$subset) {
                continue;
            }
            $out .= self::core()->heading(get_string($optional ? 'rolesoptional' : 'rolesbase', 'tool_vae'), 3);
            if ($optional) {
                $out .= \html_writer::tag('p', s(get_string('rolesoptionalwarning', 'tool_vae')), ['class' => 'text-muted small']);
            }
            $table = new \html_table();
            $table->attributes['class'] = 'generaltable tool-vae-roles';
            $table->head = [
                get_string('rolescolrole', 'tool_vae'),
                get_string('rolescolrights', 'tool_vae'),
                get_string('rolescolstate', 'tool_vae'),
            ];
            foreach ($subset as $preset) {
                $rights = implode(', ', array_map(fn($capability) => s(get_capability_string($capability)), $preset->rights));
                if ($exists[$preset->shortname]) {
                    $state = \html_writer::tag('span', s(get_string('rolesexists', 'tool_vae')), ['class' => 'small text-muted']);
                } else {
                    $state = self::core()->single_button(
                        new \moodle_url($url, ['shortname' => $preset->shortname]),
                        get_string('rolescreatenamed', 'tool_vae', $preset->name),
                        'get'
                    );
                }
                $table->data[] = [
                    s($preset->name) . \html_writer::div(s($preset->description), 'small text-muted'),
                    $rights,
                    $state,
                ];
            }
            $out .= \html_writer::table($table);
        }

        return $out;
    }

    /**
     * What was drawn above the confirmation of the core before the migration (its code, kept as the expected value).
     *
     * @param \stdClass $preset
     * @return string HTML.
     */
    private static function before_confirm(\stdClass $preset): string {
        $rights = array_map(
            fn($capability) => \html_writer::tag('li', s(get_capability_string($capability)) . ' (' . s($capability) . ')'),
            $preset->rights
        );

        return \html_writer::tag('p', s(get_string('rolesconfirmwhat', 'tool_vae', $preset->name)))
            . \html_writer::tag('ul', implode('', $rights));
    }

    public function test_the_pages_are_named_templates(): void {
        global $PAGE;

        $renderer = $PAGE->get_renderer('tool_vae');
        $presets = role_installer::presets();
        $list = new roles_install_page($presets, array_fill_keys(array_keys($presets), false), $this->url);
        $confirm = new roles_install_confirm_page(reset($presets));

        foreach ([$list, $confirm] as $page) {
            $this->assertInstanceOf(\core\output\renderable::class, $page);
            $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        }
        $this->assertSame('tool_vae/roles_install_page', $list->get_template_name($renderer));
        $this->assertSame('tool_vae/roles_install_confirm_page', $confirm->get_template_name($renderer));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        $presets = role_installer::presets();
        $none = array_fill_keys(array_keys($presets), false);
        $some = $none;
        $some[array_key_first($presets)] = true;
        $some[array_key_last($presets)] = true;
        $base = array_filter($presets, fn($preset) => !$preset->optional);

        $cases = [
            'nothing exists' => [$presets, $none],
            'some exist' => [$presets, $some],
            'only the base ones' => [$base, $none],
        ];
        foreach ($cases as $name => [$p, $exists]) {
            $this->assertSame(
                self::canon(self::before_list($p, $exists, $this->url)),
                self::canon($this->render(new roles_install_page($p, $exists, $this->url))),
                $name
            );
        }
        foreach ($presets as $shortname => $preset) {
            $this->assertSame(
                self::canon(self::before_confirm($preset)),
                self::canon($this->render(new roles_install_confirm_page($preset))),
                $shortname
            );
        }
    }

    public function test_the_contexts_hold_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $renderer = $PAGE->get_renderer('tool_vae');
        $presets = role_installer::presets();
        $preset = reset($presets);

        $context = (new roles_install_page($presets, array_fill_keys(array_keys($presets), false), $this->url))
            ->export_for_template($renderer);
        $this->assertSame(get_string('rolesintro', 'tool_vae'), $context['intro']);
        $this->assertCount(2, $context['groups']);
        $this->assertStringContainsString(get_string('rolesbase', 'tool_vae'), $context['groups'][0]['headinghtml']);
        $this->assertFalse($context['groups'][0]['warning']);
        $this->assertSame(get_string('rolesoptionalwarning', 'tool_vae'), $context['groups'][1]['warning']);
        $this->assertStringContainsString('tool-vae-roles', $context['groups'][1]['tablehtml']);

        $confirm = (new roles_install_confirm_page($preset))->export_for_template($renderer);
        $this->assertSame(get_string('rolesconfirmwhat', 'tool_vae', $preset->name), $confirm['what']);
        $this->assertSame(get_capability_string($preset->rights[0]) . ' (' . $preset->rights[0] . ')', $confirm['rights'][0]);
        $this->assertCount(count($preset->rights), $confirm['rights']);
    }

    public function test_a_script_in_a_name_or_a_description_comes_out_escaped(): void {
        $this->resetAfterTest();
        $preset = (object) ['shortname' => 'essai', 'name' => '<script>alert(1)</script>',
            'description' => '<script>alert(2)</script>', 'rights' => ['tool/vae:viewown'], 'optional' => false];

        $html = $this->render(new roles_install_page(['essai' => $preset], ['essai' => false], $this->url))
            . $this->render(new roles_install_confirm_page($preset));

        $this->assertSame(3, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_templates_render_their_example_contexts(): void {
        $this->resetAfterTest();

        foreach (['roles_install_page' => ['headinghtml', 'tablehtml'], 'roles_install_confirm_page' => []] as $name => $keys) {
            $file = __DIR__ . '/../../templates/' . $name . '.mustache';
            $this->assertFileExists($file);
            $source = file_get_contents($file);
            $this->assertStringContainsString('@template tool_vae/' . $name, $source);
            preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
            $this->assertSame($keys, array_values(array_unique($raw[1])), $name);
            $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
            $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
            $context = json_decode($matches[1]);
            $this->assertIsObject($context, json_last_error_msg());

            $html = self::core()->render_from_template('tool_vae/' . $name, $context);

            $this->assertNotSame('', trim($html), $name);
        }
    }
}
