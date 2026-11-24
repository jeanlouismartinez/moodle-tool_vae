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

/**
 * The page of the authorizations to produce the archive of the structure (booklet_authorize.php), drawn by
 * tool_vae\output\booklet_authorize_page and its form tool_vae\output\booklet_authorize_form (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration, for every state: no manager
 * to authorize; managers, one authorized (a button to take it back) and the others not (a button to authorize); a name that
 * is the figure 0. The "before" is the code of the page as it was, kept here as the expected value. Also: that the context
 * holds plain texts, that a script in a name comes out escaped, that the templates place as HTML only the keys named ...html,
 * that they render their example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(booklet_authorize_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(booklet_authorize_form::class)]
final class booklet_authorize_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->url = new \moodle_url('/admin/tool/vae/booklet_authorize.php');
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param booklet_authorize_page $page
     * @return string HTML.
     */
    private function render(booklet_authorize_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The body of booklet_authorize.php before its migration, below the heading (its code, kept as the expected value).
     *
     * @param array[] $managers Each {id, name, stands}, as the page reads them.
     * @param \moodle_url $url
     * @return string HTML.
     */
    private static function before(array $managers, \moodle_url $url): string {
        $html = \html_writer::tag('p', s(get_string('bookletauthintro', 'tool_vae')));
        if (!$managers) {
            return $html . \html_writer::tag('p', s(get_string('bookletauthnone', 'tool_vae')));
        }
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-bookletauth';
        $table->head = [
            get_string('bookletauthcolmanager', 'tool_vae'),
            get_string('bookletauthcolstatus', 'tool_vae'),
            get_string('bookletauthcolaction', 'tool_vae'),
        ];
        foreach ($managers as $manager) {
            $stands = $manager['stands'];
            $verb = $stands ? 'revoke' : 'grant';
            $form = \html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline'])
                . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
                . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'managerid', 'value' => (int) $manager['id']])
                . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $verb])
                . \html_writer::tag(
                    'button',
                    s(get_string($stands ? 'bookletauthrevoke' : 'bookletauthgrant', 'tool_vae')),
                    ['type' => 'submit', 'class' => 'btn btn-secondary btn-sm']
                )
                . \html_writer::end_tag('form');
            $table->data[] = [
                s($manager['name']),
                s(get_string($stands ? 'bookletauthstands' : 'bookletauthnotyet', 'tool_vae')),
                $form,
            ];
        }

        return $html . \html_writer::table($table);
    }

    /**
     * Three managers, the second one authorized.
     *
     * @return array[]
     */
    private static function managers(): array {
        return [
            ['id' => 6, 'name' => 'Sophie Bernard', 'stands' => false],
            ['id' => 4, 'name' => 'Marie Lefèvre', 'stands' => true],
            ['id' => 38, 'name' => 'Paul Essai-Responsable', 'stands' => false],
        ];
    }

    public function test_the_page_and_its_form_are_named_templates(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = new booklet_authorize_page([], $this->url);
        $form = new booklet_authorize_form($this->url, 4, true);

        foreach ([$page, $form] as $piece) {
            $this->assertInstanceOf(\core\output\renderable::class, $piece);
            $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
        }
        $this->assertSame('tool_vae/booklet_authorize_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
        $this->assertSame('tool_vae/booklet_authorize_form', $form->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();

        $cases = [
            'nobody' => [],
            'managers' => self::managers(),
            // A name that is the figure 0 was drawn before; a template that tests the text itself would drop it.
            'a name that is 0' => [['id' => 9, 'name' => '0', 'stands' => true], ['id' => 0, 'name' => '0', 'stands' => false]],
        ];
        foreach ($cases as $name => $managers) {
            $expected = self::before($managers, $this->url);
            $html = $this->render(new booklet_authorize_page($managers, $this->url));
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $output = $PAGE->get_renderer('tool_vae');

        $context = (new booklet_authorize_page(self::managers(), $this->url))->export_for_template($output);
        $this->assertSame(get_string('bookletauthintro', 'tool_vae'), $context['intro']);
        $this->assertFalse($context['none']);
        $this->assertStringContainsString('tool-vae-bookletauth', $context['tablehtml']);
        $this->assertStringContainsString('Marie Lefèvre', $context['tablehtml']);

        $none = (new booklet_authorize_page([], $this->url))->export_for_template($output);
        $this->assertSame(get_string('bookletauthnone', 'tool_vae'), $none['none']);
        $this->assertFalse($none['tablehtml']);

        $form = (new booklet_authorize_form($this->url, 4, true))->export_for_template($output);
        $this->assertSame([
            'url' => $this->url->out(false),
            'sesskey' => sesskey(),
            'managerid' => 4,
            'verb' => 'revoke',
            'label' => get_string('bookletauthrevoke', 'tool_vae'),
        ], $form);
        $grant = (new booklet_authorize_form($this->url, 6, false))->export_for_template($output);
        $this->assertSame('grant', $grant['verb']);
        $this->assertSame(get_string('bookletauthgrant', 'tool_vae'), $grant['label']);
    }

    public function test_a_script_in_a_name_comes_out_escaped(): void {
        $this->resetAfterTest();
        $managers = [['id' => 6, 'name' => '<script>alert(1)</script>', 'stands' => false]];

        $html = $this->render(new booklet_authorize_page($managers, $this->url));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_templates_render_their_example_context(): void {
        $this->resetAfterTest();
        foreach (['booklet_authorize_page' => ['tablehtml'], 'booklet_authorize_form' => []] as $name => $keys) {
            $file = __DIR__ . '/../../templates/' . $name . '.mustache';
            $this->assertFileExists($file);
            $source = file_get_contents($file);
            $this->assertStringContainsString('@template tool_vae/' . $name, $source);
            preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
            $this->assertSame($keys, array_values(array_unique($raw[1])), $name);
            $this->assertSame(substr_count($source, '{{{'), count($raw[1]), $name);
            $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches), $name);
            $context = json_decode($matches[1]);
            $this->assertIsObject($context, json_last_error_msg());

            $html = self::core()->render_from_template('tool_vae/' . $name, $context);

            $this->assertStringContainsString($name === 'booklet_authorize_form' ? '<form' : '<p>', $html, $name);
        }
    }
}
