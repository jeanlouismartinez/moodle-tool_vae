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
 * The type each entry point gives the codes it reads: the strictest the real values allow.
 *
 * WHAT IS ASSERTED. That a code (record number, rank, session key, day, list of pieces) is read with a strict type, that
 * every real value of its kind passes that type unchanged (record numbers and bloc codes of the export of France compétences
 * of 19 October 2026, the records of the test site, the ranks of the importer), and that a hostile value (markup, quotes,
 * a path, control bytes) comes out with none of it. A field that stays PARAM_RAW or PARAM_RAW_TRIMMED says why, in a comment
 * on the line above: a free text (cleaned when shown), or a value whose real forms a strict type would break.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class param_types_test extends \advanced_testcase {
    /** @var array Real values of each kind of code, as they are found. */
    private const REAL = [
        'fichecode' => ['RNCP40692', 'RNCP183', 'RNCP36836', 'RS5678', 'RS00000', 'DEMO-GRAND-AGE', 'DEMO-GRAND-AGE2', 'DEMOBLOCS'],
        'rank' => ['', 'BC01', 'BC05', 'C11', 'H111', 'H112', 'D2', 'C01'],
        'bloc' => ['RNCP40692', 'RNCP40692|BC01', 'DEMO-GRAND-AGE|BC03', 'RS5678|C01'],
        'day' => ['2026-11-19', '2000-01-01'],
        'piecesseen' => ['', '4:3f2a9c0b1d4e5f60718293a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4|5:-'],
    ];

    /** @var string[] Hostile values: none of their markup, quotes, path or control bytes may come out. */
    private const HOSTILE = ['<b>RNCP1</b>', '"RNCP1', "'RNCP1", '../RNCP1', "RNCP1\x00", "RNCP1\x1b[31m", '"><script>x</script>'];

    /**
     * The strict entry points: file, name, type.
     *
     * @return array
     */
    public static function strict(): array {
        return [
            'certify fichecode' => ['certify.php', 'fichecode', 'PARAM_ALPHANUMEXT', 'fichecode'],
            'certify rank' => ['certify.php', 'rank', 'PARAM_ALPHANUM', 'rank'],
            'evaluate fichecode' => ['evaluate.php', 'fichecode', 'PARAM_ALPHANUMEXT', 'fichecode'],
            'evaluate rank' => ['evaluate.php', 'rank', 'PARAM_ALPHANUM', 'rank'],
            'selfposition fiche' => ['selfposition.php', 'fiche', 'PARAM_ALPHANUMEXT', 'fichecode'],
            'selfposition rank' => ['selfposition.php', 'rank', 'PARAM_ALPHANUM', 'rank'],
            'situations fiche' => ['situations.php', 'fiche', 'PARAM_ALPHANUMEXT', 'fichecode'],
            'situations rank' => ['situations.php', 'rank', 'PARAM_ALPHANUM', 'rank'],
            'situation fiche' => ['situation.php', 'fiche', 'PARAM_ALPHANUMEXT', 'fichecode'],
            'situation tags' => ['situation.php', 'tags', 'PARAM_ALPHANUM', 'rank'],
            'candidates bloc' => ['candidates.php', 'bloc', 'PARAM_TEXT', 'bloc'],
            'organisation bloc' => ['organisation.php', 'bloc', 'PARAM_TEXT', 'bloc'],
            'selfpos_screen sesskey' => ['classes/local/selfpos_screen.php', 'sesskey', 'PARAM_ALPHANUM', null],
            'situation_screen sesskey' => ['classes/local/situation_screen.php', 'sesskey', 'PARAM_ALPHANUM', null],
            'referent_form bloc' => ['classes/form/referent_form.php', 'bloc', 'PARAM_TEXT', 'bloc'],
            'certification_form date' => ['classes/form/certification_form.php', 'decisiondate', 'PARAM_ALPHANUMEXT', 'day'],
            'certification_form validuntil' => ['classes/form/certification_form.php', 'validuntil', 'PARAM_ALPHANUMEXT', 'day'],
            'attest_form piecesseen' => ['classes/form/attest_form.php', 'piecesseen', 'PARAM_TEXT', 'piecesseen'],
        ];
    }

    /**
     * The entry points that stay raw, each with a comment above that says why.
     *
     * @return array
     */
    public static function raw(): array {
        return [
            'situation part' => ['situation.php', 'part'],
            'situation url' => ['situation.php', 'url'],
            'scope_form competency' => ['classes/form/scope_form.php', 'competency'],
            'ref_form url' => ['classes/form/ref_form.php', 'url'],
            'piece_link_form url' => ['classes/form/piece_link_form.php', 'url'],
            'certification_form reason' => ['classes/form/certification_form.php', 'reason'],
            'certification_form alertreason' => ['classes/form/certification_form.php', 'alertreason'],
            'evaluation_form reason' => ['classes/form/evaluation_form.php', 'reason'],
            'note_form notetext' => ['classes/form/note_form.php', 'notetext'],
            'section_form text' => ['classes/form/section_form.php', 'text'],
            'selfpos_form justification' => ['classes/form/selfpos_form.php', 'justification'],
            'situation_form content' => ['classes/form/situation_form.php', 'content'],
            'selfpos_screen justification' => ['classes/local/selfpos_screen.php', 'justification'],
            'situation_screen field' => ['classes/local/situation_screen.php', '$field'],
            'ai text' => ['ai.php', '$step'],
        ];
    }

    /**
     * Every line of a file that reads or types this parameter, with the line above it.
     *
     * @param string $file
     * @param string $name A quoted name, or the variable that holds it.
     * @return array[] [line above, line]
     */
    private static function reads(string $file, string $name): array {
        $lines = file(__DIR__ . '/../' . $file, FILE_IGNORE_NEW_LINES);
        $quoted = $name[0] === '$' ? preg_quote($name, '/') : "'" . preg_quote($name, '/') . "'";
        $found = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/(optional_param|required_param|optional_param_array|setType)\(\s*' . $quoted . '/', $line)) {
                $found[] = [$i > 0 ? $lines[$i - 1] : '', $line];
            }
        }

        return $found;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('strict')]
    public function test_a_code_is_read_with_a_strict_type(string $file, string $name, string $type, ?string $kind): void {
        $reads = self::reads($file, $name);

        $this->assertNotEmpty($reads, "$file reads no '$name'.");
        foreach ($reads as [, $line]) {
            $this->assertMatchesRegularExpression('/[ ,(]' . $type . '\)/', $line, "$file: $line");
        }

        $values = $kind === null ? [sesskey()] : self::REAL[$kind];
        foreach ($values as $value) {
            $this->assertSame($value, clean_param($value, constant($type)), "A real value must pass $type unchanged.");
        }
        // A code type keeps letters, digits (and - _); a text type, for a value checked against a closed list, drops markup.
        $left = $type === 'PARAM_TEXT' ? '~<\s*/?[a-z]|\x00~i' : '~[<>"\'/.\x00-\x1f]~';
        foreach (self::HOSTILE as $value) {
            $clean = (string) clean_param($value, constant($type));
            $this->assertDoesNotMatchRegularExpression($left, $clean, json_encode($value) . " under $type");
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('raw')]
    public function test_a_raw_field_says_why_it_is_raw(string $file, string $name): void {
        $reads = self::reads($file, $name);

        $this->assertNotEmpty($reads, "$file reads no '$name'.");
        foreach ($reads as [$above, $line]) {
            $this->assertMatchesRegularExpression('/PARAM_RAW(_TRIMMED)?\)/', $line, "$file: $line");
            $this->assertMatchesRegularExpression('~^\s*(//|\*)~', $above, "$file: the line above '$line' says why it is raw.");
        }
    }

    public function test_the_types_chosen_would_break_the_raw_ones(): void {
        // Why the addresses stay raw: PARAM_URL empties an address of a French site that the plugin accepts.
        $this->assertSame('', clean_param('https://fr.wikipedia.org/wiki/Établissement', PARAM_URL));
        $this->assertSame([], \tool_vae\local\piece_manager::validate_url('https://fr.wikipedia.org/wiki/Établissement')[1]);
        // Why the part concerned stays raw: PARAM_TEXT cuts a free text that holds a "<".
        $this->assertNotSame('Page 3 <-> 4', clean_param('Page 3 <-> 4', PARAM_TEXT));
    }
}
