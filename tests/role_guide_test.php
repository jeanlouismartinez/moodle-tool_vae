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

use tool_vae\local\competency_gateway;
use tool_vae\local\organisation;
use tool_vae\local\role_guide;
use tool_vae\output\guide_page;
use tool_vae\output\organisation_guide_link;

/**
 * The guides of one page for each role (lot S5): their content, who may open them, and the link "Help" of each tab.
 *
 * The guide of a role is opened by whoever may open the tab of that role on the organisation page, by the same predicates
 * and with the same refusals; a role the page does not know is refused, never replaced by another.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(role_guide::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(guide_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(organisation_guide_link::class)]
final class role_guide_test extends \advanced_testcase {
    /**
     * A piece drawn by the renderer of the plugin, as the old screen organisation_screen was drawn under PHPUnit (its calls
     * ported here by lot 7, MMR-520 ticket 4, wave 4b; no assertion changed).
     *
     * @param \core\output\renderable $piece
     * @return string HTML.
     */
    private static function draw(\core\output\renderable $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae')->render($piece);
    }

    /** @var \stdClass A vaeiste: holds viewown on their own dossier. */
    private $vaeiste;

    /** @var \stdClass A manager who follows the vaeiste and may not read a dossier of their own. */
    private $manager;

    /** @var \stdClass The direction: holds viewany at system level. */
    private $direction;

    /** @var \stdClass Somebody with no right of the plugin. */
    private $nobody;

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

        $followerroleid = $generator->create_role();
        assign_capability('tool/vae:viewassigned', CAP_ALLOW, $followerroleid, $system->id, true);
        role_assign($followerroleid, $this->manager->id, \context_user::instance($this->vaeiste->id)->id);

        $directionroleid = $generator->create_role();
        assign_capability('tool/vae:viewany', CAP_ALLOW, $directionroleid, $system->id, true);
        role_assign($directionroleid, $this->direction->id, $system->id);

        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * The three roles that have a guide.
     *
     * @return array
     */
    public static function roles(): array {
        return [
            'vaeiste' => ['vaeiste'],
            'manager' => ['manager'],
            'direction' => ['direction'],
        ];
    }

    /**
     * The strings of a language file of the plugin, read from the file and not from the cache of the core.
     *
     * @param string $lang fr or en.
     * @return string[] Key => text.
     */
    private static function strings(string $lang): array {
        require_once(__DIR__ . '/fixtures/lang_files.php');
        $string = \tool_vae\fixtures\lang_files::strings($lang);

        return $string;
    }

    /**
     * The keys of a language file that begin with "guide", in the order of the file.
     *
     * @param string $lang fr or en.
     * @return string[]
     */
    private static function guide_keys(string $lang): array {
        return array_values(array_filter(array_keys(self::strings($lang)), fn($key) => str_starts_with($key, 'guide')));
    }

    /**
     * Each guide has a title, an introduction and at least four sections, none of them empty.
     *
     * @param string $role
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_each_guide_has_a_title_an_introduction_and_four_sections(string $role): void {
        $guide = role_guide::guide($role);

        $this->assertNotSame('', trim($guide->title));
        $this->assertNotSame('', trim($guide->intro));
        $this->assertGreaterThanOrEqual(4, count($guide->sections));
        foreach ($guide->sections as $section) {
            $this->assertNotSame('', trim($section->title));
            $items = count($section->texts) + count($section->steps) + count($section->notes) + count($section->words);
            $this->assertGreaterThan(0, $items, "The section {$section->key} of the guide $role says nothing.");
            foreach (array_merge($section->texts, $section->steps, $section->notes) as $text) {
                $this->assertNotSame('', trim($text));
            }
            foreach ($section->words as $word) {
                $this->assertNotSame('', trim($word->word));
                $this->assertNotSame('', trim($word->definition));
            }
        }
        // The last section is the words to know.
        $this->assertNotEmpty(end($guide->sections)->words, "The guide $role has no words to know.");
    }

    /**
     * Every key of a guide exists in French and in English, and the two files hold the same keys of guide.
     */
    public function test_every_guide_key_exists_in_both_languages(): void {
        // French where lang/fr is present: the package of the plugin ships English only.
        require_once(__DIR__ . '/fixtures/lang_files.php');
        $fr = \tool_vae\fixtures\lang_files::present('fr') ? self::strings('fr') : null;
        $en = self::strings('en');

        $used = role_guide::string_keys();
        $this->assertGreaterThanOrEqual(30, count($used));
        foreach ($used as $key) {
            if ($fr !== null) {
                $this->assertArrayHasKey($key, $fr, "The key $key is missing in French.");
            }
            $this->assertArrayHasKey($key, $en, "The key $key is missing in English.");
        }
        if ($fr !== null) {
            $this->assertSame(self::guide_keys('fr'), self::guide_keys('en'));
            // No key of guide is left unused in the files: a string nobody reads is a text nobody checks.
            $this->assertEqualsCanonicalizing($used, self::guide_keys('fr'));
        }
    }

    /**
     * No French string of a guide says "candidat", gives a percentage or refers to a file of docs/.
     */
    public function test_no_french_guide_string_says_candidat_or_gives_a_percentage(): void {
        require_once(__DIR__ . '/fixtures/lang_files.php');
        \tool_vae\fixtures\lang_files::skip_unless_present('fr');
        $fr = self::strings('fr');

        foreach (self::guide_keys('fr') as $key) {
            $this->assertStringNotContainsStringIgnoringCase('candidat', $fr[$key], $key);
            $this->assertStringNotContainsString('%', $fr[$key], $key);
            $this->assertStringNotContainsString('docs/', $fr[$key], $key);
            $this->assertStringNotContainsString('vaéiste', $fr[$key], $key);
        }
    }

    /**
     * Who may open which guide: the right of the tab of the role, and nothing else.
     *
     * @return array
     */
    public static function who_opens_what(): array {
        return [
            'vaeiste' => ['vaeiste', ['vaeiste']],
            'manager' => ['manager', ['manager']],
            'direction' => ['direction', ['direction']],
            'nobody' => ['nobody', []],
        ];
    }

    /**
     * The right to open each guide follows that of the tab of its role, and a refused guide is refused by require_open().
     *
     * @param string $who The property that names the person.
     * @param string[] $opens The roles whose guide that person may open.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('who_opens_what')]
    public function test_the_right_to_open_a_guide_follows_that_of_the_tab(string $who, array $opens): void {
        $this->setUser($this->{$who});

        foreach (role_guide::ROLES as $role => $tab) {
            $expected = in_array($role, $opens, true);
            $this->assertSame($expected, role_guide::can_open($role), "$who, guide $role");
            $this->assertSame(organisation::can_open_tab($tab), role_guide::can_open($role), "$who, guide $role and its tab");
            if ($expected) {
                role_guide::require_open($role);
            } else {
                try {
                    role_guide::require_open($role);
                    $this->fail("$who opened the guide $role.");
                } catch (\required_capability_exception $e) {
                    $this->assertStringContainsString('nopermissions', $e->errorcode);
                }
            }
        }
    }

    /**
     * In a session of "log in as" a vaeiste, the guide of the vaeiste is refused, as the tab "My pathway" is.
     */
    public function test_the_guide_of_the_vaeiste_is_refused_in_a_session_of_log_in_as(): void {
        $this->setUser($this->vaeiste);
        $this->assertTrue(role_guide::can_open(role_guide::VAEISTE));

        $this->setAdminUser();
        \core\session\manager::loginas((int) $this->vaeiste->id, \context_system::instance());

        $this->assertFalse(role_guide::can_open(role_guide::VAEISTE));
        $this->expectException(\required_capability_exception::class);
        role_guide::require_open(role_guide::VAEISTE);
    }

    /**
     * A role the page does not know is refused, for everybody, the site administrator included: never replaced by another.
     */
    public function test_an_unknown_role_is_refused(): void {
        $this->setAdminUser();

        foreach (['', 'admin', 'Vaeiste', 'mine', 'site', 'candidate'] as $role) {
            $this->assertFalse(role_guide::can_open($role), "Role \"$role\"");
            try {
                role_guide::require_open($role);
                $this->fail("The role \"$role\" opened a guide.");
            } catch (\moodle_exception $e) {
                $this->assertSame('guideunknown', $e->errorcode, "Role \"$role\"");
            }
        }
    }

    /**
     * The message of a refused guide names "the vaeiste, the manager and the direction": the French word for the manager
     * ("responsable") opens the guide of the manager, as the address with the code name "manager" does, and any other word is
     * left as typed, so that the refusal of an unknown role stays.
     */
    public function test_the_french_name_of_the_manager_opens_the_guide_of_the_manager(): void {
        $this->assertSame(role_guide::MANAGER, role_guide::named('responsable'));
        $this->assertSame(role_guide::MANAGER, role_guide::named('manager'));
        $this->assertSame(role_guide::VAEISTE, role_guide::named('vaeiste'));
        $this->assertSame(role_guide::DIRECTION, role_guide::named('direction'));
        $this->assertSame('Responsable', role_guide::named('Responsable'));
        $this->assertSame('admin', role_guide::named('admin'));
        $this->assertSame('', role_guide::named(''));
        $this->assertFalse(role_guide::can_open(role_guide::named('admin')));
    }

    /**
     * The link "Help" of a tab is drawn for who may open the guide of that tab, and for nobody else.
     */
    public function test_the_help_link_is_drawn_in_the_tab_for_who_may_open_the_guide(): void {
        $expected = [
            'vaeiste' => organisation::TAB_MINE,
            'manager' => organisation::TAB_FOLLOWED,
            'direction' => organisation::TAB_SITE,
        ];
        foreach ($expected as $who => $tab) {
            $this->setUser($this->{$who});
            $role = role_guide::role_of_tab($tab);
            $html = self::draw(new organisation_guide_link($tab));
            $this->assertStringContainsString(s(role_guide::url($role)->out(false)), $html, $who);
            $this->assertStringContainsString(get_string('guidelink_' . $role, 'tool_vae'), $html, $who);
            foreach ($expected as $othertab) {
                if ($othertab !== $tab) {
                    $this->assertSame('', self::draw(new organisation_guide_link($othertab)), "$who, tab $othertab");
                }
            }
        }

        $this->setUser($this->nobody);
        foreach ($expected as $tab) {
            $this->assertSame('', self::draw(new organisation_guide_link($tab)));
        }
        $this->assertSame('', self::draw(new organisation_guide_link('unknown')));
    }

    /**
     * The page of a guide draws its introduction, its sections, the sentence on the other functions and the way back.
     *
     * @param string $role
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_the_page_draws_the_whole_guide(string $role): void {
        $this->setUser($this->{$role});
        $guide = role_guide::guide($role);

        $html = $this->render($role);

        $this->assertStringContainsString(s($guide->intro), $html);
        foreach ($guide->sections as $section) {
            $this->assertStringContainsString(s($section->title), $html);
            foreach (array_merge($section->texts, $section->steps, $section->notes) as $text) {
                $this->assertStringContainsString(s($text), $html);
            }
            foreach ($section->words as $word) {
                $this->assertStringContainsString(s($word->definition), $html);
            }
        }
        $this->assertStringContainsString(s(get_string('guidemore', 'tool_vae')), $html);
        $back = new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => role_guide::ROLES[$role]]);
        $this->assertStringContainsString(s($back->out(false)), $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    /**
     * The guide of the direction leads to the page of the declarations awaiting an attestation, which asks the same right.
     */
    public function test_the_guide_of_the_direction_leads_to_the_pending_declarations(): void {
        $this->setUser($this->direction);

        $html = $this->render(role_guide::DIRECTION);

        $this->assertStringContainsString(s((new \moodle_url('/admin/tool/vae/pending.php'))->out(false)), $html);
        $this->assertTrue(has_capability('tool/vae:viewany', \context_system::instance()));
    }

    /**
     * The template renders the example context of its docblock.
     */
    public function test_the_template_renders_its_example_context(): void {
        global $OUTPUT;

        $file = __DIR__ . '/../templates/guide_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/guide_page', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*\})\s*\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = $OUTPUT->render_from_template('tool_vae/guide_page', $context);

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('class="tool-vae-guide"', $html);
    }

    /**
     * The page is drawn by the renderer of the plugin from the template tool_vae/guide_page; its context holds plain texts.
     */
    public function test_the_page_is_a_named_template_with_a_context_of_plain_texts(): void {
        global $PAGE;
        $this->setUser($this->vaeiste);
        $renderer = $PAGE->get_renderer('tool_vae');
        $page = new guide_page(role_guide::VAEISTE);

        $context = $page->export_for_template($renderer);

        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/guide_page', $page->get_template_name($renderer));
        $guide = role_guide::guide(role_guide::VAEISTE);
        $this->assertSame($guide->intro, $context['intro']);
        $this->assertCount(count($guide->sections), $context['sections']);
        $this->assertSame('tool-vae-guide-' . $guide->sections[0]->key, $context['sections'][0]['id']);
        $this->assertSame(get_string('guidemore', 'tool_vae'), $context['more']);
        $back = new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => role_guide::ROLES[role_guide::VAEISTE]]);
        $this->assertSame($back->out(false), $context['back']['url']);
    }

    /**
     * A text of the context that holds a script comes out escaped: the template uses {{ }} only.
     */
    public function test_a_script_in_the_context_comes_out_escaped(): void {
        global $PAGE;
        $this->setUser($this->vaeiste);
        $renderer = $PAGE->get_renderer('tool_vae');
        $context = (new guide_page(role_guide::VAEISTE))->export_for_template($renderer);
        $context['intro'] = '<script>alert(1)</script>';
        $context['sections'][0]['title'] = '<script>alert(2)</script>';
        $context['back']['label'] = '<script>alert(3)</script>';

        $html = $renderer->render_from_template('tool_vae/guide_page', $context);

        $this->assertStringNotContainsString('<script', $html);
        foreach ([1, 2, 3] as $n) {
            $this->assertStringContainsString('&lt;script&gt;alert(' . $n . ')&lt;/script&gt;', $html);
        }
        $source = file_get_contents(__DIR__ . '/../templates/guide_page.mustache');
        $this->assertStringNotContainsString('{{{', $source);
    }

    /**
     * The page as guide.php draws it: by the renderer of the plugin.
     *
     * @param string $role
     * @return string HTML.
     */
    private function render(string $role): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae')->render(new guide_page($role));
    }
}
