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

namespace tool_vae;

use tool_vae\local\actors;
use tool_vae\local\competency_gateway;
use tool_vae\local\piece_manager;
use tool_vae\local\role_installer;
use tool_vae\local\setup_checklist;

/**
 * The steps of the setting up of the plugin, as the page "Mise en route" lists them (lot S1).
 *
 * WHAT IS ASSERTED. That the steps come in the order of the installation guide; that each state is a fact the plugin reads
 * (competencies, cron, base roles, the expected rights some role gives, an imported record whose framework still exists, the
 * antivirus), and that what it cannot read (roles held, cohorts, the policy of the site) is never "done"; that the rights step
 * names only the expected rights no role gives; that the antivirus step is there only while adding pieces is open; that
 * reading the steps writes nothing.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(setup_checklist::class)]
final class setup_checklist_test extends \advanced_testcase {
    /**
     * The step of this key, or null when the list does not hold it.
     *
     * @param string $key
     * @param int|null $now
     * @return \stdClass|null
     */
    private function step(string $key, ?int $now = null): ?\stdClass {
        foreach (setup_checklist::steps($now) as $step) {
            if ($step->key === $key) {
                return $step;
            }
        }

        return null;
    }

    /**
     * An imported record, written by the generator of the plugin.
     *
     * @param string $fichecode
     */
    private function record(string $fichecode): void {
        $this->getDataGenerator()->get_plugin_generator('tool_vae')->create_source(['fichecode' => $fichecode]);
    }

    public function test_the_steps_come_in_the_order_of_the_guide(): void {
        $this->resetAfterTest();
        set_config('piecesopen', 0, 'tool_vae');

        $steps = setup_checklist::steps();

        $this->assertSame(
            ['competencies', 'cron', 'roles', 'rights', 'assignments', 'record', 'policy'],
            array_column($steps, 'key')
        );
        foreach ($steps as $step) {
            $this->assertContains($step->state, [setup_checklist::DONE, setup_checklist::TODO, setup_checklist::CHECK]);
            $this->assertIsArray($step->details);
            $this->assertIsArray($step->guide);
        }
        $this->assertSame([4], $this->step('competencies')->guide);
        $this->assertSame([6, 7], $this->step('assignments')->guide);
        $expected = [
            'competencies' => ['/admin/settings.php?section=optionalsubsystems', 'moodle/site:config'],
            'cron' => ['/admin/tool/task/scheduledtasks.php', 'moodle/site:config'],
            'roles' => ['/admin/tool/vae/roles_install.php', 'moodle/role:manage'],
            'record' => ['/admin/tool/vae/import_fiches.php', 'tool/vae:import'],
            'policy' => ['/admin/settings.php?section=policysettings', 'moodle/site:config'],
        ];
        foreach ($expected as $key => [$url, $capability]) {
            $this->assertSame($url, $this->step($key)->url->out_as_local_url(false), $key);
            $this->assertSame($capability, $this->step($key)->capability, $key);
        }
    }

    public function test_competencies_on_and_off(): void {
        $this->resetAfterTest();

        set_config('enabled', 1, competency_gateway::SUBSYSTEM);
        $this->assertSame(setup_checklist::DONE, $this->step('competencies')->state);

        set_config('enabled', 0, competency_gateway::SUBSYSTEM);
        $this->assertSame(setup_checklist::TODO, $this->step('competencies')->state);
        $this->assertSame(
            ['competencies', 'cron', 'roles', 'rights', 'assignments', 'record', 'policy'],
            array_column(setup_checklist::steps(), 'key')
        );
    }

    public function test_cron_recent_late_and_never(): void {
        $this->resetAfterTest();
        $now = 1800000000;

        set_config('lastcronstart', $now - 3600, 'tool_task');
        $step = $this->step('cron', $now);
        $this->assertSame(setup_checklist::DONE, $step->state);
        $this->assertSame([], $step->details);

        set_config('lastcronstart', $now - DAYSECS - 1, 'tool_task');
        $step = $this->step('cron', $now);
        $this->assertSame(setup_checklist::TODO, $step->state);
        $this->assertSame(get_string('setupcronlate', 'tool_vae'), $step->details[0]);

        unset_config('lastcronstart', 'tool_task');
        $step = $this->step('cron', $now);
        $this->assertSame(setup_checklist::TODO, $step->state);
        $this->assertSame(get_string('setupcronnever', 'tool_vae'), $step->details[0]);
    }

    public function test_base_roles_all_present_and_one_missing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $note = get_string('setuprolesnote', 'tool_vae');

        $step = $this->step('roles');
        $this->assertSame(setup_checklist::TODO, $step->state);
        foreach (['vaeiste', 'vae_responsable', 'vae_direction'] as $shortname) {
            $this->assertStringContainsString($shortname, $step->details[0]);
        }
        $this->assertSame($note, end($step->details));

        role_installer::create('vaeiste');
        role_installer::create('vae_responsable');
        $step = $this->step('roles');
        $this->assertSame(setup_checklist::TODO, $step->state);
        $this->assertStringContainsString('vae_direction', $step->details[0]);
        $this->assertStringNotContainsString('vae_responsable', $step->details[0]);
        $this->assertSame($note, end($step->details));

        role_installer::create('vae_direction');
        $step = $this->step('roles');
        $this->assertSame(setup_checklist::DONE, $step->state);
        $this->assertSame($note, end($step->details));
    }

    /**
     * The three base roles, created from their presets.
     */
    private function create_base_roles(): void {
        foreach (role_installer::BASE as $shortname) {
            role_installer::create($shortname);
        }
    }

    /**
     * The sentences of the rights step that name an actor: every detail but the note, and but the pointer to the roles.
     *
     * @param \stdClass $step
     * @return string[]
     */
    private function actor_sentences(\stdClass $step): array {
        $skip = [get_string('setuprightsnote', 'tool_vae'), get_string('setuprightsfromroles', 'tool_vae')];

        return array_values(array_filter($step->details, fn(string $detail): bool => !in_array($detail, $skip, true)));
    }

    public function test_no_right_is_missing_once_the_three_roles_exist(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_base_roles();

        $step = $this->step('rights');
        $this->assertSame(setup_checklist::DONE, $step->state);
        $this->assertSame([get_string('setuprightsnote', 'tool_vae')], $step->details);
        $this->assertSame([6], $step->guide);
        $this->assertSame('/admin/tool/vae/organisation.php?tab=site', $step->url->out_as_local_url(false));
        $this->assertSame('tool/vae:viewany', $step->capability);
    }

    public function test_a_missing_right_is_named_and_alone_listed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_base_roles();
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'vae_responsable'], MUST_EXIST);
        unassign_capability('tool/vae:attest', $roleid);
        unassign_capability('tool/vae:writeopinion', $roleid);

        $step = $this->step('rights');
        $this->assertSame(setup_checklist::TODO, $step->state);
        $sentences = $this->actor_sentences($step);
        $this->assertCount(1, $sentences);
        $this->assertStringContainsString(get_string('orgactorresponsable', 'tool_vae'), $sentences[0]);
        $this->assertStringContainsString(get_string('vae:attest', 'tool_vae'), $sentences[0]);
        $this->assertStringContainsString(get_string('vae:writeopinion', 'tool_vae'), $sentences[0]);
        $this->assertSame(get_string('setuprightsactor', 'tool_vae', (object) [
            'actor' => get_string('orgactorresponsable', 'tool_vae'),
            'rights' => get_string('vae:attest', 'tool_vae') . '; ' . get_string('vae:writeopinion', 'tool_vae'),
        ]), $sentences[0]);
        $this->assertStringNotContainsString(get_string('vae:viewassigned', 'tool_vae'), $sentences[0]);
        $this->assertNotContains(get_string('setuprightsfromroles', 'tool_vae'), $step->details);
        $this->assertSame(get_string('setuprightsnote', 'tool_vae'), end($step->details));
    }

    public function test_an_optional_right_nobody_gives_is_not_listed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_base_roles();
        $read = \tool_vae\local\organisation::role_rights();
        $states = [];
        foreach (actors::grid($read->capabilities, $read->settings) as $actor) {
            foreach ($actor->rights as $right) {
                $states[$right->name] = $right->state;
            }
        }
        $this->assertSame(actors::FREE, $states['tool/vae:evaluate']);
        $this->assertSame(actors::FREE, $states['tool/vae:certify']);

        $step = $this->step('rights');
        $this->assertSame(setup_checklist::DONE, $step->state);
        foreach ($step->details as $detail) {
            $this->assertStringNotContainsString(get_string('vae:evaluate', 'tool_vae'), $detail);
            $this->assertStringNotContainsString(get_string('vae:certify', 'tool_vae'), $detail);
        }
    }

    public function test_missing_rights_point_to_the_missing_roles_first(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $step = $this->step('rights');
        $this->assertSame(setup_checklist::TODO, $step->state);
        $this->assertSame(get_string('setuprightsfromroles', 'tool_vae'), $step->details[0]);
        $this->assertCount(5, $step->details);
        $sentences = $this->actor_sentences($step);
        $this->assertCount(3, $sentences);
        $this->assertStringContainsString(get_string('orgactorvaeiste', 'tool_vae'), $sentences[0]);
        $this->assertStringContainsString(get_string('orgactorresponsable', 'tool_vae'), $sentences[1]);
        $this->assertStringContainsString(get_string('orgactordirection', 'tool_vae'), $sentences[2]);
        $this->assertSame(get_string('setuprightsnote', 'tool_vae'), end($step->details));
    }

    public function test_roles_made_by_hand_leave_the_roles_step_to_do_and_the_rights_step_done(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roleid = create_role('Fait main', 'fait_main', '');
        $systemid = \context_system::instance()->id;
        foreach (actors::catalogue() as $actor) {
            foreach ($actor['rights'] as $short => $status) {
                if ($status === actors::EXPECTED) {
                    assign_capability('tool/vae:' . $short, CAP_ALLOW, $roleid, $systemid, true);
                }
            }
        }

        $this->assertSame(setup_checklist::TODO, $this->step('roles')->state);
        $step = $this->step('rights');
        $this->assertSame(setup_checklist::DONE, $step->state);
        $this->assertNotContains(get_string('setuprightsfromroles', 'tool_vae'), $step->details);
    }

    public function test_assignments_and_policy_are_never_done(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        foreach ([false, true] as $equipped) {
            if ($equipped) {
                foreach (role_installer::BASE as $shortname) {
                    role_installer::create($shortname);
                }
                $this->record('RNCP36832');
            }
            $this->assertSame(setup_checklist::CHECK, $this->step('assignments')->state);
            $this->assertSame(setup_checklist::CHECK, $this->step('policy')->state);
            $this->assertNull($this->step('assignments')->url);
            $this->assertNull($this->step('assignments')->capability);
        }
    }

    public function test_a_record_counts_only_while_its_framework_exists(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $step = $this->step('record');
        $this->assertSame(setup_checklist::TODO, $step->state);
        $this->assertSame(get_string('setuprecordnone', 'tool_vae'), $step->details[0]);

        $this->record('RNCP36832');
        $this->assertSame(setup_checklist::DONE, $this->step('record')->state);

        $DB->set_field('tool_vae_source', 'frameworkid', 0, ['fichecode' => 'RNCP36832']);
        $step = $this->step('record');
        $this->assertSame(setup_checklist::TODO, $step->state);
        $this->assertStringContainsString('RNCP36832', $step->details[0]);
    }

    public function test_a_record_whose_framework_is_gone_does_not_count_next_to_a_valid_one(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->record('RNCP36832');
        $this->record('RNCP35993');
        $DB->set_field('tool_vae_source', 'frameworkid', 0, ['fichecode' => 'RNCP36832']);

        $step = $this->step('record');
        $this->assertSame(setup_checklist::DONE, $step->state);
        $gone = get_string('setuprecordgone', 'tool_vae', 'RNCP36832');
        $this->assertNotContains($gone, $step->details);
        foreach ($step->details as $detail) {
            $this->assertStringNotContainsString('RNCP36832', $detail);
        }
    }

    public function test_antivirus_is_absent_while_the_pieces_are_closed(): void {
        $this->resetAfterTest();
        set_config('piecesopen', 0, 'tool_vae');

        $this->assertNull($this->step('antivirus'));
    }

    public function test_antivirus_three_cases_once_the_pieces_are_open(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('piecesopen', 1, 'tool_vae');
        $CFG->antiviruses = '';

        set_config('piecenoantivirus', 0, 'tool_vae');
        $step = $this->step('antivirus');
        $this->assertSame(setup_checklist::TODO, $step->state);
        $this->assertSame(get_string('setupantivirusrefused', 'tool_vae'), $step->details[0]);
        $this->assertSame('manageantiviruses', $step->url->get_param('section'));
        $this->assertSame('moodle/site:config', $step->capability);

        set_config('piecenoantivirus', 1, 'tool_vae');
        $step = $this->step('antivirus');
        $this->assertSame(setup_checklist::CHECK, $step->state);
        $this->assertSame(get_string('setupantivirusaccepted', 'tool_vae'), $step->details[0]);
        $keys = array_column(setup_checklist::steps(), 'key');
        $this->assertSame('antivirus', end($keys));
    }

    public function test_antivirus_is_done_once_a_scanner_of_the_core_is_configured(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('piecesopen', 1, 'tool_vae');
        set_config('piecenoantivirus', 0, 'tool_vae');

        // ClamAV in command line mode: configured as soon as its path is given. Nothing is run, the step only reads.
        $CFG->antiviruses = 'clamav';
        set_config('runningmethod', 'commandline', 'antivirus_clamav');
        set_config('pathtoclam', '/usr/bin/clamscan', 'antivirus_clamav');
        $this->assertTrue(piece_manager::antivirus_configured());

        $step = $this->step('antivirus');
        $this->assertSame(setup_checklist::DONE, $step->state);
        $this->assertSame([], $step->details);

        // Done whatever the setting for a site without antivirus says.
        set_config('piecenoantivirus', 1, 'tool_vae');
        $this->assertSame(setup_checklist::DONE, $this->step('antivirus')->state);
    }

    public function test_nothing_is_written(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('piecesopen', 1, 'tool_vae');
        $this->record('RNCP36832');

        $before = $DB->perf_get_writes();
        setup_checklist::steps();
        $this->assertSame($before, $DB->perf_get_writes());
    }
}
