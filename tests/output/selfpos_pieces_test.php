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

use tool_vae\local\competency_gateway;
use tool_vae\local\selfpos_manager;
use tool_vae\local\selfpos_screen;
use tool_vae\local\selfpos_tree;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/positioning_before.php');

/**
 * The records, the tree of a record and a hidden record of the self-positioning, drawn by pieces (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the pieces selfpos_records, selfpos_record_tree, selfpos_hidden and selfpos_disclaimer draw the same
 * DOM as selfpos_screen::records(), tree(), hidden() and disclaimer() drew before their migration (their code kept in the
 * trait positioning_before): for the vaeiste and for a reader; with no record, with records and hidden ones; for a tree with
 * nodes at four levels, a node that cannot be positioned on, a level that stands, one withdrawn, none, a step renamed with an
 * apostrophe and accents ("J'ai déjà pratiqué"); for a hidden record with nothing typed, blanks, a text; each state with a
 * premise. That the text kept is placed EXACTLY as before in its textarea; that selfpos_screen::level_words() still gives
 * the words escaped once and level_text() the same words plain; that the templates place HTML only under the keys named and
 * render their example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(selfpos_records::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(selfpos_record_tree::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(selfpos_hidden::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(selfpos_disclaimer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(selfpos_screen::class)]
final class selfpos_pieces_test extends \advanced_testcase {
    use dom_canon;
    use positioning_before;

    /** @var \stdClass The vaeiste. */
    private $vaeiste;

    /** @var \stdClass Follows the vaeiste. */
    private $manager;

    /** @var \tool_vae_generator */
    private $generator;

    /**
     * RNCP400 (four levels: D1 > C11 > H111, H112; C12 under D1; D2 alone) and RNCP100; the people.
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/selfposition.php'));
        $PAGE->set_context(\context_system::instance());

        $datagenerator = $this->getDataGenerator();
        $this->generator = $datagenerator->get_plugin_generator('tool_vae');
        $this->generator->create_source(['fichecode' => 'RNCP400', 'ficherang' => 'D1', 'shortname' => 'Domaine & "un"']);
        foreach ([['C11', 'D1'], ['H111', 'C11'], ['H112', 'C11'], ['C12', 'D1'], ['D2', '']] as [$rank, $parent]) {
            $this->generator->create_competency([
                'fichecode' => 'RNCP400', 'ficherang' => $rank, 'shortname' => 'Noeud ' . $rank, 'parent' => $parent,
            ]);
        }
        $this->generator->create_source(['fichecode' => 'RNCP100', 'ficherang' => 'C01', 'shortname' => 'Une competence']);

        $system = \context_system::instance()->id;
        $this->vaeiste = $datagenerator->create_user();
        $this->manager = $datagenerator->create_user();
        $own = $datagenerator->create_role();
        foreach (['declare', 'viewown', 'positionself'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $own, $system, true);
        }
        set_role_contextlevels($own, [CONTEXT_USER]);
        role_assign($own, $this->vaeiste->id, \context_user::instance($this->vaeiste->id)->id);
        $follow = $datagenerator->create_role();
        assign_capability('tool/vae:viewassigned', CAP_ALLOW, $follow, $system, true);
        set_role_contextlevels($follow, [CONTEXT_USER]);
        role_assign($follow, $this->manager->id, \context_user::instance($this->vaeiste->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
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
     * The vaeiste writes one line.
     *
     * @param string $rank
     * @param int $level
     */
    private function position(string $rank, int $level): void {
        $this->generator->create_selfpos([
            'userid' => $this->vaeiste->id, 'fichecode' => 'RNCP400', 'ficherang' => $rank, 'level' => $level,
        ]);
    }

    public function test_the_pieces_are_named_templates(): void {
        global $PAGE;
        $id = (int) $this->vaeiste->id;
        $tree = selfpos_tree::for_record('RNCP400');
        $pieces = [
            'tool_vae/selfpos_records' => new selfpos_records($id, [], []),
            'tool_vae/selfpos_record_tree' => new selfpos_record_tree($id, 'RNCP400', $tree, [], true),
            'tool_vae/selfpos_hidden' => new selfpos_hidden('', ''),
            'tool_vae/selfpos_disclaimer' => new selfpos_disclaimer($id),
        ];
        foreach ($pieces as $template => $piece) {
            $this->assertInstanceOf(\core\output\renderable::class, $piece);
            $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
            $this->assertSame($template, $piece->get_template_name($PAGE->get_renderer('tool_vae')));
        }
    }

    public function test_the_records_draw_the_dom_of_before(): void {
        global $DB;
        $id = (int) $this->vaeiste->id;
        $sources = $DB->get_records('tool_vae_source', null, 'fichecode ASC', '*');
        $offered = [];
        foreach ($sources as $source) {
            $offered[(string) $source->fichecode] = $source;
        }
        $hidden = [
            'RNCP500' => get_string('errorframeworkhidden', 'tool_vae', s('Aide & soins (RNCP500)')),
            'RNCP600' => get_string('errorframeworkhidden', 'tool_vae', s('Autre "fiche" (RNCP600)')),
        ];
        $cases = [
            'nothing offered, nothing hidden' => [[], []],
            'records offered' => [$offered, []],
            'records offered and hidden' => [$offered, $hidden],
            'every record hidden' => [[], $hidden],
        ];
        foreach (['the vaeiste' => $this->vaeiste, 'a reader' => $this->manager] as $who => $reader) {
            $this->setUser($reader);
            foreach ($cases as $name => [$sources, $messages]) {
                $before = self::before_selfpos_records($id, $sources, $messages);
                $after = $this->render(new selfpos_records($id, $sources, $messages));
                $this->assertSame(self::canon($before), self::canon($after), "$who: $name");
                // Premises.
                $this->assertSame(count($messages), substr_count($before, 'alert alert-info'), "$who: $name");
                $this->assertSame($sources === [], str_contains($before, s(get_string('selfposnorecord', 'tool_vae'))));
                $this->assertSame(count($sources), substr_count($before, '<li>'), "$who: $name");
            }
            $visible = $reader === $this->vaeiste ? 'selfposvisible' : 'selfposvisibleread';
            $this->assertStringContainsString(s(get_string($visible, 'tool_vae')), $before, $who);
        }
        $this->assertStringContainsString('Aide &amp; soins (RNCP500)', $after, 'Escaped once, as before.');
        $this->assertStringNotContainsString('&amp;amp;', $after);
        $this->assertStringContainsString('Autre &quot;fiche&quot;', $after);
    }

    public function test_the_tree_draws_the_dom_of_before(): void {
        global $DB;
        // A step renamed by the administrator, with an apostrophe and accents (PARAM_TEXT keeps them).
        $label = "J'ai déjà pratiqué";
        $this->assertSame($label, clean_param($label, PARAM_TEXT));
        set_config('selfposlabel3', $label, 'tool_vae');
        $this->position('', 3);
        $this->position('H111', 2);
        $this->position('C12', 1);
        $this->position('C12', 0);
        // A node no other screen can name.
        $this->setAdminUser();
        $gateway = new competency_gateway();
        $gateway->create_competency((int) $gateway->find_framework('RNCP400'), 'AS-1-1', 'Un code & "tirets"');
        $id = (int) $this->vaeiste->id;

        foreach ([[$this->vaeiste, true], [$this->vaeiste, false], [$this->manager, false]] as [$reader, $canwrite]) {
            $this->setUser($reader);
            $current = (new selfpos_manager())->current($id, 'RNCP400');
            $tree = selfpos_tree::for_record('RNCP400');
            $before = self::before_selfpos_tree($id, 'RNCP400', $tree, $current, $canwrite);
            $after = $this->render(new selfpos_record_tree($id, 'RNCP400', $tree, $current, $canwrite));
            $name = fullname($reader) . ($canwrite ? ', who writes' : ', who reads');
            $this->assertSame(self::canon($before), self::canon($after), $name);

            // Premises.
            $this->assertStringContainsString(s(get_string('selfposcurrent', 'tool_vae', (object) [
                'label' => $label, 'when' => '',
            ])), $before, 'The step renamed, escaped once: ' . $name);
            $this->assertStringContainsString('J&#039;ai déjà pratiqué', $before);
            $this->assertStringNotContainsString('&amp;#039;', $before);
            $none = s(get_string('selfposnone', 'tool_vae'));
            $this->assertSame(5, substr_count($before, $none), 'D1, C11, H112, D2; C12 withdrawn.');
            $this->assertSame(1, substr_count($before, s(get_string('selfposexplainnotpositionable', 'tool_vae'))));
            $this->assertStringContainsString('margin-left: 0rem', $before);
            $this->assertStringContainsString('margin-left: 1.5rem', $before);
            $this->assertStringContainsString('margin-left: 3rem', $before);
            $this->assertStringContainsString('margin-left: 4.5rem', $before);
            $this->assertSame($canwrite, str_contains($before, s(get_string('selfposintro', 'tool_vae'))), $name);
            $link = get_string($canwrite ? 'selfposnodelink' : 'selfposreadlink', 'tool_vae');
            $this->assertSame(7, substr_count($before, '>' . $link . '</a>'), 'The record and six nodes: ' . $name);
            $this->assertStringContainsString('Un code &amp; &quot;tirets&quot;', $after);
        }
        $this->assertSame(1, $DB->count_records('competency', ['idnumber' => 'AS-1-1']));
    }

    public function test_a_step_with_an_ampersand_and_quotes_is_escaped_once_in_the_tree_as_before(): void {
        // An apostrophe alone would not tell: s() gives back "&#039;" when it meets "&amp;#039;", so the words of a level
        // escaped twice read the same. An ampersand and a quote are escaped again by a second pass (PARAM_TEXT keeps both).
        $label = 'Pratiqué & "vu"';
        $this->assertSame($label, clean_param($label, PARAM_TEXT));
        $this->assertNotSame(s($label), s(s($label)), 'The premise: a second escaping changes this label.');
        $this->assertSame(s("J'ai"), s(s("J'ai")), 'While an apostrophe alone reads the same when escaped twice.');
        set_config('selfposlabel3', $label, 'tool_vae');
        $this->position('H111', 3);
        $id = (int) $this->vaeiste->id;

        foreach ([[$this->vaeiste, true], [$this->manager, false]] as [$reader, $canwrite]) {
            $this->setUser($reader);
            $current = (new selfpos_manager())->current($id, 'RNCP400');
            $tree = selfpos_tree::for_record('RNCP400');
            $before = self::before_selfpos_tree($id, 'RNCP400', $tree, $current, $canwrite);
            $after = $this->render(new selfpos_record_tree($id, 'RNCP400', $tree, $current, $canwrite));
            $name = fullname($reader);

            $this->assertSame(self::canon($before), self::canon($after), $name);
            $this->assertStringContainsString('Pratiqué &amp; &quot;vu&quot;', $before, 'The premise: the old code drew it.');
            $this->assertStringContainsString('Pratiqué &amp; &quot;vu&quot;', $after, $name);
            $this->assertStringNotContainsString('&amp;amp;', $after, $name);
            $this->assertStringNotContainsString('&amp;quot;', $after, $name);
        }
    }

    public function test_the_level_in_words_is_escaped_once_by_level_words_and_not_at_all_by_level_text(): void {
        $label = "J'ai déjà pratiqué & \"vu\"";
        $this->assertSame($label, clean_param($label, PARAM_TEXT));
        set_config('selfposlabel3', $label, 'tool_vae');
        $this->position('H111', 3);
        $this->setUser($this->vaeiste);
        $current = (new selfpos_manager())->current((int) $this->vaeiste->id, 'RNCP400');

        $text = selfpos_screen::level_text($current['H111']);

        $this->assertStringStartsWith($label . ', ', $text);
        $this->assertSame(s($text), selfpos_screen::level_words($current['H111']));
        $this->assertSame(s(self::before_selfpos_level_words($current['H111'])), s(selfpos_screen::level_words($current['H111'])));
        $this->assertSame(self::before_selfpos_level_words($current['H111']), selfpos_screen::level_words($current['H111']));
        $this->assertSame(self::before_selfpos_level_words(null), selfpos_screen::level_words(null));
        $this->assertSame(get_string('selfposnone', 'tool_vae'), selfpos_screen::level_text(null));
    }

    public function test_a_hidden_record_draws_the_dom_of_before_and_keeps_the_text_exactly(): void {
        $message = get_string('errorframeworkhidden', 'tool_vae', s('Aide & soins (RNCP400)'));
        $texts = [
            'nothing typed' => '',
            'blanks' => "   \n ",
            'a text' => "\nMon texte <b>long</b> & \"cite\"\nsur deux lignes.  ",
            'the figure 0' => '0',
            'a script' => '</textarea><script>alert(1)</script>',
        ];
        foreach ($texts as $name => $text) {
            $before = self::before_selfpos_hidden($message, $text);
            $after = $this->render(new selfpos_hidden($message, $text));
            $this->assertSame(self::canon($before), self::canon($after), $name);
            // Premises: the colour of the notice and the field follow two different tests of the old code.
            $this->assertSame($text === '', str_contains($before, 'alert alert-info'), $name);
            $this->assertSame(trim($text) !== '', str_contains($before, '<textarea'), $name);
            if (trim($text) !== '') {
                $this->assertStringContainsString('>' . s($text) . '</textarea>', $after, $name . ': exactly as before.');
                $this->assertStringContainsString('>' . s($text) . '</textarea>', $before, $name);
            }
            $this->assertStringContainsString('Aide &amp; soins (RNCP400)', $after);
            $this->assertStringNotContainsString('<script', $after);
        }
    }

    public function test_the_disclaimer_speaks_to_the_vaeiste_and_of_the_vaeiste_as_before(): void {
        foreach ([$this->vaeiste, $this->manager] as $reader) {
            $this->setUser($reader);
            $before = self::before_selfpos_disclaimer((int) $this->vaeiste->id);
            $after = $this->render(new selfpos_disclaimer((int) $this->vaeiste->id));
            $this->assertSame(self::canon($before), self::canon($after));
            $this->assertSame(
                $reader === $this->vaeiste,
                str_contains($before, s(get_string('selfposnotvalidation', 'tool_vae'))),
                'Premise: the two forms are played.'
            );
        }
    }

    public function test_the_templates_place_html_only_under_the_keys_named_and_render_their_example(): void {
        $templates = [
            'selfpos_records' => [['disclaimerhtml', 'messagehtml'], 'alert alert-info'],
            'selfpos_record_tree' => [['disclaimerhtml'], 'tool-vae-selfpos-node'],
            'selfpos_hidden' => [['messagehtml'], '<textarea'],
            'selfpos_disclaimer' => [[], 'tool-vae-selfpos-disclaimer'],
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
