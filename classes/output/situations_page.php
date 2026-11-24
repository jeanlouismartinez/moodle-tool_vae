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
use tool_vae\local\selfpos_tree;
use tool_vae\local\situation_screen;
use tool_vae\local\validation_status;

/**
 * The page of the work situations of the vaeiste (situations.php), under its title, in its four states that exclude each
 * other (lot 7, MMR-520 ticket 4, wave 4a): the competencies switched off; the records; the inverse view of a node (its
 * situations and its declarations); the situations of a record (the notices, the button "new situation", the list, the
 * annotations of the deleted situations, the panel "where I stand").
 *
 * Was the screens situation_screen::records(), inverse() and list(), and the HTML the page wrote around them.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class situations_page implements named_templatable, renderable {
    /** @var string disabled, records, inverse or record. */
    private string $state;

    /** @var int The vaeiste. */
    private int $candidateid = 0;

    /** @var bool Whether the vaeiste reads their own. */
    private bool $own = false;

    /** @var array Code => null or the message of a hidden record (records). */
    private array $records = [];

    /** @var string The record. */
    private string $fichecode = '';

    /** @var string The node (inverse). */
    private string $rank = '';

    /** @var \stdClass[] The situations: tagged on the node (inverse), or of the record (record). */
    private array $situations = [];

    /** @var \stdClass[] The declarations on the node (inverse). */
    private array $declarations = [];

    /** @var \context_user|null The vaeiste's context (inverse). */
    private ?\context_user $context = null;

    /** @var string|null The message of the record hidden, already escaped (record). */
    private ?string $hiddenmessage = null;

    /** @var bool Whether the vaeiste may write (record). */
    private bool $canwrite = false;

    /** @var bool Whether the preparation is open (record). */
    private bool $open = false;

    /** @var \moodle_url|null The address of the page (record). */
    private ?\moodle_url $url = null;

    /** @var notes_orphans|null The annotations of the deleted situations (record). */
    private ?notes_orphans $orphans = null;

    /** @var whereami_tree|null The panel of the record (record). */
    private ?whereami_tree $panel = null;

    /**
     * A state of the page.
     *
     * @param string $state
     */
    private function __construct(string $state) {
        $this->state = $state;
    }

    /**
     * The competencies of the site are switched off.
     *
     * @return self
     */
    public static function disabled(): self {
        return new self('disabled');
    }

    /**
     * The records a vaeiste has situations on or may open one on, each leading to its list; then the way back.
     *
     * @param int $candidateid
     * @param array $records Code => null for a record offered, or the message of a record hidden (already escaped by
     *        hidden_frameworks::message()) that still holds situations; in the order of the codes.
     * @param bool $own Whether the vaeiste reads their own.
     * @return self
     */
    public static function records(int $candidateid, array $records, bool $own): self {
        $page = new self('records');
        $page->candidateid = $candidateid;
        $page->records = $records;
        $page->own = $own;

        return $page;
    }

    /**
     * The inverse view of a node, under the code of the record: its situations and its declarations, in reading; then the
     * way back to the list.
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param string $rank
     * @param \stdClass[] $situations As situation_manager::tagged() gives them.
     * @param \stdClass[] $declarations Rows of tool_vae_evidence on the node.
     * @param \context_user $context The vaeiste's context.
     * @return self
     */
    public static function inverse(
        int $candidateid,
        string $fichecode,
        string $rank,
        array $situations,
        array $declarations,
        \context_user $context
    ): self {
        $page = new self('inverse');
        $page->candidateid = $candidateid;
        $page->fichecode = $fichecode;
        $page->rank = $rank;
        $page->situations = $situations;
        $page->declarations = $declarations;
        $page->context = $context;

        return $page;
    }

    /**
     * The situations of a record, under its code: the record hidden, the preparation closed, the button "new situation"
     * for the vaeiste who may open one, the list, the annotations of the deleted situations, the panel, the way back.
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param bool $own Whether the vaeiste reads their own.
     * @param string|null $hiddenmessage The message of the record when it is hidden (already escaped by
     *        hidden_frameworks::message()), or null.
     * @param bool $canwrite Whether the reader may write the situations (situation_manager::can_write()).
     * @param bool $open Whether the preparation is open.
     * @param \moodle_url $url The address of the page, to which "new situation" is sent.
     * @param \stdClass[] $situations As situation_manager::list() gives them.
     * @param notes_orphans $orphans The annotations of the deleted situations.
     * @param whereami_tree $panel The panel of the record.
     * @return self
     */
    public static function record(
        int $candidateid,
        string $fichecode,
        bool $own,
        ?string $hiddenmessage,
        bool $canwrite,
        bool $open,
        \moodle_url $url,
        array $situations,
        notes_orphans $orphans,
        whereami_tree $panel
    ): self {
        $page = new self('record');
        $page->candidateid = $candidateid;
        $page->fichecode = $fichecode;
        $page->own = $own;
        $page->hiddenmessage = $hiddenmessage;
        $page->canwrite = $canwrite;
        $page->open = $open;
        $page->url = $url;
        $page->situations = $situations;
        $page->orphans = $orphans;
        $page->panel = $panel;

        return $page;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/situations_page';
    }

    /**
     * The context of the template.
     *
     * @param renderer_base $output
     * @return array disabled, records, inverse and record, each an object or null.
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'disabled' => $this->state === 'disabled' ? [
                'noticehtml' => $output->notification(
                    get_string('errorcompetenciesdisabled', 'tool_vae'),
                    notification::NOTIFY_ERROR
                ),
            ] : null,
            'records' => $this->state === 'records' ? $this->records_parts($output) : null,
            'inverse' => $this->state === 'inverse' ? $this->inverse_parts($output) : null,
            'record' => $this->state === 'record' ? $this->record_parts($output) : null,
        ];
    }

    /**
     * The way back - to the pathway for the vaeiste, to the dossier for a reader - and, beside it, the consent to
     * annotations (lot 1b).
     *
     * @return array
     */
    private function back(): array {
        if ($this->own) {
            $url = new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => 'mine']);
            $words = get_string('selfposbackpathway', 'tool_vae');
        } else {
            $url = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $this->candidateid]);
            $words = get_string('selfposbackdossier', 'tool_vae');
        }

        return [
            'url' => $url->out(false),
            'text' => $words,
            'consenturl' => (new \moodle_url('/admin/tool/vae/consent.php', ['userid' => $this->candidateid]))->out(false),
            'consent' => get_string('consentlink', 'tool_vae'),
        ];
    }

    /**
     * A situation as an item of a list: its title leading to it, and its facts.
     *
     * @param renderer_base $output
     * @param \stdClass $row
     * @param \stdClass|null $tree
     * @param string[] $more The lines added after the facts.
     * @return array
     */
    private function item(renderer_base $output, \stdClass $row, ?\stdClass $tree, array $more): array {
        return [
            'slot' => (string) (int) $row->slot,
            'url' => situation_screen::url($this->candidateid, $this->fichecode, (int) $row->slot)->out(false),
            'title' => situation_screen::title($row),
            'factshtml' => $output->render(new situation_facts($row, $tree, $more, 'small')),
        ];
    }

    /**
     * The records.
     *
     * @param renderer_base $output
     * @return array
     */
    private function records_parts(renderer_base $output): array {
        $items = [];
        foreach ($this->records as $code => $message) {
            $items[] = [
                'url' => situation_screen::url($this->candidateid, (string) $code)->out(false),
                'code' => (string) $code,
                // Already escaped by hidden_frameworks::message(): a second pass would show entities in the title.
                'hidden' => $message !== null ? ['messagehtml' => $message] : null,
            ];
        }

        return [
            'disclaimerhtml' => $output->render(new situation_disclaimer($this->candidateid)),
            'none' => $this->records ? null : ['text' => get_string('selfposnorecord', 'tool_vae')],
            'list' => $this->records ? ['choose' => get_string('selfposchoose', 'tool_vae'), 'items' => $items] : null,
            'back' => $this->back(),
        ];
    }

    /**
     * The inverse view of a node.
     *
     * @param renderer_base $output
     * @return array
     */
    private function inverse_parts(renderer_base $output): array {
        $tree = selfpos_tree::for_record($this->fichecode);
        $items = [];
        foreach ($this->situations as $row) {
            $items[] = $this->item($output, $row, $tree, []);
        }
        $declarations = [];
        foreach ($this->declarations as $evidence) {
            $declarations[] = ['declhtml' => $output->render(new declaration($evidence, $this->context))];
        }

        return [
            'code' => $this->fichecode,
            'disclaimerhtml' => $output->render(new situation_disclaimer($this->candidateid)),
            'heading' => get_string('situationsonnode', 'tool_vae', situation_screen::node_words($tree, $this->rank)),
            'none' => $items ? null : ['text' => get_string('situationnone', 'tool_vae')],
            'items' => $items,
            'declheading' => get_string('situationdeclarations', 'tool_vae'),
            'nodeclaration' => $declarations ? null : ['text' => get_string('situationnodeclaration', 'tool_vae')],
            'declarations' => $declarations,
            'back' => [
                'url' => situation_screen::url($this->candidateid, $this->fichecode)->out(false),
                'text' => get_string('situationbacklist', 'tool_vae'),
            ],
        ];
    }

    /**
     * The situations of a record.
     *
     * @param renderer_base $output
     * @return array
     */
    private function record_parts(renderer_base $output): array {
        $hidden = $this->hiddenmessage !== null;
        $items = [];
        if ($this->situations) {
            $tree = selfpos_tree::for_record($this->fichecode);
            foreach ($this->situations as $row) {
                $items[] = $this->item($output, $row, $tree, [
                    get_string('situationpieces', 'tool_vae', (int) $row->pieces),
                    validation_status::words($row->status),
                ]);
            }
        }

        return [
            'code' => $this->fichecode,
            'disclaimerhtml' => $output->render(new situation_disclaimer($this->candidateid)),
            // Already escaped by hidden_frameworks::message().
            'hidden' => $hidden ? ['messagehtml' => $this->hiddenmessage] : null,
            'closed' => $this->canwrite && !$this->open ? ['text' => get_string('errorvalidationclosed', 'tool_vae')] : null,
            // The button of the core: a POST with the session key.
            'newhtml' => $this->canwrite && $this->open && !$hidden
                ? $output->single_button(
                    new \moodle_url($this->url, ['action' => 'new']),
                    get_string('situationnew', 'tool_vae'),
                    'post'
                )
                : '',
            'none' => $items ? null : ['text' => get_string('situationnone', 'tool_vae')],
            'items' => $items,
            'orphanshtml' => $this->orphans->drawn() ? $output->render($this->orphans) : '',
            'panelhtml' => $this->panel->drawn() ? $output->render($this->panel) : '',
            'back' => $this->back(),
        ];
    }
}
