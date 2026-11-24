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
 * What the screens of the self-positioning of the vaeiste (lot 1a) read: the text kept on a hidden record, a level in words,
 * the address of the page.
 *
 * NO HTML HERE ANY MORE (lot 7, MMR-520 ticket 4): the records, the tree of a record, one node and a hidden record are drawn by
 * the pieces tool_vae\output\selfpos_records, selfpos_record_tree, selfpos_node, selfpos_hidden and selfpos_disclaimer and
 * their templates. NO TOTAL, NO RATE, NO COLOUR OF RANKING: a level and a date on each node, the history on request, the word
 * of the structure next to the vaeiste's own without any gap.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class selfpos_screen {
    /**
     * The justification the vaeiste had typed when their record was hidden under them: read from THEIR send only.
     *
     * A text that arrives in the address, or in a send without the session key, is nothing: otherwise anyone could make
     * a link that shows a text of their choice on the page (the rule hidden_frameworks::sent() follows for the declaration).
     *
     * @return string '' unless a form was sent with a valid session key.
     */
    public static function kept_text(): string {
        $sesskey = (string) optional_param('sesskey', '', PARAM_ALPHANUM);
        if (!data_submitted() || $sesskey === '' || !confirm_sesskey($sesskey)) {
            return '';
        }

        // Free text, given back to the same form: never written without the session key, escaped by the form.
        return (string) optional_param('justification', '', PARAM_RAW);
    }

    /**
     * The vaeiste's current level on a node, in words: the label and the date, or that there is none.
     *
     * @param \stdClass|null $latest The line that stands, if any.
     * @return string HTML: the words of level_text(), escaped.
     */
    public static function level_words(?\stdClass $latest): string {
        return s(self::level_text($latest));
    }

    /**
     * The vaeiste's current level on a node, in words, as plain text (not escaped): for a template, which escapes it.
     *
     * @param \stdClass|null $latest The line that stands, if any.
     * @return string
     */
    public static function level_text(?\stdClass $latest): string {
        if (!selfpos_manager::standing($latest)) {
            return get_string('selfposnone', 'tool_vae');
        }

        return get_string('selfposcurrent', 'tool_vae', (object) [
            'label' => $latest->levellabel,
            'when' => userdate((int) $latest->timecreated, get_string('strftimedate', 'langconfig')),
        ]);
    }

    /**
     * The address of the page: the records, the tree of a record, or one node.
     *
     * @param int $candidateid
     * @param string $fichecode Empty for the list of records.
     * @param string|null $rank Null for the tree; '' is the record itself.
     * @return \moodle_url
     */
    public static function url(int $candidateid, string $fichecode = '', ?string $rank = null): \moodle_url {
        $params = ['userid' => $candidateid];
        if ($fichecode !== '') {
            $params['fiche'] = $fichecode;
        }
        if ($rank !== null) {
            $params['rank'] = $rank;
        }

        return new \moodle_url('/admin/tool/vae/selfposition.php', $params);
    }
}
