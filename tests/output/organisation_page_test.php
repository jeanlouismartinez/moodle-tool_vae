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

use tool_vae\local\organisation;
use tool_vae\local\organisation_screen;
use tool_vae\local\referent_manager;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/organisation_before.php');
require_once(__DIR__ . '/pages_before.php');

/**
 * The page organisation.php, played as the site plays it (tests/fixtures/plugin_pages.php), draws under its heading the DOM
 * the old page drew (lot 7, MMR-520 ticket 4, wave 4b): the old code is kept in the trait pages_before (and the old screen in
 * organisation_before) and run for the same reader on the same data, compared in canonical DOM. "My pathway" with every
 * button and with none, the empty pathway, the followed vaeistes with the filter of a referent (no choice made, a choice
 * made), the tab of the site; the competencies switched off by the class against the old notification (the page ends that
 * way with die(), which a test cannot play).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(organisation_page::class)]
final class organisation_page_test extends \advanced_testcase {
    use dom_canon;
    use organisation_before;
    use pages_before;

    /** @var \stdClass The vaeiste, with a declaration. */
    private $vaeiste;

    /** @var \stdClass A vaeiste without anything. */
    private $empty;

    /** @var \stdClass Follows the vaeiste, referent of a bloc. */
    private $manager;

    /** @var \stdClass A vaeiste who may only read their own pathway. */
    private $reader;

    /**
     * A record with a bloc, a vaeiste who declared, one who did not, a manager who follows both and is referent of BC01;
     * the preparation and the pieces open.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        set_config('piecesopen', 1, 'tool_vae');
        set_config('validationopen', 1, 'tool_vae');
        $generator = $this->getDataGenerator();
        $plugin = $generator->get_plugin_generator('tool_vae');
        $plugin->create_source(['fichecode' => 'RNCP100', 'ficherang' => 'BC01', 'shortname' => 'Bloc & "un"']);
        $plugin->create_competency(['fichecode' => 'RNCP100', 'ficherang' => 'C01', 'shortname' => 'Aider', 'bloc' => 'BC01']);
        $system = \context_system::instance()->id;
        $own = $generator->create_role();
        foreach (['declare', 'viewown', 'draftown', 'positionself'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $own, $system, true);
        }
        $follow = $generator->create_role();
        foreach (['viewassigned', 'attest'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $follow, $system, true);
        }
        set_role_contextlevels($follow, [CONTEXT_USER]);
        $this->vaeiste = $generator->create_user(['firstname' => 'Camille', 'lastname' => 'Martin']);
        $this->empty = $generator->create_user(['firstname' => 'Denis', 'lastname' => 'Vide']);
        $this->manager = $generator->create_user(['firstname' => 'Nadia', 'lastname' => 'Benali']);
        foreach ([$this->vaeiste, $this->empty] as $vaeiste) {
            $context = \context_user::instance($vaeiste->id)->id;
            role_assign($own, $vaeiste->id, $context);
            role_assign($follow, $this->manager->id, $context);
        }
        accesslib_clear_all_caches_for_unit_testing();
        $plugin->create_declaration([
            'userid' => $this->vaeiste->id, 'fichecode' => 'RNCP100', 'ficherang' => 'C01', 'situation' => 'Au lever',
        ]);
        // A vaeiste who may read their own pathway and nothing else: no right to declare, to position or to write.
        $readonly = $generator->create_role();
        assign_capability('tool/vae:viewown', CAP_ALLOW, $readonly, $system, true);
        $this->reader = $generator->create_user(['firstname' => 'Rita', 'lastname' => 'Lecture']);
        role_assign($readonly, $this->reader->id, \context_user::instance($this->reader->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setAdminUser();
        referent_manager::add('RNCP100', 'BC01', (int) $this->manager->id);
        $this->setUser();
        $DB->delete_records('task_adhoc');
    }

    /**
     * The page, played as the site plays it, for the current user.
     *
     * @param array $get The parameters of the address.
     * @return string
     */
    private function page(array $get): string {
        // The page is a script of the site: it reads these as globals.
        global $CFG, $DB, $OUTPUT, $PAGE, $SITE, $USER;

        $_GET = $get;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $PAGE = new \moodle_page();
        ob_start();
        try {
            require_once(__DIR__ . '/../fixtures/plugin_pages.php');
            \tool_vae\fixtures\plugin_pages::run('organisation.php');
        } finally {
            $out = ob_get_clean();
            $_GET = [];
        }

        return $out;
    }

    /**
     * The new page played, then the old code under the heading run for the same reader on what the page reads.
     *
     * @param \stdClass $reader
     * @param string $tab
     * @param string $bloc
     * @return string The old HTML, for the premises.
     */
    private function same(\stdClass $reader, string $tab, string $bloc = ''): string {
        $this->setUser($reader);
        $get = ['tab' => $tab] + ($bloc !== '' ? ['bloc' => $bloc] : []);
        $new = $this->page($get);
        $id = (int) $reader->id;
        $site = $tab === organisation::TAB_SITE ? organisation::site() : null;
        $pathway = $tab === organisation::TAB_MINE ? organisation::for_candidate($id) : null;
        $followed = $tab === organisation::TAB_FOLLOWED ? organisation::followed($id, $bloc) : null;
        $params = ['tab' => $tab];
        if ($followed !== null && $followed->chosen !== '') {
            $params['bloc'] = $followed->chosen;
        }
        $url = new \moodle_url('/admin/tool/vae/organisation.php', $params);
        $old = self::before_organisation_page($tab, $site, $pathway, $followed, $url, $id, \context_user::instance($id));
        // Under PHPUnit the page draws its header and heading as text (the renderer of the command line): what follows the
        // heading is the page under its heading, compared whole.
        $heading = '-->' . get_string('orgtab' . $tab, 'tool_vae');
        $this->assertSame(1, substr_count($new, $heading), $tab . ': premise, one heading.');
        $under = substr($new, strpos($new, $heading) + strlen($heading));
        $this->assertSame(self::canon($old), self::canon($under), $tab);
        $this->assertSame(substr_count($old, 'singlebutton'), substr_count($under, 'singlebutton'), $tab . ': buttons.');

        return $old;
    }

    public function test_my_pathway_draws_the_dom_of_before_with_its_buttons(): void {
        $old = $this->same($this->vaeiste, organisation::TAB_MINE);
        foreach (['declare.php', 'selfposition.php', 'situations.php', 'dossier.php', 'validation.php'] as $page) {
            $this->assertStringContainsString($page, $old, 'Premise: the button ' . $page);
        }
        $this->assertStringContainsString(get_string('orgintrovalidation', 'tool_vae'), $old, 'Premise.');
        $this->assertStringContainsString('tool-vae-guide-link', $old, 'Premise: the link "Help".');
        $this->assertStringContainsString('nav-tabs', $old, 'Premise: the tabs.');

        set_config('validationopen', 0, 'tool_vae');
        $old = $this->same($this->vaeiste, organisation::TAB_MINE);
        $this->assertStringNotContainsString('validation.php', $old, 'Premise: the preparation closed.');
        $this->assertStringNotContainsString(get_string('orgintrovalidation', 'tool_vae'), $old, 'Premise.');
    }

    public function test_a_vaeiste_who_may_only_read_gets_no_button_as_before(): void {
        // Added after the mutants of page that turn the conditions of the buttons to true stayed unseen by the tool (every
        // account it serves on this tab holds the three rights).
        $old = $this->same($this->reader, organisation::TAB_MINE);
        $this->assertSame(0, substr_count($old, 'singlebutton'), 'Premise: no button at all.');
        $this->assertStringContainsString(s(get_string('orgnofiche', 'tool_vae')), $old, 'Premise.');
    }

    public function test_an_empty_pathway_draws_the_dom_of_before(): void {
        $old = $this->same($this->empty, organisation::TAB_MINE);
        $this->assertStringContainsString(s(get_string('orgnofiche', 'tool_vae')), $old, 'Premise.');
        $this->assertStringNotContainsString('dossier.php', $old, 'Premise: no button to a dossier.');
    }

    public function test_a_record_only_a_plan_names_draws_the_dom_of_before_with_the_button_of_the_dossier(): void {
        global $DB;

        // Added by the pilot of wave 4b after his mutants of page stayed green: no state had a pathway with a planned
        // record and no declaration, the one where "nothing" and the button of the dossier each hang on the second term
        // of their condition.
        $core = $this->getDataGenerator()->get_plugin_generator(\tool_vae\local\competency_gateway::SUBSYSTEM);
        $plan = $core->create_plan([
            'userid' => $this->empty->id, 'name' => 'Plan AES', 'status' => \tool_vae\local\competency_gateway::PLAN_ACTIVE,
        ]);
        $core->create_plan_competency([
            'planid' => $plan->get('id'),
            'competencyid' => $DB->get_field('competency', 'id', ['idnumber' => 'RNCP100-C01'], MUST_EXIST),
        ]);

        $old = $this->same($this->empty, organisation::TAB_MINE);
        $this->assertStringContainsString('tool-vae-organisation-planned', $old, 'Premise: a planned record.');
        $this->assertStringNotContainsString(s(get_string('orgnofiche', 'tool_vae')), $old, 'Premise: not "nothing".');
        $this->assertStringContainsString('dossier.php', $old, 'Premise: the button of the dossier.');
        $this->assertStringNotContainsString(s(get_string('orgintromine', 'tool_vae')), $old, 'Premise: no introduction.');
    }

    public function test_the_marks_and_the_right_to_mark_are_drawn_as_before_when_the_site_opens_them(): void {
        // Added by the pilot of wave 4b: the function of the marks was closed in every state, so the box drew nothing and
        // neither its labels nor the right to manage the marks were ever seen.
        set_config('scopeopen', 1, 'tool_vae');
        $id = (int) $this->vaeiste->id;
        $this->setUser($this->vaeiste);
        \tool_vae\local\scope_manager::mark($id, 'RNCP100', 'C01', 'Hors de mon poste');
        $marks = \tool_vae\local\scope_manager::for_candidate($id);
        $this->assertNotSame([], array_filter(\tool_vae\local\scope_manager::labels($marks)), 'Premise: the mark has a label.');

        $old = $this->same($this->vaeiste, organisation::TAB_MINE);
        $this->assertStringContainsString('Hors de mon poste', $old, 'Premise: the mark.');
        $this->assertStringContainsString('scope.php', $old, 'Premise: the vaeiste may manage the marks.');

        // Somebody who may read and not declare: no mark and no right to mark, so no box at all.
        $old = $this->same($this->reader, organisation::TAB_MINE);
        $this->assertStringNotContainsString('scope.php', $old, 'Premise: no right to mark.');
    }

    public function test_the_validation_is_not_offered_to_a_vaeiste_who_may_not_write_as_before(): void {
        // Added by the pilot of wave 4b: every vaeiste with a declaration could write, so the second term of the condition
        // of the preparation of the validation was never false alone.
        $id = (int) $this->vaeiste->id;
        $role = $this->getDataGenerator()->create_role();
        assign_capability('tool/vae:draftown', CAP_PROHIBIT, $role, \context_system::instance()->id, true);
        role_assign($role, $id, \context_user::instance($id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $old = $this->same($this->vaeiste, organisation::TAB_MINE);
        $this->assertStringContainsString(s(get_string('orgintromine', 'tool_vae')), $old, 'Premise: a declaration.');
        $this->assertStringNotContainsString(get_string('orgintrovalidation', 'tool_vae'), $old, 'Premise: no preparation.');
    }

    /**
     * The old code under the heading, with the renderer of the core for the web as $OUTPUT for the time of the call.
     *
     * @param string $tab
     * @param \stdClass|null $site
     * @param \stdClass|null $followed
     * @param \moodle_url|null $url
     * @param int $id
     * @return string
     */
    private static function before_web(string $tab, ?\stdClass $site, ?\stdClass $followed, ?\moodle_url $url, int $id): string {
        global $OUTPUT;

        $kept = $OUTPUT;
        $OUTPUT = self::core();
        try {
            return self::before_organisation_page($tab, $site, null, $followed, $url, $id, \context_user::instance($id));
        } finally {
            $OUTPUT = $kept;
        }
    }

    public function test_the_followed_vaeistes_draw_the_dom_of_before_with_the_filter_of_a_referent(): void {
        // This way out of the page ends with die(), which a test cannot play: the class is compared to the old code, the page
        // itself is served by the tool of equivalence (states followed, followed-bloc) and played by organisation_followed.feature.
        global $PAGE;

        $this->setUser($this->manager);
        $id = (int) $this->manager->id;
        foreach (['' => false, 'RNCP100|BC01' => true] as $bloc => $chosen) {
            $followed = organisation::followed($id, $bloc);
            $url = new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => 'followed']);
            $old = self::before_web('followed', null, $followed, $url, $id);
            $tabs = organisation_screen::tabs(organisation::tabs());
            $new = $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render(organisation_page::followed(
                new organisation_guide_link('followed'),
                $tabs,
                'followed',
                next_actions::manager(\tool_vae\local\next_actions::for_manager(array_keys($followed->candidates))),
                new organisation_followed($followed, $url)
            ));
            $this->assertSame(self::canon($old), self::canon($new), $bloc);
            $this->assertStringContainsString('<select', $old, 'Premise: the filter of a referent.');
            $this->assertSame($chosen, str_contains($old, 'selected="selected"'), 'Premise: a choice made or not.');
            $this->assertStringContainsString('Camille Martin', $old, 'Premise.');
            $this->assertStringContainsString(s(get_string('orgintrofollowed', 'tool_vae')), $old, 'Premise.');
        }
    }

    public function test_the_site_draws_the_dom_of_before(): void {
        // As the followed vaeistes: the page ends this way with die(); the tool of equivalence serves the state site.
        global $PAGE;

        $this->setAdminUser();
        $id = (int) get_admin()->id;
        $site = organisation::site();
        $old = self::before_web('site', $site, null, null, $id);
        $tabs = organisation_screen::tabs(organisation::tabs());
        $new = $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render(
            organisation_page::site(new organisation_guide_link('site'), $tabs, 'site', new organisation_site($site))
        );
        $this->assertSame(self::canon($old), self::canon($new));
        $this->assertStringContainsString('tool-vae-site-fiches', $old, 'Premise.');
        $this->assertStringContainsString(s(get_string('orgintrosite', 'tool_vae')), $old, 'Premise.');
    }

    public function test_the_competencies_switched_off_draw_the_dom_of_before(): void {
        global $PAGE;

        $this->setUser($this->vaeiste);
        $tabs = organisation_screen::tabs(organisation::tabs());
        $new = $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render(
            organisation_page::disabled(new organisation_guide_link('mine'), $tabs, 'mine')
        );
        $old = self::before_organisation_guide_link('mine') . self::core()->tabtree($tabs, 'mine')
            . self::core()->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                \core\output\notification::NOTIFY_ERROR
            );
        $this->assertSame(self::canon($old), self::canon($new));
        $this->assertStringContainsString('alert-danger', $new);
    }
}
