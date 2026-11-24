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
use tool_vae\local\validation_catalog;
use tool_vae\local\whereami_panel;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/whereami_before.php');

/**
 * The pieces of the panel "where am I" (lot 7, MMR-520 ticket 4, wave 3b): whereami_tree (was whereami_panel::render()) and
 * tagged_situations (was whereami_panel::tagged_on_bloc()) draw the DOM the old screen drew, kept in the trait
 * whereami_before, for each reader, each option and each state of the data; nothing when the old code drew nothing.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(whereami_tree::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(tagged_situations::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(whereami_panel::class)]
final class whereami_pieces_test extends \advanced_testcase {
    use dom_canon;
    use whereami_before;

    /** @var \stdClass Declares, positions themselves and writes their preparation. */
    private $vaeiste;

    /** @var \stdClass Another vaeiste, who may not view the dossier of the first. */
    private $othervaeiste;

    /** @var \stdClass Follows the vaeiste. */
    private $manager;

    /** @var \tool_vae_generator */
    private $generator;

    /**
     * RNCP100 (BC01 holding C01 and C02, BC02 holding C03) and RNCP400 (four levels: D1 holding C11, which holds H111 and
     * H112, and C12; D2 alone); the people; the preparation open.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        validation_catalog::reset();
        set_config('validationopen', 1, 'tool_vae');

        $datagenerator = $this->getDataGenerator();
        $this->generator = $datagenerator->get_plugin_generator('tool_vae');
        $this->generator->create_source(['fichecode' => 'RNCP100', 'ficherang' => 'BC01', 'shortname' => 'Bloc 1']);
        $this->generator->create_competency(['fichecode' => 'RNCP100', 'ficherang' => 'BC02', 'shortname' => 'Bloc 2']);
        foreach ([['C01', 'BC01'], ['C02', 'BC01'], ['C03', 'BC02']] as [$rank, $bloc]) {
            $this->generator->create_competency([
                'fichecode' => 'RNCP100', 'ficherang' => $rank, 'shortname' => 'Competence ' . $rank, 'bloc' => $bloc,
            ]);
        }
        $this->generator->create_source(['fichecode' => 'RNCP400', 'ficherang' => 'D1', 'shortname' => 'Domaine 1']);
        foreach ([['C11', 'D1'], ['H111', 'C11'], ['H112', 'C11'], ['C12', 'D1'], ['D2', '']] as [$rank, $parent]) {
            $this->generator->create_competency([
                'fichecode' => 'RNCP400', 'ficherang' => $rank, 'shortname' => 'Noeud ' . $rank, 'parent' => $parent,
            ]);
        }

        $system = \context_system::instance()->id;
        $this->vaeiste = $datagenerator->create_user(['firstname' => 'Camille', 'lastname' => 'Vaeiste']);
        $this->othervaeiste = $datagenerator->create_user(['firstname' => 'Sacha', 'lastname' => 'Autre']);
        $this->manager = $datagenerator->create_user(['firstname' => 'Nadia', 'lastname' => 'Benali']);
        $own = $datagenerator->create_role();
        foreach (['declare', 'viewown', 'positionself', 'draftown'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $own, $system, true);
        }
        set_role_contextlevels($own, [CONTEXT_USER]);
        foreach ([$this->vaeiste, $this->othervaeiste] as $person) {
            role_assign($own, $person->id, \context_user::instance($person->id)->id);
        }
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
     * @return string
     */
    private function render(\core\output\renderable $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($piece);
    }

    /**
     * The panel of the vaeiste drawn by the old code and by the piece, compared, as the reader reads it.
     *
     * @param \stdClass $reader
     * @param string $fichecode
     * @param array $options
     * @param string $name
     * @return string The old HTML, for the premises.
     */
    private function same_tree(\stdClass $reader, string $fichecode, array $options, string $name): string {
        $this->setUser($reader);
        $id = (int) $this->vaeiste->id;
        $before = self::before_whereami_render($id, $fichecode, $options);
        $piece = new whereami_tree($id, $fichecode, $options);
        $after = $this->render($piece);
        $this->assertSame(self::canon($before), self::canon($after), $name);
        $this->assertSame($before !== '', $piece->drawn(), $name . ': drawn() says what the old code drew.');

        return $before;
    }

    /**
     * The situations of a bloc drawn by the old code and by the piece, compared, as the reader reads them.
     *
     * @param \stdClass $reader
     * @param string $fichecode
     * @param string $blocrank
     * @param string $name
     * @return string The old HTML, for the premises.
     */
    private function same_tagged(\stdClass $reader, string $fichecode, string $blocrank, string $name): string {
        $this->setUser($reader);
        $id = (int) $this->vaeiste->id;
        $before = self::before_whereami_tagged_on_bloc($id, $fichecode, $blocrank);
        $piece = new tagged_situations($id, $fichecode, $blocrank);
        $after = $this->render($piece);
        $this->assertSame(self::canon($before), self::canon($after), $name);
        $this->assertSame($before !== '', $piece->drawn(), $name . ': drawn() says what the old code drew.');

        return $before;
    }

    /**
     * A competency the core keeps but the record cannot position on (a rank of its own form), added under D1.
     *
     * @param string $rank
     */
    private function odd(string $rank): void {
        $this->setAdminUser();
        $gateway = new competency_gateway();
        $gateway->create_competency((int) $gateway->find_framework('RNCP400'), $rank, 'Un code a tirets ' . $rank);
    }

    public function test_the_pieces_are_named_templates(): void {
        global $PAGE;

        $renderer = $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL);
        $this->assertSame('tool_vae/whereami_tree', (new whereami_tree(1, 'RNCP400'))->get_template_name($renderer));
        $this->assertSame('tool_vae/tagged_situations', (new tagged_situations(1, 'RNCP400', ''))->get_template_name($renderer));
    }

    public function test_each_reader_and_each_option_draws_the_dom_of_before(): void {
        global $DB;

        $id = (int) $this->vaeiste->id;
        $this->odd('AS-1-1');
        $this->odd('AS-2-2');
        $this->generator->create_selfpos(['userid' => $id, 'fichecode' => 'RNCP400', 'ficherang' => 'H111', 'level' => 3]);
        $this->generator->create_selfpos(['userid' => $id, 'fichecode' => 'RNCP400', 'ficherang' => 'C12', 'level' => 2]);
        $this->generator->create_selfpos([
            'userid' => $id, 'fichecode' => 'RNCP400', 'ficherang' => 'C12', 'level' => selfpos_manager::WITHDRAWN,
        ]);
        $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP400', 'tags' => 'H111,D1', 'title' => 'Une']);
        $gone = $this->generator->create_situation([
            'userid' => $id, 'fichecode' => 'RNCP400', 'tags' => 'H112', 'title' => 'Deux',
        ]);
        foreach (['H111', 'D2', 'C12'] as $rank) {
            $this->generator->create_declaration([
                'userid' => $id, 'fichecode' => 'RNCP400', 'ficherang' => $rank, 'situation' => 'Sur ' . $rank . '.',
            ]);
        }
        // A declaration kept on a node the record holds but no longer lets be positioned on (as the import leaves it).
        $DB->set_field('tool_vae_evidence', 'ficherang', 'AS-1-1', ['candidateid' => $id, 'ficherang' => 'D2']);
        // H112 and C12 left the record at a later import for what was written on them: they keep their rank, apart.
        $DB->set_field('tool_vae_sitetag', 'ficherang', 'H199', ['situationid' => (int) $gone->id]);
        $DB->set_field('tool_vae_evidence', 'ficherang', 'C99', ['candidateid' => $id, 'ficherang' => 'C12']);

        $options = [
            'nothing asked' => [],
            'boxes' => ['boxes' => true],
            'boxes ticked' => ['boxes' => true, 'checked' => ['C11', 'H112', 'AS-1-1']],
            'link' => ['link' => true],
            'no title' => ['title' => ''],
            'a title' => ['title' => 'Un titre'],
            'the title 0' => ['title' => '0'],
            'link, no title' => ['link' => true, 'title' => ''],
        ];
        $readers = ['vaeiste' => $this->vaeiste, 'manager' => $this->manager, 'admin' => get_admin()];
        foreach ($readers as $who => $reader) {
            foreach ($options as $what => $option) {
                $before = $this->same_tree($reader, 'RNCP400', $option, "$who, $what");

                // Premises: each state is drawn by the old code.
                // The administrator reads no situation: H199, which carries one only, is not listed for them.
                $this->assertSame($who === 'admin' ? 10 : 11, substr_count($before, 'tool-vae-whereami-node'), "$who, $what");
                $this->assertStringContainsString('margin-left: 4.5rem', $before, 'Four levels.');
                $this->assertStringContainsString(s(get_string('whereamigone', 'tool_vae')), $before);
                $this->assertStringContainsString(s(get_string('selfposexplainnotpositionable', 'tool_vae')), $before);
                $boxes = !empty($option['boxes']);
                $this->assertSame($boxes ? 6 : 0, substr_count($before, 'type="checkbox"'), "$who, $what");
                $this->assertSame($boxes && !empty($option['checked']) ? 2 : 0, substr_count($before, 'checked="checked"'));
                $this->assertSame(!empty($option['link']), str_contains($before, 'selfposition.php'), "$who, $what");
                $this->assertSame(!array_key_exists('title', $option) || $option['title'] !== '', str_contains($before, '<h4'));
                if (($option['title'] ?? null) === '0') {
                    $this->assertStringContainsString('<h4 class="h5">0</h4>', $before);
                }
                // The level of the vaeiste: one standing, one withdrawn (told as none), the others none.
                $this->assertStringContainsString(s(get_string('selfposlabeldefault3', 'tool_vae')), $before);
                $this->assertSame(6, substr_count($before, s(get_string('selfposnone', 'tool_vae'))), "$who, $what");
                if ($who === 'admin') {
                    // The site administrator never reads the preparation: the declarations only, and no way to the list.
                    $this->assertStringNotContainsString('situations.php', $before);
                    $this->assertStringContainsString(s(get_string('whereamideclarationsonly', 'tool_vae', (object) [
                        'declarations' => 1,
                    ])), $before);
                } else {
                    $this->assertSame(5, substr_count($before, 'situations.php'), "$who, $what: H111, D1, AS-1-1, H199, C99.");
                }
            }
        }
        // The words of the link follow the reader: "position myself" for the vaeiste, "read" for the others.
        $this->setUser($this->vaeiste);
        $this->assertStringContainsString(s(get_string('selfposlink', 'tool_vae')), $this->render(
            new whereami_tree($id, 'RNCP400', ['link' => true])
        ));
        $this->setUser($this->manager);
        $this->assertStringContainsString(s(get_string('selfposreadall', 'tool_vae')), $this->render(
            new whereami_tree($id, 'RNCP400', ['link' => true])
        ));
        // The default title follows the reader too.
        $this->assertStringContainsString(s(get_string('whereamititlereader', 'tool_vae')), $this->render(
            new whereami_tree($id, 'RNCP400')
        ));
    }

    public function test_a_label_and_a_level_with_an_ampersand_and_quotes_are_escaped_once_as_before(): void {
        // An apostrophe alone would not tell: s() gives back "&#039;" when it meets "&amp;#039;". An ampersand and a quote
        // are escaped again by a second pass (PARAM_TEXT keeps both).
        $level = 'Pratiqué & "vu"';
        $label = 'Soins & "aide" < 3';
        $this->assertSame($level, clean_param($level, PARAM_TEXT));
        $this->assertNotSame(s($level), s(s($level)), 'The premise: a second escaping changes this level.');
        set_config('selfposlabel3', $level, 'tool_vae');
        $this->generator->create_competency([
            'fichecode' => 'RNCP400', 'ficherang' => 'X1', 'shortname' => $label, 'parent' => 'D1',
        ]);
        $id = (int) $this->vaeiste->id;
        $this->generator->create_selfpos(['userid' => $id, 'fichecode' => 'RNCP400', 'ficherang' => 'X1', 'level' => 3]);
        $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP400', 'tags' => 'X1']);

        foreach ([$this->vaeiste, $this->manager] as $reader) {
            $name = fullname($reader);
            $before = $this->same_tree($reader, 'RNCP400', ['boxes' => true, 'checked' => ['X1']], $name);
            $after = $this->render(new whereami_tree($id, 'RNCP400', ['boxes' => true, 'checked' => ['X1']]));
            $this->assertStringContainsString('Pratiqué &amp; &quot;vu&quot;', $before, 'The premise: the old code drew it.');
            $this->assertStringContainsString('Pratiqué &amp; &quot;vu&quot;', $after, $name);
            $this->assertStringContainsString('Soins &amp; ', $before, 'The premise: the label, given by format_string().');
            $this->assertStringContainsString(' &lt; 3', $after, $name);
            $this->assertStringNotContainsString('&amp;amp;', $after, $name);
            $this->assertStringNotContainsString('&amp;quot;', $after, $name);
            $this->assertStringNotContainsString('&amp;lt;', $after, $name);
        }
    }

    public function test_a_script_in_the_title_comes_out_escaped_as_before(): void {
        $before = $this->same_tree($this->vaeiste, 'RNCP100', ['title' => '<script>alert(1)</script>Titre'], 'script');
        $after = $this->render(new whereami_tree((int) $this->vaeiste->id, 'RNCP100', ['title' => '<script>alert(1)</script>']));
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;Titre', $before);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $after);
        $this->assertStringNotContainsString('<script', $after);
    }

    public function test_the_figure_0_is_drawn_as_before(): void {
        global $DB;

        $id = (int) $this->vaeiste->id;
        $this->generator->create_competency([
            'fichecode' => 'RNCP400', 'ficherang' => 'Z0', 'shortname' => '0', 'parent' => 'D1',
        ]);
        $this->generator->create_declaration([
            'userid' => $id, 'fichecode' => 'RNCP400', 'ficherang' => 'D2', 'situation' => 'Sur D2.',
        ]);
        // A rank "0" left the record: its rank is drawn alone, in bold.
        $DB->set_field('tool_vae_evidence', 'ficherang', '0', ['candidateid' => $id, 'ficherang' => 'D2']);

        $before = $this->same_tree($this->vaeiste, 'RNCP400', ['title' => '0'], 'the figure 0');
        $this->assertStringContainsString('<strong>0</strong>', $before, 'The premise: the label 0, and the rank 0 gone.');
        $this->assertSame(2, substr_count($before, '<strong>0</strong>'));
        $this->assertStringContainsString('data-rank="0"', $before);
        $this->assertStringContainsString('<h4 class="h5">0</h4>', $before);
    }

    public function test_nothing_is_drawn_when_the_reader_may_not_view_the_competencies_are_off_or_the_record_is_absent(): void {
        $id = (int) $this->vaeiste->id;
        // The premise: drawn for the vaeiste, on a record imported, the competencies on.
        $this->assertNotSame('', $this->same_tree($this->vaeiste, 'RNCP400', [], 'drawn'));

        $this->assertSame('', $this->same_tree($this->othervaeiste, 'RNCP400', ['link' => true], 'another vaeiste'));
        $this->assertSame('', $this->same_tree($this->vaeiste, 'RNCP999', ['link' => true], 'a record not imported'));
        set_config('enabled', 0, competency_gateway::SUBSYSTEM);
        $this->assertSame('', $this->same_tree($this->vaeiste, 'RNCP400', ['link' => true], 'competencies off'));
        $this->assertSame('', $this->render(new whereami_tree($id, 'RNCP400', ['link' => true])));
    }

    public function test_the_tagged_situations_draw_the_dom_of_before(): void {
        $id = (int) $this->vaeiste->id;
        $start = strtotime('2026-01-05 00:00:00 UTC');
        $end = strtotime('2026-03-20 00:00:00 UTC');
        $make = fn(string $tags, string $title, array $more = []) => $this->generator->create_situation([
            'userid' => $id, 'fichecode' => 'RNCP100', 'tags' => $tags, 'title' => $title,
        ] + $more);
        $make('BC01', 'Sur le bloc', ['periodstart' => $start, 'periodend' => $end]);
        $make('C02', 'Sur "une" competence & plus', ['periodstart' => $start]);
        $make('C01', '0', ['periodend' => $end]);
        // A title cannot hold a tag (PARAM_TEXT strips it): what HTML would read is enough to show the escaping.
        $make('C02', 'Moins < 3 & "plus"');
        $make('', 'Sans etiquette');

        $date = fn(int $time) => \tool_vae\local\display_date::format($time, get_string('strftimedate', 'langconfig'));
        foreach (['vaeiste' => $this->vaeiste, 'manager' => $this->manager] as $who => $reader) {
            $before = $this->same_tagged($reader, 'RNCP100', 'bc01', "$who, bc01");
            // Premises: four situations, each kind of period, the title 0, the script escaped.
            $this->assertSame(4, substr_count($before, '<li>'), $who);
            $this->assertStringContainsString(s($date($start) . ' - ' . $date($end)), $before);
            $this->assertStringContainsString('<span class="text-muted">' . s($date($start)) . '</span>', $before);
            $this->assertStringContainsString('<span class="text-muted">' . s($date($end)) . '</span>', $before);
            $this->assertStringContainsString('>0</a>', $before);
            $this->assertStringContainsString('Sur &quot;une&quot; competence &amp; plus', $before);
            $this->assertStringContainsString('Moins &lt; 3 &amp; &quot;plus&quot;', $before);
            $this->assertStringNotContainsString('Sans etiquette', $before);
            $after = $this->render(new tagged_situations($id, 'RNCP100', 'bc01'));
            $this->assertStringContainsString('Moins &lt; 3 &amp; &quot;plus&quot;', $after);
            $this->assertStringNotContainsString('&amp;amp;', $after);
            $this->assertStringNotContainsString('&amp;lt;', $after);

            // None on this bloc, or a bloc the record does not hold: the sentence that says none is.
            foreach (['bc02', 'bc99'] as $bloc) {
                $before = $this->same_tagged($reader, 'RNCP100', $bloc, "$who, $bloc");
                $this->assertStringContainsString(s(get_string('validlinksituationsnone', 'tool_vae')), $before, $bloc);
            }
            // The whole record: every node counts.
            $before = $this->same_tagged($reader, 'RNCP100', '', "$who, the whole record");
            $this->assertSame(4, substr_count($before, '<li>'), $who);
        }
    }

    public function test_a_situation_tagged_on_a_node_that_cannot_be_positioned_on_is_not_listed_as_before(): void {
        global $DB;

        $id = (int) $this->vaeiste->id;
        $this->odd('AS-1-1');
        $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP400', 'tags' => 'H111', 'title' => 'Sur H111']);
        $kept = $this->generator->create_situation([
            'userid' => $id, 'fichecode' => 'RNCP400', 'tags' => 'H112', 'title' => 'Sur un noeud ecarte',
        ]);
        // The tag stays on a node the record still holds but no longer lets be positioned on (as a later import leaves it).
        $DB->set_field('tool_vae_sitetag', 'ficherang', 'AS-1-1', ['situationid' => (int) $kept->id]);

        $before = $this->same_tagged($this->vaeiste, 'RNCP400', '', 'the whole record');
        // Premises: the node is in the tree, out of the ranks the part gathers; only the other situation is listed.
        $this->assertNotNull(\tool_vae\local\selfpos_tree::node(\tool_vae\local\selfpos_tree::for_record('RNCP400'), 'AS-1-1'));
        $this->assertStringContainsString('Sur H111', $before);
        $this->assertStringNotContainsString('Sur un noeud ecarte', $before);
        $this->assertSame(1, substr_count($before, '<li>'));
    }

    public function test_no_situation_is_drawn_when_the_reader_may_not_read_them_or_nothing_can_be_read(): void {
        $this->generator->create_situation(['userid' => (int) $this->vaeiste->id, 'fichecode' => 'RNCP100', 'tags' => 'C01']);
        // The premise: drawn for the vaeiste.
        $this->assertNotSame('', $this->same_tagged($this->vaeiste, 'RNCP100', 'bc01', 'drawn'));

        // The site administrator never reads the preparation (D-1b-G).
        $this->assertSame('', $this->same_tagged(get_admin(), 'RNCP100', 'bc01', 'the administrator'));
        $this->assertSame('', $this->same_tagged($this->vaeiste, 'RNCP999', '', 'a record not imported'));
        set_config('enabled', 0, competency_gateway::SUBSYSTEM);
        $this->assertSame('', $this->same_tagged($this->vaeiste, 'RNCP100', 'bc01', 'competencies off'));
    }

    public function test_the_templates_place_no_text_unescaped(): void {
        global $CFG;

        $tree = file_get_contents($CFG->dirroot . '/admin/tool/vae/templates/whereami_tree.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $tree, $keys);
        $this->assertSame(['labelhtml'], array_values(array_unique($keys[1])));
        $tagged = file_get_contents($CFG->dirroot . '/admin/tool/vae/templates/tagged_situations.mustache');
        $this->assertStringNotContainsString('{{{', $tagged);
    }
}
