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

/**
 * The settings of the plugin and its pages in the administration tree (lot S4).
 *
 * WHAT IS ASSERTED. That the settings page carries exactly the twenty settings it carried before the lot, under the same full
 * names and with the same defaults - a renamed setting would lose the value a site installed; that they stand under seven
 * group headings, each followed by the settings of its group and by them only, and that the text of a heading has one
 * sentence per setting of its group; that the eleven pages of the plugin sit in a category "VAE" directly under
 * Competencies, "Getting started" first; and that each page is found for whoever held it before the lot - and for nobody
 * else - which fails if the category is created only for some readers, since a page then has no parent to be filed under.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class settings_tree_test extends \advanced_testcase {
    /** @var string[] The pages of the category, in the order they are shown. */
    private const PAGES = [
        'toolvae_setup',
        'toolvae',
        'toolvae_roles',
        'tool_vae_settings',
        'toolvae_organisation',
        'toolvae_pending',
        'toolvae_renewals',
        'toolvae_bookletauth',
        'toolvae_supersession',
        'toolvae_referents',
        'toolvae_refs',
    ];

    /**
     * The groups of the settings page, in order: the heading, then the full names of its settings with their defaults.
     *
     * The names and defaults are those of settings.php at commit 5218a68, before the lot: none may change.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function settinggroups(): array {
        return [
            'tool_vae_groupattest' => [
                'tool_vae/managerwithdrawown' => 0,
                'tool_vae/overduedays' => 0,
            ],
            'tool_vae_groupdossiers' => [
                'tool_vae/validationopen' => 0,
                'tool_vae/reflectionopen' => 0,
                'tool_vae/scopeopen' => 0,
                'tool_vae/refopen' => 0,
            ],
            'tool_vae_grouppieces' => [
                'tool_vae/piecesopen' => 0,
                'tool_vae/piecemaxbytes' => 5 * 1048576,
                'tool_vae/piecemaxpercandidate' => 30 * 1048576,
                'tool_vae/piecenoantivirus' => 0,
            ],
            'tool_vae_groupai' => [
                'tool_vae/aiopen' => 0,
                'tool_vae/aimaxchars' => 3000,
                'tool_vae/aidaily' => 25,
            ],
            'tool_vae_groupcopies' => [
                'tool_vae/copyenabled' => 0,
                'tool_vae/copyformat' => 'docx',
            ],
            'tool_vae_groupselfpos' => [
                'tool_vae/selfposlabel1' => '',
                'tool_vae/selfposlabel2' => '',
                'tool_vae/selfposlabel3' => '',
                'tool_vae/selfposlabel4' => '',
            ],
            'tool_vae_groupimport' => [
                'tool_vae/importscaleid' => 0,
            ],
        ];
    }

    /**
     * The full name of a setting as the core stores it: plugin/name, or the name alone.
     *
     * @param \admin_setting $setting
     * @return string
     */
    private static function full(\admin_setting $setting): string {
        return $setting->plugin ? $setting->plugin . '/' . $setting->name : $setting->name;
    }

    /**
     * The whole tree, built for the administrator.
     *
     * @return \admin_root
     */
    private function tree_as_admin(): \admin_root {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->setAdminUser();
        return admin_get_root(true, true);
    }

    public function test_the_page_carries_the_twenty_settings_with_their_names_and_defaults(): void {
        $this->resetAfterTest();
        $page = $this->tree_as_admin()->locate('tool_vae_settings');
        $this->assertInstanceOf(\admin_settingpage::class, $page);

        $expected = array_merge(...array_values(self::settinggroups()));
        $this->assertCount(20, $expected);
        $found = [];
        foreach ($page->settings as $setting) {
            if ($setting instanceof \admin_setting_heading) {
                continue;
            }
            $found[self::full($setting)] = $setting->get_defaultsetting();
        }
        ksort($expected);
        ksort($found);
        $this->assertSame($expected, $found);
    }

    public function test_seven_headings_each_followed_by_the_settings_of_its_group(): void {
        $this->resetAfterTest();
        $page = $this->tree_as_admin()->locate('tool_vae_settings');

        $expected = [];
        foreach (self::settinggroups() as $heading => $settings) {
            $expected[] = $heading;
            foreach (array_keys($settings) as $name) {
                $expected[] = $name;
            }
        }
        $found = [];
        $headings = 0;
        foreach ($page->settings as $setting) {
            $found[] = self::full($setting);
            if ($setting instanceof \admin_setting_heading) {
                $headings++;
            }
        }
        $this->assertSame(7, $headings);
        $this->assertSame($expected, $found);
    }

    public function test_the_text_of_a_heading_has_one_sentence_per_setting_of_its_group(): void {
        $this->resetAfterTest();
        $page = $this->tree_as_admin()->locate('tool_vae_settings');

        $sentences = [];
        $expected = [];
        foreach (self::settinggroups() as $heading => $settings) {
            $expected[$heading] = count($settings);
        }
        foreach ($page->settings as $setting) {
            if (!($setting instanceof \admin_setting_heading)) {
                continue;
            }
            $this->assertNotSame('', trim((string) $setting->visiblename), $setting->name . ' has no title.');
            // A sentence ends with a full stop followed by a space or the end; ".docx" is not an end.
            $sentences[$setting->name] = preg_match_all('/\.(\s|$)/', trim((string) $setting->description));
        }
        $this->assertSame($expected, $sentences);
    }

    public function test_the_eleven_pages_sit_in_the_category_vae_under_competencies_getting_started_first(): void {
        $this->resetAfterTest();
        $root = $this->tree_as_admin();

        $competencies = $root->locate('competencies');
        $children = array_map(fn($child) => $child->name, $competencies->children);
        $this->assertContains('toolvaefolder', $children);
        // No page of the plugin is left directly under Competencies.
        $this->assertSame([], array_values(array_intersect(self::PAGES, $children)));

        $folder = $root->locate('toolvaefolder');
        $this->assertInstanceOf(\admin_category::class, $folder);
        $this->assertSame(get_string('adminfolder', 'tool_vae'), (string) $folder->visiblename);
        $this->assertSame(self::PAGES, array_map(fn($child) => $child->name, $folder->children));
    }

    public function test_each_reader_finds_the_pages_of_their_right_and_no_other(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/adminlib.php');
        $generator = $this->getDataGenerator();
        $system = \context_system::instance();
        $holders = [
            'viewany' => ['tool/vae:viewany'],
            'rolemanage' => ['moodle/role:manage'],
            'import' => ['tool/vae:import'],
            'nothing' => [],
        ];
        $expected = [
            'viewany' => ['toolvae_organisation', 'toolvae_pending'],
            'rolemanage' => ['toolvae_roles'],
            'import' => ['toolvae_setup', 'toolvae', 'toolvae_supersession', 'toolvae_referents', 'toolvae_refs'],
            'nothing' => [],
        ];
        $users = [];
        foreach ($holders as $name => $capabilities) {
            $users[$name] = $generator->create_user();
            if ($capabilities) {
                $role = $generator->create_role();
                foreach ($capabilities as $capability) {
                    assign_capability($capability, CAP_ALLOW, $role, $system->id, true);
                }
                role_assign($role, $users[$name]->id, $system->id);
            }
        }
        accesslib_clear_all_caches_for_unit_testing();

        foreach ($users as $name => $user) {
            $this->setUser($user);
            $root = admin_get_root(true, false);
            $found = [];
            foreach (self::PAGES as $page) {
                if ($root->locate($page) !== null) {
                    $found[] = $page;
                }
            }
            $this->assertSame($expected[$name], $found, "Pages found for '$name'.");

            // Each page found is filed in the category, which a reader with no page does not see.
            $folder = $root->locate('toolvaefolder');
            $this->assertNotNull($folder, "The category is missing for '$name'.");
            $this->assertSame($expected[$name], array_map(fn($child) => $child->name, $folder->children));
            $this->assertSame($expected[$name] !== [], $folder->check_access(), "Access to the category for '$name'.");
        }
    }
}
