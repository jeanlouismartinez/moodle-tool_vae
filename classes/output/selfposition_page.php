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

/**
 * The page of the self-positioning (selfposition.php), under its title, in the three states that the pieces of wave 3a did
 * not draw whole (lot 7, MMR-520 ticket 4, wave 4a): the competencies switched off; the tree of a record between its code
 * and the way back; one node with the errors of a send, the node and its annotations. The list of the records and a record
 * hidden since the page was opened are drawn by the pieces selfpos_records and selfpos_hidden, each in one rendering.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class selfposition_page implements named_templatable, renderable {
    /** @var string disabled, tree or node. */
    private string $state;

    /** @var int The vaeiste. */
    private int $candidateid = 0;

    /** @var string The record. */
    private string $fichecode = '';

    /** @var bool Whether the vaeiste reads their own pathway. */
    private bool $own = false;

    /** @var selfpos_record_tree|null The tree of the record. */
    private ?selfpos_record_tree $tree = null;

    /** @var string|null The error of the send of the self-positioning. */
    private ?string $error = null;

    /** @var string|null The error of the send of an annotation. */
    private ?string $noteerror = null;

    /** @var selfpos_node|null The node. */
    private ?selfpos_node $node = null;

    /** @var notes_block|null The annotations of the node; null for a node that holds none. */
    private ?notes_block $notes = null;

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
     * The tree of a record, under its code, then the way back: to the pathway for the vaeiste, to the dossier for a reader.
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param bool $own Whether the vaeiste reads their own.
     * @param selfpos_record_tree $tree
     * @return self
     */
    public static function tree(int $candidateid, string $fichecode, bool $own, selfpos_record_tree $tree): self {
        $page = new self('tree');
        $page->candidateid = $candidateid;
        $page->fichecode = $fichecode;
        $page->own = $own;
        $page->tree = $tree;

        return $page;
    }

    /**
     * One node: the error of a send of the self-positioning, then of an annotation, the node, then its annotations.
     *
     * @param string|null $error The message of the send of the self-positioning that failed, or null.
     * @param string|null $noteerror The message of the send of an annotation that failed, or null.
     * @param selfpos_node $node
     * @param notes_block|null $notes The annotations; null for a node no screen can name.
     * @return self
     */
    public static function node(?string $error, ?string $noteerror, selfpos_node $node, ?notes_block $notes): self {
        $page = new self('node');
        $page->error = $error;
        $page->noteerror = $noteerror;
        $page->node = $node;
        $page->notes = $notes;

        return $page;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/selfposition_page';
    }

    /**
     * The context of the template.
     *
     * @param renderer_base $output
     * @return array disabled, tree and node, each an object or null.
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'disabled' => $this->state === 'disabled' ? [
                'noticehtml' => $output->notification(
                    get_string('errorcompetenciesdisabled', 'tool_vae'),
                    notification::NOTIFY_ERROR
                ),
            ] : null,
            'tree' => $this->state === 'tree' ? $this->tree_parts($output) : null,
            'node' => $this->state === 'node' ? [
                // The message of an exception, escaped before the notification of the core, as before.
                'errorhtml' => $this->error !== null ? $output->notification(s($this->error), notification::NOTIFY_ERROR) : '',
                'noteerrorhtml' => $this->noteerror !== null
                    ? $output->notification(s($this->noteerror), notification::NOTIFY_ERROR) : '',
                'nodehtml' => $output->render($this->node),
                'noteshtml' => $this->notes !== null ? $output->render($this->notes) : '',
            ] : null,
        ];
    }

    /**
     * The tree of a record, between its code and the way back.
     *
     * @param renderer_base $output
     * @return array
     */
    private function tree_parts(renderer_base $output): array {
        // The vaeiste goes back to their pathway; a reader goes back to the dossier they came from.
        if ($this->own) {
            $url = new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => 'mine']);
            $words = get_string('selfposbackpathway', 'tool_vae');
        } else {
            $url = new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $this->candidateid]);
            $words = get_string('selfposbackdossier', 'tool_vae');
        }

        return [
            'code' => $this->fichecode,
            'treehtml' => $output->render($this->tree),
            'back' => ['url' => $url->out(false), 'text' => $words],
        ];
    }
}
