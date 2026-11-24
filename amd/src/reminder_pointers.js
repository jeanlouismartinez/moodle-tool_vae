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

/**
 * Keeps the anonymisation reminders attached to their fields (T-72).
 *
 * The core templates write aria-describedby="id_error_<field>" for a field in error before the attributes a form gives the
 * element, and a browser keeps the first. The reminder ids are therefore merged into the attribute once the page is built,
 * after whatever the core wrote: the error is read first, then the reminder. Nothing is removed, an id already there is not
 * added again, and an id the page does not have is not added.
 *
 * @module     tool_vae/reminder_pointers
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Merge the reminder ids into the attribute aria-describedby of each field.
 *
 * @param {Object} map Element id => list of ids the element must be described by.
 */
export const init = (map) => {
    Object.keys(map).forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        var ids = (el.getAttribute("aria-describedby") || "").split(/\s+/).filter(Boolean);
        map[id].forEach(function(t) {
            if (ids.indexOf(t) < 0 && document.getElementById(t)) {
                ids.push(t);
            }
        });
        el.setAttribute("aria-describedby", ids.join(" "));
    });
};
