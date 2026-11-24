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
require_once(__DIR__ . '/presenter_before.php');

use tool_vae\local\anchor;
use tool_vae\local\competency_gateway;
use tool_vae\local\evidence_manager;
use tool_vae\local\importer;

/**
 * A declaration drawn for reading, the warning of its anchor, and the two together (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the pieces declaration, anchor_warning and attest_declaration draw the same DOM as
 * presenter::declaration(), presenter::anchor_warning() and attest_screen::declaration() drew before their migration (their
 * code kept in the trait presenter_before), for a competency under a bloc, one outside any bloc, a declaration with no
 * competency, a detail empty or equal to "0", a rank "0", each divergence of the anchor and none; that the warning comes
 * before the declaration with nothing between them; that the context holds plain texts; that a script typed in the detail
 * or the narrative comes out escaped; that the templates place as HTML only their keys ...html and render their examples.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(declaration::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(anchor_warning::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(attest_declaration::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\local\presenter::class)]
final class declaration_test extends \advanced_testcase {
    use dom_canon;
    use presenter_before;

    /** @var \stdClass The vaeiste. */
    private \stdClass $candidate;

    /** @var int[] The competencies of the record RS00000, by code. */
    private array $ids = [];

    protected function setUp(): void {
        global $CFG, $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->libdir . '/formslib.php');
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/attest.php'));
        $PAGE->set_context(\context_system::instance());

        $result = (new importer())->import((object) [
            'fichecode' => 'RS00000',
            'fichetype' => 'rs',
            'name' => 'Certification inventée pour les tests',
            'sourceurl' => 'https://www.data.gouv.fr/',
            'sourceupdated' => strtotime('2026-06-01'),
            'licence' => 'Licence Ouverte 2.0',
            'attribution' => 'Personne — jeu d\'essai',
        ], importer::rows_from_csv(
            "rang,intitule,description,bloc\n"
            . "BC01,Accompagner la personne,,\n"
            . "C02,Aider au lever,,BC01\n"
            . "Z01,Une compétence hors bloc,,\n"
        ));
        foreach ((new competency_gateway())->list_framework_competencies((int) $result->frameworkid) as $competency) {
            $this->ids[$competency->idnumber] = (int) $competency->id;
        }

        $generator = $this->getDataGenerator();
        $this->candidate = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('tool/vae:declare', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        role_assign($roleid, $this->candidate->id, \context_user::instance($this->candidate->id)->id);
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
     * A declaration recorded on a competency of RS00000, read back from its table.
     *
     * @param string $idnumber
     * @param array $fields What to change in the submission.
     * @return \stdClass The row of tool_vae_evidence.
     */
    private function declare(string $idnumber, array $fields = []): \stdClass {
        global $DB;
        $this->setUser($this->candidate);
        $record = (new evidence_manager())->declare_activity((int) $this->candidate->id, (object) ($fields + [
            'competencyid' => $this->ids[$idnumber],
            'periodstart' => 1756684800,
            'periodend' => 1782172800,
            'settingtype' => 'ehpad',
            'settingdetail' => 'Unité protégée, équipe de nuit',
            'situation' => "Accompagnement au lever.\n\nSecond paragraphe *en Markdown*.",
            'situationformat' => FORMAT_MARKDOWN,
        ]));
        $this->setAdminUser();

        return $DB->get_record('tool_vae_evidence', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * The declarations of the cases.
     *
     * @return \stdClass[] By name.
     */
    private function cases(): array {
        $underbloc = $this->declare('RS00000-C02');
        $nobloc = $this->declare('RS00000-Z01', ['settingdetail' => '', 'situationformat' => FORMAT_PLAIN]);
        $zero = clone $nobloc;
        $zero->settingdetail = '0';
        $zero->ficherang = '0';
        $zero->situation = '0';
        $none = clone $underbloc;
        $none->competencyid = null;

        return ['under a bloc' => $underbloc, 'outside any bloc, no detail' => $nobloc, 'the figure 0' => $zero,
            'no competency' => $none];
    }

    public function test_the_pieces_are_named_templates(): void {
        global $PAGE;
        $claim = $this->declare('RS00000-C02');
        $context = \context_user::instance((int) $this->candidate->id);
        $renderer = $PAGE->get_renderer('tool_vae');
        $pieces = [
            'tool_vae/declaration' => new declaration($claim, $context),
            'tool_vae/anchor_warning' => new anchor_warning($claim, null),
            'tool_vae/attest_declaration' => new attest_declaration($claim, $context),
        ];
        foreach ($pieces as $name => $piece) {
            $this->assertInstanceOf(\core\output\renderable::class, $piece);
            $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
            $this->assertSame($name, $piece->get_template_name($renderer));
        }
    }

    public function test_the_declaration_draws_the_dom_of_before(): void {
        $context = \context_user::instance((int) $this->candidate->id);
        foreach ($this->cases() as $name => $claim) {
            $this->assertSame(
                self::canon(self::before_declaration($claim, $context)),
                self::canon($this->render(new declaration($claim, $context))),
                $name
            );
        }
    }

    public function test_the_warning_draws_the_dom_of_before_for_each_divergence_and_none(): void {
        $claims = $this->cases();
        foreach ([anchor::MISSING, anchor::RENAMED, anchor::UNLINKED, null] as $divergence) {
            foreach ($claims as $name => $claim) {
                $expected = self::before_anchor_warning($claim, $divergence);
                $html = $this->render(new anchor_warning($claim, $divergence));
                $this->assertSame(self::canon($expected), self::canon($html), $name . ' ' . ($divergence ?? 'none'));
            }
        }
        $this->assertSame('', $this->render(new anchor_warning($claims['under a bloc'], null)), 'Nothing to say, nothing drawn.');
    }

    public function test_the_screen_of_attestation_puts_the_warning_first_with_nothing_between(): void {
        $context = \context_user::instance((int) $this->candidate->id);
        $holds = $this->declare('RS00000-C02');
        $missing = clone $holds;
        $missing->userevidenceid = 0;
        $unlinked = clone $holds;
        $unlinked->competencyid = $this->ids['RS00000-Z01'];
        $renamed = clone $holds;
        $renamed->corelabel = 'Un autre libellé';

        foreach (['holds' => $holds, 'missing' => $missing, 'unlinked' => $unlinked, 'renamed' => $renamed] as $name => $claim) {
            $html = $this->render(new attest_declaration($claim, $context));
            $this->assertSame(self::canon(self::before_attest_declaration($claim, $context)), self::canon($html), $name);
            $warning = $this->render(new anchor_warning($claim, anchor::divergence($claim)));
            $this->assertSame($warning . $this->render(new declaration($claim, $context)), $html, $name);
        }
        $this->assertNotSame(
            $this->render(new declaration($missing, $context)),
            $this->render(new attest_declaration($missing, $context)),
            'The premise: a missing anchor is warned of.'
        );
    }

    public function test_the_gateway_given_is_the_one_read(): void {
        global $CFG;
        require_once($CFG->dirroot . '/admin/tool/vae/tests/fixtures/unreadable_gateway.php');
        $context = \context_user::instance((int) $this->candidate->id);
        $claim = $this->declare('RS00000-C02');
        $claim->userevidenceid = 0;
        $gateway = new \tool_vae\fixtures\unreadable_gateway();

        $html = $this->render(new attest_declaration($claim, $context, $gateway));
        $this->assertDebuggingCalled();

        // The gateway that cannot read says nothing of the anchor (anchor::divergence()), as before.
        $this->assertSame(self::canon(self::before_declaration($claim, $context)), self::canon($html));
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');
        $context = \context_user::instance((int) $this->candidate->id);
        $claim = $this->declare('RS00000-C02', ['settingdetail' => 'A & B']);

        $exported = (new declaration($claim, $context))->export_for_template($output);
        $this->assertSame(['label' => get_string('fichesource', 'tool_vae'), 'value' => 'RS00000-C02'], $exported['rows'][0]);
        $this->assertSame(['label' => get_string('competency', 'tool_vae'), 'value' => 'Aider au lever'], $exported['rows'][1]);
        $this->assertSame(
            ['label' => get_string('declarationbloc', 'tool_vae'), 'value' => 'RS00000-BC01 — Accompagner la personne'],
            $exported['rows'][2]
        );
        $this->assertSame(['label' => get_string('settingdetail', 'tool_vae'), 'value' => 'A & B'], end($exported['rows']));
        $this->assertSame(get_string('situation', 'tool_vae'), $exported['situation']);
        $this->assertStringContainsString('<em>en Markdown</em>', $exported['situationhtml']);

        $this->assertSame(['noticehtml' => false], (new anchor_warning($claim, null))->export_for_template($output));
        $warning = (new anchor_warning($claim, anchor::MISSING))->export_for_template($output);
        $this->assertStringContainsString(get_string('anchormissing', 'tool_vae'), $warning['noticehtml']);

        $both = (new attest_declaration($claim, $context))->export_for_template($output);
        $this->assertSame('', $both['warninghtml']);
        $this->assertStringContainsString('tool-vae-claim', $both['declarationhtml']);
    }

    public function test_a_script_in_the_detail_or_the_narrative_comes_out_escaped(): void {
        $context = \context_user::instance((int) $this->candidate->id);
        $claim = $this->declare('RS00000-C02', [
            'settingdetail' => '<script>alert(1)</script>',
            'situation' => '<script>alert(2)</script>',
            'situationformat' => FORMAT_PLAIN,
        ]);
        $claim->fichecode = '<script>alert(3)</script>';

        $html = $this->render(new attest_declaration($claim, $context));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(3)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    /**
     * The warning names the record in a notification of the core, which does not escape its message: the piece does.
     *
     * The declaration of the test above holds its anchor, so that no warning is drawn there: this one is given a divergence.
     */
    public function test_a_script_in_the_code_of_the_record_comes_out_escaped_in_the_warning(): void {
        $claim = $this->declare('RS00000-C02');
        $claim->fichecode = '<script>alert(1)</script>';
        $claim->ficherang = '<b>C02</b>';

        // The two sentences that name the record (the one of a missing anchor does not).
        foreach ([anchor::RENAMED, anchor::UNLINKED] as $divergence) {
            $html = $this->render(new anchor_warning($claim, $divergence));
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;-&lt;b&gt;C02&lt;/b&gt;', $html, $divergence);
            $this->assertStringNotContainsString('<script', $html, $divergence);
            $this->assertStringNotContainsString('<b>', $html, $divergence);
            $this->assertSame(self::canon(self::before_anchor_warning($claim, $divergence)), self::canon($html), $divergence);
        }
    }

    public function test_only_the_keys_of_html_are_placed_as_they_are(): void {
        $allowed = [
            'declaration' => ['situationhtml'],
            'anchor_warning' => ['noticehtml'],
            'attest_declaration' => ['warninghtml', 'declarationhtml'],
        ];
        foreach ($allowed as $name => $keys) {
            $source = file_get_contents(__DIR__ . '/../../templates/' . $name . '.mustache');
            preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
            $this->assertSame($keys, array_values(array_unique($raw[1])), $name);
            $this->assertSame(substr_count($source, '{{{'), count($raw[1]), $name);
        }
    }

    public function test_the_templates_render_their_example_context(): void {
        $expected = [
            'declaration' => 'tool-vae-claim',
            'anchor_warning' => 'alert',
            'attest_declaration' => 'tool-vae-situation',
        ];
        foreach ($expected as $name => $needle) {
            $file = __DIR__ . '/../../templates/' . $name . '.mustache';
            $this->assertFileExists($file);
            $source = file_get_contents($file);
            $this->assertStringContainsString('@template tool_vae/' . $name, $source);
            $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches), $name);
            $context = json_decode($matches[1]);
            $this->assertIsObject($context, $name . ' ' . json_last_error_msg());

            $this->assertStringContainsString($needle, self::core()->render_from_template('tool_vae/' . $name, $context), $name);
        }
    }
}
