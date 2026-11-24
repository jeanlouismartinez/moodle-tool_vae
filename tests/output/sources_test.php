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
require_once(__DIR__ . '/presenter_before.php');

use tool_vae\local\importer;

/**
 * The table of the certification records of the administration screen, and the provenance line of a record.
 *
 * WHAT IS ASSERTED (lot 7, MMR-520 ticket 4). That the pieces sources, provenance and source_code draw the same DOM as
 * presenter::sources() and presenter::provenance() drew before their migration (their code kept in the trait
 * presenter_before), for records of each register, a record whose import failed, one whose framework is gone, one with
 * divergences recorded, a provenance not yet published, and texts that are the figure 0; that the context holds plain
 * texts; that a script in a name or an attribution comes out escaped; that the templates place as HTML only their keys
 * ...html and render their examples.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sources::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(provenance::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(source_code::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\tool_vae\local\presenter::class)]
final class sources_test extends \advanced_testcase {
    use dom_canon;
    use presenter_before;

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/admin/tool/vae/index.php'));
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * A piece drawn by the renderer of the plugin for the web.
     *
     * @param \core\output\renderable $piece
     * @return string HTML.
     */
    private function render(\core\output\renderable $piece): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render($piece);
    }

    /**
     * The records of the cases, read back from their table.
     *
     * @return \stdClass[]
     */
    private function records(): array {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_vae');
        $generator->create_source(['fichecode' => 'RNCP36832', 'fichetype' => 'rncp', 'name' => 'Diplôme aide-soignant']);
        $generator->create_source(['fichecode' => 'RS5000', 'fichetype' => 'rs', 'name' => '0']);
        $generator->create_source(['fichecode' => 'RS6000', 'fichetype' => 'rs', 'name' => 'Prévenir les chutes']);
        $DB->set_field('tool_vae_source', 'frameworkid', 0, ['fichecode' => 'RS6000']);
        $DB->set_field('tool_vae_source', 'lastimportstatus', 'error', ['fichecode' => 'RS6000']);
        $DB->set_field('tool_vae_source', 'sourceupdated', 0, ['fichecode' => 'RS6000']);
        $id = (int) $DB->get_field('tool_vae_source', 'id', ['fichecode' => 'RNCP36832']);
        foreach ([importer::GONE, importer::GONE, importer::DIVERGED] as $i => $kind) {
            $DB->insert_record('tool_vae_obsolete', (object) ['competencyid' => 900 + $i, 'sourceid' => $id,
                'detectedat' => time(), 'kind' => $kind]);
        }

        return $DB->get_records('tool_vae_source', null, 'fichecode ASC');
    }

    public function test_the_pieces_are_named_templates(): void {
        global $PAGE;
        $renderer = $PAGE->get_renderer('tool_vae');
        $source = (object) ['sourceupdated' => 0, 'attribution' => 'X', 'licence' => 'Y'];
        $pieces = [
            'tool_vae/sources' => new sources([]),
            'tool_vae/provenance' => new provenance($source),
            'tool_vae/source_code' => new source_code('X'),
        ];
        foreach ($pieces as $name => $piece) {
            $this->assertInstanceOf(\core\output\renderable::class, $piece);
            $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
            $this->assertSame($name, $piece->get_template_name($renderer));
        }
    }

    public function test_the_table_draws_the_dom_of_before(): void {
        $records = $this->records();

        $this->assertSame(self::canon(self::before_sources($records)), self::canon($this->render(new sources($records))));
        $this->assertSame(self::canon(self::before_sources([])), self::canon($this->render(new sources([]))), 'No record.');
        $html = $this->render(new sources($records));
        $this->assertStringContainsString('tool-vae-divergence', $html, 'The premise: divergences are said.');
    }

    public function test_the_provenance_draws_the_dom_of_before(): void {
        $cases = [
            'published' => (object) ['sourceupdated' => 1781136000, 'attribution' => 'France compétences', 'licence' => 'LO-2.0'],
            'not yet published' => (object) ['sourceupdated' => 0, 'attribution' => 'France compétences', 'licence' => 'LO-2.0'],
            'the figure 0' => (object) ['sourceupdated' => null, 'attribution' => '0', 'licence' => '0'],
            'an ampersand' => (object) ['sourceupdated' => 1781136000, 'attribution' => 'A & B', 'licence' => '<b>L</b>'],
        ];
        foreach ($cases as $name => $source) {
            $this->assertSame(
                self::canon(self::before_provenance($source)),
                self::canon($this->render(new provenance($source))),
                $name
            );
        }
    }

    public function test_the_code_of_a_record_is_drawn_with_its_provenance_and_without(): void {
        $source = (object) ['sourceupdated' => 1781136000, 'attribution' => 'France compétences', 'licence' => 'LO-2.0'];

        $this->assertSame(
            \html_writer::tag('strong', 'RS5000') . self::before_provenance($source),
            $this->render(new source_code('RS5000', new provenance($source)))
        );
        $this->assertSame('<strong>0</strong>', $this->render(new source_code('0')));
    }

    public function test_the_context_holds_plain_texts(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('tool_vae');
        $source = (object) ['sourceupdated' => 0, 'attribution' => 'A & B', 'licence' => 'L'];

        $this->assertSame(
            ['text' => get_string('sourceprovenancepending', 'tool_vae', (object) ['attribution' => 'A & B', 'licence' => 'L'])],
            (new provenance($source))->export_for_template($output)
        );
        $this->assertSame(['code' => 'A & B', 'provenancehtml' => false], (new source_code('A & B'))->export_for_template($output));
        $exported = (new sources([]))->export_for_template($output);
        $this->assertStringContainsString(get_string('sourcetable', 'tool_vae'), $exported['headinghtml']);
        $this->assertStringContainsString('tool-vae-sources', $exported['tablehtml']);
    }

    public function test_a_script_in_an_attribution_or_a_licence_comes_out_escaped(): void {
        global $DB;
        // The name of a framework of the core cannot hold one: the core refuses it.
        $this->getDataGenerator()->get_plugin_generator('tool_vae')->create_source(['fichecode' => 'RS7000', 'fichetype' => 'rs']);
        $DB->set_field('tool_vae_source', 'attribution', '<script>alert(1)</script>', ['fichecode' => 'RS7000']);
        $DB->set_field('tool_vae_source', 'licence', '<script>alert(2)</script>', ['fichecode' => 'RS7000']);

        $html = $this->render(new sources($DB->get_records('tool_vae_source')));

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_only_the_keys_of_html_are_placed_as_they_are(): void {
        $allowed = ['sources' => ['headinghtml', 'tablehtml'], 'provenance' => [], 'source_code' => ['provenancehtml']];
        foreach ($allowed as $name => $keys) {
            $source = file_get_contents(__DIR__ . '/../../templates/' . $name . '.mustache');
            preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
            $this->assertSame($keys, array_values(array_unique($raw[1])), $name);
            $this->assertSame(substr_count($source, '{{{'), count($raw[1]), $name);
        }
    }

    public function test_the_templates_render_their_example_context(): void {
        $expected = ['sources' => 'tool-vae-sources', 'provenance' => 'text-muted', 'source_code' => '<strong>'];
        foreach ($expected as $name => $needle) {
            $file = __DIR__ . '/../../templates/' . $name . '.mustache';
            $this->assertFileExists($file);
            $source = file_get_contents($file);
            $this->assertStringContainsString('@template tool_vae/' . $name, $source);
            $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches), $name);
            $context = json_decode($matches[1]);
            $this->assertIsObject($context, $name . ' ' . json_last_error_msg());

            $this->assertStringContainsString($needle, self::core()->render_from_template('tool_vae/' . $name, $context), $name);
        }
    }
}
