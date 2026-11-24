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

use tool_vae\local\certification_manager;
use tool_vae\local\evaluation_manager;
use tool_vae\local\positioning_rules;
use tool_vae\local\positioning_screen;
use tool_vae\local\positioning_successor;
use tool_vae\local\selfpos_manager;
use tool_vae\local\selfpos_screen;
use tool_vae\local\structure_archive;

/**
 * The HTML the screens positioning_screen and selfpos_screen wrote before lot 7 (MMR-520 ticket 4, wave 3a).
 *
 * Their code as it was in classes/local/ before its migration to pieces of classes/output/, kept here as the expected value of
 * the tests that prove each piece draws the same DOM, and for the waves that migrate selfposition.php (4a) and dossier.php
 * (4b). The only changes: each method is "protected static" and named before_pos_... (positioning_screen) or
 * before_selfpos_... (selfpos_screen), the calls between them follow, and "self::url(" is the address of the screen, which
 * stays in classes/local/ (positioning_screen::url(), selfpos_screen::url()). Nothing else is touched: the escaping, the
 * order, the classes and the spaces are those of the old code.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait positioning_before {
    /**
     * The section of the dossier: for each record, each bloc with its certification and its competencies with their evaluation.
     *
     * @param int $candidateid
     * @param \context $context The context of the candidate.
     * @return string HTML; empty when the candidate declared on no record the site still holds.
     */
    protected static function before_pos_section(int $candidateid, \context $context): string {
        global $DB;

        $codes = $DB->get_fieldset_sql(
            'SELECT DISTINCT fichecode FROM {tool_vae_evidence} WHERE candidateid = :c ORDER BY fichecode ASC',
            ['c' => $candidateid]
        );
        $evaluations = new evaluation_manager();
        $certifications = new certification_manager();
        $html = '';
        foreach ($codes as $code) {
            $layout = positioning_rules::layout((string) $code);
            if (!$layout || !$layout->leaves) {
                continue;
            }
            $evaluated = $evaluations->current($candidateid, (string) $code);
            $certified = $certifications->current($candidateid, (string) $code);
            // What the record this one replaces positioned, read and never copied.
            $older = positioning_successor::readings($candidateid, (string) $code, $layout);
            // The vaeiste's own word on each node (lot 1a), put next to the structure's, without any gap. The reader has the
            // right to read the dossier: the caller asked it at the door.
            $selfpos = positioning_rules::latest(selfpos_manager::TABLE, $candidateid, (string) $code);
            $html .= \html_writer::tag('h4', s($code), ['class' => 'h5']);
            foreach ($layout->blocs as $rank => $bloc) {
                $html .= self::before_pos_bloc(
                    $candidateid,
                    (string) $code,
                    (string) $rank,
                    $bloc,
                    $evaluated,
                    $certified,
                    $older,
                    $context,
                    $selfpos
                );
            }
            $alone = array_filter($layout->leaves, fn($leaf) => $leaf->bloc === null && $layout->hasblocs);
            if ($alone) {
                $html .= self::before_pos_leaves(
                    $candidateid,
                    (string) $code,
                    $alone,
                    $evaluated,
                    $context,
                    $older['eval'],
                    $selfpos
                );
            }
        }
        if ($html === '') {
            // The records are gone from the site, but the archive of the structure in place is still the vaeiste's to take.
            $block = self::before_pos_structure_block($candidateid);

            return $block === '' ? '' : \html_writer::tag('section', $block, ['class' => 'tool-vae-positioning mb-4']);
        }

        // The way to the self-positioning of the vaeiste at every level (lot 1a): the vaeiste goes to position themselves,
        // anyone else who reads the dossier goes to read it. The right to read was asked at the door of the dossier.
        $selfposlink = \html_writer::tag('p', \html_writer::link(
            selfpos_screen::url($candidateid),
            get_string(selfpos_manager::can_write($candidateid) ? 'selfposlink' : 'selfposreadall', 'tool_vae')
        ), ['class' => 'small']);

        return \html_writer::start_tag('section', ['class' => 'tool-vae-positioning mb-4'])
            . \html_writer::tag('h3', s(get_string('positioningheading', 'tool_vae')))
            . \html_writer::tag('p', s(get_string('positioningdisclaimer', 'tool_vae')), ['class' => 'text-muted small'])
            . $selfposlink
            . self::before_pos_structure_block($candidateid)
            . $html
            . \html_writer::end_tag('section');
    }

    /**
     * The archive of the structure: the one in place, for whoever may read it, and the button that produces a new one, for whoever
     * may produce it. A document of its own, apart from the dossiers of the vaeiste.
     *
     * @param int $candidateid
     * @return string HTML; empty when the reader may do neither.
     */
    protected static function before_pos_structure_block(int $candidateid): string {
        $out = '';
        $current = structure_archive::current($candidateid);
        if ($current && structure_archive::can_read($candidateid)) {
            $out .= \html_writer::tag('p', s(get_string('structurecurrent', 'tool_vae', (object) [
                'date' => userdate((int) $current->get_timecreated()),
                'id' => substr($current->get_filename(), -30, 26),
            ])) . ' ' . \html_writer::link(
                \moodle_url::make_pluginfile_url(
                    $current->get_contextid(),
                    'tool_vae',
                    structure_archive::FILEAREA,
                    $current->get_itemid(),
                    $current->get_filepath(),
                    $current->get_filename(),
                    true
                ),
                get_string('structuredownload', 'tool_vae')
            ));
        }
        if (structure_archive::can_issue($candidateid)) {
            $out .= \html_writer::tag('p', \html_writer::link(
                new \moodle_url('/admin/tool/vae/structure.php', ['userid' => $candidateid]),
                get_string('structureissue', 'tool_vae'),
                ['class' => 'btn btn-secondary']
            ));
        }

        return $out;
    }

    /**
     * One bloc: its certification, the lacks, and its competencies.
     *
     * @param int $candidateid
     * @param string $code
     * @param string $rank
     * @param \stdClass $bloc label, leaves.
     * @param \stdClass[] $evaluated Rank => latest evaluation.
     * @param \stdClass[] $certified Rank => latest certification.
     * @param array $older What the record this one replaces positioned, as positioning_successor::readings() gives it.
     * @param \context $context
     * @param \stdClass[] $selfpos Rank => the vaeiste's own line that stands (lot 1a).
     * @return string
     */
    protected static function before_pos_bloc(
        int $candidateid,
        string $code,
        string $rank,
        \stdClass $bloc,
        array $evaluated,
        array $certified,
        array $older,
        \context $context,
        array $selfpos = []
    ): string {
        $title = $rank === '' ? get_string('positioningwhole', 'tool_vae') : $bloc->label;
        $out = \html_writer::tag('h5', s(get_string('positioningbloc', 'tool_vae')) . ' - ' . s($title), ['class' => 'h6']);

        $line = $certified[$rank] ?? null;
        $expired = false;
        if ($line === null || $line->status === positioning_rules::WITHDRAWN) {
            $out .= \html_writer::tag('p', s(get_string('positioningnone', 'tool_vae')), ['class' => 'text-muted']);
        } else {
            $out .= self::before_pos_line($line, $context, 'certif');
            // Told on reading, on the day of the reader, and written nowhere: the same words for whoever reads the dossier.
            $expired = certification_manager::is_expired($line, certification_manager::today());
            if ($expired) {
                $days = certification_manager::days_overdue((string) $line->validuntil, certification_manager::today());
                $out .= \html_writer::div(
                    \html_writer::tag('strong', s(get_string('positioningtorenew', 'tool_vae'))) . ' : '
                        . s(get_string('positioningexpired', 'tool_vae', (object) [
                            'until' => $line->validuntil, 'days' => $days,
                        ])),
                    'alert alert-secondary small'
                );
            }
            $newest = 0;
            foreach ($bloc->leaves as $leafrank => $leaf) {
                $newest = max($newest, (int) (($evaluated[(string) $leafrank]->timecreated ?? 0)));
            }
            if ($newest > (int) $line->timecreated) {
                $out .= \html_writer::div(s(get_string('positioningstale', 'tool_vae')), 'alert alert-warning small');
            }
        }
        $out .= self::before_pos_older_lines($older['certif'][$rank] ?? [], 'certif');
        $out .= self::before_pos_selfpos_line($selfpos[$rank] ?? null);
        $links = [];
        if (certification_manager::can_write($candidateid)) {
            $links[] = \html_writer::link(
                positioning_screen::url('certify', $candidateid, $code, $rank),
                get_string($expired ? 'positioningrenewlink' : 'positioningcertifylink', 'tool_vae')
            );
        }
        $links[] = \html_writer::link(
            positioning_screen::url('certify', $candidateid, $code, $rank),
            get_string('positioninghistory', 'tool_vae')
        );
        $out .= \html_writer::tag('p', implode(' | ', array_unique($links)), ['class' => 'small']);

        return $out . self::before_pos_leaves($candidateid, $code, $bloc->leaves, $evaluated, $context, $older['eval'], $selfpos);
    }

    /**
     * The vaeiste's own word on a node (lot 1a), as a line to read next to the structure's: no gap, no link to write from here.
     *
     * @param \stdClass|null $latest The vaeiste's line that stands on the node, if any.
     * @return string HTML; empty when the vaeiste gave no level, or withdrew it.
     */
    protected static function before_pos_selfpos_line(?\stdClass $latest): string {
        if (!selfpos_manager::standing($latest)) {
            return '';
        }

        return \html_writer::div(s(get_string('selfposonevaluation', 'tool_vae', (object) [
            'label' => $latest->levellabel,
            'when' => userdate((int) $latest->timecreated, get_string('strftimedate', 'langconfig')),
        ])), 'small text-muted ms-3 tool-vae-selfpos-line');
    }

    /**
     * What the record this one replaces positioned, as a line to read: no link, nothing to write on it from here.
     *
     * @param \stdClass[] $entries As positioning_successor::readings() gives them for one rank.
     * @param string $kind eval or certif.
     * @return string HTML.
     */
    protected static function before_pos_older_lines(array $entries, string $kind): string {
        $out = '';
        foreach ($entries as $entry) {
            $when = $kind === 'certif'
                ? (string) $entry->decisiondate
                : userdate((int) $entry->time, get_string('strftimedate', 'langconfig'));
            $text = get_string('positioningonold', 'tool_vae', (object) [
                'code' => $entry->fromcode,
                'rank' => $entry->fromrank,
                'status' => get_string($kind . 'status' . $entry->status, 'tool_vae'),
                'when' => $when,
            ]);
            if ($kind === 'certif' && !empty($entry->validuntil)) {
                $text .= ' - ' . get_string('positioninguntil', 'tool_vae', $entry->validuntil);
            }
            $out .= \html_writer::div(s($text), 'small text-muted ms-3');
        }

        return $out;
    }

    /**
     * The competencies of a bloc (or standing alone), each with the line that stands.
     *
     * @param int $candidateid
     * @param string $code
     * @param \stdClass[] $leaves Rank => label, gone.
     * @param \stdClass[] $evaluated Rank => latest evaluation.
     * @param \context $context
     * @param array $older Rank => what the record this one replaces positioned on the equivalent competency.
     * @param \stdClass[] $selfpos Rank => the vaeiste's own line that stands (lot 1a).
     * @return string
     */
    protected static function before_pos_leaves(
        int $candidateid,
        string $code,
        array $leaves,
        array $evaluated,
        \context $context,
        array $older,
        array $selfpos = []
    ): string {
        $items = [];
        $maywrite = evaluation_manager::can_write($candidateid);
        foreach ($leaves as $rank => $leaf) {
            $line = $evaluated[(string) $rank] ?? null;
            $text = \html_writer::tag('strong', s($leaf->label));
            if ($leaf->gone) {
                $text .= ' ' . \html_writer::tag(
                    'span',
                    s(get_string('coveragegone', 'tool_vae')),
                    ['class' => 'small text-muted']
                );
            }
            if ($line === null || $line->status === positioning_rules::WITHDRAWN) {
                $text .= ' - ' . s(get_string('positioningnone', 'tool_vae'));
            } else {
                $text .= self::before_pos_line($line, $context, 'eval');
            }
            $text .= self::before_pos_older_lines($older[(string) $rank] ?? [], 'eval');
            $text .= self::before_pos_selfpos_line($selfpos[(string) $rank] ?? null);
            $address = positioning_screen::url('evaluate', $candidateid, $code, (string) $rank);
            $links = [\html_writer::link($address, get_string('positioninghistory', 'tool_vae'))];
            if ($maywrite) {
                array_unshift($links, \html_writer::link(
                    positioning_screen::url('evaluate', $candidateid, $code, (string) $rank),
                    get_string('positioningevaluatelink', 'tool_vae')
                ));
            }
            $items[] = \html_writer::tag(
                'li',
                $text . \html_writer::tag('span', ' ' . implode(' | ', array_unique($links)), ['class' => 'small'])
            );
        }

        return \html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled ms-3']);
    }

    /**
     * One line: its status, who and when, and its reason.
     *
     * @param \stdClass $row A row of tool_vae_eval or tool_vae_certif.
     * @param \context $context
     * @param string $kind eval or certif.
     * @return string HTML.
     */
    protected static function before_pos_line(\stdClass $row, \context $context, string $kind): string {
        $status = s(get_string($kind . 'status' . $row->status, 'tool_vae'));
        if ($kind === 'certif' && !empty($row->decisiondate)) {
            $status .= ' (' . s(get_string('positioningcertifiedon', 'tool_vae', $row->decisiondate)) . ')';
        }
        if ($kind === 'certif' && !empty($row->validuntil)) {
            $status .= ' - ' . s(get_string('positioninguntil', 'tool_vae', $row->validuntil));
        }
        $out = \html_writer::tag(
            'div',
            \html_writer::tag('strong', $status) . ' ' . s(self::before_pos_signature($row)),
            ['class' => 'small']
        );
        $out .= \html_writer::div(format_text($row->reason, FORMAT_PLAIN, ['context' => $context, 'filter' => false]));
        if (!empty($row->alertreason)) {
            $out .= \html_writer::div(
                \html_writer::tag('em', s(get_string('positioningalertreasonlabel', 'tool_vae')) . ' : ')
                . format_text($row->alertreason, FORMAT_PLAIN, ['context' => $context, 'filter' => false]),
                'small'
            );
        }

        return \html_writer::div($out, 'ms-3 mb-2');
    }

    /**
     * Who wrote a line and when, in words; no identifier.
     *
     * @param \stdClass $row
     * @return string
     */
    protected static function before_pos_signature(\stdClass $row): string {
        $when = userdate((int) $row->timecreated, get_string('strftimedatetime', 'langconfig'));
        if (!empty($row->importedat)) {
            return $when . ' - ' . get_string('positioningrestored', 'tool_vae');
        }
        $who = '';
        if (!empty($row->authorid)) {
            $author = \core_user::get_user((int) $row->authorid);
            $who = $author && empty($author->deleted) ? fullname($author) : get_string('positioningauthorgone', 'tool_vae');
        } else {
            $who = get_string('positioningauthorgone', 'tool_vae');
        }

        return $who . ', ' . $when;
    }

    /**
     * The history of a rank: the line that stands, then the earlier ones.
     *
     * @param \stdClass[] $rows The rows, the earliest first.
     * @param \context $context
     * @param string $kind eval or certif.
     * @return string HTML.
     */
    protected static function before_pos_history(array $rows, \context $context, string $kind): string {
        if (!$rows) {
            return \html_writer::tag('p', s(get_string('positioningnoline', 'tool_vae')), ['class' => 'text-muted']);
        }
        $rows = array_values($rows);
        $standing = array_pop($rows);
        $out = \html_writer::tag('h4', s(get_string('positioningcurrent', 'tool_vae')), ['class' => 'h5'])
            . self::before_pos_line($standing, $context, $kind);
        if ($rows) {
            $out .= \html_writer::tag('h4', s(get_string('positioningearlier', 'tool_vae')), ['class' => 'h5']);
            foreach (array_reverse($rows) as $row) {
                $out .= self::before_pos_line($row, $context, $kind);
            }
        }

        return \html_writer::div($out, 'tool-vae-history');
    }

    /**
     * The two sentences every screen carries: visible to the manager, not a validation.
     *
     * Said to the vaeiste on their own screens ("what you write"), and of the vaeiste to anyone else who reads them
     * (the manager who follows, the direction): a reader is never told what to write.
     *
     * @param int $candidateid The vaeiste whose screen it is.
     * @return string HTML.
     */
    protected static function before_selfpos_disclaimer(int $candidateid): string {
        global $USER;

        $own = (int) $USER->id === $candidateid;

        return \html_writer::tag(
            'p',
            s(get_string($own ? 'selfposvisible' : 'selfposvisibleread', 'tool_vae')) . ' '
                . s(get_string($own ? 'selfposnotvalidation' : 'selfposnotvalidationread', 'tool_vae')),
            ['class' => 'text-muted small tool-vae-selfpos-disclaimer']
        );
    }

    /**
     * The records the vaeiste may position themselves on, and the ones that are hidden, said why.
     *
     * @param int $candidateid
     * @param \stdClass[] $sources The rows of tool_vae_source that are offered, by code.
     * @param string[] $hidden Code => the message of a record that is hidden.
     * @return string HTML.
     */
    protected static function before_selfpos_records(int $candidateid, array $sources, array $hidden): string {
        $out = self::before_selfpos_disclaimer($candidateid);
        foreach ($hidden as $message) {
            // Already escaped by hidden_frameworks::message(): a second pass would show entities in the title.
            $out .= \html_writer::div($message, 'alert alert-info');
        }
        if (!$sources) {
            return $out . \html_writer::tag('p', s(get_string('selfposnorecord', 'tool_vae')), ['class' => 'text-muted']);
        }
        $items = [];
        foreach ($sources as $source) {
            $items[] = \html_writer::tag('li', \html_writer::link(
                selfpos_screen::url($candidateid, (string) $source->fichecode),
                s((string) $source->fichecode)
            ));
        }

        return $out . \html_writer::tag('p', s(get_string('selfposchoose', 'tool_vae')))
            . \html_writer::tag('ul', implode('', $items));
    }

    /**
     * The tree of a record, every node with its level name, the vaeiste's current level and its date, and the way to its page.
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param \stdClass $tree As selfpos_tree::for_record() gives it.
     * @param array $current Rank => the line that stands, as selfpos_manager::current() gives it.
     * @param bool $canwrite Whether the reader is the vaeiste and may position themselves.
     * @return string HTML.
     */
    protected static function before_selfpos_tree(
        int $candidateid,
        string $fichecode,
        \stdClass $tree,
        array $current,
        bool $canwrite
    ): string {
        $items = [];
        foreach ($tree->nodes as $node) {
            $text = \html_writer::tag('span', s($node->levelname), ['class' => 'small text-muted me-2'])
                . \html_writer::tag('strong', s($node->label));
            if (!$node->positionable) {
                $text .= ' ' . \html_writer::tag('span', s(get_string('selfposexplainnotpositionable', 'tool_vae')), [
                    'class' => 'small text-muted',
                ]);
            } else {
                $text .= ' - ' . self::before_selfpos_level_words($current[(string) $node->rank] ?? null);
                $text .= ' ' . \html_writer::tag('span', \html_writer::link(
                    selfpos_screen::url($candidateid, $fichecode, (string) $node->rank),
                    get_string($canwrite ? 'selfposnodelink' : 'selfposreadlink', 'tool_vae')
                ), ['class' => 'small']);
            }
            $items[] = \html_writer::tag('li', $text, [
                'class' => 'tool-vae-selfpos-node',
                'style' => 'margin-left: ' . (((int) $node->level - 1) * 1.5) . 'rem',
                'data-level' => (string) $node->level,
            ]);
        }

        // The invitation to say where one stands is for the one who writes; a reader gets the tree alone.
        return \html_writer::start_tag('section', ['class' => 'tool-vae-selfpos'])
            . self::before_selfpos_disclaimer($candidateid)
            . ($canwrite ? \html_writer::tag('p', s(get_string('selfposintro', 'tool_vae'))) : '')
            . \html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled tool-vae-selfpos-tree'])
            . \html_writer::end_tag('section');
    }

    /**
     * One node: the form (given by the page), the word of the structure next to the vaeiste's own, the lines of the record
     * before, and the history.
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param \stdClass $node A node of selfpos_tree::for_record().
     * @param \stdClass[] $lines Every line of the node, the earliest first.
     * @param \stdClass $regard As selfpos_regard::for_node() gives it.
     * @param array $successor What the record this one replaces positioned on the equivalent node, as selfpos_successor gives it.
     * @param bool $canwrite Whether the reader is the vaeiste and may position themselves.
     * @param string $formhtml The form, rendered, when there is one to show.
     * @return string HTML.
     */
    protected static function before_selfpos_node(
        int $candidateid,
        string $fichecode,
        \stdClass $node,
        array $lines,
        \stdClass $regard,
        array $successor,
        bool $canwrite,
        string $formhtml = ''
    ): string {
        $context = \context_user::instance($candidateid);
        $out = self::before_selfpos_disclaimer($candidateid);
        $out .= \html_writer::tag(
            'p',
            \html_writer::tag('span', s($node->levelname), ['class' => 'small text-muted me-2'])
            . \html_writer::tag('strong', s($fichecode)) . ' - ' . s($node->label)
        );
        if (!$node->positionable) {
            $out .= \html_writer::tag('p', s(get_string('selfposexplainnotpositionable', 'tool_vae')), ['class' => 'text-muted']);
        } else if ($canwrite && $formhtml !== '') {
            $out .= $formhtml;
        }

        // The word of the structure, next to the vaeiste's own, without any gap.
        $out .= \html_writer::tag('h3', s(get_string('selfposregard', 'tool_vae')), ['class' => 'h5']);
        if ($regard->structure === null) {
            $out .= \html_writer::tag('p', s(get_string('selfposregardnone', 'tool_vae')), ['class' => 'text-muted']);
        } else {
            $out .= \html_writer::tag('p', s(get_string('selfposregardline', 'tool_vae', (object) [
                'status' => get_string($regard->structure->kind . 'status' . $regard->structure->status, 'tool_vae'),
                'when' => userdate($regard->structure->time, get_string('strftimedate', 'langconfig')),
            ])));
        }
        if ($regard->declarations > 0) {
            $out .= \html_writer::tag('p', s(get_string('selfposdeclarations', 'tool_vae', (object) [
                'declarations' => $regard->declarations, 'attested' => $regard->attested, 'pieces' => $regard->pieces,
            ])), ['class' => 'small']);
        }
        foreach ($successor as $entry) {
            $out .= \html_writer::tag('p', s(get_string('selfposonold', 'tool_vae', (object) [
                'code' => $entry->fromcode,
                'rank' => $entry->fromrank,
                'label' => $entry->levellabel,
                'when' => userdate($entry->time, get_string('strftimedate', 'langconfig')),
            ])), ['class' => 'small text-muted']);
        }

        $out .= \html_writer::tag('h3', s(get_string('selfposhistory', 'tool_vae')), ['class' => 'h5']);
        $out .= self::before_selfpos_history($lines, $context);
        $out .= \html_writer::tag('p', \html_writer::link(
            selfpos_screen::url($candidateid, $fichecode),
            get_string('selfposback', 'tool_vae')
        ));

        return \html_writer::tag('section', $out, ['class' => 'tool-vae-selfpos']);
    }

    /**
     * The history of a node: the line that stands, then the earlier ones.
     *
     * @param \stdClass[] $rows The rows, the earliest first.
     * @param \context $context
     * @return string HTML.
     */
    protected static function before_selfpos_history(array $rows, \context $context): string {
        if (!$rows) {
            return \html_writer::tag('p', s(get_string('selfposnoline', 'tool_vae')), ['class' => 'text-muted']);
        }
        $rows = array_values($rows);
        $standing = array_pop($rows);
        $out = \html_writer::tag('h4', s(get_string('selfposstanding', 'tool_vae')), ['class' => 'h6'])
            . self::before_selfpos_line($standing, $context);
        if ($rows) {
            $out .= \html_writer::tag('h4', s(get_string('selfposearlier', 'tool_vae')), ['class' => 'h6']);
            foreach (array_reverse($rows) as $row) {
                $out .= self::before_selfpos_line($row, $context);
            }
        }

        return \html_writer::div($out, 'tool-vae-history');
    }

    /**
     * One line: its step, its date, and its justification.
     *
     * @param \stdClass $row A row of tool_vae_selfpos.
     * @param \context $context
     * @return string HTML.
     */
    protected static function before_selfpos_line(\stdClass $row, \context $context): string {
        $when = userdate((int) $row->timecreated, get_string('strftimedatetime', 'langconfig'));
        if (!empty($row->importedat)) {
            $when .= ' - ' . get_string('selfposrestored', 'tool_vae');
        }
        $out = \html_writer::tag(
            'div',
            \html_writer::tag('strong', s((string) $row->levellabel)) . ' ' . s($when),
            ['class' => 'small']
        );
        if ((string) $row->justification !== '') {
            $format = (int) $row->justificationformat === 4 ? FORMAT_MARKDOWN : FORMAT_PLAIN;
            $options = ['context' => $context, 'filter' => false];
            $out .= \html_writer::div(format_text((string) $row->justification, $format, $options));
        }

        return \html_writer::div($out, 'ms-3 mb-2');
    }

    /**
     * The hidden record: its message, and the text the vaeiste had typed, given back.
     *
     * @param string $message The message of hidden_frameworks for the record, its title already escaped.
     * @param string $text What was typed, if anything.
     * @return string HTML.
     */
    protected static function before_selfpos_hidden(string $message, string $text): string {
        // The message comes from hidden_frameworks::message(), its title already escaped: shown as it is, once.
        $out = \html_writer::div($message, 'alert alert-' . ($text === '' ? 'info' : 'danger'));
        if (trim($text) !== '') {
            $out .= \html_writer::tag('p', s(get_string('selfposhiddenkept', 'tool_vae')))
                . \html_writer::tag('textarea', s($text), [
                    'readonly' => 'readonly', 'rows' => 8, 'cols' => 70, 'class' => 'form-control mb-3',
                ]);
        }

        return $out;
    }

    /**
     * The vaeiste's current level on a node, in words: the label and the date, or that there is none.
     *
     * @param \stdClass|null $latest The line that stands, if any.
     * @return string HTML.
     */
    protected static function before_selfpos_level_words(?\stdClass $latest): string {
        if (!selfpos_manager::standing($latest)) {
            return s(get_string('selfposnone', 'tool_vae'));
        }

        return s(get_string('selfposcurrent', 'tool_vae', (object) [
            'label' => $latest->levellabel,
            'when' => userdate((int) $latest->timecreated, get_string('strftimedate', 'langconfig')),
        ]));
    }
}
