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

use tool_vae\form\piece_file_form;
use tool_vae\local\draft_upload;

/**
 * A file given through the file picker of the core: read from the draft area of the person, copied out, then taken away.
 *
 * WHAT IS ASSERTED. That the filepicker element works on this core under PHP 8.3 (rendered, sent, its draft item given back),
 * where the file element of the form library was a fatal error; what the core itself checks on the server, and what it does
 * not (a file over the limit of the element is taken out without a word when the form gives its data, an extension not
 * accepted is refused by the element); that the plugin reads one file of the area of the CURRENT user only, refuses in the
 * words its pages always used (no file, too large, unreadable), and leaves nothing behind (draft area and copy); what the
 * cleaning of the name by the core makes of ten hostile names, and that no name reaches the store of the plugin.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(draft_upload::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(piece_file_form::class)]
final class draft_upload_test extends \advanced_testcase {
    /** @var \stdClass The person who uploads. */
    private $vaeiste;

    #[\Override]
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest();
        $this->vaeiste = $this->getDataGenerator()->create_user();
        $this->setUser($this->vaeiste);
        $PAGE->set_url('/admin/tool/vae/piece.php', ['evidenceid' => 1]);
        $PAGE->set_context(\context_user::instance($this->vaeiste->id));
    }

    /**
     * A file in a draft area of the current user, as the upload repository of the core leaves it.
     *
     * @param string $name
     * @param string $bytes
     * @param int $itemid 0 for a new area.
     * @return int The draft item.
     */
    private function draft(string $name, string $bytes, int $itemid = 0): int {
        global $USER;

        $itemid = $itemid ?: file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $itemid, 'filepath' => '/', 'filename' => $name,
        ], $bytes);

        return $itemid;
    }

    /**
     * The files left in a draft area of a person, directories aside.
     *
     * @param int $userid
     * @param int $itemid
     * @return int
     */
    private function left(int $userid, int $itemid): int {
        return count(get_file_storage()->get_area_files(\context_user::instance($userid)->id, 'user', 'draft', $itemid, '', false));
    }

    /**
     * A declaration of a vaeiste, written as piece_manager_test writes it.
     *
     * @param int $candidateid
     * @return int Its id.
     */
    public static function declaration(int $candidateid): int {
        global $DB;

        $now = time();

        return (int) $DB->insert_record('tool_vae_evidence', (object) [
            'uuid' => local\identity_map::generate_ulid(), 'userevidenceid' => 1, 'candidateid' => $candidateid,
            'periodstart' => $now - 1000, 'periodend' => $now, 'settingtype' => 'ehpad', 'situationformat' => FORMAT_PLAIN,
            'situation' => 'Une situation.', 'fichecode' => 'RNCP36832', 'ficherang' => 'C03', 'ficheupdated' => $now,
            'timecreated' => $now, 'timemodified' => $now, 'usermodified' => $candidateid,
        ]);
    }

    /**
     * Refused, with which code.
     *
     * @param callable $call
     * @return \moodle_exception
     */
    private function refused(callable $call): \moodle_exception {
        try {
            $call();
        } catch (\moodle_exception $e) {
            return $e;
        }
        $this->fail('The call must be refused.');
    }

    /**
     * The form of a piece, sent with this draft item.
     *
     * @param int $itemid
     * @param int $maxbytes
     * @return piece_file_form
     */
    private function sent_form(int $itemid, int $maxbytes): piece_file_form {
        piece_file_form::mock_submit(['evidenceid' => 1, 'filenature' => 'other', 'filetitle' => '', 'piecefile' => $itemid]);

        return new piece_file_form(null, ['evidenceid' => 1, 'maxbytes' => $maxbytes]);
    }

    public function test_the_file_picker_works_on_this_core_and_gives_back_its_draft_item(): void {
        $this->assertTrue(version_compare(PHP_VERSION, '8.3.0', '>='), 'Played on the PHP of the bench: ' . PHP_VERSION);

        $form = new piece_file_form(null, ['evidenceid' => 1, 'maxbytes' => 1048576]);
        ob_start();
        $form->display();
        $html = ob_get_clean();
        $this->assertMatchesRegularExpression('~<input type="hidden" name="piecefile" id="id_piecefile" value="\d+"'
            . ' class="filepickerhidden"/>~', $html, 'The filepicker element, not a plain field.');
        $this->assertStringNotContainsString('type="file"', $html);
        $this->assertStringContainsString('data-fieldtype="filepicker"', $html);

        $itemid = $this->draft('releve.pdf', '%PDF-1.4 x');
        $data = $this->sent_form($itemid, 1048576)->get_data();
        $this->assertNotNull($data, 'Sent and valid.');
        $this->assertSame($itemid, (int) $data->piecefile);
        $this->assertSame(1, $this->left((int) $this->vaeiste->id, $itemid), 'The core leaves the file in the area.');
    }

    public function test_the_core_takes_out_a_file_over_the_limit_of_the_element_when_the_form_gives_its_data(): void {
        $itemid = $this->draft('gros.pdf', str_repeat('x', 2000));
        $form = $this->sent_form($itemid, 1000);

        // Measured before the data is read: this is why the page measures first (draft_upload::larger_than()).
        $this->assertTrue(draft_upload::larger_than($itemid, 1000));
        $this->assertFalse(draft_upload::larger_than($itemid, 2000), 'Exactly the limit is within it.');
        $this->assertNotNull($form->get_data());
        $this->assertSame(0, $this->left((int) $this->vaeiste->id, $itemid), 'MoodleQuickForm_filepicker::exportValue().');
    }

    public function test_the_element_refuses_an_extension_that_is_not_accepted(): void {
        $itemid = $this->draft('notes.txt', 'Des notes.');
        $form = $this->sent_form($itemid, 1048576);

        $this->assertNull($form->get_data(), 'MoodleQuickForm_filepicker::validateSubmitValue().');
        $errors = (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form)->_errors;
        $this->assertStringContainsString('notes.txt', (string) ($errors['piecefile'] ?? ''));
    }

    public function test_a_piece_is_the_one_file_of_the_area_copied_out(): void {
        $itemid = $this->draft('Projet_Mme_X.pdf', '%PDF-1.4 contenu');

        $copy = draft_upload::piece($itemid, 1048576);

        $this->assertSame('%PDF-1.4 contenu', file_get_contents($copy->path));
        $this->assertSame('Projet_Mme_X.pdf', $copy->name, 'Given to the caller, who never keeps it.');
        $this->assertSame(16, $copy->size);
        $this->assertStringStartsWith(get_request_storage_directory() . '/', $copy->path, 'In the directory of the request.');
        $this->assertSame(1, $this->left((int) $this->vaeiste->id, $itemid), 'Reading takes nothing.');
    }

    public function test_a_piece_is_refused_in_the_words_of_the_pages(): void {
        $this->assertSame('errorpiecenofile', $this->refused(fn() => draft_upload::piece(0, 1000))->errorcode);
        $empty = file_get_unused_draft_itemid();
        $this->assertSame('errorpiecenofile', $this->refused(fn() => draft_upload::piece($empty, 1000))->errorcode);

        $big = $this->draft('gros.pdf', str_repeat('x', 1001));
        $refusal = $this->refused(fn() => draft_upload::piece($big, 1000));
        $this->assertSame('errorpiecetoolarge', $refusal->errorcode);
        $this->assertStringContainsString(display_size(1000), $refusal->getMessage());

        $broken = $this->createMock(\stored_file::class);
        $broken->method('copy_content_to')->willReturn(false);
        $broken->method('get_filename')->willReturn('a.pdf');
        $broken->method('get_filesize')->willReturn(10);
        $this->assertSame('errorpiecefile', $this->refused(fn() => draft_upload::copy($broken))->errorcode);
    }

    public function test_the_area_of_another_person_is_never_read(): void {
        $itemid = $this->draft('a.pdf', '%PDF-1.4 x');
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($other);

        $this->assertNull(draft_upload::file($itemid));
        $this->assertSame('errorpiecenofile', $this->refused(fn() => draft_upload::piece($itemid, 1000))->errorcode);
        draft_upload::discard($itemid);
        $this->assertSame(1, $this->left((int) $this->vaeiste->id, $itemid), 'Nor emptied.');
    }

    public function test_the_newest_file_of_the_area_is_the_one_read(): void {
        $itemid = $this->draft('premier.pdf', 'un');
        $this->waitForSecond();
        $this->draft('second.pdf', 'deux', $itemid);

        $this->assertSame('second.pdf', draft_upload::file($itemid)->get_filename());
    }

    public function test_discarding_empties_the_area_and_removes_the_copy(): void {
        $itemid = $this->draft('a.pdf', '%PDF-1.4 x');
        $copy = draft_upload::piece($itemid, 1000);

        draft_upload::discard($itemid, $copy);

        $this->assertSame(0, $this->left((int) $this->vaeiste->id, $itemid));
        $this->assertFileDoesNotExist($copy->path);
        draft_upload::discard($itemid, $copy);
        draft_upload::discard(0);
    }

    /**
     * Ten hostile names, and what the cleaning of the core (PARAM_FILE, repository_upload::process_upload()) makes of them.
     *
     * @return array Name => [the name sent, the name the draft area holds, or null when the core refuses it, and - when it
     *         differs from the name held - the name the import shows].
     */
    public static function hostile_names(): array {
        $long = str_repeat('a', 296) . '.pdf';

        return [
            'parent directories' => ['../../etc/passwd', '....etcpasswd'],
            'a slash' => ['a/b.pdf', 'ab.pdf'],
            'a backslash' => ['a\\b.pdf', 'ab.pdf'],
            'a null byte' => ["a\0.php.pdf", 'a.php.pdf'],
            'three hundred characters' => [$long, null],
            'a final dot (Windows)' => ['piece.pdf.', 'piece.pdf.'],
            'a reserved name of Windows' => ['CON.pdf', 'CON.pdf'],
            // U+202E reverses the direction of what follows: the core keeps it (a format character, not a control one), the
            // import takes it off the name it shows.
            'control characters of Unicode' => ["fiche\u{202E}fdp.exe", "fiche\u{202E}fdp.exe", 'fichefdp.exe'],
            'a dot file' => ['.htaccess', '.htaccess'],
            'a double extension' => ['x.php.pdf', 'x.php.pdf'],
        ];
    }

    /**
     * The name through the real upload of the core, then through the plugin: what the area holds, and what is kept.
     *
     * @param string $sent
     * @param string|null $held
     * @param string|null $shown The name the import shows, when it is not the name held.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('hostile_names')]
    public function test_a_hostile_name_is_cleaned_by_the_core_and_never_kept(
        string $sent,
        ?string $held,
        ?string $shown = null
    ): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/repository/lib.php');
        require_once($CFG->dirroot . '/repository/upload/lib.php');

        $this->assertSame($held ?? str_repeat('a', 296) . '.pdf', clean_param($sent, PARAM_FILE), 'The cleaning of the core.');

        // The upload repository is there on a new site; created only if it is not.
        $repositories = \repository::get_instances(['type' => 'upload']);
        if (!$repositories) {
            $this->getDataGenerator()->create_repository_type('upload');
            $repositories = \repository::get_instances(['type' => 'upload']);
        }
        $repository = reset($repositories);
        $itemid = file_get_unused_draft_itemid();
        $path = make_request_directory() . '/upload';
        file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
        $_FILES = ['repo_upload_file' => [
            'name' => $sent, 'type' => 'application/pdf', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path),
        ]];
        try {
            $repository->process_upload('', 1048576, '*', '/', $itemid);
            $this->assertNotNull($held, 'The core keeps a name it should refuse.');
        } catch (\Throwable $e) {
            $this->assertNull($held, 'The core refuses ' . $sent . ': ' . $e->getMessage());
            $this->assertNull(draft_upload::file($itemid));

            return;
        } finally {
            $_FILES = [];
        }

        // The import shows the name it reads the day from, escaped, in its refusal and in its preview (fiche_screen::source()).
        // A character of format (the override of the writing direction, U+202E) is not a control character for the core and
        // would make the name read otherwise than it is written: the import shows the name without it.
        $shown = $shown ?? $held;
        $upload = local\fiche_import::upload_path(draft_upload::file($itemid));
        $this->assertSame($shown, $upload->name);
        $said = local\fiche_messages::html(new local\fiche_refusal(local\fiche_refusal::NO_DATE, ['name' => $upload->name]));
        $this->assertStringContainsString('"' . s(local\fiche_messages::shorten($shown)) . '"', $said);
        $this->assertDoesNotMatchRegularExpression('/\p{Cf}/u', $said, 'A character of format reached the screen.');
        draft_upload::discard(0, $upload);

        $copy = draft_upload::piece($itemid, 1048576);
        $this->assertSame($held, $copy->name, 'The name the area holds.');
        $this->assertSame(1, preg_match('~/[^/]+$~', $copy->path), 'The copy is under a name of its own.');
        $this->assertStringNotContainsString($held, basename($copy->path));

        set_config('piecesopen', 1, 'tool_vae');
        set_config('piecenoantivirus', 1, 'tool_vae');
        $role = $this->getDataGenerator()->create_role();
        assign_capability('tool/vae:declare', CAP_ALLOW, $role, \context_system::instance()->id, true);
        assign_capability('tool/vae:viewown', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $this->vaeiste->id, \context_user::instance($this->vaeiste->id)->id);
        accesslib_clear_all_caches_for_unit_testing();
        $evidenceid = self::declaration((int) $this->vaeiste->id);
        try {
            $row = (new local\piece_manager())->add_file($evidenceid, 'other', '', $copy->path, $copy->name);
        } finally {
            draft_upload::discard($itemid, $copy);
        }

        $stored = $DB->get_records('files', ['component' => 'tool_vae', 'filearea' => 'piece', 'itemid' => $row->id]);
        $names = array_values(array_filter(array_column($stored, 'filename'), fn($name) => $name !== '.'));
        $this->assertSame([$row->uuid . '.pdf'], $names, 'Stored under its identifier and its detected type.');
        $this->assertSame(0, $DB->count_records('files', ['filename' => $held]), 'The name is nowhere in the files any more.');
        $this->assertSame(0, $DB->count_records_select('files', $DB->sql_like('source', ':s'), ['s' => '%' . $DB->sql_like_escape(
            $held
        ) . '%']), 'Nor in the source the core wrote.');
        $this->assertStringNotContainsString($held, json_encode($DB->get_record('tool_vae_piece', ['id' => $row->id])));
    }
}
