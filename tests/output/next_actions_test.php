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

use tool_vae\local\next_actions as actions;

/**
 * The banner "Ce que je peux faire maintenant" drawn by tool_vae\output\next_actions (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That each block draws the same DOM as tool_vae\local\next_actions_screen drew before its migration (its
 * code is kept here, before()): the vaeiste with actions and with none, a single request and several, the manager with
 * lines and with none, a number of 1 and of more. The rest of what the banner says is asserted by
 * tests/next_actions_screen_test.php, which follows the piece. Also: a script in an address comes out escaped.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(next_actions::class)]
final class next_actions_test extends \advanced_testcase {
    use dom_canon;

    /**
     * The piece drawn by the renderer of the plugin.
     *
     * @param next_actions $piece
     * @return string HTML.
     */
    private function render(next_actions $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($piece);
    }

    /**
     * One action or one line made by hand, as next_actions gives them.
     *
     * @param string $key
     * @param int $count
     * @param string $path
     * @param array $params
     * @return \stdClass
     */
    private function made(string $key, int $count, string $path, array $params = []): \stdClass {
        return (object) ['key' => $key, 'count' => $count, 'url' => new \moodle_url($path, $params)];
    }

    /**
     * next_actions_screen::vaeiste() and ::manager() before, with their block().
     *
     * @param bool $manager
     * @param \stdClass[] $entries
     * @return string
     */
    private static function before(bool $manager, array $entries): string {
        $link = fn(\moodle_url|string $url, string $label): array => [
            'url' => $url instanceof \moodle_url ? $url->out(false) : $url, 'label' => $label,
        ];
        $block = fn(array $content): string => self::core()->render_from_template('tool_vae/next_actions', $content + [
            'title' => get_string('nextactionstitle', 'tool_vae'),
            'none' => false,
            'hasbuttons' => false,
            'buttons' => [],
            'haslines' => false,
            'lines' => [],
            'note' => '',
        ]);
        if (!$manager) {
            if (!$entries) {
                return $block(['none' => get_string('nextactionsnone', 'tool_vae')]);
            }
            $buttons = [];
            foreach ($entries as $action) {
                $several = $action->key === actions::ANSWER && (int) $action->count > 1;
                $prefix = $several ? 'nextactionsactions_' : 'nextactionsaction_';
                $buttons[] = $link($action->url, get_string($prefix . $action->key, 'tool_vae', (int) $action->count));
            }

            return $block(['hasbuttons' => true, 'buttons' => $buttons]);
        }
        if (!$entries) {
            return $block(['none' => get_string('nextactionsnonemanager', 'tool_vae')]);
        }
        $lines = [];
        foreach ($entries as $row) {
            $count = (int) $row->count;
            $key = ($count === 1 ? 'nextactionsline_' : 'nextactionslines_') . $row->key;
            $lines[] = $link($row->url, get_string($key, 'tool_vae', $count));
        }

        return $block(['haslines' => true, 'lines' => $lines, 'note' => get_string('nextactionsallblocs', 'tool_vae')]);
    }

    public function test_the_piece_is_a_named_template(): void {
        global $PAGE;

        $piece = next_actions::candidate([]);

        $this->assertInstanceOf(\core\output\renderable::class, $piece);
        $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
        $this->assertSame('tool_vae/next_actions', $piece->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_block_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        $vaeiste = [
            'none' => [],
            'every action' => [
                $this->made(actions::DECLARE, 0, '/admin/tool/vae/declare.php'),
                $this->made(actions::ANSWER, 3, '/admin/tool/vae/dossier.php', ['userid' => 7, 'tab' => 'a&b']),
                $this->made(actions::DRAFT, 0, '/admin/tool/vae/faisabilite.php'),
                $this->made(actions::SELFPOSITION, 0, '/admin/tool/vae/selfposition.php', ['userid' => 7]),
            ],
            'a single request' => [$this->made(actions::ANSWER, 1, '/admin/tool/vae/precision.php', ['evidenceid' => 5])],
        ];
        foreach ($vaeiste as $name => $actions) {
            $this->assertSame(
                self::canon(self::before(false, $actions)),
                self::canon($this->render(next_actions::candidate($actions))),
                "vaeiste, $name"
            );
        }
        $manager = [
            'none' => [],
            'several' => [
                $this->made(actions::ATTEST, 12, '/admin/tool/vae/candidates.php'),
                $this->made(actions::ANSWERS, 1, '/admin/tool/vae/precision.php', ['evidenceid' => 5]),
                $this->made(actions::OPINIONS, 2, '/admin/tool/vae/deposit.php', ['userid' => 9]),
            ],
        ];
        foreach ($manager as $name => $rows) {
            $this->assertSame(
                self::canon(self::before(true, $rows)),
                self::canon($this->render(next_actions::manager($rows))),
                "manager, $name"
            );
        }
        // Premise: the two kinds of block are not the same drawing.
        $this->assertNotSame(self::canon(self::before(true, [])), self::canon(self::before(false, [])));
        $this->assertStringContainsString('<li>', self::before(true, $manager['several']));
    }

    public function test_a_script_in_an_address_comes_out_escaped(): void {
        $this->resetAfterTest();
        $url = '/admin/tool/vae/declare.php?x="><script>alert(1)</script>';
        $actions = [(object) ['key' => actions::DECLARE, 'count' => 0, 'url' => $url]];

        $html = $this->render(next_actions::candidate($actions));

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertSame(self::canon(self::before(false, $actions)), self::canon($html));
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $output = $PAGE->get_renderer('tool_vae');

        $context = next_actions::manager([$this->made(actions::ATTEST, 1, '/admin/tool/vae/candidates.php')])
            ->export_for_template($output);

        $this->assertSame(get_string('nextactionstitle', 'tool_vae'), $context['title']);
        $this->assertTrue($context['haslines']);
        $this->assertFalse($context['hasbuttons']);
        $this->assertFalse($context['none']);
        $this->assertSame([[
            'url' => (new \moodle_url('/admin/tool/vae/candidates.php'))->out(false),
            'label' => get_string('nextactionsline_attest', 'tool_vae', 1),
        ]], $context['lines']);
        $this->assertSame(get_string('nextactionsallblocs', 'tool_vae'), $context['note']);
        $none = next_actions::candidate([])->export_for_template($output);
        $this->assertSame(get_string('nextactionsnone', 'tool_vae'), $none['none']);
    }

    public function test_the_template_places_no_html_as_it_is(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/next_actions.mustache');
        $this->assertStringNotContainsString('{{{', $source);
        $this->assertStringContainsString('tool_vae\output\next_actions', $source);
    }
}
