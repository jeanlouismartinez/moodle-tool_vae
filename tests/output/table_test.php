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
 * The piece tool_vae\output\table: an html_table drawn by a template, with the DOM of the core (lot 7, MMR-520 ticket 4).
 *
 * WHAT IS ASSERTED. That, for tables built as the plugin builds them (a caption read by a screen reader only, classes of
 * columns, cells of the core with a header and a scope, a cell over three columns, an empty heading, no row, HTML in a cell,
 * a button of the core in a cell, the default class), the piece draws the same DOM as html_writer::table() of the core;
 * that every property of html_table it does not draw makes it fail in words instead of being ignored; that a text given to
 * a cell through cell_content comes out escaped, a script among them, and so does a value of an attribute; that the context
 * holds no object; that the template renders its example context and places as HTML only the keys named ...html.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(table::class)]
final class table_test extends \advanced_testcase {
    use dom_canon;

    /**
     * A table of the plugin, built as its page or screen builds it, with fixed data.
     *
     * @param string $name
     * @return \html_table
     */
    private static function real(string $name): \html_table {

        $table = new \html_table();
        switch ($name) {
            case 'candidates':
                // As candidates.php: a class, three headings, a link and two numbers per row.
                $table->head = ['Vaeiste', 'Déclarations', 'En attente'];
                $table->attributes['class'] = 'generaltable tool-vae-candidates';
                foreach ([[3, 'Camille Durand', 7, 6], [7, 'Lucie Martin', 5, 0]] as [$id, $name, $all, $pending]) {
                    $table->data[] = [
                        \html_writer::link(new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $id]), s($name)),
                        $all,
                        $pending,
                    ];
                }
                break;
            case 'pending':
                // As pending.php: a caption drawn for a screen reader only.
                $table->attributes['class'] = 'generaltable tool-vae-pending';
                $table->caption = 'Déclarations en attente, par vaeiste';
                $table->captionhide = true;
                $table->head = ['Vaeiste', 'En attente', 'La plus ancienne', 'Jours', 'Responsables'];
                $table->data[] = ['Camille Durand', 6, s('13 octobre 2026'), 37, 3];
                break;
            case 'referents':
                // As referents.php: no class (the default of the core), an empty heading, empty cells, a button.
                $table->head = ['Fiche', 'Bloc', 'Référent', ''];
                $table->data[] = [s('DEMO') . ' — ' . format_string('Démo'), '', 'Aucun référent', ''];
                $table->data[] = [
                    s('DEMOBLOCS') . ' — ' . format_string('Démo blocs'),
                    'bloc BC01',
                    s('Suiveur Essai') . ' (' . s('suiveur.essai') . ')',
                    self::core()->single_button(
                        new \moodle_url('/admin/tool/vae/referents.php', ['remove' => 2]),
                        'Retirer',
                        'post'
                    ),
                ];
                break;
            case 'faisabilite':
                // As faisabilite.php: classes of columns, all empty, and a caption.
                $table->attributes['class'] = 'generaltable tool-vae-faisabilite';
                $table->caption = 'Sections du dossier';
                $table->captionhide = true;
                $table->head = ['Section', 'État', 'Dernière écriture', 'Actions'];
                $table->colclasses = ['', '', '', ''];
                $table->data[] = [s('Identité'), s('À venir'), '', ''];
                $table->data[] = [s('Parcours'), s('Écrite'), s('1 octobre 2026'), \html_writer::link('#', 'Écrire')];
                break;
            case 'followed':
                // As tool_vae\output\organisation_followed: rows of the core whose first cell is the header of its row.
                $table->attributes['class'] = 'generaltable tool-vae-organisation tool-vae-followed';
                $table->caption = 'Vaeistes suivis';
                $table->captionhide = true;
                $table->head = ['Vaeiste', 'Fiches', 'Plans', 'Déclarations', 'En attente'];
                $name = new \html_table_cell(\html_writer::link(
                    new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => 3]),
                    s('Camille Durand')
                ));
                $name->header = true;
                $name->scope = 'row';
                $table->data[] = new \html_table_row([$name, 'RNCP40692', '', '7', '6']);
                break;
            case 'refused':
                // As fiche_preview::line() for a refused record: a header cell, then one cell over three columns, with HTML.
                $table->attributes['class'] = 'generaltable tool-vae-fcimport';
                $table->head = ['Fiche', 'Export', 'Moodle', 'Action'];
                $first = new \html_table_cell(\html_writer::tag('strong', s('RNCP36832')) . \html_writer::div(s('Titre')));
                $first->header = true;
                $first->scope = 'row';
                $reason = new \html_table_cell(\html_writer::div('Pourquoi') . \html_writer::div('Pied', 'fw-bold'));
                $reason->colspan = 3;
                $table->data[] = [$first, $reason];
                break;
            case 'empty':
                // As a page with headings and no row: the core draws an empty body over every column.
                $table->attributes['class'] = 'generaltable tool-vae-candidates';
                $table->head = ['Vaeiste', 'Déclarations', 'En attente'];
                break;
            case 'roles':
                // As roles_install.php: a name followed by a block of HTML in the same cell.
                $table->attributes['class'] = 'generaltable tool-vae-roles';
                $table->head = ['Rôle', 'Droits', 'État'];
                $table->data[] = [
                    s('Vaeiste') . \html_writer::div(s('Prépare son dossier.'), 'small text-muted'),
                    s('Déclarer') . ', ' . s('Exporter'),
                    \html_writer::tag('span', s('Ce rôle existe déjà.'), ['class' => 'small text-muted']),
                ];
                break;
            case 'nohead':
                // A table with rows and no heading, a caption drawn for every reader, an attribute of its own, an identifier.
                $table->caption = 'Essai';
                $table->id = 'tool-vae-essai';
                $table->attributes['data-region'] = 'essai';
                $table->data[] = ['a', 'b'];
                $table->data[] = new \html_table_row(['c', 'd']);
                break;
            case 'plain':
                // A table drawn without the responsive wrapper, a row with an id and a style, a cell with attributes.
                $table->responsive = false;
                $table->head = ['Un', 'Deux'];
                $row = new \html_table_row(['x', 'y']);
                $row->id = 'tool-vae-ligne';
                $row->style = 'color: red;';
                $row->attributes['class'] = 'tool-vae-ligne';
                $cell = new \html_table_cell('z');
                $cell->attributes['class'] = 'tool-vae-cellule';
                $cell->abbr = 'zed';
                $cell->rowspan = 2;
                $cell->style = 'font-weight: bold;';
                $table->data[] = $row;
                $table->data[] = [$cell, '0'];
                break;
            case 'columns':
                // Classes of columns that are not empty, on the headings and on the cells, next to a class of the cell itself;
                // a cell that is the header of its row and says no scope (the core gives it the scope "row").
                $table->head = ['Un', 'Deux'];
                $table->colclasses = ['tool-vae-un', 'tool-vae-deux text-end'];
                $header = new \html_table_cell('Titre de ligne');
                $header->header = true;
                $own = new \html_table_cell('x');
                $own->attributes['class'] = 'tool-vae-cellule';
                $table->data[] = [$header, $own];
                $table->data[] = new \html_table_row(['y', 'z']);
                break;
            case 'bare':
                // No heading and no row: only the frame.
                break;
        }

        return $table;
    }

    /**
     * The tables of the plugin the piece is compared on.
     *
     * @return array
     */
    public static function tables(): array {
        $names = ['candidates', 'pending', 'referents', 'faisabilite', 'followed', 'refused', 'empty', 'roles', 'nohead',
            'plain', 'columns', 'bare'];

        return array_combine($names, array_map(fn($name) => [$name], $names));
    }

    /**
     * The table drawn by the renderer of the plugin.
     *
     * @param \html_table $table
     * @return string HTML.
     */
    private function render(\html_table $table): string {
        global $PAGE;

        return $PAGE->get_renderer('tool_vae', null, RENDERER_TARGET_GENERAL)->render(new table($table));
    }

    public function test_the_piece_is_a_named_template(): void {
        global $PAGE;

        $piece = new table(new \html_table());

        $this->assertInstanceOf(\core\output\renderable::class, $piece);
        $this->assertInstanceOf(\core\output\named_templatable::class, $piece);
        $this->assertSame('tool_vae/table', $piece->get_template_name($PAGE->get_renderer('tool_vae')));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tables')]
    public function test_the_piece_draws_the_dom_of_the_core(string $name): void {
        $this->resetAfterTest();

        // Each side gets its own table: the writer of the core changes the table it draws.
        $expected = \html_writer::table(self::real($name));
        $html = $this->render(self::real($name));

        $this->assertSame(self::canon($expected), self::canon($html), $name);
    }

    /**
     * The properties of html_table the piece does not draw, each with a value that the core would draw.
     *
     * @return array
     */
    public static function unsupported(): array {
        return [
            'align' => ['align', ['left'], 'align'],
            'size' => ['size', ['10%'], 'size'],
            'wrap' => ['wrap', [true], 'wrap'],
            'headspan' => ['headspan', [2], 'headspan'],
            'rowclasses' => ['rowclasses', ['odd'], 'rowclasses'],
            'width' => ['width', '100%', 'width'],
            'tablealign' => ['tablealign', 'center', 'tablealign'],
            'cellpadding' => ['cellpadding', '2', 'cellpadding'],
            'cellspacing' => ['cellspacing', '2', 'cellspacing'],
            'summary' => ['summary', 'Résumé', 'summary'],
            'class' => ['class', 'tool-vae-x', 'class'],
            'a heading cell' => ['head', [new \html_table_cell('Un')], 'head'],
            'a divider row' => ['data', ['hr'], 'hr'],
            'a row of another kind' => ['data', ['ligne'], 'row'],
            'an object in a cell' => ['data', [[new \stdClass()]], 'cell'],
            'an array in an attribute' => ['attributes', ['class' => ['a']], 'attribute'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsupported')]
    public function test_a_property_it_does_not_draw_fails_in_words(string $property, $value, string $word): void {
        $table = new \html_table();
        $table->head = ['Un'];
        $table->$property = $value;

        try {
            new table($table);
            $this->fail('No exception for ' . $property);
        } catch (\coding_exception $e) {
            $this->assertStringContainsString($word, $e->getMessage());
            $this->assertStringContainsString('tool_vae\output\table', $e->getMessage());
        }
    }

    public function test_a_text_given_through_cell_content_comes_out_escaped(): void {
        $this->resetAfterTest();
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable" onclick="x';
        $table->head = ['Nom'];
        $table->data[] = [new cell_content([cell_content::text('<script>alert(1)</script>')])];

        $html = $this->render($table);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('class="generaltable&quot; onclick=&quot;x"', $html);
    }

    public function test_the_context_holds_no_object(): void {
        global $PAGE;
        $this->resetAfterTest();
        $table = self::real('pending');

        $context = (new table($table))->export_for_template($PAGE->get_renderer('tool_vae'));

        $this->assertTrue($context['responsive']);
        $this->assertSame([['name' => 'class', 'value' => 'generaltable tool-vae-pending']], $context['attributes']);
        $this->assertSame([
            'attributes' => [['name' => 'class', 'value' => 'visually-hidden']],
            'texthtml' => 'Déclarations en attente, par vaeiste',
        ], $context['caption']);
        $this->assertTrue($context['hashead']);
        $this->assertCount(5, $context['head']);
        $this->assertSame([
            'isth' => true,
            'attributes' => [
                ['name' => 'class', 'value' => 'header c0'],
                ['name' => 'style', 'value' => ''],
                ['name' => 'scope', 'value' => 'col'],
            ],
            'texthtml' => 'Vaeiste',
        ], $context['head'][0]);
        $this->assertFalse($context['emptybody']);
        $this->assertTrue($context['hasbody']);
        $this->assertSame([['name' => 'class', 'value' => 'lastrow']], $context['rows'][0]['attributes']);
        $this->assertSame('6', $context['rows'][0]['cells'][1]['texthtml']);
        array_walk_recursive($context, fn($value) => $this->assertFalse(is_object($value)));
    }

    public function test_the_template_renders_its_example_context(): void {
        $this->resetAfterTest();
        $file = __DIR__ . '/../../templates/table.mustache';
        $this->assertFileExists($file);
        $source = file_get_contents($file);
        $this->assertStringContainsString('@template tool_vae/table', $source);
        preg_match_all('/\{\{\{\s*([a-z]+)\s*\}\}\}/', $source, $raw);
        $this->assertSame(['texthtml'], array_values(array_unique($raw[1])));
        $this->assertSame(substr_count($source, '{{{'), count($raw[1]));
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1]);
        $this->assertIsObject($context, json_last_error_msg());

        $html = self::core()->render_from_template('tool_vae/table', $context);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('class="table-responsive"', $html);
        $this->assertStringContainsString('scope="col"', $html);
    }
}
