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

use tool_vae\local\feasibility_fields;
use tool_vae\local\text_diff;

/**
 * The page of the versions of a section (history.php) drawn by tool_vae\output\history_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That each state of the page draws, below its heading, the same DOM as before its migration (the code of
 * the page as it was, kept here, before()): no version; one version; three versions read by a reader and by the vaeiste
 * (the forms of restoration), a version restored from another, a note, an author gone, a section of separate fields; the
 * way back of a section, of a work situation, or none. The tool of equivalence shows four states of the page on its data;
 * the others are proved here. Also: a script and a "0" in the texts that come from the data, the context, the keys placed as
 * HTML, the example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(history_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(history_cell::class)]
final class history_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url This page. */
    private \moodle_url $url;

    #[\Override]
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->url = new \moodle_url('/admin/tool/vae/history.php', ['draftid' => 5]);
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param history_page $page
     * @return string HTML.
     */
    private function render(history_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * A draft made by hand.
     *
     * @param string $section
     * @return \stdClass
     */
    private static function draft(string $section = 'objectifs'): \stdClass {
        return (object) ['id' => 5, 'candidateid' => 7, 'doctype' => 'faisabilite', 'section' => $section, 'slot' => 0,
            'fichecode' => '', 'workingat' => 1234];
    }

    /**
     * Versions made by hand, by number.
     *
     * @param int|null $author The author of the versions; the second has none (an account gone).
     * @param string $note The note of the first version.
     * @return \stdClass[]
     */
    private static function versions(?int $author, string $note = 'Première version.'): array {
        $version = fn(int $number, ?int $authorid, string $content, string $note, ?int $from) => (object) [
            'number' => $number, 'authorid' => $authorid, 'content' => $content, 'note' => $note, 'restoredfrom' => $from,
            'timecreated' => make_timestamp(2026, 10, 10 + $number, 9, 0, 0, 99),
        ];

        return [
            1 => $version(1, $author, "Un texte.\nDeux lignes.", $note, null),
            2 => $version(2, null, "Un texte.\nTrois lignes.\nEncore.", '', null),
            3 => $version(3, $author, "Un texte.\nDeux lignes.", 'Retour.', 1),
        ];
    }

    /**
     * The names of the authors as the page reads them.
     *
     * @param \stdClass[] $versions
     * @return string[]
     */
    private static function authors(array $versions): array {
        $authors = [];
        foreach ($versions as $number => $version) {
            $user = $version->authorid !== null ? \core_user::get_user((int) $version->authorid) : false;
            $authors[$number] = $user ? fullname($user) : get_string('historyauthorerased', 'tool_vae');
        }

        return $authors;
    }

    /**
     * history.php before its migration, below the heading.
     *
     * @param string $line
     * @param \moodle_url|null $back
     * @param bool $issituation
     * @param \stdClass $draft
     * @param \stdClass[] $versions
     * @param bool $canwrite
     * @return string
     */
    private function before(
        string $line,
        ?\moodle_url $back,
        bool $issituation,
        \stdClass $draft,
        array $versions,
        bool $canwrite
    ): string {
        $url = $this->url;
        $draftid = (int) $draft->id;
        $out = \html_writer::tag('p', s($line));
        if ($back !== null) {
            $out .= \html_writer::tag('p', \html_writer::link(
                $back,
                get_string($issituation ? 'situationbacktosituation' : 'historyback', 'tool_vae')
            ));
        }
        if (!$versions) {
            $empty = get_string('historyempty', 'tool_vae');

            return $out . self::core()->notification($empty, \core\output\notification::NOTIFY_INFO);
        }

        $numbers = array_keys($versions);
        $latest = max($numbers);
        $out .= \html_writer::start_tag('form', [
            'method' => 'get',
            'action' => (new \moodle_url('/admin/tool/vae/compare.php'))->out(false),
        ]);
        $out .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'draftid', 'value' => $draftid]);

        $table = new \html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [
            get_string('historyfrom', 'tool_vae'),
            get_string('historyto', 'tool_vae'),
            get_string('historyversion', 'tool_vae'),
            get_string('historydate', 'tool_vae'),
            get_string('historyauthor', 'tool_vae'),
            get_string('historynote', 'tool_vae'),
            get_string('historysize', 'tool_vae'),
            get_string('historychanges', 'tool_vae'),
            '',
        ];
        $sorted = $versions;
        krsort($sorted);
        foreach ($sorted as $number => $version) {
            $previous = $versions[$number - 1] ?? null;
            $changes = '';
            if ($previous !== null) {
                $counts = text_diff::counts(text_diff::diff(
                    feasibility_fields::readable((string) $draft->section, (string) $previous->content),
                    feasibility_fields::readable((string) $draft->section, (string) $version->content)
                ));
                $changes = get_string('diffsummary', 'tool_vae', (object) $counts);
            }
            $by = '';
            if ($version->authorid !== null && $user = \core_user::get_user((int) $version->authorid)) {
                $by = fullname($user);
            } else {
                $by = get_string('historyauthorerased', 'tool_vae');
            }
            $label = get_string('historyversion', 'tool_vae') . ' ' . $number;
            $restore = '';
            if ($canwrite && $number !== $latest) {
                $restore = \html_writer::tag('button', get_string('historyrestore', 'tool_vae'), [
                    'type' => 'submit',
                    'form' => 'tool-vae-restore-' . $number,
                    'class' => 'btn btn-secondary btn-sm',
                ]);
            }
            $note = s((string) $version->note);
            if ($version->restoredfrom !== null) {
                $note = trim(s(get_string('historyrestoredfrom', 'tool_vae', (int) $version->restoredfrom)) . ' ' . $note);
            }
            $table->data[] = [
                \html_writer::empty_tag('input', [
                    'type' => 'radio', 'name' => 'from', 'value' => $number,
                    'aria-label' => get_string('historyfrom', 'tool_vae') . ': ' . $label,
                    'checked' => $number === ($latest - 1 > 0 ? $latest - 1 : $latest) ? 'checked' : null,
                ]),
                \html_writer::empty_tag('input', [
                    'type' => 'radio', 'name' => 'to', 'value' => $number,
                    'aria-label' => get_string('historyto', 'tool_vae') . ': ' . $label,
                    'checked' => $number === $latest ? 'checked' : null,
                ]),
                \html_writer::link(
                    new \moodle_url('/admin/tool/vae/compare.php', ['draftid' => $draftid, 'from' => $number, 'to' => $number]),
                    $label
                ),
                userdate((int) $version->timecreated),
                s($by),
                $note,
                \core_text::strlen(feasibility_fields::readable((string) $draft->section, (string) $version->content)),
                s($changes),
                $restore,
            ];
        }
        $out .= \html_writer::table($table);
        $out .= \html_writer::tag('button', get_string('historycompare', 'tool_vae'), [
            'type' => 'submit', 'class' => 'btn btn-primary',
        ]);
        $out .= \html_writer::end_tag('form');
        if ($canwrite) {
            foreach ($numbers as $number) {
                if ($number === $latest) {
                    continue;
                }
                $out .= \html_writer::start_tag('form', [
                    'method' => 'post', 'action' => $url->out(false), 'id' => 'tool-vae-restore-' . $number,
                ]);
                $out .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                $out .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'restore']);
                $out .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'number', 'value' => $number]);
                $out .= \html_writer::empty_tag('input', [
                    'type' => 'hidden', 'name' => 'workingat', 'value' => (int) ($draft->workingat ?? 0),
                ]);
                $out .= \html_writer::end_tag('form');
            }
        }

        return $out;
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');

        $page = new history_page('x', null, false, self::draft(), [], [], false, $this->url);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/history_page', $page->get_template_name($output));
        $this->assertSame('tool_vae/history_cell', history_cell::restore(1, 'x')->get_template_name($output));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $author = (int) $this->getDataGenerator()->create_user(['firstname' => 'Lucie', 'lastname' => 'Martin'])->id;
        $versions = self::versions($author);
        $section = new \moodle_url('/admin/tool/vae/section.php', [
            'doctype' => 'faisabilite', 'section' => 'objectifs', 'slot' => 0,
        ]);
        $situation = new \moodle_url('/admin/tool/vae/situation.php', ['userid' => 7]);
        $line = get_string('faisabsectionline', 'tool_vae', 'Objectifs');
        $fields = [1 => (object) ['number' => 1, 'authorid' => $author, 'note' => '', 'restoredfrom' => null,
            'content' => json_encode(['nom' => 'Martin']), 'timecreated' => 0]];
        $cases = [
            'no version, the way back' => [$line, $section, false, self::draft(), [], true],
            'no version, a reader' => [$line, null, false, self::draft(), [], false],
            'one version' => [$line, $section, false, self::draft(), [1 => $versions[1]], true],
            'three versions, the vaeiste' => [$line, $section, false, self::draft(), $versions, true],
            'three versions, a reader' => [$line, null, false, self::draft(), $versions, false],
            'a work situation' => [$line, $situation, true, self::draft(), $versions, true],
            'separate fields' => [$line, null, false, self::draft('identite'), $fields, false],
            'texts equal to 0' => ['0', $section, false, self::draft(), self::versions(null, '0'), true],
        ];
        foreach ($cases as $name => [$text, $back, $issituation, $draft, $list, $canwrite]) {
            $html = $this->render(new history_page(
                $text,
                $back,
                $issituation,
                $draft,
                $list,
                self::authors($list),
                $canwrite,
                $this->url
            ));
            $this->assertSame(
                self::canon($this->before($text, $back, $issituation, $draft, $list, $canwrite)),
                self::canon($html),
                $name
            );
        }
        // Premises: two forms of restoration for the vaeiste, none for a reader; an author gone; a restored version.
        $all = $this->before($line, $section, false, self::draft(), $versions, true);
        $this->assertSame(2, substr_count($all, 'name="action" value="restore"'));
        $this->assertSame(0, substr_count($this->before($line, null, false, self::draft(), $versions, false), 'value="restore"'));
        $this->assertStringContainsString(s(get_string('historyauthorerased', 'tool_vae')), $all);
        $this->assertStringContainsString('Lucie Martin', $all);
        $this->assertStringContainsString(s(get_string('historyrestoredfrom', 'tool_vae', 1)), $all);
        $this->assertSame(2, substr_count($all, 'checked="checked"'));
    }

    public function test_the_size_and_the_changes_count_the_lines_read_in_characters_as_before(): void {
        // Added by the pilot of lot 7: the content of "separate fields" above holds a key the plugin refuses, so that it is
        // counted as it is stored, and no text above holds an accent. Here the fields are ones the plugin decodes, with
        // accents: the size is the one of the lines a reader reads, in characters, and the changes are counted on them.
        $identity = fn(array $more) => json_encode($more + [
            'nom_naissance' => 'Lefèvre', 'nationalite' => 'FR', 'certifications' => 'CAP Petite enfance, obtenu à Sète',
        ]);
        $version = fn(int $number, string $content) => (object) [
            'number' => $number, 'authorid' => null, 'content' => $content, 'note' => '', 'restoredfrom' => null,
            'timecreated' => make_timestamp(2026, 10, 10 + $number, 9, 0, 0, 99),
        ];
        $versions = [1 => $version(1, $identity([])), 2 => $version(2, $identity(['date_naissance' => '1984-03-12']))];
        $line = get_string('faisabsectionline', 'tool_vae', 'Identité');
        $draft = self::draft('identite');

        $before = $this->before($line, null, false, $draft, $versions, false);
        $html = $this->render(new history_page(
            $line,
            null,
            false,
            $draft,
            $versions,
            self::authors($versions),
            false,
            $this->url
        ));

        $this->assertSame(self::canon($before), self::canon($html));
        // Premises: what is counted is the text read, not the stored one, and its characters, not its bytes.
        $read = feasibility_fields::readable('identite', $versions[1]->content);
        $this->assertStringContainsString('Lefèvre', $read);
        $this->assertNotSame($versions[1]->content, $read);
        $size = \core_text::strlen($read);
        $this->assertNotSame($size, strlen($read));
        $this->assertNotSame($size, \core_text::strlen($versions[1]->content));
        $this->assertSame(1, preg_match('~<td[^>]*>' . $size . '</td>~', $before));
    }

    public function test_a_script_in_what_comes_from_the_data_comes_out_escaped(): void {
        $script = '<script>alert(1)</script>';
        $versions = self::versions(null, $script);

        $html = $this->render(new history_page(
            $script,
            null,
            false,
            self::draft(),
            $versions,
            self::authors($versions),
            true,
            $this->url
        ));

        $this->assertStringNotContainsString('<script', $html);
        // The line, the note.
        $this->assertSame(2, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
        $this->assertSame(self::canon($this->before($script, null, false, self::draft(), $versions, true)), self::canon($html));

        // The core cleans the name of an account of its tags: the names the page reads are given here as they could come.
        $html = $this->render(new history_page(
            'x',
            null,
            false,
            self::draft(),
            $versions,
            [1 => $script, 2 => 'y', 3 => $script],
            true,
            $this->url
        ));
        $this->assertStringNotContainsString('<script', $html);
        $this->assertSame(3, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'The note, two names.');
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');
        $versions = self::versions(null);

        $context = (new history_page('a<b', null, false, self::draft(), $versions, self::authors($versions), true, $this->url))
            ->export_for_template($output);

        $this->assertSame('a<b', $context['line']);
        $this->assertFalse($context['back']);
        $this->assertFalse($context['emptyhtml']);
        $this->assertSame(5, $context['compare']['draftid']);
        $this->assertSame(get_string('historycompare', 'tool_vae'), $context['compare']['label']);
        $this->assertSame([1, 2], array_column($context['restores'], 'number'));
        $this->assertSame(1234, $context['restores'][0]['workingat']);
        $this->assertSame($this->url->out(false), $context['restores'][0]['action']);

        $empty = (new history_page('x', $this->url, true, self::draft(), [], [], false, $this->url))->export_for_template($output);
        $this->assertSame(get_string('situationbacktosituation', 'tool_vae'), $empty['back']['label']);
        $this->assertFalse($empty['compare']);
        $this->assertSame([], $empty['restores']);
        $this->assertSame(
            ['radio' => ['name' => 'to', 'value' => 3, 'label' => 'l', 'checked' => true], 'restore' => false],
            history_cell::radio('to', 3, 'l', true)->export_for_template($output)
        );
    }

    public function test_only_the_html_of_the_core_and_of_the_pieces_is_placed_as_it_is(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/history_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['emptyhtml', 'tablehtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertStringNotContainsString('{{{', file_get_contents(__DIR__ . '/../../templates/history_cell.mustache'));
    }

    public function test_the_templates_render_their_example_context(): void {
        foreach (['history_page' => '<form', 'history_cell' => 'type="radio"'] as $template => $mark) {
            $source = file_get_contents(__DIR__ . '/../../templates/' . $template . '.mustache');
            $this->assertStringContainsString('@template tool_vae/' . $template, $source);
            $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches), $template);
            $context = json_decode($matches[1]);
            $this->assertIsObject($context, $template . ': ' . json_last_error_msg());

            $html = self::core()->render_from_template('tool_vae/' . $template, $context);

            $this->assertStringContainsString($mark, $html, $template);
        }
    }
}
