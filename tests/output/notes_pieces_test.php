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

use tool_vae\local\note_manager;
use tool_vae\local\note_screen;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/situation_before.php');

/**
 * The pieces of the annotations (notes_block, notes_orphans, note_lines) draw the DOM note_screen drew before lot 7
 * (MMR-520 ticket 4, wave 4a), kept in the trait situation_before: each kind of line (standing in plain text or Markdown,
 * withdrawn, restored, by an author erased, by an account deleted, by the reader with and without an address), each target,
 * the form given or not to whoever may or may not write, nothing, and the escaping, once.
 *
 * The lines are written as the table holds them (the fields the pieces read: state, textformat, text, authorid, authoruuid,
 * importedat, timecreated, targetuuid, slot, fichecode), with ->standing as note_manager::for_target() adds it.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(notes_block::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(notes_orphans::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(note_lines::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(note_screen::class)]
final class notes_pieces_test extends \advanced_testcase {
    use dom_canon;
    use situation_before;

    /** @var \stdClass The vaeiste. */
    private $vaeiste;

    /** @var \stdClass The manager who annotates and reads. */
    private $manager;

    /** @var \stdClass Another manager, whose account is deleted. */
    private $gone;

    /** @var int The next id of a line. */
    private $next = 100;

    /**
     * The people; the manager reads.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $datagenerator = $this->getDataGenerator();
        $this->vaeiste = $datagenerator->create_user(['firstname' => 'Camille', 'lastname' => 'Vaeiste']);
        $this->manager = $datagenerator->create_user(['firstname' => 'Nadia', 'lastname' => 'Benali']);
        $this->gone = $datagenerator->create_user(['firstname' => 'Remi', 'lastname' => 'Parti']);
        delete_user($this->gone);
        $this->setUser($this->manager);
    }

    /**
     * A line of annotation as the table holds it, with ->standing.
     *
     * @param array $fields
     * @return \stdClass
     */
    private function row(array $fields): \stdClass {
        $this->next++;

        return (object) ($fields + [
            'id' => (string) $this->next,
            'state' => note_manager::ACTIVE,
            'textformat' => (string) FORMAT_PLAIN,
            'text' => 'Une annotation ' . $this->next . '.',
            'authorid' => (string) $this->manager->id,
            'authoruuid' => null,
            'importedat' => null,
            'timecreated' => (string) (1763700000 + $this->next * 60),
            'targetuuid' => 'uuid-a',
            'slot' => '2',
            'fichecode' => 'RNCP100',
            'standing' => true,
        ]);
    }

    /**
     * A piece drawn by the renderer of the plugin for the web.
     *
     * @param \core\output\renderable $piece
     * @return string
     */
    private function render(\core\output\renderable $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($piece);
    }

    /**
     * The old code, drawn with the renderer of the core for the web as $OUTPUT (it reads the global).
     *
     * @param callable $old
     * @return string
     */
    private function before(callable $old): string {
        global $OUTPUT;

        $kept = $OUTPUT;
        $OUTPUT = self::core();
        try {
            return $old();
        } finally {
            $OUTPUT = $kept;
        }
    }

    /**
     * Every kind of line, in the order they would be read.
     *
     * @return \stdClass[]
     */
    private function every_line(): array {
        return [
            $this->row(['text' => 'Mine, standing, in plain text.']),
            $this->row(['text' => "Mine in **Markdown**,\n\nin two paragraphs.", 'textformat' => (string) FORMAT_MARKDOWN]),
            $this->row(['text' => 'Withdrawn.', 'state' => note_manager::WITHDRAWN, 'standing' => false]),
            $this->row(['text' => 'Mine, withdrawn by a later line.', 'standing' => false]),
            $this->row(['text' => 'Restored from an archive.', 'authorid' => null, 'authoruuid' => 'abc-123',
                'importedat' => '1763000000']),
            $this->row(['text' => 'Mine, restored.', 'importedat' => '1763000000']),
            $this->row(['text' => 'Nobody wrote it.', 'authorid' => null]),
            $this->row(['text' => 'By an account deleted.', 'authorid' => (string) $this->gone->id]),
            $this->row(['text' => 'By the vaeiste.', 'authorid' => (string) $this->vaeiste->id]),
        ];
    }

    public function test_the_block_draws_the_dom_of_before_for_each_kind_of_line_target_and_form(): void {
        $id = (int) $this->vaeiste->id;
        $rows = $this->every_line();
        $form = '<form id="tool-vae-note-form" method="post"><input type="text" name="notetext"></form>';
        $states = [
            'situation, the form to whoever writes' => [note_manager::TARGET_SITUATION, 2, '', $rows, true, $form],
            'situation, no right to write' => [note_manager::TARGET_SITUATION, 2, '', $rows, false, $form],
            'self-positioning, no form given' => [note_manager::TARGET_SELFPOS, 0, 'C02', $rows, true, ''],
            'nothing yet' => [note_manager::TARGET_SELFPOS, 0, 'C02', [], true, $form],
            'nothing, reading' => [note_manager::TARGET_SITUATION, 3, '', [], false, ''],
        ];
        foreach ($states as $name => [$target, $slot, $rank, $lines, $canwrite, $formhtml]) {
            $old = $this->before(fn() => self::before_note_block(
                $id,
                $target,
                'RNCP100',
                $slot,
                $rank,
                $lines,
                $canwrite,
                $formhtml
            ));
            $new = $this->render(new notes_block($id, $target, 'RNCP100', $slot, $rank, $lines, $canwrite, $formhtml));
            $this->assertSame(self::canon($old), self::canon($new), $name);
            $this->assertSame($canwrite && $formhtml !== '', str_contains($old, 'tool-vae-note-form'), $name . ': premise.');
            $this->assertSame($lines === [], str_contains($old, s(get_string('notesnone', 'tool_vae'))), $name);
        }

        // The premises of the lines: each kind is in the old drawing.
        $old = $this->before(fn() => self::before_note_block($id, note_manager::TARGET_SITUATION, 'RNCP100', 2, '', $rows, true));
        $this->assertSame(2, substr_count($old, 'withdrawnote'), 'Mine, standing, not restored: two lines.');
        $this->assertSame(1, substr_count($old, s(get_string('notewithdrawn', 'tool_vae'))), 'One withdrawn line.');
        $this->assertSame(2, substr_count($old, 'ms-3 mb-2 text-muted'), 'Two lines that do not stand.');
        $this->assertStringContainsString('<strong>Markdown</strong>', $old, 'Markdown drawn.');
        $this->assertSame(2, substr_count($old, s(get_string('selfposrestored', 'tool_vae'))), 'Two restored lines.');
        $this->assertSame(1, substr_count($old, s(get_string('noteauthorarchive', 'tool_vae'))), 'Restored, nobody named.');
        $this->assertSame(2, substr_count($old, s(get_string('noteauthorgone', 'tool_vae'))), 'Nobody, and a deleted account.');
        // A button of the core: the address is the action of its form, the parameters its hidden fields.
        $this->assertStringContainsString('/admin/tool/vae/situation.php"', $old, 'The withdrawal goes to the situation.');
        $self = $this->before(fn() => self::before_note_block($id, note_manager::TARGET_SELFPOS, 'RNCP100', 0, 'C02', $rows, true));
        $this->assertStringContainsString('/admin/tool/vae/selfposition.php"', $self, 'And to the node.');
        $this->assertStringContainsString('name="rank" value="C02"', $self, 'Of this node.');

        // The vaeiste reads the same lines: no button at all.
        $this->setUser($this->vaeiste);
        $old = $this->before(fn() => self::before_note_block($id, note_manager::TARGET_SITUATION, 'RNCP100', 2, '', $rows, false));
        $new = $this->render(new notes_block($id, note_manager::TARGET_SITUATION, 'RNCP100', 2, '', $rows, false));
        $this->assertSame(self::canon($old), self::canon($new));
        $this->assertSame(1, substr_count($old, 'withdrawnote'), 'The line "by the vaeiste" is theirs.');
    }

    public function test_the_lines_without_an_address_draw_no_button_as_before(): void {
        $id = (int) $this->vaeiste->id;
        $rows = $this->every_line();
        $old = $this->before(fn() => self::before_note_lines($id, $rows, null));
        $new = $this->render(new note_lines($id, $rows, null));
        $this->assertSame(self::canon($old), self::canon($new));
        $this->assertStringNotContainsString('withdrawnote', $old, 'Premise: no address, no button.');
        $this->assertSame(
            self::canon($this->before(fn() => self::before_note_lines($id, [], null))),
            self::canon($this->render(new note_lines($id, [], null)))
        );
    }

    public function test_the_annotations_of_deleted_situations_draw_the_dom_of_before(): void {
        $id = (int) $this->vaeiste->id;
        $this->assertSame('', $this->before(fn() => self::before_note_orphans($id, [])), 'Premise: nothing.');
        $none = new notes_orphans($id, []);
        $this->assertFalse($none->drawn());
        $this->assertSame('', $this->render($none));

        $rows = [
            $this->row(['targetuuid' => 'uuid-a', 'slot' => '2', 'text' => 'Sur la deuxieme.']),
            $this->row(['targetuuid' => 'uuid-b', 'slot' => '0', 'text' => 'Sur une autre.']),
            $this->row(['targetuuid' => 'uuid-a', 'slot' => '2', 'text' => 'Encore sur la deuxieme.',
                'state' => note_manager::WITHDRAWN, 'standing' => false]),
            // An older situation deleted on the SAME slot: another situation (its uuid), never merged with the first.
            $this->row(['targetuuid' => 'uuid-c', 'slot' => '2', 'text' => 'Sur une plus ancienne du meme creneau.']),
        ];
        $orphans = new notes_orphans($id, $rows);
        $this->assertTrue($orphans->drawn());
        $old = $this->before(fn() => self::before_note_orphans($id, $rows));
        $this->assertSame(self::canon($old), self::canon($this->render($orphans)));
        $this->assertSame(3, substr_count($old, '<h4'), 'Premise: three former situations, two of them on the same slot.');
        $this->assertLessThan(strpos($old, 'Sur une autre.'), strpos($old, 'Encore sur la deuxieme.'), 'Grouped by situation.');
        $this->assertStringContainsString(s(get_string('validpart:situation', 'tool_vae', 0)), $old, 'The slot 0.');
        $this->assertStringContainsString('/admin/tool/vae/situations.php"', $old, 'Withdrawn from the list.');
    }

    public function test_a_name_and_a_text_are_escaped_once_as_before(): void {
        global $DB;

        $id = (int) $this->vaeiste->id;
        $DB->set_field('user', 'lastname', 'Ben & "Ali"', ['id' => (int) $this->manager->id]);
        $this->setUser($DB->get_record('user', ['id' => (int) $this->manager->id]));
        $name = fullname($DB->get_record('user', ['id' => (int) $this->manager->id]));
        $this->assertNotSame(s($name), s(s($name)), 'Premise: a second escaping would show.');
        $rows = [
            $this->row(['text' => '<script>alert(1)</script> & "vu"']),
            $this->row(['text' => '0']),
            $this->row(['text' => '0', 'state' => note_manager::WITHDRAWN, 'standing' => false]),
        ];
        $old = $this->before(fn() => self::before_note_block($id, note_manager::TARGET_SITUATION, 'RNCP100', 2, '', $rows, false));
        $new = $this->render(new notes_block($id, note_manager::TARGET_SITUATION, 'RNCP100', 2, '', $rows, false));
        $this->assertSame(self::canon($old), self::canon($new));
        $this->assertStringContainsString('Ben &amp; &quot;Ali&quot;', $new);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $new);
        $this->assertStringNotContainsString('<script', $new);
        $this->assertStringNotContainsString('&amp;amp;', $new);
        $this->assertStringNotContainsString('&amp;quot;', $new);
        $this->assertMatchesRegularExpression('~>0</~', $old, 'Premise: the figure 0 is drawn as a text.');
        $this->assertSame(substr_count($old, '>0<'), substr_count($new, '>0<'), 'The figure 0 as often as before.');
    }

    public function test_the_templates_place_raw_only_the_keys_named_html(): void {
        global $CFG;

        foreach (['note_lines', 'notes_block', 'notes_orphans'] as $name) {
            $text = file_get_contents($CFG->dirroot . '/admin/tool/vae/templates/' . $name . '.mustache');
            preg_match_all('/\{\{\{(\w+)\}\}\}/', $text, $raw);
            foreach ($raw[1] as $key) {
                $this->assertStringEndsWith('html', $key, $name);
            }
        }
    }
}
