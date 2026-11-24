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

use tool_vae\form\referent_form;

/**
 * The page of the referents of the blocs (referents.php) drawn by tool_vae\output\referents_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration: no record imported, an
 * error of the last sending (both in no instant of the tool of equivalence: an error follows a sending), a table of records
 * with and without referents, with its buttons, then the heading and the form of the core, drawn once. The "before" is the
 * code of the page as it was, kept here as the expected value. Also: that the context holds plain texts, that a script in a
 * name, a rank or an error comes out escaped, that the template renders its example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(referents_page::class)]
final class referents_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $self;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->self = new \moodle_url('/admin/tool/vae/referents.php');
        $PAGE->set_url($this->self);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * A form of the page, as the page builds it.
     *
     * @return referent_form
     */
    private function form(): referent_form {
        return new referent_form($this->self, ['options' => [
            'DEMO' => 'DEMO — Démo — fiche entière',
            'DEMO|BC01' => 'DEMO|BC01',
        ]]);
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param referents_page $page
     * @return string HTML.
     */
    private function render(referents_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The page below its heading as referents.php drew it before its migration (its code, kept as the expected value).
     *
     * @param array $rows
     * @param array $referents
     * @param array $users
     * @param string|null $error
     * @param referent_form $form
     * @param \moodle_url $self
     * @return string HTML.
     */
    private static function before(
        array $rows,
        array $referents,
        array $users,
        ?string $error,
        referent_form $form,
        \moodle_url $self
    ): string {

        $out = \html_writer::tag('p', get_string('referentintro', 'tool_vae'));
        if ($error !== null) {
            $out .= self::core()->notification(s($error), \core\output\notification::NOTIFY_ERROR);
        }
        if (!$rows) {
            return $out . self::core()->notification(
                get_string('supersessionnone', 'tool_vae'),
                \core\output\notification::NOTIFY_INFO
            );
        }
        $table = new \html_table();
        $table->head = [
            get_string('supersessioncolfiche', 'tool_vae'),
            get_string('referentbloc', 'tool_vae'),
            get_string('referentuser', 'tool_vae'),
            '',
        ];
        foreach ($rows as $row) {
            $name = s($row->fichecode) . ' — ' . format_string($row->label);
            if (empty($referents[$row->fichecode])) {
                $table->data[] = [$name, '', get_string('referentnone', 'tool_vae'), ''];
                continue;
            }
            foreach ($referents[$row->fichecode] as $referent) {
                $person = $users[$referent->userid] ?? null;
                $table->data[] = [
                    $name,
                    $referent->ficherang === ''
                        ? get_string('referentwhole', 'tool_vae')
                        : get_string('referentblocrank', 'tool_vae', s($referent->ficherang)),
                    $person ? s(fullname($person)) . ' (' . s($person->username) . ')' : '',
                    self::core()->single_button(
                        new \moodle_url($self, ['remove' => $referent->id]),
                        get_string('referentremove', 'tool_vae'),
                        'post'
                    ),
                ];
                $name = '';
            }
        }
        $out .= \html_writer::table($table);
        $out .= self::core()->heading(get_string('referentaddheading', 'tool_vae'), 3);
        ob_start();
        $form->display();

        return $out . ob_get_clean();
    }

    /**
     * Records, referents and their people, as the page reads them.
     *
     * @return array [rows, referents, users]
     */
    private function data(): array {
        $gen = $this->getDataGenerator();
        $a = $gen->create_user(['firstname' => 'Suiveur', 'lastname' => 'Essai', 'username' => 'suiveur.essai']);
        $b = $gen->create_user(['firstname' => 'Alice', 'lastname' => 'Martin', 'username' => 'alice']);
        $rows = [
            'DEMO' => (object) ['fichecode' => 'DEMO', 'label' => 'Démo'],
            'DEMOBLOCS' => (object) ['fichecode' => 'DEMOBLOCS', 'label' => 'Demo blocs'],
        ];
        $referents = ['DEMOBLOCS' => [
            (object) ['id' => 2, 'fichecode' => 'DEMOBLOCS', 'ficherang' => 'BC01', 'userid' => $a->id],
            (object) ['id' => 5, 'fichecode' => 'DEMOBLOCS', 'ficherang' => '', 'userid' => $b->id],
            (object) ['id' => 6, 'fichecode' => 'DEMOBLOCS', 'ficherang' => 'BC02', 'userid' => 999999],
        ]];

        return [$rows, $referents, [$a->id => $a, $b->id => $b]];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = new referents_page([], [], [], null, $this->form(), $this->self);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/referents_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        [$rows, $referents, $users] = $this->data();

        $cases = [
            'none' => [[], [], [], null],
            'none, an error' => [[], [], [], 'Ce nom ne désigne personne.'],
            'table' => [$rows, $referents, $users, null],
            'table, an error' => [$rows, $referents, $users, 'Ce nom ne désigne personne.'],
        ];
        foreach ($cases as $name => [$r, $ref, $u, $error]) {
            $expected = self::before($r, $ref, $u, $error, $this->form(), $this->self);
            $html = $this->render(new referents_page($r, $ref, $u, $error, $this->form(), $this->self));
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$rows, $referents, $users] = $this->data();

        $context = (new referents_page($rows, $referents, $users, 'Erreur', $this->form(), $this->self))
            ->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertSame(get_string('referentintro', 'tool_vae'), $context['intro']);
        $this->assertStringContainsString('Erreur', $context['errorhtml']);
        $this->assertFalse($context['nonehtml']);
        $this->assertStringContainsString('<table', $context['tablehtml']);
        $this->assertStringContainsString(get_string('referentaddheading', 'tool_vae'), $context['headinghtml']);
        $this->assertStringContainsString('_qf__tool_vae_form_referent_form', $context['formhtml']);

        $none = (new referents_page([], [], [], null, $this->form(), $this->self))
            ->export_for_template($PAGE->get_renderer('tool_vae'));
        $this->assertFalse($none['errorhtml']);
        $this->assertStringContainsString(get_string('supersessionnone', 'tool_vae'), $none['nonehtml']);
        $this->assertFalse($none['tablehtml']);
        $this->assertFalse($none['headinghtml']);
        $this->assertFalse($none['formhtml']);
    }

    public function test_a_script_in_a_name_a_rank_or_an_error_comes_out_escaped(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['lastname' => 'X']);
        // The generator strips tags from a name: the record read by the page is given the script in memory.
        $user->firstname = '<script>alert(1)</script>';
        $rows = ['DEMO' => (object) ['fichecode' => 'DEMO', 'label' => 'Démo']];
        $referents = ['DEMO' => [(object) ['id' => 2, 'fichecode' => 'DEMO', 'ficherang' => '<script>alert(2)</script>',
            'userid' => $user->id]]];

        $html = $this->render(new referents_page(
            $rows,
            $referents,
            [$user->id => $user],
            '<script>alert(3)</script>',
            $this->form(),
            $this->self
        ));

        foreach ([1, 2, 3] as $n) {
            $this->assertStringContainsString('&lt;script&gt;alert(' . $n . ')&lt;/script&gt;', $html);
        }
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/referents_page.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/referents_page', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['errorhtml', 'nonehtml', 'tablehtml', 'headinghtml', 'formhtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/referents_page', $context);

        $this->assertStringContainsString('<p>', $html);
    }
}
