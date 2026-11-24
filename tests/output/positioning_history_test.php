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

use tool_vae\local\positioning_screen;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/positioning_before.php');

/**
 * The history of a rank and one line of a positioning, drawn by pieces (lot 7, MMR-520 ticket 4, wave 3a).
 *
 * WHAT IS ASSERTED. That the pieces positioning_history and positioning_line draw the same DOM as positioning_screen::history()
 * and line() drew before their migration (their code kept in the trait positioning_before), for an evaluation and for a
 * certification, with no line, one line, two lines and four; for each field a line may have or not (the day of the decision,
 * the day it stands until, the reason of an alert, a reason or an alert that is the figure 0), for each author (known, whose
 * account was deleted, unknown, a line restored from an archive); each with a premise. That the context holds plain texts;
 * that a script in a reason or an alert comes out escaped; that the templates place HTML only under the keys named and
 * render their example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(positioning_history::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(positioning_line::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(positioning_screen::class)]
final class positioning_history_test extends \advanced_testcase {
    use dom_canon;
    use positioning_before;

    /** @var \stdClass A person who wrote lines. */
    private $author;

    /** @var \context */
    private $context;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/certify.php'));
        $PAGE->set_context(\context_system::instance());
        $this->author = $this->getDataGenerator()->create_user(['firstname' => 'Dana', 'lastname' => "D'Irection & Co"]);
        $candidate = $this->getDataGenerator()->create_user();
        $this->context = \context_user::instance($candidate->id);
    }

    /**
     * A row of tool_vae_eval or tool_vae_certif, as the managers write it.
     *
     * @param string $status
     * @param int $time
     * @param array $more
     * @return \stdClass
     */
    private function row(string $status, int $time, array $more = []): \stdClass {
        return (object) ($more + [
            'status' => $status,
            'reason' => "Motif a $time.\nSur deux lignes.",
            'authorid' => (int) $this->author->id,
            'importedat' => null,
            'timecreated' => $time,
            'decisiondate' => null,
            'alertreason' => null,
            'validuntil' => null,
        ]);
    }

    /**
     * A piece drawn by the renderer of the plugin for the web.
     *
     * @param \core\output\renderable $piece
     * @return string HTML.
     */
    private function render(\core\output\renderable $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($piece);
    }

    /**
     * The histories of the test, for a kind.
     *
     * @param string $kind
     * @return array[] Name => rows.
     */
    private function histories(string $kind): array {
        $status = $kind === 'certif' ? 'certified' : 'inproof';
        $other = $kind === 'certif' ? 'withdrawn' : 'sufficient';
        $gone = $this->getDataGenerator()->create_user();
        delete_user($gone);

        return [
            'no line' => [],
            'one line' => [$this->row($status, 1762000000)],
            'two lines' => [$this->row($status, 1762000000), $this->row($other, 1762000100)],
            'four lines, every author and every field' => [
                $this->row($status, 1760000000, ['authorid' => null]),
                $this->row($status, 1760000100, ['authorid' => (int) $gone->id, 'decisiondate' => '2026-01-02']),
                $this->row($other, 1760000200, ['importedat' => 1760000300, 'validuntil' => '2027-01-02']),
                $this->row($status, 1760000400, [
                    'decisiondate' => '2026-11-04', 'validuntil' => '2027-11-04', 'alertreason' => "Une alerte\nmotivee.",
                ]),
            ],
            'the figure 0' => [$this->row($status, 1762000000, ['reason' => '0', 'alertreason' => '0'])],
        ];
    }

    public function test_the_pieces_are_named_templates(): void {
        global $PAGE;
        $history = new positioning_history([], $this->context, 'eval');
        $line = new positioning_line($this->row('inproof', 1), $this->context, 'eval');

        foreach (['tool_vae/positioning_history' => $history, 'tool_vae/positioning_line' => $line] as $template => $piece) {
            $this->assertInstanceOf(\core\output\renderable::class, $piece);
            $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
            $this->assertSame($template, $piece->get_template_name($PAGE->get_renderer('tool_vae')));
        }
    }

    public function test_each_history_draws_the_dom_of_before(): void {
        foreach (['eval', 'certif'] as $kind) {
            foreach ($this->histories($kind) as $name => $rows) {
                $before = self::before_pos_history($rows, $this->context, $kind);
                $after = $this->render(new positioning_history($rows, $this->context, $kind));
                $this->assertSame(self::canon($before), self::canon($after), "$kind: $name");
            }
        }
    }

    public function test_the_premises_of_the_histories_hold(): void {
        $certif = $this->histories('certif');
        $eval = $this->histories('eval');
        $draw = fn(array $rows, string $kind) => self::before_pos_history($rows, $this->context, $kind);

        $this->assertStringContainsString(s(get_string('positioningnoline', 'tool_vae')), $draw($certif['no line'], 'certif'));
        $this->assertStringNotContainsString(s(get_string('positioningearlier', 'tool_vae')), $draw($eval['one line'], 'eval'));
        $two = $draw($eval['two lines'], 'eval');
        $this->assertSame(1, substr_count($two, s(get_string('positioningearlier', 'tool_vae'))));
        $this->assertLessThan(strpos($two, 'Motif a 1762000000'), strpos($two, 'Motif a 1762000100'), 'The latest first.');
        $four = $draw($certif['four lines, every author and every field'], 'certif');
        $this->assertSame(1, substr_count($four, s(get_string('positioningearlier', 'tool_vae'))), 'One heading for three lines.');
        $this->assertSame(2, substr_count($four, s(get_string('positioningauthorgone', 'tool_vae'))), 'Unknown and deleted.');
        $this->assertSame(1, substr_count($four, s(get_string('positioningrestored', 'tool_vae'))));
        $this->assertSame(1, substr_count($four, s(fullname($this->author))), 'Known: the line that stands.');
        $this->assertSame(2, substr_count($four, '(' . s(get_string('positioningcertifiedon', 'tool_vae', ''))), 'Two dated.');
        $this->assertSame(2, substr_count($four, s(get_string('positioninguntil', 'tool_vae', '2027-'))), 'Two with a day.');
        $this->assertSame(1, substr_count($four, s(get_string('positioningalertreasonlabel', 'tool_vae'))), 'One alert.');
        $evalfour = $draw($eval['four lines, every author and every field'], 'eval');
        $this->assertStringNotContainsString(s(get_string('positioninguntil', 'tool_vae', '2027-')), $evalfour, 'Not on an eval.');
        $this->assertStringNotContainsString(s(get_string('positioningcertifiedon', 'tool_vae', '')), $evalfour);
        $this->assertSame(1, substr_count($evalfour, s(get_string('positioningalertreasonlabel', 'tool_vae'))));
        $zero = $draw($certif['the figure 0'], 'certif');
        $this->assertStringContainsString('<div>0</div>', $zero);
        $this->assertStringNotContainsString(s(get_string('positioningalertreasonlabel', 'tool_vae')), $zero);
    }

    public function test_a_script_in_a_reason_or_an_alert_comes_out_escaped(): void {
        $rows = [$this->row('certified', 1762000000, [
            'reason' => '<script>alert(1)</script>', 'alertreason' => '<script>alert(2)</script>', 'decisiondate' => '2026-11-04',
        ])];

        $before = self::before_pos_history($rows, $this->context, 'certif');
        $after = $this->render(new positioning_history($rows, $this->context, 'certif'));

        $this->assertSame(self::canon($before), self::canon($after));
        $this->assertSame(2, substr_count($after, '&lt;script&gt;alert('));
        $this->assertStringNotContainsString('<script', $after);
        $this->assertStringContainsString('D&#039;Irection &amp; Co', $after);
    }

    public function test_the_context_of_a_line_holds_plain_texts(): void {
        global $PAGE;
        $row = $this->row('certified', 1762000000, [
            'decisiondate' => '2026-11-04', 'validuntil' => '2027-11-04', 'alertreason' => 'A & B',
        ]);

        $exported = (new positioning_line($row, $this->context, 'certif'))->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertSame(get_string('certifstatuscertified', 'tool_vae')
            . ' (' . get_string('positioningcertifiedon', 'tool_vae', '2026-11-04') . ')'
            . ' - ' . get_string('positioninguntil', 'tool_vae', '2027-11-04'), $exported['status']);
        $this->assertSame(positioning_screen::signature($row), $exported['signature']);
        $this->assertStringContainsString(fullname($this->author), $exported['signature'], 'Plain text: not escaped.');
        $this->assertSame(get_string('positioningalertreasonlabel', 'tool_vae'), $exported['alert']['label']);
        $this->assertStringContainsString('A &amp; B', $exported['alert']['alerthtml'], 'Drawn by format_text().');
        $this->assertNull((new positioning_line($this->row('inproof', 1), $this->context, 'eval'))
            ->export_for_template($PAGE->get_renderer('tool_vae'))['alert']);
    }

    public function test_the_templates_place_html_only_under_the_keys_named_and_render_their_example(): void {
        $templates = [
            'positioning_history' => [['linehtml'], 'tool-vae-history'],
            'positioning_line' => [['reasonhtml', 'alerthtml'], '<em>'],
        ];
        foreach ($templates as $template => [$keys, $mark]) {
            $source = file_get_contents(__DIR__ . '/../../templates/' . $template . '.mustache');
            $this->assertStringContainsString('@template tool_vae/' . $template, $source);
            preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $placed);
            $this->assertSame($keys, array_values(array_unique($placed[1])), $template);
            $this->assertSame(substr_count($source, '{{{'), count($placed[1]), $template);
            $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches), $template);
            $context = json_decode($matches[1]);
            $this->assertIsObject($context, $template . ': ' . json_last_error_msg());

            $this->assertStringContainsString($mark, self::core()->render_from_template('tool_vae/' . $template, $context));
        }
    }
}
