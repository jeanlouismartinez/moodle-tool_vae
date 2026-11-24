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

use tool_vae\local\selfpos_tree;
use tool_vae\local\situation_manager;
use tool_vae\local\situation_screen;
use tool_vae\local\spiece_manager;
use tool_vae\local\validation_catalog;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/situation_before.php');

/**
 * The pieces of the work situations (situation_disclaimer, situation_facts, situation_pieces, situation_piece_forms) draw
 * the DOM situation_screen drew before lot 7 (MMR-520 ticket 4, wave 4a), kept in the trait situation_before: the sentences
 * for the vaeiste and for a reader; the facts with one, two, three lines and none, a tag the record no longer holds, a label
 * with an ampersand and quotes; each kind of piece (a link, a file, removed, that the reader may not open, with and without
 * a title and a part, with and without the buttons); the forms with and without a piece to attach; the escaping, once.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(situation_disclaimer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(situation_facts::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(situation_pieces::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(situation_piece_forms::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(situation_screen::class)]
final class situation_pieces_test extends \advanced_testcase {
    use dom_canon;
    use situation_before;

    /** @var \stdClass The vaeiste. */
    private $vaeiste;

    /** @var \stdClass Follows the vaeiste. */
    private $manager;

    /** @var \stdClass Follows nobody. */
    private $stranger;

    /** @var \tool_vae_generator */
    private $generator;

    /**
     * RNCP100 (BC01 holding C01 and C02, the label of C02 with an ampersand and quotes); the people; the preparation and the
     * pieces open, no antivirus asked.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        validation_catalog::reset();
        set_config('validationopen', 1, 'tool_vae');
        set_config('piecesopen', 1, 'tool_vae');
        set_config('piecenoantivirus', 1, 'tool_vae');
        $datagenerator = $this->getDataGenerator();
        $this->generator = $datagenerator->get_plugin_generator('tool_vae');
        $this->generator->create_source(['fichecode' => 'RNCP100', 'ficherang' => 'BC01', 'shortname' => 'Bloc 1']);
        $this->generator->create_competency([
            'fichecode' => 'RNCP100', 'ficherang' => 'C01', 'shortname' => 'Competence C01', 'bloc' => 'BC01',
        ]);
        $this->generator->create_competency([
            'fichecode' => 'RNCP100', 'ficherang' => 'C02', 'shortname' => 'Soins & "aide"', 'bloc' => 'BC01',
        ]);
        $system = \context_system::instance()->id;
        $this->vaeiste = $datagenerator->create_user(['firstname' => 'Camille', 'lastname' => 'Vaeiste']);
        $this->manager = $datagenerator->create_user(['firstname' => 'Nadia', 'lastname' => 'Benali']);
        $this->stranger = $datagenerator->create_user(['firstname' => 'Paul', 'lastname' => 'Ailleurs']);
        $own = $datagenerator->create_role();
        foreach (['declare', 'viewown', 'positionself', 'draftown'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $own, $system, true);
        }
        set_role_contextlevels($own, [CONTEXT_USER]);
        role_assign($own, $this->vaeiste->id, \context_user::instance($this->vaeiste->id)->id);
        $follow = $datagenerator->create_role();
        assign_capability('tool/vae:viewassigned', CAP_ALLOW, $follow, $system, true);
        set_role_contextlevels($follow, [CONTEXT_USER]);
        role_assign($follow, $this->manager->id, \context_user::instance($this->vaeiste->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->vaeiste);
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
     * A situation of the vaeiste on RNCP100.
     *
     * @param array $data
     * @return \stdClass
     */
    private function situation(array $data): \stdClass {
        return $this->generator->create_situation($data + ['userid' => $this->vaeiste->id, 'fichecode' => 'RNCP100']);
    }

    public function test_the_sentences_speak_to_the_vaeiste_and_of_the_vaeiste_as_before(): void {
        $id = (int) $this->vaeiste->id;
        foreach ([$this->vaeiste, $this->manager] as $reader) {
            $this->setUser($reader);
            $old = self::before_situation_disclaimer($id);
            $this->assertSame(self::canon($old), self::canon($this->render(new situation_disclaimer($id))));
            $this->assertSame($reader === $this->vaeiste, str_contains($old, s(get_string('selfposvisible', 'tool_vae'))));
        }
    }

    public function test_the_facts_draw_the_dom_of_before_with_none_one_two_and_three_lines(): void {
        $tree = selfpos_tree::for_record('RNCP100');
        $rows = [
            'everything' => $this->situation(['title' => 'Tout', 'periodstart' => 1756684800, 'periodend' => 1782172800,
                'setting' => 'Unite & "vie"', 'tags' => 'C01,C02']),
            'the start, a setting "0"' => $this->situation(['title' => 'Debut', 'periodstart' => 1756684800,
                'setting' => '0']),
            'the end alone' => $this->situation(['title' => 'Fin', 'periodend' => 1782172800]),
            'nothing' => $this->situation(['title' => 'Rien']),
        ];
        // A tag on a rank the record no longer holds is said by its rank alone (as an import that drops a node leaves it).
        $gone = clone $rows['everything'];
        $gone->tags = ['C02', 'Z99'];
        $rows['a rank gone'] = $gone;
        $breaks = ['everything' => 2, 'the start, a setting "0"' => 1, 'the end alone' => 0, 'nothing' => 0, 'a rank gone' => 2];
        foreach ($rows as $name => $row) {
            $old = self::before_situation_details($row);
            $new = $this->render(new situation_facts($row, $tree, [], 'tool-vae-situation-details mb-3'));
            $this->assertSame(self::canon($old), self::canon($new), $name);
            $this->assertSame($breaks[$name], substr_count($old, '<br>'), $name . ': premise, a break between two lines.');
            $this->assertSame(substr_count($old, '<br>'), substr_count($new, '<br'), $name . ': never after the last.');
        }
        $this->assertStringContainsString('Z99</div>', self::before_situation_details($gone), 'Premise: the rank alone.');
        $label = 'C02 - Soins & "aide"';
        $this->assertNotSame(s($label), s(s($label)), 'Premise: a second escaping would show.');
        $new = $this->render(new situation_facts($rows['everything'], $tree, [], 'small'));
        $this->assertStringContainsString(s($label), $new);
        $this->assertStringContainsString('Unite &amp; &quot;vie&quot;', $new);
        $this->assertStringNotContainsString('&amp;amp;', $new);
        $this->assertStringNotContainsString('&amp;quot;', $new);

        // The lines a list adds after the facts: "nothing" gets two lines, one break.
        $more = ['2 pieces', 'Not written yet'];
        $new = $this->render(new situation_facts($rows['nothing'], $tree, $more, 'small'));
        $this->assertSame('<div class="small">2 pieces<br>Not written yet</div>', $new);
    }

    /**
     * The function situation.php gave the screen, before lot 7, to draw the buttons of the vaeiste (its code, $OUTPUT
     * being the renderer of the core for the web).
     *
     * @param \moodle_url $url
     * @return callable
     */
    private static function actions(\moodle_url $url): callable {
        $output = self::core();

        return function (\stdClass $link) use ($output, $url): string {
            $out = $output->single_button(
                new \moodle_url($url, ['action' => 'detach', 'piece' => (int) $link->pieceid]),
                get_string('spiecedetach', 'tool_vae'),
                'post'
            );
            if ($link->piece->timeremoved === null) {
                $out .= $output->single_button(
                    new \moodle_url($url, ['action' => 'remove', 'piece' => (int) $link->pieceid]),
                    get_string('pieceremove', 'tool_vae'),
                    'post'
                );
            }

            return $out;
        };
    }

    public function test_each_kind_of_piece_draws_the_dom_of_before_with_and_without_the_buttons(): void {
        $first = $this->situation(['title' => 'Avec pieces']);
        $other = $this->situation(['title' => 'Autre']);
        $empty = $this->situation(['title' => 'Sans piece']);
        $pieces = new spiece_manager();
        $pieces->add_link(
            (int) $first->id,
            'writing',
            'Protocole & "toilette"',
            'https://example.org/a?x=1&y=2',
            '<script>x</script>'
        );
        $path = make_request_directory() . '/upload';
        file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
        $pieces->add_file((int) $first->id, 'contract', '0', $path, '');
        $removed = $pieces->add_link((int) $other->id, 'other', '', 'https://example.org/b', 'La fin');
        $pieces->attach((int) $first->id, (int) $removed->id, '');
        $pieces->remove((int) $removed->id);
        // A FILE removed everywhere: it keeps its line, without its size (only a file that stands says its size).
        $gonepath = make_request_directory() . '/gone';
        file_put_contents($gonepath, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
        $gone = $pieces->add_file((int) $other->id, 'contract', 'Retire', $gonepath, '');
        $pieces->attach((int) $first->id, (int) $gone->id, '');
        $pieces->remove((int) $gone->id);
        $context = \context_user::instance((int) $this->vaeiste->id);
        $url = situation_screen::url((int) $this->vaeiste->id, 'RNCP100', (int) $first->slot);
        $links = $pieces->for_situation((int) $first->id);
        $this->assertCount(4, $links, 'Premise: a link, a file, a link removed, a file removed.');

        foreach (['vaeiste' => $this->vaeiste, 'manager' => $this->manager, 'stranger' => $this->stranger] as $who => $reader) {
            $this->setUser($reader);
            foreach ([true, false] as $buttons) {
                $actions = $buttons ? self::actions($url) : null;
                $old = $this->before(fn() => self::before_situation_pieces($links, $context, $actions));
                $new = $this->render(new situation_pieces($links, $context, $buttons ? $url : null));
                $this->assertSame(self::canon($old), self::canon($new), $who . ($buttons ? ' buttons' : ''));
                $this->assertSame(
                    $buttons ? 6 : 0,
                    substr_count($old, 'value="detach"') + substr_count($old, 'value="remove"'),
                    'Premise: detach on the four, remove on the two that stand.'
                );
                $this->assertSame(1, substr_count($old, 'PDF, '), 'Premise: the size of the file that stands only.');
                $mayopen = $reader !== $this->stranger;
                $this->assertSame($mayopen, str_contains($old, 'noopener noreferrer'), $who . ': the link opened.');
                $this->assertSame($mayopen, str_contains($old, 'pluginfile.php'), $who . ': the file opened.');
                $this->assertSame($mayopen, str_contains($old, 'Protocole &amp; &quot;toilette&quot;'), $who . ': the title.');
                $this->assertStringContainsString(s(get_string('spieceremoved', 'tool_vae')), $old);
                $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $new, 'The part, escaped.');
                $this->assertStringNotContainsString('<script', $new);
                $this->assertStringNotContainsString('&amp;amp;', $new);
                $this->assertStringContainsString('PDF, ', $old, 'Premise: the size of a file.');
            }
        }
        $this->setUser($this->vaeiste);
        $this->assertSame(
            self::canon(self::before_situation_pieces([], $context)),
            self::canon($this->render(new situation_pieces($pieces->for_situation((int) $empty->id), $context, $url)))
        );
        $this->assertStringContainsString(s(get_string('piecenone', 'tool_vae')), self::before_situation_pieces([], $context));
    }

    /**
     * The form of a file, new each time (a form is drawn once).
     *
     * @param \moodle_url $url
     * @return \tool_vae\form\spiece_file_form
     */
    private static function fileform(\moodle_url $url): \tool_vae\form\spiece_file_form {
        return new \tool_vae\form\spiece_file_form($url->out(false), ['maxbytes' => 1048576], 'post', '', [
            'class' => 'mb-4 tool-vae-spiece-file',
        ]);
    }

    public function test_the_forms_draw_the_dom_of_before_with_and_without_a_piece_to_attach(): void {
        $first = $this->situation(['title' => 'Une']);
        $second = $this->situation(['title' => 'Deux']);
        $pieces = new spiece_manager();
        $pieces->add_link((int) $second->id, 'other', 'A & "B"', 'https://example.org/a', '');
        $pieces->add_link((int) $second->id, 'writing', '', 'https://example.org/b', '');
        $pieces->add_link((int) $second->id, 'payslip', '0', 'https://example.org/c', '');
        $url = situation_screen::url((int) $this->vaeiste->id, 'RNCP100', (int) $first->slot);
        $attachable = $pieces->for_candidate((int) $this->vaeiste->id, 'RNCP100');
        $this->assertCount(3, $attachable, 'Premise: three pieces to attach.');

        foreach (['three pieces' => $attachable, 'none' => []] as $name => $choices) {
            $old = self::before_situation_piece_forms($url, $choices, self::fileform($url));
            $new = $this->render(new situation_piece_forms($url, $choices, self::fileform($url)));
            $this->assertSame(self::canon(self::drafts($old)), self::canon(self::drafts($new)), $name);
            $this->assertSame($choices !== [], str_contains($old, 'tool-vae-spiece-attach'), $name . ': premise.');
        }
        $new = $this->render(new situation_piece_forms($url, $attachable, self::fileform($url)));
        $this->assertMatchesRegularExpression('~<option value="\d+">[^<]*A &amp; &quot;B&quot;~', $new, 'Escaped once.');
        $this->assertStringNotContainsString('&amp;amp;', $new);
        $this->assertStringContainsString('<option selected="selected" value="">', $new, 'The first nature chosen.');
        $this->assertSame(1, substr_count($new, 'id="tool_vae_anonymise_spiece"'));
        $this->assertSame(1, substr_count($new, 'tool-vae-spiece-file'), 'The form of a file, once.');
    }

    public function test_the_templates_place_raw_only_the_keys_named_html(): void {
        global $CFG;

        foreach (['situation_disclaimer', 'situation_facts', 'situation_pieces', 'situation_piece_forms'] as $name) {
            $text = file_get_contents($CFG->dirroot . '/admin/tool/vae/templates/' . $name . '.mustache');
            preg_match_all('/\{\{\{(\w+)\}\}\}/', $text, $raw);
            foreach ($raw[1] as $key) {
                $this->assertStringEndsWith('html', $key, $name);
            }
        }
    }
}
