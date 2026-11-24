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

use tool_vae\local\selfpos_manager;
use tool_vae\local\selfpos_screen;
use tool_vae\local\validation_catalog;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/whereami_before.php');

/**
 * The page that declares an activity (declare.php) draws, in each of its four states, the DOM the page drew before lot 7
 * (MMR-520 ticket 4, wave 3b): its code from under the heading to the footer, kept here in before(), the panel by the trait
 * whereami_before. The states that follow a send (a refusal, every record hidden since the form was opened) are in no
 * instant of the tool of equivalence: they are compared here.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(declare_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(kept_text::class)]
final class declare_page_test extends \advanced_testcase {
    use dom_canon;
    use whereami_before;

    /** @var \stdClass Declares and positions themselves. */
    private $vaeiste;

    /** @var \stdClass Declares, but neither positions themselves nor writes a preparation. */
    private $limited;

    /** @var \tool_vae_generator */
    private $generator;

    /**
     * RNCP100 (BC01 holding C01 and C02) and RNCP400 (D1 holding C11); the people; the preparation open.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        validation_catalog::reset();
        set_config('validationopen', 1, 'tool_vae');
        $datagenerator = $this->getDataGenerator();
        $this->generator = $datagenerator->get_plugin_generator('tool_vae');
        $this->generator->create_source(['fichecode' => 'RNCP100', 'ficherang' => 'BC01', 'shortname' => 'Bloc 1']);
        foreach (['C01', 'C02'] as $rank) {
            $this->generator->create_competency([
                'fichecode' => 'RNCP100', 'ficherang' => $rank, 'shortname' => 'Competence ' . $rank, 'bloc' => 'BC01',
            ]);
        }
        $this->generator->create_source(['fichecode' => 'RNCP400', 'ficherang' => 'D1', 'shortname' => 'Domaine 1']);
        $this->generator->create_competency([
            'fichecode' => 'RNCP400', 'ficherang' => 'C11', 'shortname' => 'Noeud', 'parent' => 'D1',
        ]);

        $system = \context_system::instance()->id;
        $this->vaeiste = $datagenerator->create_user(['firstname' => 'Camille', 'lastname' => 'Vaeiste']);
        $this->limited = $datagenerator->create_user(['firstname' => 'Lou', 'lastname' => 'Limite']);
        $own = $datagenerator->create_role();
        foreach (['declare', 'viewown', 'positionself', 'draftown'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $own, $system, true);
        }
        set_role_contextlevels($own, [CONTEXT_USER]);
        role_assign($own, $this->vaeiste->id, \context_user::instance($this->vaeiste->id)->id);
        $declareonly = $datagenerator->create_role();
        foreach (['declare', 'viewown'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $declareonly, $system, true);
        }
        set_role_contextlevels($declareonly, [CONTEXT_USER]);
        role_assign($declareonly, $this->limited->id, \context_user::instance($this->limited->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * A page drawn by the renderer of the plugin for the web.
     *
     * @param \core\output\renderable $page
     * @return string
     */
    private function render(\core\output\renderable $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * A form, new each time (a form is drawn once).
     *
     * @return \moodleform
     */
    private static function form(): \moodleform {
        return new \tool_vae\form\section_form((new \moodle_url('/admin/tool/vae/declare.php'))->out(false), ['aiurl' => null]);
    }

    /**
     * What declare.php wrote under its heading before lot 7, state by state. The only changes: echo becomes $out .=, $OUTPUT
     * the renderer of the core for the web (self::core()), $form->display() $out .= $form->render(), whereami_panel::render()
     * the old code kept in the trait (self::before_whereami_render()), and what the page read is given as parameters.
     *
     * @param string $state disabled, noview, nothing or form.
     * @param array $hidden
     * @param kept_text|null $sent The text sent (nothing).
     * @param string|null $refusal
     * @param int $candidateid
     * @param string[] $declaredcodes
     * @param \moodleform|null $form
     * @return string
     */
    private function before(
        string $state,
        array $hidden = [],
        ?kept_text $sent = null,
        ?string $refusal = null,
        int $candidateid = 0,
        array $declaredcodes = [],
        ?\moodleform $form = null
    ): string {
        global $PAGE;

        $out = '';
        if ($state === 'disabled') {
            return self::core()->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                \core\output\notification::NOTIFY_ERROR
            );
        }
        if ($state === 'noview') {
            return self::core()->notification(
                get_string('errordeclarenoview', 'tool_vae'),
                \core\output\notification::NOTIFY_ERROR
            );
        }
        if ($state === 'nothing') {
            $kept = $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($sent);
            $level = $kept === '' ? \core\output\notification::NOTIFY_INFO : \core\output\notification::NOTIFY_ERROR;
            foreach ($hidden as $message) {
                $out .= self::core()->notification($message, $level);
            }
            if (!$hidden) {
                $out .= self::core()->notification(
                    get_string('noframeworkimported', 'tool_vae'),
                    \core\output\notification::NOTIFY_INFO
                );
            }
            $out .= $kept;

            return $out;
        }
        if ($refusal !== null) {
            $out .= self::core()->notification($refusal, \core\output\notification::NOTIFY_ERROR);
            $out .= \html_writer::tag('p', get_string('declarehiddenkept', 'tool_vae'));
        }
        foreach ($hidden as $message) {
            if ($message !== $refusal) {
                $out .= self::core()->notification($message, \core\output\notification::NOTIFY_INFO);
            }
        }
        if (!$declaredcodes && selfpos_manager::can_write($candidateid)) {
            $out .= \html_writer::tag('p', \html_writer::link(
                selfpos_screen::url($candidateid),
                s(get_string('selfposlink', 'tool_vae'))
            ));
        }
        foreach ($declaredcodes as $declaredcode) {
            $panel = self::before_whereami_render($candidateid, (string) $declaredcode, ['link' => true, 'title' => '']);
            if ($panel !== '') {
                $out .= \html_writer::tag(
                    'details',
                    \html_writer::tag('summary', s(get_string('whereamideclare', 'tool_vae', $declaredcode))) . $panel,
                    ['class' => 'mb-3']
                );
            }
        }
        $out .= $form->render();

        return $out;
    }

    public function test_the_states_with_no_form_draw_the_dom_of_before(): void {
        $this->setUser($this->vaeiste);
        $this->assertSame(self::canon($this->before('disabled')), self::canon($this->render(declare_page::disabled())));
        $this->assertSame(self::canon($this->before('noview')), self::canon($this->render(declare_page::noview())));
        $this->assertStringContainsString('alert-danger', $this->render(declare_page::noview()));

        $hidden = ['RNCP100' => s('The record A & "B" (RNCP100) is hidden'), 'RNCP400' => 'The record RNCP400 is hidden'];
        $cases = [
            'hidden, nothing sent' => [$hidden, '', ''],
            'hidden, a text sent' => [$hidden, "Mon récit\nsur deux lignes & <b>gras</b>", ''],
            'hidden, a detail only' => [$hidden, '   ', 'Un détail'],
            'hidden, blanks sent' => [$hidden, "  \n ", ' '],
            'nothing imported' => [[], '', ''],
            'nothing imported, the text 0' => [[], '0', ''],
        ];
        foreach ($cases as $name => [$messages, $situation, $detail]) {
            $before = $this->before('nothing', $messages, new kept_text($situation, $detail));
            $kept = new kept_text($situation, $detail);
            $after = $this->render(declare_page::nothing($messages, $kept));
            $this->assertSame(self::canon($before), self::canon($after), $name);
            // Premises: the colour follows what was sent, the sentence "no record imported" the records hidden.
            $sent = trim($situation) !== '' || trim($detail) !== '';
            $this->assertSame($sent, $kept->drawn(), $name);
            $this->assertSame($sent && $messages !== [], str_contains($before, 'alert-danger'), $name);
            $this->assertSame(!$sent || $messages === [], str_contains($before, 'alert-info'), $name);
            $this->assertSame($messages === [], str_contains($before, s(get_string('noframeworkimported', 'tool_vae'))));
            $this->assertSame($sent, str_contains($before, '<textarea'), $name);
        }
    }

    public function test_the_form_draws_the_dom_of_before_with_each_part_above_it(): void {
        global $DB;

        $id = (int) $this->vaeiste->id;
        $this->generator->create_declaration([
            'userid' => $id, 'fichecode' => 'RNCP100', 'ficherang' => 'C01', 'situation' => 'Une.',
        ]);
        $this->generator->create_declaration([
            'userid' => $id, 'fichecode' => 'RNCP400', 'ficherang' => 'C11', 'situation' => 'Deux.',
        ]);
        // A declaration on a record this site no longer holds: no panel, and no fold, for it.
        $DB->insert_record('tool_vae_evidence', (object) [
            'uuid' => \tool_vae\local\identity_map::generate_ulid(), 'userevidenceid' => 99001, 'candidateid' => $id,
            'periodstart' => 1, 'periodend' => 2, 'settingtype' => 'ehpad', 'situationformat' => FORMAT_PLAIN,
            'situation' => 'Sur une fiche partie.', 'fichecode' => 'RNCP999', 'ficherang' => 'C01', 'ficheupdated' => 1,
            'timecreated' => 1, 'timemodified' => 1, 'usermodified' => $id,
        ]);
        $codes = ['RNCP100', 'RNCP400', 'RNCP999'];
        $refused = s('The record A & "B" (RNCP100) is hidden');
        $hidden = ['RNCP100' => $refused, 'RNCP500' => 'The record RNCP500 is hidden'];

        $cases = [
            'the vaeiste, three records, nothing hidden' => [$this->vaeiste, null, [], $codes],
            'a refusal and another record hidden' => [$this->vaeiste, $refused, $hidden, $codes],
            'a refusal of an exception, records hidden' => [$this->vaeiste, 'Refusé : <b>RNCP100</b>', $hidden, $codes],
            'records hidden, no refusal' => [$this->vaeiste, null, $hidden, $codes],
            'no declaration, who may position' => [$this->vaeiste, null, [], []],
            'no declaration, who may not position' => [$this->limited, null, [], []],
        ];
        foreach ($cases as $name => [$reader, $refusal, $messages, $declared]) {
            $this->setUser($reader);
            $who = (int) $reader->id;
            $before = $this->before('form', $messages, null, $refusal, $who, $declared, self::form());
            $after = $this->render(declare_page::form($refusal, $messages, $who, $declared, self::form()));
            $this->assertSame(self::canon($before), self::canon($after), $name);

            // Premises: each part of the page is drawn by the old code in the state that has it.
            $this->assertSame($refusal !== null, str_contains($before, s(get_string('declarehiddenkept', 'tool_vae'))), $name);
            $this->assertSame($refusal !== null, str_contains($before, 'alert-danger'), $name);
            // Each message once: the one of the refusal is not said again among the records hidden.
            // (The notification of the core cleans its message: its end is looked for, which nothing cleans.)
            foreach ($messages as $code => $message) {
                $said = substr_count($before, $code . ') is hidden') + substr_count($before, $code . ' is hidden');
                $this->assertSame(1, $said, $name);
            }
            $this->assertSame($declared ? 2 : 0, substr_count($before, '<details'), $name . ': no fold for RNCP999.');
            $this->assertSame(!$declared && $reader === $this->vaeiste, str_contains($before, 'selfposition.php?userid='
                . $who . '"'), $name);
            $this->assertStringContainsString('<form', $before);
            if ($declared) {
                $this->assertStringContainsString(s(get_string('whereamideclare', 'tool_vae', 'RNCP400')), $before);
                $this->assertStringNotContainsString(s(get_string('whereamideclare', 'tool_vae', 'RNCP999')), $before);
                $this->assertStringNotContainsString('<h4', $before, 'No title in a fold.');
            }
        }
    }

    public function test_the_words_escaped_before_are_escaped_once_and_a_script_comes_out_escaped(): void {
        global $CFG;

        $id = (int) $this->vaeiste->id;
        $this->generator->create_declaration([
            'userid' => $id, 'fichecode' => 'RNCP100', 'ficherang' => 'C01', 'situation' => 'Une.',
        ]);
        // A site that customised the words (tool_customlang writes there) with a script, an ampersand and quotes.
        $file = $CFG->langlocalroot . '/en_local/tool_vae.php';
        check_dir_exists(dirname($file));
        $words = '<script>alert(1)</script> & "vu" {$a}';
        file_put_contents($file, "<?php\n" . '$string[\'whereamideclare\'] = ' . var_export($words, true) . ";\n"
            . '$string[\'selfposlink\'] = ' . var_export($words, true) . ";\n");
        get_string_manager()->reset_caches();
        try {
            $this->assertNotSame(s($words), s(s($words)), 'The premise: a second escaping changes these words.');
            foreach ([['RNCP100'], []] as $declared) {
                $this->setUser($this->vaeiste);
                $before = $this->before('form', [], null, null, $id, $declared, self::form());
                $after = $this->render(declare_page::form(null, [], $id, $declared, self::form()));
                $this->assertSame(self::canon($before), self::canon($after));
                $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;vu&quot;', $after);
                $this->assertStringNotContainsString('<script>alert', $after);
                $this->assertStringNotContainsString('&amp;amp;', $after);
                $this->assertStringNotContainsString('&amp;quot;', $after);
            }
        } finally {
            unlink($file);
            get_string_manager()->reset_caches();
        }
    }

    public function test_the_template_places_html_only_under_the_keys_named(): void {
        global $CFG;

        $template = file_get_contents($CFG->dirroot . '/admin/tool/vae/templates/declare_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $template, $keys);
        $this->assertSame(['formhtml', 'kepthtml', 'noticehtml', 'panelhtml'], array_values(array_unique(
            (function (array $list): array {
                sort($list);

                return $list;
            })($keys[1])
        )));
    }
}
