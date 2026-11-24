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
use tool_vae\local\selfpos_manager;
use tool_vae\local\selfpos_regard;
use tool_vae\local\selfpos_tree;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/situation_before.php');

/**
 * The page of the self-positioning (selfposition.php) draws, under its title, the DOM it drew before lot 7 (MMR-520 ticket 4,
 * wave 4a) in the three states the class draws: the competencies switched off, the tree of a record between its code and
 * the way back (the vaeiste, a reader, a code "0"), one node (no error, the error of each send, with and without its
 * annotations). The old code of the page under its heading is kept in before_...(); the annotations by the trait
 * situation_before.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(selfposition_page::class)]
final class selfposition_page_test extends \advanced_testcase {
    use dom_canon;
    use situation_before;

    /** @var \stdClass The vaeiste. */
    private $vaeiste;

    /** @var \stdClass The tree of RNCP100. */
    private $tree;

    /**
     * RNCP100 (BC01 holding C01 and C02); the vaeiste, who positions themselves.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $datagenerator = $this->getDataGenerator();
        $generator = $datagenerator->get_plugin_generator('tool_vae');
        $generator->create_source(['fichecode' => 'RNCP100', 'ficherang' => 'BC01', 'shortname' => 'Bloc 1']);
        foreach (['C01', 'C02'] as $rank) {
            $generator->create_competency([
                'fichecode' => 'RNCP100', 'ficherang' => $rank, 'shortname' => 'Competence ' . $rank, 'bloc' => 'BC01',
            ]);
        }
        $this->vaeiste = $datagenerator->create_user(['firstname' => 'Camille', 'lastname' => 'Vaeiste']);
        $own = $datagenerator->create_role();
        foreach (['declare', 'viewown', 'positionself'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $own, \context_system::instance()->id, true);
        }
        set_role_contextlevels($own, [CONTEXT_USER]);
        role_assign($own, $this->vaeiste->id, \context_user::instance($this->vaeiste->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->vaeiste);
        $this->tree = selfpos_tree::for_record('RNCP100');
    }

    /**
     * A page drawn by the renderer of the plugin for the web.
     *
     * @param \core\output\renderable $page
     * @return string
     */
    private function render(\core\output\renderable $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * What selfposition.php wrote when the competencies are switched off, under its heading, before lot 7 ($OUTPUT is
     * self::core()).
     *
     * @return string
     */
    private function before_disabled(): string {
        return self::core()->notification(
            get_string('errorcompetenciesdisabled', 'tool_vae'),
            \core\output\notification::NOTIFY_ERROR
        );
    }

    /**
     * What selfposition.php wrote for the tree of a record under its heading, before lot 7 (echo becomes $out .=; what the
     * page read is given).
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param bool $own
     * @param selfpos_record_tree $piece
     * @return string
     */
    private function before_tree(int $candidateid, string $fichecode, bool $own, selfpos_record_tree $piece): string {
        global $PAGE;

        $renderer = $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL);
        $out = '';
        $out .= \html_writer::tag('p', \html_writer::tag('strong', s($fichecode)));
        $out .= $renderer->render($piece);
        // The vaeiste goes back to their pathway; a reader goes back to the dossier they came from.
        $out .= \html_writer::tag('p', $own
            ? \html_writer::link(
                new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => 'mine']),
                get_string('selfposbackpathway', 'tool_vae')
            )
            : \html_writer::link(
                new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]),
                get_string('selfposbackdossier', 'tool_vae')
            ));

        return $out;
    }

    /**
     * What selfposition.php wrote for one node under its heading, before lot 7 (echo becomes $out .=, $OUTPUT is
     * self::core(), note_screen::block() is the old code kept in the trait; the arguments of the block are given).
     *
     * @param string|null $error
     * @param string|null $noteerror
     * @param selfpos_node $piece
     * @param array|null $block The arguments of note_screen::block(), or null for a node that is not positionable.
     * @return string
     */
    private function before_node(?string $error, ?string $noteerror, selfpos_node $piece, ?array $block): string {
        global $PAGE, $OUTPUT;

        $renderer = $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL);
        $out = '';
        if ($error !== null) {
            $out .= self::core()->notification(s($error), \core\output\notification::NOTIFY_ERROR);
        }
        if ($noteerror !== null) {
            $out .= self::core()->notification(s($noteerror), \core\output\notification::NOTIFY_ERROR);
        }
        $out .= $renderer->render($piece);
        if ($block !== null) {
            $kept = $OUTPUT;
            $OUTPUT = self::core();
            try {
                $out .= self::before_note_block(...$block);
            } finally {
                $OUTPUT = $kept;
            }
        }

        return $out;
    }

    /**
     * The node C01 of the vaeiste, as the page builds it.
     *
     * @return selfpos_node
     */
    private function node(): selfpos_node {
        $id = (int) $this->vaeiste->id;
        $node = selfpos_tree::node($this->tree, 'C01');

        return new selfpos_node(
            $id,
            'RNCP100',
            $node,
            (new selfpos_manager())->lines($id, 'RNCP100', 'C01'),
            selfpos_regard::for_node($id, 'RNCP100', $node),
            [],
            true,
            ''
        );
    }

    public function test_the_competencies_switched_off_draw_the_dom_of_before(): void {
        $old = $this->before_disabled();
        $this->assertSame(self::canon($old), self::canon($this->render(selfposition_page::disabled())));
        $this->assertStringContainsString('alert-danger', $old, 'Premise: an error.');
    }

    public function test_the_tree_draws_the_dom_of_before_for_the_vaeiste_and_a_reader(): void {
        $id = (int) $this->vaeiste->id;
        foreach ([['RNCP100', true], ['RNCP100', false], ['0', true]] as [$code, $own]) {
            $old = $this->before_tree($id, $code, $own, new selfpos_record_tree($id, 'RNCP100', $this->tree, [], true));
            $new = $this->render(selfposition_page::tree(
                $id,
                $code,
                $own,
                new selfpos_record_tree($id, 'RNCP100', $this->tree, [], true)
            ));
            $this->assertSame(self::canon($old), self::canon($new), $code . ($own ? ' own' : ' read'));
            $this->assertSame($own, str_contains($old, 'organisation.php?tab=mine'), 'Premise: the way back.');
            $this->assertSame(!$own, str_contains($old, 'dossier.php?userid=' . $id), 'Premise: the dossier.');
            $this->assertStringContainsString('<strong>' . $code . '</strong>', $old, 'Premise: the code, even "0".');
        }
    }

    public function test_a_node_draws_the_dom_of_before_with_each_error_and_its_annotations_or_none(): void {
        $id = (int) $this->vaeiste->id;
        $rows = [(object) [
            'id' => '7', 'state' => note_manager::ACTIVE, 'textformat' => (string) FORMAT_PLAIN, 'text' => 'Sur le noeud.',
            'authorid' => null, 'authoruuid' => null, 'importedat' => null, 'timecreated' => '1763700000', 'standing' => true,
        ]];
        $block = [$id, note_manager::TARGET_SELFPOS, 'RNCP100', 0, 'C01', $rows, true, '<form id="note"></form>'];
        $states = [
            'no error, annotations' => [null, null, $block],
            'error of the send' => ['<script>alert(1)</script> & "vu"', null, $block],
            'error of the annotation' => [null, 'Trop long & "vu".', $block],
            'both, no annotation (a node no screen names)' => ['Un.', 'Deux.', null],
        ];
        foreach ($states as $name => [$error, $noteerror, $args]) {
            $old = $this->before_node($error, $noteerror, $this->node(), $args);
            $new = $this->render(selfposition_page::node(
                $error,
                $noteerror,
                $this->node(),
                $args === null ? null : new notes_block(...$args)
            ));
            $this->assertSame(self::canon($old), self::canon($new), $name);
            $this->assertSame($args !== null, str_contains($old, 'tool-vae-notes'), $name . ': premise.');
            $this->assertSame(($error !== null) + ($noteerror !== null), substr_count($old, 'alert-danger'), $name);
        }
        $new = $this->render(selfposition_page::node('<script>alert(1)</script> & "vu"', null, $this->node(), null));
        $this->assertStringNotContainsString('<script', $new);
        $this->assertStringNotContainsString('&amp;amp;', $new);
    }
}
