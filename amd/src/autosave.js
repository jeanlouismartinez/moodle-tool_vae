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
 * The autosave of a text being written: every twenty seconds, when the text has changed, to the external function
 * tool_vae_save_draft, called through core/ajax (which adds the session key).
 *
 * Used by a section of the feasibility or validation dossier (section.php, field id_text) and by a work situation
 * (situation.php, field id_content). The form carries the time of the draft the page knows (the field workingat): the module
 * writes there each time the autosave is answered, or the writer's own autosave would look like another tab to the button that
 * saves a version. It stops at the submission of the form, except, when the page asks for it, a submission to a new tab (the
 * button of the aid), which leaves this page going on. Without fetch, or without the field or the live region, nothing starts:
 * the button that saves a version remains.
 *
 * WHAT IS SAID. The sentence of the answer; when another tab has saved since (conflict), that sentence and nothing is taken as
 * saved; when the function refuses (an exception of the plugin: a draft closed, a right missing), the sentence of the refusal;
 * when no answer comes, the message of a failure. Every failure is handled here, so the page never shows a dialog of the core
 * while someone writes: core/ajax of Moodle 5.3 rejects the promise and leaves the handling to the caller. One case is the
 * core's own: a session that has expired (servicerequireslogin) is sent by core/ajax to the login page.
 *
 * @module     tool_vae/autosave
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Start the autosave of the field the page names.
 *
 * @param {Object} cfg What the page hands over.
 * @param {Number} cfg.draftid The draft that holds the text.
 * @param {Number} cfg.workingat The time of the draft in progress the page was drawn with, 0 for none.
 * @param {String} cfg.failed The message said when the autosave could not be answered.
 * @param {String} cfg.areaid The id of the field of the text.
 * @param {Boolean} cfg.sparenewtab Whether a submission to a new tab leaves the autosave going on.
 */
export const init = (cfg) => {
    var area = document.getElementById(cfg.areaid);
    var live = document.getElementById("tool-vae-autosave");
    if (!area || !live || !window.fetch) {
        return;
    }
    var form = area.form;
    var field = form ? form.elements.workingat : null;
    var last = area.value;
    var known = cfg.workingat;
    var submitting = false;
    var timer = null;

    /**
     * Say a message in the live region.
     *
     * @param {String} t The message.
     */
    function say(t) {
        live.textContent = t;
    }

    /**
     * Send the text when it has changed and no submission of the form is under way.
     */
    function tick() {
        if (submitting || area.value === last) {
            return;
        }
        var sent = area.value;
        Ajax.call([{
            methodname: "tool_vae_save_draft",
            args: {draftid: cfg.draftid, text: sent, workingat: known},
        }])[0]
            .then(function(j) {
                if (submitting) {
                    return;
                }
                if (j.conflict) {
                    // Another tab saved since: nothing was written, the text stays to send and the time the page knew stays.
                    say(j.message);
                    return;
                }
                last = sent;
                known = j.workingat;
                if (field) {
                    field.value = known;
                }
                say(j.message);
                // Nothing to hand on: the chain ends here (promise/always-return).
                return;
            })
            .catch(function(e) {
                if (submitting) {
                    return;
                }
                // An exception of Moodle carries its error code and its sentence; anything else (no answer, a page of error)
                // is said by the message of a failure.
                say(e && e.errorcode && e.message ? e.message : cfg.failed);
            });
    }

    /**
     * Start the clock of the autosave.
     */
    function start() {
        timer = setInterval(tick, 20000);
    }

    start();
    if (form) {
        form.addEventListener("submit", function(e) {
            if (cfg.sparenewtab && e.submitter && e.submitter.formTarget === "_blank") {
                return;
            }
            submitting = true;
            clearInterval(timer);
        });
    }
    window.addEventListener("pageshow", function(e) {
        if (e.persisted && submitting) {
            submitting = false;
            start();
        }
    });
};
