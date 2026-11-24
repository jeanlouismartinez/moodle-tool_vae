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

use tool_vae\local\draft_manager;
use tool_vae\local\feasibility_catalog;
use tool_vae\local\feasibility_fields;
use tool_vae\local\validation_catalog;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/whereami_before.php');

/**
 * The page that writes one section (section.php) draws, under its title, the DOM the page drew before lot 7 (MMR-520 ticket
 * 4, wave 3b): its code from under the heading to the footer, kept here in before(), the situations of the part "lier" by
 * the trait whereami_before; in its three states, and for the text, each document (dossier de faisabilité, preparation,
 * private journal), with and without a conflict, a version, a draft saved by the autosave.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(section_page::class)]
final class section_page_test extends \advanced_testcase {
    use dom_canon;
    use whereami_before;

    /** @var \stdClass The writer. */
    private $vaeiste;

    /** @var \tool_vae_generator */
    private $generator;

    /**
     * RNCP100 (BC01 holding C01 and C02, BC02 holding C03); the writer; the preparation open.
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
        $system = \context_system::instance()->id;
        $this->vaeiste = $datagenerator->create_user(['firstname' => 'Camille', 'lastname' => 'Vaeiste']);
        $own = $datagenerator->create_role();
        foreach (['declare', 'viewown', 'positionself', 'draftown'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $own, $system, true);
        }
        set_role_contextlevels($own, [CONTEXT_USER]);
        role_assign($own, $this->vaeiste->id, \context_user::instance($this->vaeiste->id)->id);
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
     * A form, new each time (a form is drawn once).
     *
     * @return \moodleform
     */
    private static function form(): \moodleform {
        return new \tool_vae\form\section_form((new \moodle_url('/admin/tool/vae/section.php'))->out(false), ['aiurl' => null]);
    }

    /**
     * What section.php wrote for the contact details under its heading, before lot 7. The only changes: echo becomes
     * $out .=, $USER->id is $userid, and the values read from the account are given.
     *
     * @param string $sectioncode
     * @param array $shown
     * @param int $userid
     * @return string
     */
    private function before_account(string $sectioncode, array $shown, int $userid): string {
        $out = '';
        $help = feasibility_catalog::help($sectioncode);
        if ($help !== null) {
            $out .= \html_writer::tag('p', s($help), ['class' => 'tool-vae-section-help']);
        }
        $out .= \html_writer::start_tag('dl', ['class' => 'row']);
        foreach (feasibility_fields::account_keys()[$sectioncode] as $key) {
            $out .= \html_writer::tag('dt', s(get_string('faisabaccount:' . $key, 'tool_vae')), ['class' => 'col-sm-3']);
            $out .= \html_writer::tag(
                'dd',
                s($shown[$key] ?? get_string('faisabnotfilled', 'tool_vae')),
                ['class' => 'col-sm-9']
            );
        }
        $out .= \html_writer::end_tag('dl');
        $out .= \html_writer::tag('p', \html_writer::link(
            new \moodle_url('/user/edit.php', ['id' => $userid]),
            get_string('faisabeditprofile', 'tool_vae')
        ));
        $out .= \html_writer::tag('p', \html_writer::link(
            new \moodle_url('/admin/tool/vae/faisabilite.php'),
            get_string('faisabbacktolist', 'tool_vae')
        ));

        return $out;
    }

    /**
     * What section.php wrote for a section of separate fields under its heading, before lot 7 (echo becomes $out .=,
     * $fieldsform->display() $out .= $form->render()).
     *
     * @param string $sectioncode
     * @param \stdClass $draft
     * @param \moodle_url $history
     * @param \moodleform $form
     * @return string
     */
    private function before_fields(string $sectioncode, \stdClass $draft, \moodle_url $history, \moodleform $form): string {
        $out = '';
        $help = feasibility_catalog::help($sectioncode);
        if ($help !== null) {
            $out .= \html_writer::tag('p', s($help), ['class' => 'tool-vae-section-help']);
        }
        $out .= \html_writer::tag('p', s((int) $draft->currentversion > 0
            ? get_string('sectioncurrentversion', 'tool_vae', (int) $draft->currentversion)
            : get_string('sectionnoversionfields', 'tool_vae')));
        $out .= \html_writer::tag('p', \html_writer::link($history, get_string('historylink', 'tool_vae')));
        $out .= \html_writer::tag('p', \html_writer::link(
            new \moodle_url('/admin/tool/vae/faisabilite.php'),
            get_string('faisabbacktolist', 'tool_vae')
        ));
        $out .= $form->render();

        return $out;
    }

    /**
     * What section.php wrote for a section of free text under its heading, before lot 7. The only changes: echo becomes
     * $out .=, $OUTPUT is self::core(), $form->display() $out .= $form->render(), whereami_panel::tagged_on_bloc() the old
     * code kept in the trait, and what the page computed is given as parameters.
     *
     * @param string $doctype
     * @param string $sectioncode
     * @param string $fichecode
     * @param int $candidateid
     * @param \stdClass $draft
     * @param bool $conflict
     * @param \moodle_url $url
     * @param \moodle_url $history
     * @param \moodle_url $backurl
     * @param string $backwords
     * @param \moodleform $form
     * @return string
     */
    private function before_text(
        string $doctype,
        string $sectioncode,
        string $fichecode,
        int $candidateid,
        \stdClass $draft,
        bool $conflict,
        \moodle_url $url,
        \moodle_url $history,
        \moodle_url $backurl,
        string $backwords,
        \moodleform $form
    ): string {
        $isvalidation = $doctype === draft_manager::DOCTYPE_VALIDATION;
        $isreflexion = $doctype === draft_manager::DOCTYPE_REFLEXION;
        $out = '';
        if ($conflict) {
            $out .= self::core()->notification(
                get_string('errordraftconflict', 'tool_vae'),
                \core\output\notification::NOTIFY_ERROR
            );
            $out .= \html_writer::tag('p', s(get_string('draftconflicttextkept', 'tool_vae')));
            $out .= \html_writer::tag('p', \html_writer::link($url, get_string('draftconflictreload', 'tool_vae'))
                . ' - ' . \html_writer::link($history, get_string('historylink', 'tool_vae')));
        }
        if ($isvalidation) {
            $out .= \html_writer::div(
                s(get_string('validationlabelling', 'tool_vae')),
                'alert alert-info tool-vae-labelling',
                ['role' => 'note']
            );
        }
        if ($isreflexion) {
            $out .= \html_writer::div(s(get_string('reflexionprivate', 'tool_vae')), 'alert alert-info tool-vae-labelling', [
                'role' => 'note',
            ]);
            $out .= \html_writer::div(s(get_string('reflexionconsigne', 'tool_vae')), 'alert alert-warning tool-vae-anonymise', [
                'role' => 'note',
            ]);
            $out .= \html_writer::tag('p', s(get_string('reflexionprompts', 'tool_vae')), ['class' => 'tool-vae-section-help']);
        }
        $help = $doctype === draft_manager::DOCTYPE_FAISABILITE
            ? feasibility_catalog::help($sectioncode)
            : ($isvalidation ? validation_catalog::help($sectioncode) : null);
        if ($help !== null) {
            $out .= \html_writer::tag('p', s($help), ['class' => 'tool-vae-section-help']);
        }
        if ($isvalidation && validation_catalog::describes_situations($sectioncode)) {
            $out .= \html_writer::div(
                s(get_string('validationconsigne', 'tool_vae')),
                'alert alert-warning tool-vae-anonymise',
                ['role' => 'note']
            );
        }
        $status = (int) $draft->currentversion > 0
            ? get_string('sectioncurrentversion', 'tool_vae', (int) $draft->currentversion)
            : get_string('sectionnoversion', 'tool_vae');
        $out .= \html_writer::tag('p', s($status));
        $out .= \html_writer::tag('p', \html_writer::link($history, get_string('historylink', 'tool_vae')));
        $out .= \html_writer::tag('p', \html_writer::link($backurl, $backwords));
        $parsed = $isvalidation ? validation_catalog::parse($sectioncode) : null;
        if ($parsed !== null && $parsed[1] === 'lier') {
            $out .= self::before_whereami_tagged_on_bloc($candidateid, $fichecode, $parsed[0]);
        }
        $out .= $form->render();
        $out .= \html_writer::div(
            $draft->workingat && !$conflict ? s(get_string('draftautosaved', 'tool_vae', userdate((int) $draft->workingat))) : '',
            'text-muted small',
            ['id' => 'tool-vae-autosave', 'role' => 'status', 'aria-live' => 'polite']
        );

        return $out;
    }

    public function test_the_contact_details_draw_the_dom_of_before(): void {
        $id = (int) $this->vaeiste->id;
        $cases = [
            'every value' => ['email' => 'c@example.com', 'address' => '1 rue & "Bis"', 'city' => 'Lyon', 'country' => 'France',
                'phone' => '0'],
            'none filled' => [],
            'some filled' => ['email' => 'c@example.com', 'city' => '<script>alert(1)</script>'],
        ];
        foreach ($cases as $name => $shown) {
            $before = $this->before_account('contact', $shown, $id);
            $after = $this->render(section_page::account('contact', $shown, $id));
            $this->assertSame(self::canon($before), self::canon($after), $name);
            $this->assertSame(5 - count($shown), substr_count($before, s(get_string('faisabnotfilled', 'tool_vae'))), $name);
            $this->assertStringNotContainsString('<script', $after, $name);
            $this->assertStringNotContainsString('&amp;amp;', $after, $name);
        }
        $this->assertStringContainsString(
            '<dd class="col-sm-9">0</dd>',
            $this->before_account('contact', $cases['every value'], $id)
        );
        $this->assertStringContainsString('user/edit.php?id=' . $id, $after);
        // Identity: two keys from the account.
        $before = $this->before_account('identite', ['prenoms' => 'Camille'], $id);
        $after = $this->render(section_page::account('identite', ['prenoms' => 'Camille'], $id));
        $this->assertSame(self::canon($before), self::canon($after));
    }

    public function test_a_section_of_separate_fields_draws_the_dom_of_before(): void {
        $history = new \moodle_url('/admin/tool/vae/history.php', ['draftid' => 12]);
        foreach (['identite' => 0, 'statut' => 3] as $section => $version) {
            $draft = (object) ['id' => 12, 'currentversion' => $version, 'workingat' => null];
            $before = $this->before_fields($section, $draft, $history, self::form());
            $after = $this->render(section_page::fields($section, $draft, $history, self::form()));
            $this->assertSame(self::canon($before), self::canon($after), $section);
            $this->assertSame($version > 0, str_contains($before, s(get_string('sectioncurrentversion', 'tool_vae', $version))));
            $this->assertStringContainsString('history.php?draftid=12', $after);
            $this->assertStringContainsString('<form', $after);
        }
    }

    public function test_a_section_of_free_text_draws_the_dom_of_before_in_each_document_and_state(): void {
        $id = (int) $this->vaeiste->id;
        $start = strtotime('2026-01-05 00:00:00 UTC');
        $this->generator->create_situation([
            'userid' => $id, 'fichecode' => 'RNCP100', 'tags' => 'C01', 'title' => 'Sur C01', 'periodstart' => $start,
        ]);
        $history = new \moodle_url('/admin/tool/vae/history.php', ['draftid' => 21]);
        $faisab = new \moodle_url('/admin/tool/vae/faisabilite.php');
        $prep = new \moodle_url('/admin/tool/vae/validation.php', ['fiche' => 'RNCP100']);
        $journal = new \moodle_url('/admin/tool/vae/journal.php');
        $cases = [
            'faisabilite, nothing yet' => ['faisabilite', 'objectifs', '', 0, null, false, $faisab, 'faisabbacktolist'],
            'faisabilite, a version, a draft saved' => ['faisabilite', 'experiences', '', 2, 1767225600, false, $faisab,
                'faisabbacktolist'],
            'faisabilite, a conflict' => ['faisabilite', 'experiences', '', 2, 1767225600, true, $faisab, 'faisabbacktolist'],
            'preparation, describe' => ['validation', 'bc01_decrire', 'RNCP100', 1, null, false, $prep, 'validationbacktofiche'],
            'preparation, link, situations' => ['validation', 'bc01_lier', 'RNCP100', 0, 1767225600, false, $prep,
                'validationbacktofiche'],
            'preparation, link, none' => ['validation', 'bc02_lier', 'RNCP100', 0, null, false, $prep, 'validationbacktofiche'],
            'preparation, synthesis, conflict' => ['validation', '_synthese', 'RNCP100', 4, 1767225600, true, $prep,
                'validationbacktofiche'],
            'journal' => ['reflexion', 'entree', '', 1, 1767225600, false, $journal, 'reflexionbacktojournal'],
        ];
        foreach ($cases as $name => [$doctype, $section, $fiche, $version, $workingat, $conflict, $back, $words]) {
            $draft = (object) ['id' => 21, 'currentversion' => $version, 'workingat' => $workingat];
            $url = new \moodle_url('/admin/tool/vae/section.php', ['doctype' => $doctype, 'section' => $section, 'slot' => 0]);
            $backwords = get_string($words, 'tool_vae');
            $given = [$doctype, $section, $fiche, $id, $draft, $conflict, $url, $history, $back, $backwords, self::form()];
            $before = $this->before_text(...$given);
            $given[10] = self::form();
            $after = $this->render(section_page::text(...$given));
            $this->assertSame(self::canon($before), self::canon($after), $name);

            // Premises: each part drawn by the old code in the state that has it.
            $this->assertSame($conflict, str_contains($before, 'alert-danger'), $name);
            $this->assertSame($conflict, str_contains($before, s(get_string('draftconflictreload', 'tool_vae'))), $name);
            $this->assertSame($doctype === 'validation', str_contains($before, 'tool-vae-labelling') && !str_contains(
                $before,
                s(get_string('reflexionprivate', 'tool_vae'))
            ), $name);
            $this->assertSame($doctype === 'reflexion', str_contains($before, s(get_string('reflexionprivate', 'tool_vae'))));
            $this->assertSame($section === 'bc01_decrire', str_contains($before, s(get_string('validationconsigne', 'tool_vae'))));
            $this->assertSame(str_ends_with($section, '_lier'), str_contains($before, 'tool-vae-linksituations'), $name);
            $this->assertSame($section === 'bc01_lier', str_contains($before, 'Sur C01'), $name);
            $this->assertSame($workingat !== null && !$conflict, str_contains($before, s(get_string(
                'draftautosaved',
                'tool_vae',
                userdate((int) $workingat)
            ))), $name);
            $helped = $doctype === 'validation' || feasibility_catalog::help($section) !== null;
            // The prompts of the journal carry the class of a help too.
            $this->assertSame($doctype === 'reflexion' || $helped, str_contains($before, 'tool-vae-section-help'), $name);
            $this->assertStringContainsString('>' . s($backwords) . '</a>', $after, $name);
            $this->assertStringContainsString('id="tool-vae-autosave"', $after, $name);
        }
    }

    public function test_the_template_places_html_only_under_the_keys_named(): void {
        global $CFG;

        $template = file_get_contents($CFG->dirroot . '/admin/tool/vae/templates/section_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $template, $keys);
        $found = array_values(array_unique($keys[1]));
        sort($found);
        $this->assertSame(['formhtml', 'noticehtml', 'taggedhtml'], $found);
    }
}
