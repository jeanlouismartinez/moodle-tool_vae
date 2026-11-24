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
 * The button that authorizes a manager, or takes the authorization back, in a cell of booklet_authorize.php (lot 7, MMR-520).
 *
 * A small form that posts the session key, the manager and the action to the page, drawn by the template
 * tool_vae/booklet_authorize_form with the DOM the page wrote before by the HTML writer of the core.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class booklet_authorize_form implements named_templatable, renderable {
    /** @var \moodle_url The address of the page. */
    private \moodle_url $url;

    /** @var int The manager. */
    private int $managerid;

    /** @var bool Whether the authorization stands: the button takes it back, else it authorizes. */
    private bool $stands;

    /**
     * The button.
     *
     * @param \moodle_url $url The address of the page.
     * @param int $managerid
     * @param bool $stands Whether the authorization of this manager stands.
     */
    public function __construct(\moodle_url $url, int $managerid, bool $stands) {
        $this->url = $url;
        $this->managerid = $managerid;
        $this->stands = $stands;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/booklet_authorize_form';
    }

    /**
     * The context of the template: the address unescaped, plain texts.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'url' => $this->url->out(false),
            'sesskey' => sesskey(),
            'managerid' => $this->managerid,
            'verb' => $this->stands ? 'revoke' : 'grant',
            'label' => get_string($this->stands ? 'bookletauthrevoke' : 'bookletauthgrant', 'tool_vae'),
        ];
    }
}
