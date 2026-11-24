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

/**
 * The renderer of the plugin (lot 7, MMR-520 ticket 4): a page gets it by $PAGE->get_renderer('tool_vae').
 *
 * It holds no method of its own: plugin_renderer_base::render() draws every renderable of tool_vae\output that is a
 * named_templatable by its template, and a theme may replace that template, or this renderer, as for any plugin of the core.
 * A method render_<name>() is added here only for a screen that a template cannot draw alone, and says why.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \core\output\plugin_renderer_base {
}
