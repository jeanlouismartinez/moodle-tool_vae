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
require_once(__DIR__ . '/forms_before.php');

use tool_vae\form\attest_form;
use tool_vae\form\certification_form;
use tool_vae\form\declare_form;
use tool_vae\form\evaluation_form;
use tool_vae\form\fiche_confirm_form;
use tool_vae\form\fiche_import_form;
use tool_vae\form\fields_form;
use tool_vae\form\note_form;
use tool_vae\form\piece_file_form;
use tool_vae\form\piece_link_form;
use tool_vae\form\precision_form;
use tool_vae\form\remove_record_form;
use tool_vae\form\scope_form;
use tool_vae\form\section_form;
use tool_vae\form\selfpos_form;
use tool_vae\form\situation_details_form;
use tool_vae\form\situation_form;
use tool_vae\form\withdraw_form;

/**
 * The HTML the forms of the plugin place between their fields, drawn by templates (lot 7, MMR-520 ticket 4, wave 4c).
 *
 * WHAT IS ASSERTED. That each piece draws the same DOM as the line of the form it replaces (kept in the trait forms_before,
 * copied by script from the forms as they were): the reminders, the warnings, the heading and the memo (form_note), the
 * links (form_link), the hidden first submit button (form_default_submit), the list of what leaves with the row of a record
 * (remove_record_leaves), in each state that changes what they draw. And that each of the eighteen forms, rendered whole,
 * holds that old DOM at its place exactly as many times as before, and not where the form did not write it (a precision
 * asked, not answered; a section of the dossier de faisabilité the account does not fill). Also: the context, the escaping,
 * the figure 0, the example contexts.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(form_note::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(form_link::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(form_default_submit::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(remove_record_leaves::class)]
final class form_pieces_test extends \advanced_testcase {
    use dom_canon;
    use forms_before;

    /** @var string Escaped once by the template, and not twice: an ampersand, quotes and a tag. */
    private const ODD = 'Soins & "aide" <script>alert(1)</script>';

    /**
     * A piece drawn by the renderer of the plugin, as the forms draw it.
     *
     * @param \core\output\renderable $piece
     * @return string HTML.
     */
    private static function draw(\core\output\renderable $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae')->render($piece);
    }

    /**
     * How many times a form holds a fragment, compared in canonical DOM, line by line, whatever the depth (the body that
     * canon() wraps each around is left out).
     *
     * @param string $form The HTML of the whole form.
     * @param string $fragment The HTML looked for.
     * @return int
     */
    private static function holds(string $form, string $fragment): int {
        $lines = fn(string $html): array => array_values(array_filter(
            array_map('trim', explode("\n", self::canon($html))),
            fn($line) => !in_array($line, ['', '<body>', '</body>'], true)
        ));
        $haystack = $lines($form);
        $needle = $lines($fragment);
        $count = 0;
        for ($at = 0; $at + count($needle) <= count($haystack); $at++) {
            if (array_slice($haystack, $at, count($needle)) === $needle) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * What record_remover::inventory() gives, for the cases of the list.
     *
     * @param array $over Fields that change.
     * @return \stdClass
     */
    private static function inventory(array $over = []): \stdClass {
        return (object) ($over + [
            'fichecode' => 'DEMOBLOCS',
            'source' => (object) ['licence' => 'Licence Ouverte', 'attribution' => 'France compétences',
                'sourceupdated' => 1792411200, 'lastimport' => 0],
            'refs' => [],
            'referents' => 0,
            'passageids' => [],
            'marks' => 0,
            'successor' => '',
            'replaced' => [],
        ]);
    }

    public function test_the_pieces_are_named_templates(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');
        $this->assertSame('tool_vae/form_note', form_note::short()->get_template_name($output));
        $this->assertSame('tool_vae/form_link', form_link::profile(2)->get_template_name($output));
        $this->assertSame('tool_vae/form_default_submit', (new form_default_submit())->get_template_name($output));
        $this->assertSame(
            'tool_vae/remove_record_leaves',
            (new remove_record_leaves(self::inventory()))->get_template_name($output)
        );
        foreach ([form_note::short(), form_link::profile(2), new form_default_submit()] as $piece) {
            $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
            $this->assertInstanceOf(\core\output\renderable::class, $piece);
        }
    }

    public function test_each_sentence_of_a_form_draws_the_dom_of_before(): void {
        $id = 'tool_vae_anonymise_x';
        $pairs = [
            'certification 72' => [form_note::short(), self::before_certification_form_l72()],
            'certification 78' => [form_note::short(), self::before_certification_form_l78()],
            'evaluation' => [form_note::short(), self::before_evaluation_form_l47()],
            'scope' => [form_note::short(), self::before_scope_form_l50()],
            'self-positioning' => [form_note::short(), self::before_selfpos_form_l52()],
            'details of a situation' => [form_note::short_with_id($id), self::before_situation_details_form_l50($id)],
            'annotation' => [form_note::short_marked($id), self::before_note_form_l46($id)],
            'attestation' => [form_note::reminder($id), self::before_attest_form_reminder($id)],
            'declaration, in full' => [form_note::reminder($id), self::before_declare_form_full_reminder($id)],
            'precision' => [form_note::reminder($id), self::before_precision_form_l50($id)],
            'section' => [form_note::reminder($id), self::before_section_form_l52($id)],
            'situation' => [form_note::reminder($id), self::before_situation_form_l50($id)],
            'declaration, one field' => [form_note::short_reminder($id), self::before_declare_form_reminder($id)],
            'piece, file' => [form_note::piece($id), self::before_piece_file_form_l63($id)],
            'piece, link' => [form_note::piece($id), self::before_piece_link_form_l50($id)],
            'removal of a record' => [form_note::removal(), self::before_remove_record_form_l49()],
            'withdrawal' => [form_note::withdrawal(), self::before_withdraw_form_l60()],
            'tags of a situation' => [form_note::tags_heading(), self::before_situation_details_form_l69()],
            'memo of a situation' => [form_note::situation_memo(), self::before_situation_form_l56()],
            'way back to the numbers' => [form_link::redo('abc123'), self::before_fiche_confirm_form_l73('abc123')],
            'way to the profile' => [form_link::profile(7), self::before_fields_form_l62(7)],
            'hidden first button' => [new form_default_submit(), self::before_fiche_import_form_l59()],
        ];
        foreach ($pairs as $name => [$piece, $before]) {
            $html = self::draw($piece);
            $this->assertNotSame('', trim(self::canon($before)), $name);
            $this->assertSame(self::canon($before), self::canon($html), $name);
        }
    }

    public function test_the_source_of_the_records_is_a_link_in_its_sentence_as_before(): void {
        $sentence = get_string(
            'fcimportziptext',
            'tool_vae',
            self::draw(new form_link(\tool_vae\local\fiche_import::SOURCE_URL, 'data.gouv.fr'))
        );

        $this->assertSame(self::canon(self::before_fiche_import_form_l75()), self::canon($sentence));
        $this->assertStringContainsString('>data.gouv.fr</a>', $sentence);
    }

    public function test_each_form_holds_the_dom_of_before_as_many_times_as_before(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Zoé', 'lastname' => 'Martin']);
        $this->setUser($user);
        $inventory = self::inventory(['refs' => [(object) ['title' => 'Fiche', 'url' => 'https://example.com/fiche']]]);

        $cases = [
            'attestation' => [new attest_form(null), [[self::before_attest_form_reminder('tool_vae_anonymise_statement'), 1]]],
            'certification' => [new certification_form(null), [[self::before_certification_form_l72(), 2]]],
            'declaration' => [new declare_form(null, ['competencies' => []]), [
                [self::before_declare_form_full_reminder('tool_vae_anonymise'), 1],
                [self::before_declare_form_reminder('tool_vae_anonymise_settingdetail'), 1],
                [self::before_declare_form_reminder('tool_vae_anonymise_situation'), 1],
            ]],
            'evaluation' => [new evaluation_form(null), [[self::before_evaluation_form_l47(), 1]]],
            'confirmation of an import' => [
                // Not made of hexadecimal digits: canon() would name such a token as an identifier drawn at random.
                new fiche_confirm_form(null, ['token' => str_repeat('z', 32), 'importable' => 1, 'older' => 0]),
                [[self::before_fiche_confirm_form_l73(str_repeat('z', 32)), 1]],
            ],
            'import of records' => [new fiche_import_form(null, ['maxbytes' => 1048576]), [
                [self::before_fiche_import_form_l59(), 1],
                [self::before_fiche_import_form_l75(), 1],
            ]],
            'fields the account fills' => [
                new fields_form(null, ['section' => 'identite']),
                [[self::before_fields_form_l62((int) $user->id), 1]],
            ],
            'fields the account does not fill' => [
                new fields_form(null, ['section' => 'statut']),
                [[self::before_fields_form_l62((int) $user->id), 0]],
            ],
            'annotation' => [new note_form(null), [[self::before_note_form_l46('tool_vae_anonymise_note'), 1]]],
            'piece, file' => [
                new piece_file_form(null, ['evidenceid' => 1, 'maxbytes' => 1048576]),
                [[self::before_piece_file_form_l63('tool_vae_anonymise_piecefile'), 1]],
            ],
            'piece, link' => [
                new piece_link_form(null, ['evidenceid' => 1]),
                [[self::before_piece_link_form_l50('tool_vae_anonymise_piecelink'), 1]],
            ],
            'precision answered' => [
                new precision_form(null, ['answering' => true, 'requestid' => 1]),
                [[self::before_precision_form_l50('tool_vae_anonymise_precision'), 1]],
            ],
            'precision asked' => [
                new precision_form(null, ['answering' => false, 'requestid' => 0]),
                [[self::before_precision_form_l50('tool_vae_anonymise_precision'), 0]],
            ],
            'removal of a record' => [new remove_record_form(null, ['inventory' => $inventory]), [
                [self::before_remove_record_form_l49(), 1],
                [self::before_remove_record_form_leaves($inventory), 1],
            ]],
            'scope' => [new scope_form(null, ['options' => ['1' => 'RNCP1-C1']]), [[self::before_scope_form_l50(), 1]]],
            'section' => [new section_form(null), [[self::before_section_form_l52('tool_vae_anonymise_section'), 1]]],
            'self-positioning' => [new selfpos_form(null, ['canwithdraw' => false]), [[self::before_selfpos_form_l52(), 1]]],
            'details of a situation' => [new situation_details_form(null, ['panel' => '']), [
                [self::before_situation_details_form_l50('tool_vae_anonymise_situationdetails'), 1],
                [self::before_situation_details_form_l69(), 1],
            ]],
            'text of a situation' => [new situation_form(null), [
                [self::before_situation_form_l50('tool_vae_anonymise_situation'), 1],
                [self::before_situation_form_l56(), 1],
            ]],
            'withdrawal' => [new withdraw_form(null), [[self::before_withdraw_form_l60(), 1]]],
        ];
        foreach ($cases as $name => [$form, $expected]) {
            $html = $form->render();
            foreach ($expected as $rank => [$before, $times]) {
                $this->assertSame($times, self::holds($html, $before), "$name, piece $rank");
            }
        }
        // The two short reminders of the declaration are told apart by their id only: each is found once, not twice.
        $this->assertNotSame(
            self::canon(self::before_declare_form_reminder('tool_vae_anonymise_settingdetail')),
            self::canon(self::before_declare_form_reminder('tool_vae_anonymise_situation'))
        );
    }

    public function test_the_form_of_the_details_places_the_panel_the_page_gives_as_the_renderer_draws_it(): void {
        // Added by the pilot of wave 4c: every test gave the form an empty panel, so the line that draws the panel the page
        // now gives as an object (it gave it already drawn, as a string, until the wave) was played by no test.
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_vae');
        // A box stands on a node of the second level or below: a domain, and a competency under it.
        $generator->create_source(['fichecode' => 'RNCP400', 'ficherang' => 'D1', 'shortname' => 'Domaine 1']);
        $generator->create_competency([
            'fichecode' => 'RNCP400', 'ficherang' => 'C11', 'shortname' => 'Aider', 'parent' => 'D1',
        ]);
        // The panel is drawn for somebody who may read the pathway: the vaeiste, on their own.
        $user = $this->getDataGenerator()->create_user();
        $own = $this->getDataGenerator()->create_role();
        assign_capability('tool/vae:viewown', CAP_ALLOW, $own, \context_system::instance()->id, true);
        set_role_contextlevels($own, [CONTEXT_USER]);
        role_assign($own, $user->id, \context_user::instance($user->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($user);

        $tree = fn(): whereami_tree => new whereami_tree(
            (int) $user->id,
            'RNCP400',
            ['boxes' => true, 'checked' => ['C11'], 'title' => '']
        );
        $panel = self::draw($tree());
        $boxes = substr_count($panel, 'name="tags[]"');
        $this->assertGreaterThan(0, $boxes, 'Premise: the panel holds a box.');
        $this->assertStringContainsString('Aider', $panel, 'Premise: the panel names the competency.');
        $this->assertSame(1, preg_match('/<input[^>]*name="tags\[\]"[^>]*\bchecked\b/', $panel), 'Premise: a box is ticked.');

        // As when the page gave the string: the form holds the panel once, as the renderer draws it. The form of the core
        // writes a non-breaking space in a label that ends with a tag (HTML_QuickForm_Renderer_Tableless::finishForm()), in
        // the panel as anywhere else, and it did so when the page gave the string.
        $placed = str_replace('></label>', '>&nbsp;</label>', $panel);
        $this->assertNotSame($panel, $placed, 'Premise: the label of a box ends with a tag.');
        $form = (new situation_details_form(null, ['panel' => $tree()]))->render();
        $this->assertSame(1, substr_count($form, $placed), 'The panel is placed as the renderer draws it, to the byte.');
        $this->assertSame($boxes, substr_count($form, 'name="tags[]"'), 'Once, and no other box.');

        // The page gives nothing else than the object: anything else draws no panel.
        $none = (new situation_details_form(null, ['panel' => $panel]))->render();
        $this->assertSame(0, substr_count($none, 'name="tags[]"'));
    }

    public function test_an_address_of_several_parameters_is_escaped_once_in_a_link_as_before(): void {
        // Added by the pilot of wave 4c: no form gives a link an address of two parameters, so nothing told the address
        // given plain to the template (the ampersand escaped once) from the address already escaped (escaped twice).
        $url = new \moodle_url('/admin/tool/vae/import_fiches.php', ['redo' => 'z', 'more' => 1]);
        $after = self::draw(new form_link($url, 'Words'));
        $this->assertSame(\html_writer::link($url, 'Words'), $after);
        $this->assertStringContainsString('redo=z&amp;more=1', $after);
        $this->assertStringNotContainsString('&amp;amp;', $after);
    }

    public function test_the_pieces_of_a_form_stand_where_they_stood_among_its_fields(): void {
        // Added by the pilot of wave 4c: the test above counts the pieces a form holds, and a piece moved under its field, or
        // two pieces exchanged, was seen by no test (the tool of equivalence serves neither the evaluation, the precision,
        // the marks, the removal of a record nor the confirmation of an import). Each form: marks of its HTML, in order.
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $inventory = self::inventory(['refs' => [(object) ['title' => 'Fiche', 'url' => 'https://example.com/fiche']]]);
        $short = 'class="small text-muted mb-2"';

        $cases = [
            'certification' => [new certification_form(null), [
                $short, 'name="reason"', $short, 'name="alertreason"',
            ]],
            'evaluation' => [new evaluation_form(null), ['name="status"', $short, 'name="reason"']],
            'confirmation of an import' => [
                new fiche_confirm_form(null, ['token' => str_repeat('z', 32), 'importable' => 1, 'older' => 0]),
                ['name="token"', 'type="submit"', 'import_fiches.php?redo=' . str_repeat('z', 32)],
            ],
            'import of records' => [new fiche_import_form(null, ['maxbytes' => 1048576]), [
                'name="defaultbutton"', 'name="numbers"', '>data.gouv.fr</a>',
            ]],
            'annotation' => [new note_form(null), ['id="tool_vae_anonymise_note"', 'name="notetext"']],
            'precision answered' => [
                new precision_form(null, ['answering' => true, 'requestid' => 1]),
                ['name="requestid"', 'id="tool_vae_anonymise_precision"', '<textarea'],
            ],
            'removal of a record' => [new remove_record_form(null, ['inventory' => $inventory]), [
                'name="fichecode"', 'class="alert alert-warning"', 'class="tool-vae-removal"', 'type="submit"',
            ]],
            'scope' => [new scope_form(null, ['options' => ['1' => 'RNCP1-C1']]), [
                'name="competency"', $short, 'name="note"',
            ]],
            'section' => [new section_form(null), ['id="tool_vae_anonymise_section"', '<textarea']],
            'self-positioning' => [new selfpos_form(null, ['canwithdraw' => false]), [
                'name="level"', $short, 'name="justification"',
            ]],
            'details of a situation' => [new situation_details_form(null, ['panel' => '']), [
                'id="tool_vae_anonymise_situationdetails"', 'name="title"', 'name="setting"', '<h3 class="h5 mt-3">',
                'type="submit"',
            ]],
            'text of a situation' => [new situation_form(null), [
                'id="tool_vae_anonymise_situation"', 'id="tool_vae_situationmemo"', 'name="content"',
            ]],
            'withdrawal' => [new withdraw_form(null), ['class="alert alert-warning"', 'name="reason"']],
        ];
        foreach ($cases as $name => [$form, $marks]) {
            $html = $form->render();
            $at = 0;
            foreach ($marks as $rank => $mark) {
                $found = strpos($html, $mark, $at);
                $this->assertNotFalse($found, "$name: mark $rank ($mark) stands after the one before");
                $at = $found + strlen($mark);
            }
        }
    }

    public function test_the_list_of_what_leaves_draws_the_dom_of_before_in_each_state(): void {
        $this->resetAfterTest();
        $cases = [
            'nothing attached, no date of import' => self::inventory(),
            'two addresses, a successor, two replaced, two dates' => self::inventory([
                'source' => (object) ['licence' => self::ODD, 'attribution' => 'A & "B"',
                    'sourceupdated' => 1792411200, 'lastimport' => 1792497600],
                'refs' => [
                    (object) ['title' => self::ODD, 'url' => 'https://example.com/?a=1&b="2"'],
                    (object) ['title' => 'Second', 'url' => 'https://example.com/second'],
                ],
                // Each count its own figure (the pilot of wave 4c: two passages and two marks told nothing apart).
                'referents' => 3,
                'passageids' => [4, 5, 6, 7],
                'marks' => 5,
                'successor' => 'RNCP2',
                'replaced' => ['RNCP0', 'RNCP9'],
            ]),
            'every value the figure 0' => self::inventory([
                'source' => (object) ['licence' => '0', 'attribution' => '0', 'sourceupdated' => 0, 'lastimport' => 0],
                'refs' => [(object) ['title' => '0', 'url' => '0']],
                'successor' => '0',
                'replaced' => ['0'],
            ]),
        ];
        foreach ($cases as $name => $inventory) {
            $before = self::before_remove_record_form_leaves($inventory);
            $html = self::draw(new remove_record_leaves($inventory));
            $this->assertSame(self::canon($before), self::canon($html), $name);
        }

        // The premises: the addresses are listed under their line, in their order, escaped once; none when there are none.
        $rich = self::draw(new remove_record_leaves($cases['two addresses, a successor, two replaced, two dates']));
        $this->assertSame(2, substr_count($rich, '<ul'));
        $this->assertLessThan(strpos($rich, '>Second ('), strpos($rich, '&lt;script&gt;'));
        $this->assertStringNotContainsString('<script', $rich);
        $this->assertNotSame(s(self::ODD), s(s(self::ODD)));
        $this->assertStringNotContainsString('&amp;amp;', $rich);
        $this->assertStringNotContainsString('&amp;quot;', $rich);
        $bare = self::draw(new remove_record_leaves($cases['nothing attached, no date of import']));
        $this->assertSame(1, substr_count($bare, '<ul'));
        $zero = self::draw(new remove_record_leaves($cases['every value the figure 0']));
        $this->assertStringContainsString('<li>0 (0)</li>', $zero);
    }

    public function test_the_contexts_hold_plain_texts(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');

        $this->assertSame([
            'isdiv' => true,
            'isp' => false,
            'ish3' => false,
            'class' => 'alert alert-info tool-vae-anonymise',
            'withid' => ['value' => 'tool_vae_anonymise'],
            'text' => get_string('anonymisereminder', 'tool_vae'),
        ], form_note::reminder('tool_vae_anonymise')->export_for_template($output));
        $short = form_note::short()->export_for_template($output);
        $this->assertNull($short['withid']);
        $this->assertSame(get_string('anonymiseshort', 'tool_vae'), $short['text']);
        $memo = form_note::situation_memo()->export_for_template($output);
        $this->assertTrue($memo['isp']);
        $this->assertSame(['value' => 'tool_vae_situationmemo'], $memo['withid']);
        $this->assertTrue(form_note::tags_heading()->export_for_template($output)['ish3']);

        $this->assertSame([
            'url' => (new \moodle_url('/admin/tool/vae/import_fiches.php', ['redo' => 'x1']))->out(false),
            'text' => get_string('fcimportedit', 'tool_vae'),
            'paragraph' => true,
        ], form_link::redo('x1')->export_for_template($output));
        $this->assertFalse(form_link::profile(3)->export_for_template($output)['paragraph']);
        $this->assertSame([], (new form_default_submit())->export_for_template($output));

        $leaves = (new remove_record_leaves(self::inventory()))->export_for_template($output);
        $this->assertSame(get_string('removerecordleaves', 'tool_vae'), $leaves['leaves']);
        $this->assertCount(8, $leaves['items']);
        $this->assertNull($leaves['items'][2]['refs']);
    }

    public function test_a_script_in_a_text_comes_out_escaped(): void {
        $note = self::core()->render_from_template('tool_vae/form_note', [
            'isdiv' => true, 'isp' => false, 'ish3' => false, 'class' => 'small', 'withid' => ['value' => 'x"y'],
            'text' => '<script>alert(1)</script>',
        ]);
        $link = self::draw(new form_link('https://example.com/?a="1"', '<script>alert(1)</script>', true));
        $leaves = self::draw(new remove_record_leaves(self::inventory([
            'refs' => [(object) ['title' => '<script>alert(1)</script>', 'url' => '<script>alert(2)</script>']],
        ])));
        foreach (['note' => $note, 'link' => $link, 'leaves' => $leaves] as $name => $html) {
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html, $name);
            $this->assertStringNotContainsString('<script', $html, $name);
        }
        $this->assertStringContainsString('id="x&quot;y"', $note);
        $this->assertStringContainsString('href="https://example.com/?a=&quot;1&quot;"', $link);
    }

    public function test_the_templates_render_their_example_context_and_place_nothing_raw(): void {
        foreach (['form_note', 'form_link', 'form_default_submit', 'remove_record_leaves'] as $name) {
            $file = __DIR__ . '/../../templates/' . $name . '.mustache';
            $this->assertFileExists($file);
            $source = file_get_contents($file);
            $this->assertStringContainsString('@template tool_vae/' . $name, $source);
            $this->assertStringNotContainsString('{{{', $source, $name);
            $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches), $name);
            $context = json_decode($matches[1]);
            $this->assertIsObject($context, $name . ' ' . json_last_error_msg());
            $html = self::core()->render_from_template('tool_vae/' . $name, $context);
            $this->assertNotSame('', $html, $name);
        }
    }
}
