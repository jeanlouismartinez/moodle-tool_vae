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
 * The code of the pages organisation.php and dossier.php under their heading, as it was before lot 7 (MMR-520 ticket 4,
 * wave 4b).
 *
 * Kept here as the expected value of the tests that play the new pages (tests/fixtures/plugin_pages.php). The only changes:
 * each page is a method whose parameters are the variables the page had set above its heading; "echo" writes to the string
 * returned; the footer and its "die()" return that string; the classes the page imported are named in full, and so are the
 * classes of the core it named (moodle_url, core_user, context_user); the calls to the old screen organisation_screen (but
 * tabs()) call the trait organisation_before; some long lines are folded. Nothing else is touched.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait pages_before {
    /**
     * The old page organisation.php under its heading.
     *
     * @param string $tab The tab the page opens on.
     * @param \stdClass|null $site What organisation::site() reads, on the tab of the site; null on the others.
     * @param \stdClass|null $pathway What organisation::for_candidate() reads, on the tab of the vaeiste; null on the others.
     * @param \stdClass|null $followed What organisation::followed() reads, on the tab of the followed vaeistes; null on the others.
     * @param \moodle_url|null $url The address of the page with its tab (and its bloc) in it, for the table of the followed.
     * @param int $candidateid The user whose own pathway the page shows (the name of the variable on the old page).
     * @param \context|null $context The context the right to declare is read in: the user context of $candidateid.
     * @return string HTML.
     */
    protected static function before_organisation_page(
        string $tab,
        ?\stdClass $site,
        ?\stdClass $pathway,
        ?\stdClass $followed,
        ?\moodle_url $url,
        int $candidateid,
        ?\context $context
    ): string {
        global $DB, $OUTPUT, $PAGE, $USER;

        $renderer = $PAGE->get_renderer('tool_vae');
        $out = '';
        // The link "Help" to the guide of the role of the tab (lot S5), near its heading, for whoever may open that guide.
        $out .= self::before_organisation_guide_link($tab);
        $out .= $OUTPUT->tabtree(\tool_vae\local\organisation_screen::tabs(\tool_vae\local\organisation::tabs()), $tab);

        if ($site !== null) {
            $out .= self::before_organisation_notice(get_string('orgintrosite', 'tool_vae'));
            $out .= self::before_organisation_site($site);
            return $out;
        }

        if ($pathway === null && $followed === null) {
            $out .= $OUTPUT->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                \core\output\notification::NOTIFY_ERROR
            );
            return $out;
        }

        if ($followed !== null) {
            // What the manager can do now (lot S2): over every followed vaeiste, whatever bloc the table below is limited to.
            $out .= $renderer->render(\tool_vae\output\next_actions::manager(
                \tool_vae\local\next_actions::for_manager(array_keys($followed->candidates))
            ));
            // No button of action on this tab: the names lead to the dossiers, which carry their own rights.
            $out .= self::before_organisation_notice(get_string('orgintrofollowed', 'tool_vae'));
            $out .= self::before_organisation_followed($followed, $url);
            return $out;
        }

        // What the vaeiste can do now (lot S2), on their own dossier, before the tables.
        $out .= $renderer->render(
            \tool_vae\output\next_actions::candidate(\tool_vae\local\next_actions::for_vaeiste($candidateid))
        );

        // The preparation of the validation dossier: one's own, offered on the same conditions as on the dossier.
        $mayvalidate = $pathway->declared && \tool_vae\local\draft_manager::can_write($candidateid)
            && \tool_vae\local\draft_manager::validation_open();

        if (!$pathway->declared && !$pathway->planned) {
            $out .= self::before_organisation_nothing($pathway);
        } else {
            // The introduction speaks of the tables: a pathway of records only a plan names has none.
            if ($pathway->declared) {
                $intro = get_string('orgintromine', 'tool_vae');
                if ($mayvalidate) {
                    $intro .= ' ' . get_string('orgintrovalidation', 'tool_vae');
                }
                $out .= self::before_organisation_notice($intro);
            }
            $out .= self::before_organisation_mine($pathway);
        }

        // What the candidate says is outside their activity (T-42), apart from the counts.
        $maydeclare = has_capability('tool/vae:declare', $context);
        $out .= $renderer->render(new \tool_vae\output\scope_box(
            $pathway->marks,
            \tool_vae\local\scope_manager::labels($pathway->marks),
            $maydeclare,
            !empty(get_config('tool_vae', 'scopeopen'))
        ));

        if ($maydeclare) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/declare.php'),
                get_string('declareheading', 'tool_vae'),
                'get'
            );
        }
        // Positioning oneself on the nodes of a record (lot 1a): the vaeiste's own word, open at any time, with or without a
        // declaration.
        if (\tool_vae\local\selfpos_manager::can_write($candidateid)) {
            $out .= $OUTPUT->single_button(
                \tool_vae\local\selfpos_screen::url($candidateid),
                get_string('selfposlink', 'tool_vae'),
                'get'
            );
        }
        // The work situations of the vaeiste (lot 1b): their own account of their work, with or without a declaration.
        if (\tool_vae\local\situation_manager::can_write($candidateid)) {
            $out .= $OUTPUT->single_button(
                \tool_vae\local\situation_screen::url($candidateid),
                get_string('situationslink', 'tool_vae'),
                'get'
            );
        }
        if ($pathway->declared || $pathway->planned) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $candidateid]),
                get_string('mydossier', 'tool_vae'),
                'get'
            );
        }
        if ($mayvalidate) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/validation.php'),
                get_string('validationtitle', 'tool_vae'),
                'get'
            );
        }

        return $out;
    }

    /**
     * The old page dossier.php under its heading.
     *
     * @param int $candidateid The user whose dossier the page shows (the name of the variable on the old page).
     * @param \context $context The context of that dossier: the user context of $candidateid.
     * @return string HTML.
     */
    protected static function before_dossier_page(int $candidateid, \context $context): string {
        global $DB, $OUTPUT, $PAGE, $USER;

        $renderer = $PAGE->get_renderer('tool_vae');
        $out = '';
        if (!\tool_vae\local\competency_gateway::is_subsystem_enabled()) {
            $out .= $OUTPUT->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                \core\output\notification::NOTIFY_ERROR
            );
            return $out;
        }

        $isown = ($candidateid === (int) $USER->id);

        // Whether this reader may attest. Never true on one's own dossier, whatever the roles
        // say: the check that matters is in attestation_manager, this one only decides what to draw.
        $mayattest = !$isown && \tool_vae\local\access::can_attest($candidateid);

        $claims = $DB->get_records('tool_vae_evidence', ['candidateid' => $candidateid], 'timecreated ASC, id ASC');

        // The coverage is the candidate's alone, on their own dossier - decided with the project owner on
        // 12 October 2026. Read by a manager or the direction, the same counts become a gauge on a person,
        // which is the verdict this plugin refuses to give. A session of "log in as" makes $USER the candidate while the
        // reader is somebody else: it is refused here as it is on the pathway page (decision of 3 November 2026).
        if ($isown && $claims && !\core\session\manager::is_loggedinas()) {
            $out .= $renderer->render(new \tool_vae\output\coverage(\tool_vae\local\coverage::for_candidate($candidateid)));
        }

        // What the candidate says is outside their activity (T-42): read by whoever reads the dossier, apart from the coverage.
        $marks = \tool_vae\local\scope_manager::for_candidate($candidateid);
        $mayscope = $isown && has_capability('tool/vae:declare', $context);
        $out .= $renderer->render(new \tool_vae\output\scope_box(
            $marks,
            \tool_vae\local\scope_manager::labels($marks),
            $mayscope,
            !empty(get_config('tool_vae', 'scopeopen'))
        ));

        if (!$claims) {
            $out .= $OUTPUT->notification(
                get_string('nodeclarationyet', 'tool_vae'),
                \core\output\notification::NOTIFY_INFO
            );
        } else {
            $out .= $renderer->render(new \tool_vae\output\state_legend());
            $requests = new \tool_vae\local\request_manager();
            $carries = new \tool_vae\local\carry_manager();
            foreach ($claims as $claim) {
                $out .= \html_writer::start_tag('section', ['class' => 'tool-vae-claim-block mb-4']);

                // Computed on sight, not read from a flag: the candidate can change the core record
                // at any moment through tool_lp, unlinking raises no event at all, and a dossier that
                // asserted a link the platform no longer holds would mislead the one person it exists
                // to inform. Three queries per claim - measured, not estimated; it was five before the
                // core record stopped being read twice - on a page that shows a handful.
                $out .= $renderer->render(new \tool_vae\output\anchor_warning($claim, \tool_vae\local\anchor::divergence($claim)));

                $attestations = \tool_vae\local\attestation_manager::attestations((int) $claim->id);
                $asked = $requests->for_evidence($claim);
                $out .= \html_writer::tag('p', $renderer->render(new \tool_vae\output\state_label(
                    $attestations,
                    \tool_vae\local\request_manager::count_open((int) $claim->id)
                )));

                $carrynotes = $carries->notes($claim);
                $out .= $renderer->render(new \tool_vae\output\carry_notes($carrynotes));
                $out .= $renderer->render(new \tool_vae\output\declaration($claim, $context));

                $out .= \html_writer::tag('h3', s(get_string('attestation', 'tool_vae')));

                $out .= $renderer->render(new \tool_vae\output\attestations($attestations));
                $carryacts = [];
                foreach (['carriedto', 'carriedfrom'] as $direction) {
                    if ($carrynotes->$direction) {
                        $carryacts[] = ['time' => (int) $carrynotes->$direction->carriedat, 'act' => $direction];
                    }
                }
                $out .= $renderer->render(new \tool_vae\output\chronology($claim, $attestations, $asked, $carryacts));

                $standing = \tool_vae\local\attestation_manager::active_attestation((int) $claim->id);

                if ($standing) {
                    // Drawn for whoever the rule of \tool_vae\local\attestation_manager::require_can_withdraw() names: the
                    // site administrator, a holder of tool/vae:withdrawany, or - when the site setting is on
                    // - the signatory for their own attestation. Not tied to the right to attest:
                    // a coordinator may withdraw without ever signing.
                    $requestopen = ($standing->withdrawreqat ?? null) !== null;
                    $issignatory = $standing->attestantid !== null && (int) $standing->attestantid === (int) $USER->id;

                    if (!$isown && $issignatory && $requestopen) {
                        // A colleague asked this signatory to withdraw: two buttons, accept or refuse.
                        $out .= $OUTPUT->notification(
                            get_string('requestwithdrawalyours', 'tool_vae'),
                            \core\output\notification::NOTIFY_WARNING
                        );
                        $out .= $OUTPUT->single_button(
                            new \moodle_url('/admin/tool/vae/withdraw.php', ['attestationid' => $standing->id]),
                            get_string('acceptwithdrawal', 'tool_vae'),
                            'get'
                        );
                        $out .= $OUTPUT->single_button(
                            new \moodle_url('/admin/tool/vae/withdrawrequest.php', [
                                'attestationid' => $standing->id,
                                'action' => 'refuse',
                                'sesskey' => sesskey(),
                            ]),
                            get_string('refusewithdrawal', 'tool_vae'),
                            'post'
                        );
                    } else if (!$isown && \tool_vae\local\attestation_manager::may_withdraw($standing, $candidateid)) {
                        $out .= $OUTPUT->single_button(
                            new \moodle_url('/admin/tool/vae/withdraw.php', ['attestationid' => $standing->id]),
                            get_string('withdraw', 'tool_vae'),
                            'get'
                        );
                    } else if (
                        !$isown && $mayattest && !$issignatory && $standing->attestantid !== null
                            && !\tool_vae\local\attestation_manager::signatory_reachable($standing)
                    ) {
                        // WHY BEFORE THE PENDING SENTENCE: a request to a signatory who is gone (deleted, or suspended after the
                        // request was made) waits for nobody. The reader is sent to the one person who can still withdraw, the
                        // administrator, instead of a button that is no longer offered or a sentence that promises an answer.
                        $out .= $OUTPUT->notification(
                            get_string('requestwithdrawalnosignatory', 'tool_vae'),
                            \core\output\notification::NOTIFY_INFO
                        );
                    } else if (!$isown && $requestopen && $mayattest) {
                        $out .= $OUTPUT->notification(
                            get_string('requestwithdrawalpending', 'tool_vae'),
                            \core\output\notification::NOTIFY_INFO
                        );
                    } else if (!$isown && \tool_vae\local\attestation_manager::may_request_withdrawal($standing, $candidateid)) {
                        $out .= $OUTPUT->single_button(
                            new \moodle_url('/admin/tool/vae/withdrawrequest.php', [
                                'attestationid' => $standing->id,
                                'action' => 'request',
                                'sesskey' => sesskey(),
                            ]),
                            get_string('requestwithdrawal', 'tool_vae'),
                            'post'
                        );
                    }
                } else if ($mayattest) {
                    $out .= $OUTPUT->single_button(
                        new \moodle_url('/admin/tool/vae/attest.php', ['evidenceid' => $claim->id]),
                        \tool_vae\local\presenter::attest_label($attestations),
                        'get'
                    );
                }

                // A declaration on a replaced record, with a correspondence decided: its owner may carry it over (T-30,
                // lot 3). Never offered to anyone else, never done by itself.
                if ($isown && $carries->offer($claim)) {
                    $out .= $OUTPUT->single_button(
                        new \moodle_url('/admin/tool/vae/carry.php', ['evidenceid' => $claim->id]),
                        get_string('carrylink', 'tool_vae'),
                        'get'
                    );
                }

                // The questions and answers on this declaration (T-20): a link for whoever reads, with how many
                // still wait. Never a state of the declaration and never a reason it may not be attested.
                $waiting = \tool_vae\local\request_manager::count_open((int) $claim->id);
                $out .= \html_writer::tag('p', \html_writer::link(
                    new \moodle_url('/admin/tool/vae/precision.php', ['evidenceid' => $claim->id]),
                    get_string($waiting ? 'precisionlinkwaiting' : 'precisionlink', 'tool_vae', $waiting)
                ));

                // The supporting pieces (T-13): a link for whoever reads the dossier when a piece is there, and for the candidate
                // also when adding is open.
                $active = \tool_vae\local\piece_manager::active_for_evidence((int) $claim->id);
                $pieces = count($active);
                if ($pieces || ($isown && \tool_vae\local\piece_manager::is_open())) {
                    // Those the signature standing does not cover: said where the reader works, whether or not the message arrived.
                    $standing = \tool_vae\local\attestation_manager::active_attestation((int) $claim->id);
                    $late = 0;
                    if ($standing !== null && $pieces) {
                        $covered = \tool_vae\local\piece_manager::covered_by((int) $standing->id);
                        $late = count(array_filter($active, fn($piece) => !array_key_exists((int) $piece->id, $covered)));
                    }
                    $line = \html_writer::link(
                        new \moodle_url('/admin/tool/vae/piece.php', ['evidenceid' => $claim->id]),
                        get_string($pieces ? 'pieceslinkcount' : 'pieceslink', 'tool_vae', $pieces)
                    );
                    if ($late) {
                        $line .= ' ' . \html_writer::tag(
                            'span',
                            s(get_string('pieceslate', 'tool_vae', $late)),
                            ['class' => 'badge bg-warning text-dark']
                        );
                    }
                    $out .= \html_writer::tag('p', $line);
                }

                $out .= \html_writer::end_tag('section');
            }
        }

        // The positionings of the structure (T-90, lot 3): the evaluation of each competency and the certification of each bloc, on
        // the
        // tree of the records the candidate declared on. Read by whoever reads the dossier; never a validation, which the jury
        // alone gives.
        $out .= $renderer->render(new \tool_vae\output\positioning_section($candidateid, $context));

        if ($isown) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/declare.php'),
                get_string('declareheading', 'tool_vae'),
                'get'
            );
            // The pathway of the candidate (T-90): theirs alone, so only on their own dossier and not in a session of "log in as".
            if (!\core\session\manager::is_loggedinas()) {
                $out .= $OUTPUT->single_button(
                    new \moodle_url('/admin/tool/vae/organisation.php'),
                    get_string('orgtabmine', 'tool_vae'),
                    'get'
                );
            }
        }

        if ($claims && \tool_vae\local\access::can_export($candidateid)) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/export.php', ['userid' => $candidateid]),
                get_string('exportdossier', 'tool_vae'),
                'get'
            );
        }

        // The sections of the dossier de faisabilité (T-10): one's own, written by the candidate alone.
        if ($candidateid === (int) $USER->id && \tool_vae\local\draft_manager::can_write($candidateid)) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/faisabilite.php'),
                get_string('faisabheading', 'tool_vae'),
                'get'
            );
        }

        // The preparation of the validation dossier (T-11): one's own, not the official dossier of the certifier.
        if (
            $candidateid === (int) $USER->id && $claims && \tool_vae\local\draft_manager::can_write($candidateid)
                && \tool_vae\local\draft_manager::validation_open()
        ) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/validation.php'),
                get_string('validationtitle', 'tool_vae'),
                'get'
            );
        }

        // The same preparation, read by whoever may view the dossier (D-1b-G), once the vaeiste has begun it.
        if (
            $candidateid !== (int) $USER->id
                && \tool_vae\local\draft_manager::can_read_doctype($candidateid, \tool_vae\local\draft_manager::DOCTYPE_VALIDATION)
                && $DB->record_exists('tool_vae_draft', [
                    'candidateid' => $candidateid,
                    'doctype' => \tool_vae\local\draft_manager::DOCTYPE_VALIDATION,
                ])
        ) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/validation.php', ['userid' => $candidateid]),
                get_string('validationreadlink', 'tool_vae'),
                'get'
            );
        }

        // The work situations (lot 1b): the vaeiste's own, to write; read by whoever may read the preparation, once there is one.
        if ($candidateid === (int) $USER->id && \tool_vae\local\situation_manager::can_write($candidateid)) {
            $out .= $OUTPUT->single_button(
                \tool_vae\local\situation_screen::url($candidateid),
                get_string('situationslink', 'tool_vae'),
                'get'
            );
        } else if (
            $candidateid !== (int) $USER->id
                && \tool_vae\local\draft_manager::can_read_doctype($candidateid, \tool_vae\local\draft_manager::DOCTYPE_VALIDATION)
                && $DB->record_exists(\tool_vae\local\situation_manager::TABLE, ['candidateid' => $candidateid])
        ) {
            $out .= $OUTPUT->single_button(
                \tool_vae\local\situation_screen::url($candidateid),
                get_string('situationsreadlink', 'tool_vae'),
                'get'
            );
        }

        // The consent to annotations (lot 1b): the vaeiste's own, to give or take back, before any line; and whoever reads the
        // preparation (the same rule as situations.php, whose door is can_read_doctype). The administrator does not (MC-16).
        if (
            \tool_vae\local\consent_manager::can_manage($candidateid)
                || \tool_vae\local\draft_manager::can_read_doctype($candidateid, \tool_vae\local\draft_manager::DOCTYPE_VALIDATION)
        ) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/consent.php', ['userid' => $candidateid]),
                get_string('consentlink', 'tool_vae'),
                'get'
            );
        }

        // The deposits of the dossier de faisabilité (T-21): the candidate's own, and whoever may read them.
        if (\tool_vae\local\deposit_manager::can_read($candidateid)) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/deposit.php', ['userid' => $candidateid]),
                get_string('depositheading', 'tool_vae'),
                'get'
            );
        }

        // The reflective journal (T-14): the candidate's own, nobody else's.
        $journalkept = ['candidateid' => $candidateid, 'doctype' => \tool_vae\local\draft_manager::DOCTYPE_REFLEXION];
        if (
            $candidateid === (int) $USER->id && \tool_vae\local\draft_manager::can_write($candidateid)
                && (\tool_vae\local\draft_manager::reflection_open() || $DB->record_exists('tool_vae_draft', $journalkept))
        ) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/journal.php'),
                get_string('reflexiontitle', 'tool_vae'),
                'get'
            );
        }

        // The deposits of the preparation of the validation dossier (T-11, lot 3): read as deposited, by whoever may read deposits.
        $validationdeposits = ['candidateid' => $candidateid, 'doctype' => \tool_vae\local\draft_manager::DOCTYPE_VALIDATION];
        if (
            \tool_vae\local\deposit_manager::can_read($candidateid)
                && $DB->record_exists('tool_vae_deposit', $validationdeposits)
        ) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/deposit.php', [
                    'userid' => $candidateid,
                    'doctype' => \tool_vae\local\draft_manager::DOCTYPE_VALIDATION,
                ]),
                get_string('validationdepositheading', 'tool_vae'),
                'get'
            );
        }

        // The editable copies (T-83): the archive's right, since they leave the platform just as it does.
        // Posted: the button then carries the session key that document.php requires, and the key stays out of the address.
        if ($claims && \tool_vae\local\access::can_export($candidateid)) {
            foreach (['docx' => 'documentdocx', 'odt' => 'documentodt'] as $format => $label) {
                $out .= $OUTPUT->single_button(
                    new \moodle_url('/admin/tool/vae/document.php', ['userid' => $candidateid, 'format' => $format]),
                    get_string($label, 'tool_vae'),
                    'post'
                );
            }
        }

        if ($claims && \tool_vae\local\backup::can_backup($candidateid)) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/backup.php', ['userid' => $candidateid]),
                get_string('backupdossier', 'tool_vae'),
                'get'
            );
        }

        if (\tool_vae\local\dossier_importer::can_import()) {
            $out .= $OUTPUT->single_button(
                new \moodle_url('/admin/tool/vae/import_dossier.php', ['userid' => $candidateid]),
                get_string('importdossier', 'tool_vae'),
                'get'
            );
        }

        return $out;
    }
}
