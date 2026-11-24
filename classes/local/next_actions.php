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
 * What the reader can do now: the actions of the banner "Ce que je peux faire maintenant" (lot S2).
 *
 * WHAT IS POSSIBLE, NEVER A PROGRESS. Each action says that something can be done now, with a link: no rate, no "N of M", no
 * step "done", no verdict. An action is offered only when the reader holds the right to do it - the predicates of the plugin
 * are called, never copied - AND its rule, read from the tables, is true.
 *
 * READS ONLY. Nothing is written: no row, no setting, no session.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class next_actions {
    /** @var string Declare an activity: the vaeiste has no declaration yet. */
    public const DECLARE = 'declare';

    /** @var string Answer the requests for precision still without an answer. */
    public const ANSWER = 'answer';

    /** @var string Resume the text in progress of the feasibility dossier. */
    public const DRAFT = 'draft';

    /** @var string Position oneself: no level of self-positioning stands. */
    public const SELFPOSITION = 'selfposition';

    /** @var string The declarations of the followed vaeistes waiting for an attestation. */
    public const ATTEST = 'attest';

    /** @var string The answers to the requests the reader asked, on declarations not yet attested. */
    public const ANSWERS = 'answers';

    /** @var string The latest feasibility deposits of the followed vaeistes without any opinion. */
    public const OPINIONS = 'opinions';

    /**
     * The actions the vaeiste can take now, on their own dossier, in the order declare, answer, draft, selfposition.
     *
     * Read for the reader alone: for anybody else than the current user, nothing is offered.
     *
     * @param int $userid The vaeiste, who must be the current user.
     * @return \stdClass[] Objects {key: string, count: int, url: \moodle_url}; count is 0 but for ANSWER.
     */
    public static function for_vaeiste(int $userid): array {
        global $DB, $USER;

        if (!isloggedin() || isguestuser() || (int) $USER->id !== $userid) {
            return [];
        }
        $context = \context_user::instance($userid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }

        $actions = [];

        if (
            has_capability('tool/vae:declare', $context)
            && competency_gateway::can_view_competencies(\context_system::instance())
            && !$DB->record_exists('tool_vae_evidence', ['candidateid' => $userid])
        ) {
            $actions[] = self::action(self::DECLARE, 0, new \moodle_url('/admin/tool/vae/declare.php'));
        }

        if (request_manager::can_answer($userid)) {
            // One open request at a time on a declaration: one row per declaration waiting for an answer.
            $evidenceids = $DB->get_fieldset_sql(
                'SELECT r.evidenceid
                   FROM {tool_vae_request} r
                   JOIN {tool_vae_evidence} e ON e.id = r.evidenceid
                  WHERE e.candidateid = :candidateid AND r.answer IS NULL
               ORDER BY r.id',
                ['candidateid' => $userid]
            );
            $count = count($evidenceids);
            if ($count === 1) {
                $actions[] = self::action(self::ANSWER, 1, new \moodle_url(
                    '/admin/tool/vae/precision.php',
                    ['evidenceid' => (int) reset($evidenceids)]
                ));
            } else if ($count > 1) {
                $actions[] = self::action(self::ANSWER, $count, new \moodle_url(
                    '/admin/tool/vae/dossier.php',
                    ['userid' => $userid]
                ));
            }
        }

        if (draft_manager::can_write($userid)) {
            // A draft is a working text not yet a version: save_working() sets it back to NULL once it equals the version.
            $select = 'candidateid = :candidateid AND doctype = :doctype AND working IS NOT NULL AND '
                . $DB->sql_isnotempty('tool_vae_draft', 'working', true, true);
            $params = ['candidateid' => $userid, 'doctype' => draft_manager::DOCTYPE_FAISABILITE];
            if ($DB->record_exists_select('tool_vae_draft', $select, $params)) {
                $actions[] = self::action(self::DRAFT, 0, new \moodle_url('/admin/tool/vae/faisabilite.php'));
            }
        }

        if (selfpos_manager::can_write($userid) && !self::stands($userid)) {
            $actions[] = self::action(self::SELFPOSITION, 0, selfpos_screen::url($userid));
        }

        return $actions;
    }

    /**
     * The actions the manager can take now on the vaeistes they follow, in the order attest, answers, opinions.
     *
     * Only the lines whose number is above zero are given. No line names a vaeiste: each carries a number, and a link to the
     * object waiting (the list of the followed for the declarations, the earliest answered request, the earliest deposit
     * without opinion).
     *
     * One query per line, never one per vaeiste. The rights are asked of the plugin's predicates, one vaeiste at a time
     * (access::can_attest(), access::can_view(), opinion_manager::can_write()): new checks, since the list of the followed
     * only says follows(); they are made on contexts already loaded, and read nothing from the database once these are in
     * the cache. The list is not trusted: a vaeiste the reader may not read gives no line, whoever hands the list over.
     *
     * @param int[] $candidateids The vaeistes the reader follows, as organisation::followed() has read them.
     * @return \stdClass[] Objects {key: string, count: int, url: \moodle_url}, count above zero.
     */
    public static function for_manager(array $candidateids): array {
        global $DB, $USER;

        if (!isloggedin() || isguestuser()) {
            return [];
        }
        $readerid = (int) $USER->id;
        // Nobody attests, reads the answers on, or gives an opinion on their own dossier.
        $candidateids = array_values(array_filter(
            array_unique(array_map('intval', $candidateids)),
            fn(int $id): bool => $id !== $readerid
        ));
        if (!$candidateids) {
            return [];
        }

        $actions = [];

        $attestable = array_values(array_filter($candidateids, fn(int $id): bool => access::can_attest($id)));
        $pending = 0;
        foreach (candidate_overview::counts($attestable) as $count) {
            $pending += $count->pending;
        }
        if ($pending > 0) {
            $actions[] = self::action(self::ATTEST, $pending, new \moodle_url('/admin/tool/vae/candidates.php'));
        }

        // The answers to what the reader asked, while the declaration is not attested: no mark "read" exists, the line goes
        // away by itself with the attestation. An answer is any value but NULL. The right is the one of the page the line
        // opens (precision.php): to read the dossier; nobody is told what a vaeiste they may not read has answered, whatever
        // list is handed over.
        $readable = array_values(array_filter($candidateids, fn(int $id): bool => access::can_view($id)));
        $requests = [];
        if ($readable) {
            [$insql, $params] = $DB->get_in_or_equal($readable, SQL_PARAMS_NAMED, 'na');
            $params['authorid'] = $readerid;
            $requests = $DB->get_records_sql(
                "SELECT r.id, r.evidenceid
                   FROM {tool_vae_request} r
                   JOIN {tool_vae_evidence} e ON e.id = r.evidenceid
                  WHERE r.authorid = :authorid
                    AND r.answer IS NOT NULL
                    AND e.candidateid $insql
                    AND NOT EXISTS (
                            SELECT 1
                              FROM {tool_vae_attest} a
                             WHERE a.evidenceid = e.id
                               AND a.withdrawnat IS NULL
                        )
               ORDER BY r.timecreated ASC, r.id ASC",
                $params
            );
        }
        if ($requests) {
            $first = reset($requests);
            $actions[] = self::action(self::ANSWERS, count($requests), new \moodle_url(
                '/admin/tool/vae/precision.php',
                ['evidenceid' => (int) $first->evidenceid]
            ));
        }

        // The latest feasibility deposit of each vaeiste, without any opinion of anybody: an older deposit does not count,
        // and an opinion is only written on a feasibility deposit.
        $writable = array_values(array_filter($candidateids, fn(int $id): bool => opinion_manager::can_write($id)));
        if ($writable) {
            [$insql, $params] = $DB->get_in_or_equal($writable, SQL_PARAMS_NAMED, 'no');
            $params['doctype'] = draft_manager::DOCTYPE_FAISABILITE;
            $deposits = $DB->get_records_sql(
                "SELECT d.id, d.candidateid
                   FROM {tool_vae_deposit} d
                  WHERE d.candidateid $insql
                    AND d.doctype = :doctype
                    AND d.number = (
                            SELECT MAX(l.number)
                              FROM {tool_vae_deposit} l
                             WHERE l.candidateid = d.candidateid
                               AND l.doctype = d.doctype
                        )
                    AND NOT EXISTS (
                            SELECT 1
                              FROM {tool_vae_opinion} o
                             WHERE o.depositid = d.id
                        )
               ORDER BY d.timecreated ASC, d.id ASC",
                $params
            );
            if ($deposits) {
                $first = reset($deposits);
                $actions[] = self::action(self::OPINIONS, count($deposits), new \moodle_url(
                    '/admin/tool/vae/deposit.php',
                    ['userid' => (int) $first->candidateid]
                ));
            }
        }

        return $actions;
    }

    /**
     * Whether some level of self-positioning of the vaeiste stands, on any node of any record.
     *
     * The latest line of each (record, rank) stands, in the order positioning_rules::latest() reads them; a withdrawal
     * (level 0) is a line too, and gives no level.
     *
     * @param int $userid
     * @return bool
     */
    private static function stands(int $userid): bool {
        global $DB;

        $rows = $DB->get_records_sql(
            'SELECT id, fichecode, ficherang, level
               FROM {' . selfpos_manager::TABLE . '}
              WHERE candidateid = :candidateid
           ORDER BY timecreated ASC, id ASC',
            ['candidateid' => $userid]
        );
        $latest = [];
        foreach ($rows as $row) {
            $latest[$row->fichecode . '|' . $row->ficherang] = $row;
        }
        foreach ($latest as $row) {
            if (selfpos_manager::standing($row)) {
                return true;
            }
        }

        return false;
    }

    /**
     * One action.
     *
     * @param string $key
     * @param int $count
     * @param \moodle_url $url
     * @return \stdClass
     */
    private static function action(string $key, int $count, \moodle_url $url): \stdClass {
        return (object) ['key' => $key, 'count' => $count, 'url' => $url];
    }
}
