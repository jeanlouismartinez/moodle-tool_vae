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
use core\output\renderable;
use core\output\renderer_base;
use tool_vae\local\selfpos_screen;

/**
 * One node of the self-positioning (lot 1a): the form (given by the page), the word of the structure next to the vaeiste's
 * own, the lines of the record before, and the history.
 *
 * NO TOTAL, NO RATE, NO GAP: the word of the structure is put next to the vaeiste's own, never compared with it. The
 * justification of a line is free text, drawn by format_text() with the format the vaeiste chose, as before. Was
 * selfpos_screen::node(), history() and line() (lot 7, MMR-520 ticket 4).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class selfpos_node implements named_templatable, renderable {
    /** @var int The vaeiste. */
    private int $candidateid;

    /** @var string The record. */
    private string $fichecode;

    /** @var \stdClass A node of selfpos_tree::for_record(). */
    private \stdClass $node;

    /** @var \stdClass[] Every line of the node, the earliest first. */
    private array $lines;

    /** @var \stdClass As selfpos_regard::for_node() gives it. */
    private \stdClass $regard;

    /** @var array What the record this one replaces positioned on the equivalent node. */
    private array $successor;

    /** @var bool Whether the reader is the vaeiste and may position themselves. */
    private bool $canwrite;

    /** @var string The form, rendered, when there is one to show. */
    private string $formhtml;

    /**
     * One node.
     *
     * @param int $candidateid
     * @param string $fichecode
     * @param \stdClass $node A node of selfpos_tree::for_record().
     * @param \stdClass[] $lines Every line of the node, the earliest first.
     * @param \stdClass $regard As selfpos_regard::for_node() gives it.
     * @param array $successor What the record this one replaces positioned on the equivalent node, as selfpos_successor gives it.
     * @param bool $canwrite Whether the reader is the vaeiste and may position themselves.
     * @param string $formhtml The form, rendered by the core (moodleform::render()), when there is one to show.
     */
    public function __construct(
        int $candidateid,
        string $fichecode,
        \stdClass $node,
        array $lines,
        \stdClass $regard,
        array $successor,
        bool $canwrite,
        string $formhtml = ''
    ) {
        $this->candidateid = $candidateid;
        $this->fichecode = $fichecode;
        $this->node = $node;
        $this->lines = $lines;
        $this->regard = $regard;
        $this->successor = $successor;
        $this->canwrite = $canwrite;
        $this->formhtml = $formhtml;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/selfpos_node';
    }

    /**
     * The context of the template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = \context_user::instance($this->candidateid);
        $node = $this->node;
        $regard = $this->regard;
        $structure = null;
        if ($regard->structure !== null) {
            $structure = ['text' => get_string('selfposregardline', 'tool_vae', (object) [
                'status' => get_string($regard->structure->kind . 'status' . $regard->structure->status, 'tool_vae'),
                'when' => userdate($regard->structure->time, get_string('strftimedate', 'langconfig')),
            ])];
        }
        $declarations = null;
        if ($regard->declarations > 0) {
            $declarations = ['text' => get_string('selfposdeclarations', 'tool_vae', (object) [
                'declarations' => $regard->declarations, 'attested' => $regard->attested, 'pieces' => $regard->pieces,
            ])];
        }
        $successor = [];
        foreach ($this->successor as $entry) {
            $successor[] = ['text' => get_string('selfposonold', 'tool_vae', (object) [
                'code' => $entry->fromcode,
                'rank' => $entry->fromrank,
                'label' => $entry->levellabel,
                'when' => userdate($entry->time, get_string('strftimedate', 'langconfig')),
            ])];
        }

        return [
            'disclaimerhtml' => $output->render(new selfpos_disclaimer($this->candidateid)),
            'levelname' => $node->levelname,
            'fichecode' => $this->fichecode,
            'label' => $node->label,
            'notpositionable' => $node->positionable ? null : ['text' => get_string('selfposexplainnotpositionable', 'tool_vae')],
            'formhtml' => $node->positionable && $this->canwrite ? $this->formhtml : '',
            'regardheading' => get_string('selfposregard', 'tool_vae'),
            'regardnone' => $structure === null ? ['text' => get_string('selfposregardnone', 'tool_vae')] : null,
            'regardline' => $structure,
            'declarations' => $declarations,
            'successor' => $successor,
            'historyheading' => get_string('selfposhistory', 'tool_vae'),
            'history' => $this->history($context),
            'backurl' => selfpos_screen::url($this->candidateid, $this->fichecode)->out(false),
            'back' => get_string('selfposback', 'tool_vae'),
        ];
    }

    /**
     * The history of the node: the line that stands, then the earlier ones, the latest first.
     *
     * @param \context $context The context of the vaeiste.
     * @return array none (object: text, or null) and lines (each: heading, an object or null, and the line).
     */
    private function history(\context $context): array {
        if (!$this->lines) {
            return ['none' => ['text' => get_string('selfposnoline', 'tool_vae')], 'lines' => []];
        }
        $rows = array_values($this->lines);
        $standing = array_pop($rows);
        $lines = [['heading' => ['text' => get_string('selfposstanding', 'tool_vae')]] + $this->line($standing, $context)];
        foreach (array_reverse($rows) as $i => $row) {
            $heading = $i === 0 ? ['text' => get_string('selfposearlier', 'tool_vae')] : null;
            $lines[] = ['heading' => $heading] + $this->line($row, $context);
        }

        return ['none' => null, 'lines' => $lines];
    }

    /**
     * One line: its step, its date, and its justification.
     *
     * @param \stdClass $row A row of tool_vae_selfpos.
     * @param \context $context
     * @return array levellabel, when, justification (object: justificationhtml, or null).
     */
    private function line(\stdClass $row, \context $context): array {
        $when = userdate((int) $row->timecreated, get_string('strftimedatetime', 'langconfig'));
        if (!empty($row->importedat)) {
            $when .= ' - ' . get_string('selfposrestored', 'tool_vae');
        }
        $justification = null;
        if ((string) $row->justification !== '') {
            $format = (int) $row->justificationformat === 4 ? FORMAT_MARKDOWN : FORMAT_PLAIN;
            $options = ['context' => $context, 'filter' => false];
            $justification = ['justificationhtml' => format_text((string) $row->justification, $format, $options)];
        }

        return ['levellabel' => (string) $row->levellabel, 'when' => $when, 'justification' => $justification];
    }
}
