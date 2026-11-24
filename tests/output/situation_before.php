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

use tool_vae\local\note_manager;
use tool_vae\local\note_screen;
use tool_vae\local\piece_manager;
use tool_vae\local\selfpos_screen;
use tool_vae\local\selfpos_tree;
use tool_vae\local\situation_screen;
use tool_vae\local\spiece_manager;
use tool_vae\local\validation_status;

/**
 * The HTML the screens situation_screen and note_screen wrote before lot 7 (MMR-520 ticket 4, wave 4a).
 *
 * Their code as it was in classes/local/ before their migration to pieces of classes/output/, kept here as the expected
 * value of the tests that prove each piece and each page draws the same DOM. The only changes: each method is "protected
 * static" and named before_situation_... or before_note_..., the calls between them follow, and the calls to the readings
 * that stay in classes/local/ name their class: situation_screen::url(), title(), period(), node_words(), and
 * note_screen::author(). Nothing else is touched: the escaping, the order, the classes and the spaces are those of the old
 * code.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait situation_before {
    /**
     * The two sentences every screen carries: who reads it, and that nothing here is attested.
     *
     * @param int $candidateid The vaeiste whose screen it is.
     * @return string HTML.
     */
    protected static function before_situation_disclaimer(int $candidateid): string {
        global $USER;

        $own = (int) $USER->id === $candidateid;

        return \html_writer::tag(
            'p',
            s(get_string($own ? 'selfposvisible' : 'selfposvisibleread', 'tool_vae')) . ' '
                . s(get_string('situationnotattested', 'tool_vae')),
            ['class' => 'text-muted small tool-vae-situation-disclaimer']
        );
    }

    /**
     * The records a vaeiste has situations on or may open one on, each leading to its list.
     *
     * @param int $candidateid
     * @param array $records Code => null for a record offered, or the message of a record hidden (already escaped by
     *        hidden_frameworks::message()) that still holds situations.
     * @return string HTML.
     */
    protected static function before_situation_records(int $candidateid, array $records): string {
        $out = self::before_situation_disclaimer($candidateid);
        if (!$records) {
            return $out . \html_writer::tag('p', s(get_string('selfposnorecord', 'tool_vae')), ['class' => 'text-muted']);
        }
        $items = [];
        foreach ($records as $code => $message) {
            $item = \html_writer::link(situation_screen::url($candidateid, (string) $code), s((string) $code));
            if ($message !== null) {
                // Already escaped by hidden_frameworks::message(): a second pass would show entities in the title.
                $item .= \html_writer::div($message, 'alert alert-info mt-1');
            }
            $items[] = \html_writer::tag('li', $item);
        }

        return $out . \html_writer::tag('p', s(get_string('selfposchoose', 'tool_vae')))
            . \html_writer::tag('ul', implode('', $items));
    }

    /**
     * The situations of a record, in the order of their slots: title, period, setting, tags, pieces, where the text stands.
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param \stdClass[] $rows As situation_manager::list() gives them.
     * @return string HTML.
     */
    protected static function before_situation_list(int $candidateid, string $fichecode, array $rows): string {
        if (!$rows) {
            return \html_writer::tag('p', s(get_string('situationnone', 'tool_vae')), ['class' => 'text-muted']);
        }
        $tree = selfpos_tree::for_record($fichecode);
        $items = [];
        foreach ($rows as $row) {
            $lines = self::before_situation_facts($row, $tree);
            $lines[] = s(get_string('situationpieces', 'tool_vae', (int) $row->pieces));
            $lines[] = s(validation_status::words($row->status));
            $items[] = \html_writer::tag(
                'li',
                \html_writer::tag('strong', \html_writer::link(
                    situation_screen::url($candidateid, $fichecode, (int) $row->slot),
                    s(situation_screen::title($row))
                )) . \html_writer::div(implode('<br>', $lines), 'small'),
                ['class' => 'mb-3 tool-vae-situation-item', 'data-slot' => (string) (int) $row->slot]
            );
        }

        return \html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled tool-vae-situations']);
    }

    /**
     * The details of one situation, in reading: its CURRENT title, period, setting and tags (D-1b-I).
     *
     * @param \stdClass $situation The row, with tags.
     * @return string HTML.
     */
    protected static function before_situation_details(\stdClass $situation): string {
        $lines = self::before_situation_facts($situation, selfpos_tree::for_record((string) $situation->fichecode));

        return \html_writer::div(implode('<br>', $lines), 'tool-vae-situation-details mb-3');
    }

    /**
     * The text of a situation for a reader: the latest version saved, then, apart, the draft in progress said to be one
     * (D-1b-H).
     *
     * @param \stdClass|null $latest The latest version (a row of tool_vae_version), or null.
     * @param \stdClass $draft The row of tool_vae_draft that holds the text.
     * @param \context $context The vaeiste's context.
     * @return string HTML.
     */
    protected static function before_situation_text(?\stdClass $latest, \stdClass $draft, \context $context): string {
        $plain = fn(string $text): string => format_text($text, FORMAT_PLAIN, ['context' => $context, 'filter' => false]);
        if ($latest === null) {
            $out = \html_writer::tag('p', s(get_string('situationnoversion', 'tool_vae')), ['class' => 'text-muted']);
        } else {
            $out = \html_writer::tag(
                'p',
                s(get_string('sectioncurrentversion', 'tool_vae', (int) $latest->number)),
                ['class' => 'text-muted small mb-1']
            ) . \html_writer::div($plain((string) $latest->content), 'mb-2 tool-vae-situation-text');
        }
        if ($draft->working !== null && trim((string) $draft->working) !== '') {
            $out .= \html_writer::div(
                \html_writer::tag('p', s(get_string('situationworkingnotice', 'tool_vae')), ['class' => 'text-muted small mb-1'])
                    . $plain((string) $draft->working),
                'border-start ps-2 mb-2 tool-vae-situation-working'
            );
        }

        return $out;
    }

    /**
     * The pieces of a situation: one line per link that stands, with the part concerned; the piece opened by whoever may.
     *
     * @param \stdClass[] $links As spiece_manager::for_situation() gives them, each with `piece`.
     * @param \context_user $context The vaeiste's context.
     * @param callable|null $actions Given a link, the HTML of what the vaeiste may do with it (detach, remove); null for a reader.
     * @return string HTML.
     */
    protected static function before_situation_pieces(array $links, \context_user $context, ?callable $actions = null): string {
        $out = \html_writer::tag('h3', s(get_string('spiecesheading', 'tool_vae')), ['class' => 'h5 mt-4']);
        if (!$links) {
            return $out . \html_writer::tag('p', s(get_string('piecenone', 'tool_vae')), ['class' => 'text-muted']);
        }
        $date = get_string('strftimedatetime', 'langconfig');
        $items = '';
        foreach ($links as $link) {
            $piece = $link->piece;
            $mayopen = spiece_manager::may_open($piece);
            $line = \html_writer::tag('strong', s(get_string('piecenature:' . $piece->nature, 'tool_vae')));
            if ((string) $piece->title !== '' && $mayopen) {
                $line .= ' - ' . s((string) $piece->title);
            }
            if ((string) $link->part !== '') {
                $line .= \html_writer::div(
                    \html_writer::span(s(get_string('spiecepart', 'tool_vae')), 'text-muted me-2') . s((string) $link->part),
                    'small'
                );
            }
            $details = [];
            if ($piece->kind === piece_manager::KIND_FILE && $piece->timeremoved === null) {
                $details[] = s(strtoupper((string) $piece->ext) . ', ' . display_size((int) $piece->filesize));
            }
            $details[] = s(userdate((int) $piece->timecreated, $date));
            $line .= \html_writer::div(implode(' - ', $details), 'text-muted small');
            if ($piece->timeremoved !== null) {
                $line .= \html_writer::div(s(get_string('spieceremoved', 'tool_vae')), 'text-muted');
            } else if ($mayopen) {
                $line .= self::before_situation_piece_access($piece, $context);
            }
            if ($actions !== null) {
                $line .= $actions($link);
            }
            $items .= \html_writer::tag('li', $line, ['class' => 'mb-3 tool-vae-piece']);
        }

        return $out . \html_writer::tag('ul', $items, ['class' => 'list-unstyled tool-vae-pieces']);
    }

    /**
     * The three ways the vaeiste adds a piece to a situation: a file, a link, a piece they already have. The file is a form of
     * the core with its file picker (spiece_file_form), built and read by the page; the link and the piece already there are
     * plain forms of the page, sent with the session key.
     *
     * @param \moodle_url $url The page of the situation.
     * @param \stdClass[] $attachable The pieces of the vaeiste on the record that may be attached here (not removed, not
     *        linked to this situation already).
     * @param \tool_vae\form\spiece_file_form $fileform The form of a file, as the page built it.
     * @return string HTML.
     */
    protected static function before_situation_piece_forms(
        \moodle_url $url,
        array $attachable,
        \tool_vae\form\spiece_file_form $fileform
    ): string {
        $natures = ['' => get_string('piecenaturechoose', 'tool_vae')];
        foreach (spiece_manager::NATURES as $nature) {
            $natures[$nature] = get_string('piecenature:' . $nature, 'tool_vae');
        }
        $reminder = \html_writer::div(get_string('anonymisepiece', 'tool_vae'), 'alert alert-info tool-vae-anonymise', [
            'id' => 'tool_vae_anonymise_spiece',
        ]);
        $described = ['aria-describedby' => 'tool_vae_anonymise_spiece', 'class' => 'form-control'];
        $field = function (string $label, string $id, string $input): string {
            return \html_writer::div(\html_writer::label($label, $id) . $input, 'mb-2');
        };
        $common = function (string $kind) use ($natures, $described, $field): string {
            return \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
                . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $kind])
                . $field(
                    get_string('piecenature', 'tool_vae'),
                    'id_' . $kind . 'nature',
                    \html_writer::select($natures, 'nature', '', false, [
                        'id' => 'id_' . $kind . 'nature', 'required' => 'required', 'class' => 'form-select',
                    ])
                )
                . $field(get_string('piecestitle', 'tool_vae'), 'id_' . $kind . 'title', \html_writer::empty_tag('input', [
                    'type' => 'text', 'name' => 'title', 'id' => 'id_' . $kind . 'title', 'size' => 60,
                    'maxlength' => piece_manager::TITLE_MAXLENGTH,
                ] + $described));
        };
        $part = function (string $kind) use ($described, $field): string {
            return $field(get_string('spiecepart', 'tool_vae'), 'id_' . $kind . 'part', \html_writer::empty_tag('input', [
                'type' => 'text', 'name' => 'part', 'id' => 'id_' . $kind . 'part', 'size' => 60,
                'maxlength' => spiece_manager::PART_MAXLENGTH,
            ] + $described));
        };
        $submit = fn(string $label): string => \html_writer::empty_tag('input', [
            'type' => 'submit', 'value' => $label, 'class' => 'btn btn-secondary',
        ]);

        $out = $reminder;
        // The file: a form of the core with its file picker (spiece_file_form), built and read by the page.
        $out .= $fileform->render();

        $out .= \html_writer::tag('form', \html_writer::tag('h4', s(get_string('piecelinkheading', 'tool_vae')), [
            'class' => 'h6',
        ])
            . \html_writer::tag('p', s(get_string('piecelinkhelp', 'tool_vae')), ['class' => 'small'])
            . $common('link')
            . $field(get_string('pieceurllabel', 'tool_vae'), 'id_url', \html_writer::empty_tag('input', [
                'type' => 'text', 'name' => 'url', 'id' => 'id_url', 'size' => 60, 'required' => 'required',
                'maxlength' => piece_manager::URL_MAXLENGTH,
            ] + $described))
            . $part('link')
            . $submit(get_string('pieceaddlinksubmit', 'tool_vae')), [
                'method' => 'post', 'action' => $url->out(false), 'class' => 'mb-4 tool-vae-spiece-link',
            ]);

        if ($attachable) {
            $choices = [];
            foreach ($attachable as $piece) {
                $words = get_string('piecenature:' . $piece->nature, 'tool_vae');
                if ((string) $piece->title !== '') {
                    $words .= ' - ' . $piece->title;
                }
                // Escaped here: the core writes the label of an option as it is given, and the title is the vaeiste's text.
                $choices[(int) $piece->id] = s($words . ' (' . userdate((int) $piece->timecreated, get_string(
                    'strftimedate',
                    'langconfig'
                )) . ')');
            }
            $out .= \html_writer::tag('form', \html_writer::tag('h4', s(get_string('spieceattach', 'tool_vae')), [
                'class' => 'h6',
            ])
                . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
                . \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'attach'])
                . $field(
                    get_string('spieceattach', 'tool_vae'),
                    'id_attachpiece',
                    \html_writer::select($choices, 'piece', '', false, ['id' => 'id_attachpiece', 'class' => 'form-select'])
                )
                . $part('attach')
                . $submit(get_string('spieceattach', 'tool_vae')), [
                    'method' => 'post', 'action' => $url->out(false), 'class' => 'mb-4 tool-vae-spiece-attach',
                ]);
        }

        return $out;
    }

    /**
     * The inverse view of a node: the situations tagged on it and the declarations that carry it, in reading.
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param string $rank
     * @param \stdClass[] $situations As situation_manager::tagged() gives them.
     * @param \stdClass[] $declarations Rows of tool_vae_evidence on the node.
     * @param \context_user $context The vaeiste's context.
     * @return string HTML.
     */
    protected static function before_situation_inverse(
        int $candidateid,
        string $fichecode,
        string $rank,
        array $situations,
        array $declarations,
        \context_user $context
    ): string {
        global $PAGE;

        $tree = selfpos_tree::for_record($fichecode);
        $out = self::before_situation_disclaimer($candidateid);
        $out .= \html_writer::tag('h3', s(get_string('situationsonnode', 'tool_vae', situation_screen::node_words($tree, $rank))), [
            'class' => 'h4',
        ]);

        if (!$situations) {
            $out .= \html_writer::tag('p', s(get_string('situationnone', 'tool_vae')), ['class' => 'text-muted']);
        } else {
            $items = [];
            foreach ($situations as $row) {
                $items[] = \html_writer::tag(
                    'li',
                    \html_writer::tag('strong', \html_writer::link(
                        situation_screen::url($candidateid, $fichecode, (int) $row->slot),
                        s(situation_screen::title($row))
                    )) . \html_writer::div(implode('<br>', self::before_situation_facts($row, $tree)), 'small'),
                    ['class' => 'mb-3 tool-vae-situation-item', 'data-slot' => (string) (int) $row->slot]
                );
            }
            $out .= \html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled tool-vae-situations']);
        }

        $out .= \html_writer::tag('h3', s(get_string('situationdeclarations', 'tool_vae')), ['class' => 'h5']);
        if (!$declarations) {
            $out .= \html_writer::tag('p', s(get_string('situationnodeclaration', 'tool_vae')), ['class' => 'text-muted']);
        }
        foreach ($declarations as $evidence) {
            $out .= \html_writer::div(
                $PAGE->get_renderer('tool_vae')->render(new \tool_vae\output\declaration($evidence, $context)),
                'border-start ps-3 mb-3 tool-vae-inverse-decl'
            );
        }

        return $out;
    }

    /**
     * The closed preparation: its message, and what the vaeiste had typed, given back.
     *
     * @param string[] $kept As kept_text() gives it.
     * @return string HTML.
     */
    protected static function before_situation_closed(array $kept): string {
        return self::before_situation_refused(s(get_string('errorvalidationclosed', 'tool_vae')), $kept);
    }

    /**
     * A situation that may not be written now - the preparation closed, the record hidden: the message, and what the vaeiste
     * had typed, given back, never lost.
     *
     * @param string $message HTML, already escaped (hidden_frameworks::message() escapes its title).
     * @param string[] $kept As kept_text() gives it.
     * @return string HTML.
     */
    protected static function before_situation_refused(string $message, array $kept): string {
        $out = \html_writer::div($message, 'alert alert-' . ($kept ? 'danger' : 'info'));
        if (!$kept) {
            return $out;
        }
        $out .= \html_writer::tag('p', s(get_string('selfposhiddenkept', 'tool_vae')));
        $labels = ['title' => 'situationtitle', 'setting' => 'situationsetting', 'content' => 'situationtext'];
        foreach ($kept as $field => $text) {
            $out .= \html_writer::tag('textarea', s($text), [
                'readonly' => 'readonly', 'rows' => $field === 'content' ? 8 : 1, 'cols' => 70, 'class' => 'form-control mb-3',
                'aria-label' => get_string($labels[$field], 'tool_vae'),
            ]);
        }

        return $out;
    }

    /**
     * The lines that say a situation: period, setting, tags - each only when it has something to say.
     *
     * @param \stdClass $row A row of tool_vae_situation, with tags.
     * @param \stdClass|null $tree The tree of its record, to name the tags.
     * @return string[] HTML lines.
     */
    protected static function before_situation_facts(\stdClass $row, ?\stdClass $tree): array {
        $lines = [];
        $period = situation_screen::period(
            $row->periodstart === null ? null : (int) $row->periodstart,
            $row->periodend === null ? null : (int) $row->periodend
        );
        if ($period !== '') {
            $lines[] = \html_writer::span(s(get_string('situationperiod', 'tool_vae')), 'text-muted me-2') . s($period);
        }
        if ((string) $row->setting !== '') {
            $lines[] = \html_writer::span(s(get_string('situationsetting', 'tool_vae')), 'text-muted me-2')
                . s((string) $row->setting);
        }
        if (!empty($row->tags)) {
            $tags = array_map(fn(string $rank) => s(situation_screen::node_words($tree, $rank)), $row->tags);
            $lines[] = \html_writer::span(s(get_string('situationtags', 'tool_vae')), 'text-muted me-2') . implode(' ; ', $tags);
        }

        return $lines;
    }

    /**
     * How a reader gets at a piece of a situation: the link, or the file, to open.
     *
     * @param \stdClass $piece A row of tool_vae_spiece, not removed, that the reader may open.
     * @param \context_user $context
     * @return string HTML.
     */
    protected static function before_situation_piece_access(\stdClass $piece, \context_user $context): string {
        if ($piece->kind === piece_manager::KIND_LINK) {
            // The whole address, as text, and its host as the network reads it; the click is the reader's.
            return \html_writer::div(s((string) $piece->url))
                . \html_writer::div(s(piece_manager::display_host((string) $piece->url)), 'text-muted small')
                . \html_writer::div(\html_writer::link(
                    (string) $piece->url,
                    get_string('piecelinkopen', 'tool_vae'),
                    ['rel' => 'noopener noreferrer', 'target' => '_blank']
                ));
        }

        return \html_writer::div(\html_writer::link(
            \moodle_url::make_pluginfile_url(
                $context->id,
                'tool_vae',
                spiece_manager::FILEAREA,
                (int) $piece->id,
                '/',
                $piece->uuid . '.' . $piece->ext,
                true
            ),
            get_string('pieceopenfile', 'tool_vae')
        ));
    }

    /**
     * The block of the annotations of one target.
     *
     * @param int $candidateid
     * @param string $target note_manager::TARGET_SITUATION or TARGET_SELFPOS.
     * @param string $fichecode
     * @param int $slot
     * @param string $ficherang
     * @param \stdClass[] $rows The lines of note_manager::for_target(), the earliest first, each with ->standing.
     * @param bool $canwrite Whether the current user may annotate (note_manager::can_write()).
     * @param string $formhtml The form, drawn for whoever may annotate only.
     * @return string HTML.
     */
    protected static function before_note_block(
        int $candidateid,
        string $target,
        string $fichecode,
        int $slot,
        string $ficherang,
        array $rows,
        bool $canwrite,
        string $formhtml = ''
    ): string {
        $url = $target === note_manager::TARGET_SITUATION
            ? situation_screen::url($candidateid, $fichecode, $slot)
            : selfpos_screen::url($candidateid, $fichecode, $ficherang);
        $out = \html_writer::tag('h3', s(get_string('notesheading', 'tool_vae')), ['class' => 'h5']);
        $out .= self::before_note_lines($candidateid, $rows, $url);
        if ($canwrite && $formhtml !== '') {
            $out .= $formhtml;
        }

        return \html_writer::tag('section', $out, ['class' => 'tool-vae-notes mt-4']);
    }

    /**
     * The annotations of the situations of a record that were deleted (D-1b-K): each former situation apart, said deleted.
     *
     * @param int $candidateid
     * @param \stdClass[] $rows The lines of note_manager::for_candidate() whose situation is gone, the earliest first.
     * @return string HTML; '' when there is none.
     */
    protected static function before_note_orphans(int $candidateid, array $rows): string {
        if (!$rows) {
            return '';
        }
        $groups = [];
        foreach ($rows as $row) {
            $groups[(string) $row->targetuuid][] = $row;
        }
        $out = \html_writer::tag('h3', s(get_string('notesheading', 'tool_vae')), ['class' => 'h5']);
        foreach ($groups as $group) {
            $first = reset($group);
            $out .= \html_writer::tag('h4', s(get_string('validpart:situation', 'tool_vae', (int) $first->slot)) . ' - '
                . s(get_string('notetargetgone', 'tool_vae')), ['class' => 'h6']);
            // The author takes back their word here too: the list of the situations is where it is still read.
            $out .= self::before_note_lines($candidateid, $group, situation_screen::url($candidateid, (string) $first->fichecode));
        }

        return \html_writer::tag('section', $out, ['class' => 'tool-vae-notes mt-4']);
    }

    /**
     * The lines, with the sentence that no annotation validates.
     *
     * @param int $candidateid
     * @param \stdClass[] $rows
     * @param \moodle_url|null $url The page the withdrawal is sent to; null draws no button.
     * @return string HTML.
     */
    protected static function before_note_lines(int $candidateid, array $rows, ?\moodle_url $url): string {
        global $OUTPUT, $USER;

        $out = \html_writer::tag('p', s(get_string('notesnotvalidation', 'tool_vae')), ['class' => 'small text-muted']);
        if (!$rows) {
            return $out . \html_writer::tag('p', s(get_string('notesnone', 'tool_vae')), ['class' => 'text-muted']);
        }
        $context = \context_user::instance($candidateid);
        foreach ($rows as $row) {
            $signature = s(get_string('noteby', 'tool_vae', (object) [
                'name' => note_screen::author($row),
                'when' => userdate((int) $row->timecreated, get_string('strftimedatetime', 'langconfig')),
            ]));
            if (!empty($row->importedat)) {
                $signature .= ' - ' . s(get_string('selfposrestored', 'tool_vae'));
            }
            if ($row->state === note_manager::WITHDRAWN) {
                $line = \html_writer::div(s(get_string('notewithdrawn', 'tool_vae')) . ' ' . $signature, 'small text-muted');
            } else {
                $format = (int) $row->textformat === (int) FORMAT_MARKDOWN ? FORMAT_MARKDOWN : FORMAT_PLAIN;
                $line = \html_writer::div($signature, 'small')
                    . \html_writer::div(format_text((string) $row->text, $format, ['context' => $context, 'filter' => false]));
                $mine = $row->authorid !== null && empty($row->importedat) && (int) $row->authorid === (int) $USER->id;
                if ($url !== null && !empty($row->standing) && $mine) {
                    $line .= $OUTPUT->single_button(
                        new \moodle_url($url, ['action' => 'withdrawnote', 'note' => (int) $row->id]),
                        get_string('notewithdraw', 'tool_vae'),
                        'post'
                    );
                }
            }
            $class = 'tool-vae-note border-start ps-2 ms-3 mb-2' . (empty($row->standing) ? ' text-muted' : '');
            $out .= \html_writer::div($line, $class);
        }

        return $out;
    }
}
