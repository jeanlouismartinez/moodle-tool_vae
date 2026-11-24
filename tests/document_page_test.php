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

use tool_vae\local\access;

/**
 * The page that builds an editable copy of a dossier (document.php) asks for the session key.
 *
 * The page builds a file and logs dossier_document_issued: a link followed from another site, or prefetched, must
 * do neither. The right is checked first, so that a person without it learns nothing more than before; then the
 * session key. The download itself (send_file, which ends the request) is proven over HTTP on the instance, not here.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(access::class)]
final class document_page_test extends \advanced_testcase {
    /**
     * @var \stdClass A vaeiste WITHOUT declaration: a page that skipped the session key would redirect (an exception in a
     * test, so a clean failure), where one with a declaration would send a file and end the whole test run.
     */
    private \stdClass $vaeiste;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->vaeiste = $this->getDataGenerator()->create_user();
    }

    /**
     * Play document.php with these parameters, and say what it threw, printed and logged.
     *
     * @param array $get
     * @return array [exception or null, output, events]
     */
    private function play(array $get): array {
        // The page is a script of the site: it reads these as globals.
        global $CFG, $DB, $OUTPUT, $PAGE, $SITE, $USER;

        $_GET = $get;
        $PAGE = new \moodle_page();
        $sink = $this->redirectEvents();
        $thrown = null;
        ob_start();
        try {
            require_once(__DIR__ . '/fixtures/plugin_pages.php');
            \tool_vae\fixtures\plugin_pages::run('document.php');
        } catch (\Throwable $e) {
            $thrown = $e;
        } finally {
            $out = ob_get_clean();
            $_GET = [];
        }
        $events = $sink->get_events();
        $sink->close();

        return [$thrown, $out, $events];
    }

    public function test_without_the_session_key_nothing_is_built_nor_logged(): void {
        $this->setAdminUser();

        foreach (['docx', 'odt'] as $format) {
            [$thrown, $out, $events] = $this->play(['userid' => $this->vaeiste->id, 'format' => $format]);

            // The core's confirm_sesskey() reads the key with required_param(): an absent key is a missing parameter.
            $this->assertInstanceOf(\moodle_exception::class, $thrown, $format);
            $this->assertSame('missingparam', $thrown->errorcode, $format);
            $this->assertSame('sesskey', $thrown->a, $format);
            $this->assertSame('', $out, 'No byte of a file is sent: ' . $format);
            $this->assertSame([], $events, 'No dossier_document_issued is logged: ' . $format);
        }
    }

    public function test_a_wrong_session_key_is_refused_too(): void {
        $this->setAdminUser();

        [$thrown, $out, $events] = $this->play([
            'userid' => $this->vaeiste->id, 'format' => 'docx', 'sesskey' => 'abcdefghij',
        ]);

        $this->assertSame('invalidsesskey', $thrown->errorcode);
        $this->assertSame('', $out);
        $this->assertSame([], $events);
    }

    public function test_the_right_is_checked_before_the_session_key(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        [$thrown, , $events] = $this->play(['userid' => $this->vaeiste->id, 'format' => 'docx']);

        $this->assertInstanceOf(
            \required_capability_exception::class,
            $thrown,
            'A person without the right is told so, whatever the session key.'
        );
        $this->assertSame([], $events);
    }
}
