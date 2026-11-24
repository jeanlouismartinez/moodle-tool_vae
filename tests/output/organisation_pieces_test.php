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

use tool_vae\local\actors;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/organisation_before.php');

/**
 * The pieces of the page organisation.php draw the DOM the screen organisation_screen drew before lot 7 (MMR-520 ticket 4,
 * wave 4b): the old screen is kept in the trait organisation_before, compared in canonical DOM (dom_canon) for each state
 * the code tells apart - the pathway (records with and without source, blocs, a competency gone, a column of pieces open,
 * closed, or held; plans readable or not; records only a plan names), the followed vaeistes (nobody, the filter with and
 * without a choice made, each kind of plans cell), the configuration of the site (records off, none, two; every state of a
 * right; cohorts off, none, two; every setting), the box of a sentence and the link "Help".
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(organisation_cell::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(organisation_pathway::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(organisation_followed::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(organisation_site::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(organisation_notice::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(organisation_guide_link::class)]
final class organisation_pieces_test extends \advanced_testcase {
    use dom_canon;
    use organisation_before;

    /** @var string A text that a second escaping changes (an ampersand and quotes), and a script. */
    private const ODD = 'Soins & "aide" <script>alert(1)</script>';

    /**
     * The pieces open; the reader is the administrator (a hidden course is named to them).
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('piecesopen', 1, 'tool_vae');
        $this->setAdminUser();
        $this->setTimezone('UTC');
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
     * The old code, with the global $OUTPUT of the web for the time of the call (the old record() and planned() read it).
     *
     * @param callable $call
     * @return string
     */
    private static function before(callable $call): string {
        global $OUTPUT;

        $kept = $OUTPUT;
        $OUTPUT = self::core();
        try {
            return $call();
        } finally {
            $OUTPUT = $kept;
        }
    }

    /**
     * One competency line, as organisation::for_candidate() gives it.
     *
     * @param array $with
     * @return \stdClass
     */
    private static function row(array $with = []): \stdClass {
        return (object) ($with + [
            'idnumber' => 'RNCP100-C01', 'shortname' => 'Aider au lever', 'declarations' => 2, 'attested' => 1,
            'lastattested' => null, 'gone' => false, 'bloc' => null, 'isbloc' => false, 'pieces' => 0, 'lastact' => null,
            'scoped' => false, 'courses' => [], 'coursesunreadable' => false,
            'activities' => [], 'activitiesunreadable' => false, 'plans' => [],
        ]);
    }

    /**
     * A rich pathway: two declared records, one with blocs, a competency gone, courses, activities and plans of each kind.
     *
     * @param array $with What to change at the top.
     * @return \stdClass
     */
    private function pathway(array $with = []): \stdClass {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Cours & "visible"']);
        $hidden = $this->getDataGenerator()->create_course(['fullname' => 'Cours masque', 'visible' => 0]);
        $courses = [
            (object) ['courseid' => $course->id, 'fullname' => 'Cours & "visible"', 'visible' => 1],
            (object) ['courseid' => $hidden->id, 'fullname' => 'Cours masque', 'visible' => 0],
            (object) ['courseid' => 987654, 'fullname' => null, 'visible' => 0],
        ];
        $activities = [
            (object) ['course' => 'Cours <b>A</b>', 'name' => 'Quiz &lt;un&gt;', 'type' => 'Quiz & "test"',
                'url' => 'https://example.org/mod/quiz/view.php?id=7&a=1'],
            (object) ['course' => null, 'name' => 'Sans adresse', 'type' => '0', 'url' => null],
            (object) ['course' => 'Cours B', 'name' => null, 'type' => 'Page', 'url' => null],
        ];
        $plans = [
            (object) ['name' => self::ODD, 'statuslabel' => 'Active'],
            (object) ['name' => '0', 'statuslabel' => 'Draft'],
        ];
        $bloc = self::row([
            'idnumber' => 'RNCP100-BC01', 'shortname' => 'Bloc & "un"', 'isbloc' => true, 'declarations' => 0, 'attested' => 0,
            'pieces' => 0, 'lastact' => 1760000000, 'blocdeclarations' => 4, 'blocattested' => 2, 'blocpieces' => 5,
            'bloclastact' => 1780000000, 'courses' => $courses, 'plans' => $plans,
        ]);
        $under = self::row(['bloc' => 'RNCP100-BC01', 'gone' => true, 'shortname' => null, 'lastact' => 1770000000,
            'activities' => $activities, 'coursesunreadable' => true]);
        $other = self::row(['idnumber' => '0', 'shortname' => '0', 'activitiesunreadable' => true, 'declarations' => 0]);
        $first = (object) [
            'fichecode' => 'RNCP100', 'name' => self::ODD, 'hasblocs' => true,
            'source' => (object) ['fichetype' => 'rs', 'attribution' => 'France & "compétences"', 'licence' => 'LO 2.0',
                'sourceupdated' => 1780000000],
            'competencies' => ['RNCP100-BC01' => $bloc, 'RNCP100-C01' => $under],
        ];
        $second = (object) [
            'fichecode' => '<i>0</i>', 'name' => '<i>0</i>', 'hasblocs' => false,
            'source' => (object) ['fichetype' => 'rncp', 'attribution' => 'FC', 'licence' => 'LO', 'sourceupdated' => null],
            'competencies' => ['0' => $other],
        ];
        $planned = [
            'RS200' => (object) ['fichecode' => 'RS200', 'name' => 'RS200', 'courses' => [], 'coursesunreadable' => true,
                'activities' => [], 'activitiesunreadable' => false, 'plans' => []],
            'RS300' => (object) ['fichecode' => 'RS300', 'name' => self::ODD, 'courses' => $courses, 'coursesunreadable' => false,
                'activities' => $activities, 'activitiesunreadable' => false, 'plans' => $plans],
        ];

        return (object) ($with + [
            'declared' => ['RNCP100' => $first, '<i>0</i>' => $second], 'planned' => $planned, 'plans' => [],
            'plansreadable' => true, 'marks' => [],
        ]);
    }

    public function test_the_pathway_draws_the_dom_of_before_in_each_state(): void {
        $rich = $this->pathway();
        $held = clone $rich;
        $held->declared = ['RNCP100' => clone $rich->declared['RNCP100']];
        $held->declared['RNCP100']->competencies = ['x' => self::row(['pieces' => 3])];
        $states = [
            'rich, pieces open' => [$rich, 1],
            'rich, pieces closed, none held' => [$rich, 0],
            'pieces closed, one held' => [$held, 0],
            'plans not readable' => [$this->pathway(['plansreadable' => false]), 1],
            'planned only' => [$this->pathway(['declared' => []]), 1],
            'declared only, plans not readable' => [$this->pathway(['planned' => [], 'plansreadable' => false]), 1],
            'nothing' => [$this->pathway(['declared' => [], 'planned' => []]), 1],
            // The note on unreadable plans is said under tables only: nothing declared, plans unreadable, no note (added
            // after the mutant op-unreadable stayed green: a condition of two terms, piege 44).
            'nothing, plans not readable' => [$this->pathway(['declared' => [], 'planned' => [], 'plansreadable' => false]), 1],
        ];
        foreach ($states as $name => [$pathway, $open]) {
            set_config('piecesopen', $open, 'tool_vae');
            $old = self::before(fn() => self::before_organisation_mine($pathway));
            $new = $this->render(new organisation_pathway($pathway));
            $this->assertSame(self::canon($old), self::canon($new), $name);
            $this->assertSame(
                $pathway->declared !== [] && ($open === 1 || $name === 'pieces closed, one held'),
                str_contains($old, get_string('orgcolpieces', 'tool_vae')),
                $name . ': premise of the column of pieces.'
            );
        }
        set_config('piecesopen', 1, 'tool_vae');
        $old = self::before(fn() => self::before_organisation_mine($rich));
        // Premises: every state of a cell is there.
        $this->assertSame('', self::before(fn() => self::before_organisation_mine($states['nothing'][0])));
        $this->assertSame(
            '',
            self::before(fn() => self::before_organisation_mine($states['nothing, plans not readable'][0])),
            'Premise: no note without a table.'
        );
        $this->assertStringContainsString('<strong>RNCP100-BC01 — Bloc &amp; &quot;un&quot;</strong>', $old);
        $this->assertStringContainsString(get_string('coveragegone', 'tool_vae'), $old);
        $this->assertStringContainsString('class="ps-4', $old);
        $this->assertStringContainsString(get_string('orgcoursehidden', 'tool_vae'), $old);
        $this->assertStringContainsString(get_string('orgcoursehiddenmark', 'tool_vae'), $old);
        $this->assertStringContainsString(get_string('orgactivityhidden', 'tool_vae'), $old);
        $this->assertStringContainsString('Quiz &lt;un&gt;</a> (Quiz &amp; &quot;test&quot;)', $old);
        $this->assertStringContainsString('Sans adresse (0)', $old);
        $this->assertStringContainsString(get_string('orgcoursesunreadable', 'tool_vae'), $old);
        $this->assertStringContainsString('0 (Draft)', $old);
        $this->assertStringContainsString('&lt;i&gt;0&lt;/i&gt;', $old);
        $this->assertStringContainsString(get_string('orgblocnote', 'tool_vae'), $old);
        $this->assertStringContainsString('RS300</strong> — Soins', $old);
        $this->assertStringNotContainsString('RS200</strong> — ', $old);
        $this->assertStringContainsString('France &amp; &quot;compétences&quot;', $old);
        $this->assertNotSame(s(self::ODD), s(s(self::ODD)), 'Premise: a second escaping shows.');
        $this->assertStringNotContainsString('<script', $old);
        foreach ([$old, $this->render(new organisation_pathway($rich))] as $html) {
            $this->assertStringNotContainsString('&amp;amp;', $html);
            $this->assertStringNotContainsString('&amp;quot;', $html);
        }
        $unreadable = self::before(fn() => self::before_organisation_mine($states['plans not readable'][0]));
        $this->assertStringContainsString(get_string('orgplansunreadablenote', 'tool_vae'), $unreadable);
    }

    public function test_the_order_of_the_records_and_of_the_lines_is_kept(): void {
        $pathway = $this->pathway();
        $new = $this->render(new organisation_pathway($pathway));
        $this->assertLessThan(strpos($new, '&lt;i&gt;0&lt;/i&gt;'), strpos($new, 'RNCP100'), 'The records in their order.');
        $this->assertLessThan(strpos($new, 'RNCP100-C01'), strpos($new, 'RNCP100-BC01'), 'The lines in their order.');
        $this->assertLessThan(strpos($new, 'RS300'), strpos($new, 'RS200'), 'The records a plan names in their order.');
        $this->assertLessThan(strpos($new, '0 (Draft)'), strpos($new, 'Soins &amp;'), 'The plans in their order.');
    }

    /**
     * What organisation::followed() gives.
     *
     * @param array $with
     * @return \stdClass
     */
    private static function followed(array $with = []): \stdClass {
        $plans = [(object) ['name' => 'Plan & "AES"', 'statuslabel' => 'Active'], (object) ['name' => '0', 'statuslabel' => 'x']];
        $candidate = fn(int $id, array $more) => (object) ($more + [
            'id' => $id, 'name' => 'Camille ' . $id, 'fiches' => [], 'declarations' => 0, 'pending' => 0,
            'plansreadable' => true, 'draftsreadable' => true, 'plans' => [],
        ]);
        $candidates = [
            7 => $candidate(7, ['name' => self::ODD, 'declarations' => 2, 'pending' => 1, 'plans' => $plans,
                'fiches' => [(object) ['fichecode' => 'RNCP100', 'name' => 'RNCP100'],
                    (object) ['fichecode' => 'RS0', 'name' => 'Lève & "personne"']]]),
            8 => $candidate(8, ['plansreadable' => false, 'draftsreadable' => false]),
            9 => $candidate(9, ['plansreadable' => true, 'draftsreadable' => false, 'plans' => $plans]),
            10 => $candidate(10, ['plansreadable' => false, 'draftsreadable' => true]),
        ];

        return (object) ($with + ['candidates' => $candidates, 'choices' => [], 'chosen' => '']);
    }

    public function test_the_followed_vaeistes_draw_the_dom_of_before_in_each_state(): void {
        $url = new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => 'followed', 'bloc' => 'x']);
        $choices = [
            'RNCP100|BC01' => (object) ['fichecode' => 'RNCP100', 'ficherang' => 'BC01'],
            'RS & "200"' => (object) ['fichecode' => 'RS & "200"', 'ficherang' => ''],
        ];
        $states = [
            'nobody' => self::followed(['candidates' => []]),
            'four, no choice' => self::followed(),
            'choices, none made' => self::followed(['choices' => $choices]),
            'choices, one made' => self::followed(['choices' => $choices, 'chosen' => 'RS & "200"']),
        ];
        foreach ($states as $name => $data) {
            $old = self::before_organisation_followed($data, $url);
            $new = $this->render(new organisation_followed($data, $url));
            $this->assertSame(self::canon($old), self::canon($new), $name);
            $this->assertSame($data->choices !== [] && $data->candidates !== [], str_contains($old, '<form'), $name);
            $this->assertSame($data->chosen !== '', str_contains($old, 'The counts are limited'), $name);
        }
        $old = self::before_organisation_followed($states['choices, one made'], $url);
        $this->assertStringContainsString('<option selected="selected" value="RS &amp; &quot;200&quot;">', $old, 'Premise.');
        $this->assertStringContainsString('action="' . $url->out_omit_querystring() . '"', $old, 'Premise: no query.');
        $this->assertStringContainsString(get_string('orgplanspartial', 'tool_vae'), $old);
        $this->assertStringContainsString(get_string('orgplansunreadable', 'tool_vae'), $old);
        $this->assertStringContainsString('RS0 — Lève &amp; &quot;personne&quot;', $old);
        $this->assertStringNotContainsString('<script', $this->render(new organisation_followed($states['four, no choice'], $url)));
        $new = $this->render(new organisation_followed($states['choices, one made'], $url));
        // The space between the label and the list, which the canonical DOM drops next to a select (rule N6), kept as the
        // writer of the core wrote it (added after the mutant oft-space stayed green).
        $this->assertStringContainsString('</label> <select', $old, 'Premise.');
        $this->assertStringContainsString('</label> <select', $new);
        $this->assertStringContainsString('<input type="hidden" name="tab" value="followed" /> <input', $new);
        $this->assertStringNotContainsString('&amp;amp;', $new);
        $this->assertStringNotContainsString('&amp;quot;', $new);
    }

    /**
     * What organisation::site() gives: two records, every state of a right, two cohorts, every setting.
     *
     * @param array $with What to change at the top of the powers, or 'fiches'.
     * @return \stdClass
     */
    private function site(array $with = []): \stdClass {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Cours site']);
        $activity = fn(string $name) => (object) ['course' => 'Cours', 'name' => $name, 'type' => 'Quiz', 'url' => null];
        $fiches = [
            'RNCP100' => (object) [
                'fichecode' => 'RNCP100', 'name' => self::ODD, 'blocs' => 2, 'competencies' => 4,
                'courses' => [(object) ['courseid' => $course->id, 'fullname' => 'Cours site', 'visible' => 1]],
                'coursesunreadable' => false, 'activities' => [$activity('A'), $activity('B')], 'activitiesunreadable' => false,
                'models' => [(object) ['templateid' => 1, 'name' => null, 'visible' => 1],
                    (object) ['templateid' => 2, 'name' => 'Modèle & "un"', 'visible' => 0],
                    (object) ['templateid' => 3, 'name' => '0', 'visible' => 1]],
                'plans' => 0,
                'referents' => [(object) ['ficherang' => '', 'names' => ['Ana & "B"', 'Zoé']],
                    (object) ['ficherang' => 'BC01', 'names' => []]],
                'rules' => [(object) ['title' => 'Règle & "un"', 'url' => 'https://example.org/r?a=1&b=2'],
                    (object) ['title' => '0', 'url' => 'https://example.org/0']],
            ],
            '0' => (object) [
                'fichecode' => '0', 'name' => '0', 'blocs' => 0, 'competencies' => null, 'courses' => [],
                'coursesunreadable' => true, 'activities' => [$activity('C')], 'activitiesunreadable' => false, 'models' => [],
                'plans' => 3, 'referents' => [], 'rules' => null,
            ],
            'RS9' => (object) [
                'fichecode' => 'RS9', 'name' => 'RS9', 'blocs' => null, 'competencies' => 1, 'courses' => [],
                'coursesunreadable' => false, 'activities' => [], 'activitiesunreadable' => true, 'models' => [],
                'plans' => 1, 'referents' => [], 'rules' => [],
            ],
        ];
        $right = fn(string $state, array $more) => (object) ($more + [
            'label' => 'Lire & "voir"', 'name' => 'tool/vae:viewany', 'state' => $state, 'roles' => [], 'adminmay' => false,
            'status' => actors::OPTIONAL, 'forbidden' => [],
        ]);
        $powers = (object) ($with + [
            'actors' => [
                (object) ['key' => 'vaeiste', 'account' => true, 'rights' => [
                    $right(actors::GIVEN, ['roles' => ['R &amp; D', 'Zeta'], 'forbidden' => ['Interdit &amp; co']]),
                    $right(actors::MISSING, ['adminmay' => true]),
                    $right(actors::MISSING, ['adminmay' => false]),
                ]],
                (object) ['key' => 'jury', 'account' => false, 'rights' => []],
                (object) ['key' => 'direction', 'account' => true, 'rights' => [
                    $right(actors::FREE, ['status' => actors::ADMIN]),
                    $right(actors::FREE, ['status' => actors::OPTIONAL]),
                ]],
                (object) ['key' => 'certificateur', 'account' => false, 'rights' => []],
            ],
            'capabilities' => [
                (object) ['name' => 'tool/vae:viewany', 'label' => 'Voir & "tout"', 'allow' => ['A &amp; B', 'C'],
                    'prevent' => [], 'prohibit' => ['D']],
                (object) ['name' => 'tool/vae:attest', 'label' => 'Attester', 'allow' => [], 'prevent' => ['E'], 'prohibit' => []],
            ],
            'cohorts' => [
                (object) ['cohortid' => 1, 'name' => null, 'roleid' => 1, 'rolename' => 'R &amp; D'],
                (object) ['cohortid' => 2, 'name' => 'Cohorte & "deux"', 'roleid' => 2, 'rolename' => 'Manager'],
            ],
            'settings' => [
                (object) ['name' => 'overduedays', 'value' => 1, 'open' => true],
                (object) ['name' => 'overduedays', 'value' => 5, 'open' => true],
                (object) ['name' => 'overduedays', 'value' => 5, 'open' => false],
                (object) ['name' => 'validationopen', 'value' => 1, 'open' => true],
                (object) ['name' => 'piecesopen', 'value' => 0, 'open' => false],
            ],
        ]);
        unset($powers->fiches);

        return (object) ['fiches' => array_key_exists('fiches', $with) ? $with['fiches'] : $fiches, 'powers' => $powers];
    }

    public function test_the_site_draws_the_dom_of_before_in_each_state(): void {
        $states = [
            'rich' => $this->site(),
            'competencies off, no cohort tool' => $this->site(['fiches' => null, 'cohorts' => null]),
            'no record, no cohort, nobody without an account' => $this->site(['fiches' => [], 'cohorts' => [],
                'actors' => [(object) ['key' => 'vaeiste', 'account' => true, 'rights' => []]]]),
        ];
        foreach ($states as $name => $data) {
            $old = self::before_organisation_site($data);
            $new = $this->render(new organisation_site($data));
            $this->assertSame(self::canon($old), self::canon($new), $name);
        }
        $old = self::before_organisation_site($states['rich']);
        $keys = ['orgmodelhidden', 'orgcoursehiddenmark', 'orgnoreferent', 'orgrulesclosed', 'orgactormissing',
            'orgactormissingadmin', 'orgactormissingnobody', 'orgactorfreeadmin', 'orgactorfreeoptional', 'orgactorjurynote',
            'orgcohortgone', 'orgdayone', 'orgcoursesunreadable', 'orgactivitiesone', 'orgnorole', 'orgplansnote'];
        foreach ($keys as $key) {
            $this->assertStringContainsString(s(get_string($key, 'tool_vae')), $old, 'Premise: ' . $key);
        }
        $this->assertStringContainsString(get_string('orgactivitiesmany', 'tool_vae', 2), $old);
        $this->assertStringContainsString(get_string('orgdays', 'tool_vae', 5), $old);
        $this->assertStringContainsString('R &amp; D, Zeta', $old, 'Premise: role names placed as HTML.');
        $this->assertStringContainsString('Ana &amp; &quot;B&quot;, Zoé', $old);
        $this->assertLessThan(strpos($old, 'Certifier'), strpos($old, 'Jury'), 'Premise: the actors in their order.');
        $none = self::before_organisation_site($states['no record, no cohort, nobody without an account']);
        $this->assertStringContainsString(get_string('orgsitenofiche', 'tool_vae'), $none);
        $this->assertStringNotContainsString('list-unstyled small', $none, 'Premise: no list of actors without an account.');
        $off = self::before_organisation_site($states['competencies off, no cohort tool']);
        $this->assertStringContainsString(get_string('orgcohortsunavailable', 'tool_vae'), $off);
        $new = $this->render(new organisation_site($states['rich']));
        $this->assertStringNotContainsString('<script', $new);
        $this->assertStringNotContainsString('&amp;amp;', $new);
        $this->assertStringNotContainsString('&amp;quot;', $new);
    }

    public function test_the_box_and_the_empty_pathway_draw_the_dom_of_before(): void {
        $this->assertSame(
            self::canon(self::before_organisation_notice(self::ODD)),
            self::canon($this->render(new organisation_notice(self::ODD)))
        );
        $this->assertSame('<div class="alert alert-info">0</div>', $this->render(new organisation_notice('0')));
        foreach ([true, false] as $readable) {
            $pathway = (object) ['declared' => [], 'planned' => [], 'plansreadable' => $readable];
            $old = self::before_organisation_nothing($pathway);
            $this->assertSame(self::canon($old), self::canon($this->render(organisation_notice::nothing($pathway))));
            $this->assertSame($readable, str_contains($old, s(get_string('orgnofiche', 'tool_vae'))), 'Premise.');
        }
    }

    public function test_the_link_help_draws_the_dom_of_before_for_each_tab(): void {
        foreach (['mine', 'followed', 'site', 'unknown'] as $tab) {
            $old = self::before_organisation_guide_link($tab);
            $this->assertSame(self::canon($old), self::canon($this->render(new organisation_guide_link($tab))), $tab);
        }
        $this->assertStringContainsString('guide.php?role=direction', self::before_organisation_guide_link('site'), 'Premise.');
        $this->assertSame('', $this->render(new organisation_guide_link('unknown')));
    }

    public function test_the_pieces_read_the_database_no_more_than_the_old_screen(): void {
        global $DB;

        $pathway = $this->pathway();
        $site = $this->site();
        $url = new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => 'followed']);
        $followed = self::followed();
        $calls = [
            'pathway' => [fn() => self::before(fn() => self::before_organisation_mine($pathway)),
                fn() => $this->render(new organisation_pathway($pathway))],
            'site' => [fn() => self::before_organisation_site($site), fn() => $this->render(new organisation_site($site))],
            'followed' => [fn() => self::before_organisation_followed($followed, $url),
                fn() => $this->render(new organisation_followed($followed, $url))],
        ];
        foreach ($calls as $name => [$old, $new]) {
            // Both drawn once first, so that what the caches of the request keep is kept for both.
            $old();
            $new();
            $before = $DB->perf_get_reads();
            $old();
            $oldreads = $DB->perf_get_reads() - $before;
            $before = $DB->perf_get_reads();
            $new();
            $newreads = $DB->perf_get_reads() - $before;
            $this->assertLessThanOrEqual($oldreads, $newreads, $name);
        }
    }

    public function test_the_templates_place_raw_only_the_keys_named_html(): void {
        global $CFG;

        $names = ['organisation_cell', 'organisation_pathway', 'organisation_followed', 'organisation_site',
            'organisation_notice', 'organisation_guide_link', 'organisation_page'];
        foreach ($names as $name) {
            $text = file_get_contents($CFG->dirroot . '/admin/tool/vae/templates/' . $name . '.mustache');
            preg_match_all('/\{\{\{(\w+)\}\}\}/', $text, $raw);
            foreach ($raw[1] as $key) {
                $this->assertStringEndsWith('html', $key, $name);
            }
        }
    }
}
