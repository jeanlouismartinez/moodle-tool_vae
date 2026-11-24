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
 * No superglobal is read outside the tests (security rule of the Marketplace, review MMR-520).
 *
 * The rule of the Marketplace: never access the superglobals of PHP directly, read the request through required_param(),
 * optional_param() and the forms of the core. Two checks: the TOKENS of every PHP file of the plugin outside tests/ (a read in
 * code, in any spacing), and the TEXT of the same files (the gate G6 of the review counts a name in a comment too, and a reader
 * of the review does as well). The tests may set the request: they simulate what a browser sends.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\local\hidden_frameworks::class)]
final class superglobal_guard_test extends \basic_testcase {
    /** @var string[] The superglobals of PHP that carry the request or the environment. */
    private const NAMES = ['$_GET', '$_POST', '$_REQUEST', '$_FILES', '$_SERVER', '$_COOKIE', '$_ENV'];

    /** @var string The pattern of the gate G6, as the review writes it. */
    private const TEXT_PATTERN = '~\$_(GET|POST|REQUEST|FILES|SERVER|COOKIE|ENV)\b~';

    /**
     * The PHP files of the plugin outside tests/, by their path from the root of the plugin.
     *
     * @return string[] Path from the root => full path.
     */
    private static function sources(): array {
        $root = realpath(__DIR__ . '/..');
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if ($file->getExtension() !== 'php' || preg_match('~^(tests|node_modules|vendor)/~', $relative)) {
                continue;
            }
            $files[$relative] = $file->getPathname();
        }
        ksort($files);

        return $files;
    }

    /**
     * Where a PHP source reads a superglobal, from its tokens.
     *
     * @param string $source PHP code.
     * @return string[] One line per read: the name and its line.
     */
    public static function reads(string $source): array {
        $reads = [];
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_VARIABLE && in_array($token[1], self::NAMES, true)) {
                $reads[] = $token[1] . ' on line ' . $token[2];
            }
        }

        return $reads;
    }

    /**
     * No file of the plugin outside the tests reads a superglobal in its code.
     */
    public function test_no_source_reads_a_superglobal(): void {
        $found = [];
        foreach (self::sources() as $relative => $path) {
            foreach (self::reads((string) file_get_contents($path)) as $read) {
                $found[] = "$relative: $read";
            }
        }

        $this->assertSame([], $found, 'Read the request through the core (optional_param(), the forms, the file picker).');
    }

    /**
     * No file of the plugin outside the tests even names one, in a comment or a string: the gate G6 reads text.
     */
    public function test_no_source_names_a_superglobal(): void {
        $found = [];
        foreach (self::sources() as $relative => $path) {
            foreach (explode("\n", (string) file_get_contents($path)) as $number => $line) {
                if (preg_match(self::TEXT_PATTERN, $line)) {
                    $found[] = "$relative:" . ($number + 1);
                }
            }
        }

        $this->assertSame([], $found, 'Say what is read without the name of the superglobal.');
    }

    /**
     * The check finds what it must: a read in code, in any spacing; and nothing in a comment for the token check.
     */
    public function test_the_check_reads_code(): void {
        $code = "<?php\n\$a = \$_POST['x'];\n\$b = \$_FILES ['y'];\nif (\$_SERVER) {\n}\n";
        $this->assertSame(['$_POST on line 2', '$_FILES on line 3', '$_SERVER on line 4'], self::reads($code));

        $comment = "<?php\n// The page never reads \$_FILES.\n/* \$_GET */\n\$s = 'x';\n";
        $this->assertSame([], self::reads($comment), 'A comment is not a read.');
        $this->assertSame(1, preg_match(self::TEXT_PATTERN, $comment), 'But the gate G6 counts it, and so does the text check.');
    }
}
