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

namespace tool_vae\local;

/**
 * The readings of the work situations of the vaeiste (lot 1b) that the pages and the pieces of classes/output/ use: the text
 * kept from a send, the autosave of the text, the title of a situation, a period in words, the addresses of the pages, a
 * node in words. Every one of them gives plain text or data; none writes HTML.
 *
 * The HTML of the screens - the records, the list of a record, one situation, the inverse view of a node, the pieces and
 * their forms, the sentences of every screen - is drawn by the pages situations_page and situation_page and the pieces
 * situation_disclaimer, situation_facts, situation_pieces and situation_piece_forms of classes/output/ (lot 7, MMR-520
 * ticket 4, wave 4a). Every free text there is escaped once by the templates, or goes through format_text() as plain text.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class situation_screen {
    /**
     * The text the vaeiste had typed when the preparation was closed under them: read from THEIR send only.
     *
     * A text that arrives in the address, or in a send without the session key, is nothing (the rule of
     * selfpos_screen::kept_text()).
     *
     * @return string[] Field => what was typed in it (title, setting, content), only the fields that hold something; empty
     *         unless a form was sent with a valid session key.
     */
    public static function kept_text(): array {
        $sesskey = (string) optional_param('sesskey', '', PARAM_ALPHANUM);
        if (!data_submitted() || $sesskey === '' || !confirm_sesskey($sesskey)) {
            return [];
        }
        $kept = [];
        foreach (['title', 'setting', 'content'] as $field) {
            // Free text, given back to the same form: never written without the session key, escaped by the form.
            $value = optional_param($field, '', PARAM_RAW);
            if (is_string($value) && trim($value) !== '') {
                $kept[$field] = $value;
            }
        }

        return $kept;
    }

    /**
     * The autosave of the text: the module tool_vae/autosave, on the field `content`, to the external function
     * tool_vae_save_draft by draftid.
     *
     * WHAT THE SCRIPT KEEPS TRUE is said in section.php: the form carries the time of the draft the page knows, the script
     * writes there each time the autosave is answered, and it stops at the submission of the form - here at every submission,
     * since no button of this form opens a new tab.
     *
     * @param int $draftid The draft that holds the text.
     * @param int $workingat The time of the draft in progress the page was drawn with, 0 for none.
     */
    public static function autosave(int $draftid, int $workingat): void {
        global $PAGE;

        $PAGE->requires->js_call_amd('tool_vae/autosave', 'init', [
            self::autosave_config($draftid, $workingat, 'id_content', false),
        ]);
    }

    /**
     * What the module tool_vae/autosave is handed, for a work situation and for a section of a dossier (section.php).
     *
     * No address and no session key: the module calls the external function tool_vae_save_draft through core/ajax, which
     * knows the address of the core's service and adds the session key itself (ticket 5 of MMR-520).
     *
     * @param int $draftid The draft that holds the text.
     * @param int $workingat The time of the draft in progress the page was drawn with, 0 for none.
     * @param string $areaid The id of the field of the text: id_content here, id_text on a section.
     * @param bool $sparenewtab Whether a submission to a new tab (the button of the aid, on a section) leaves the autosave going.
     * @return array The draft, its time, the message of a failure, the field and whether a new tab is spared.
     */
    public static function autosave_config(int $draftid, int $workingat, string $areaid, bool $sparenewtab): array {
        return [
            'draftid' => $draftid,
            'workingat' => $workingat,
            'failed' => get_string('draftautosaveerror', 'tool_vae'),
            'areaid' => $areaid,
            'sparenewtab' => $sparenewtab,
        ];
    }

    /**
     * The title of a situation as it is read: its own, or "Work situation n" while it has none.
     *
     * @param \stdClass $situation A row of tool_vae_situation.
     * @return string Plain text: the caller escapes it.
     */
    public static function title(\stdClass $situation): string {
        $title = trim((string) $situation->title);

        return $title !== '' ? $title : get_string('validpart:situation', 'tool_vae', (int) $situation->slot);
    }

    /**
     * A period in words: "start - end", a bound that is not given left out.
     *
     * @param int|null $start
     * @param int|null $end
     * @return string Plain text, '' when neither bound is given.
     */
    public static function period(?int $start, ?int $end): string {
        $format = get_string('strftimedate', 'langconfig');
        $bounds = [];
        foreach ([$start, $end] as $bound) {
            if ($bound !== null) {
                $bounds[] = display_date::format($bound, $format);
            }
        }

        return implode(' - ', $bounds);
    }

    /**
     * The address of the pages: the records, the list of a record, the inverse view of a node, or one situation.
     *
     * @param int $candidateid
     * @param string $fichecode Empty for the list of records.
     * @param int|null $slot A situation; null for a list.
     * @param string|null $rank A node, for the inverse view; ignored with a slot.
     * @return \moodle_url
     */
    public static function url(int $candidateid, string $fichecode = '', ?int $slot = null, ?string $rank = null): \moodle_url {
        $params = ['userid' => $candidateid];
        if ($fichecode !== '') {
            $params['fiche'] = $fichecode;
        }
        if ($slot !== null) {
            $params['slot'] = $slot;

            return new \moodle_url('/admin/tool/vae/situation.php', $params);
        }
        if ($rank !== null) {
            $params['rank'] = $rank;
        }

        return new \moodle_url('/admin/tool/vae/situations.php', $params);
    }

    /**
     * A node in words: its rank and its label, or the rank alone when the record no longer holds it.
     *
     * @param \stdClass|null $tree
     * @param string $rank
     * @return string Plain text.
     */
    public static function node_words(?\stdClass $tree, string $rank): string {
        $node = $tree === null ? null : selfpos_tree::node($tree, $rank);
        if ($node === null) {
            return $rank;
        }

        return $rank . ' - ' . format_string($node->label, true, ['context' => \context_system::instance(), 'escape' => false]);
    }
}
