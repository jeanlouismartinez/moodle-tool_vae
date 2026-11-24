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

use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;

/**
 * A link a form of the plugin places among its fields, alone or in a paragraph (lot 7, MMR-520 ticket 4, wave 4c).
 *
 * Three forms wrote it with the writer of HTML of the core: the way back to the list of numbers of the import of records (in a
 * paragraph, fiche_confirm_form), the way to the profile of the account (the text of a static element, fields_form), and
 * the source of the export of the records (given to the sentence of the language file that holds it, fiche_import_form).
 * The words are given by the form: the last one is the name of a site, not a sentence.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class form_link implements named_templatable, renderable {
    /** @var string The address, not escaped. */
    private string $url;

    /** @var string The words of the link, plain text. */
    private string $text;

    /** @var bool Whether the link stands in a paragraph of its own. */
    private bool $paragraph;

    /**
     * A link.
     *
     * @param \moodle_url|string $url The address.
     * @param string $text Plain text, not escaped.
     * @param bool $paragraph Whether the link stands in a paragraph of its own.
     */
    public function __construct($url, string $text, bool $paragraph = false) {
        $this->url = $url instanceof \moodle_url ? $url->out(false) : (string) $url;
        $this->text = $text;
        $this->paragraph = $paragraph;
    }

    /**
     * The way back to the list of numbers, under the confirmation of an import of records.
     *
     * @param string $token The plan of the import the list belongs to.
     * @return self
     */
    public static function redo(string $token): self {
        return new self(
            new \moodle_url('/admin/tool/vae/import_fiches.php', ['redo' => $token]),
            get_string('fcimportedit', 'tool_vae'),
            true
        );
    }

    /**
     * The way to the profile of the account, under the fields of the dossier de faisabilité that the account fills.
     *
     * @param int $userid The account of the person who writes.
     * @return self
     */
    public static function profile(int $userid): self {
        return new self(new \moodle_url('/user/edit.php', ['id' => $userid]), get_string('faisabeditprofile', 'tool_vae'));
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/form_link';
    }

    /**
     * The context of the template: the address, the words, and whether a paragraph holds them.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return ['url' => $this->url, 'text' => $this->text, 'paragraph' => $this->paragraph];
    }
}
