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

use tool_vae\local\draft_manager;
use tool_vae\local\note_manager;
use tool_vae\local\situation_manager;
use tool_vae\local\situation_screen;
use tool_vae\local\spiece_manager;
use tool_vae\local\validation_catalog;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/dom_canon.php');
require_once(__DIR__ . '/situation_before.php');
require_once(__DIR__ . '/whereami_before.php');

/**
 * The page of one work situation (situation.php) draws, under its title, the DOM it drew before lot 7 (MMR-520 ticket 4,
 * wave 4a), out of the confirmation of a deletion (a screen of the core the page still draws itself): its code from under
 * the heading to the footer, kept here in before_situation(), the old screens by the trait situation_before; for the vaeiste
 * who writes (the forms, a version, a draft saved, a conflict, an error, the pieces with their buttons and their forms, the
 * adding closed), the record hidden and the preparation closed with and without a text given back, and a reader (the
 * latest of two versions, the draft in progress, a draft of blanks, the annotations and their form, the error of one).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(situation_page::class)]
final class situation_page_test extends \advanced_testcase {
    use dom_canon;
    use situation_before;
    use whereami_before;

    /** @var \stdClass The vaeiste. */
    private $vaeiste;

    /** @var \stdClass Follows the vaeiste. */
    private $manager;

    /** @var \tool_vae_generator */
    private $generator;

    /**
     * RNCP100 (BC01 holding C01 and C02); the vaeiste and the manager who follows; the preparation and the pieces open.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        validation_catalog::reset();
        set_config('validationopen', 1, 'tool_vae');
        set_config('piecesopen', 1, 'tool_vae');
        $datagenerator = $this->getDataGenerator();
        $this->generator = $datagenerator->get_plugin_generator('tool_vae');
        $this->generator->create_source(['fichecode' => 'RNCP100', 'ficherang' => 'BC01', 'shortname' => 'Bloc 1']);
        foreach (['C01', 'C02'] as $rank) {
            $this->generator->create_competency([
                'fichecode' => 'RNCP100', 'ficherang' => $rank, 'shortname' => 'Competence ' . $rank, 'bloc' => 'BC01',
            ]);
        }
        $system = \context_system::instance()->id;
        $this->vaeiste = $datagenerator->create_user(['firstname' => 'Camille', 'lastname' => 'Vaeiste']);
        $this->manager = $datagenerator->create_user(['firstname' => 'Nadia', 'lastname' => 'Benali']);
        $own = $datagenerator->create_role();
        foreach (['declare', 'viewown', 'positionself', 'draftown'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $own, $system, true);
        }
        set_role_contextlevels($own, [CONTEXT_USER]);
        role_assign($own, $this->vaeiste->id, \context_user::instance($this->vaeiste->id)->id);
        $follow = $datagenerator->create_role();
        assign_capability('tool/vae:viewassigned', CAP_ALLOW, $follow, $system, true);
        set_role_contextlevels($follow, [CONTEXT_USER]);
        role_assign($follow, $this->manager->id, \context_user::instance($this->vaeiste->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($this->vaeiste);
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
     * What situation.php wrote under its heading for one situation, before lot 7. The only changes: echo becomes $out .=,
     * $OUTPUT is self::core(), $form->display() $out .= $form->render(), the screens the old code kept in the trait, and what
     * the page read or built is given (the function of the buttons is built here from $mayact, as the page built it).
     *
     * @param array $p The values the page had, by the names of its variables.
     * @return string
     */
    private static function before_situation(array $p): string {
        global $OUTPUT;

        $kept = $OUTPUT;
        $OUTPUT = self::core();
        try {
            [$fichecode, $slot, $candidateid, $context, $error, $conflict, $url] = [$p['fichecode'], $p['slot'],
                $p['candidateid'], $p['context'], $p['error'], $p['conflict'], $p['url']];
            [$hiddenmessage, $canwrite, $open, $keptsent, $editing, $detailsform, $textform] = [$p['hiddenmessage'],
                $p['canwrite'], $p['open'], $p['keptsent'], $p['editing'], $p['detailsform'], $p['textform']];
            [$latest, $draft, $situation, $history, $links, $mayact, $canadd] = [$p['latest'], $p['draft'], $p['situation'],
                $p['history'], $p['links'], $p['mayact'], $p['canadd']];
            [$attachable, $fileform, $noteerror, $noterows, $canannotate, $noteform, $listurl] = [$p['attachable'],
                $p['fileform'], $p['noteerror'], $p['noterows'], $p['canannotate'], $p['noteform'], $p['listurl']];
            $notify = \core\output\notification::class;
            $out = '';
            $out .= \html_writer::tag('p', \html_writer::tag('strong', s($fichecode)));
            $out .= self::before_situation_disclaimer($candidateid);
            if ($error !== null) {
                $out .= $OUTPUT->notification(s($error), $notify::NOTIFY_ERROR);
            }
            if ($conflict) {
                $out .= $OUTPUT->notification(get_string('errordraftconflict', 'tool_vae'), $notify::NOTIFY_ERROR);
                $out .= \html_writer::tag('p', s(get_string('draftconflicttextkept', 'tool_vae')));
                $out .= \html_writer::tag('p', \html_writer::link($url, get_string('draftconflictreload', 'tool_vae')));
            }
            if ($hiddenmessage !== null) {
                $out .= self::before_situation_refused($hiddenmessage, $canwrite ? $keptsent : []);
            } else if ($canwrite && !$open) {
                $out .= self::before_situation_closed($keptsent);
            }

            if ($editing) {
                $out .= $detailsform->render();
                $out .= \html_writer::tag('h3', s(get_string('situationtext', 'tool_vae')), ['class' => 'h5 mt-4']);
                $out .= \html_writer::tag('p', s($latest !== null
                    ? get_string('sectioncurrentversion', 'tool_vae', (int) $latest->number)
                    : get_string('sectionnoversion', 'tool_vae')));
                $out .= $textform->render();
                $out .= \html_writer::div(
                    $draft->workingat && !$conflict
                        ? s(get_string('draftautosaved', 'tool_vae', userdate((int) $draft->workingat))) : '',
                    'text-muted small',
                    ['id' => 'tool-vae-autosave', 'role' => 'status', 'aria-live' => 'polite']
                );
            } else {
                $out .= self::before_situation_details($situation);
                $out .= self::before_situation_text($latest, $draft, $context);
            }
            $out .= \html_writer::tag('p', \html_writer::link($history, get_string('situationversions', 'tool_vae')));

            $actions = null;
            if ($mayact) {
                $actions = function (\stdClass $link) use ($OUTPUT, $url): string {
                    $out = $OUTPUT->single_button(
                        new \moodle_url($url, ['action' => 'detach', 'piece' => (int) $link->pieceid]),
                        get_string('spiecedetach', 'tool_vae'),
                        'post'
                    );
                    if ($link->piece->timeremoved === null) {
                        $out .= $OUTPUT->single_button(
                            new \moodle_url($url, ['action' => 'remove', 'piece' => (int) $link->pieceid]),
                            get_string('pieceremove', 'tool_vae'),
                            'post'
                        );
                    }

                    return $out;
                };
            }
            $out .= self::before_situation_pieces($links, $context, $actions);
            if ($canadd) {
                $out .= self::before_situation_piece_forms($url, $attachable, $fileform);
            } else if ($mayact) {
                $out .= $OUTPUT->notification(get_string('piececlosed', 'tool_vae'), $notify::NOTIFY_INFO);
            }

            if ($noteerror !== null) {
                $out .= $OUTPUT->notification(s($noteerror), $notify::NOTIFY_ERROR);
            }
            $out .= self::before_note_block(
                $candidateid,
                note_manager::TARGET_SITUATION,
                $fichecode,
                $slot,
                '',
                $noterows,
                $canannotate,
                $noteform ? $noteform->render() : ''
            );

            if ($editing) {
                $out .= $OUTPUT->single_button(
                    new \moodle_url($url, ['action' => 'delete']),
                    get_string('situationdelete', 'tool_vae'),
                    'post'
                );
            }
            $out .= \html_writer::tag('p', \html_writer::link($listurl, get_string('situationbacklist', 'tool_vae')));

            return $out;
        } finally {
            $OUTPUT = $kept;
        }
    }

    /**
     * The values of the page for one situation, as situation.php reads them, with the forms built new.
     *
     * @param \stdClass $situation
     * @param array $more Values that differ from what the page reads (error, conflict, hiddenmessage, open, keptsent,
     *        canadd, noteerror, noterows, canannotate, note).
     * @return array
     */
    private function values(\stdClass $situation, array $more): array {
        global $DB, $USER;

        $id = (int) $this->vaeiste->id;
        $manager = new situation_manager();
        $url = situation_screen::url($id, 'RNCP100', (int) $situation->slot);
        $canwrite = situation_manager::can_write($id);
        $hiddenmessage = $more['hiddenmessage'] ?? null;
        $open = $more['open'] ?? true;
        $mayact = $canwrite && $hiddenmessage === null;
        $editing = $mayact && $open;
        $versions = $manager->versions((int) $situation->id);
        $draft = $DB->get_record('tool_vae_draft', ['id' => (int) $situation->draftid], '*', MUST_EXIST);
        $pieces = new spiece_manager();
        $links = $pieces->for_situation((int) $situation->id);
        $canadd = $more['canadd'] ?? $mayact;
        $linked = array_map(fn($link) => (int) $link->pieceid, $links);
        $attachable = $canadd ? array_filter(
            $pieces->for_candidate($id, 'RNCP100'),
            fn($piece) => $piece->timeremoved === null && !in_array((int) $piece->id, $linked, true)
        ) : [];
        $form = fn() => $editing ? new \tool_vae\form\situation_details_form($url->out(false), ['panel' => '']) : null;

        return [
            'situation' => $situation,
            'fichecode' => 'RNCP100',
            'slot' => (int) $situation->slot,
            'candidateid' => $id,
            'context' => \context_user::instance($id),
            'error' => $more['error'] ?? null,
            'conflict' => $more['conflict'] ?? false,
            'url' => $url,
            'hiddenmessage' => $hiddenmessage,
            'canwrite' => $canwrite,
            'open' => $open,
            'keptsent' => $more['keptsent'] ?? [],
            'editing' => $editing,
            'detailsform' => $form,
            'textform' => fn() => $editing ? new \tool_vae\form\situation_form($url->out(false)) : null,
            'latest' => $versions ? reset($versions) : null,
            'draft' => $draft,
            'history' => new \moodle_url('/admin/tool/vae/history.php', ['draftid' => (int) $draft->id]),
            'links' => $links,
            'mayact' => $mayact,
            'canadd' => $canadd,
            'attachable' => $attachable,
            'fileform' => fn() => $mayact ? new \tool_vae\form\spiece_file_form(
                $url->out(false),
                ['maxbytes' => 1048576],
                'post',
                '',
                ['class' => 'mb-4 tool-vae-spiece-file']
            ) : null,
            'noteerror' => $more['noteerror'] ?? null,
            'noterows' => $more['noterows'] ?? [],
            'canannotate' => $more['canannotate'] ?? false,
            'noteform' => fn() => !empty($more['note']) ? new \tool_vae\form\note_form($url->out(false)) : null,
            'listurl' => situation_screen::url($id, 'RNCP100'),
            'reader' => (int) $USER->id,
        ];
    }

    /**
     * The old drawing and the new one of the same values, each with its own forms.
     *
     * @param array $v
     * @return string[] old, new
     */
    private function both(array $v): array {
        $old = $v;
        foreach (['detailsform', 'textform', 'fileform', 'noteform'] as $name) {
            $old[$name] = $v[$name]();
        }
        $before = self::before_situation($old);
        $new = $v;
        foreach (['detailsform', 'textform', 'fileform', 'noteform'] as $name) {
            $new[$name] = $v[$name]();
        }
        $after = $this->render(situation_page::situation(
            $new['situation'],
            $new['candidateid'],
            $new['context'],
            $new['error'],
            $new['conflict'],
            $new['url'],
            $new['hiddenmessage'],
            $new['canwrite'],
            $new['open'],
            $new['keptsent'],
            $new['editing'],
            $new['detailsform'],
            $new['textform'],
            $new['latest'],
            $new['draft'],
            $new['history'],
            new situation_pieces($new['links'], $new['context'], $new['mayact'] ? $new['url'] : null),
            $new['mayact'],
            $new['canadd'] ? new situation_piece_forms($new['url'], $new['attachable'], $new['fileform']) : null,
            $new['noteerror'],
            $new['noterows'],
            $new['canannotate'],
            $new['noteform'],
            $new['listurl']
        ));

        return [self::canon(self::drafts($before)), self::canon(self::drafts($after)), $before, $after];
    }

    public function test_the_vaeiste_who_writes_gets_the_dom_of_before_in_each_state(): void {
        $id = (int) $this->vaeiste->id;
        $situation = $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP100', 'title' => 'Accueil',
            'tags' => 'C01', 'text' => 'Version un.']);
        $other = $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP100', 'title' => 'Autre']);
        $pieces = new spiece_manager();
        $pieces->add_link((int) $situation->id, 'other', 'Un lien', 'https://example.org/a', 'Le debut');
        $pieces->add_link((int) $other->id, 'writing', 'A attacher', 'https://example.org/b', '');
        (new draft_manager())->save_working((int) $situation->draftid, 'En cours.');
        $situation = (new situation_manager())->get((int) $situation->id);
        $states = [
            'writing, a version, a draft saved, a piece to attach' => [],
            'a conflict, an error' => ['conflict' => true, 'error' => '<script>alert(1)</script> & "vu"'],
            'adding closed' => ['canadd' => false],
            'closed, a text given back' => ['open' => false,
                'keptsent' => ['title' => 'Titre & "tape"', 'content' => "Mon\n texte."]],
            'closed, nothing sent' => ['open' => false],
            'hidden, a text given back' => ['hiddenmessage' => 'The record Aide &amp; soins is hidden.',
                'keptsent' => ['setting' => '0']],
        ];
        foreach ($states as $name => $more) {
            [$old, $new, $before, $after] = $this->both($this->values($situation, $more));
            $this->assertSame($old, $new, $name);
            $editing = empty($more['hiddenmessage']) && ($more['open'] ?? true);
            $this->assertSame($editing, str_contains($before, 'tool-vae-autosave'), $name . ': premise, the forms.');
            $this->assertSame($editing, str_contains($before, 'value="delete"'), $name . ': the button delete.');
            $this->assertSame(!empty($more['conflict']), str_contains($before, s(get_string('draftconflicttextkept', 'tool_vae'))));
            $this->assertSame(isset($more['keptsent']), str_contains($before, '<textarea readonly'), $name . ': text given.');
            $this->assertSame(isset($more['canadd']), str_contains($before, s(get_string('piececlosed', 'tool_vae'))), $name);
            $this->assertSame(empty($more['hiddenmessage']), str_contains($before, 'value="detach"'), $name . ': buttons.');
            // The forms of the core carry scripts of their own: the error, never.
            $this->assertStringNotContainsString('<script>alert', $after);
        }
        // Writing with no version yet: the sentence of the form, never the one of the reading (the pilot, a mutant).
        $fresh = (new situation_manager())->get((int) $other->id);
        [$old, $new, $before] = $this->both($this->values($fresh, []));
        $this->assertSame($old, $new, 'writing, no version yet');
        $this->assertStringContainsString('tool-vae-autosave', $before, 'Premise: the forms.');
        $this->assertStringContainsString(s(get_string('sectionnoversion', 'tool_vae')), $before, 'Premise: no version yet.');
        [, , $before] = $this->both($this->values($situation, []));
        $this->assertStringContainsString('tool-vae-spiece-attach', $before, 'Premise: a piece to attach.');
        $live = '~<div class="text-muted small" id="tool-vae-autosave" role="status" aria-live="polite">[^<]+</div>~';
        $this->assertMatchesRegularExpression($live, $before, 'Premise: the draft saved, said in the live region.');
        [, , $before] = $this->both($this->values($situation, ['conflict' => true]));
        $this->assertDoesNotMatchRegularExpression($live, $before, 'Premise: empty after a conflict.');
        [, , $before] = $this->both($this->values($situation, ['open' => false, 'keptsent' => ['content' => 'x']]));
        $this->assertStringContainsString('alert-danger', $before, 'Premise: something given back, the alarm.');
        $this->assertStringContainsString('rows="8"', $before, 'Premise: the text in eight rows.');
        [, , $before] = $this->both($this->values($situation, ['open' => false]));
        $this->assertStringContainsString('alert alert-info', $before, 'Premise: nothing given back, the information.');
    }

    public function test_the_text_kept_and_given_back_is_written_exactly(): void {
        $id = (int) $this->vaeiste->id;
        $situation = $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP100', 'title' => 'Accueil']);
        [, , $before, $after] = $this->both($this->values($situation, ['open' => false,
            'keptsent' => ['content' => "  Deux espaces\n& une ligne"]]));
        $exact = '>  Deux espaces' . "\n" . '&amp; une ligne</textarea>';
        $this->assertStringContainsString($exact, $before, 'Premise: the old code wrote it so.');
        $this->assertStringContainsString($exact, $after, 'A textarea keeps its text as it is written.');
    }

    public function test_a_reader_gets_the_dom_of_before_with_the_latest_version_the_draft_and_the_annotations(): void {
        $id = (int) $this->vaeiste->id;
        $situation = $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP100', 'title' => 'Accueil',
            'tags' => 'C01,C02', 'setting' => 'Unite', 'text' => 'Version un.']);
        (new situation_manager())->save_version((int) $situation->id, 'Version deux & "plus".');
        $pieces = new spiece_manager();
        $pieces->add_link((int) $situation->id, 'other', 'Un lien', 'https://example.org/a', '');
        $rows = [(object) [
            'id' => '9', 'state' => note_manager::ACTIVE, 'textformat' => (string) FORMAT_PLAIN, 'text' => 'Bien dit.',
            'authorid' => (string) $this->manager->id, 'authoruuid' => null, 'importedat' => null,
            'timecreated' => '1763700000', 'standing' => true,
        ]];
        $empty = $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP100', 'title' => 'Vide']);
        $blank = $this->generator->create_situation(['userid' => $id, 'fichecode' => 'RNCP100', 'title' => 'Blanc',
            'text' => 'Seule version.']);
        (new draft_manager())->save_working((int) $situation->draftid, 'Un brouillon en cours.');
        (new draft_manager())->save_working((int) $blank->draftid, '   ');
        $this->setUser($this->manager);
        $states = [
            'two versions, a draft, the annotations and their form' => [$situation, ['noterows' => $rows,
                'canannotate' => true, 'note' => true]],
            'the error of an annotation' => [$situation, ['noterows' => $rows,
                'noteerror' => 'Trop <b>long</b> & "vu".']],
            'no version' => [$empty, []],
            // The page reads what was sent for every reader; a reader who does not write is given nothing back.
            'a draft of blanks, hidden, a send' => [$blank, ['hiddenmessage' => 'Hidden &amp; said.',
                'keptsent' => ['content' => 'Sent by nobody here.']]],
            // The preparation closed is said to the vaeiste who writes, never to a reader (the pilot, a mutant).
            'closed, read by a reader' => [$situation, ['open' => false, 'noterows' => $rows]],
            // The form of an annotation is drawn for who may annotate only, whatever the page gives (the pilot, a mutant).
            'a form given to who may not annotate' => [$situation, ['noterows' => $rows, 'note' => true]],
        ];
        foreach ($states as $name => [$row, $more]) {
            [$old, $new, $before] = $this->both($this->values($row, $more));
            $this->assertSame($old, $new, $name);
            $this->assertStringNotContainsString('tool-vae-autosave', $before, $name . ': premise, no form.');
            $this->assertStringNotContainsString('<textarea readonly', $before, $name . ': a reader is given back nothing.');
        }
        [, , $before] = $this->both($this->values($situation, $states['two versions, a draft, the annotations and their form'][1]));
        $this->assertStringContainsString('Version deux &amp; &quot;plus&quot;.', $before, 'Premise: the latest version.');
        $this->assertStringNotContainsString('Version un.', $before, 'Premise: never the first of two.');
        $this->assertStringContainsString('Un brouillon en cours.', $before, 'Premise: the draft in progress.');
        $this->assertStringContainsString('_qf__tool_vae_form_note_form', $before, 'Premise: the form of an annotation.');
        $this->assertStringContainsString('withdrawnote', $before, 'Premise: the author may withdraw.');
        [, , $before] = $this->both($this->values($situation, ['open' => false, 'noterows' => $rows]));
        $closed = s(get_string('errorvalidationclosed', 'tool_vae'));
        $this->assertStringNotContainsString($closed, $before, 'Premise: closed is not said to a reader.');
        [, , $before] = $this->both($this->values($situation, ['noterows' => $rows, 'note' => true]));
        $this->assertStringNotContainsString('_qf__tool_vae_form_note_form', $before, 'Premise: no form without the right.');
        [, , $before] = $this->both($this->values($blank, []));
        $this->assertStringNotContainsString(
            s(get_string('situationworkingnotice', 'tool_vae')),
            $before,
            'Premise: a draft of blanks is no draft.'
        );
        [, , $before] = $this->both($this->values($empty, []));
        $this->assertStringContainsString(s(get_string('situationnoversion', 'tool_vae')), $before, 'Premise: no version.');
    }

    public function test_the_template_places_raw_only_the_keys_named_html(): void {
        global $CFG;

        $text = file_get_contents($CFG->dirroot . '/admin/tool/vae/templates/situation_page.mustache');
        preg_match_all('/\{\{\{(\w+)\}\}\}/', $text, $raw);
        $this->assertNotEmpty($raw[1]);
        foreach ($raw[1] as $key) {
            $this->assertStringEndsWith('html', $key);
        }
    }
}
