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
use tool_vae\local\situation_manager;
use tool_vae\local\situation_screen;
use tool_vae\local\spiece_manager;
use tool_vae\local\validation_catalog;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/situation_before.php');
require_once(__DIR__ . '/whereami_before.php');

/**
 * The page of the work situations (situations.php) draws, under its title, the DOM it drew before lot 7 (MMR-520 ticket 4,
 * wave 4a): its code from under the heading to the footer, kept here in before_...(), the old screens by the traits
 * situation_before and whereami_before; in its four states - the competencies switched off; the records (none, two of which
 * one hidden, for the vaeiste and a reader); the inverse view of a node (no situation, two; no declaration, two); the
 * situations of a record (the record hidden, the preparation closed, the button for the vaeiste, a reader, none, two in the
 * order of their slots, the annotations of a deleted situation, the panel).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(situations_page::class)]
final class situations_page_test extends \advanced_testcase {
    use dom_canon;
    use situation_before;
    use whereami_before;

    /** @var \stdClass The vaeiste. */
    private $vaeiste;

    /** @var \stdClass Follows the vaeiste. */
    private $manager;

    /** @var \tool_vae_generator */
    private $generator;

    /**
     * RNCP100 (BC01 holding C01 and C02); the vaeiste and the manager who follows; the preparation and the pieces open.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        validation_catalog::reset();
        set_config('validationopen', 1, 'tool_vae');
        set_config('piecesopen', 1, 'tool_vae');
        $datagenerator = $this->getDataGenerator();
        $this->generator = $datagenerator->get_plugin_generator('tool_vae');
        $this->generator->create_source(['fichecode' => 'RNCP100', 'ficherang' => 'BC01', 'shortname' => 'Bloc 1']);
        foreach (['C01', 'C02'] as $rank) {
            $this->generator->create_competency([
                'fichecode' => 'RNCP100', 'ficherang' => $rank, 'shortname' => 'Competence ' . $rank, 'bloc' => 'BC01',
            ]);
        }
        $system = \context_system::instance()->id;
        $this->vaeiste = $datagenerator->create_user(['firstname' => 'Camille', 'lastname' => 'Vaeiste']);
        $this->manager = $datagenerator->create_user(['firstname' => 'Nadia', 'lastname' => 'Benali']);
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
     * The way back situations.php built before lot 7 (its code; $own and $candidateid given).
     *
     * @param int $candidateid
     * @param bool $own
     * @return string
     */
    private static function before_back(int $candidateid, bool $own): string {
        // The vaeiste goes back to their pathway; a reader goes back to the dossier they came from.
        $back = $own
            ? \html_writer::link(
                new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => 'mine']),
                get_string('selfposbackpathway', 'tool_vae')
            )
            : \html_writer::link(
                new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]),
                get_string('selfposbackdossier', 'tool_vae')
            );
        $back .= ' ' . \html_writer::link(
            new \moodle_url('/admin/tool/vae/consent.php', ['userid' => $candidateid]),
            get_string('consentlink', 'tool_vae')
        );

        return $back;
    }

    /**
     * What situations.php wrote under its heading for the records, before lot 7 ($body .= becomes $out .=).
     *
     * @param int $candidateid
     * @param array $records
     * @param bool $own
     * @return string
     */
    private static function before_records(int $candidateid, array $records, bool $own): string {
        $out = '';
        $out .= self::before_situation_records($candidateid, $records);
        $out .= \html_writer::tag('p', self::before_back($candidateid, $own));

        return $out;
    }

    /**
     * What situations.php wrote under its heading for the inverse view of a node, before lot 7.
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param string $rank
     * @param array $situations
     * @param array $declarations
     * @param \context_user $context
     * @return string
     */
    private static function before_inverse(
        int $candidateid,
        string $fichecode,
        string $rank,
        array $situations,
        array $declarations,
        \context_user $context
    ): string {
        $out = '';
        $out .= \html_writer::tag('p', \html_writer::tag('strong', s($fichecode)));
        $out .= self::before_situation_inverse($candidateid, $fichecode, $rank, $situations, $declarations, $context);
        $out .= \html_writer::tag('p', \html_writer::link(
            situation_screen::url($candidateid, $fichecode),
            get_string('situationbacklist', 'tool_vae')
        ));

        return $out;
    }

    /**
     * What situations.php wrote under its heading for the situations of a record, before lot 7 ($body .= becomes $out .=,
     * $OUTPUT is self::core(), the screens and the panel the old code kept in the traits; what the page read is given).
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param bool $own
     * @param string|null $hiddenmessage
     * @param bool $canwrite
     * @param bool $open
     * @param \moodle_url $url
     * @param array $situations
     * @param array $orphans
     * @return string
     */
    private static function before_record(
        int $candidateid,
        string $fichecode,
        bool $own,
        ?string $hiddenmessage,
        bool $canwrite,
        bool $open,
        \moodle_url $url,
        array $situations,
        array $orphans
    ): string {
        global $OUTPUT;

        $kept = $OUTPUT;
        $OUTPUT = self::core();
        try {
            $out = '';
            $out .= \html_writer::tag('p', \html_writer::tag('strong', s($fichecode)));
            $out .= self::before_situation_disclaimer($candidateid);
            $hidden = $hiddenmessage !== null;
            if ($hidden) {
                // Already escaped by hidden_frameworks::message().
                $out .= \html_writer::div($hiddenmessage, 'alert alert-info');
            }
            if ($canwrite && !$open) {
                $out .= \html_writer::div(s(get_string('errorvalidationclosed', 'tool_vae')), 'alert alert-info');
            }
            if ($canwrite && $open && !$hidden) {
                $out .= self::core()->single_button(
                    new \moodle_url($url, ['action' => 'new']),
                    get_string('situationnew', 'tool_vae'),
                    'post'
                );
            }
            $out .= self::before_situation_list($candidateid, $fichecode, $situations);
            $out .= self::before_note_orphans($candidateid, $orphans);
            $out .= self::before_whereami_render($candidateid, $fichecode, ['link' => true]);
            $out .= \html_writer::tag('p', self::before_back($candidateid, $own));

            return $out;
        } finally {
            $OUTPUT = $kept;
        }
    }

    public function test_the_competencies_switched_off_draw_the_dom_of_before(): void {
        $old = self::core()->notification(
            get_string('errorcompetenciesdisabled', 'tool_vae'),
            \core\output\notification::NOTIFY_ERROR
        );
        $this->assertSame(self::canon($old), self::canon($this->render(situations_page::disabled())));
    }

    public function test_the_records_draw_the_dom_of_before_for_the_vaeiste_and_a_reader(): void {
        $id = (int) $this->vaeiste->id;
        $hidden = 'The record Aide &amp; &quot;soins&quot; (RNCP200) is hidden.';
        $states = [
            'none' => [],
            'two, one hidden, the code "0"' => ['0' => null, 'RNCP100' => null, 'RNCP200' => $hidden],
        ];
        foreach ([$this->vaeiste, $this->manager] as $reader) {
            $this->setUser($reader);
            $own = $reader === $this->vaeiste;
            foreach ($states as $name => $records) {
                $old = self::before_records($id, $records, $own);
                $new = $this->render(situations_page::records($id, $records, $own));
                $this->assertSame(self::canon($old), self::canon($new), $name);
                $this->assertSame($records === [], str_contains($old, s(get_string('selfposnorecord', 'tool_vae'))), $name);
                $this->assertSame($own, str_contains($old, 'organisation.php?tab=mine'), $name . ': the way back.');
                $this->assertStringContainsString('consent.php?userid=' . $id, $old, $name);
            }
            $this->assertStringContainsString('Aide &amp; &quot;soins&quot;', $new, 'The message escaped once, as before.');
            $this->assertStringNotContainsString('&amp;amp;', $new);
            $this->assertStringContainsString('>0</a>', $new, 'The code 0.');
        }
    }

    public function test_the_inverse_view_draws_the_dom_of_before(): void {
        global $DB;

        $id = (int) $this->vaeiste->id;
        $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP100', 'title' => 'Matin & "soir"',
            'tags' => 'C01', 'setting' => 'Unite']);
        $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP100', 'title' => '0', 'tags' => 'C01,C02']);
        $this->generator->create_declaration(['userid' => $id, 'fichecode' => 'RNCP100', 'ficherang' => 'C01',
            'situation' => 'Une premiere declaration.']);
        $this->generator->create_declaration(['userid' => $id, 'fichecode' => 'RNCP100', 'ficherang' => 'C01',
            'situation' => 'Une seconde declaration.']);
        $context = \context_user::instance($id);
        $manager = new situation_manager();
        $declarations = $DB->get_records('tool_vae_evidence', ['candidateid' => $id, 'fichecode' => 'RNCP100',
            'ficherang' => 'C01'], 'id ASC');
        $this->assertCount(2, $declarations, 'Premise: two declarations.');
        $states = [
            'two situations, two declarations' => ['C01', $manager->tagged($id, 'RNCP100', ['C01']), $declarations],
            'nothing' => ['C02', [], []],
            'a rank gone' => ['Z99', [], []],
        ];
        foreach ([$this->vaeiste, $this->manager] as $reader) {
            $this->setUser($reader);
            foreach ($states as $name => [$rank, $situations, $claims]) {
                $old = self::before_inverse($id, 'RNCP100', $rank, $situations, $claims, $context);
                $new = $this->render(situations_page::inverse($id, 'RNCP100', $rank, $situations, $claims, $context));
                $this->assertSame(self::canon($old), self::canon($new), $name);
                $this->assertSame(count($situations), substr_count($old, 'tool-vae-situation-item'), $name);
                $this->assertSame(count($claims), substr_count($old, 'tool-vae-inverse-decl'), $name);
            }
        }
        $old = self::before_inverse($id, 'RNCP100', 'C01', $states['two situations, two declarations'][1], $declarations, $context);
        $this->assertLessThan(strpos($old, 'seconde'), strpos($old, 'premiere'), 'Premise: the declarations in their order.');
        $this->assertStringContainsString('Matin &amp; &quot;soir&quot;', $old, 'Premise: the title escaped once.');
    }

    public function test_the_situations_of_a_record_draw_the_dom_of_before_in_each_state(): void {
        $id = (int) $this->vaeiste->id;
        $first = $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP100', 'title' => 'Accueil',
            'tags' => 'C01', 'periodstart' => 1756684800, 'text' => 'Un texte.']);
        // A situation just opened, with no title yet: "Work situation 2".
        (new situation_manager())->create($id, 'RNCP100');
        (new spiece_manager())->add_link((int) $first->id, 'other', 'Un lien', 'https://example.org/a', '');
        $situations = (new situation_manager())->list($id, 'RNCP100');
        $this->assertCount(2, $situations, 'Premise: two situations.');
        $orphans = [(object) [
            'id' => '9', 'state' => note_manager::ACTIVE, 'textformat' => (string) FORMAT_PLAIN, 'text' => 'Sur une partie.',
            'authorid' => null, 'authoruuid' => null, 'importedat' => null, 'timecreated' => '1763700000', 'standing' => true,
            'targetuuid' => 'gone-1', 'slot' => '5', 'fichecode' => 'RNCP100',
        ]];
        $url = situation_screen::url($id, 'RNCP100');
        $hidden = 'The record Aide &amp; soins (RNCP100) is hidden.';
        $states = [
            'the vaeiste, open' => [$this->vaeiste, null, true, true, $situations, $orphans],
            'the vaeiste, closed' => [$this->vaeiste, null, true, false, $situations, []],
            'the vaeiste, hidden' => [$this->vaeiste, $hidden, true, true, $situations, []],
            'the vaeiste, nothing yet' => [$this->vaeiste, null, true, true, [], []],
            'a reader' => [$this->manager, $hidden, false, true, $situations, $orphans],
            // Closed, said to the vaeiste who writes only (added after the mutant ssp-closed stayed green).
            'a reader, closed' => [$this->manager, null, false, false, $situations, []],
            // Hidden AND closed under the vaeiste: the two notices, in the order of before (the pilot, mutant of order).
            'the vaeiste, hidden and closed' => [$this->vaeiste, $hidden, true, false, $situations, []],
        ];
        foreach ($states as $name => [$reader, $message, $canwrite, $open, $rows, $notes]) {
            $this->setUser($reader);
            $own = $reader === $this->vaeiste;
            $old = self::before_record($id, 'RNCP100', $own, $message, $canwrite, $open, $url, $rows, $notes);
            $new = $this->render(situations_page::record(
                $id,
                'RNCP100',
                $own,
                $message,
                $canwrite,
                $open,
                $url,
                $rows,
                new notes_orphans($id, $notes),
                new whereami_tree($id, 'RNCP100', ['link' => true])
            ));
            $this->assertSame(self::canon($old), self::canon($new), $name);
            $this->assertSame($canwrite && $open && $message === null, str_contains($old, 'value="new"'), $name . ': button.');
            $this->assertSame($canwrite && !$open, str_contains($old, s(get_string('errorvalidationclosed', 'tool_vae'))));
            $this->assertSame($message !== null, str_contains($old, 'Aide &amp; soins'), $name . ': hidden.');
            $this->assertSame($notes !== [], str_contains($old, 'tool-vae-notes'), $name . ': orphans.');
            $this->assertSame(count($rows), substr_count($old, 'tool-vae-situation-item'), $name);
            $this->assertStringContainsString('tool-vae-whereami', $old, $name . ': the panel.');
        }
        $this->setUser($this->vaeiste);
        $old = self::before_record($id, 'RNCP100', true, $hidden, true, false, $url, $situations, []);
        $this->assertLessThan(
            strpos($old, s(get_string('errorvalidationclosed', 'tool_vae'))),
            strpos($old, 'Aide &amp; soins'),
            'Premise: the record hidden is said before the preparation closed.'
        );
        $old = self::before_record($id, 'RNCP100', true, null, true, true, $url, $situations, []);
        $this->assertSame(2, substr_count($old, s(get_string('situationpieces', 'tool_vae', 0)))
            + substr_count($old, s(get_string('situationpieces', 'tool_vae', 1))), 'Premise: each its count of pieces.');
        $this->assertLessThan(strpos($old, 'data-slot="2"'), strpos($old, 'data-slot="1"'), 'Premise: the order of the slots.');
    }

    public function test_the_template_places_raw_only_the_keys_named_html(): void {
        global $CFG;

        $text = file_get_contents($CFG->dirroot . '/admin/tool/vae/templates/situations_page.mustache');
        preg_match_all('/\{\{\{(\w+)\}\}\}/', $text, $raw);
        $this->assertNotEmpty($raw[1]);
        foreach ($raw[1] as $key) {
            $this->assertStringEndsWith('html', $key);
        }
    }
}
