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
use tool_vae\local\correspondence_manager;

/**
 * The form that decides the equivalent of one competency, or takes the decision back, in a cell of passage.php (lot 7).
 *
 * The parameters of the page and the session key, hidden; the list of the competencies of the record that replaces, its
 * label hidden to the eye and read by a screen reader (the same words on every row would not say which competency they are
 * about); the note, its label hidden the same way; the button that decides and, once a decision stands, the one that takes
 * it back. Drawn by the template tool_vae/correspondence_decision_form with the DOM the page wrote before by the HTML writer of the
 * core.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class correspondence_decision_form implements named_templatable, renderable {
    /** @var \moodle_url The address of the page, with its parameters. */
    private \moodle_url $self;

    /** @var int The competency of the replaced record. */
    private int $fromid;

    /** @var string Its code, which names the fields for a screen reader. */
    private string $code;

    /** @var array[] The choices, each {value, text, labelhtml}. */
    private array $options;

    /** @var string The value chosen. */
    private string $selected;

    /** @var bool Whether a decision stands, which can be taken back. */
    private bool $undo;

    /**
     * The form.
     *
     * @param \moodle_url $self The address of the page, with its parameters.
     * @param int $fromid The competency of the replaced record.
     * @param string $code Its code, as text.
     * @param array[] $options The choices, in order, each ['value' => string, 'text' => string (plain text, drawn first),
     *     'labelhtml' => string (the label of a competency as format_string() gives it, or '')].
     * @param string $selected The value chosen ('' for no equivalent).
     * @param bool $undo Whether a decision stands.
     */
    public function __construct(\moodle_url $self, int $fromid, string $code, array $options, string $selected, bool $undo) {
        $this->self = $self;
        $this->fromid = $fromid;
        $this->code = $code;
        $this->options = $options;
        $this->selected = $selected;
        $this->undo = $undo;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/correspondence_decision_form';
    }

    /**
     * The context of the template: addresses unescaped, plain texts; HTML only in labelhtml (format_string()).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $params = [];
        foreach ($this->self->params() as $name => $value) {
            $params[] = ['name' => (string) $name, 'value' => (string) $value];
        }
        $options = [];
        foreach ($this->options as $option) {
            $options[] = [
                'value' => (string) $option['value'],
                'text' => (string) $option['text'],
                'labelhtml' => (string) $option['labelhtml'],
                'selected' => (string) $option['value'] === $this->selected,
            ];
        }

        return [
            'url' => $this->self->out_omit_querystring(),
            'params' => $params,
            'sesskey' => sesskey(),
            'fromid' => $this->fromid,
            'tolabel' => get_string('passagecoldecide', 'tool_vae') . ' ' . $this->code,
            'options' => $options,
            'notelabel' => get_string('passagenote', 'tool_vae') . ' ' . $this->code,
            'maxlength' => correspondence_manager::NOTE_MAXLENGTH,
            'placeholder' => get_string('passagenote', 'tool_vae'),
            'decide' => get_string('passagedecide', 'tool_vae'),
            'undo' => $this->undo,
            'undolabel' => get_string('passageundo', 'tool_vae'),
        ];
    }
}
