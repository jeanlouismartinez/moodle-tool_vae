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

use tool_vae\local\positioning_screen;

/**
 * The page of the certifications to renew (renewals.php) drawn by tool_vae\output\renewals_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration: with no certification
 * past its date (the only state of the bench), and with a table (in no instant of the tool of equivalence) holding the
 * reader's own line (no link), a line that cannot be renewed from here (one sentence per cause) and a line to renew. The
 * "before" is the code of the page as it was, kept here as the expected value. Also: that the context holds plain texts,
 * that a script in a name or a title comes out escaped, that the template renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(renewals_page::class)]
final class renewals_page_test extends \advanced_testcase {
    use dom_canon;

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param renewals_page $page
     * @return string HTML.
     */
    private function render(renewals_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The page below its heading as renewals.php drew it before its migration (its code, kept as the expected value).
     *
     * @param string $today
     * @param \stdClass[] $rows
     * @param int $readerid
     * @return string HTML.
     */
    private static function before(string $today, array $rows, int $readerid): string {
        $out = \html_writer::tag('p', s(get_string('positioningdisclaimer', 'tool_vae')), ['class' => 'text-muted small']);
        $out .= \html_writer::tag('p', s(get_string('renewalsintro', 'tool_vae')));
        $out .= \html_writer::tag('p', s(get_string('renewalsasof', 'tool_vae', $today)), ['class' => 'text-muted small']);
        if (!$rows) {
            return $out . \html_writer::tag('p', s(get_string('renewalsnone', 'tool_vae')));
        }
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-renewals';
        $table->head = [
            get_string('renewalscolcandidate', 'tool_vae'),
            get_string('renewalscolrecord', 'tool_vae'),
            get_string('renewalscolbloc', 'tool_vae'),
            get_string('renewalscoluntil', 'tool_vae'),
            get_string('renewalscoldays', 'tool_vae'),
            get_string('renewalscolaction', 'tool_vae'),
        ];
        foreach ($rows as $row) {
            $candidateid = (int) $row->candidateid;
            $dossier = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]);
            $page = positioning_screen::url('certify', $candidateid, (string) $row->fichecode, (string) $row->ficherang);
            $own = $readerid === $candidateid;
            if ($own) {
                $action = \html_writer::tag('span', s(get_string('renewalsreadonly', 'tool_vae')), ['class' => 'small text-muted']);
            } else if (!$row->held) {
                $action = \html_writer::tag(
                    'span',
                    s(get_string('renewals' . $row->reason, 'tool_vae')),
                    ['class' => 'small text-muted']
                );
            } else {
                $action = \html_writer::link($page, get_string('positioningrenewlink', 'tool_vae'));
            }
            $table->data[] = [
                $own ? s(fullname($row)) : \html_writer::link($dossier, s(fullname($row))),
                s($row->fichecode),
                s($row->label),
                s($row->validuntil),
                (int) $row->days,
                $action,
            ];
        }

        return $out . \html_writer::table($table);
    }

    /**
     * A row of renewal_report::rows().
     *
     * @param \stdClass $user
     * @param bool $held
     * @param string $reason
     * @param string $label
     * @return \stdClass
     */
    private static function row(\stdClass $user, bool $held, string $reason, string $label = 'Bloc 1'): \stdClass {
        $row = (object) [
            'candidateid' => $user->id, 'fichecode' => 'RNCP40692', 'ficherang' => 'BC01', 'label' => $label,
            'validuntil' => '2026-11-01', 'days' => 18, 'held' => $held, 'reason' => $reason,
        ];
        foreach (\core_user\fields::get_name_fields() as $field) {
            $row->$field = $user->$field;
        }

        return $row;
    }

    /**
     * Three rows: the reader's own, one gone from the site, one to renew.
     *
     * @return array [rows, reader id]
     */
    private function rows(): array {
        $gen = $this->getDataGenerator();
        $reader = $gen->create_user(['firstname' => 'Dora', 'lastname' => 'Direction']);
        $a = $gen->create_user(['firstname' => 'Camille', 'lastname' => 'Durand']);
        $b = $gen->create_user(['firstname' => 'Lucie', 'lastname' => 'Martin']);

        return [[self::row($reader, true, ''), self::row($a, false, 'gone'), self::row($b, true, '')], (int) $reader->id];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;

        $page = new renewals_page('2026-11-19', [], 2);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/renewals_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        [$rows, $reader] = $this->rows();
        $gen = $this->getDataGenerator();
        foreach (['disabled', 'undeclared'] as $reason) {
            $rows[] = self::row($gen->create_user(), false, $reason);
        }

        foreach (['none' => [], 'table' => $rows] as $name => $list) {
            $expected = self::before('2026-11-19', $list, $reader);
            $this->assertSame(self::canon($expected), self::canon($this->render(new renewals_page(
                '2026-11-19',
                $list,
                $reader
            ))), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$rows, $reader] = $this->rows();

        $context = (new renewals_page('2026-11-19', $rows, $reader))->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertSame(get_string('positioningdisclaimer', 'tool_vae'), $context['disclaimer']);
        $this->assertSame(get_string('renewalsintro', 'tool_vae'), $context['intro']);
        $this->assertSame(get_string('renewalsasof', 'tool_vae', '2026-11-19'), $context['asof']);
        $this->assertFalse($context['none']);
        $this->assertStringContainsString('tool-vae-renewals', $context['tablehtml']);

        $empty = (new renewals_page('2026-11-19', [], $reader))->export_for_template($PAGE->get_renderer('tool_vae'));
        $this->assertSame(get_string('renewalsnone', 'tool_vae'), $empty['none']);
        $this->assertFalse($empty['tablehtml']);
    }

    public function test_a_script_in_a_name_or_a_title_comes_out_escaped(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['lastname' => 'X']);
        // The generator strips tags from a name: the record read by the page is given the script in memory.
        $user->firstname = '<script>alert(1)</script>';

        $html = $this->render(new renewals_page('2026-11-19', [self::row($user, true, '', '<script>alert(2)</script>')], 2));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/renewals_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/renewals_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['tablehtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/renewals_page', $context);

        $this->assertStringContainsString('text-muted small', $html);
    }
}
