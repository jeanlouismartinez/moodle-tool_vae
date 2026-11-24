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

namespace tool_vae\fixtures;

/**
 * The language files of the plugin, read as they are written, without the fallback of the string manager.
 *
 * WHY NOT get_string_manager(). load_component_strings() fills a language from its parent and from English: a string
 * missing from lang/fr would come back in English, and a test that looks for it would pass. The tests that check what a
 * file says read the file itself.
 *
 * EVERY PATH IS WRITTEN OUT. A language file is included by a literal path, one per language the plugin ships, never by
 * a path built from a variable. A language added to lang/ without being added here is caught by lang_strings_test,
 * which compares this list with the directories it finds.
 *
 * ENGLISH ONLY IN THE PACKAGE. The package of the plugin leaves lang/fr out (export-ignore, decision of the project owner,
 * 22 November 2026): lang/fr stays in the repository. A test reads French only when present('fr') says the file is here;
 * a test that reads nothing else is skipped by skip_unless_present(), with the reason. In the repository nothing is skipped.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lang_files {
    /** @var string[] The languages this class can read, English first. */
    public const KNOWN = ['en', 'fr'];

    /**
     * The languages of the plugin whose file is present here, English first.
     *
     * @return string[]
     */
    public static function languages(): array {
        return array_values(array_filter(
            self::KNOWN,
            fn(string $lang): bool => is_readable(self::path($lang))
        ));
    }

    /**
     * Whether the file of one language is present here (the package of the plugin ships English only).
     *
     * @param string $lang A language of KNOWN.
     * @return bool
     */
    public static function present(string $lang): bool {
        return in_array($lang, self::languages(), true);
    }

    /**
     * Skips the running test when the file of one language is absent, saying why. For a test that reads that language only.
     *
     * @param string $lang A language of KNOWN.
     */
    public static function skip_unless_present(string $lang): void {
        if (!self::present($lang)) {
            \PHPUnit\Framework\Assert::markTestSkipped(
                "No lang/$lang/tool_vae.php: an installed package, which ships English only (lang/$lang stays in the repository)."
            );
        }
    }

    /**
     * The path of the file of one language.
     *
     * @param string $lang A language of KNOWN.
     * @return string
     */
    public static function path(string $lang): string {
        return match ($lang) {
            'en' => __DIR__ . '/../../lang/en/tool_vae.php',
            'fr' => __DIR__ . '/../../lang/fr/tool_vae.php',
            default => throw new \coding_exception("No language file known for '$lang'"),
        };
    }

    /**
     * The strings of one language file, in the order the file writes them.
     *
     * @param string $lang A language of KNOWN.
     * @return string[] Key => value.
     */
    public static function strings(string $lang): array {
        $string = [];
        match ($lang) {
            'en' => include(__DIR__ . '/../../lang/en/tool_vae.php'),
            'fr' => include(__DIR__ . '/../../lang/fr/tool_vae.php'),
            default => throw new \coding_exception("No language file known for '$lang'"),
        };

        return $string;
    }
}
