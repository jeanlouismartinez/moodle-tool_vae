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

use tool_vae\local\anchor;
use tool_vae\local\competency_gateway;
use tool_vae\local\display_date;
use tool_vae\local\importer;
use tool_vae\local\piece_manager;
use tool_vae\local\presenter;
use tool_vae\local\ref_manager;

/**
 * The HTML the screens presenter, attest_screen and hidden_frameworks::kept_text() wrote before lot 7 (MMR-520 ticket 4).
 *
 * Their code as it was in classes/local/ before its migration to pieces of classes/output/, kept here as the expected value of
 * the tests that prove each piece draws the same DOM. The only change: the renderer of the core is self::core() (the one of
 * the web, given by the trait dom_canon) where the old code read $OUTPUT. Nothing else is touched: the escaping, the order,
 * the classes and the spaces are those of the old code.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait presenter_before {
    /**
     * presenter::sources() before.
     *
     * @param \stdClass[] $sources
     * @return string
     */
    protected static function before_sources(array $sources): string {
        global $DB;

        $gateway = new competency_gateway();

        $table = new \html_table();
        $table->head = [
            get_string('sourcecode', 'tool_vae'),
            get_string('importname', 'tool_vae'),
            get_string('importfichetype', 'tool_vae'),
            get_string('sourcecompetencies', 'tool_vae'),
            get_string('sourcedeclarations', 'tool_vae'),
            get_string('sourcelastimport', 'tool_vae'),
        ];
        $table->attributes['class'] = 'generaltable tool-vae-sources';

        $dateformat = get_string('strftimedate', 'langconfig');

        foreach ($sources as $source) {
            $competencies = $gateway->count_framework_competencies((int) $source->frameworkid);
            $claims = $DB->count_records('tool_vae_evidence', ['fichecode' => $source->fichecode]);
            $gone = $DB->count_records('tool_vae_obsolete', [
                'sourceid' => (int) $source->id,
                'kind' => importer::GONE,
            ]);
            $diverged = $DB->count_records('tool_vae_obsolete', [
                'sourceid' => (int) $source->id,
                'kind' => importer::DIVERGED,
            ]);

            $status = $source->lastimportstatus === 'ok'
                ? get_string('sourcestatusok', 'tool_vae')
                : get_string('sourcestatuserror', 'tool_vae');

            $provenance = self::before_provenance($source);

            $divergences = '';
            if ($gone || $diverged) {
                $divergences = \html_writer::div(
                    s(get_string('sourcedivergences', 'tool_vae', (object) [
                        'gone' => $gone,
                        'diverged' => $diverged,
                    ])),
                    'small tool-vae-divergence'
                );
            }

            $table->data[] = [
                \html_writer::tag('strong', s($source->fichecode)) . $provenance,
                s((string) $gateway->framework_name((int) $source->frameworkid)),
                s($source->fichetype === 'rs'
                    ? get_string('typers', 'tool_vae')
                    : get_string('typerncp', 'tool_vae')),
                $competencies . $divergences,
                $claims,
                s(display_date::format((int) $source->lastimport, $dateformat)) . ' — ' . s($status),
            ];
        }

        return self::core()->heading(get_string('sourcetable', 'tool_vae'), 3)
            . \html_writer::table($table);
    }

    /**
     * presenter::provenance() before.
     *
     * @param \stdClass $source
     * @return string
     */
    protected static function before_provenance(\stdClass $source): string {
        return \html_writer::div(
            s(empty($source->sourceupdated)
                ? get_string('sourceprovenancepending', 'tool_vae', (object) [
                    'attribution' => $source->attribution,
                    'licence' => $source->licence,
                ])
                : get_string('sourceprovenance', 'tool_vae', (object) [
                    'attribution' => $source->attribution,
                    'licence' => $source->licence,
                    'updated' => display_date::format((int) $source->sourceupdated, get_string('strftimedate', 'langconfig')),
                ])),
            'small text-muted'
        );
    }

    /**
     * presenter::rule_refs() before.
     *
     * @param \stdClass[][] $refs
     * @return string
     */
    protected static function before_rule_refs(array $refs): string {
        $lines = [];
        foreach ($refs as $code => $list) {
            $lines[] = \html_writer::tag('li', \html_writer::tag('strong', s($code)) . ' : ' . self::before_rule_links($list));
        }
        if (!$lines) {
            return '';
        }

        return \html_writer::div(
            \html_writer::tag('strong', s(get_string('refrulelabel', 'tool_vae')))
                . \html_writer::tag('ul', implode('', $lines), ['class' => 'mb-1'])
                . \html_writer::tag('p', s(get_string('refsentence', 'tool_vae')), ['class' => 'mb-0']),
            'small border rounded p-2 my-2 tool-vae-rule-refs'
        );
    }

    /**
     * presenter::rule_links() before.
     *
     * @param \stdClass[] $refs
     * @return string
     */
    protected static function before_rule_links(array $refs): string {
        $dateformat = get_string('strftimedate', 'langconfig');
        $links = [];
        foreach ($refs as $ref) {
            $host = piece_manager::display_host($ref->url);
            $tip = strpos($ref->url, $host) === false ? $ref->url . ' (' . $host . ')' : $ref->url;
            $link = \html_writer::link($ref->url, s($ref->title), [
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
                'title' => $tip,
            ]);
            if ($ref->checkedat !== null) {
                $link .= ' ' . s(get_string('refcheckedon', 'tool_vae', display_date::format($ref->checkedat, $dateformat, 'UTC')));
            }
            $links[] = $link;
        }

        return implode(' ; ', $links);
    }

    /**
     * presenter::scope_box() before.
     *
     * @param \stdClass[] $marks
     * @param string[] $labels
     * @param bool $isown
     * @param bool $open
     * @return string
     */
    protected static function before_scope_box(array $marks, array $labels, bool $isown, bool $open): string {
        if (!$marks && !($isown && $open)) {
            return '';
        }

        $dateformat = get_string('strftimedate', 'langconfig');
        $out = \html_writer::start_tag('section', ['class' => 'tool-vae-scope mb-4']);
        $out .= self::core()->heading(get_string('scopedossierheading', 'tool_vae'), 3);
        if ($marks) {
            $items = [];
            foreach ($marks as $key => $mark) {
                $line = \html_writer::tag('strong', s($key)) . (($labels[$key] ?? '') === '' ? '' : ' — ' . s($labels[$key]));
                $said = display_date::format((int) $mark->timecreated, $dateformat);
                $line .= ' ' . s(get_string('scopesaidonshort', 'tool_vae', $said));
                if ($mark->note !== null && $mark->note !== '') {
                    $line .= \html_writer::div(s($mark->note), 'small');
                }
                $items[] = \html_writer::tag('li', $line);
            }
            $out .= \html_writer::tag('ul', implode('', $items));
            $out .= \html_writer::tag('p', s(get_string('scopesentence', 'tool_vae')), ['class' => 'small text-muted']);
        } else {
            $out .= \html_writer::tag('p', s(get_string('scopenone', 'tool_vae')));
        }
        if ($isown) {
            $out .= \html_writer::div(\html_writer::link(
                new \moodle_url('/admin/tool/vae/scope.php'),
                get_string('scopemanage', 'tool_vae')
            ));
        }

        return $out . \html_writer::end_tag('section');
    }

    /**
     * presenter::coverage() before.
     *
     * @param \stdClass[] $fiches
     * @return string
     */
    protected static function before_coverage(array $fiches): string {
        if (!$fiches) {
            return '';
        }

        $dateformat = get_string('strftimedate', 'langconfig');
        $refs = empty(get_config('tool_vae', 'refopen')) ? [] : ref_manager::for_fiches(array_column($fiches, 'fichecode'));

        $out = self::before_rule_refs($refs);
        $out .= \html_writer::start_tag('section', ['class' => 'tool-vae-coverage mb-4']);
        $out .= self::core()->heading(get_string('coverage', 'tool_vae'), 3);
        $out .= \html_writer::tag('p', s(get_string('coverageintro', 'tool_vae')));

        foreach ($fiches as $fiche) {
            $title = $fiche->fichecode;
            if ($fiche->source) {
                $title .= ' — ' . get_string($fiche->source->fichetype === 'rs' ? 'typers' : 'typerncp', 'tool_vae');
            }
            $out .= self::core()->heading(s($title), 4);
            if ($fiche->source) {
                $out .= self::before_provenance($fiche->source);
            }

            if (!empty($fiche->hasblocs)) {
                $out .= \html_writer::tag('p', s(get_string('coverageblocnote', 'tool_vae')), ['class' => 'small text-muted']);
            }

            $table = new \html_table();
            $table->caption = get_string('coveragecaption', 'tool_vae', $fiche->fichecode);
            $table->captionhide = true;
            $table->head = [
                get_string('coveragecompetency', 'tool_vae'),
                get_string('coveragedeclarations', 'tool_vae'),
                get_string('coverageattested', 'tool_vae'),
                get_string('coveragelastattested', 'tool_vae'),
            ];
            $table->attributes['class'] = 'generaltable tool-vae-coverage-table';

            foreach ($fiche->competencies as $row) {
                $label = s($row->idnumber);
                if ($row->shortname !== null) {
                    $label .= ' — ' . s($row->shortname);
                }
                if ($row->gone) {
                    $label .= \html_writer::div(s(get_string('coveragegone', 'tool_vae')), 'small');
                }

                $declarations = $row->declarations;
                $attested = $row->attested;
                $last = $row->lastattested;
                if (!empty($row->isbloc)) {
                    $label = \html_writer::tag('strong', $label) . ' '
                        . \html_writer::tag('span', s(get_string('coveragebloctag', 'tool_vae')), ['class' => 'small text-muted']);
                    $declarations = $row->blocdeclarations;
                    $attested = $row->blocattested;
                    $last = $row->bloclastattested;
                }

                $competency = new \html_table_cell($label);
                $competency->header = true;
                $competency->scope = 'row';
                if (!empty($row->bloc)) {
                    $competency->attributes['class'] = 'ps-4';
                }

                $table->data[] = [
                    $competency,
                    (string) $declarations,
                    (string) $attested,
                    $last === null ? '' : s(display_date::format($last, $dateformat)),
                ];
            }

            $out .= \html_writer::table($table);
        }

        return $out . \html_writer::end_tag('section');
    }

    /**
     * presenter::anchor_warning() before.
     *
     * @param \stdClass $evidence
     * @param string|null $divergence
     * @return string
     */
    protected static function before_anchor_warning(\stdClass $evidence, ?string $divergence): string {
        if ($divergence === null) {
            return '';
        }

        $message = get_string('anchor' . $divergence, 'tool_vae', (object) [
            'record' => s($evidence->fichecode . '-' . $evidence->ficherang),
        ]);

        return self::core()->notification($message, \core\output\notification::NOTIFY_WARNING);
    }

    /**
     * presenter::carry_notes() before.
     *
     * @param \stdClass $notes
     * @return string
     */
    protected static function before_carry_notes(\stdClass $notes): string {
        $dateformat = get_string('strftimedate', 'langconfig');
        $describe = function (\stdClass $row) use ($dateformat): \stdClass {
            $decision = '';
            if ((int) $row->decidedat > 0) {
                $decision = ' ' . get_string('carrydecision', 'tool_vae', (object) [
                    'who' => $row->decidername === '' ? get_string('carrywhoadmin', 'tool_vae') : s($row->decidername),
                    'when' => s(display_date::format((int) $row->decidedat, $dateformat)),
                ]);
            }

            return (object) [
                'record' => s($row->record),
                'when' => s(display_date::format((int) $row->carriedat, $dateformat)),
                'decision' => $decision,
            ];
        };

        $out = '';
        if ($notes->carriedto) {
            $out .= \html_writer::tag('p', get_string('carriedto', 'tool_vae', $describe($notes->carriedto)), [
                'class' => 'tool-vae-carry small text-muted',
            ]);
        }
        if ($notes->carriedfrom) {
            $out .= \html_writer::tag('p', get_string('carriedfrom', 'tool_vae', $describe($notes->carriedfrom)), [
                'class' => 'tool-vae-carry small text-muted',
            ]);
        }

        return $out;
    }

    /**
     * presenter::declaration() before.
     *
     * @param \stdClass $evidence
     * @param \context $context
     * @return string
     */
    protected static function before_declaration(\stdClass $evidence, \context $context): string {
        $rows = [
            get_string('fichesource', 'tool_vae')
                => s($evidence->fichecode . '-' . $evidence->ficherang),
        ];

        $name = ($evidence->competencyid ?? null) === null
            ? null
            : (new competency_gateway())->competency_name((int) $evidence->competencyid);
        if ($name !== null && trim($name) !== '') {
            $rows[get_string('competency', 'tool_vae')] = s($name);
        }

        $bloc = ($evidence->competencyid ?? null) === null
            ? null
            : (new competency_gateway())->bloc_of((int) $evidence->competencyid);
        if ($bloc !== null) {
            $rows[get_string('declarationbloc', 'tool_vae')] = s($bloc->idnumber . ' — ' . $bloc->label);
        }

        $rows += [
            get_string('ficheupdated', 'tool_vae')
                => s(display_date::format((int) $evidence->ficheupdated, get_string('strftimedate', 'langconfig'))),
            get_string('period', 'tool_vae') => s(get_string(
                'periodfromto',
                'tool_vae',
                (object) [
                    'from' => display_date::format((int) $evidence->periodstart, get_string('strftimedate', 'langconfig')),
                    'to' => display_date::format((int) $evidence->periodend, get_string('strftimedate', 'langconfig')),
                ]
            )),
            get_string('settingtype', 'tool_vae')
                => s(get_string('setting:' . $evidence->settingtype, 'tool_vae')),
        ];

        if (trim((string) $evidence->settingdetail) !== '') {
            $rows[get_string('settingdetail', 'tool_vae')] = s($evidence->settingdetail);
        }

        $out = \html_writer::start_tag('dl', ['class' => 'tool-vae-claim']);
        foreach ($rows as $label => $value) {
            $out .= \html_writer::tag('dt', s($label));
            $out .= \html_writer::tag('dd', $value);
        }
        $out .= \html_writer::end_tag('dl');

        $out .= \html_writer::tag('h3', s(get_string('situation', 'tool_vae')));
        $out .= \html_writer::div(
            format_text(
                $evidence->situation,
                (int) $evidence->situationformat,
                ['context' => $context, 'para' => true]
            ),
            'tool-vae-situation'
        );

        return $out;
    }

    /**
     * attest_screen::declaration() before.
     *
     * @param \stdClass $evidence
     * @param \context $context
     * @param competency_gateway|null $gateway
     * @return string
     */
    protected static function before_attest_declaration(
        \stdClass $evidence,
        \context $context,
        ?competency_gateway $gateway = null
    ): string {
        return self::before_anchor_warning($evidence, anchor::divergence($evidence, $gateway))
            . self::before_declaration($evidence, $context);
    }

    /**
     * presenter::pieces() before.
     *
     * @param \stdClass[] $pieces
     * @param \context_user $context
     * @param \stdClass|null $standing
     * @param callable|null $actions
     * @return string
     */
    protected static function before_pieces(
        array $pieces,
        \context_user $context,
        ?\stdClass $standing = null,
        ?callable $actions = null
    ): string {
        global $USER;

        if (!$pieces) {
            return \html_writer::tag('p', s(get_string('piecenone', 'tool_vae')));
        }

        $covered = $standing ? piece_manager::covered_by((int) $standing->id) : null;
        $reader = (int) $USER->id !== (int) $context->instanceid;
        $opened = $reader ? piece_manager::opened_by((int) $USER->id, array_map(fn($piece) => (int) $piece->id, $pieces)) : [];
        $date = get_string('strftimedatetime', 'langconfig');

        $items = '';
        foreach ($pieces as $piece) {
            $line = \html_writer::tag('strong', s(get_string('piecenature:' . $piece->nature, 'tool_vae')));
            if ($piece->title !== '' && piece_manager::may_open($piece)) {
                $line .= ' - ' . s($piece->title);
            }
            $details = [];
            if ($piece->kind === piece_manager::KIND_FILE) {
                $details[] = s(strtoupper((string) $piece->ext) . ', ' . display_size((int) $piece->filesize));
                $details[] = s(get_string('piecedigest', 'tool_vae') . ' ' . $piece->sha256);
            }
            $details[] = s(userdate($piece->timecreated, $date));
            $line .= \html_writer::tag('div', implode(' - ', $details), ['class' => 'text-muted small']);

            if ($piece->timeremoved !== null) {
                $removed = get_string('pieceremovedon', 'tool_vae', userdate($piece->timeremoved, $date));
                $line .= \html_writer::tag('div', s($removed), ['class' => 'text-muted']);
                if ($covered !== null && array_key_exists((int) $piece->id, $covered)) {
                    $after = get_string('pieceremovedaftersignature', 'tool_vae');
                    $line .= \html_writer::tag('div', s($after), ['class' => 'text-muted']);
                }
            } else {
                if ($covered !== null && !array_key_exists((int) $piece->id, $covered)) {
                    $line .= \html_writer::tag('div', s(get_string(
                        'piecesaftersignature',
                        'tool_vae',
                        userdate((int) $standing->attestedat, $date)
                    )), ['class' => 'alert alert-warning py-1']);
                }
                $line .= self::before_piece_access($piece, $context, $reader, $opened);
                if ($actions) {
                    $line .= $actions($piece);
                }
            }
            $items .= \html_writer::tag('li', $line, ['class' => 'mb-3 tool-vae-piece']);
        }

        $out = \html_writer::tag('ul', $items, ['class' => 'list-unstyled tool-vae-pieces']);
        if ($reader) {
            $active = array_filter($pieces, fn($piece) => $piece->timeremoved === null
                && $piece->kind === piece_manager::KIND_FILE && piece_manager::may_open($piece));
            $count = count(array_filter($active, fn($piece) => isset($opened[(int) $piece->id])));
            $out .= \html_writer::tag('p', s(get_string('piecesopenedcount', 'tool_vae', (object) [
                'opened' => $count,
                'total' => count($active),
            ])), ['class' => 'text-muted small']);
        }

        return $out;
    }

    /**
     * presenter::piece_access() before.
     *
     * @param \stdClass $piece
     * @param \context_user $context
     * @param bool $reader
     * @param bool[] $opened
     * @return string
     */
    protected static function before_piece_access(\stdClass $piece, \context_user $context, bool $reader, array $opened): string {
        if (!piece_manager::may_open($piece)) {
            return \html_writer::tag('div', s(get_string('piecelistonly', 'tool_vae')), ['class' => 'text-muted']);
        }

        if ($piece->kind === piece_manager::KIND_LINK) {
            $out = \html_writer::tag('div', s($piece->url))
                . \html_writer::tag('div', s(piece_manager::display_host($piece->url)), ['class' => 'text-muted small'])
                . \html_writer::tag('div', \html_writer::link(
                    $piece->url,
                    get_string('piecelinkopen', 'tool_vae'),
                    ['rel' => 'noopener noreferrer', 'target' => '_blank']
                ));
        } else {
            $out = \html_writer::tag('div', \html_writer::link(
                \moodle_url::make_pluginfile_url(
                    $context->id,
                    'tool_vae',
                    piece_manager::FILEAREA,
                    (int) $piece->id,
                    '/',
                    $piece->uuid . '.' . $piece->ext,
                    true
                ),
                get_string('pieceopenfile', 'tool_vae')
            ));
        }
        if ($reader && $piece->kind === piece_manager::KIND_FILE) {
            $out .= \html_writer::tag(
                'div',
                s(get_string(isset($opened[(int) $piece->id]) ? 'pieceopenedyes' : 'pieceopenedno', 'tool_vae')),
                ['class' => 'text-muted small']
            );
        }

        return $out;
    }

    /**
     * presenter::state_label() before.
     *
     * @param \stdClass[] $attestations
     * @param int $openrequests
     * @return string
     */
    protected static function before_state_label(array $attestations, int $openrequests = 0): string {
        $out = \html_writer::tag(
            'span',
            s(get_string('state:' . presenter::state($attestations), 'tool_vae')),
            ['class' => 'badge bg-secondary text-white tool-vae-state']
        );
        if ($openrequests > 0) {
            $out .= ' ' . \html_writer::tag(
                'span',
                s(get_string('state:questionopen', 'tool_vae')),
                ['class' => 'badge bg-info text-dark tool-vae-state']
            );
        }

        return $out;
    }

    /**
     * presenter::state_legend() before.
     *
     * @return string
     */
    protected static function before_state_legend(): string {
        $items = [];
        foreach (array_merge(presenter::STATES, ['questionopen']) as $state) {
            $items[] = \html_writer::tag('strong', s(get_string('state:' . $state, 'tool_vae')))
                . ' - ' . s(get_string('state:' . $state . '_help', 'tool_vae'));
        }

        return \html_writer::tag(
            'details',
            \html_writer::tag('summary', s(get_string('statelegendheading', 'tool_vae')))
            . \html_writer::alist($items),
            ['class' => 'tool-vae-legend mb-3']
        );
    }

    /**
     * presenter::chronology_html() before.
     *
     * @param \stdClass $evidence
     * @param \stdClass[] $attestations
     * @param \stdClass[] $requests
     * @param array[] $carries
     * @return string
     */
    protected static function before_chronology_html(
        \stdClass $evidence,
        array $attestations,
        array $requests,
        array $carries = []
    ): string {
        $items = [];
        foreach (presenter::chronology($evidence, $attestations, $requests, $carries) as $act) {
            $items[] = \html_writer::tag('span', s(display_date::format(
                $act['time'],
                get_string('strftimedate', 'langconfig')
            )), ['class' => 'text-muted']) . ' - ' . s(get_string('chrono:' . $act['act'], 'tool_vae'));
        }

        return \html_writer::tag(
            'details',
            \html_writer::tag('summary', s(get_string('chronologyheading', 'tool_vae')))
            . \html_writer::alist($items, ['class' => 'tool-vae-chronology']),
            ['class' => 'mb-2']
        );
    }

    /**
     * presenter::attestations() before.
     *
     * @param \stdClass[] $attestations
     * @return string
     */
    protected static function before_attestations(array $attestations): string {
        if (!$attestations) {
            return \html_writer::div(s(get_string('noattestationyet', 'tool_vae')), 'text-muted');
        }

        $standing = (bool) array_filter($attestations, fn($attestation) => $attestation->withdrawnat === null);
        $last = array_key_last($attestations);

        $items = [];
        foreach ($attestations as $key => $attestation) {
            if ($attestation->withdrawnat !== null) {
                $sentence = get_string('attestationwithdrawnon', 'tool_vae', (object) [
                    'date' => display_date::format((int) $attestation->withdrawnat, get_string('strftimedate', 'langconfig')),
                    'reason' => get_string('withdrawreason:' . $attestation->withdrawnreason, 'tool_vae'),
                ]);
                if (!$standing && $key === $last) {
                    $sentence .= ' ' . get_string('attestationwithdrawnagain', 'tool_vae');
                }
                $items[] = s($sentence);
                continue;
            }

            $item = \html_writer::tag('strong', s($attestation->attestantrole))
                . \html_writer::empty_tag('br')
                . nl2br(s($attestation->statement))
                . \html_writer::empty_tag('br')
                . \html_writer::tag('span', s(get_string('attestedon', 'tool_vae', display_date::format(
                    (int) $attestation->attestedat,
                    get_string('strftimedate', 'langconfig')
                ))), ['class' => 'text-muted']);

            if (!empty($attestation->importedat)) {
                $item .= \html_writer::empty_tag('br')
                    . \html_writer::tag('span', s(get_string('attestationimportedon', 'tool_vae', display_date::format(
                        (int) $attestation->importedat,
                        get_string('strftimedate', 'langconfig')
                    ))), ['class' => 'text-muted']);
            }

            $items[] = $item;
        }

        return \html_writer::alist($items, ['class' => 'tool-vae-attestations']);
    }

    /**
     * hidden_frameworks::kept_text() before.
     *
     * @param string $situation
     * @param string $settingdetail
     * @return string
     */
    protected static function before_kept_text(string $situation, string $settingdetail = ''): string {
        $html = '';
        foreach (['situation' => $situation, 'settingdetail' => $settingdetail] as $field => $text) {
            if (trim($text) === '') {
                continue;
            }
            $id = 'tool_vae_kept_' . $field;
            $html .= \html_writer::label(get_string($field, 'tool_vae'), $id, true, ['class' => 'font-weight-bold d-block'])
                . \html_writer::tag('textarea', s($text), [
                    'id' => $id,
                    'readonly' => 'readonly',
                    'rows' => $field === 'situation' ? 12 : 2,
                    'cols' => 70,
                    'class' => 'form-control mb-3',
                ]);
        }

        return $html === '' ? '' : \html_writer::tag('p', get_string('declarekeptintro', 'tool_vae')) . $html;
    }
}
