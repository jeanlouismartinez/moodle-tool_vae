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

use tool_vae\privacy\provider;

/**
 * Every table of db/install.xml is declared to the privacy provider, with each of its columns.
 *
 * THIS IS HOW A PROVIDER ROTS: a table, or a column, is added with a lot, nobody declares it, and the administrator answers
 * a subject access request in good faith with an incomplete answer. Neither Moodle nor moodle-plugin-ci says so.
 *
 * A table that holds no personal data is declared all the same, its strings saying so (decision of the project owner,
 * 19 November 2026: tool_vae_obsolete and tool_vae_ref, exempted until then). The marker of an exemption,
 *
 *     // Privacy-exempt: tool_vae_x.
 *
 * is still read, so that a stale one fails; none is left, and a test says so.
 *
 * TWO READINGS, BECAUSE EACH CAN BE FOOLED ALONE. The metadata the provider returns to the core (what an administrator is
 * actually told), and the names written in its source (what a reader of the file sees): a table added to one and not to
 * the other fails. The first version of this guard, in bash, looked for the word null_provider and found it in a COMMENT
 * that explained why the provider no longer was one: a literal test where a test of meaning was needed.
 *
 * A GUARD THAT FINDS NOTHING PROVES NOTHING: the comparison is also given tables that must fail it.
 * This test replaces scripts/verifier-privacy.php, a command line script that ran outside Moodle (19 November 2026).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\privacy\provider::class)]
final class privacy_coverage_test extends \advanced_testcase {
    /**
     * The tables of db/install.xml.
     *
     * @return string[]
     */
    private static function installed_tables(): array {
        $xml = simplexml_load_file(__DIR__ . '/../db/install.xml');
        if ($xml === false) {
            throw new \coding_exception('db/install.xml cannot be read');
        }
        $tables = [];
        foreach ($xml->TABLES->TABLE as $table) {
            $tables[] = (string) $table['NAME'];
        }

        return $tables;
    }

    /**
     * The source of the provider.
     *
     * @return string
     */
    private static function source(): string {
        return (string) file_get_contents(__DIR__ . '/../classes/privacy/provider.php');
    }

    /**
     * The tables the provider declares to the core.
     *
     * @return string[]
     */
    private static function declared_to_the_core(): array {
        $declared = [];
        foreach (provider::get_metadata(new \core_privacy\local\metadata\collection('tool_vae'))->get_collection() as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $declared[] = $item->get_name();
            }
        }

        return $declared;
    }

    /**
     * The tables the source of the provider names in add_database_table().
     *
     * @param string $source
     * @return string[]
     */
    private static function declared_in_the_source(string $source): array {
        preg_match_all("/add_database_table\(\s*'([^']+)'/", $source, $matches);

        return $matches[1];
    }

    /**
     * The tables the source of the provider exempts, by the marker.
     *
     * @param string $source
     * @return string[]
     */
    private static function exempted(string $source): array {
        preg_match_all('/privacy-exempt:\s*([A-Za-z0-9_]+)/i', $source, $matches);

        return $matches[1];
    }

    /**
     * What is wrong between the tables, the declared ones and the exempted ones.
     *
     * @param string[] $tables The tables of install.xml.
     * @param string[] $declared The tables declared.
     * @param string[] $exempted The tables exempted.
     * @return string[] One line per problem.
     */
    private static function problems(array $tables, array $declared, array $exempted): array {
        $problems = [];
        foreach ($tables as $table) {
            if (!in_array($table, $declared, true) && !in_array($table, $exempted, true)) {
                $problems[] = "table $table neither declared nor explicitly exempted";
            }
        }
        foreach ($declared as $table) {
            if (!in_array($table, $tables, true)) {
                $problems[] = "table $table declared to the provider but absent from install.xml";
            }
        }
        foreach ($exempted as $table) {
            if (!in_array($table, $tables, true)) {
                $problems[] = "table $table exempted but absent from install.xml: a stale exemption";
            }
        }

        return $problems;
    }

    /**
     * The columns of each table of db/install.xml, but the id: the core asks for the fields that hold data, and the id of a
     * row is the row itself, declared with its table.
     *
     * @return array Table => column names.
     */
    private static function installed_columns(): array {
        $xml = simplexml_load_file(__DIR__ . '/../db/install.xml');
        $columns = [];
        foreach ($xml->TABLES->TABLE as $table) {
            foreach ($table->FIELDS->FIELD as $field) {
                if ((string) $field['NAME'] !== 'id') {
                    $columns[(string) $table['NAME']][] = (string) $field['NAME'];
                }
            }
        }

        return $columns;
    }

    /**
     * The columns the provider declares to the core, table by table.
     *
     * @return array Table => column names.
     */
    private static function columns_declared_to_the_core(): array {
        $declared = [];
        foreach (provider::get_metadata(new \core_privacy\local\metadata\collection('tool_vae'))->get_collection() as $item) {
            if ($item instanceof \core_privacy\local\metadata\types\database_table) {
                $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
            }
        }

        return $declared;
    }

    /**
     * What is wrong between the columns of the declared tables and the columns declared.
     *
     * A table exempted whole is not read here: the test of the tables says it is exempted, and why.
     *
     * @param array $columns Table => columns of install.xml.
     * @param array $declared Table => columns declared.
     * @return string[] One line per problem.
     */
    private static function column_problems(array $columns, array $declared): array {
        $problems = [];
        foreach ($declared as $table => $fields) {
            foreach (array_diff($columns[$table] ?? [], $fields) as $column) {
                $problems[] = "column $table.$column not declared";
            }
            foreach (array_diff($fields, $columns[$table] ?? []) as $column) {
                $problems[] = "column $table.$column declared but absent from install.xml";
            }
        }

        return $problems;
    }

    public function test_every_column_of_a_declared_table_is_declared_and_exists(): void {
        $this->assertSame([], self::column_problems(self::installed_columns(), self::columns_declared_to_the_core()));
    }

    public function test_no_table_is_exempted_any_more(): void {
        $this->assertSame([], self::exempted(self::source()), 'Every table is declared, with each of its columns.');
    }

    public function test_the_comparison_of_the_columns_catches_what_it_must(): void {
        $this->assertSame([], self::column_problems(['tool_vae_a' => ['x', 'y']], ['tool_vae_a' => ['y', 'x']]));
        $this->assertSame(
            [
                'column tool_vae_a.y not declared',
                'column tool_vae_a.z declared but absent from install.xml',
                'column tool_vae_gone.w declared but absent from install.xml',
            ],
            self::column_problems(['tool_vae_a' => ['x', 'y']], ['tool_vae_a' => ['x', 'z'], 'tool_vae_gone' => ['w']])
        );
    }

    public function test_the_provider_declares_metadata_when_the_plugin_has_tables(): void {
        $this->assertNotEmpty(self::installed_tables());
        $this->assertStringContainsString('metadata\\provider', self::source());
        $this->assertContains(\core_privacy\local\metadata\provider::class, class_implements(provider::class));
    }

    public function test_every_table_is_declared_to_the_core_or_exempted(): void {
        $tables = self::installed_tables();
        $declared = self::declared_to_the_core();

        $this->assertSame([], self::problems($tables, $declared, self::exempted(self::source())));
        $this->assertGreaterThan(20, count($declared), 'The metadata of the provider is read, not an empty list.');
    }

    public function test_the_source_names_the_same_tables_as_the_metadata(): void {
        $tables = self::installed_tables();
        $source = self::source();
        $declared = self::declared_in_the_source($source);
        $metadata = self::declared_to_the_core();
        sort($declared);
        sort($metadata);

        $this->assertSame([], self::problems($tables, $declared, self::exempted($source)));
        $this->assertSame($metadata, $declared, 'A table name in the source is the name the core is given.');
    }

    public function test_the_comparison_catches_what_it_must(): void {
        $this->assertSame([], self::problems(['tool_vae_a', 'tool_vae_b'], ['tool_vae_a'], ['tool_vae_b']));
        $this->assertSame(
            [
                'table tool_vae_b neither declared nor explicitly exempted',
                'table tool_vae_gone declared to the provider but absent from install.xml',
                'table tool_vae_old exempted but absent from install.xml: a stale exemption',
            ],
            self::problems(['tool_vae_a', 'tool_vae_b'], ['tool_vae_a', 'tool_vae_gone'], ['tool_vae_old'])
        );
        $this->assertSame(['tool_vae_x'], self::exempted("// Privacy-exempt: tool_vae_x.\n"));
        $this->assertSame(['tool_vae_y'], self::declared_in_the_source("\$c->add_database_table(\n 'tool_vae_y', []);"));
    }
}
