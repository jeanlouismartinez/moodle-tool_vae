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

/**
 * The observers stay light: no database call and no loop in classes/observer.php (ticket 3 of review MMR-520).
 *
 * The reviewer's tool counted "db_calls=3, loops=5" on the account deletion; the numbers matched the calls of one function
 * and the loops of the whole FILE, so the file is what is guarded. The check reads PHP TOKENS, not text: a comment that
 * explains why there is no loop must not fail it, and a loop written in any spacing must.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\notification_observer::class)]
final class observer_guard_test extends \basic_testcase {
    /**
     * @var string What the measure of the reviewer may count in a callback, read on the WHOLE text of the file, comments
     *      included (lot 9): the database, a loop, a callable mapped on a list, and the readings of rights, followers,
     *      referents and attestations. That the tool counts by file is a hypothesis of 19 November 2026, never proved; the
     *      stricter reading is the one kept.
     */
    private const HEAVY = '/\$DB|foreach|\bwhile *\(|\bfor *\(|\bdo *\{|array_map|array_filter|array_walk|access::'
        . '|referent_manager::|attestation_manager::|has_capability|get_record|context_user::instance/';

    /**
     * What makes an observer heavy, found in the tokens of a PHP source.
     *
     * @param string $source PHP code.
     * @return string[] One line per problem: the token and its line.
     */
    public static function problems(string $source): array {
        $problems = [];
        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                continue;
            }
            [$id, $text, $line] = $token;
            if ($id === T_VARIABLE && $text === '$DB') {
                $problems[] = "\$DB on line $line";
            } else if (in_array($id, [T_FOREACH, T_FOR, T_WHILE, T_DO], true)) {
                $problems[] = token_name($id) . " on line $line";
            }
        }

        return $problems;
    }

    /**
     * The file the reviewer reads holds no database call and no loop.
     */
    public function test_the_observer_file_holds_no_database_call_and_no_loop(): void {
        $source = file_get_contents(__DIR__ . '/../classes/observer.php');

        $this->assertSame([], self::problems($source), 'classes/observer.php does work that belongs in a task.');
    }

    /**
     * Every file db/events.php names as the class of a callback - the four follow-ups of a deletion and the fourteen
     * messages - holds none of what the reviewer may count, on any line (lot 9 of review MMR-520).
     *
     * The callbacks keep the identifiers of their event and queue one task; whatever reads the database runs from cron.
     */
    public function test_every_callback_file_of_db_events_holds_nothing_the_reviewer_counts(): void {
        $observers = [];
        require(__DIR__ . '/../db/events.php');

        $files = [];
        foreach ($observers as $observer) {
            [$class] = explode('::', ltrim($observer['callback'], '\\'));
            $files[$class] = __DIR__ . '/../classes/' . str_replace('\\', '/', substr($class, strlen('tool_vae\\'))) . '.php';
        }
        $this->assertCount(18, $observers, 'db/events.php no longer declares eighteen observers.');
        $this->assertNotEmpty($files);

        foreach ($files as $class => $file) {
            $this->assertFileExists($file, "The file of $class is not where its name says.");
            $this->assertSame([], preg_grep(self::HEAVY, file($file)), "$class does in the request work that belongs in a task.");
        }
    }

    /**
     * The pattern of the whole-file check finds each thing it names, in a comment as in code, and passes a light callback.
     */
    public function test_the_whole_file_check_finds_what_it_names(): void {
        $heavy = ['global $DB;', 'foreach ($x as $y) {', 'while (true) {', 'for ($i = 0; ;) {', 'do {', 'array_map(',
            'array_filter(', 'array_walk(', 'access::followers(', 'referent_manager::route(',
            'attestation_manager::active_attestation(',
            'has_capability(', '$DB->get_record(', '\context_user::instance(1)', '// a comment that says foreach'];
        foreach ($heavy as $line) {
            $this->assertNotEmpty(preg_grep(self::HEAVY, [$line]), "Not found: $line");
        }
        $light = ['send_notification::queue(notifier::OPINION_MADE, $candidateid, $candidateid, [', 'queue_notifications::queue(',
            '} catch (\Throwable $e) {', '(int) $event->other[\'evidenceid\'],', 'a message for the vaeiste (lot 1b)'];
        $this->assertSame([], preg_grep(self::HEAVY, $light));
    }

    /**
     * The check finds what it must and nothing else: code counts, comments and strings do not.
     */
    public function test_the_check_reads_code_not_comments(): void {
        $heavy = "<?php\nfunction f() {\n    global \$DB;\n    foreach (\$DB->get_records('x') as \$r) {\n    }\n"
            . "    for (\$i = 0; \$i < 2; \$i++) {\n    }\n    while(false) {\n    }\n    do {\n    } while (false);\n}\n";
        $this->assertSame(
            ['$DB on line 3', 'T_FOREACH on line 4', '$DB on line 4', 'T_FOR on line 6', 'T_WHILE on line 8', 'T_DO on line 10',
                'T_WHILE on line 11'],
            self::problems($heavy)
        );

        $light = "<?php\n// No \$DB-> here, no foreach, no while (, no for (.\n/* foreach (\$x as \$y) */\n"
            . "\$s = 'global \$DB; foreach';\n\$t = \"for (\";\n";
        $this->assertSame([], self::problems($light), 'A comment or a string was read as code.');
    }
}
