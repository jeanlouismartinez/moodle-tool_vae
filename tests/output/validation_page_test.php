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

use tool_vae\local\attestation_manager;
use tool_vae\local\draft_manager;
use tool_vae\local\validation_catalog;
use tool_vae\local\validation_status;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/whereami_before.php');

/**
 * The preparation of the validation dossier (validation.php) draws, under its title, the DOM the page drew before lot 7
 * (MMR-520 ticket 4, wave 3b): its code from under the heading to the footer, kept here in before(), the panel by the trait
 * whereami_before; for the author and for a reader, the list of the records (none, some) and the preparation of a record
 * by blocs, with no bloc, with too many, with sections written, in progress, and left by a bloc that left the record.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(validation_page::class)]
final class validation_page_test extends \advanced_testcase {
    use dom_canon;
    use whereami_before;

    /** @var \stdClass Declares and writes their preparation. */
    private $vaeiste;

    /** @var \stdClass Follows the vaeiste. */
    private $manager;

    /** @var \tool_vae_generator */
    private $generator;

    /**
     * RNCP100 (BC01 holding C01 and C02, BC02 holding C03) and RS200, a record with no bloc (C21, C22); the people; the
     * preparation open.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        validation_catalog::reset();
        set_config('validationopen', 1, 'tool_vae');
        $datagenerator = $this->getDataGenerator();
        $this->generator = $datagenerator->get_plugin_generator('tool_vae');
        $this->generator->create_source(['fichecode' => 'RNCP100', 'ficherang' => 'BC01', 'shortname' => 'Bloc & "un"']);
        $this->generator->create_competency(['fichecode' => 'RNCP100', 'ficherang' => 'BC02', 'shortname' => 'Bloc 2']);
        foreach ([['C01', 'BC01'], ['C02', 'BC01'], ['C03', 'BC02']] as [$rank, $bloc]) {
            $this->generator->create_competency([
                'fichecode' => 'RNCP100', 'ficherang' => $rank, 'shortname' => 'Competence ' . $rank, 'bloc' => $bloc,
            ]);
        }
        $this->generator->create_source(['fichecode' => 'RS200', 'ficherang' => 'C21', 'shortname' => 'Sans bloc 1']);
        $this->generator->create_competency(['fichecode' => 'RS200', 'ficherang' => 'C22', 'shortname' => 'Sans bloc 2']);

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
     * What validation.php wrote under its heading before lot 7, up to the list of the records or the refusal of a record not
     * offered. The only changes: echo becomes $out .=, and what the page read is given as parameters.
     *
     * @param bool $reading
     * @return string
     */
    private function before_head(bool $reading): string {
        $out = '';
        if ($reading) {
            $out .= \html_writer::div(s(get_string('validationreadnotice', 'tool_vae')), 'alert alert-info', ['role' => 'note']);
        }
        $out .= \html_writer::div(s(get_string('validationlabelling', 'tool_vae')), 'alert alert-info tool-vae-labelling', [
            'role' => 'note',
        ]);
        if (!$reading) {
            $out .= \html_writer::tag('p', \html_writer::link(
                new \moodle_url('/admin/tool/vae/deposit.php', ['doctype' => draft_manager::DOCTYPE_VALIDATION]),
                get_string('validationdepositheading', 'tool_vae')
            ));
        }

        return $out;
    }

    /**
     * What validation.php wrote for the list of the records, before lot 7 ($OUTPUT is self::core()).
     *
     * @param bool $reading
     * @param int $candidateid
     * @param string[] $codes
     * @return string
     */
    private function before_records(bool $reading, int $candidateid, array $codes): string {
        $base = $reading ? ['userid' => $candidateid] : [];
        $dossier = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]);
        $back = $reading ? 'faisabbacktodossier' : 'validationbacktodossier';
        $out = '';
        if (!$reading) {
            $out .= \html_writer::tag('p', s(get_string('validationintro', 'tool_vae')));
        }
        if (!$codes) {
            $none = $reading ? 'validationnodeclaration' : 'validationnofiche';
            $out .= self::core()->notification(get_string($none, 'tool_vae'), \core\output\notification::NOTIFY_INFO);
        } else {
            if (!$reading) {
                $out .= \html_writer::tag('p', s(get_string('validationchoose', 'tool_vae')));
            }
            $items = [];
            foreach ($codes as $code) {
                $items[] = \html_writer::link(
                    new \moodle_url('/admin/tool/vae/validation.php', $base + ['fiche' => $code]),
                    s($code)
                );
            }
            $out .= \html_writer::alist($items);
        }
        $out .= \html_writer::tag('p', \html_writer::link($dossier, get_string($back, 'tool_vae')));

        return $out;
    }

    /**
     * What validation.php wrote for the preparation of a record, before lot 7, from the heading of the record to the way
     * back. The only changes: echo becomes $out .=, $OUTPUT is self::core(), whereami_panel::render() the old code kept in the
     * trait, and the three readings of the page ($written, the status, the declarations) are made here as the page made
     * them, in the same place.
     *
     * @param bool $reading
     * @param int $candidateid
     * @param string $fichecode
     * @return string
     */
    private function before_record(bool $reading, int $candidateid, string $fichecode): string {
        global $DB;

        $base = $reading ? ['userid' => $candidateid] : [];
        $context = \context_user::instance($candidateid);
        $dossier = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]);
        $back = $reading ? 'faisabbacktodossier' : 'validationbacktodossier';
        $renderer = self::core();
        $plugin = $GLOBALS['PAGE']->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL);
        $structure = validation_catalog::structure($fichecode);
        $out = '';

        $out .= self::core()->heading(s($fichecode), 3);
        $out .= \html_writer::tag('p', \html_writer::link(
            new \moodle_url('/admin/tool/vae/validation.php', $base),
            get_string('validationbacktolist', 'tool_vae')
        ));
        if ($structure->overflow) {
            $out .= self::core()->notification(
                get_string('validationoverflow', 'tool_vae', validation_catalog::MAX_BLOCS),
                \core\output\notification::NOTIFY_INFO
            );
        } else if (!$structure->byblocs) {
            $out .= self::core()->notification(get_string('validationflat', 'tool_vae'), \core\output\notification::NOTIFY_INFO);
        }
        $out .= self::before_whereami_render($candidateid, $fichecode, ['link' => true]);

        $written = [];
        foreach (
            $DB->get_records('tool_vae_draft', [
                'candidateid' => $candidateid, 'doctype' => draft_manager::DOCTYPE_VALIDATION, 'fichecode' => $fichecode,
            ]) as $row
        ) {
            $written[$row->section] = $row;
        }
        $status = validation_status::for_record($candidateid, $fichecode);
        $state = function (string $section) use ($status): string {
            return validation_status::words($status[$section] ?? validation_status::place(null));
        };
        $manager = new draft_manager();
        $texts = function (?\stdClass $row) use ($manager, $context): string {
            if (!$row) {
                return '';
            }
            $plain = fn(string $text): string => format_text($text, FORMAT_PLAIN, ['context' => $context, 'filter' => false]);
            $out = '';
            if ((int) $row->currentversion > 0) {
                $latest = $manager->version((int) $row->id, (int) $row->currentversion);
                $out .= \html_writer::div($plain((string) $latest->content), 'mb-2');
            }
            if ($row->working !== null && trim((string) $row->working) !== '') {
                $out .= \html_writer::div(
                    \html_writer::tag('p', s(get_string('situationworkingnotice', 'tool_vae')), [
                        'class' => 'text-muted small mb-1',
                    ])
                        . $plain((string) $row->working),
                    'border-start ps-2 mb-2'
                );
            }

            return $out;
        };
        $line = function (string $section) use ($written, $fichecode, $state, $reading, $texts): string {
            $row = $written[$section] ?? null;
            $label = s(validation_catalog::label($fichecode, $section));
            if (!$reading) {
                $label = \html_writer::link(new \moodle_url('/admin/tool/vae/section.php', [
                    'doctype' => draft_manager::DOCTYPE_VALIDATION, 'fiche' => $fichecode, 'section' => $section,
                ]), $label);
            }
            $out = $label . ' - ' . \html_writer::span(s($state($section)), 'text-muted');
            if ($row && (int) $row->currentversion > 0) {
                $out .= ' - ' . \html_writer::link(
                    new \moodle_url('/admin/tool/vae/history.php', ['draftid' => $row->id]),
                    get_string('historylink', 'tool_vae')
                );
            }
            if ($reading) {
                $out .= $texts($row);
            }

            return \html_writer::tag('li', $out);
        };
        $declarations = function (array $competencyids) use ($DB, $candidateid, $fichecode, $context, $plugin): string {
            $params = ['c' => $candidateid, 'f' => $fichecode];
            $where = 'candidateid = :c AND fichecode = :f';
            if ($competencyids) {
                [$insql, $inparams] = $DB->get_in_or_equal($competencyids, SQL_PARAMS_NAMED);
                $where .= " AND competencyid $insql";
                $params += $inparams;
            }
            $claims = $DB->get_records_select('tool_vae_evidence', $where, $params, 'timecreated ASC, id ASC');
            if (!$claims) {
                return \html_writer::tag('p', s(get_string('validationnodeclaration', 'tool_vae')), ['class' => 'text-muted']);
            }
            $out = '';
            foreach ($claims as $claim) {
                $summary = userdate((int) $claim->periodstart, get_string('strftimedate', 'langconfig')) . ' - '
                    . userdate((int) $claim->periodend, get_string('strftimedate', 'langconfig')) . ' '
                    . $plugin->render(new state_label(attestation_manager::attestations((int) $claim->id)));
                $out .= \html_writer::tag(
                    'details',
                    \html_writer::tag('summary', $summary) . $plugin->render(new declaration($claim, $context)),
                    ['class' => 'mb-2']
                );
            }

            return $out;
        };

        if (!$reading) {
            $out .= \html_writer::tag('p', s(get_string('validationintro', 'tool_vae')));
        }
        $declared = s(get_string($reading ? 'candidatedeclarations' : 'validationdeclarations', 'tool_vae'));
        if ($structure->byblocs) {
            $ranks = array_keys($structure->blocs);
            sort($ranks, SORT_NATURAL);
            foreach ($ranks as $rank) {
                $bloc = $structure->blocs[$rank];
                $out .= $renderer->heading(s(strtoupper((string) $rank) . ' - ' . $bloc->label), 4);
                $out .= \html_writer::tag('h5', $declared, ['class' => 'h6']);
                $out .= $declarations($bloc->competencyids);
                $items = '';
                foreach (validation_catalog::PARTS as $part) {
                    $items .= $line($rank . '_' . $part);
                }
                $out .= \html_writer::tag('ul', $items);
            }
        } else {
            $out .= \html_writer::tag('h5', $declared, ['class' => 'h6']);
            $out .= $declarations([]);
            $items = '';
            foreach (validation_catalog::PARTS as $part) {
                $items .= $line('_' . $part);
            }
            $out .= \html_writer::tag('ul', $items);
        }
        $out .= $renderer->heading(s(get_string('validpart:synthese', 'tool_vae')), 4);
        $out .= \html_writer::tag('ul', $line('_' . validation_catalog::SYNTHESIS));
        $known = array_column(validation_catalog::sections($fichecode), 'section');
        $retired = array_diff_key($written, array_flip($known));
        if ($retired) {
            $out .= $renderer->heading(s(get_string('validationretired', 'tool_vae')), 4);
            if (!$reading) {
                $out .= \html_writer::tag('p', s(get_string('validationretiredhelp', 'tool_vae')));
            }
            $items = '';
            foreach ($retired as $section => $row) {
                $items .= \html_writer::tag('li', s(validation_catalog::label($fichecode, (string) $section))
                    . ' - ' . \html_writer::span(s($state((string) $section)), 'text-muted')
                    . ((int) $row->currentversion > 0 ? ' - ' . \html_writer::link(
                        new \moodle_url('/admin/tool/vae/history.php', ['draftid' => $row->id]),
                        get_string('historylink', 'tool_vae')
                    ) : '')
                    . ($reading ? $texts($row) : ''));
            }
            $out .= \html_writer::tag('ul', $items);
        }
        $out .= \html_writer::tag('p', \html_writer::link($dossier, get_string($back, 'tool_vae')));

        return $out;
    }

    /**
     * The page drawn for a record by the class, with the readings the page makes (copied from validation.php).
     *
     * @param bool $reading
     * @param int $candidateid
     * @param string $fichecode
     * @return string
     */
    private function after_record(bool $reading, int $candidateid, string $fichecode): string {
        global $DB;

        $structure = validation_catalog::structure($fichecode);
        $written = [];
        foreach (
            $DB->get_records('tool_vae_draft', [
                'candidateid' => $candidateid, 'doctype' => draft_manager::DOCTYPE_VALIDATION, 'fichecode' => $fichecode,
            ]) as $row
        ) {
            $written[$row->section] = $row;
        }
        $status = validation_status::for_record($candidateid, $fichecode);
        $claims = [];
        $blocs = $structure->byblocs ? $structure->blocs : ['' => (object) ['competencyids' => []]];
        foreach ($blocs as $rank => $bloc) {
            $params = ['c' => $candidateid, 'f' => $fichecode];
            $where = 'candidateid = :c AND fichecode = :f';
            if ($bloc->competencyids) {
                [$insql, $inparams] = $DB->get_in_or_equal($bloc->competencyids, SQL_PARAMS_NAMED);
                $where .= " AND competencyid $insql";
                $params += $inparams;
            }
            $claims[(string) $rank] = $DB->get_records_select('tool_vae_evidence', $where, $params, 'timecreated ASC, id ASC');
        }

        return $this->render(validation_page::record($reading, $candidateid, $fichecode, $structure, $written, $status, $claims));
    }

    /**
     * A section of the preparation of the vaeiste, opened as section.php opens it.
     *
     * @param string $fichecode
     * @param string $section
     * @return \stdClass
     */
    private function draft(string $fichecode, string $section): \stdClass {
        $this->setUser($this->vaeiste);

        return (new draft_manager())->section((int) $this->vaeiste->id, draft_manager::DOCTYPE_VALIDATION, $section, 0, $fichecode);
    }

    public function test_the_head_and_the_list_draw_the_dom_of_before(): void {
        $id = (int) $this->vaeiste->id;
        foreach ([false, true] as $reading) {
            $this->setUser($reading ? $this->manager : $this->vaeiste);
            $this->assertSame(
                self::canon($this->before_head($reading)),
                self::canon($this->render(validation_page::head($reading))),
                $reading ? 'reader' : 'author'
            );
            $this->assertSame($reading, str_contains($this->before_head($reading), 'validationreadnotice') ||
                str_contains($this->before_head($reading), s(get_string('validationreadnotice', 'tool_vae'))));
            foreach ([[], ['RNCP100', 'RS200'], ['0']] as $codes) {
                $before = $this->before_records($reading, $id, $codes);
                $after = $this->render(validation_page::records($reading, $id, $codes));
                $this->assertSame(self::canon($before), self::canon($after), implode(',', $codes));
                $this->assertSame($codes === [], str_contains($before, 'alert-info'), 'The premise: the notice "none".');
                $this->assertSame($reading && $codes !== [], str_contains($before, 'validation.php?userid=' . $id), 'Who stays.');
                $this->assertSame(count($codes), substr_count($after, '<li>'));
                if ($codes === ['0']) {
                    $this->assertStringContainsString('>0</a>', $after);
                }
            }
        }
    }

    public function test_the_preparation_of_a_record_draws_the_dom_of_before_for_the_author_and_a_reader(): void {
        global $DB;

        $id = (int) $this->vaeiste->id;
        foreach ([['RNCP100', 'C01'], ['RNCP100', 'C03'], ['RNCP100', 'C03'], ['RS200', 'C22']] as $n => [$fiche, $rank]) {
            $this->generator->create_declaration([
                'userid' => $id, 'fichecode' => $fiche, 'ficherang' => $rank, 'situation' => 'Declaration ' . $n . ' & "co".',
            ]);
        }
        $manager = new draft_manager();
        $described = $this->draft('RNCP100', 'bc01_decrire');
        // Two versions: a reader reads the latest one, never an earlier one.
        $manager->save_version((int) $described->id, 'Une version plus ancienne.');
        $manager->save_version((int) $described->id, "Le texte & \"la\" version.\n<b>gras</b>");
        $this->assertSame(2, (int) $DB->get_field('tool_vae_draft', 'currentversion', ['id' => (int) $described->id]));
        $manager->save_working((int) $described->id, 'Le texte en cours.');
        $linked = $this->draft('RNCP100', 'bc02_lier');
        $manager->save_working((int) $linked->id, '   ');
        $synthesis = $this->draft('RNCP100', '_synthese');
        $manager->save_version((int) $synthesis->id, '0');
        $flat = $this->draft('RS200', '_decrire');
        $manager->save_version((int) $flat->id, 'Sans bloc.');
        // A section written for a bloc that left the record since.
        $old = $this->draft('RNCP100', 'bc01_lier');
        $manager->save_version((int) $old->id, 'Pour un bloc parti.');
        $DB->set_field('tool_vae_draft', 'section', 'bc09_lier', ['id' => (int) $old->id]);
        $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP100', 'tags' => 'C02']);

        foreach (['author' => $this->vaeiste, 'reader' => $this->manager] as $who => $reader) {
            $reading = $who === 'reader';
            foreach (['RNCP100', 'RS200'] as $fiche) {
                $this->setUser($reader);
                validation_catalog::reset();
                $before = $this->before_record($reading, $id, $fiche);
                $after = $this->after_record($reading, $id, $fiche);
                $this->assertSame(self::canon($before), self::canon($after), "$who, $fiche");

                // Premises: each part drawn by the old code.
                $this->assertStringContainsString('tool-vae-whereami', $before, "$who, $fiche: the panel");
                $this->assertSame($reading, !str_contains($before, 'section.php'), "$who, $fiche: the ways to write");
                $this->assertSame(!$reading, str_contains($before, s(get_string('validationintro', 'tool_vae'))));
                $this->assertStringContainsString('<details', $before, "$who, $fiche: a declaration");
                if ($fiche === 'RNCP100') {
                    $this->assertSame(3, substr_count($before, '<details'), "$who: two on BC02, one on BC01");
                    $this->assertStringContainsString(s(get_string('validationretired', 'tool_vae')), $before);
                    $this->assertSame(!$reading, str_contains($before, s(get_string('validationretiredhelp', 'tool_vae'))));
                    $this->assertStringContainsString('Bloc &amp; &quot;un&quot;', $before);
                    $this->assertStringNotContainsString('&amp;amp;', $after);
                    $this->assertSame(3, substr_count($before, 'history.php'), "$who: three versions");
                    $this->assertSame($reading, str_contains($before, 'Le texte &amp; &quot;la&quot; version.'), 'The latest.');
                    $this->assertStringNotContainsString('Une version plus ancienne.', $before, "$who: never an earlier one");
                    $this->assertSame($reading, str_contains($before, s(get_string('situationworkingnotice', 'tool_vae'))));
                    $this->assertSame($reading, str_contains($before, 'border-start'), 'Blanks are no draft in progress.');
                    $this->assertSame($reading ? 1 : 0, substr_count($before, 'border-start'));
                    $this->assertStringNotContainsString(s(get_string('validationflat', 'tool_vae')), $before);
                } else {
                    $this->assertStringContainsString(s(get_string('validationflat', 'tool_vae')), $before);
                    $this->assertSame(1, substr_count($before, '<details'));
                }
            }
        }
    }

    public function test_a_record_with_too_many_blocs_and_a_bloc_with_no_declaration_draw_the_dom_of_before(): void {
        $id = (int) $this->vaeiste->id;
        $this->generator->create_source(['fichecode' => 'RNCP900', 'ficherang' => 'BC01', 'shortname' => 'Bloc 1']);
        for ($n = 2; $n <= validation_catalog::MAX_BLOCS + 1; $n++) {
            $this->generator->create_competency([
                'fichecode' => 'RNCP900', 'ficherang' => sprintf('BC%02d', $n), 'shortname' => 'Bloc ' . $n,
            ]);
            // A bloc is one that holds a competency.
            $this->generator->create_competency([
                'fichecode' => 'RNCP900', 'ficherang' => sprintf('C%02d', $n), 'shortname' => 'C' . $n,
                'bloc' => sprintf('BC%02d', $n),
            ]);
        }
        $this->generator->create_competency(['fichecode' => 'RNCP900', 'ficherang' => 'C01', 'shortname' => 'C', 'bloc' => 'BC01']);
        $this->generator->create_declaration([
            'userid' => $id, 'fichecode' => 'RNCP900', 'ficherang' => 'C01', 'situation' => 'Une.',
        ]);
        $this->generator->create_declaration([
            'userid' => $id, 'fichecode' => 'RNCP100', 'ficherang' => 'C01', 'situation' => 'Une.',
        ]);
        foreach ([false, true] as $reading) {
            $this->setUser($reading ? $this->manager : $this->vaeiste);
            validation_catalog::reset();
            $before = $this->before_record($reading, $id, 'RNCP900');
            $this->assertSame(self::canon($before), self::canon($this->after_record($reading, $id, 'RNCP900')));
            $overflow = s(get_string('validationoverflow', 'tool_vae', validation_catalog::MAX_BLOCS));
            $this->assertStringContainsString($overflow, $before);
            // On RNCP100, BC02 has no declaration: the sentence that says so.
            $before = $this->before_record($reading, $id, 'RNCP100');
            $this->assertSame(self::canon($before), self::canon($this->after_record($reading, $id, 'RNCP100')));
            $none = '<p class="text-muted">' . s(get_string('validationnodeclaration', 'tool_vae'));
            $this->assertStringContainsString($none, $before);
        }
    }

    public function test_the_templates_place_html_only_under_the_keys_named(): void {
        global $CFG;

        $template = file_get_contents($CFG->dirroot . '/admin/tool/vae/templates/validation_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $template, $keys);
        $found = array_values(array_unique($keys[1]));
        sort($found);
        $expected = ['declarationhtml', 'headinghtml', 'latesthtml', 'nonehtml', 'noticehtml', 'panelhtml', 'statehtml',
            'workinghtml'];
        $this->assertSame($expected, $found);
    }
}
