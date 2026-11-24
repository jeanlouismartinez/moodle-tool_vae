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

use core\output\named_templatable;
use core\output\notification;
use core\output\renderable;
use core\output\renderer_base;
use tool_vae\local\attestation_manager;
use tool_vae\local\draft_manager;
use tool_vae\local\validation_catalog;
use tool_vae\local\validation_status;

/**
 * The preparation of the validation dossier (validation.php), under its title (lot 7, MMR-520 ticket 4, wave 3b).
 *
 * Three drawings: the head that every screen of the page carries (the notice to a reader, the words "not the official
 * dossier", the way to the deposits for the author), drawn alone first because the page refuses a record that is not
 * offered AFTER it, as it always did; then either the list of the records, or the preparation of one record - the panel
 * "where am I", and bloc by bloc the declarations of the vaeiste, read only, and the sections with where each stands and,
 * for a reader, their text; the synthesis; the sections of a bloc that left the record.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validation_page implements named_templatable, renderable {
    /** @var string head, records or record. */
    private string $state;

    /** @var bool Whether the reader is not the author (D-1b-G). */
    private bool $reading;

    /** @var int The vaeiste. */
    private int $candidateid;

    /** @var string[] The records the vaeiste declared on (the list). */
    private array $codes = [];

    /** @var string The record (the preparation). */
    private string $fichecode = '';

    /** @var \stdClass|null The structure of the record, as validation_catalog::structure() gives it. */
    private ?\stdClass $structure = null;

    /** @var \stdClass[] The sections written, by section code. */
    private array $written = [];

    /** @var array Where each section stands, as validation_status::for_record() gives it. */
    private array $status = [];

    /** @var array Rank of the bloc ('' for a record with no bloc) => the declarations of the vaeiste on it. */
    private array $claims = [];

    /**
     * A drawing of the page.
     *
     * @param string $state
     * @param bool $reading
     * @param int $candidateid
     */
    private function __construct(string $state, bool $reading, int $candidateid) {
        $this->state = $state;
        $this->reading = $reading;
        $this->candidateid = $candidateid;
    }

    /**
     * The head of every screen, under the title.
     *
     * @param bool $reading Whether the reader is not the author.
     * @return self
     */
    public static function head(bool $reading): self {
        return new self('head', $reading, 0);
    }

    /**
     * The list of the records the vaeiste declared on.
     *
     * @param bool $reading Whether the reader is not the author.
     * @param int $candidateid The vaeiste.
     * @param string[] $codes The records, in order.
     * @return self
     */
    public static function records(bool $reading, int $candidateid, array $codes): self {
        $page = new self('records', $reading, $candidateid);
        $page->codes = $codes;

        return $page;
    }

    /**
     * The preparation of one record.
     *
     * @param bool $reading Whether the reader is not the author.
     * @param int $candidateid The vaeiste.
     * @param string $fichecode The record.
     * @param \stdClass $structure Its structure, as validation_catalog::structure() gives it.
     * @param \stdClass[] $written The sections written, by section code.
     * @param array $status Where each section stands, as validation_status::for_record() gives it.
     * @param array $claims Rank of the bloc ('' for a record drawn with no bloc) => the declarations of the vaeiste on it.
     * @return self
     */
    public static function record(
        bool $reading,
        int $candidateid,
        string $fichecode,
        \stdClass $structure,
        array $written,
        array $status,
        array $claims
    ): self {
        $page = new self('record', $reading, $candidateid);
        $page->fichecode = $fichecode;
        $page->structure = $structure;
        $page->written = $written;
        $page->status = $status;
        $page->claims = $claims;

        return $page;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/validation_page';
    }

    /**
     * The context of the template.
     *
     * @param renderer_base $output
     * @return array head, records and record, each an object or null.
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'head' => $this->state === 'head' ? $this->head_parts() : null,
            'records' => $this->state === 'records' ? $this->records_parts($output) : null,
            'record' => $this->state === 'record' ? $this->record_parts($output) : null,
        ];
    }

    /**
     * The parameters every link of this page carries: the reader stays on the dossier they read.
     *
     * @return array
     */
    private function base(): array {
        return $this->reading ? ['userid' => $this->candidateid] : [];
    }

    /**
     * The way back to the dossier, in the words of the author or of a reader.
     *
     * @return array url and text.
     */
    private function back(): array {
        return [
            'url' => (new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $this->candidateid]))->out(false),
            'text' => get_string($this->reading ? 'faisabbacktodossier' : 'validationbacktodossier', 'tool_vae'),
        ];
    }

    /**
     * The head: who wrote this, for whom, and what it is not.
     *
     * @return array
     */
    private function head_parts(): array {
        return [
            'notice' => $this->reading ? ['text' => get_string('validationreadnotice', 'tool_vae')] : null,
            // The same words on every screen, the PDF, the copy and the archive (MC-17).
            'labelling' => get_string('validationlabelling', 'tool_vae'),
            'deposit' => $this->reading ? null : [
                'url' => (new \moodle_url('/admin/tool/vae/deposit.php', [
                    'doctype' => draft_manager::DOCTYPE_VALIDATION,
                ]))->out(false),
                'text' => get_string('validationdepositheading', 'tool_vae'),
            ],
        ];
    }

    /**
     * The list of the records.
     *
     * @param renderer_base $output
     * @return array
     */
    private function records_parts(renderer_base $output): array {
        $nonehtml = '';
        $list = null;
        if (!$this->codes) {
            $none = $this->reading ? 'validationnodeclaration' : 'validationnofiche';
            $nonehtml = $output->notification(get_string($none, 'tool_vae'), notification::NOTIFY_INFO);
        } else {
            $items = [];
            foreach ($this->codes as $code) {
                $items[] = [
                    'url' => (new \moodle_url('/admin/tool/vae/validation.php', $this->base() + ['fiche' => $code]))->out(false),
                    'code' => (string) $code,
                ];
            }
            $list = [
                'choose' => $this->reading ? null : ['text' => get_string('validationchoose', 'tool_vae')],
                'codes' => $items,
            ];
        }

        return [
            'intro' => $this->reading ? null : ['text' => get_string('validationintro', 'tool_vae')],
            'nonehtml' => $nonehtml,
            'list' => $list,
            'back' => $this->back(),
        ];
    }

    /**
     * The preparation of one record.
     *
     * @param renderer_base $output
     * @return array
     */
    private function record_parts(renderer_base $output): array {
        $structure = $this->structure;
        $noticehtml = '';
        if ($structure->overflow) {
            $noticehtml = $output->notification(
                get_string('validationoverflow', 'tool_vae', validation_catalog::MAX_BLOCS),
                notification::NOTIFY_INFO
            );
        } else if (!$structure->byblocs) {
            $noticehtml = $output->notification(get_string('validationflat', 'tool_vae'), notification::NOTIFY_INFO);
        }
        // Where the vaeiste stands on this record, in reading, above the blocs (lot 1b, spec 1a § 6.6).
        $panel = new whereami_tree($this->candidateid, $this->fichecode, ['link' => true]);
        $panelhtml = $panel->drawn() ? $output->render($panel) : '';

        // The groups of sections, in the order of the page: each bloc (or the record as a whole) with the declarations of the
        // vaeiste beside it, the synthesis, and the sections of a bloc that left the record.
        $declared = ['text' => get_string($this->reading ? 'candidatedeclarations' : 'validationdeclarations', 'tool_vae')];
        $groups = [];
        if ($structure->byblocs) {
            $ranks = array_keys($structure->blocs);
            sort($ranks, SORT_NATURAL);
            foreach ($ranks as $rank) {
                $bloc = $structure->blocs[$rank];
                $lines = [];
                foreach (validation_catalog::PARTS as $part) {
                    $lines[] = $this->line($rank . '_' . $part, true);
                }
                $groups[] = self::group(
                    $output->heading(s(strtoupper((string) $rank) . ' - ' . $bloc->label), 4),
                    $declared,
                    $this->declarations($output, (string) $rank),
                    null,
                    $lines
                );
            }
        } else {
            $lines = [];
            foreach (validation_catalog::PARTS as $part) {
                $lines[] = $this->line('_' . $part, true);
            }
            $groups[] = self::group('', $declared, $this->declarations($output, ''), null, $lines);
        }
        $groups[] = self::group(
            $output->heading(s(get_string('validpart:synthese', 'tool_vae')), 4),
            null,
            null,
            null,
            [$this->line('_' . validation_catalog::SYNTHESIS, true)]
        );

        // What was written for a bloc that has since left the record stays here, readable and exported, never hidden.
        $known = array_column(validation_catalog::sections($this->fichecode), 'section');
        $left = array_diff_key($this->written, array_flip($known));
        if ($left) {
            $lines = [];
            foreach (array_keys($left) as $section) {
                $lines[] = $this->line((string) $section, false);
            }
            $groups[] = self::group(
                $output->heading(s(get_string('validationretired', 'tool_vae')), 4),
                null,
                null,
                $this->reading ? null : ['text' => get_string('validationretiredhelp', 'tool_vae')],
                $lines
            );
        }

        return [
            'headinghtml' => $output->heading(s($this->fichecode), 3),
            'backtolist' => [
                'url' => (new \moodle_url('/admin/tool/vae/validation.php', $this->base()))->out(false),
                'text' => get_string('validationbacktolist', 'tool_vae'),
            ],
            'noticehtml' => $noticehtml,
            'panelhtml' => $panelhtml,
            'intro' => $this->reading ? null : ['text' => get_string('validationintro', 'tool_vae')],
            'groups' => $groups,
            'back' => $this->back(),
        ];
    }

    /**
     * One group of sections: its heading, the declarations beside it, a help, the lines.
     *
     * @param string $headinghtml The heading drawn by the core; '' for a record drawn with no bloc.
     * @param array|null $declared The heading of the declarations (text), or null.
     * @param array|null $declarations As declarations() gives them, or null.
     * @param array|null $help A sentence above the lines (text), or null.
     * @param array $lines As line() gives them.
     * @return array
     */
    private static function group(string $headinghtml, ?array $declared, ?array $declarations, ?array $help, array $lines): array {
        return [
            'headinghtml' => $headinghtml,
            'declared' => $declared,
            'declarations' => $declarations,
            'help' => $help,
            'lines' => $lines,
        ];
    }

    /**
     * The declarations of the vaeiste on a bloc, read only, as folds.
     *
     * @param renderer_base $output
     * @param string $rank The rank of the bloc; '' for a record drawn with no bloc.
     * @return array none (an object: text) or folds (a list of {start, end, statehtml, declarationhtml}).
     */
    private function declarations(renderer_base $output, string $rank): array {
        $claims = $this->claims[$rank] ?? [];
        if (!$claims) {
            return ['none' => ['text' => get_string('validationnodeclaration', 'tool_vae')], 'folds' => []];
        }
        $context = \context_user::instance($this->candidateid);
        $folds = [];
        foreach ($claims as $claim) {
            $folds[] = [
                'start' => userdate((int) $claim->periodstart, get_string('strftimedate', 'langconfig')),
                'end' => userdate((int) $claim->periodend, get_string('strftimedate', 'langconfig')),
                'statehtml' => $output->render(new state_label(attestation_manager::attestations((int) $claim->id))),
                'declarationhtml' => $output->render(new declaration($claim, $context)),
            ];
        }

        return ['none' => null, 'folds' => $folds];
    }

    /**
     * A section as a line: its name, where it stands, and the ways in; for a reader, no way to write and the text itself.
     *
     * @param string $section
     * @param bool $known Whether the section belongs to the record (a retired one is never a way to write).
     * @return array
     */
    private function line(string $section, bool $known): array {
        $row = $this->written[$section] ?? null;
        $write = null;
        if ($known && !$this->reading) {
            $write = ['url' => (new \moodle_url('/admin/tool/vae/section.php', [
                'doctype' => draft_manager::DOCTYPE_VALIDATION, 'fiche' => $this->fichecode, 'section' => $section,
            ]))->out(false)];
        }
        $history = null;
        if ($row && (int) $row->currentversion > 0) {
            $history = [
                'url' => (new \moodle_url('/admin/tool/vae/history.php', ['draftid' => $row->id]))->out(false),
                'text' => get_string('historylink', 'tool_vae'),
            ];
        }

        return [
            'label' => validation_catalog::label($this->fichecode, $section),
            'write' => $write,
            // Where the section stands, in words: never a verdict, only whether something is there.
            'state' => validation_status::words($this->status[$section] ?? validation_status::place(null)),
            'history' => $history,
            'texts' => $this->reading ? $this->texts($row) : null,
        ];
    }

    /**
     * For a reader, what a section holds (D-1b-H): the latest version saved and, apart, the draft in progress, said to be
     * one. The text is the vaeiste's free text, shown as plain text.
     *
     * @param \stdClass|null $row
     * @return array|null latest (an object: latesthtml) and working (an object: notice, workinghtml), each or null.
     */
    private function texts(?\stdClass $row): ?array {
        if (!$row) {
            return null;
        }
        $context = \context_user::instance($this->candidateid);
        $plain = fn(string $text): string => format_text($text, FORMAT_PLAIN, ['context' => $context, 'filter' => false]);
        $latest = null;
        if ((int) $row->currentversion > 0) {
            $version = (new draft_manager())->version((int) $row->id, (int) $row->currentversion);
            $latest = ['latesthtml' => $plain((string) $version->content)];
        }
        $working = null;
        if ($row->working !== null && trim((string) $row->working) !== '') {
            $working = [
                'notice' => get_string('situationworkingnotice', 'tool_vae'),
                'workinghtml' => $plain((string) $row->working),
            ];
        }

        return ['latest' => $latest, 'working' => $working];
    }
}
