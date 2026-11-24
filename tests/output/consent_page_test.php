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

use tool_vae\local\consent_manager;

/**
 * The page "Accord pour annoter" (consent.php) drawn by tool_vae\output\consent_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration, for every state: nobody to
 * receive the consent; the vaeiste, with a consent given (a button to take it back), taken back by a person who may receive
 * it again (a button to give it), taken back by a person who no longer may (no button), never given (a button to give it); a
 * reader who is not the vaeiste (no button column); the dated history, with a line restored from an archive and a line whose
 * person is gone; names that are the figure 0. The "before" is the code of the page as it was, kept here as the expected
 * value, the names it read being given to it as they are given to the class. Also: that the context holds plain texts, that a
 * script in a name comes out escaped, that the template renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(consent_page::class)]
final class consent_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    /** @var \moodle_url The dossier of the vaeiste. */
    private \moodle_url $dossier;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->url = new \moodle_url('/admin/tool/vae/consent.php', ['userid' => 7]);
        $this->dossier = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => 7]);
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param consent_page $page
     * @return string HTML.
     */
    private function render(consent_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The body of consent.php before its migration (its code, kept as the expected value).
     *
     * @param int[] $people
     * @param \stdClass[] $current
     * @param int[] $eligible
     * @param bool $canmanage
     * @param \stdClass[] $history
     * @param string[] $names The names the page read, by user id; 0 for a line with no person.
     * @param \moodle_url $url
     * @param int $candidateid
     * @return string HTML.
     */
    private static function before(
        array $people,
        array $current,
        array $eligible,
        bool $canmanage,
        array $history,
        array $names,
        \moodle_url $url,
        int $candidateid
    ): string {
        $nameof = fn(?int $userid): string => $names[$userid ?? 0] ?? get_string('unknownuser');

        $body = \html_writer::tag('p', s(get_string('consentintro', 'tool_vae')));
        if (!$people) {
            $body .= \html_writer::tag('p', s(get_string('consentnone', 'tool_vae')));
        } else {
            $table = new \html_table();
            $table->head = [get_string('fullnameuser'), get_string('status')];
            if ($canmanage) {
                $table->head[] = '';
            }
            foreach ($people as $personid) {
                $state = isset($current[$personid]) ? (string) $current[$personid]->state : '';
                $label = $state === consent_manager::GIVEN ? 'consentgiven'
                    : ($state === consent_manager::WITHDRAWN ? 'consentwithdrawn' : 'consentnone');
                $row = [s($nameof($personid)), s(get_string($label, 'tool_vae'))];
                if ($canmanage) {
                    $button = '';
                    if ($state === consent_manager::GIVEN) {
                        $button = self::core()->single_button(
                            new \moodle_url($url, ['action' => 'withdraw', 'managerid' => $personid]),
                            get_string('consentwithdraw', 'tool_vae'),
                            'post'
                        );
                    } else if (in_array($personid, $eligible, true)) {
                        $button = self::core()->single_button(
                            new \moodle_url($url, ['action' => 'give', 'managerid' => $personid]),
                            get_string('consentgive', 'tool_vae'),
                            'post'
                        );
                    }
                    $row[] = $button;
                }
                $table->data[] = $row;
            }
            $body .= \html_writer::table($table);
        }

        if ($history) {
            $body .= \html_writer::tag('h3', s(get_string('consenthistory', 'tool_vae')));
            $table = new \html_table();
            $table->head = [get_string('date'), get_string('fullnameuser'), get_string('status')];
            foreach ($history as $line) {
                $table->data[] = [
                    s(userdate((int) $line->timecreated, get_string('strftimedatetime', 'langconfig'))),
                    s($line->restored
                        ? get_string('consentrestored', 'tool_vae')
                        : $nameof($line->managerid ? (int) $line->managerid : null)),
                    s(get_string($line->state === consent_manager::GIVEN ? 'consentgiven' : 'consentwithdrawn', 'tool_vae')),
                ];
            }
            $body .= \html_writer::table($table);
        }

        return $body . \html_writer::tag('p', \html_writer::link(
            new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]),
            get_string('selfposbackdossier', 'tool_vae')
        ));
    }

    /**
     * Four people and the lines that stand for three of them, as the page reads them.
     *
     * @return array [people, current, eligible, history, names]
     */
    private static function data(): array {
        $current = [
            11 => (object) ['managerid' => 11, 'state' => consent_manager::GIVEN],
            12 => (object) ['managerid' => 12, 'state' => consent_manager::WITHDRAWN],
            13 => (object) ['managerid' => 13, 'state' => consent_manager::WITHDRAWN],
        ];
        $eligible = [11, 12, 14];
        $history = [
            (object) ['timecreated' => 1792411200, 'managerid' => 11, 'state' => consent_manager::GIVEN, 'restored' => false],
            (object) ['timecreated' => 1792411300, 'managerid' => 12, 'state' => consent_manager::GIVEN, 'restored' => true],
            (object) ['timecreated' => 1792411400, 'managerid' => 12, 'state' => consent_manager::WITHDRAWN, 'restored' => false],
            (object) ['timecreated' => 1792411500, 'managerid' => null, 'state' => consent_manager::WITHDRAWN, 'restored' => false],
        ];
        $names = [11 => 'Marie Lefèvre', 12 => 'Hugo Bernard', 13 => 'Sophie Petit', 14 => 'Léa Martin',
            0 => get_string('unknownuser')];

        return [[11, 12, 13, 14], $current, $eligible, $history, $names];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = new consent_page([], [], [], false, [], [], $this->url, $this->dossier);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/consent_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        [$people, $current, $eligible, $history, $names] = self::data();
        $zeros = [11 => '0', 12 => '0', 13 => '0', 14 => '0', 0 => '0'];

        $cases = [
            'nobody, no history' => [[], [], [], true, [], []],
            'nobody, a history' => [[], [], [], false, $history, $names],
            'the vaeiste' => [$people, $current, $eligible, true, $history, $names],
            'another reader' => [[11, 12, 13], $current, [], false, $history, $names],
            'the vaeiste, no history' => [$people, $current, $eligible, true, [], $names],
            // A name that is the figure 0 was drawn before; a template that tests the text itself would drop it.
            'names that are 0' => [$people, $current, $eligible, true, $history, $zeros],
        ];
        foreach ($cases as $name => [$p, $c, $e, $canmanage, $h, $n]) {
            $expected = self::before($p, $c, $e, $canmanage, $h, $n, $this->url, 7);
            $html = $this->render(new consent_page($p, $c, $e, $canmanage, $h, $n, $this->url, $this->dossier));
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$people, $current, $eligible, $history, $names] = self::data();
        $output = $PAGE->get_renderer('tool_vae');

        $context = (new consent_page($people, $current, $eligible, true, $history, $names, $this->url, $this->dossier))
            ->export_for_template($output);
        $this->assertSame(get_string('consentintro', 'tool_vae'), $context['intro']);
        $this->assertFalse($context['none']);
        $this->assertStringContainsString('Léa Martin', $context['tablehtml']);
        $this->assertSame(get_string('consenthistory', 'tool_vae'), $context['history']['label']);
        $this->assertStringContainsString(get_string('consentrestored', 'tool_vae'), $context['history']['tablehtml']);
        $this->assertSame(
            ['url' => $this->dossier->out(false), 'label' => get_string('selfposbackdossier', 'tool_vae')],
            $context['back']
        );

        $none = (new consent_page([], [], [], true, [], [], $this->url, $this->dossier))->export_for_template($output);
        $this->assertSame(get_string('consentnone', 'tool_vae'), $none['none']);
        $this->assertFalse($none['tablehtml']);
        $this->assertFalse($none['history']);
    }

    public function test_a_script_in_a_name_comes_out_escaped(): void {
        $this->resetAfterTest();
        [$people, $current, $eligible, $history] = self::data();
        $names = [11 => '<script>alert(1)</script>', 12 => '<script>alert(2)</script>', 13 => 'C', 14 => 'D', 0 => 'E'];

        $html = $this->render(new consent_page($people, $current, $eligible, true, $history, $names, $this->url, $this->dossier));

        foreach ([1, 2] as $n) {
            $this->assertStringContainsString('&lt;script&gt;alert(' . $n . ')&lt;/script&gt;', $html);
        }
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/consent_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/consent_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['tablehtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/consent_page', $context);

        $this->assertStringContainsString('<h3>', $html);
    }
}
