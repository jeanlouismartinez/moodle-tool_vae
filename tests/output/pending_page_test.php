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

use tool_vae\local\display_date;
use tool_vae\local\pending_report;

/**
 * The page of the declarations awaiting an attestation (pending.php) drawn by tool_vae\output\pending_page (lot 7).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration in each of its states:
 * nothing awaits (in no instant of the tool of equivalence), nobody is left without a manager to tell, the table with the
 * oldest first, the box ticked or not, a number of days given. The "before" is the code of the page as it was, kept here as
 * the expected value. Also: that the context holds plain texts and numbers, that a script in a name comes out escaped, that
 * the template renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(pending_page::class)]
final class pending_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var int A fixed time of reading. */
    private const NOW = 1795000000;

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param pending_page $page
     * @return string HTML.
     */
    private function render(pending_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The page below its heading as pending.php drew it before its migration (its code, kept as the expected value).
     *
     * @param int $days
     * @param bool $unreached
     * @param \moodle_url $action
     * @param array $groups After the filter of the box.
     * @param array $reach
     * @param array $users
     * @param string $none The key of the sentence drawn when no group is left.
     * @return string HTML.
     */
    private static function before(
        int $days,
        bool $unreached,
        \moodle_url $action,
        array $groups,
        array $reach,
        array $users,
        string $none
    ): string {
        $out = \html_writer::tag('p', s(get_string('pendingintro', 'tool_vae')));
        $form = \html_writer::tag('label', s(get_string('pendingdays', 'tool_vae')), ['for' => 'pendingdays'])
            . ' ' . \html_writer::empty_tag('input', [
                'type' => 'number', 'name' => 'days', 'id' => 'pendingdays', 'min' => 0,
                'max' => pending_report::MAX_DAYS, 'value' => $days,
            ])
            . ' ' . \html_writer::empty_tag('input', [
                'type' => 'checkbox', 'name' => 'unreached', 'id' => 'pendingunreached', 'value' => 1,
            ] + ($unreached ? ['checked' => 'checked'] : []))
            . ' ' . \html_writer::tag('label', s(get_string('pendingunreached', 'tool_vae')), ['for' => 'pendingunreached'])
            . ' ' . \html_writer::empty_tag('input', [
                'type' => 'submit', 'class' => 'btn btn-secondary', 'value' => get_string('pendingshow', 'tool_vae'),
            ]);
        $out .= \html_writer::tag('form', $form, ['method' => 'get', 'action' => $action->out_omit_querystring()]);
        if (!$groups) {
            return $out . \html_writer::tag('p', s(get_string($none, 'tool_vae')));
        }
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-pending';
        $table->caption = get_string('pendingcaption', 'tool_vae');
        $table->captionhide = true;
        $table->head = [
            get_string('pendingcolcandidate', 'tool_vae'),
            get_string('pendingcolcount', 'tool_vae'),
            get_string('pendingcololdest', 'tool_vae'),
            get_string('pendingcoldays', 'tool_vae'),
            get_string('pendingcolreach', 'tool_vae'),
        ];
        $now = self::NOW;
        uasort($groups, fn($a, $b) => $a['oldest'] <=> $b['oldest']);
        foreach ($groups as $candidateid => $group) {
            $user = $users[$candidateid] ?? null;
            $name = $user ? fullname($user) : (string) $candidateid;
            $table->data[] = [
                \html_writer::link(new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]), s($name)),
                (int) $group['count'],
                s(display_date::format((int) $group['oldest'], get_string('strftimedate', 'langconfig'))),
                (int) floor(($now - $group['oldest']) / DAYSECS),
                (int) $reach[$candidateid],
            ];
        }

        return $out . \html_writer::table($table);
    }

    /**
     * Three vaeistes with declarations waiting, one of them gone from the site.
     *
     * @return array [groups, reach, users]
     */
    private function waiting(): array {
        $gen = $this->getDataGenerator();
        $a = $gen->create_user(['firstname' => 'Camille', 'lastname' => 'Durand']);
        $b = $gen->create_user(['firstname' => 'Lucie', 'lastname' => 'Martin']);
        $groups = [
            $b->id => ['count' => 5, 'oldest' => self::NOW - 31 * DAYSECS],
            $a->id => ['count' => 6, 'oldest' => self::NOW - 37 * DAYSECS - 10],
            999999 => ['count' => 1, 'oldest' => self::NOW - 2 * DAYSECS],
        ];

        return [$groups, [$a->id => 3, $b->id => 0, 999999 => 1], [$a->id => $a, $b->id => $b]];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;

        $page = new pending_page(0, false, new \moodle_url('/admin/tool/vae/pending.php'), [], [], [], self::NOW, 'pendingnone');

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/pending_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        [$groups, $reach, $users] = $this->waiting();
        $action = new \moodle_url('/admin/tool/vae/pending.php', ['days' => 30]);

        $cases = [
            'none' => [0, false, [], [], [], 'pendingnone'],
            'none unreached' => [0, true, [], [], [], 'pendingnoneunreached'],
            'table' => [0, false, $groups, $reach, $users, 'pendingnone'],
            'table ticked, days' => [30, true, $groups, $reach, $users, 'pendingnoneunreached'],
        ];
        foreach ($cases as $name => [$days, $unreached, $g, $r, $u, $none]) {
            $page = new pending_page($days, $unreached, $action, $g, $r, $u, self::NOW, $none);
            $expected = self::before($days, $unreached, $action, $g, $r, $u, $none);
            $this->assertSame(self::canon($expected), self::canon($this->render($page)), $name);
        }
    }

    public function test_the_context_holds_plain_texts_and_numbers(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$groups, $reach, $users] = $this->waiting();

        $context = (new pending_page(
            30,
            true,
            new \moodle_url('/admin/tool/vae/pending.php', ['days' => 30]),
            $groups,
            $reach,
            $users,
            self::NOW,
            'pendingnoneunreached'
        ))->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertSame(get_string('pendingintro', 'tool_vae'), $context['intro']);
        $this->assertSame([
            'action' => (new \moodle_url('/admin/tool/vae/pending.php'))->out(false),
            'dayslabel' => get_string('pendingdays', 'tool_vae'),
            'days' => 30,
            'max' => pending_report::MAX_DAYS,
            'unreached' => true,
            'unreachedlabel' => get_string('pendingunreached', 'tool_vae'),
            'show' => get_string('pendingshow', 'tool_vae'),
        ], $context['form']);
        $this->assertFalse($context['none']);
        $this->assertStringContainsString('tool-vae-pending', $context['tablehtml']);

        $empty = (new pending_page(
            0,
            false,
            new \moodle_url('/admin/tool/vae/pending.php'),
            [],
            [],
            [],
            self::NOW,
            'pendingnone'
        ))->export_for_template($PAGE->get_renderer('tool_vae'));
        $this->assertFalse($empty['form']['unreached']);
        $this->assertSame(get_string('pendingnone', 'tool_vae'), $empty['none']);
        $this->assertFalse($empty['tablehtml']);
    }

    public function test_a_script_in_a_name_comes_out_escaped(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['lastname' => 'X']);
        // The generator strips tags from a name: the record read by the page is given the script in memory.
        $user->firstname = '<script>alert(1)</script>';

        $html = $this->render(new pending_page(
            0,
            false,
            new \moodle_url('/admin/tool/vae/pending.php'),
            [$user->id => ['count' => 1, 'oldest' => self::NOW]],
            [$user->id => 0],
            [$user->id => $user],
            self::NOW,
            'pendingnone'
        ));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/pending_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/pending_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['tablehtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/pending_page', $context);

        $this->assertStringContainsString('id="pendingdays"', $html);
        $this->assertStringContainsString('checked="checked"', $html);
    }
}
