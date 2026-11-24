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

use tool_vae\local\next_actions;

/**
 * The rendering of the banner "Ce que je peux faire maintenant" (lot S2).
 *
 * WHAT IS ASSERTED. That each action of the vaeiste is a button worded in words with its link; that each line of the manager
 * gives its number, in the singular for 1, with a link readable out of context; that an empty list reads a plain sentence;
 * that no block gives a total, a percentage, an "N of M" or a colour of success; that no displayed string says "candidat";
 * that the block of the manager names nobody.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\output\next_actions::class)]
final class next_actions_screen_test extends \advanced_testcase {
    /**
     * One action or one line made by hand, as next_actions gives them.
     *
     * @param string $key
     * @param int $count
     * @param string $path
     * @param array $params
     * @return \stdClass
     */
    private function made(string $key, int $count, string $path, array $params = []): \stdClass {
        return (object) ['key' => $key, 'count' => $count, 'url' => new \moodle_url($path, $params)];
    }

    /**
     * The strings nextactions* of a language, read from the file of the plugin (the tests run in English).
     *
     * @param string $lang 'fr' or 'en'.
     * @return string[] Key => text.
     */
    private function strings(string $lang): array {
        require_once(__DIR__ . '/fixtures/lang_files.php');
        $string = \tool_vae\fixtures\lang_files::strings($lang);

        return array_filter($string, fn(string $key): bool => str_starts_with($key, 'nextactions'), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Whether lang/fr is here: the package of the plugin ships English only, the repository English and French.
     *
     * @return bool
     */
    private static function has_french(): bool {
        require_once(__DIR__ . '/fixtures/lang_files.php');

        return \tool_vae\fixtures\lang_files::present('fr');
    }

    /**
     * Every action of the vaeiste and every line of the manager, as next_actions could give them.
     *
     * @return \stdClass[][] [vaeiste actions, manager lines]
     */
    private function everything(): array {
        return [
            [
                $this->made(next_actions::DECLARE, 0, '/admin/tool/vae/declare.php'),
                $this->made(next_actions::ANSWER, 3, '/admin/tool/vae/dossier.php', ['userid' => 7]),
                $this->made(next_actions::DRAFT, 0, '/admin/tool/vae/faisabilite.php'),
                $this->made(next_actions::SELFPOSITION, 0, '/admin/tool/vae/selfposition.php', ['userid' => 7]),
            ],
            [
                $this->made(next_actions::ATTEST, 12, '/admin/tool/vae/candidates.php'),
                $this->made(next_actions::ANSWERS, 1, '/admin/tool/vae/precision.php', ['evidenceid' => 5]),
                $this->made(next_actions::OPINIONS, 2, '/admin/tool/vae/deposit.php', ['userid' => 9]),
            ],
        ];
    }

    /**
     * The block of the vaeiste drawn by the renderer of the plugin (lot 7: next_actions_screen became a piece).
     *
     * @param \stdClass[] $actions
     * @return string HTML.
     */
    private static function candidate(array $actions): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae')->render(\tool_vae\output\next_actions::candidate($actions));
    }

    /**
     * The block of the manager drawn by the renderer of the plugin.
     *
     * @param \stdClass[] $rows
     * @return string HTML.
     */
    private static function manager(array $rows): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae')->render(\tool_vae\output\next_actions::manager($rows));
    }

    public function test_the_vaeiste_block_lists_each_action_with_its_link(): void {
        $this->resetAfterTest();
        [$actions] = $this->everything();

        $html = self::candidate($actions);

        $this->assertStringContainsString('class="tool-vae-next"', $html);
        $this->assertStringContainsString(s(get_string('nextactionstitle', 'tool_vae')), $html);
        $this->assertSame(4, substr_count($html, '<a '));
        $expected = [
            'declare.php' => get_string('nextactionsaction_declare', 'tool_vae'),
            'dossier.php?userid=7' => get_string('nextactionsactions_answer', 'tool_vae', 3),
            'faisabilite.php' => get_string('nextactionsaction_draft', 'tool_vae'),
            'selfposition.php?userid=7' => get_string('nextactionsaction_selfposition', 'tool_vae'),
        ];
        foreach ($expected as $target => $label) {
            $this->assertMatchesRegularExpression(
                '~<a [^>]*href="[^"]*' . preg_quote($target, '~') . '"[^>]*>' . preg_quote(s($label), '~') . '</a>~',
                $html,
                $target
            );
        }
        if (self::has_french()) {
            $french = $this->strings('fr');
            $this->assertSame('Déclarer une activité', $french['nextactionsaction_declare']);
            $this->assertSame('Répondre aux précisions demandées ({$a})', $french['nextactionsactions_answer']);
        }
        $this->assertSame('Answer the requests for precision ({$a})', $this->strings('en')['nextactionsactions_answer']);
        if (self::has_french()) {
            $this->assertSame('Reprendre mon dossier de faisabilité', $french['nextactionsaction_draft']);
            $this->assertSame('Se positionner', $french['nextactionsaction_selfposition']);
            $this->assertSame('Ce que vous pouvez faire maintenant', $french['nextactionstitle']);
        }
        $this->assertStringNotContainsString(s(get_string('nextactionsnone', 'tool_vae')), $html);
    }

    public function test_a_single_request_reads_in_the_singular_without_a_number(): void {
        $this->resetAfterTest();

        $html = self::candidate([
            $this->made(next_actions::ANSWER, 1, '/admin/tool/vae/precision.php', ['evidenceid' => 5]),
        ]);

        if (self::has_french()) {
            $this->assertSame('Répondre à la précision demandée', $this->strings('fr')['nextactionsaction_answer']);
        }
        $this->assertSame('Answer the request for precision', $this->strings('en')['nextactionsaction_answer']);
        $this->assertMatchesRegularExpression(
            '~<a [^>]*href="[^"]*precision\.php\?evidenceid=5"[^>]*>'
            . preg_quote(s(get_string('nextactionsaction_answer', 'tool_vae')), '~') . '</a>~',
            $html
        );
        $this->assertStringNotContainsString('(1)', $html);
    }

    public function test_a_vaeiste_with_no_action_reads_a_plain_sentence(): void {
        $this->resetAfterTest();

        $html = self::candidate([]);

        $this->assertStringContainsString('class="tool-vae-next"', $html);
        // The sentence speaks of the banner, not of the pathway: it never says that all is done.
        if (self::has_french()) {
            $this->assertSame('Aucune action n\'est signalée ici en ce moment.', $this->strings('fr')['nextactionsnone']);
        }
        $this->assertSame('No action is flagged here at the moment.', $this->strings('en')['nextactionsnone']);
        $this->assertStringContainsString(s(get_string('nextactionsnone', 'tool_vae')), $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    public function test_the_manager_block_lists_each_row_with_its_number_and_link(): void {
        $this->resetAfterTest();
        [, $rows] = $this->everything();

        $html = self::manager($rows);

        $this->assertStringContainsString('class="tool-vae-next"', $html);
        $this->assertStringContainsString(s(get_string('nextactionstitle', 'tool_vae')), $html);
        $this->assertSame(3, substr_count($html, '<a '));
        $expected = [
            'candidates.php' => get_string('nextactionslines_attest', 'tool_vae', 12),
            'precision.php?evidenceid=5' => get_string('nextactionsline_answers', 'tool_vae', 1),
            'deposit.php?userid=9' => get_string('nextactionslines_opinions', 'tool_vae', 2),
        ];
        foreach ($expected as $target => $label) {
            $this->assertMatchesRegularExpression(
                '~<a [^>]*href="[^"]*' . preg_quote($target, '~') . '"[^>]*>' . preg_quote(s($label), '~') . '</a>~',
                $html,
                $target
            );
        }
        $this->assertStringContainsString(s(get_string('nextactionsallblocs', 'tool_vae')), $html);
        $this->assertStringNotContainsString(s(get_string('nextactionsnonemanager', 'tool_vae')), $html);

        // A number of 1 is said in the singular, on every line.
        $ones = self::manager([
            $this->made(next_actions::ATTEST, 1, '/admin/tool/vae/candidates.php'),
            $this->made(next_actions::ANSWERS, 2, '/admin/tool/vae/precision.php', ['evidenceid' => 5]),
            $this->made(next_actions::OPINIONS, 1, '/admin/tool/vae/deposit.php', ['userid' => 9]),
        ]);
        $this->assertStringContainsString('>' . s(get_string('nextactionsline_attest', 'tool_vae', 1)) . '</a>', $ones);
        $this->assertStringContainsString('>' . s(get_string('nextactionslines_answers', 'tool_vae', 2)) . '</a>', $ones);
        $this->assertStringContainsString('>' . s(get_string('nextactionsline_opinions', 'tool_vae', 1)) . '</a>', $ones);
        if (self::has_french()) {
            $french = $this->strings('fr');
            $this->assertSame('{$a} déclaration à attester', $french['nextactionsline_attest']);
            $this->assertSame('{$a} déclarations à attester', $french['nextactionslines_attest']);
            $this->assertSame('{$a} réponse à vos questions à lire', $french['nextactionsline_answers']);
            $this->assertSame('{$a} réponses à vos questions à lire', $french['nextactionslines_answers']);
            $this->assertSame('{$a} dépôt sans avis', $french['nextactionsline_opinions']);
            $this->assertSame('{$a} dépôts sans avis', $french['nextactionslines_opinions']);
            $this->assertSame(
                'Ces nombres comptent tous vos vaeistes suivis, quel que soit le bloc choisi.',
                $french['nextactionsallblocs']
            );
        }
    }

    public function test_a_manager_with_nothing_awaiting_reads_a_plain_sentence(): void {
        $this->resetAfterTest();

        $html = self::manager([]);

        $this->assertStringContainsString('class="tool-vae-next"', $html);
        if (self::has_french()) {
            $this->assertSame('Rien n\'attend votre réponse en ce moment.', $this->strings('fr')['nextactionsnonemanager']);
        }
        $this->assertStringContainsString(s(get_string('nextactionsnonemanager', 'tool_vae')), $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    public function test_the_blocks_give_no_total_no_percentage_and_no_success_colour(): void {
        $this->resetAfterTest();
        [$actions, $rows] = $this->everything();

        foreach (
            [
                self::candidate($actions),
                self::candidate([]),
                self::manager($rows),
                self::manager([]),
            ] as $html
        ) {
            $this->assertStringNotContainsString('%', $html);
            $this->assertStringNotContainsString('alert-success', $html);
            $this->assertStringNotContainsString('text-success', $html);
            $this->assertStringNotContainsString('badge-success', $html);
            $this->assertStringNotContainsString('bg-success', $html);
            $this->assertDoesNotMatchRegularExpression('/\d+\s*(\/|sur|of|out of)\s*\d+/', strip_tags($html));
            // The total of the lines (12 + 1 + 2) is never given.
            $this->assertStringNotContainsString('15', strip_tags($html));
        }
    }

    public function test_no_displayed_text_says_candidat(): void {
        $this->resetAfterTest();
        $strings = [];
        foreach (self::has_french() ? ['fr', 'en'] : ['en'] as $lang) {
            $strings[$lang] = $this->strings($lang);
            $this->assertNotEmpty($strings[$lang], $lang);
        }

        if (isset($strings['fr'])) {
            $this->assertSame(array_keys($strings['fr']), array_keys($strings['en']));
            foreach ($strings['fr'] as $key => $text) {
                $this->assertStringNotContainsStringIgnoringCase('candidat', $text, $key);
            }
        }
        foreach ($strings['en'] as $key => $text) {
            $this->assertStringNotContainsStringIgnoringCase('candidate', $text, $key);
        }
    }

    public function test_the_manager_block_carries_no_name_even_with_named_followed(): void {
        $this->resetAfterTest();
        $camille = $this->getDataGenerator()->create_user(['firstname' => 'Camille', 'lastname' => 'Durand']);
        $lucas = $this->getDataGenerator()->create_user(['firstname' => 'Lucas', 'lastname' => 'Bernard']);

        $html = self::manager([
            $this->made(next_actions::ATTEST, 2, '/admin/tool/vae/candidates.php'),
            $this->made(next_actions::ANSWERS, 1, '/admin/tool/vae/precision.php', ['evidenceid' => 41]),
            $this->made(next_actions::OPINIONS, 2, '/admin/tool/vae/deposit.php', ['userid' => $camille->id]),
        ]);

        foreach ([$camille, $lucas] as $user) {
            $this->assertStringNotContainsString($user->firstname, $html);
            $this->assertStringNotContainsString($user->lastname, $html);
            $this->assertStringNotContainsString(fullname($user), $html);
        }
    }

    public function test_the_template_renders_its_example_context(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $file = __DIR__ . '/../templates/next_actions.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/next_actions', $source);
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*\})\s*\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = $OUTPUT->render_from_template('tool_vae/next_actions', $context);

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('class="tool-vae-next"', $html);
        $this->assertStringContainsString('<a ', $html);
    }
}
