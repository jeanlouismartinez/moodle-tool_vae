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
 * The body in HTML of a message of the plugin (email or notification): the sentence, then the link to the dossier (lot 7,
 * MMR-520 ticket 4, wave 4c).
 *
 * Was written by notifier::send() with the writer of HTML of the core. The words are given worded, NOT resolved here: the
 * notifier reads them in the language of the recipient, which is not the language of the session that sends (a cron task,
 * a request of somebody else). Rendered by the renderer of the plugin into the field fullmessagehtml of the message.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class message_body implements named_templatable, renderable {
    /** @var string The sentence, plain text. */
    private string $body;

    /** @var string The address of the dossier, not escaped. */
    private string $url;

    /** @var string The words of the link, plain text. */
    private string $linkname;

    /**
     * The body of a message.
     *
     * @param string $body The sentence, in the language of the recipient, not escaped.
     * @param \moodle_url $url The address of the dossier.
     * @param string $linkname The words of the link, in the language of the recipient, not escaped.
     */
    public function __construct(string $body, \moodle_url $url, string $linkname) {
        $this->body = $body;
        $this->url = $url->out(false);
        $this->linkname = $linkname;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/message_body';
    }

    /**
     * The context of the template: the sentence, the address, the words of the link, plain.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return ['body' => $this->body, 'url' => $this->url, 'linkname' => $this->linkname];
    }
}
