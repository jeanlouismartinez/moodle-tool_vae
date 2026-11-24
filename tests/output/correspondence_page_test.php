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

use tool_vae\local\correspondence_manager;

/**
 * The page of the correspondences of a replaced record (passage.php), drawn by tool_vae\output\correspondence_page and its form
 * tool_vae\output\correspondence_decision_form (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That the page draws, below its heading, the same DOM as before its migration, for every state: no
 * competency; competencies with no suggestion, a suggestion of the same rank or of a near label, no decision, a decision with
 * its note, with no note, by nobody, towards a competency gone; the error of a decision refused; codes, labels, a name and a
 * note that are the figure 0. The "before" is the code of the page as it was, kept here as the expected value. Also: that the
 * context holds plain texts, that a script in a code, a name, a note or an error comes out escaped, that the templates place
 * as HTML only the keys named ...html and render their example context.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(correspondence_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(correspondence_decision_form::class)]
final class correspondence_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var \moodle_url The address of the page. */
    private \moodle_url $self;

    /** @var \moodle_url The page of the replaced records. */
    private \moodle_url $back;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->self = new \moodle_url('/admin/tool/vae/passage.php', ['fiche' => 'DEMO-GRAND-AGE']);
        $this->back = new \moodle_url('/admin/tool/vae/supersession.php');
        $PAGE->set_url($this->self);
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param correspondence_page $page
     * @return string HTML.
     */
    private function render(correspondence_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * The body of passage.php before its migration, below the heading (its code, kept as the expected value).
     *
     * @param \stdClass[] $rows correspondence_manager::overview().
     * @param \stdClass[] $targets correspondence_manager::targets().
     * @param string|null $error
     * @param \moodle_url $self
     * @param \moodle_url $back
     * @return string HTML.
     */
    private static function before(array $rows, array $targets, ?string $error, \moodle_url $self, \moodle_url $back): string {
        $show = function (\stdClass $competency): string {
            return s($competency->idnumber) . ' — ' . format_string($competency->label ?? $competency->shortname);
        };

        $html = \html_writer::tag('p', get_string('passageintro', 'tool_vae'));
        $html .= \html_writer::link($back, get_string('passageback', 'tool_vae'));
        if ($error !== null) {
            $html .= self::core()->notification(s($error), \core\output\notification::NOTIFY_ERROR);
        }

        $options = ['' => get_string('passagenoequivalent', 'tool_vae')];
        foreach ($targets as $id => $target) {
            $options[$id] = $show($target);
        }

        $table = new \html_table();
        $table->head = [
            get_string('passagecolfrom', 'tool_vae'),
            get_string('passagecoldeclarations', 'tool_vae'),
            get_string('passagecolproposed', 'tool_vae'),
            get_string('passagecoldecision', 'tool_vae'),
            get_string('passagecoldecide', 'tool_vae'),
        ];
        foreach ($rows as $id => $row) {
            if ($row->proposed === null) {
                $proposed = get_string('passagenoproposal', 'tool_vae');
            } else {
                $proposed = $show($row->proposed) . ' (' . get_string(
                    $row->reason === correspondence_manager::REASON_RANK ? 'passagereasonrank' : 'passagereasonlabel',
                    'tool_vae'
                ) . ')';
            }

            if ($row->decision === null) {
                $decision = get_string('passagenodecision', 'tool_vae');
            } else {
                $decision = ($row->target ? $show($row->target) : get_string('passagetargetgone', 'tool_vae'))
                    . \html_writer::tag('div', get_string('passagedecidedby', 'tool_vae', (object) [
                        'who' => $row->decidername === '' ? get_string('passagenobody', 'tool_vae') : s($row->decidername),
                        'when' => userdate((int) $row->decision->decidedat, get_string('strftimedatefullshort', 'langconfig')),
                    ]), ['class' => 'small text-muted'])
                    . ($row->decision->note === '' ? '' : \html_writer::tag('div', s($row->decision->note), ['class' => 'small']));
            }

            $selected = $row->decision ? (int) $row->decision->tocompetencyid : ($row->proposed ? (int) $row->proposed->id : '');
            $form = \html_writer::start_tag('form', ['method' => 'post', 'action' => $self->out_omit_querystring()])
                . \html_writer::input_hidden_params($self)
                . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
                . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'fromid', 'value' => $id])
                . \html_writer::label(
                    get_string('passagecoldecide', 'tool_vae') . ' ' . s($row->competency->idnumber),
                    'tool_vae_to_' . $id,
                    true,
                    ['class' => 'visually-hidden']
                )
                . \html_writer::select($options, 'toid', $selected, false, ['id' => 'tool_vae_to_' . $id])
                . ' ' . \html_writer::label(
                    get_string('passagenote', 'tool_vae') . ' ' . s($row->competency->idnumber),
                    'tool_vae_note_' . $id,
                    true,
                    ['class' => 'visually-hidden']
                )
                . \html_writer::empty_tag('input', [
                    'type' => 'text', 'name' => 'note', 'id' => 'tool_vae_note_' . $id,
                    'maxlength' => correspondence_manager::NOTE_MAXLENGTH, 'size' => 30,
                    'placeholder' => get_string('passagenote', 'tool_vae'),
                ])
                . ' ' . \html_writer::tag('button', get_string('passagedecide', 'tool_vae'), [
                    'type' => 'submit', 'name' => 'action', 'value' => 'decide', 'class' => 'btn btn-primary btn-sm',
                ]);
            if ($row->decision !== null) {
                $form .= ' ' . \html_writer::tag('button', get_string('passageundo', 'tool_vae'), [
                    'type' => 'submit', 'name' => 'action', 'value' => 'undo', 'class' => 'btn btn-secondary btn-sm',
                ]);
            }
            $form .= \html_writer::end_tag('form');

            $table->data[] = [$show($row->competency), $row->declarations, $proposed, $decision, $form];
        }

        return $html . \html_writer::table($table);
    }

    /**
     * A competency as correspondence_manager gives it.
     *
     * @param int $id
     * @param string $idnumber
     * @param string $shortname
     * @param string|null $label
     * @return \stdClass
     */
    private static function competency(int $id, string $idnumber, string $shortname, ?string $label = null): \stdClass {
        $competency = (object) ['id' => $id, 'idnumber' => $idnumber, 'shortname' => $shortname];
        if ($label !== null) {
            $competency->label = $label;
        }

        return $competency;
    }

    /**
     * A row of correspondence_manager::overview().
     *
     * @param \stdClass $competency
     * @param int $declarations
     * @param \stdClass|null $proposed
     * @param string $reason
     * @param \stdClass|null $decision
     * @param \stdClass|null $target
     * @param string $decidername
     * @return \stdClass
     */
    private static function row(
        \stdClass $competency,
        int $declarations,
        ?\stdClass $proposed,
        string $reason,
        ?\stdClass $decision,
        ?\stdClass $target,
        string $decidername
    ): \stdClass {
        return (object) ['competency' => $competency, 'declarations' => $declarations, 'proposed' => $proposed,
            'reason' => $reason, 'decision' => $decision, 'target' => $target, 'decidername' => $decidername];
    }

    /**
     * A decision as correspondence_manager gives it.
     *
     * @param int $to
     * @param string $note
     * @return \stdClass
     */
    private static function decision(int $to, string $note): \stdClass {
        return (object) ['tocompetencyid' => $to, 'decidedat' => 1792411200, 'decidedby' => 2, 'note' => $note];
    }

    /**
     * The targets and rows of a replaced record, with every kind of line.
     *
     * @return array [rows, targets]
     */
    private static function data(): array {
        $targets = [
            10 => self::competency(10, 'NEW-BC01', 'Accompagner', 'Accompagner la personne (v2)'),
            11 => self::competency(11, 'NEW-BC02', 'Repérer'),
        ];
        $rank = correspondence_manager::REASON_RANK;
        $first = self::competency(1, 'OLD-BC01', 'Accompagner', 'Accompagner la personne');
        $fifth = self::competency(5, 'OLD-BC05', 'Note');
        $rows = [
            1 => self::row($first, 4, $targets[10], $rank, self::decision(10, 'Essai plan 7'), $targets[10], 'Admin User'),
            2 => self::row(self::competency(2, 'OLD-BC02', 'Repérer'), 1, $targets[11], 'label', null, null, ''),
            3 => self::row(self::competency(3, 'OLD-BC03', 'Contribuer'), 0, null, '', null, null, ''),
            4 => self::row(self::competency(4, 'OLD-BC04', 'Gone'), 2, null, '', self::decision(99, ''), null, ''),
            5 => self::row($fifth, 0, $targets[11], $rank, self::decision(11, ''), $targets[11], 'Marie Lefèvre'),
        ];

        return [$rows, $targets];
    }

    public function test_the_page_and_its_form_are_named_templates(): void {
        global $PAGE;
        $this->resetAfterTest();

        $page = new correspondence_page([], [], null, $this->self, $this->back);
        $form = new correspondence_decision_form($this->self, 1, 'OLD-BC01', [], '', false);

        foreach ([$page, $form] as $piece) {
            $this->assertInstanceOf(\core\output\renderable::class, $piece);
            $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
        }
        $this->assertSame('tool_vae/correspondence_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
        $this->assertSame('tool_vae/correspondence_decision_form', $form->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_each_state_draws_the_dom_of_before(): void {
        $this->resetAfterTest();
        [$rows, $targets] = self::data();
        $zero = self::competency(0, '0', '0', '0');
        $zeros = [0 => self::row($zero, 0, $zero, correspondence_manager::REASON_RANK, self::decision(0, '0'), $zero, '0')];

        $cases = [
            'no competency' => [[], [], null],
            'every kind of line' => [$rows, $targets, null],
            'a decision refused' => [$rows, $targets, 'Cette compétence a déjà un équivalent.'],
            'no target' => [[3 => $rows[3]], [], null],
            // Codes, labels, a name and a note that are the figure 0 were drawn before.
            'values that are 0' => [$zeros, [0 => $zero], '0'],
        ];
        foreach ($cases as $name => [$r, $t, $error]) {
            $expected = self::before($r, $t, $error, $this->self, $this->back);
            $html = $this->render(new correspondence_page($r, $t, $error, $this->self, $this->back));
            $this->assertSame(self::canon($expected), self::canon($html), $name);
        }
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $this->resetAfterTest();
        $output = $PAGE->get_renderer('tool_vae');
        [$rows, $targets] = self::data();

        $context = (new correspondence_page($rows, $targets, null, $this->self, $this->back))->export_for_template($output);
        $this->assertSame(get_string('passageintro', 'tool_vae'), $context['intro']);
        $this->assertSame(['url' => $this->back->out(false), 'label' => get_string('passageback', 'tool_vae')], $context['back']);
        $this->assertFalse($context['errorhtml']);
        $this->assertStringContainsString('tool_vae_to_5', $context['tablehtml']);

        $refused = (new correspondence_page([], [], 'Refusée', $this->self, $this->back))->export_for_template($output);
        $this->assertStringContainsString('Refusée', $refused['errorhtml']);

        $form = (new correspondence_decision_form($this->self, 5, 'OLD-BC05', [
            ['value' => '', 'text' => 'None', 'labelhtml' => ''],
            ['value' => '11', 'text' => 'NEW-BC02 — ', 'labelhtml' => 'Repérer'],
        ], '11', true))->export_for_template($output);
        $this->assertSame($this->self->out_omit_querystring(), $form['url']);
        $this->assertSame([['name' => 'fiche', 'value' => 'DEMO-GRAND-AGE']], $form['params']);
        $this->assertSame(sesskey(), $form['sesskey']);
        $this->assertSame(5, $form['fromid']);
        $this->assertSame(get_string('passagecoldecide', 'tool_vae') . ' OLD-BC05', $form['tolabel']);
        $this->assertSame(get_string('passagenote', 'tool_vae') . ' OLD-BC05', $form['notelabel']);
        $this->assertTrue($form['undo']);
        $this->assertFalse($form['options'][0]['selected']);
        $this->assertTrue($form['options'][1]['selected']);
    }

    public function test_a_script_in_the_data_comes_out_escaped(): void {
        $this->resetAfterTest();
        $script = fn(int $n) => '<script>alert(' . $n . ')</script>';
        $competency = self::competency(1, $script(1), 'x');
        $target = self::competency(10, $script(2), 'y');
        $decision = self::decision(10, $script(3));
        $rows = [1 => self::row($competency, 1, $target, correspondence_manager::REASON_RANK, $decision, $target, $script(4))];

        $html = $this->render(new correspondence_page($rows, [10 => $target], $script(5), $this->self, $this->back));

        foreach ([1, 2, 3, 4, 5] as $n) {
            $this->assertStringContainsString('&lt;script&gt;alert(' . $n . ')&lt;/script&gt;', $html);
        }
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_the_templates_render_their_example_context(): void {
        $this->resetAfterTest();
        $templates = ['correspondence_page' => ['errorhtml', 'tablehtml'], 'correspondence_decision_form' => ['labelhtml']];
        foreach ($templates as $name => $keys) {
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

            $this->assertStringContainsString($name === 'correspondence_decision_form' ? '<select' : '<p>', $html, $name);
        }
    }
}
