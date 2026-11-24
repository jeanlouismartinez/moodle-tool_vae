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

use tool_vae\local\access;
use tool_vae\local\competency_gateway;
use tool_vae\local\next_actions;
use tool_vae\local\organisation;

/**
 * The cost in database reads of the three tabs of the organisation page and of the link "VAE", on a large site (lot Q8).
 *
 * MEASURED, THEN BOUND. Each tab is read on a small set of data, then the set grows along one dimension at a time - the
 * records, the competencies, the plans, the courses, the activities, the cohorts, the vaeistes followed - and the reads are
 * counted again. Where the count does not move, the test holds it still (the two counts are equal); where it grows, the
 * test holds the growth to what was measured per element added, and the comment says which line of the plugin pays it.
 * No absolute number: PostgreSQL and MariaDB do not count a recordset alike, and the bounds hold on both.
 *
 * COLD, AS A PAGE IS. Each call is made once, so that what the core keeps from one page to the next (its application
 * caches: role definitions, settings, module information) is there as on a site in service; then the caches of contexts,
 * of rights and of module information of the request are emptied, as at the start of a page, and the second call is
 * counted.
 *
 * With the environment variable TOOL_VAE_LOAD_REPORT set to 1, the measures are written to the error output as a table, one
 * line per tab and dimension; without it nothing is written.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\local\organisation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\local\next_actions::class)]
final class organisation_load_test extends \advanced_testcase {
    /** @var array[] The measures of the class: tab, dimension, small size, large size, reads at each size. */
    private static $lines = [];

    /** @var \tool_vae_generator */
    private $plugingenerator;

    /** @var \component_generator_base The generator of the competency subsystem, got by the gateway's constant. */
    private $coregenerator;

    /** @var int The role of a vaeiste: declare and read their own dossier. */
    private $ownrole;

    /** @var int How many records the test has made, for their codes. */
    private $made = 0;

    /** @var int[] The id of the competency C01 of each record made, by record code. */
    private $firstcompetency = [];

    /**
     * The generators and the role of a vaeiste.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->plugingenerator = $generator->get_plugin_generator('tool_vae');
        $this->coregenerator = $generator->get_plugin_generator(competency_gateway::SUBSYSTEM);
        $this->ownrole = $generator->create_role();
        foreach (['tool/vae:declare', 'tool/vae:viewown'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $this->ownrole, \context_system::instance()->id, true);
        }
    }

    /**
     * Write the table of the measures to the error output when TOOL_VAE_LOAD_REPORT is 1.
     */
    public static function tearDownAfterClass(): void {
        if (getenv('TOOL_VAE_LOAD_REPORT') === '1' && self::$lines) {
            $out = "\n| Onglet | Dimension | Petite taille | Grande taille | Lectures (petite) | Lectures (grande) "
                . "| Lectures par élément ajouté |\n|---|---|---|---|---|---|---|\n";
            foreach (self::$lines as [$tab, $dimension, $small, $large, $smallreads, $largereads]) {
                $per = str_replace('.', ',', sprintf('%.2f', ($largereads - $smallreads) / ($large - $small)));
                $out .= "| $tab | $dimension | $small | $large | $smallreads | $largereads | $per |\n";
            }
            fwrite(STDERR, $out);
        }
        self::$lines = [];
        parent::tearDownAfterClass();
    }

    /**
     * Keep a measure for the report.
     *
     * @param string $tab The tab, as the screen names it.
     * @param string $dimension What grew between the two measures.
     * @param int $small Its size in the small set.
     * @param int $large Its size in the large set.
     * @param int $smallreads The reads measured on the small set.
     * @param int $largereads The reads measured on the large set.
     */
    private static function line(string $tab, string $dimension, int $small, int $large, int $smallreads, int $largereads): void {
        self::$lines[] = [$tab, $dimension, $small, $large, $smallreads, $largereads];
    }

    /**
     * Empty what a page starts without: the contexts, the rights and the module information of the request.
     */
    private static function cold(): void {
        \context_helper::reset_caches();
        accesslib_clear_all_caches_for_unit_testing();
        \core_course\modinfo::clear_instance_cache();
    }

    /**
     * The reads of one call, made a second time from cold caches (see the class).
     *
     * @param callable $call What a tab reads.
     * @return int
     */
    private static function reads(callable $call): int {
        global $DB;

        $call();
        self::cold();
        $before = $DB->perf_get_reads();
        $call();

        return $DB->perf_get_reads() - $before;
    }

    /**
     * A new record of one competency, C01, and its code.
     *
     * @return string
     */
    private function record(): string {
        $code = 'RNCP7' . str_pad((string) ++$this->made, 3, '0', STR_PAD_LEFT);
        $this->plugingenerator->create_source(['fichecode' => $code, 'ficherang' => 'C01', 'shortname' => 'Competence']);
        $gateway = new competency_gateway();
        $competencies = $gateway->list_framework_competencies((int) $gateway->find_framework($code));
        $this->firstcompetency[$code] = (int) reset($competencies)->id;

        return $code;
    }

    /**
     * A vaeiste: an account that may declare and read their own dossier.
     *
     * @return \stdClass
     */
    private function vaeiste(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        role_assign($this->ownrole, $user->id, \context_user::instance($user->id)->id);

        return $user;
    }

    /**
     * A declaration of a vaeiste on the competency C01 of a record.
     *
     * @param \stdClass $vaeiste
     * @param string $code
     */
    private function declare(\stdClass $vaeiste, string $code): void {
        $this->plugingenerator->create_declaration([
            'userid' => $vaeiste->id, 'fichecode' => $code, 'ficherang' => 'C01', 'situation' => 'Une situation vécue.',
        ]);
    }

    /**
     * An active learning plan of the core, holding some competencies.
     *
     * @param \stdClass $vaeiste
     * @param int[] $competencyids
     */
    private function plan(\stdClass $vaeiste, array $competencyids): void {
        $this->setAdminUser();
        $plan = $this->coregenerator->create_plan(['userid' => $vaeiste->id, 'status' => competency_gateway::PLAN_ACTIVE]);
        foreach ($competencyids as $competencyid) {
            $this->coregenerator->create_plan_competency(['planid' => $plan->get('id'), 'competencyid' => $competencyid]);
        }
    }

    /**
     * A course that carries a competency.
     *
     * @param int $competencyid
     * @return \stdClass
     */
    private function course(int $competencyid): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $this->coregenerator->create_course_competency(['courseid' => $course->id, 'competencyid' => $competencyid]);

        return $course;
    }

    /**
     * An activity of a course that carries a competency.
     *
     * @param \stdClass $course
     * @param int $competencyid
     */
    private function activity(\stdClass $course, int $competencyid): void {
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $this->coregenerator->create_course_module_competency(['cmid' => $quiz->cmid, 'competencyid' => $competencyid]);
    }

    /**
     * "My pathway": the reads grow by the records declared, the plans and the courses, never by the competencies.
     *
     * From 2 records declared, 1 competency each, 1 plan and 1 course, to 30 records of 5 competencies, 5 plans and 30
     * distinct courses, one dimension at a time.
     */
    public function test_the_reads_of_my_pathway(): void {
        $vaeiste = $this->vaeiste();
        $codes = [$this->record(), $this->record()];
        foreach ($codes as $code) {
            $this->declare($vaeiste, $code);
        }
        $this->course($this->firstcompetency[$codes[0]]);
        $this->plan($vaeiste, [$this->firstcompetency[$codes[0]]]);
        $read = fn() => organisation::for_candidate((int) $vaeiste->id);

        $this->setUser($vaeiste);
        $start = self::reads($read);

        for ($i = 0; $i < 28; $i++) {
            $code = $this->record();
            $this->declare($vaeiste, $code);
            $codes[] = $code;
        }
        $this->setUser($vaeiste);
        $records = self::reads($read);
        self::line('Mon parcours', 'fiches déclarées', 2, 30, $start, $records);

        foreach ($codes as $code) {
            for ($rank = 2; $rank <= 5; $rank++) {
                $this->plugingenerator->create_competency([
                    'fichecode' => $code, 'ficherang' => sprintf('C%02d', $rank), 'shortname' => 'Autre',
                ]);
            }
        }
        $this->setUser($vaeiste);
        $competencies = self::reads($read);
        self::line('Mon parcours', 'compétences par fiche', 1, 5, $records, $competencies);

        for ($i = 1; $i <= 4; $i++) {
            $this->plan($vaeiste, [$this->firstcompetency[$codes[$i]], $this->firstcompetency[$codes[$i + 10]]]);
        }
        $this->setUser($vaeiste);
        $plans = self::reads($read);
        self::line('Mon parcours', 'plans', 1, 5, $competencies, $plans);

        for ($i = 1; $i < 30; $i++) {
            $this->course($this->firstcompetency[$codes[$i]]);
        }
        $this->setUser($vaeiste);
        $courses = self::reads($read);
        self::line('Mon parcours', 'cours distincts liés', 1, 30, $plans, $courses);

        // The large set is what was meant: 30 records of 5 competencies, 5 plans, a course on each record.
        $pathway = organisation::for_candidate((int) $vaeiste->id);
        $this->assertCount(30, $pathway->declared);
        $this->assertCount(5, $pathway->plans);
        $this->assertCount(5, $pathway->declared[$codes[29]]->competencies);
        $this->assertCount(1, $pathway->declared[$codes[29]]->competencies[$codes[29] . '-C01']->courses);

        // A record declared: its name, competency_gateway::framework_name() in organisation::for_candidate(), and its
        // competencies, competency_gateway::list_framework_competencies() read twice, there and in coverage::for_candidate().
        $this->assertLessThanOrEqual($start + 3 * 28, $records, 'At most 3 reads per record declared.');
        // The competencies of a record are read in the same query whatever their number.
        $this->assertSame($records, $competencies, 'The competencies of a record are read at once.');
        // A plan: competency_gateway::plan_competency_ids(), the plan and its competencies ("one query per plan").
        $this->assertLessThanOrEqual($competencies + 2 * 4, $plans, 'At most 2 reads per plan.');
        // A distinct course: its context, read for the core's right to see its competencies (readable_courses()).
        $this->assertLessThanOrEqual($plans + 1 * 29, $courses, 'At most 1 read per distinct course.');
    }

    /**
     * "Vaeistes followed": the reads grow by the vaeistes followed and the distinct records, never by the plans.
     *
     * From 10 vaeistes followed, each with 1 record declared and 1 plan, to 100 vaeistes with 5 plans and 3 records each
     * (three records in all), one dimension at a time. The tab reads organisation::followed() and the strip of what the
     * manager can do now, next_actions::for_manager(), as organisation.php does. The link "VAE" of the primary navigation
     * is measured for the same manager, for the record.
     */
    public function test_the_reads_of_the_followed_vaeistes(): void {
        $generator = $this->getDataGenerator();
        $manager = $generator->create_user();
        $followrole = $generator->create_role();
        foreach (['tool/vae:viewassigned', 'tool/vae:attest', 'moodle/competency:planview'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $followrole, \context_system::instance()->id, true);
        }
        $codes = [$this->record(), $this->record(), $this->record()];
        $followed = [];
        $add = function (int $count) use (&$followed, $manager, $followrole, $codes): void {
            for ($i = 0; $i < $count; $i++) {
                $vaeiste = $this->vaeiste();
                role_assign($followrole, $manager->id, \context_user::instance($vaeiste->id)->id);
                $this->declare($vaeiste, $codes[0]);
                $this->plan($vaeiste, []);
                $followed[] = $vaeiste;
            }
        };
        $read = function () use ($manager): void {
            $list = organisation::followed((int) $manager->id);
            next_actions::for_manager(array_keys($list->candidates));
        };
        $list = fn() => access::followed_candidates();

        $add(10);
        $this->setUser($manager);
        $start = self::reads($read);
        $startlist = self::reads($list);
        $startlink = $this->reads_of_the_link($manager);

        $add(90);
        $this->setUser($manager);
        $vaeistes = self::reads($read);
        $vaeisteslist = self::reads($list);
        $vaeisteslink = $this->reads_of_the_link($manager);
        self::line('Vaeistes suivis', 'vaeistes suivis', 10, 100, $start, $vaeistes);
        $dimension = 'vaeistes suivis, dont la liste (access::followed_candidates)';
        self::line('Vaeistes suivis', $dimension, 10, 100, $startlist, $vaeisteslist);
        self::line('Lien « VAE » de la barre', 'vaeistes suivis', 10, 100, $startlink, $vaeisteslink);

        foreach ($followed as $vaeiste) {
            for ($i = 0; $i < 4; $i++) {
                $this->plan($vaeiste, []);
            }
        }
        $this->setUser($manager);
        $plans = self::reads($read);
        self::line('Vaeistes suivis', 'plans par vaeiste', 1, 5, $vaeistes, $plans);

        foreach ($followed as $vaeiste) {
            $this->declare($vaeiste, $codes[1]);
            $this->declare($vaeiste, $codes[2]);
        }
        $this->setUser($manager);
        $records = self::reads($read);
        self::line('Vaeistes suivis', 'fiches par vaeiste (3 fiches en tout)', 1, 3, $plans, $records);

        // The large set is what was meant: 100 vaeistes, each with 5 plans the manager may read and 3 records.
        $this->setUser($manager);
        $table = organisation::followed((int) $manager->id);
        $this->assertCount(100, $table->candidates);
        $last = $table->candidates[(int) end($followed)->id];
        $this->assertCount(5, $last->plans);
        $this->assertCount(3, $last->fiches);

        // A vaeiste followed: their context, read by access::follows() for each id access::followed_candidates() finds
        // (decision D9). The page pays it once: the checks of next_actions::for_manager() and the plans of
        // organisation::add_plans() find the context in the cache.
        $this->assertLessThanOrEqual($start + 1 * 90, $vaeistes, 'At most 1 read per vaeiste followed.');
        $this->assertSame($vaeistes - $start, $vaeisteslist - $startlist, 'The list of the followed is all the growth.');
        // The link asks access::follows_anyone(): one query, which stops at the first vaeiste followed.
        $this->assertSame($startlink, $vaeisteslink, 'The link costs the same whatever the number followed.');
        // The plans of all the vaeistes are read in one query per kind of right (organisation::add_plans()).
        $this->assertSame($vaeistes, $plans, 'The plans are read at once.');
        // A distinct record: its name, competency_gateway::framework_name() in organisation::add_fiches(); 200
        // declarations on 2 more records cost 2 reads, not 200.
        $this->assertLessThanOrEqual($plans + 1 * 2, $records, 'At most 1 read per distinct record.');
    }

    /**
     * The reads of the link "VAE" alone, on a page where the core has already read the user's rights and context.
     *
     * The same measure as tests/entry_link_test.php: the caches emptied, then what any page reads before the primary
     * navigation is read again, and what remains is what the rule reads of its own.
     *
     * @param \stdClass $user
     * @return int
     */
    private function reads_of_the_link(\stdClass $user): int {
        global $DB;

        self::cold();
        $this->setUser($user);
        \context_user::instance((int) $user->id);
        has_capability('moodle/site:config', \context_system::instance());
        competency_gateway::is_subsystem_enabled();
        get_role_definitions(array_keys(get_all_roles()));

        $before = $DB->perf_get_reads();
        $this->assertNotNull(organisation::entry_url());

        return $DB->perf_get_reads() - $before;
    }

    /**
     * "Organisation": the reads grow by the distinct courses, never by the records, the activities of a course or the cohorts.
     *
     * From 2 records, 1 course, 1 activity and 1 cohort, to 30 records, 30 distinct courses, 30 activities one per course
     * then 29 more in one course, and 30 cohorts, one dimension at a time, read by a direction who holds viewany alone.
     */
    public function test_the_reads_of_the_organisation(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $direction = $generator->create_user();
        $directionrole = $generator->create_role();
        assign_capability('tool/vae:viewany', CAP_ALLOW, $directionrole, \context_system::instance()->id, true);
        role_assign($directionrole, $direction->id, \context_system::instance()->id);
        $followrole = $generator->create_role();
        assign_capability('tool/vae:viewassigned', CAP_ALLOW, $followrole, \context_system::instance()->id, true);
        $cohort = function () use ($generator, $followrole): void {
            global $DB;
            $DB->insert_record('tool_cohortroles', (object) [
                'cohortid' => $generator->create_cohort()->id, 'roleid' => $followrole, 'userid' => 2,
                'timecreated' => time(), 'timemodified' => time(), 'usermodified' => 2,
            ]);
        };
        $read = fn() => organisation::site();

        $codes = [$this->record(), $this->record()];
        $shared = $this->course($this->firstcompetency[$codes[0]]);
        $this->coregenerator->create_course_competency([
            'courseid' => $shared->id, 'competencyid' => $this->firstcompetency[$codes[1]],
        ]);
        $this->activity($shared, $this->firstcompetency[$codes[0]]);
        $cohort();
        $this->setUser($direction);
        $start = self::reads($read);

        // The records all carried by the course already there: what grows is the records alone.
        for ($i = 0; $i < 28; $i++) {
            $code = $this->record();
            $this->coregenerator->create_course_competency([
                'courseid' => $shared->id, 'competencyid' => $this->firstcompetency[$code],
            ]);
            $codes[] = $code;
        }
        $this->setUser($direction);
        $records = self::reads($read);
        self::line('Organisation', 'fiches', 2, 30, $start, $records);

        $courses = [];
        for ($i = 1; $i < 30; $i++) {
            $courses[$i] = $this->course($this->firstcompetency[$codes[$i]]);
        }
        $this->setUser($direction);
        $distinct = self::reads($read);
        self::line('Organisation', 'cours distincts liés', 1, 30, $records, $distinct);

        foreach ($courses as $i => $course) {
            $this->activity($course, $this->firstcompetency[$codes[$i]]);
        }
        $this->setUser($direction);
        $activities = self::reads($read);
        self::line('Organisation', 'cours portant une activité', 1, 30, $distinct, $activities);

        for ($i = 1; $i < 30; $i++) {
            $this->activity($shared, $this->firstcompetency[$codes[$i]]);
        }
        $this->setUser($direction);
        $together = self::reads($read);
        self::line('Organisation', 'activités dans un même cours', 1, 30, $activities, $together);

        for ($i = 0; $i < 29; $i++) {
            $cohort();
        }
        $this->setUser($direction);
        $cohorts = self::reads($read);
        self::line('Organisation', 'cohortes', 1, 30, $together, $cohorts);

        // The large set is what was meant: 30 records, a course of its own and an activity in it for 29 of them, 29 more
        // activities in the shared course, 30 cohorts.
        $site = organisation::site();
        $this->assertCount(30, $site->fiches);
        $this->assertCount(2, $site->fiches[$codes[29]]->courses);
        $this->assertCount(2, $site->fiches[$codes[29]]->activities);
        $this->assertCount(30, $site->powers->cohorts);
        $this->assertSame(30, $DB->count_records('tool_cohortroles'));

        // The records, their frameworks, competencies, courses, templates, plans and referents are read by group
        // (organisation::site_fiches()).
        $this->assertSame($start, $records, 'The records are read at once.');
        // A distinct course: its context, read for the core's right to see its competencies (readable_courses(), through
        // competency_gateway::can_view_course_competencies()).
        $this->assertLessThanOrEqual($records + 1 * 29, $distinct, 'At most 1 read per distinct course.');
        // A course that holds an activity: get_fast_modinfo(), called once per course by organisation::describe_activity(),
        // reads the row of the course and the contexts of all its activities at once (two queries seen in the SQL).
        $this->assertLessThanOrEqual($distinct + 2 * 29, $activities, 'At most 2 reads per course holding an activity.');
        // More activities in a course already read cost nothing: their contexts came with the module information.
        $this->assertSame($activities, $together, 'The activities of a course are read at once.');
        // The cohorts are read in one recordset (organisation::cohort_roles()).
        $this->assertSame($together, $cohorts, 'The cohorts are read at once.');
    }
}
