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

/**
 * The page "Mes vaeistes" (candidates.php) drawn by tool_vae\output\candidates_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as the page did before its migration, in each of
 * its states: the sub-system of competencies off, nobody followed, the followed vaeistes without and with the choice of a
 * bloc (the filter is in no instant of the tool of equivalence: no account of the bench is referent of a bloc), with a bloc
 * chosen. The "before" is the code of the page as it was, kept here as the expected value. Also: that the context holds
 * plain texts and addresses, that a name holding a script comes out escaped (before, the name was placed as it was: the one
 * deliberate difference, which no name of the bench shows), that the template renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(candidates_page::class)]
final class candidates_page_test extends \advanced_testcase {
    use dom_canon;

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param candidates_page $page
     * @return string HTML.
     */
    private function render(candidates_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * Two followed vaeistes and their counts, as the page reads them.
     *
     * @return array [users keyed by id in the order of their names, counts keyed by id]
     */
    private function followed(): array {
        $gen = $this->getDataGenerator();
        $a = $gen->create_user(['firstname' => 'Camille', 'lastname' => 'Durand']);
        $b = $gen->create_user(['firstname' => 'Lucie', 'lastname' => 'Martin']);

        return [[$a->id => $a, $b->id => $b], [$a->id => (object) ['declarations' => 7, 'pending' => 6]]];
    }

    /**
     * A choice of blocs, as candidate_overview::narrowing() gives it.
     *
     * @param string $chosen
     * @return \stdClass
     */
    private static function narrowing(string $chosen): \stdClass {
        $choices = [
            'DEMOBLOCS' => (object) ['fichecode' => 'DEMOBLOCS', 'ficherang' => ''],
            'DEMOBLOCS|BC01' => (object) ['fichecode' => 'DEMOBLOCS', 'ficherang' => 'BC01'],
            'RNCP<1>' => (object) ['fichecode' => 'RNCP<1>', 'ficherang' => 'B&C'],
        ];

        return (object) ['choices' => $choices, 'chosen' => $chosen, 'fichecode' => '', 'ranks' => null];
    }

    /**
     * The page below its heading as candidates.php drew it before its migration (its code, kept as the expected value).
     *
     * @param string $state disabled, nobody or followed.
     * @param array $users
     * @param array $counts
     * @param \stdClass|null $narrowing
     * @param \moodle_url|null $action
     * @return string HTML.
     */
    private static function before(
        string $state,
        array $users = [],
        array $counts = [],
        ?\stdClass $narrowing = null,
        ?\moodle_url $action = null
    ): string {

        if ($state === 'disabled') {
            return self::core()->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                \core\output\notification::NOTIFY_ERROR
            );
        }
        if ($state === 'nobody') {
            return self::core()->notification(
                get_string('nocandidates', 'tool_vae'),
                \core\output\notification::NOTIFY_INFO
            );
        }
        $out = '';
        $choices = $narrowing->choices;
        $chosen = $narrowing->chosen;
        if ($choices) {
            $options = [get_string('candidatesfilterall', 'tool_vae')];
            foreach ($choices as $key => $referent) {
                $options[$key] = $referent->fichecode . ' — ' . ($referent->ficherang === ''
                    ? get_string('referentwhole', 'tool_vae')
                    : get_string('referentblocrank', 'tool_vae', $referent->ficherang));
            }
            $form = \html_writer::tag('label', s(get_string('candidatesfilter', 'tool_vae')), ['for' => 'candidatesbloc'])
                . ' ' . \html_writer::select(array_map('s', $options), 'bloc', $chosen, false, ['id' => 'candidatesbloc'])
                . ' ' . \html_writer::empty_tag('input', [
                    'type' => 'submit', 'class' => 'btn btn-secondary', 'value' => get_string('candidatesfilterapply', 'tool_vae'),
                ]);
            $out .= \html_writer::tag('form', $form, ['method' => 'get', 'action' => $action->out_omit_querystring()]);
            if ($chosen !== '') {
                $out .= \html_writer::tag('p', s(get_string('candidatesfiltered', 'tool_vae', $options[$chosen])));
            }
        }
        $table = new \html_table();
        $table->head = [
            get_string('candidate', 'tool_vae'),
            get_string('candidatedeclarations', 'tool_vae'),
            get_string('candidatepending', 'tool_vae'),
        ];
        $table->attributes['class'] = 'generaltable tool-vae-candidates';
        foreach ($users as $user) {
            $count = $counts[$user->id] ?? null;
            $table->data[] = [
                \html_writer::link(
                    new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $user->id]),
                    fullname($user)
                ),
                (int) ($count->declarations ?? 0),
                (int) ($count->pending ?? 0),
            ];
        }
        $out .= \html_writer::table($table);
        $out .= \html_writer::tag('p', \html_writer::link(
            new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => 'followed']),
            get_string('orgfollowedlink', 'tool_vae')
        ));

        return $out;
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;

        $page = candidates_page::nobody();

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/candidates_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        [$users, $counts] = $this->followed();
        $action = new \moodle_url('/admin/tool/vae/candidates.php', ['bloc' => 'DEMOBLOCS']);
        $none = (object) ['choices' => [], 'chosen' => '', 'fichecode' => '', 'ranks' => null];

        $cases = [
            'disabled' => [candidates_page::disabled(), self::before('disabled')],
            'nobody' => [candidates_page::nobody(), self::before('nobody')],
            'followed' => [candidates_page::followed($users, $counts, $none, $action),
                self::before('followed', $users, $counts, $none, $action)],
            'filter' => [candidates_page::followed($users, $counts, self::narrowing(''), $action),
                self::before('followed', $users, $counts, self::narrowing(''), $action)],
            'filtered' => [candidates_page::followed($users, $counts, self::narrowing('DEMOBLOCS|BC01'), $action),
                self::before('followed', $users, $counts, self::narrowing('DEMOBLOCS|BC01'), $action)],
            'no row' => [candidates_page::followed([], [], $none, $action), self::before('followed', [], [], $none, $action)],
        ];
        foreach ($cases as $name => [$page, $expected]) {
            $this->assertSame(self::canon($expected), self::canon($this->render($page)), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$users, $counts] = $this->followed();
        $action = new \moodle_url('/admin/tool/vae/candidates.php', ['bloc' => 'DEMOBLOCS']);

        $context = candidates_page::followed($users, $counts, self::narrowing('RNCP<1>'), $action)
            ->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertFalse($context['noticehtml']);
        $this->assertSame((new \moodle_url('/admin/tool/vae/candidates.php'))->out(false), $context['filter']['action']);
        $this->assertSame(get_string('candidatesfilter', 'tool_vae'), $context['filter']['label']);
        $this->assertSame(get_string('candidatesfilterapply', 'tool_vae'), $context['filter']['apply']);
        $this->assertSame(
            ['value' => '0', 'label' => get_string('candidatesfilterall', 'tool_vae'), 'selected' => false],
            $context['filter']['options'][0]
        );
        $this->assertSame(['value' => 'RNCP<1>', 'label' => 'RNCP<1> — ' . get_string('referentblocrank', 'tool_vae', 'B&C'),
            'selected' => true], $context['filter']['options'][3]);
        $this->assertSame(get_string('candidatesfiltered', 'tool_vae', 'RNCP<1> — '
            . get_string('referentblocrank', 'tool_vae', 'B&C')), $context['filtered']);
        $this->assertStringContainsString('tool-vae-candidates', $context['tablehtml']);
        $this->assertSame([
            'url' => (new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => 'followed']))->out(false),
            'label' => get_string('orgfollowedlink', 'tool_vae'),
        ], $context['followed']);

        $notice = candidates_page::nobody()->export_for_template($PAGE->get_renderer('tool_vae'));
        $this->assertStringContainsString(get_string('nocandidates', 'tool_vae'), $notice['noticehtml']);
        $this->assertFalse($notice['filter']);
        $this->assertFalse($notice['filtered']);
        $this->assertFalse($notice['tablehtml']);
        $this->assertFalse($notice['followed']);
    }

    public function test_a_script_in_a_name_or_a_rank_comes_out_escaped(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['lastname' => 'X']);
        // The generator strips tags from a name: the record read by the page is given the script in memory.
        $user->firstname = '<script>alert(1)</script>';
        $narrowing = (object) ['choices' => [
            'A' => (object) ['fichecode' => 'A', 'ficherang' => '<script>alert(2)</script>'],
        ], 'chosen' => 'A', 'fichecode' => 'A', 'ranks' => []];

        $html = $this->render(candidates_page::followed(
            [$user->id => $user],
            [],
            $narrowing,
            new \moodle_url('/admin/tool/vae/candidates.php')
        ));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/candidates_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/candidates_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['noticehtml', 'tablehtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/candidates_page', $context);

        $this->assertStringContainsString('id="candidatesbloc"', $html);
        $this->assertStringContainsString('selected="selected"', $html);
    }
}
