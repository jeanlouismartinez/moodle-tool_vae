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

use tool_vae\fixtures\lang_files;

/**
 * The language files of the plugin: the strings of the capabilities and of the message providers, the same keys in every
 * language, the key every plugin needs, and the order of the keys.
 *
 * THE KEY OF A CAPABILITY STRING IS NOT THE NAME OF THE CAPABILITY. For 'tool/vae:declare', Moodle looks for 'vae:declare'
 * in the component tool_vae: get_capability_string() splits the name on [/:] and builds the key again. A string written
 * $string['tool/vae:declare'] shows as "???" on the screen that defines the roles, and no other tool complains.
 *
 * A STRING PRESENT IN ENGLISH AND MISSING IN ANOTHER LANGUAGE goes unnoticed until a user of that language sees English;
 * the string manager fills the gap from English, so the files are read as they are written (lang_files).
 *
 * THE ORDER IS THE ORDER OF strcmp(). moodle-plugin-ci validate requires it; this test says it in seconds, not in the
 * hour and a half of the continuous integration.
 *
 * A GUARD THAT FINDS NOTHING PROVES NOTHING: every check is a function that is also given strings that must fail it.
 * This test replaces scripts/verifier-langue.php, a command line script that ran outside Moodle (19 November 2026).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class lang_strings_test extends \advanced_testcase {
    /** @var string The language Moodle falls back to, and the reference of the parity. */
    private const REFERENCE = 'en';

    /**
     * @var string[] The keys that exist in English only, each with its reason. A translation of one of them would never be
     *      read: it is a dead string, and the parity refuses it as it refuses a missing one.
     */
    public const ENGLISH_ONLY = [
        // The instruction sent to the AI provider: read from the English file whatever the language of the screen
        // (ai_assistant::instruction()), so that the approved wording is the same for every user. It asks for French.
        'aiprompt' => 'the instruction sent to the AI provider, one approved wording whatever the language of the screen',
    ];

    /**
     * Loads the reader of the language files.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        require_once(__DIR__ . '/fixtures/lang_files.php');
    }

    /**
     * The capabilities of db/access.php, read as Moodle reads them.
     *
     * @return array Name => definition.
     */
    private static function capabilities(): array {
        $capabilities = [];
        require(__DIR__ . '/../db/access.php');

        return $capabilities;
    }

    /**
     * The message providers of db/messages.php, read as Moodle reads them.
     *
     * @return string[] Provider names.
     */
    private static function providers(): array {
        $messageproviders = [];
        require(__DIR__ . '/../db/messages.php');

        return array_keys($messageproviders);
    }

    /**
     * The strings of every language of the plugin.
     *
     * @return array Language => (key => value).
     */
    private static function all_strings(): array {
        $all = [];
        foreach (lang_files::languages() as $lang) {
            $all[$lang] = lang_files::strings($lang);
        }

        return $all;
    }

    /**
     * What is wrong with the strings of the capabilities.
     *
     * @param string[] $capabilities Capability names.
     * @param array $all Language => (key => value).
     * @return string[] One line per problem.
     */
    private static function capability_problems(array $capabilities, array $all): array {
        $problems = [];
        foreach ($capabilities as $capability) {
            $parts = preg_split('|[/:]|', $capability);
            if (count($parts) !== 3) {
                $problems[] = "malformed capability: $capability (expected type/name:action)";
                continue;
            }
            [, $name, $action] = $parts;
            $key = "$name:$action";
            foreach ($all as $lang => $strings) {
                if (!isset($strings[$key])) {
                    $problems[] = "lang/$lang: missing string '$key' for $capability";
                }
                if (isset($strings[$capability])) {
                    $problems[] = "lang/$lang: wrong key '$capability', expected '$key'";
                }
            }
        }

        return $problems;
    }

    /**
     * What is wrong with the strings of the message providers.
     *
     * @param string[] $providers Provider names.
     * @param array $all Language => (key => value).
     * @return string[] One line per problem.
     */
    private static function provider_problems(array $providers, array $all): array {
        $problems = [];
        foreach ($providers as $provider) {
            $key = "messageprovider:$provider";
            foreach ($all as $lang => $strings) {
                if (!isset($strings[$key]) || trim((string) $strings[$key]) === '') {
                    $problems[] = "lang/$lang: missing string '$key' for the message provider $provider";
                }
            }
        }

        return $problems;
    }

    /**
     * What one language has that the reference has not, and the reverse; the English-only keys apart.
     *
     * @param array $all Language => (key => value).
     * @param string[] $englishonly The keys that exist in the reference only.
     * @return string[] One line per problem.
     */
    private static function parity_problems(array $all, array $englishonly = []): array {
        if (!isset($all[self::REFERENCE])) {
            return ['lang/' . self::REFERENCE . ' is missing: it is the reference language of Moodle'];
        }
        $problems = [];
        foreach (array_diff($englishonly, array_keys($all[self::REFERENCE])) as $unknown) {
            $problems[] = "English-only string '$unknown' absent from " . self::REFERENCE;
        }
        $expected = array_diff(array_keys($all[self::REFERENCE]), $englishonly);
        foreach ($all as $lang => $strings) {
            if ($lang === self::REFERENCE) {
                continue;
            }
            foreach (array_diff($expected, array_keys($strings)) as $missing) {
                $problems[] = "lang/$lang: missing string '$missing' (present in " . self::REFERENCE . ')';
            }
            foreach (array_intersect(array_keys($strings), $englishonly) as $dead) {
                $problems[] = "lang/$lang: string '$dead' is English-only: a translation would never be read";
            }
            foreach (array_diff(array_keys($strings), array_keys($all[self::REFERENCE])) as $orphan) {
                $problems[] = "lang/$lang: string '$orphan' absent from " . self::REFERENCE;
            }
        }

        return $problems;
    }

    /**
     * Where the keys of a file do not follow the strict order of strcmp().
     *
     * @param array $all Language => (key => value), in the order of the file.
     * @return string[] One line per problem.
     */
    private static function order_problems(array $all): array {
        $problems = [];
        foreach ($all as $lang => $strings) {
            $previous = null;
            foreach (array_keys($strings) as $key) {
                if ($previous !== null && strcmp((string) $previous, (string) $key) >= 0) {
                    $problems[] = "lang/$lang: '$key' should not follow '$previous' (strcmp order)";
                }
                $previous = $key;
            }
        }

        return $problems;
    }

    /**
     * The values that begin or end with a space.
     *
     * A space at the edge of a string is invisible to a translator, who drops it or keeps it at random; the code that joins
     * two strings carries the separator instead.
     *
     * @param array $all Language => (key => value).
     * @return string[] One line per problem.
     */
    private static function edge_space_problems(array $all): array {
        $problems = [];
        foreach ($all as $lang => $strings) {
            foreach ($strings as $key => $value) {
                // Unicode aware: a no-break space counts, and the bytes of a dash or of an accented letter do not.
                if (preg_match('/^[\s\p{Zs}]|[\s\p{Zs}]$/u', (string) $value)) {
                    $problems[] = "lang/$lang: '$key' begins or ends with a space";
                }
            }
        }

        return $problems;
    }

    public function test_the_reader_knows_every_language_of_the_plugin(): void {
        $found = [];
        foreach (new \DirectoryIterator(__DIR__ . '/../lang') as $entry) {
            if ($entry->isDir() && !$entry->isDot() && is_file($entry->getPathname() . '/tool_vae.php')) {
                $found[] = $entry->getFilename();
            }
        }
        sort($found);
        $known = lang_files::languages();
        sort($known);

        $this->assertSame($found, $known, 'A language added to lang/ must be added to tests/fixtures/lang_files.php.');
        $this->assertContains(self::REFERENCE, $known);
    }

    public function test_every_capability_has_its_string_under_the_right_key_in_every_language(): void {
        $capabilities = array_keys(self::capabilities());

        $this->assertNotEmpty($capabilities, 'db/access.php declares no capability.');
        $this->assertSame([], self::capability_problems($capabilities, self::all_strings()));
    }

    public function test_every_message_provider_has_its_string_in_every_language(): void {
        $providers = self::providers();

        $this->assertNotEmpty($providers, 'db/messages.php declares no provider.');
        $this->assertSame([], self::provider_problems($providers, self::all_strings()));
    }

    public function test_every_language_has_the_keys_of_english_and_no_other(): void {
        // The parity of the translations: the package of the plugin ships English only, lang/fr stays in the repository.
        lang_files::skip_unless_present('fr');
        $this->assertSame([], self::parity_problems(self::all_strings(), array_keys(self::ENGLISH_ONLY)));
    }

    public function test_english_names_the_plugin(): void {
        $this->assertArrayHasKey('pluginname', lang_files::strings(self::REFERENCE), 'moodle-plugin-ci validate needs it.');
    }

    public function test_the_keys_of_every_file_follow_the_order_of_strcmp(): void {
        $this->assertSame([], self::order_problems(self::all_strings()));
    }

    public function test_no_value_begins_or_ends_with_a_space(): void {
        $this->assertSame([], self::edge_space_problems(self::all_strings()));
    }

    public function test_the_checks_catch_what_they_must(): void {
        $all = [
            'en' => ['a' => 'A', 'messageprovider:sent' => 'Sent', 'tool/vae:declare' => 'Declare', 'vae:read' => 'Read'],
            'fr' => ['b' => 'B', 'a' => 'A', 'messageprovider:sent' => ' ', 'vae:read' => 'Lire'],
        ];

        $capabilities = self::capability_problems(['tool/vae:declare', 'tool/vae:read', 'tool_vae_read'], $all);
        $this->assertContains("lang/en: missing string 'vae:declare' for tool/vae:declare", $capabilities);
        $this->assertContains("lang/en: wrong key 'tool/vae:declare', expected 'vae:declare'", $capabilities);
        $this->assertContains("lang/fr: missing string 'vae:declare' for tool/vae:declare", $capabilities);
        $this->assertContains('malformed capability: tool_vae_read (expected type/name:action)', $capabilities);
        $this->assertCount(4, $capabilities, 'The capability whose string exists everywhere raises nothing.');

        $this->assertSame(
            ["lang/fr: missing string 'messageprovider:sent' for the message provider sent"],
            self::provider_problems(['sent'], $all),
            'A blank string is a missing string.'
        );

        $parity = self::parity_problems($all);
        $this->assertContains("lang/fr: missing string 'tool/vae:declare' (present in en)", $parity);
        $this->assertContains("lang/fr: string 'b' absent from en", $parity);
        $this->assertSame(['lang/en is missing: it is the reference language of Moodle'], self::parity_problems(['fr' => []]));
        $this->assertSame(
            [
                "English-only string 'gone' absent from en",
                "lang/fr: string 'only' is English-only: a translation would never be read",
            ],
            self::parity_problems(['en' => ['a' => 'A', 'only' => 'O'], 'fr' => ['a' => 'A', 'only' => 'O']], ['only', 'gone']),
            'An English-only key is not asked of French, refused if translated, and must exist in English.'
        );
        $this->assertSame([], self::parity_problems(['en' => ['a' => 'A', 'only' => 'O'], 'fr' => ['a' => 'A']], ['only']));

        $this->assertSame(["lang/fr: 'a' should not follow 'b' (strcmp order)"], self::order_problems(['fr' => $all['fr']]));
        $this->assertSame(
            ["lang/en: 'B' should not follow 'a' (strcmp order)"],
            self::order_problems(['en' => ['a' => 'x', 'B' => 'y']]),
            'strcmp() is the byte order: an upper case letter comes before every lower case one.'
        );
        $this->assertSame([], self::order_problems(['en' => ['B' => 'y', 'a' => 'x']]));

        $this->assertSame(
            [
                "lang/en: 'lead' begins or ends with a space",
                "lang/en: 'trail' begins or ends with a space",
                "lang/fr: 'nbsp' begins or ends with a space",
            ],
            self::edge_space_problems([
                'en' => ['inner' => 'a b', 'lead' => ' (a)', 'trail' => 'added: '],
                'fr' => ['dash' => "\u{2014}", 'nbsp' => "ajout\u{00A0}", 'plain' => 'ajouté', 'upper' => "\u{00C0}"],
            ])
        );
    }
}
