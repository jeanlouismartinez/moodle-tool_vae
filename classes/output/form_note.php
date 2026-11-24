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
 * A sentence a form of the plugin places between its fields: a reminder, a warning, a heading, a memo (lot 7, MMR-520
 * ticket 4, wave 4c).
 *
 * The forms gave it to $mform->addElement('html', ...) as HTML they wrote themselves; they now give what this piece draws,
 * rendered by the renderer of the plugin at the same place. One constructor per sentence: the same reminder stands in
 * several forms (the short one of anonymisation in five), and each is worded here once, with its classes and its id. The
 * id is the form's: the fields it stands against point to it (aria-describedby), so the form keeps it as a constant.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class form_note implements named_templatable, renderable {
    /** @var string The element: div, p or h3. */
    private string $tag;

    /** @var string The sentence, plain text. */
    private string $text;

    /** @var string The classes of the element. */
    private string $class;

    /** @var string|null The id of the element, or null for none. */
    private ?string $id;

    /**
     * A sentence in an element.
     *
     * @param string $tag div, p or h3.
     * @param string $text Plain text, not escaped.
     * @param string $class The classes of the element.
     * @param string|null $id The id of the element, or null.
     */
    private function __construct(string $tag, string $text, string $class, ?string $id) {
        if (!in_array($tag, ['div', 'p', 'h3'], true)) {
            throw new \coding_exception('A note of a form is a div, a p or an h3, not ' . $tag . '.');
        }
        $this->tag = $tag;
        $this->text = $text;
        $this->class = $class;
        $this->id = $id;
    }

    /**
     * The short reminder of anonymisation, small and muted, with no id (certification, evaluation, scope, self-positioning).
     *
     * @return self
     */
    public static function short(): self {
        return new self('div', get_string('anonymiseshort', 'tool_vae'), 'small text-muted mb-2', null);
    }

    /**
     * The short reminder, small and muted, that the fields of the form point to (the details of a situation).
     *
     * @param string $id
     * @return self
     */
    public static function short_with_id(string $id): self {
        return new self('div', get_string('anonymiseshort', 'tool_vae'), 'small text-muted mb-2', $id);
    }

    /**
     * The short reminder, small and muted, with the class of the reminders and an id (an annotation).
     *
     * @param string $id
     * @return self
     */
    public static function short_marked(string $id): self {
        return new self('div', get_string('anonymiseshort', 'tool_vae'), 'small text-muted mb-2 tool-vae-anonymise', $id);
    }

    /**
     * The full reminder of anonymisation in an information box (attestation, declaration, precision, section, situation).
     *
     * @param string $id
     * @return self
     */
    public static function reminder(string $id): self {
        return new self('div', get_string('anonymisereminder', 'tool_vae'), 'alert alert-info tool-vae-anonymise', $id);
    }

    /**
     * The short reminder in an information box, against one free field of the declaration.
     *
     * @param string $id
     * @return self
     */
    public static function short_reminder(string $id): self {
        return new self(
            'div',
            get_string('anonymiseshort', 'tool_vae'),
            'alert alert-info tool-vae-anonymise tool-vae-anonymise-short',
            $id
        );
    }

    /**
     * The reminder of anonymisation of a supporting piece, in an information box (a file, a link).
     *
     * @param string $id
     * @return self
     */
    public static function piece(string $id): self {
        return new self('div', get_string('anonymisepiece', 'tool_vae'), 'alert alert-info tool-vae-anonymise', $id);
    }

    /**
     * The warning that opens the removal of the row of a record.
     *
     * @return self
     */
    public static function removal(): self {
        return new self('div', get_string('removerecordintro', 'tool_vae'), 'alert alert-warning', null);
    }

    /**
     * The warning that opens the withdrawal of an attestation.
     *
     * @return self
     */
    public static function withdrawal(): self {
        return new self('div', get_string('withdrawwarning', 'tool_vae'), 'alert alert-warning', null);
    }

    /**
     * The heading of the competencies a situation is tagged with, in the form of its details.
     *
     * @return self
     */
    public static function tags_heading(): self {
        return new self('h3', get_string('situationtags', 'tool_vae'), 'h5 mt-3', null);
    }

    /**
     * The eight prompts of the Livret 2 above the text of a situation; its text area points to it.
     *
     * @return self
     */
    public static function situation_memo(): self {
        return new self('p', get_string('situationmemo', 'tool_vae'), 'tool-vae-section-help', 'tool_vae_situationmemo');
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/form_note';
    }

    /**
     * The context of the template: which element, its classes, its id, the sentence, plain.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'isdiv' => $this->tag === 'div',
            'isp' => $this->tag === 'p',
            'ish3' => $this->tag === 'h3',
            'class' => $this->class,
            'withid' => $this->id === null ? null : ['value' => $this->id],
            'text' => $this->text,
        ];
    }
}
