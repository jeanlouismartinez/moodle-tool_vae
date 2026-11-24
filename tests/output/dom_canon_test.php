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

/**
 * The reading of a "<" that begins no tag by the canonical form of the tests of lot 7 (dom_canon).
 *
 * WHY THIS TEST EXISTS. The continuous integration of 23 November 2026 failed two tests of fiche_preview_test. The old code
 * wrote the string fcimportnotemarkup as it is, with a "<" followed by a quote; the template escapes it. A browser reads the
 * two the same way (HTML5: a "<" that is not followed by a letter, "/", "!" or "?" is text). libxml 2.9.13, the one of the
 * image of the runner (Ubuntu 22.04), reads the raw one as the start of a tag and drops the text up to the next ">"; libxml
 * 2.9.14, the one of the bench, reads it as text. So the same two drawings were equal on one machine and different on the
 * other. The canonical form now reads such a "<" as a browser does, whatever the machine.
 *
 * WHAT IS ASSERTED. That a "<" followed by a quote, a space, a digit or an equal sign gives the same canonical form raw or
 * escaped, and that this form holds the whole text; that what begins a tag (a letter, "/", "!") is still markup; that an
 * escaped "<" is not escaped twice; that the text of a script, which a reader takes as it is, is not touched.
 *
 * THE FIRST ASSERTION IS RED ONLY UNDER libxml 2.9.13 (or the same reading in another version): the bench cannot show it
 * red. The others are red on the bench when the rule is made too wide or too narrow.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class dom_canon_test extends \basic_testcase {
    use dom_canon;

    /**
     * The texts that hold a "<" that begins no tag. The first is the real one: the string of the preview of an import.
     *
     * @return string[][]
     */
    public static function stray_signs(): array {
        return [
            'a quote after it' => ['1 bloc label(s) containing "<" or a tag (BC01): Moodle would refuse them as a title.'],
            'a space after it' => ['if 1 < 2 then'],
            'a digit after it' => ['at <3 days'],
            'an equal sign after it' => ['a <= b'],
        ];
    }

    /**
     * A "<" that begins no tag is text, raw or escaped, and the whole text is kept.
     *
     * @param string $text
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('stray_signs')]
    public function test_a_less_than_sign_that_begins_no_tag_is_text(string $text): void {
        $escaped = self::canon('<div class="small">' . htmlspecialchars($text, ENT_COMPAT) . '</div>');
        // The premise: the escaped text is read whole, whatever the version of libxml.
        $this->assertStringContainsString(json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $escaped);

        $this->assertSame($escaped, self::canon('<div class="small">' . $text . '</div>'));
    }

    /**
     * What begins a tag or closes one is still read as markup, next to a "<" that is text.
     */
    public function test_what_begins_a_tag_is_still_read_as_markup(): void {
        $canon = self::canon('<div>if 1 < 2 then <b>bold</b></div>');
        $this->assertStringContainsString('"if 1 < 2 then "', $canon);
        $this->assertStringContainsString("<b>\n      \"bold\"\n    </b>", $canon);
    }

    /**
     * What opens a comment is still read as markup: the comment is dropped, and its text does not appear.
     */
    public function test_a_comment_is_still_dropped(): void {
        $this->assertSame(
            "<body>\n  <div>\n    \"a\"\n    \"b\"\n  </div>\n</body>\n",
            self::canon('<div>a<!-- x <b> -->b</div>')
        );
    }

    /**
     * A "<" already escaped stays what it is: a text "<", not a text "&lt;".
     */
    public function test_a_less_than_sign_already_escaped_is_not_escaped_twice(): void {
        $this->assertStringContainsString('"<b>"', self::canon('<div>&lt;b&gt;</div>'));
    }

    /**
     * The text of a script is taken as it is by a reader: it is not changed.
     */
    public function test_the_text_of_a_script_is_left_as_it_is(): void {
        $canon = self::canon('<div><script>if (a < b) { x(); }</script></div>');
        $this->assertStringContainsString('"if (a < b) { x(); }"', $canon);
    }
}
