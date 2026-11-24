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
use tool_vae\local\organisation;

/**
 * The link "VAE" in the primary navigation of the site (lot S3).
 *
 * The link is shown to whoever may open a tab of the organisation page, and to nobody else: a link to a page that refuses
 * is a fault. It is never shown in a session of "log in as". It is asked on every page of the site, so the question it
 * asks costs at most one reading, whatever the number of vaeistes the reader follows.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(hook_callbacks::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(organisation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(access::class)]
final class entry_link_test extends \advanced_testcase {
    /** @var \stdClass A vaeiste: holds viewown on their own dossier. */
    private $vaeiste;

    /** @var \stdClass A manager who follows the vaeiste and may not read a dossier of their own. */
    private $manager;

    /** @var \stdClass The direction: holds viewany at system level. */
    private $direction;

    /** @var \stdClass Somebody with no right of the plugin. */
    private $nobody;

    /** @var int The role that carries viewassigned, assigned in the user context of a followed vaeiste. */
    private $followerroleid;

    /** @var int A role assigned in user contexts that carries no right of the plugin. */
    private $plainroleid;

    /**
     * The competency subsystem is on; a vaeiste, a manager who follows them, the direction, and somebody with no right.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, competency_gateway::SUBSYSTEM);

        $generator = $this->getDataGenerator();
        $system = \context_system::instance();

        $this->vaeiste = $generator->create_user();
        $this->manager = $generator->create_user();
        $this->direction = $generator->create_user();
        $this->nobody = $generator->create_user();

        $ownroleid = $generator->create_role();
        assign_capability('tool/vae:viewown', CAP_ALLOW, $ownroleid, $system->id, true);
        role_assign($ownroleid, $this->vaeiste->id, \context_user::instance($this->vaeiste->id)->id);

        $this->followerroleid = $generator->create_role();
        assign_capability('tool/vae:viewassigned', CAP_ALLOW, $this->followerroleid, $system->id, true);
        role_assign($this->followerroleid, $this->manager->id, \context_user::instance($this->vaeiste->id)->id);

        $directionroleid = $generator->create_role();
        assign_capability('tool/vae:viewany', CAP_ALLOW, $directionroleid, $system->id, true);
        role_assign($directionroleid, $this->direction->id, $system->id);

        $this->plainroleid = $generator->create_role();

        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * The address of the organisation page, without a parameter.
     *
     * @return string
     */
    private static function page(): string {
        return (new \moodle_url('/admin/tool/vae/organisation.php'))->out(false);
    }

    /**
     * The address of the link for the current user, or null.
     *
     * @return string|null
     */
    private static function link(): ?string {
        $url = organisation::entry_url();

        return $url === null ? null : $url->out(false);
    }

    /**
     * A vaeiste gets the link, to the page without a tab.
     */
    public function test_a_vaeiste_gets_the_link(): void {
        $this->setUser($this->vaeiste);

        $this->assertSame(self::page(), self::link());
    }

    /**
     * A manager who follows a vaeiste gets the link, without any right on a dossier of their own.
     */
    public function test_a_manager_who_follows_a_vaeiste_gets_the_link(): void {
        $this->setUser($this->manager);

        $this->assertFalse(access::can_view((int) $this->manager->id), 'The manager has no dossier of their own to read.');
        $this->assertTrue(access::follows_anyone());
        $this->assertSame(self::page(), self::link());
    }

    /**
     * The direction gets the link.
     */
    public function test_the_direction_gets_the_link(): void {
        $this->setUser($this->direction);

        $this->assertSame(self::page(), self::link());
    }

    /**
     * Somebody with no right of the plugin gets no link, even with a role in another person's user context.
     */
    public function test_somebody_without_a_right_gets_no_link(): void {
        role_assign($this->plainroleid, $this->nobody->id, \context_user::instance($this->vaeiste->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->nobody);

        $this->assertFalse(access::follows_anyone());
        $this->assertNull(self::link());
    }

    /**
     * The guest gets no link.
     */
    public function test_the_guest_gets_no_link(): void {
        $this->setGuestUser();

        $this->assertNull(self::link());
    }

    /**
     * Nobody logged in gets no link.
     */
    public function test_nobody_logged_in_gets_no_link(): void {
        $this->setUser(null);

        $this->assertNull(self::link());
    }

    /**
     * The three people who get the link.
     *
     * @return array
     */
    public static function people_with_a_link(): array {
        return [
            'vaeiste' => ['vaeiste'],
            'manager' => ['manager'],
            'direction' => ['direction'],
        ];
    }

    /**
     * In a session of "log in as", nobody gets the link, whatever the page would open in that session.
     *
     * @param string $who The property that names the person the administrator logs in as.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('people_with_a_link')]
    public function test_no_link_in_a_session_of_log_in_as(string $who): void {
        $this->setUser($this->{$who});
        $this->assertNotNull(self::link(), 'Out of the session of "log in as", this person gets the link.');

        $this->setAdminUser();
        \core\session\manager::loginas((int) $this->{$who}->id, \context_system::instance());

        $this->assertTrue(\core\session\manager::is_loggedinas());
        $this->assertNull(self::link());
    }

    /**
     * With the competency subsystem off, the direction alone gets the link, to the tab of the site.
     */
    public function test_competencies_off_the_direction_alone_gets_the_link_to_the_site(): void {
        set_config('enabled', 0, competency_gateway::SUBSYSTEM);

        $this->setUser($this->direction);
        $this->assertSame(
            (new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => organisation::TAB_SITE]))->out(false),
            self::link()
        );

        $this->setUser($this->vaeiste);
        $this->assertNull(self::link());

        $this->setUser($this->manager);
        $this->assertNull(self::link());
    }

    /**
     * Holding viewassigned at system level gives no link on that ground: the capability then means "nobody", not
     * "everybody".
     */
    public function test_viewassigned_held_at_system_level_gives_no_link(): void {
        $wide = $this->getDataGenerator()->create_user();
        role_assign($this->followerroleid, $wide->id, \context_system::instance()->id);
        role_assign($this->followerroleid, $wide->id, \context_user::instance($this->vaeiste->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($wide);

        $this->assertFalse(access::follows_anyone());
        $this->assertNull(self::link());
    }

    /**
     * Out of "log in as", the link is shown exactly when the page has a tab to open: with the competency subsystem on,
     * when organisation::tabs() is not empty; off, when the tab of the site may be opened.
     */
    public function test_the_link_is_shown_exactly_when_the_page_opens_a_tab(): void {
        $both = $this->getDataGenerator()->create_user();
        $ownroleid = $this->getDataGenerator()->create_role();
        assign_capability('tool/vae:viewown', CAP_ALLOW, $ownroleid, \context_system::instance()->id, true);
        role_assign($ownroleid, $both->id, \context_user::instance($both->id)->id);
        role_assign($this->followerroleid, $both->id, \context_user::instance($this->vaeiste->id)->id);
        $wide = $this->getDataGenerator()->create_user();
        role_assign($this->followerroleid, $wide->id, \context_system::instance()->id);
        role_assign($this->followerroleid, $wide->id, \context_user::instance($this->vaeiste->id)->id);
        $plain = $this->getDataGenerator()->create_user();
        role_assign($this->plainroleid, $plain->id, \context_user::instance($this->vaeiste->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $accounts = [
            'vaeiste' => $this->vaeiste,
            'manager' => $this->manager,
            'direction' => $this->direction,
            'nobody' => $this->nobody,
            'both' => $both,
            'wide' => $wide,
            'plain' => $plain,
            'admin' => get_admin(),
            'guest' => guest_user(),
            'not logged in' => null,
        ];

        $shown = [];
        foreach ([1, 0] as $enabled) {
            set_config('enabled', $enabled, competency_gateway::SUBSYSTEM);
            foreach ($accounts as $name => $account) {
                $this->setUser($account);
                $expected = $enabled ? organisation::tabs() !== [] : organisation::can_open_tab(organisation::TAB_SITE);
                $this->assertSame($expected, self::link() !== null, "Account $name, competencies " . ($enabled ? 'on' : 'off'));
                if ($expected) {
                    $shown[] = "$name/$enabled";
                }
            }
        }

        // A rule that answers no to everyone would pass the loop above: the accounts that get the link are named.
        $this->assertSame(
            ['vaeiste/1', 'manager/1', 'direction/1', 'both/1', 'admin/1', 'direction/0', 'admin/0'],
            $shown
        );
    }

    /**
     * The readings the rule makes for a user, on a page where the core has already read that user's rights and context.
     *
     * The caches of contexts and rights are emptied first, so that a measure never benefits from the one before it; then
     * what any page of the site reads before the primary navigation is read again: the rights of the user, the context
     * of the user, and what the core keeps in its application caches from one page to the next - the definitions of the
     * roles and the setting of the competency subsystem. What remains is what the rule reads of its own.
     *
     * @param \stdClass $user
     * @return int
     */
    private function reads_of_the_rule(\stdClass $user): int {
        global $DB;

        accesslib_clear_all_caches_for_unit_testing();
        \context_helper::reset_caches();
        $this->setUser($user);
        \context_user::instance((int) $user->id);
        has_capability('moodle/site:config', \context_system::instance());
        competency_gateway::is_subsystem_enabled();
        get_role_definitions(array_keys(get_all_roles()));

        $before = $DB->perf_get_reads();
        organisation::entry_url();

        return $DB->perf_get_reads() - $before;
    }

    /**
     * The cost of the link does not grow with the number of vaeistes followed: one reading at most for who is neither the
     * direction nor a vaeiste, none for the direction and the vaeiste.
     */
    public function test_the_cost_does_not_grow_with_the_number_of_followed_vaeistes(): void {
        $generator = $this->getDataGenerator();

        $many = $generator->create_user();
        $idle = $generator->create_user();
        for ($i = 0; $i < 30; $i++) {
            $context = \context_user::instance($generator->create_user()->id);
            role_assign($this->followerroleid, $many->id, $context->id);
            role_assign($this->plainroleid, $idle->id, $context->id);
        }

        $one = $this->reads_of_the_rule($this->manager);
        $thirty = $this->reads_of_the_rule($many);
        $none = $this->reads_of_the_rule($idle);

        $this->assertSame($one, $thirty, 'Following 30 vaeistes costs what following one costs.');
        $this->assertLessThanOrEqual(1, $one, 'One reading at most for a manager.');
        $this->assertLessThanOrEqual(1, $none, 'One reading at most for 30 roles that follow nobody.');
        $this->assertSame(0, $this->reads_of_the_rule($this->direction), 'No reading of its own for the direction.');
        $this->assertSame(0, $this->reads_of_the_rule($this->vaeiste), 'No reading of its own for a vaeiste.');

        // The answers are still right after the caches were emptied.
        $this->setUser($many);
        $this->assertNotNull(self::link());
        $this->setUser($idle);
        $this->assertNull(self::link());
    }

    /**
     * The node of the primary navigation for the current user, built as the core builds it on a page.
     *
     * @return \navigation_node|false
     */
    private static function node() {
        global $PAGE;

        $PAGE = new \moodle_page();
        $PAGE->set_url('/');
        $view = new \core\navigation\views\primary($PAGE);
        $view->initialise();

        return $view->get(hook_callbacks::PRIMARY_NODE_KEY);
    }

    /**
     * The node is in the primary navigation, labelled "VAE" and leading to the page, for whoever has a right; it is not
     * there for anybody else.
     */
    public function test_the_node_is_in_the_primary_navigation_for_whoever_has_a_right(): void {
        foreach (['vaeiste', 'manager', 'direction'] as $who) {
            $this->setUser($this->{$who});
            $node = self::node();
            $this->assertInstanceOf(\navigation_node::class, $node, "No node for the $who.");
            $this->assertSame(get_string('navlink', 'tool_vae'), $node->text);
            $this->assertSame(self::page(), $node->action()->out(false));
        }

        $this->setUser($this->nobody);
        $this->assertFalse(self::node());

        $this->setGuestUser();
        $this->assertFalse(self::node());

        $this->setAdminUser();
        \core\session\manager::loginas((int) $this->direction->id, \context_system::instance());
        $this->assertFalse(self::node());
    }

    /**
     * With the competency subsystem off, the node leads the direction to the tab of the site, and is not there for a vaeiste.
     */
    public function test_the_node_leads_to_the_site_when_competencies_are_off(): void {
        set_config('enabled', 0, competency_gateway::SUBSYSTEM);

        $this->setUser($this->direction);
        $node = self::node();
        $this->assertInstanceOf(\navigation_node::class, $node);
        $this->assertSame(
            (new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => organisation::TAB_SITE]))->out(false),
            $node->action()->out(false)
        );

        $this->setUser($this->vaeiste);
        $this->assertFalse(self::node());
    }

    /**
     * While the site is upgraded, the listener says nothing: no node, even for the direction.
     *
     * The core builds no navigation during an upgrade (the list of courses refuses to be read then), so the listener is
     * called here on its own, on a view that is not initialised.
     */
    public function test_no_node_while_the_site_is_upgraded(): void {
        global $CFG, $PAGE;

        $this->setUser($this->direction);
        $PAGE = new \moodle_page();
        $PAGE->set_url('/');

        $CFG->upgraderunning = time();
        try {
            $view = new \core\navigation\views\primary($PAGE);
            hook_callbacks::extend_primary_navigation(new \core\hook\navigation\primary_extend($view));
            $this->assertFalse($view->get(hook_callbacks::PRIMARY_NODE_KEY));
        } finally {
            unset($CFG->upgraderunning);
        }

        $view = new \core\navigation\views\primary($PAGE);
        hook_callbacks::extend_primary_navigation(new \core\hook\navigation\primary_extend($view));
        $this->assertInstanceOf(\navigation_node::class, $view->get(hook_callbacks::PRIMARY_NODE_KEY));
    }
}
