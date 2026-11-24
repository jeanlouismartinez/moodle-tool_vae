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
 * A record hidden under the vaeiste since the page was opened (lot 1a): its message, and the text they had typed, given back.
 *
 * The notice is the information of a hidden record when nothing was sent, the alarm of a send that could not be recorded
 * when something was (even only blanks); the text is given back in a field that can be read and copied when it holds more
 * than blanks. Was selfpos_screen::hidden() (lot 7, MMR-520 ticket 4).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class selfpos_hidden implements named_templatable, renderable {
    /** @var string The message of hidden_frameworks for the record, its title already escaped. */
    private string $message;

    /** @var string What was typed, if anything. */
    private string $text;

    /**
     * The hidden record.
     *
     * @param string $message The message of hidden_frameworks::message() for the record, its title already escaped.
     * @param string $text What was typed, if anything (selfpos_screen::kept_text()).
     */
    public function __construct(string $message, string $text) {
        $this->message = $message;
        $this->text = $text;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/selfpos_hidden';
    }

    /**
     * The context of the template: the notice, and the text kept (not escaped) when it holds more than blanks.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'alertclass' => 'alert alert-' . ($this->text === '' ? 'info' : 'danger'),
            // The message comes from hidden_frameworks::message(), its title already escaped: shown as it is, once.
            'messagehtml' => $this->message,
            'kept' => trim($this->text) !== ''
                ? ['intro' => get_string('selfposhiddenkept', 'tool_vae'), 'text' => $this->text]
                : null,
        ];
    }
}
