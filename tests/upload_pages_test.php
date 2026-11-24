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

use tool_vae\fixtures\export_zip_builder;
use tool_vae\form\piece_file_form;
use tool_vae\form\spiece_file_form;
use tool_vae\local\draft_upload;
use tool_vae\local\fiche_messages;
use tool_vae\local\fiche_refusal;
use tool_vae\local\piece_manager;
use tool_vae\local\situation_screen;
use tool_vae\local\spiece_manager;
use tool_vae\local\validation_catalog;

/**
 * The three uploads of the plugin, played as the site plays them, with a REAL draft area of the person.
 *
 * A browser hands a file to the file picker of the core, which puts it in a draft area of the person (component user, area
 * draft, in their user context); the form then sends only the number of that area. These tests put the file where the upload
 * repository of the core puts it, send the form as the page receives it, and read what the plugin stored and what it left.
 *
 * WHAT IS ASSERTED, for each page (piece.php, situation.php, import_fiches.php): a file accepted is stored (under its
 * identifier, never its name: the pieces; read as an export: the import); a file of a refused type, no file, a file over the
 * limit store nothing; the draft area of the person is empty afterwards in every case where the page took the file.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(draft_upload::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(piece_file_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(spiece_file_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(situation_screen::class)]
final class upload_pages_test extends \advanced_testcase {
    /** @var string The smallest PDF piece_manager accepts. */
    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    /** @var \stdClass Declares and writes their preparation. */
    private $vaeiste;

    /** @var \stdClass The declaration of the vaeiste. */
    private $evidence;

    /** @var \tool_vae_generator */
    private $generator;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        validation_catalog::reset();
        set_config('validationopen', 1, 'tool_vae');
        set_config('piecesopen', 1, 'tool_vae');
        set_config('piecenoantivirus', 1, 'tool_vae');

        $datagenerator = $this->getDataGenerator();
        $this->generator = $datagenerator->get_plugin_generator('tool_vae');
        $this->generator->create_source(['fichecode' => 'RNCP100', 'ficherang' => 'BC01', 'shortname' => 'Bloc 1']);
        $this->generator->create_competency([
            'fichecode' => 'RNCP100', 'ficherang' => 'C01', 'shortname' => 'Competence C01', 'bloc' => 'BC01',
        ]);

        $system = \context_system::instance()->id;
        $this->vaeiste = $datagenerator->create_user(['firstname' => 'Camille', 'lastname' => 'Vaeiste']);
        $own = $datagenerator->create_role();
        foreach (['declare', 'viewown', 'positionself', 'draftown'] as $capability) {
            assign_capability('tool/vae:' . $capability, CAP_ALLOW, $own, $system, true);
        }
        set_role_contextlevels($own, [CONTEXT_USER]);
        role_assign($own, $this->vaeiste->id, \context_user::instance($this->vaeiste->id)->id);
        accesslib_clear_all_caches_for_unit_testing();

        $this->evidence = $this->generator->create_declaration([
            'userid' => $this->vaeiste->id, 'fichecode' => 'RNCP100', 'ficherang' => 'C01', 'situation' => 'Une declaration.',
        ]);
        $this->setUser($this->vaeiste);
    }

    /**
     * A file in a draft area of the current user, where the upload repository of the core puts it.
     *
     * @param string $name
     * @param string $bytes
     * @return int The draft item.
     */
    private function draft(string $name, string $bytes): int {
        global $USER;

        $itemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $itemid, 'filepath' => '/', 'filename' => $name,
        ], $bytes);

        return $itemid;
    }

    /**
     * The files left in a draft area of the current user, directories aside.
     *
     * @param int $itemid
     * @return int
     */
    private function left(int $itemid): int {
        global $USER;

        return count(get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $itemid,
            '',
            false
        ));
    }

    /**
     * A page of the plugin, played as the site plays it, for the current user.
     *
     * @param string $script
     * @param array $get
     * @param array $post
     * @return string|null The page, or null when it redirected.
     */
    private function page(string $script, array $get, array $post = []): ?string {
        // The page is a script of the site: it reads these as globals.
        global $CFG, $DB, $OUTPUT, $PAGE, $SITE, $USER;

        $_GET = $get;
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
        $PAGE = new \moodle_page();
        ob_start();
        try {
            require_once(__DIR__ . '/fixtures/plugin_pages.php');
            \tool_vae\fixtures\plugin_pages::run($script);
        } catch (\moodle_exception $e) {
            if ($e->errorcode !== 'redirecterrordetected') {
                throw $e;
            }
            return null;
        } finally {
            $out = ob_get_clean();
            $_GET = [];
            $_POST = [];
            $_SERVER['REQUEST_METHOD'] = 'GET';
        }

        return $out;
    }

    /**
     * Send the form of a file of piece.php with this draft item.
     *
     * @param int $itemid
     * @return string|null
     */
    private function send_piece(int $itemid): ?string {
        return $this->page('piece.php', ['evidenceid' => (int) $this->evidence->id], [
            'evidenceid' => (int) $this->evidence->id, 'filenature' => 'contract', 'filetitle' => 'Mon contrat',
            'piecefile' => $itemid, 'submitbutton' => 'x', 'sesskey' => sesskey(), '_qf__tool_vae_form_piece_file_form' => 1,
        ]);
    }

    public function test_piece_page_stores_the_file_of_the_draft_area_under_its_identifier_and_empties_the_area(): void {
        global $DB;

        $itemid = $this->draft('Projet_Mme_X.pdf', self::PDF);

        $this->assertNull($this->send_piece($itemid), 'Stored, and sent back to the page.');

        $piece = $DB->get_record('tool_vae_piece', ['evidenceid' => (int) $this->evidence->id], '*', MUST_EXIST);
        $this->assertSame('contract', $piece->nature);
        $this->assertSame('Mon contrat', $piece->title);
        $this->assertSame(hash('sha256', self::PDF), $piece->sha256);
        $file = get_file_storage()->get_file(
            \context_user::instance($this->vaeiste->id)->id,
            'tool_vae',
            piece_manager::FILEAREA,
            (int) $piece->id,
            '/',
            $piece->uuid . '.pdf'
        );
        $this->assertNotFalse($file);
        $this->assertSame(self::PDF, $file->get_content());
        $this->assertSame(0, $this->left($itemid), 'The draft area is emptied once read.');
        $this->assertSame(0, $DB->count_records('files', ['filename' => 'Projet_Mme_X.pdf']), 'The name is nowhere.');
    }

    public function test_piece_page_stores_nothing_for_a_refused_type_no_file_or_a_file_over_the_limit(): void {
        global $DB;

        $html = $this->draft('page.pdf', '<html><body>Pas un PDF</body></html>');
        $this->assertNull($this->send_piece($html));
        $this->assertSame(0, $this->left($html), 'Taken, refused, and taken away.');

        $none = file_get_unused_draft_itemid();
        $this->assertNull($this->send_piece($none));

        set_config('piecemaxbytes', 100, 'tool_vae');
        $big = $this->draft('gros.pdf', self::PDF . str_repeat('x', 200));
        $this->assertNull($this->send_piece($big));
        $this->assertSame(0, $this->left($big));

        $this->assertSame(0, $DB->count_records('tool_vae_piece'), 'Nothing stored.');
    }

    public function test_piece_page_gives_the_form_back_for_an_extension_not_accepted(): void {
        global $DB;

        $itemid = $this->draft('notes.txt', 'Des notes.');

        $html = $this->send_piece($itemid);

        $this->assertNotNull($html, 'The form comes back, with the error of the element.');
        $this->assertStringContainsString('notes.txt', $html);
        $this->assertSame(0, $DB->count_records('tool_vae_piece'));
    }

    public function test_situation_page_stores_the_file_of_the_draft_area_and_empties_the_area(): void {
        global $DB;

        $situation = $this->generator->create_situation([
            'userid' => $this->vaeiste->id, 'fichecode' => 'RNCP100', 'title' => 'Accueil du matin', 'tags' => 'C01',
        ]);
        $at = ['userid' => (int) $this->vaeiste->id, 'fiche' => 'RNCP100', 'slot' => (int) $situation->slot];
        $send = fn(int $itemid) => $this->page('situation.php', $at, [
            'filenature' => 'other', 'filetitle' => 'Fiche de tournee', 'filepart' => 'La premiere page', 'piecefile' => $itemid,
            'submitbutton' => 'x', 'sesskey' => sesskey(), '_qf__tool_vae_form_spiece_file_form' => 1,
        ]);

        $html = $this->page('situation.php', $at);
        $this->assertStringContainsString('_qf__tool_vae_form_spiece_file_form', $html, 'The file: a form of the core.');
        $this->assertMatchesRegularExpression('~name="piecefile"[^>]*class="filepickerhidden"~', $html);

        $none = file_get_unused_draft_itemid();
        $this->assertNull($send($none));
        $this->assertSame(0, $DB->count_records(spiece_manager::TABLE), 'No file: nothing.');

        $itemid = $this->draft('Projet_Mme_X.pdf', self::PDF);
        $this->assertNull($send($itemid));

        $piece = $DB->get_record(spiece_manager::TABLE, [], '*', MUST_EXIST);
        $this->assertSame('Fiche de tournee', $piece->title);
        $this->assertSame(hash('sha256', self::PDF), $piece->sha256);
        $link = $DB->get_record('tool_vae_slink', ['pieceid' => (int) $piece->id], '*', MUST_EXIST);
        $this->assertSame('La premiere page', $link->part);
        $this->assertSame((int) $situation->id, (int) $link->situationid);
        $this->assertSame(0, $this->left($itemid));
        $this->assertSame(0, $DB->count_records('files', ['filename' => 'Projet_Mme_X.pdf']));

        set_config('piecemaxbytes', 100, 'tool_vae');
        $big = $this->draft('gros.pdf', self::PDF . str_repeat('x', 200));
        $this->assertNull($send($big));
        $this->assertSame(1, $DB->count_records(spiece_manager::TABLE), 'Over the limit: nothing more.');
        $this->assertSame(0, $this->left($big));
    }

    public function test_import_page_reads_the_export_of_the_draft_area_and_empties_the_area(): void {
        global $USER;
        require_once(__DIR__ . '/fixtures/export_zip_builder.php');

        $this->setAdminUser();
        $plans = fn() => count(glob(make_temp_directory('tool_vae/fiche_import') . '/' . (int) $USER->id . '-*') ?: []);
        $send = fn(int $itemid) => $this->page('import_fiches.php', [], [
            'numbers' => 'RNCP99001', 'exportzip' => $itemid, 'zipbutton' => 'x', 'sesskey' => sesskey(),
            '_qf__tool_vae_form_fiche_import_form' => 1,
        ]);

        $html = $this->page('import_fiches.php', []);
        $this->assertMatchesRegularExpression('~name="exportzip"[^>]*class="filepickerhidden"~', $html);

        $none = $send(file_get_unused_draft_itemid());
        $this->assertNotNull($none);
        $this->assertStringContainsString(fiche_messages::html(new fiche_refusal(fiche_refusal::ZIP_MISSING)), $none);
        $this->assertSame(0, $plans());

        $notzip = $this->draft('export-fiches-csv-2026-10-19.zip', 'Pas une archive.');
        $html = $send($notzip);
        $this->assertNotNull($html);
        $this->assertStringContainsString(fiche_messages::html(new fiche_refusal(fiche_refusal::NOT_ZIP)), $html);
        $this->assertSame(0, $this->left($notzip));

        $directory = make_request_directory();
        $zip = export_zip_builder::build($directory, 'export-fiches-csv-2026-10-19.zip');
        $itemid = $this->draft('export-fiches-csv-2026-10-19.zip', file_get_contents($zip));
        $this->assertNull($send($itemid), 'Read, and sent to the preview.');
        $this->assertSame(1, $plans());
        $this->assertSame(0, $this->left($itemid));
    }
}
