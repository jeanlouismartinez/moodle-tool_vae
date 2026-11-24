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
 * What the screens read of a claim, its attestations, its pieces and the records, to show them.
 *
 * Three real callers - the dossier, the attestation screen and the withdrawal screen - all needing the same block. That is
 * the threshold the project rules set for pulling something out into its own place: two real uses, not one anticipated one.
 *
 * NO HTML HERE ANY MORE (lot 7, MMR-520 ticket 4). The blocks are drawn by pieces of classes/output (declaration,
 * attestations, pieces, sources, coverage, scope_box...) and their templates; what is left is what draws nothing: the label
 * of a button, the state of a declaration and the order of its acts, and the readings the pieces need (counts, names,
 * addresses, who opened what), which go to the database and the gateway and so stay out of classes/output.
 *
 * ESCAPING STAYS THE POINT. Two fields are free text written by a user and stored outside the cleaning core applies to its
 * own columns: the narrative and the detail about the setting. They are rendered in the session of a manager who, by
 * definition, can read other people's dossiers. What is read here is given as plain text, never as HTML: the templates
 * escape it, and the narrative goes through format_text() in the piece, with the format the vaeiste chose.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class presenter {
    /**
     * What the table of the certification records shows of each one, read from the database and the gateway.
     *
     * The count of declarations tells an administrator what a re-import will and will not touch. The divergences an import
     * recorded in tool_vae_obsolete are counted, so that a human reads them somewhere (decision 3 keeps the evidence; it does
     * not excuse keeping quiet about it). The name is the one the import gave the framework.
     *
     * @param \stdClass[] $sources Rows of tool_vae_source.
     * @return \stdClass[] In the order given, each: source (the row), name (of its framework, '' when none), competencies,
     *         claims, gone and diverged (counts).
     */
    public static function source_facts(array $sources): array {
        global $DB;

        $gateway = new competency_gateway();
        $facts = [];
        foreach ($sources as $source) {
            $facts[] = (object) [
                'source' => $source,
                'name' => (string) $gateway->framework_name((int) $source->frameworkid),
                'competencies' => $gateway->count_framework_competencies((int) $source->frameworkid),
                'claims' => $DB->count_records('tool_vae_evidence', ['fichecode' => $source->fichecode]),
                'gone' => $DB->count_records('tool_vae_obsolete', [
                    'sourceid' => (int) $source->id,
                    'kind' => importer::GONE,
                ]),
                'diverged' => $DB->count_records('tool_vae_obsolete', [
                    'sourceid' => (int) $source->id,
                    'kind' => importer::DIVERGED,
                ]),
            ];
        }

        return $facts;
    }

    /**
     * The lines of a claim, label then value, as plain text.
     *
     * What the code stands for is given with it: the page used to give the code alone - the PDF gave the name - and a manager
     * signing, or a vaeiste reading, had to know the framework by heart. Absent when the name cannot be read: the code stays.
     * The bloc the competency sits under, when the record has blocs (T-33).
     *
     * @param \stdClass $evidence A tool_vae_evidence row.
     * @return string[] Label => value, in the order they are read; neither is escaped.
     */
    public static function declaration_rows(\stdClass $evidence): array {
        $rows = [
            get_string('fichesource', 'tool_vae') => $evidence->fichecode . '-' . $evidence->ficherang,
        ];

        $name = ($evidence->competencyid ?? null) === null
            ? null
            : (new competency_gateway())->competency_name((int) $evidence->competencyid);
        if ($name !== null && trim($name) !== '') {
            $rows[get_string('competency', 'tool_vae')] = $name;
        }

        $bloc = ($evidence->competencyid ?? null) === null
            ? null
            : (new competency_gateway())->bloc_of((int) $evidence->competencyid);
        if ($bloc !== null) {
            $rows[get_string('declarationbloc', 'tool_vae')] = $bloc->idnumber . ' — ' . $bloc->label;
        }

        $rows += [
            get_string('ficheupdated', 'tool_vae')
                => display_date::format((int) $evidence->ficheupdated, get_string('strftimedate', 'langconfig')),
            get_string('period', 'tool_vae') => get_string(
                'periodfromto',
                'tool_vae',
                (object) [
                    'from' => display_date::format((int) $evidence->periodstart, get_string('strftimedate', 'langconfig')),
                    'to' => display_date::format((int) $evidence->periodend, get_string('strftimedate', 'langconfig')),
                ]
            ),
            get_string('settingtype', 'tool_vae') => get_string('setting:' . $evidence->settingtype, 'tool_vae'),
        ];

        if (trim((string) $evidence->settingdetail) !== '') {
            $rows[get_string('settingdetail', 'tool_vae')] = (string) $evidence->settingdetail;
        }

        return $rows;
    }

    /**
     * The addresses where the certifiers of the records of a coverage publish their rule of validation (T-41).
     *
     * Shown only once the site has opened them.
     *
     * @param \stdClass[] $fiches What coverage::for_candidate() returns.
     * @return \stdClass[][] As ref_manager::for_fiches() gives them; empty while the site has not opened them.
     */
    public static function coverage_refs(array $fiches): array {
        return empty(get_config('tool_vae', 'refopen')) ? [] : ref_manager::for_fiches(array_column($fiches, 'fichecode'));
    }

    /**
     * What the list of the pieces of a declaration needs to know of its reader and of the signature standing on it.
     *
     * @param \stdClass[] $pieces Rows of tool_vae_piece, removed ones included.
     * @param \context_user $context The vaeiste's context.
     * @param \stdClass|null $standing The attestation in force, if any.
     * @return \stdClass covered (piece id => covered, as piece_manager::covered_by() gives it, or null with no signature),
     *         reader (whether the reader is somebody else than the vaeiste), opened (piece id => true for those this reader
     *         opened; empty for the vaeiste).
     */
    public static function pieces_reading(array $pieces, \context_user $context, ?\stdClass $standing): \stdClass {
        global $USER;

        $reader = (int) $USER->id !== (int) $context->instanceid;

        return (object) [
            'covered' => $standing ? piece_manager::covered_by((int) $standing->id) : null,
            'reader' => $reader,
            'opened' => $reader
                ? piece_manager::opened_by((int) $USER->id, array_map(fn($piece) => (int) $piece->id, $pieces))
                : [],
        ];
    }

    /**
     * The label of the button that records an attestation on a declaration.
     *
     * T-60. A declaration whose attestation was withdrawn offered the same button as one never
     * attested, and the reader could not tell that this would be a new attestation, not the
     * old one restored. Called only when no attestation stands.
     *
     * @param \stdClass[] $attestations Rows of tool_vae_attest, none of them in force.
     * @return string
     */
    public static function attest_label(array $attestations): string {
        return get_string($attestations ? 'attestagain' : 'attest', 'tool_vae');
    }

    /** @var string[] The states a declaration reads as, in the order the legend gives them. */
    public const STATES = ['declared', 'attested', 'withdrawn'];

    /**
     * The state a declaration is in, as one word of the legend (T-40).
     *
     * DESCRIBES, NEVER JUDGES. The state says what was done to the declaration: declared and not yet
     * attested, attested, or attested then withdrawn. It says nothing of whether it is good enough.
     *
     * @param \stdClass[] $attestations Rows of tool_vae_attest, oldest first.
     * @return string One of self::STATES.
     */
    public static function state(array $attestations): string {
        foreach ($attestations as $attestation) {
            if ($attestation->withdrawnat === null) {
                return 'attested';
            }
        }

        return $attestations ? 'withdrawn' : 'declared';
    }

    /**
     * The acts done on a declaration, in order of date (T-40).
     *
     * ACTS AND DATES ONLY. No name, no text: the dates are what the dossier already shows, put in
     * order. A withdrawn attestation appears twice, signed and withdrawn.
     *
     * @param \stdClass $evidence A row of tool_vae_evidence.
     * @param \stdClass[] $attestations Rows of tool_vae_attest.
     * @param \stdClass[] $requests Rows of tool_vae_request.
     * @param array[] $carries Acts of a carry (T-30, lot 3), each ['time' => int, 'act' => 'carriedto' or
     *        'carriedfrom'].
     * @return array[] Each ['time' => int, 'act' => string], sorted by time.
     */
    public static function chronology(\stdClass $evidence, array $attestations, array $requests, array $carries = []): array {
        $acts = [['time' => (int) $evidence->timecreated, 'act' => 'declared']];
        foreach ($carries as $carry) {
            $acts[] = ['time' => (int) $carry['time'], 'act' => $carry['act']];
        }
        foreach ($requests as $request) {
            $acts[] = ['time' => (int) $request->timecreated, 'act' => 'questionasked'];
            if ($request->timeanswered !== null) {
                $acts[] = ['time' => (int) $request->timeanswered, 'act' => 'questionanswered'];
            }
        }
        foreach ($attestations as $attestation) {
            $acts[] = [
                'time' => (int) ($attestation->attestedat ?? $attestation->timecreated),
                'act' => 'attested',
            ];
            if ($attestation->withdrawnat !== null) {
                $acts[] = ['time' => (int) $attestation->withdrawnat, 'act' => 'withdrawn'];
            }
        }
        // Stable: acts of the same second keep the order they were added in.
        foreach ($acts as $i => &$act) {
            $act['i'] = $i;
        }
        unset($act);
        usort($acts, fn($a, $b) => [$a['time'], $a['i']] <=> [$b['time'], $b['i']]);

        return array_map(fn($a) => ['time' => $a['time'], 'act' => $a['act']], $acts);
    }
}
