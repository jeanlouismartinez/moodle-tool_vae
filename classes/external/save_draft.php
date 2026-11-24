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

namespace tool_vae\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_vae\local\draft_manager;

/**
 * External function tool_vae_save_draft: save the draft in progress of a text being written (T-81, lot 3; ticket 5 of MMR-520).
 *
 * Called by the module tool_vae/autosave, through core/ajax, on a section of a dossier (section.php) and on a work situation
 * (situation.php). It writes the draft in progress and nothing else: a version is only written by the button of the form.
 * The core checks the login and the session key before execute() is reached (the function is declared with loginrequired).
 *
 * WHAT THE PAGE IS ANSWERED. The time of the draft kept and the sentence to say; when another tab has saved since the page
 * learnt its time, nothing is written and the page is told so as an answer (conflict), in the words of the plugin, rather
 * than by an exception: it is not a refusal of a right, and the page goes on. Every other refusal - a draft that is not the
 * writer's, a preparation closed, a record no longer offered, a text too long - stays an exception, which the core hands
 * back to core/ajax as it does for every function.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_draft extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'draftid' => new external_value(PARAM_INT, 'The draft that holds the text'),
            // PARAM_RAW on purpose: the text of a draft is free text, typed in a plain textarea (accents, quotes, angle brackets
            // and line breaks included), and it is kept as typed. It is never printed from here: the
            // forms put it back in their textarea, which escapes it, validation.php prints it through format_text() with
            // FORMAT_PLAIN, and draft_manager normalises its line breaks and bounds its length.
            'text' => new external_value(PARAM_RAW, 'The text being written'),
            // What the page knew of the draft in progress: 0 when it was opened with none, and 0 is compared like any other time
            // (draft_manager::autosave()), so that a draft another tab has written since is not overwritten.
            'workingat' => new external_value(
                PARAM_INT,
                'The time of the draft in progress the page knows, 0 for none',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Save the draft in progress.
     *
     * @param int $draftid The draft that holds the text.
     * @param string $text The text being written.
     * @param int $workingat The time of the draft in progress the page knows, 0 for none.
     * @return array workingat, message and conflict.
     */
    public static function execute(int $draftid, string $text, int $workingat = 0): array {
        global $USER;

        [
            'draftid' => $draftid,
            'text' => $text,
            'workingat' => $workingat,
        ] = self::validate_parameters(self::execute_parameters(), [
            'draftid' => $draftid,
            'text' => $text,
            'workingat' => $workingat,
        ]);

        // The context of the writer: the one the pages that call this function use (section.php, situation.php), and the one
        // in which draft_manager reads tool/vae:draftown. Only the writer may write their draft (draft_manager::can_write()),
        // so the writer's context is the draft's; the right itself is checked by draft_manager, as for every other door.
        self::validate_context(\context_user::instance((int) $USER->id));

        // The page keeps what it knew when nothing is written: the time it sent, read as draft_manager reads it.
        $known = max(0, $workingat);
        try {
            $draft = (new draft_manager())->autosave($draftid, $text, $known);
        } catch (\moodle_exception $e) {
            if ($e->errorcode !== 'errordraftconflict') {
                throw $e;
            }

            return ['workingat' => $known, 'message' => $e->getMessage(), 'conflict' => true];
        }

        if ($draft->workingat === null) {
            // The text is the one of the latest version: no draft is kept, and the page is told so rather than a time.
            return ['workingat' => 0, 'message' => get_string('draftautosavedsame', 'tool_vae'), 'conflict' => false];
        }

        return [
            'workingat' => (int) $draft->workingat,
            'message' => get_string('draftautosaved', 'tool_vae', userdate((int) $draft->workingat)),
            'conflict' => false,
        ];
    }

    /**
     * Describes what the page is answered: what the module tool_vae/autosave reads.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'workingat' => new external_value(PARAM_INT, 'The time of the draft in progress the page must keep, 0 for none'),
            'message' => new external_value(PARAM_TEXT, 'The sentence the page says'),
            'conflict' => new external_value(PARAM_BOOL, 'Whether another tab saved since the page knew: nothing was written'),
        ]);
    }
}
