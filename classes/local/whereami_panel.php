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
 * The readings of the panel "where am I" (lot 1b, task 7; spec 1a § 6.6): the tree of a record in reading, every node with the
 * vaeiste's own level and how many situations and declarations carry it.
 *
 * COUNTS OF ITS OWN, NOTHING ADDED UP. Each node says what was written on IT: a parent never gets the situations or the
 * declarations of its children, and no total, no rate, no colour of ranking is drawn. A rank that carries something but has
 * left the record is listed after the tree, by its rank, never folded into a parent.
 *
 * NO HTML HERE (lot 7, MMR-520 ticket 4): the panel is drawn by the pieces tool_vae\output\whereami_tree and
 * tool_vae\output\tagged_situations and their templates, which ask these readings what to draw.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class whereami_panel {
    /**
     * The tree of a record, when its panel is drawn for the current reader.
     *
     * @param int $candidateid The vaeiste.
     * @param string $fichecode The record.
     * @return \stdClass|null As selfpos_tree::for_record() gives it; null when the reader may not view the dossier, the
     *         competencies are off or the record is not imported: then nothing is drawn.
     */
    public static function tree(int $candidateid, string $fichecode): ?\stdClass {
        if (!access::can_view($candidateid) || !competency_gateway::is_subsystem_enabled()) {
            return null;
        }

        return selfpos_tree::for_record($fichecode);
    }

    /**
     * How many situations and declarations carry each rank of a record: two grouped queries, never one per node, and NOTHING
     * added up - a rank with nothing is absent, a parent gets nothing from its children.
     *
     * The situations are counted only for a reader who may read the preparation (draft_manager::can_read_doctype): for
     * anyone else they are not read at all, and situations is null - never a zero the reader could take for a fact.
     *
     * @param int $candidateid
     * @param string $fichecode
     * @return \stdClass[] Rank => (object) ['situations' => int|null, 'declarations' => int], in natural order of the ranks;
     *         situations is null when the reader may not read them.
     * @throws \moodle_exception When the reader may not view the dossier.
     */
    public static function counts(int $candidateid, string $fichecode): array {
        global $DB;

        access::require_can_view($candidateid);
        $readable = draft_manager::can_read_doctype($candidateid, draft_manager::DOCTYPE_VALIDATION);
        $situations = $readable ? (new situation_manager())->counts($candidateid, $fichecode) : [];
        $declarations = $DB->get_records_sql_menu(
            'SELECT ficherang, COUNT(1) AS declarations
               FROM {tool_vae_evidence}
              WHERE candidateid = :candidateid AND fichecode = :fichecode
           GROUP BY ficherang',
            ['candidateid' => $candidateid, 'fichecode' => $fichecode]
        );

        $out = [];
        foreach ($situations as $rank => $count) {
            $out[(string) $rank] = (object) ['situations' => (int) $count, 'declarations' => 0];
        }
        foreach ($declarations as $rank => $count) {
            $rank = (string) $rank;
            $out[$rank] = $out[$rank] ?? (object) ['situations' => $readable ? 0 : null, 'declarations' => 0];
            $out[$rank]->declarations = (int) $count;
        }
        uksort($out, 'strnatcmp');

        return $out;
    }

    /**
     * The situations tagged on a bloc of the preparation or on a node under it, for the part "lier".
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param string $blocrank The rank of the bloc as validation_catalog::parse() gives it (lower case); '' for the record as
     *        a whole, whose every node counts.
     * @return \stdClass[]|null Slot => situation, by slot; [] when none is tagged there; null when the reader may not read
     *         the situations, the competencies are off or the record is not imported: then nothing is drawn.
     */
    public static function tagged(int $candidateid, string $fichecode, string $blocrank): ?array {
        if (
            !draft_manager::can_read_doctype($candidateid, draft_manager::DOCTYPE_VALIDATION)
                || !competency_gateway::is_subsystem_enabled()
        ) {
            return null;
        }
        $tree = selfpos_tree::for_record($fichecode);
        if ($tree === null) {
            return null;
        }
        $ranks = self::ranks_under($tree, $fichecode, $blocrank);

        return $ranks ? (new situation_manager())->tagged($candidateid, $fichecode, $ranks) : [];
    }

    /**
     * The ranks of a bloc and of every node under it, in the tree of the record.
     *
     * @param \stdClass $tree As selfpos_tree::for_record() gives it.
     * @param string $fichecode
     * @param string $blocrank In lower case, as validation_catalog::parse() gives it; '' for the whole record.
     * @return string[]
     */
    private static function ranks_under(\stdClass $tree, string $fichecode, string $blocrank): array {
        if ($blocrank === '') {
            $top = '';
        } else {
            $structure = validation_catalog::structure($fichecode);
            $bloc = $structure->blocs[$blocrank] ?? null;
            $node = $bloc === null ? null : selfpos_tree::node($tree, (string) $bloc->rank);
            if ($node === null) {
                return [];
            }
            $top = (string) $node->rank;
        }

        // The nodes come in the order of the tree, a parent before its children: one pass gathers the whole branch.
        $branch = [$top => true];
        $ranks = $top === '' ? [] : [$top];
        foreach ($tree->nodes as $node) {
            if ((int) $node->level > 1 && isset($branch[(string) $node->parentrank])) {
                $branch[(string) $node->rank] = true;
                if ($node->positionable) {
                    $ranks[] = (string) $node->rank;
                }
            }
        }

        return array_values(array_unique($ranks));
    }
}
