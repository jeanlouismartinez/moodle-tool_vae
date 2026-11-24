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
 * A second click on the same button must not send twice: the button is disabled as the form goes.
 *
 * The button is disabled just after the submission has left (a timeout of 0), so that the request still carries it.
 *
 * @module     tool_vae/submit_once
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Disable the submit button of each form the selector finds, once that form is submitted.
 *
 * @param {String} selector The CSS selector of the forms.
 */
export const init = (selector) => {
    document.querySelectorAll(selector).forEach(function(f) {
        f.addEventListener("submit", function() {
            var b = f.querySelector("input[type=submit]");
            if (b) {
                setTimeout(function() {
                    b.disabled = true;
                }, 0);
            }
        });
    });
};
