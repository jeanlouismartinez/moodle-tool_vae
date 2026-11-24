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

use tool_vae\form\deposit_form;
use tool_vae\form\opinion_form;
use tool_vae\local\deposit_manager;
use tool_vae\local\feasibility_catalog;
use tool_vae\local\feasibility_fields;
use tool_vae\local\validation_catalog;

/**
 * The page of the deposits of a dossier (deposit.php) drawn by tool_vae\output\deposit_page (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That each state of the page draws, below its heading, the same DOM as before its migration (the code of
 * the page as it was, kept here, before_...()): a deposit of the validation dossier (one record and two, a note or none, a
 * section the catalogue does not know); a deposit of the dossier de faisabilité (with and without the separate fields, the
 * engagement confirmed or not, no opinion, two opinions of which one by an account gone, the form of an opinion or none);
 * the list (none, two deposits, a note equal to "0", the form or none, the vaeiste, a reader, the validation dossier). The
 * tool of equivalence shows five states of the page on its data; the others are proved here. Also: a script in each text
 * that comes from the data, the context, the keys placed as HTML, the example.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(deposit_page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(deposit_digest::class)]
final class deposit_page_test extends \advanced_testcase {
    use dom_canon;

    /** @var int The vaeiste. */
    private int $candidateid;

    /** @var \moodle_url The list of the deposits. */
    private \moodle_url $url;

    #[\Override]
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->candidateid = (int) $this->getDataGenerator()->create_user()->id;
        $this->url = new \moodle_url('/admin/tool/vae/deposit.php', ['userid' => $this->candidateid]);
        $PAGE->set_url($this->url);
        $PAGE->set_context(\context_user::instance($this->candidateid));
    }

    /**
     * The page drawn by the renderer of the plugin.
     *
     * @param deposit_page $page
     * @return string HTML.
     */
    private function render(deposit_page $page): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($page);
    }

    /**
     * What a form prints.
     *
     * @param \moodleform $form
     * @return string
     */
    private static function display(\moodleform $form): string {
        ob_start();
        $form->display();

        return ob_get_clean();
    }

    /**
     * A deposit made by hand, as the table holds it.
     *
     * @param string $doctype
     * @param array $content
     * @param array $override
     * @return \stdClass
     */
    private function made(string $doctype, array $content, array $override = []): \stdClass {
        return (object) ($override + [
            'id' => 1, 'candidateid' => $this->candidateid, 'doctype' => $doctype, 'number' => 2,
            'note' => 'Deuxième dépôt.', 'contenthash' => str_repeat('3f2a9c41b07e', 5) . 'abcd',
            'content' => json_encode($content), 'timecreated' => make_timestamp(2026, 10, 19, 10, 30, 0, 99),
        ]);
    }

    /**
     * A deposit of the dossier de faisabilité.
     *
     * @param bool $engagement
     * @param array $override
     * @return \stdClass
     */
    private function faisabilite(bool $engagement, array $override = []): \stdClass {
        $section = fn(string $code, int $slot, string $text) => [
            'code' => $code, 'emplacement' => $slot, 'version' => 3, 'enregistree_le' => '2026-10-18T10:00:00+00:00',
            'contenu' => $text,
        ];

        return $this->made('faisabilite', [
            'rubriques' => [
                $section('certification', 0, "Ma certification.\nDeux lignes."),
                $section('identite', 0, json_encode(['nom' => 'Martin', 'prenom' => 'Lucie'])),
                $section('experiences', 2, 'Une expérience.'),
                $section('inconnue', 0, 'Une section que le catalogue ne connaît pas.'),
            ],
            'engagement_confirme' => $engagement,
        ], $override);
    }

    /**
     * A deposit of the preparation of the validation dossier.
     *
     * @param array $override
     * @return \stdClass
     */
    private function validation(array $override = []): \stdClass {
        $section = fn(string $fiche, string $code, string $text) => [
            'fiche' => $fiche, 'code' => $code, 'version' => 4, 'texte' => $text,
        ];

        return $this->made('validation', ['rubriques' => [
            $section('RNCP99001', 'bc01_situation', 'Une situation.'),
            $section('RNCP99001', 'motivation', "Ma motivation.\nSuite."),
            $section('RNCP99002', 'bc02_analyse', 'Une analyse.'),
        ]], $override);
    }

    /**
     * The deposit of the validation dossier as deposit.php drew it, below its heading.
     *
     * @param \stdClass $deposit
     * @return string
     */
    private function before_validation(\stdClass $deposit): string {
        $output = self::core();
        $out = '';
        if ($deposit->note !== null && $deposit->note !== '') {
            $out .= \html_writer::tag('p', s($deposit->note));
        }
        $out .= \html_writer::tag('p', s(get_string('depositdigest', 'tool_vae', $deposit->contenthash)));
        $out .= \html_writer::div(
            s(get_string('validationlabelling', 'tool_vae')),
            'alert alert-info tool-vae-labelling',
            ['role' => 'note']
        );
        $fiche = null;
        foreach (deposit_manager::validation_sections($deposit) as $validationsection) {
            if ($validationsection['fiche'] !== $fiche) {
                $fiche = $validationsection['fiche'];
                $out .= $output->heading(s(get_string('validationdepositrecord', 'tool_vae', $fiche)), 3);
            }
            $out .= $output->heading(s(validation_catalog::label($fiche, (string) $validationsection['code'])), 4);
            $out .= \html_writer::tag(
                'p',
                s(get_string('depositsectionversion', 'tool_vae', (int) $validationsection['version'])),
                ['class' => 'text-muted small']
            );
            $out .= format_text((string) $validationsection['texte'], FORMAT_PLAIN, [
                'context' => \context_user::instance($this->candidateid),
                'filter' => false,
            ]);
        }

        return $out . \html_writer::tag('p', \html_writer::link($this->url, get_string('depositbacklist', 'tool_vae')));
    }

    /**
     * The deposit of the dossier de faisabilité as deposit.php drew it, below its heading.
     *
     * @param \stdClass $deposit
     * @param bool $withfields
     * @param \stdClass[] $opinions
     * @param opinion_form|null $opinionform
     * @return string
     */
    private function before_deposit(\stdClass $deposit, bool $withfields, array $opinions, ?opinion_form $opinionform): string {
        $output = self::core();
        $out = '';
        if ($deposit->note !== null && $deposit->note !== '') {
            $out .= \html_writer::tag('p', s($deposit->note));
        }
        $out .= \html_writer::tag('p', s(get_string('depositdigest', 'tool_vae', $deposit->contenthash)));
        if (deposit_manager::engagement_confirmed($deposit)) {
            $out .= \html_writer::tag('p', s(get_string('depositengagementdone', 'tool_vae')));
        }
        foreach (deposit_manager::sections($deposit, $withfields) as $section) {
            $out .= $output->heading(s(feasibility_catalog::label($section['code'], $section['slot'])), 3);
            $text = $section['kind'] === feasibility_catalog::KIND_FIELDS
                ? feasibility_fields::readable($section['code'], $section['content'])
                : $section['content'];
            $out .= \html_writer::tag('p', s(get_string('depositsectionversion', 'tool_vae', $section['version'])), [
                'class' => 'text-muted small',
            ]);
            $out .= format_text($text, FORMAT_PLAIN, [
                'context' => \context_user::instance($this->candidateid),
                'filter' => false,
            ]);
        }
        if (!$withfields) {
            $out .= $output->notification(
                get_string('documentidentityomitted', 'tool_vae'),
                \core\output\notification::NOTIFY_INFO
            );
        }
        $out .= $output->heading(get_string('opinionheading', 'tool_vae'), 3);
        if (!$opinions) {
            $out .= \html_writer::tag('p', s(get_string('opinionnone', 'tool_vae')));
        }
        foreach ($opinions as $opinion) {
            $by = $opinion->authorname !== '' ? $opinion->authorname : get_string('opinionformerauthor', 'tool_vae');
            $out .= \html_writer::tag('p', s($by . ' - ' . userdate(
                $opinion->timecreated,
                get_string('strftimedatetime', 'langconfig')
            )), ['class' => 'text-muted small']);
            $out .= format_text($opinion->content, FORMAT_PLAIN, [
                'context' => \context_user::instance($this->candidateid),
                'filter' => false,
            ]);
        }
        if ($opinionform) {
            $out .= \html_writer::tag('p', s(get_string('opinionintro', 'tool_vae')));
            $out .= self::display($opinionform);
        }

        return $out . \html_writer::tag('p', \html_writer::link($this->url, get_string('depositbacklist', 'tool_vae')));
    }

    /**
     * The list of the deposits as deposit.php drew it, below its heading.
     *
     * @param bool $isvalidation
     * @param deposit_form|null $form
     * @param \stdClass[] $deposits
     * @param bool $own
     * @return string
     */
    private function before_listing(bool $isvalidation, ?deposit_form $form, array $deposits, bool $own): string {
        $out = '';
        $url = $this->url;
        $candidateid = $this->candidateid;
        if ($isvalidation) {
            $out .= \html_writer::div(
                s(get_string('validationlabelling', 'tool_vae')),
                'alert alert-info tool-vae-labelling',
                ['role' => 'note']
            );
        }
        if ($form) {
            $out .= \html_writer::tag('p', s(get_string($isvalidation ? 'validationdepositintro' : 'depositintro', 'tool_vae')));
            $out .= self::display($form);
        }
        if (!$deposits) {
            $out .= \html_writer::tag('p', s(get_string('depositnone', 'tool_vae')));
        } else {
            $table = new \html_table();
            $table->attributes['class'] = 'generaltable tool-vae-deposits';
            $table->head = [
                get_string('depositcolnumber', 'tool_vae'),
                get_string('depositcoldate', 'tool_vae'),
                get_string('depositcolnote', 'tool_vae'),
                get_string('depositcoldigest', 'tool_vae'),
                get_string('faisabcolactions', 'tool_vae'),
            ];
            foreach ($deposits as $deposit) {
                $table->data[] = [
                    (int) $deposit->number,
                    s(userdate($deposit->timecreated, get_string('strftimedatetime', 'langconfig'))),
                    s((string) $deposit->note),
                    \html_writer::tag('code', s(substr($deposit->contenthash, 0, 12))),
                    \html_writer::link(
                        new \moodle_url($url, ['number' => (int) $deposit->number]),
                        get_string('depositread', 'tool_vae', (int) $deposit->number)
                    ),
                ];
            }
            $out .= \html_writer::table($table);
        }
        if ($isvalidation && $own) {
            $back = \html_writer::link(
                new \moodle_url('/admin/tool/vae/validation.php'),
                get_string('validationtitle', 'tool_vae')
            );
        } else {
            $back = \html_writer::link(
                $own
                    ? new \moodle_url('/admin/tool/vae/faisabilite.php')
                    : new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]),
                get_string($own ? 'faisabheading' : 'faisabbacktodossier', 'tool_vae')
            );
        }

        return $out . \html_writer::tag('p', $back);
    }

    /**
     * Two opinions, one by an account gone.
     *
     * @param string $text
     * @return \stdClass[]
     */
    private static function opinions(string $text = "Un avis.\nSur deux lignes."): array {
        return [
            (object) [
                'authorname' => 'Marie Lefèvre', 'timecreated' => make_timestamp(2026, 10, 20, 9, 0, 0, 99), 'content' => $text,
            ],
            (object) ['authorname' => '', 'timecreated' => make_timestamp(2026, 10, 21, 9, 0, 0, 99), 'content' => 'Un autre.'],
        ];
    }

    public function test_the_page_is_a_named_template(): void {
        global $PAGE;

        $page = deposit_page::listing(false, null, [], $this->url, true, $this->candidateid);

        $this->assertInstanceOf(\core\output\renderable::class, $page);
        $this->assertInstanceOf(\core\output\named_templatable::class, $page);
        $this->assertSame('tool_vae/deposit_page', $page->get_template_name($PAGE->get_renderer('tool_vae')));
        $this->assertSame('tool_vae/deposit_digest', (new deposit_digest('a'))->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    public function test_a_deposit_of_the_validation_dossier_draws_the_dom_of_before(): void {
        foreach (
            [
                'two records and a note' => $this->validation(),
                'no note' => $this->validation(['note' => null]),
                'an empty note' => $this->validation(['note' => '']),
                'a note equal to 0' => $this->validation(['note' => '0']),
                'no section' => $this->made('validation', ['rubriques' => []]),
            ] as $name => $deposit
        ) {
            $this->assertSame(
                self::canon($this->before_validation($deposit)),
                self::canon($this->render(deposit_page::validation($deposit, $this->url, $this->candidateid))),
                $name
            );
        }
        $html = $this->before_validation($this->validation());
        $this->assertSame(2, substr_count($html, '<h3'), 'Premise: one heading per record.');
        $this->assertSame(3, substr_count($html, '<h4'));
        $this->assertStringContainsString('<p>0</p>', $this->before_validation($this->validation(['note' => '0'])));
    }

    public function test_a_deposit_of_the_dossier_de_faisabilite_draws_the_dom_of_before(): void {
        $url = new \moodle_url($this->url, ['number' => 2]);
        $cases = [
            'everything' => [$this->faisabilite(true), true, self::opinions(), true],
            'no field, no engagement, no opinion, no form' => [$this->faisabilite(false), false, [], false],
            'a note equal to 0, an opinion equal to 0' => [
                $this->faisabilite(false, ['note' => '0']), true, self::opinions('0'), false,
            ],
        ];
        foreach ($cases as $name => [$deposit, $withfields, $opinions, $withform]) {
            $opinionform = $withform ? new opinion_form($url->out(false)) : null;
            $before = $this->before_deposit($deposit, $withfields, $opinions, $opinionform);
            $html = $this->render(deposit_page::deposit(
                $deposit,
                $withfields,
                $opinions,
                $withform ? new opinion_form($url->out(false)) : null,
                $this->url,
                $this->candidateid
            ));
            $this->assertSame(self::canon($before), self::canon($html), $name);
        }
        // Premises: the fields, the engagement, the gone account and the form are drawn where they are named.
        $all = $this->before_deposit($this->faisabilite(true), true, self::opinions(), new opinion_form($url->out(false)));
        $none = $this->before_deposit($this->faisabilite(false), false, [], null);
        $this->assertStringContainsString('Lucie', $all);
        $this->assertStringNotContainsString('Lucie', $none);
        $this->assertStringContainsString(s(get_string('depositengagementdone', 'tool_vae')), $all);
        $this->assertStringNotContainsString(s(get_string('depositengagementdone', 'tool_vae')), $none);
        $this->assertStringContainsString(s(get_string('opinionformerauthor', 'tool_vae')), $all);
        $this->assertStringContainsString('<form', $all);
        $this->assertStringContainsString(s(get_string('opinionnone', 'tool_vae')), $none);
        $this->assertStringContainsString(get_string('documentidentityomitted', 'tool_vae'), $none);
    }

    public function test_the_separate_fields_of_a_deposit_are_read_as_lines_as_before(): void {
        // Added by the pilot of lot 7: the identity of the deposits above holds keys the plugin refuses, so that it is drawn
        // as it is stored and nothing told a section of fields from a section of free text. Here the identity is one the
        // plugin decodes, and the same text is also given to a section of free text, which must stay as it was written.
        $identity = json_encode([
            'nom_naissance' => 'Martin', 'date_naissance' => '1984-03-12', 'nationalite' => 'FR',
            'niveau_formation' => '5', 'niveau_certification' => '4', 'certifications' => "CAP Petite enfance\nBEP",
        ]);
        $section = fn(string $code) => [
            'code' => $code, 'emplacement' => 0, 'version' => 2, 'enregistree_le' => '2026-10-18T10:00:00+00:00',
            'contenu' => $identity,
        ];
        $deposit = $this->made('faisabilite', [
            'rubriques' => [$section('identite'), $section('certification')],
            'engagement_confirme' => false,
        ]);

        $before = $this->before_deposit($deposit, true, [], null);
        $html = $this->render(deposit_page::deposit($deposit, true, [], null, $this->url, $this->candidateid));

        $this->assertSame(self::canon($before), self::canon($html));
        // Premises: the fields read as lines under their labels; the stored text shows once, in the section of free text.
        $line = s(get_string('faisabfield:nom_naissance', 'tool_vae')) . ' : Martin';
        $this->assertSame(1, substr_count($before, $line));
        $this->assertSame(1, substr_count($before, 'nom_naissance'));
    }

    public function test_the_list_draws_the_dom_of_before(): void {
        $deposits = [
            3 => $this->made('faisabilite', [], ['number' => 3, 'note' => '0']),
            2 => $this->made('faisabilite', [], ['number' => 2, 'note' => null]),
            1 => $this->made('faisabilite', [], ['number' => 1, 'note' => 'Premier & seul']),
        ];
        $form = fn(bool $validation) => new deposit_form($this->url->out(false), ['validation' => $validation]);
        $cases = [
            'the vaeiste, no deposit, the form' => [false, true, [], true],
            'the vaeiste, three deposits, the form' => [false, true, $deposits, true],
            'a reader, three deposits' => [false, false, $deposits, false],
            'the validation dossier, the vaeiste' => [true, true, $deposits, true],
            'the validation dossier, a reader, none' => [true, false, [], false],
        ];
        foreach ($cases as $name => [$validation, $own, $list, $withform]) {
            $before = $this->before_listing($validation, $withform ? $form($validation) : null, $list, $own);
            $html = $this->render(deposit_page::listing(
                $validation,
                $withform ? $form($validation) : null,
                $list,
                $this->url,
                $own,
                $this->candidateid
            ));
            $this->assertSame(self::canon($before), self::canon($html), $name);
        }
        $reader = $this->before_listing(false, null, [], false);
        $this->assertStringContainsString('dossier.php?userid=' . $this->candidateid, $reader);
        $this->assertStringContainsString('validation.php', $this->before_listing(true, null, [], true));
    }

    public function test_a_script_in_what_comes_from_the_data_comes_out_escaped(): void {
        $script = '<script>alert(1)</script>';
        // The record and an unknown section of the validation dossier are put into headings of the core.
        $section = ['fiche' => $script, 'code' => $script, 'version' => 1, 'texte' => $script];
        $deposit = $this->made('validation', ['rubriques' => [$section]], ['note' => $script, 'contenthash' => $script]);
        $html = $this->render(deposit_page::validation($deposit, $this->url, $this->candidateid));
        $this->assertStringNotContainsString('<script', $html);
        $this->assertSame(5, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Note, digest, record, section, text.');
        $this->assertSame(self::canon($this->before_validation($deposit)), self::canon($html));

        $opinions = [(object) ['authorname' => $script, 'timecreated' => 0, 'content' => $script]];
        $deposit = $this->faisabilite(true, ['note' => $script]);
        $html = $this->render(deposit_page::deposit($deposit, true, $opinions, null, $this->url, $this->candidateid));
        $this->assertStringNotContainsString('<script', $html);
        $this->assertSame(3, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Note, author, opinion.');
        $this->assertSame(self::canon($this->before_deposit($deposit, true, $opinions, null)), self::canon($html));

        $list = [1 => $this->made('faisabilite', [], ['number' => 1, 'note' => $script, 'contenthash' => $script])];
        $html = $this->render(deposit_page::listing(false, null, $list, $this->url, true, $this->candidateid));
        $this->assertStringNotContainsString('<script', $html);
        $this->assertSame(1, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'The note.');
        $this->assertStringContainsString('<code>&lt;script&gt;aler</code>', $html, 'The digest, cut at 12.');
        $this->assertSame(self::canon($this->before_listing(false, null, $list, true)), self::canon($html));
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL);

        $context = deposit_page::deposit($this->faisabilite(true, ['note' => 'a<b']), false, [], null, $this->url, 1)
            ->export_for_template($output);
        $this->assertSame(['label' => 'a<b'], $context['note']);
        $this->assertSame(['text' => get_string('depositengagementdone', 'tool_vae')], $context['engagement']);
        $back = ['url' => $this->url->out(false), 'label' => get_string('depositbacklist', 'tool_vae')];
        $this->assertSame($back, $context['back']);
        $this->assertStringContainsString('alert-info', $context['omittedhtml']);
        $this->assertSame(['text' => get_string('opinionnone', 'tool_vae')], $context['opinions']['none']);
        $this->assertFalse($context['opinions']['opinionformhtml']);
        $this->assertCount(2, $context['sections'], 'The identity is left out; an unknown section is never shown.');

        $list = deposit_page::listing(true, null, [], $this->url, false, $this->candidateid)->export_for_template($output);
        $this->assertSame(['text' => get_string('validationlabelling', 'tool_vae')], $list['labelling']);
        $this->assertSame(['text' => get_string('depositnone', 'tool_vae')], $list['none']);
        $this->assertFalse($list['tablehtml']);
        $this->assertSame(get_string('faisabbacktodossier', 'tool_vae'), $list['back']['label']);
        $this->assertSame(['digest' => 'x<'], (new deposit_digest('x<'))->export_for_template($output));
    }

    public function test_only_the_html_of_the_core_and_of_the_pieces_is_placed_as_it_is(): void {
        $source = file_get_contents(__DIR__ . '/../../templates/deposit_page.mustache');
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(
            ['formhtml', 'recordhtml', 'headinghtml', 'texthtml', 'omittedhtml', 'opinionformhtml', 'tablehtml'],
            array_values(array_unique($raw[1]))
        );
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertStringNotContainsString('{{{', file_get_contents(__DIR__ . '/../../templates/deposit_digest.mustache'));
    }

    public function test_the_templates_render_their_example_context(): void {
        foreach (['deposit_page' => 'text_to_html', 'deposit_digest' => '<code>'] as $template => $mark) {
            $source = file_get_contents(__DIR__ . '/../../templates/' . $template . '.mustache');
            $this->assertStringContainsString('@template tool_vae/' . $template, $source);
            $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches), $template);
            $context = json_decode($matches[1]);
            $this->assertIsObject($context, $template . ': ' . json_last_error_msg());

            $html = self::core()->render_from_template('tool_vae/' . $template, $context);

            $this->assertStringContainsString($mark, $html, $template);
        }
    }
}
