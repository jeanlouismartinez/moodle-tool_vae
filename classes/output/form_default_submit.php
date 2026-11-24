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
 * The submit button, hidden and out of the order of the keyboard, that a form puts first so that Enter presses it (lot 7,
 * MMR-520 ticket 4, wave 4c).
 *
 * THE FIRST SUBMIT BUTTON OF A FORM IS THE ONE THAT ENTER PRESSES in a field. In the form of the import of records, the
 * other buttons send the form somewhere (one of them makes the server go out onto the Internet): this one does nothing,
 * and the page says so. The form wrote the input itself, with the writer of HTML of the core, outside its elements; it now places
 * what this piece draws, at the same place. Nothing varies.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class form_default_submit implements named_templatable, renderable {
    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/form_default_submit';
    }

    /**
     * The context of the template: nothing varies.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [];
    }
}
