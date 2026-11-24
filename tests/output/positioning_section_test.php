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

use tool_vae\local\identity_map;
use tool_vae\local\structure_archive;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/positioning_before.php');

/**
 * The positionings of the structure in the dossier, drawn by pieces (lot 7, MMR-520 ticket 4, wave 3a).
 *
 * WHAT IS ASSERTED. That the piece positioning_section (with positioning_structure and positioning_line) draws the same DOM as
 * positioning_screen::section() drew before its migration (its code kept in the trait positioning_before), for each reader
 * (the vaeiste, the manager who evaluates, the direction who certifies and produces the archive, a reader who may do
 * neither) and for each state a parameter of a piece changes, each with a PREMISE that fails if the data of the test stops
 * producing that state: a certification that stands, dated and with a day of validity, past (to renew) or not, with the
 * reason of an alert, withdrawn; a competency evaluated after its bloc was certified; the lines of the record this one
 * replaces, with and without a day of validity; the vaeiste's own level, standing and withdrawn; a competency gone from the
 * record; competencies standing alone; a record with no bloc; the author of a line who exists, whose account was deleted,
 * who is unknown, a line restored from an archive; the archive of the structure in place and the button that produces one;
 * the records gone from the site; nothing at all. Then: special characters and a script come out escaped as before; the
 * figure 0 is drawn as before; a link drawn twice is drawn once, as before; the templates place HTML only under the keys
 * named and render their example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(positioning_section::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(positioning_structure::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(positioning_line::class)]
final class positioning_section_test extends \advanced_testcase {
    use dom_canon;
    use positioning_before;

    /** @var \stdClass The vaeiste. */
    private $candidate;

    /** @var \stdClass Follows the vaeiste and evaluates. */
    private $manager;

    /** @var \stdClass Reads every dossier, certifies and produces the archive of the structure. */
    private $direction;

    /** @var \stdClass Reads every dossier, and may do nothing else. */
    private $reader;

    /** @var \tool_vae_generator */
    private $generator;

    /**
     * RNCP100 (BC01 holding C01 and C02, BC02 holding C03, C04 standing alone), RS200 (no bloc: C01, C02), RNCP90 (BC01
     * holding C01, BC02 holding C02) replaced by RNCP100; the people; the vaeiste declared on RNCP100 and RS200.
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/dossier.php'));
        $PAGE->set_context(\context_system::instance());

        $datagenerator = $this->getDataGenerator();
        $this->generator = $datagenerator->get_plugin_generator('tool_vae');
        $this->record('RNCP100', [['BC01', ''], ['BC02', ''], ['C01', 'BC01'], ['C02', 'BC01'], ['C03', 'BC02'], ['C04', '']]);
        $this->record('RS200', [['C01', ''], ['C02', '']]);
        $this->record('RNCP90', [['BC01', ''], ['BC02', ''], ['C01', 'BC01'], ['C02', 'BC02']]);
        $this->generator->create_replacement(['fichecode' => 'RNCP90', 'successorcode' => 'RNCP100']);
        foreach (['C01', 'BC01', 'BC02'] as $rank) {
            $this->generator->create_correspondence(['from' => 'RNCP90-' . $rank, 'to' => 'RNCP100-' . $rank]);
        }

        $system = \context_system::instance()->id;
        $this->candidate = $datagenerator->create_user(['firstname' => 'Camille', 'lastname' => 'Vaeiste']);
        $this->manager = $datagenerator->create_user(['firstname' => 'Nadia', 'lastname' => "Ben Ali & O'Neil"]);
        $this->direction = $datagenerator->create_user(['firstname' => 'Dana', 'lastname' => 'Direction']);
        $this->reader = $datagenerator->create_user();
        $own = $datagenerator->create_role();
        foreach (['declare', 'viewown', 'positionself'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $own, $system, true);
        }
        set_role_contextlevels($own, [CONTEXT_USER]);
        role_assign($own, $this->candidate->id, \context_user::instance($this->candidate->id)->id);
        $follow = $datagenerator->create_role();
        foreach (['viewassigned', 'evaluate'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $follow, $system, true);
        }
        set_role_contextlevels($follow, [CONTEXT_USER]);
        role_assign($follow, $this->manager->id, \context_user::instance($this->candidate->id)->id);
        $director = $datagenerator->create_role();
        foreach (['viewany', 'certify', 'issuestructure'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $director, $system, true);
        }
        role_assign($director, $this->direction->id, $system);
        $viewer = $datagenerator->create_role();
        assign_capability('tool/vae:viewany', CAP_ALLOW, $viewer, $system, true);
        role_assign($viewer, $this->reader->id, $system);
        accesslib_clear_all_caches_for_unit_testing();

        foreach (['RNCP100' => 'C01', 'RS200' => 'C01'] as $code => $rank) {
            $this->generator->create_declaration([
                'userid' => $this->candidate->id, 'fichecode' => $code, 'ficherang' => $rank, 'situation' => 'Une situation.',
            ]);
        }
    }

    /**
     * Import a record: the first rank is the source's first competency, the others are added.
     *
     * @param string $code
     * @param array $ranks [rank, bloc] pairs.
     */
    private function record(string $code, array $ranks): void {
        foreach ($ranks as $index => [$rank, $bloc]) {
            $data = ['fichecode' => $code, 'ficherang' => $rank, 'shortname' => 'Competence ' . $rank . ' ' . $code];
            if ($index === 0) {
                $this->generator->create_source($data);
            } else {
                $this->generator->create_competency($data + ($bloc === '' ? [] : ['bloc' => $bloc]));
            }
        }
    }

    /**
     * Write one line of the structure, as the managers write it (or as a restore writes it).
     *
     * @param string $table tool_vae_eval or tool_vae_certif.
     * @param string $code
     * @param string $rank
     * @param string $status
     * @param int $time
     * @param array $more Other fields: authorid, importedat, reason, decisiondate, alertreason, validuntil.
     * @return \stdClass
     */
    private function line(string $table, string $code, string $rank, string $status, int $time, array $more = []): \stdClass {
        global $DB;

        $row = (object) ($more + [
            'uuid' => identity_map::generate_ulid(),
            'candidateid' => $this->candidate->id,
            'fichecode' => $code,
            'ficherang' => $rank,
            'status' => $status,
            'reason' => 'Motif de ' . $code . ' ' . $rank . ' a ' . $time,
            'reasonhash' => hash('sha256', $table . $code . $rank . $status . $time),
            'authorid' => null,
            'authoruuid' => null,
            'importedat' => null,
            'timecreated' => $time,
        ]);
        if ($table === 'tool_vae_certif') {
            $row->decisiondate = $row->decisiondate ?? '2026-10-15';
            $row->alertreason = $row->alertreason ?? null;
            $row->validuntil = $row->validuntil ?? null;
        }
        $row->id = $DB->insert_record($table, $row);

        return $row;
    }

    /**
     * The piece drawn by the renderer of the plugin for the web.
     *
     * @param \core\output\renderable $piece
     * @return string HTML.
     */
    private function render(\core\output\renderable $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($piece);
    }

    /**
     * Assert that the section of the vaeiste draws the DOM of before for this reader, and give the HTML of before.
     *
     * @param \stdClass $reader
     * @param string $name
     * @return string The HTML of before.
     */
    private function same(\stdClass $reader, string $name): string {
        $this->setUser($reader);
        $context = \context_user::instance($this->candidate->id);
        $before = self::before_pos_section((int) $this->candidate->id, $context);
        $after = $this->render(new positioning_section((int) $this->candidate->id, $context));
        $this->assertSame(self::canon($before), self::canon($after), $name);

        return $before;
    }

    /**
     * The full dossier: every state of a bloc, a competency, a line and the archive of the structure.
     */
    private function full(): void {
        global $DB;
        $manager = (int) $this->manager->id;
        $direction = (int) $this->direction->id;
        // BC01: certified, dated, with a day of validity that is past and the reason of an alert; C01 evaluated after it.
        $this->line('tool_vae_certif', 'RNCP100', 'BC01', 'certified', 1762000000, [
            'authorid' => $direction, 'decisiondate' => '2020-01-15', 'validuntil' => '2020-06-30',
            'alertreason' => 'Seconde competence vue la semaine prochaine.',
        ]);
        $this->line('tool_vae_eval', 'RNCP100', 'C01', 'sufficient', 1762000100, ['authorid' => $manager]);
        // C02: evaluated, then withdrawn.
        $this->line('tool_vae_eval', 'RNCP100', 'C02', 'inproof', 1762000000, ['authorid' => $manager]);
        $this->line('tool_vae_eval', 'RNCP100', 'C02', 'withdrawn', 1762000050, ['authorid' => $manager]);
        // BC02: certified, then withdrawn; C03 gone from the record, evaluated by an account deleted since.
        $this->line('tool_vae_certif', 'RNCP100', 'BC02', 'certified', 1761000000, ['authorid' => $direction]);
        $this->line('tool_vae_certif', 'RNCP100', 'BC02', 'withdrawn', 1761000100, ['authorid' => $direction]);
        $gone = $this->getDataGenerator()->create_user(['firstname' => 'Ancien', 'lastname' => 'Compte']);
        $this->line('tool_vae_eval', 'RNCP100', 'C03', 'toconstruct', 1761000000, ['authorid' => (int) $gone->id]);
        delete_user($gone);
        $competency = $DB->get_record('competency', ['idnumber' => 'RNCP100-C03'], '*', MUST_EXIST);
        $source = $DB->get_record('tool_vae_source', ['fichecode' => 'RNCP100'], '*', MUST_EXIST);
        $DB->insert_record('tool_vae_obsolete', (object) [
            'competencyid' => $competency->id, 'sourceid' => $source->id, 'detectedat' => time(), 'kind' => 'missing',
        ]);
        // C04, standing alone: a line restored from an archive.
        $this->line('tool_vae_eval', 'RNCP100', 'C04', 'inproof', 1760000000, ['importedat' => 1760000500]);
        // RS200, its own bloc: certified until a day to come, no alert; C01 evaluated by nobody known.
        $this->line('tool_vae_certif', 'RS200', '', 'certified', 1762000000, [
            'authorid' => $direction, 'decisiondate' => '2026-11-04', 'validuntil' => '2099-01-01',
        ]);
        $this->line('tool_vae_eval', 'RS200', 'C01', 'sufficient', 1762000000);
        // The record RNCP100 replaces: a line on C01, a certification of BC01 with a day of validity, of BC02 without.
        $this->line('tool_vae_eval', 'RNCP90', 'C01', 'sufficient', 1750000000, ['authorid' => $manager]);
        $this->line('tool_vae_certif', 'RNCP90', 'BC01', 'certified', 1750000000, [
            'authorid' => $direction, 'decisiondate' => '2025-05-02', 'validuntil' => '2026-05-02',
        ]);
        $this->line('tool_vae_certif', 'RNCP90', 'BC02', 'certified', 1750000000, ['authorid' => $direction]);
        // The vaeiste's own level: on BC01 and C01, and on C02 given then withdrawn.
        foreach ([['BC01', 3], ['C01', 2], ['C02', 1], ['C02', 0]] as [$rank, $level]) {
            $this->generator->create_selfpos([
                'userid' => $this->candidate->id, 'fichecode' => 'RNCP100', 'ficherang' => $rank, 'level' => $level,
            ]);
        }
        // The archive of the structure in place, produced by the direction.
        $this->setUser($this->direction);
        structure_archive::issue((int) $this->candidate->id);
    }

    public function test_the_pieces_are_named_templates(): void {
        global $PAGE;
        $context = \context_user::instance($this->candidate->id);
        $row = (object) ['status' => 'inproof', 'reason' => '', 'timecreated' => 1];
        $pieces = [
            'tool_vae/positioning_section' => new positioning_section((int) $this->candidate->id, $context),
            'tool_vae/positioning_structure' => new positioning_structure((int) $this->candidate->id),
            'tool_vae/positioning_line' => new positioning_line($row, $context, 'eval'),
        ];
        foreach ($pieces as $template => $piece) {
            $this->assertInstanceOf(\core\output\renderable::class, $piece);
            $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
            $this->assertSame($template, $piece->get_template_name($PAGE->get_renderer('tool_vae')));
        }
    }

    public function test_each_reader_reads_the_dom_of_before_on_a_full_dossier(): void {
        $this->full();

        $readers = [
            'the vaeiste' => $this->candidate,
            'the manager who evaluates' => $this->manager,
            'the direction' => $this->direction,
            'a reader who may do nothing else' => $this->reader,
        ];
        $before = [];
        foreach ($readers as $name => $reader) {
            $before[$name] = $this->same($reader, $name);
        }

        // Premises: each state is drawn by the old code, so that the comparison above held it.
        $direction = $before['the direction'];
        $this->assertSame(1, substr_count($direction, 'alert alert-secondary small'), 'BC01 is to renew.');
        $this->assertSame(1, substr_count($direction, get_string('positioningrenewlink', 'tool_vae')), 'Renewal link.');
        $this->assertSame(2, substr_count($direction, get_string('positioningcertifylink', 'tool_vae')), 'BC02 and RS200.');
        $this->assertSame(1, substr_count($direction, 'alert alert-warning small'), 'C01 was evaluated after BC01.');
        $this->assertStringContainsString(s(get_string('positioningalertreasonlabel', 'tool_vae')), $direction);
        $this->assertStringContainsString(s(get_string('positioninguntil', 'tool_vae', '2099-01-01')), $direction);
        $this->assertStringContainsString(s(get_string('positioningcertifiedon', 'tool_vae', '2026-11-04')), $direction);
        $this->assertStringContainsString(s(get_string('positioningwhole', 'tool_vae')), $direction, 'RS200 has no bloc.');
        $this->assertSame(4, substr_count($direction, '<ul class="list-unstyled ms-3">'), 'BC01, BC02, alone, RS200.');
        $this->assertStringContainsString('Competence C04 RNCP100', $direction, 'The competency standing alone.');
        $this->assertSame(
            3,
            substr_count($direction, s(get_string('positioningnone', 'tool_vae'))),
            'BC02 and C02 withdrawn, C02 of RS200 never evaluated.'
        );
        $this->assertStringContainsString(s(get_string('coveragegone', 'tool_vae')), $direction, 'C03 is gone.');
        $this->assertStringContainsString(s(get_string('positioningauthorgone', 'tool_vae')), $direction, 'Deleted, unknown.');
        $this->assertStringContainsString(s(get_string('positioningrestored', 'tool_vae')), $direction, 'C04 restored.');
        $this->assertStringContainsString(s(fullname($this->manager)), $direction);
        $this->assertSame(3, substr_count($direction, 'RNCP90'), 'Three lines of the record replaced: C01, BC01, BC02.');
        $this->assertStringContainsString(s(get_string('positioninguntil', 'tool_vae', '2026-05-02')), $direction);
        $this->assertSame(1, substr_count($direction, s(get_string('positioninguntil', 'tool_vae', '2026-05-02'))));
        $this->assertSame(2, substr_count($direction, 'tool-vae-selfpos-line'), 'BC01 and C01; C02 withdrawn.');
        $this->assertStringContainsString('btn btn-secondary', $direction, 'The direction produces the archive.');
        $this->assertStringContainsString('pluginfile.php', $direction, 'The direction downloads it.');
        $this->assertStringContainsString(s(get_string('selfposreadall', 'tool_vae')), $direction);
        $this->assertSame(0, substr_count($before['the manager who evaluates'], get_string('positioningcertifylink', 'tool_vae')));
        $this->assertSame(
            6,
            substr_count($before['the manager who evaluates'], get_string('positioningevaluatelink', 'tool_vae')),
            'C01, C02, C03, C04 of RNCP100, C01 and C02 of RS200.'
        );
        $this->assertStringContainsString(s(get_string('selfposlink', 'tool_vae')), $before['the vaeiste']);
        $this->assertStringContainsString('pluginfile.php', $before['the vaeiste'], 'The vaeiste reads the archive.');
        $this->assertStringNotContainsString('btn btn-secondary', $before['the vaeiste']);
        $nothing = $before['a reader who may do nothing else'];
        $this->assertStringNotContainsString('pluginfile.php', $nothing);
        $this->assertStringNotContainsString(get_string('positioningevaluatelink', 'tool_vae'), $nothing);
        $this->assertStringNotContainsString(get_string('positioningcertifylink', 'tool_vae'), $nothing);
    }

    public function test_records_gone_from_the_site_leave_the_archive_alone_as_before(): void {
        global $DB;
        $this->full();
        // The records are gone from the site; the declarations and the archive of the structure stay.
        $DB->delete_records('tool_vae_source', ['fichecode' => 'RNCP100']);
        $DB->delete_records('tool_vae_source', ['fichecode' => 'RS200']);

        $direction = $this->same($this->direction, 'the direction');
        $vaeiste = $this->same($this->candidate, 'the vaeiste');
        $nothing = $this->same($this->reader, 'a reader who may do nothing else');

        $this->assertStringStartsWith('<section class="tool-vae-positioning mb-4"><p>', $direction, 'No heading: bare.');
        $this->assertStringContainsString('btn btn-secondary', $direction);
        $this->assertStringContainsString('pluginfile.php', $direction);
        $this->assertStringContainsString('pluginfile.php', $vaeiste);
        $this->assertStringNotContainsString('btn btn-secondary', $vaeiste);
        $this->assertSame('', $nothing, 'Nothing to read and nothing to do: nothing drawn.');
    }

    public function test_a_vaeiste_with_no_declaration_gets_the_button_or_nothing_as_before(): void {
        $stranger = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($stranger->id);

        // The direction may produce the archive of anyone: the button alone, in the section.
        $this->setUser($this->direction);
        $before = self::before_pos_section((int) $stranger->id, $context);
        $after = $this->render(new positioning_section((int) $stranger->id, $context));
        $this->assertSame(self::canon($before), self::canon($after));
        $this->assertStringStartsWith('<section class="tool-vae-positioning mb-4"><p><a', $before, 'Premise: the button.');
        $this->assertStringNotContainsString('pluginfile.php', $before, 'Premise: no archive in place.');

        // A reader who may do neither: nothing.
        $this->setUser($this->reader);
        $before = self::before_pos_section((int) $stranger->id, $context);
        $after = $this->render(new positioning_section((int) $stranger->id, $context));
        $this->assertSame('', $before);
        $this->assertSame('', trim($after));
    }

    public function test_special_characters_and_a_script_come_out_escaped_as_before(): void {
        global $DB;
        // A step renamed by the administrator: the setting is PARAM_TEXT, which keeps quotes, an ampersand and a lone "<".
        $label = 'J\'ai déjà "pratiqué" & < 3';
        $this->assertSame($label, clean_param($label, PARAM_TEXT), 'A label the setting accepts.');
        set_config('selfposlabel2', $label, 'tool_vae');
        $this->generator->create_selfpos([
            'userid' => $this->candidate->id, 'fichecode' => 'RNCP100', 'ficherang' => 'C01', 'level' => 2,
        ]);
        $this->line('tool_vae_eval', 'RNCP100', 'C01', 'inproof', 1762000000, [
            'authorid' => (int) $this->manager->id, 'reason' => "<script>alert(2)</script>\nA & B",
        ]);
        $this->line('tool_vae_eval', 'RNCP100', 'C02', 'inproof', 1762000000, ['authorid' => (int) $this->manager->id]);
        $this->line('tool_vae_certif', 'RNCP100', 'BC01', 'certified', 1762000000, [
            'authorid' => (int) $this->direction->id, 'alertreason' => '<script>alert(3)</script>',
        ]);
        $competency = $DB->get_record('competency', ['idnumber' => 'RNCP100-C02'], '*', MUST_EXIST);
        // A competency renamed in tool_lp (PARAM_TEXT too).
        $DB->set_field('competency', 'shortname', 'Soins & "aide"', ['id' => $competency->id]);

        foreach ([$this->direction, $this->candidate] as $reader) {
            $before = $this->same($reader, fullname($reader));
            $after = $this->render(new positioning_section(
                (int) $this->candidate->id,
                \context_user::instance($this->candidate->id)
            ));
            // Premises: each script and each character is in the data the old code drew.
            $this->assertSame(2, substr_count($before, '&lt;script&gt;alert('), 'The reason and the reason of the alert.');
            $this->assertStringContainsString('Ben Ali &amp; O&#039;Neil', $before, 'The name of the author.');
            $this->assertStringContainsString('J&#039;ai déjà &quot;pratiqué&quot; &amp; &lt; 3', $before, 'The level.');
            $this->assertStringNotContainsString('<script', $after);
            $this->assertSame(2, substr_count($after, '&lt;script&gt;alert('));
            $this->assertStringContainsString('Soins &amp; &quot;aide&quot;', $after);
            $this->assertStringContainsString('J&#039;ai déjà &quot;pratiqué&quot; &amp; &lt; 3', $after);
        }
    }

    public function test_a_link_drawn_twice_is_drawn_once_as_before(): void {
        global $CFG;
        $this->full();
        // A site that customised the words of its links (tool_customlang writes there) so that they say the same thing.
        $file = $CFG->langlocalroot . '/en_local/tool_vae.php';
        check_dir_exists(dirname($file));
        $history = get_string('positioninghistory', 'tool_vae');
        $words = '';
        foreach (['positioningevaluatelink', 'positioningcertifylink', 'positioningrenewlink'] as $key) {
            $words .= '$string[' . var_export($key, true) . '] = ' . var_export($history, true) . ";\n";
        }
        file_put_contents($file, "<?php\n" . $words);
        get_string_manager()->reset_caches();
        try {
            $this->assertSame($history, get_string('positioningevaluatelink', 'tool_vae'), 'The customisation is read.');
            foreach (['the manager who evaluates' => $this->manager, 'the direction' => $this->direction] as $name => $reader) {
                $before = $this->same($reader, $name);
                // Premise: the old code dropped the second link of each pair (the same address, the same words).
                $this->assertStringNotContainsString(' | ', $before, $name);
                $this->assertGreaterThan(0, substr_count($before, '>' . $history . '</a>'), $name);
            }
        } finally {
            unlink($file);
            get_string_manager()->reset_caches();
        }
    }

    public function test_the_templates_place_html_only_under_the_keys_named_and_render_their_example(): void {
        $templates = [
            'positioning_section' => [['structurehtml', 'linehtml'], 'tool-vae-positioning'],
            'positioning_structure' => [[], 'btn btn-secondary'],
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

    public function test_the_figure_0_is_drawn_as_before(): void {
        global $DB;
        set_config('selfposlabel1', '0', 'tool_vae');
        $this->generator->create_selfpos([
            'userid' => $this->candidate->id, 'fichecode' => 'RNCP100', 'ficherang' => 'C01', 'level' => 1,
        ]);
        $this->line('tool_vae_eval', 'RNCP100', 'C01', 'inproof', 1762000000, ['reason' => '0']);
        $this->line('tool_vae_certif', 'RNCP100', 'BC01', 'certified', 1762000000, ['reason' => '0', 'alertreason' => '0']);
        $DB->set_field('competency', 'shortname', '0', ['idnumber' => 'RNCP100-C02']);

        $before = $this->same($this->direction, 'the direction');

        $this->assertStringContainsString('<strong>0</strong>', $before, 'A competency named 0.');
        $this->assertSame(2, substr_count($before, '<div>0</div>'), 'Two reasons that are 0.');
        $this->assertStringNotContainsString(
            s(get_string('positioningalertreasonlabel', 'tool_vae')),
            $before,
            'An alert that is 0 was not drawn: empty() said so.'
        );
    }
}
