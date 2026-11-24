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
use tool_vae\local\selfpos_manager;
use tool_vae\local\selfpos_screen;

/**
 * The page that declares an activity (declare.php), under its title, in its four states that exclude each other: the
 * competencies off, the core's right to view them missing, no record left to choose (the text sent given back), and the form
 * with what stands above it - the refusal of a record hidden since, the records hidden, and where the vaeiste stands on each
 * record they declared on, folded (lot 7, MMR-520 ticket 4, wave 3b).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class declare_page implements named_templatable, renderable {
    /** @var string disabled, noview, nothing or form. */
    private string $state;

    /** @var string[] The message of each record hidden, as hidden_frameworks gives it (already escaped). */
    private array $hidden = [];

    /** @var kept_text|null The text sent, for the page with no form. */
    private ?kept_text $kept = null;

    /** @var string|null The message of a refusal, for the form. */
    private ?string $refusal = null;

    /** @var int The vaeiste. */
    private int $candidateid = 0;

    /** @var string[] The records the vaeiste declared on. */
    private array $declaredcodes = [];

    /** @var \moodleform|null The form. */
    private ?\moodleform $form = null;

    /**
     * A state of the page.
     *
     * @param string $state
     */
    private function __construct(string $state) {
        $this->state = $state;
    }

    /**
     * The competencies of the site are off.
     *
     * @return self
     */
    public static function disabled(): self {
        return new self('disabled');
    }

    /**
     * The role of the vaeiste does not hold the core's right to view the competencies.
     *
     * @return self
     */
    public static function noview(): self {
        return new self('noview');
    }

    /**
     * No record is left to choose: why, and the text sent given back.
     *
     * @param string[] $hidden The message of each record hidden (already escaped).
     * @param kept_text $kept The text sent; it draws nothing when nothing was sent.
     * @return self
     */
    public static function nothing(array $hidden, kept_text $kept): self {
        $page = new self('nothing');
        $page->hidden = $hidden;
        $page->kept = $kept;

        return $page;
    }

    /**
     * The form, and what stands above it.
     *
     * @param string|null $refusal The message of the record the form was refused for, or null.
     * @param string[] $hidden The message of each record hidden (already escaped).
     * @param int $candidateid The vaeiste.
     * @param string[] $declaredcodes The records the vaeiste declared on, in order.
     * @param \moodleform $form The form of the declaration.
     * @return self
     */
    public static function form(
        ?string $refusal,
        array $hidden,
        int $candidateid,
        array $declaredcodes,
        \moodleform $form
    ): self {
        $page = new self('form');
        $page->refusal = $refusal;
        $page->hidden = $hidden;
        $page->candidateid = $candidateid;
        $page->declaredcodes = $declaredcodes;
        $page->form = $form;

        return $page;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/declare_page';
    }

    /**
     * The context of the template: the notifications drawn, the sentence of a refusal, the way to position oneself, the folds
     * of the panels, the text given back and the form.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = [
            'notices' => [],
            'keptnote' => null,
            'others' => [],
            'selfpos' => null,
            'panels' => [],
            'kepthtml' => '',
            'formhtml' => '',
        ];
        switch ($this->state) {
            case 'disabled':
                $context['notices'][] = ['noticehtml' => $output->notification(
                    get_string('errorcompetenciesdisabled', 'tool_vae'),
                    notification::NOTIFY_ERROR
                )];
                break;
            case 'noview':
                $context['notices'][] = ['noticehtml' => $output->notification(
                    get_string('errordeclarenoview', 'tool_vae'),
                    notification::NOTIFY_ERROR
                )];
                break;
            case 'nothing':
                // When every record is hidden, saying so is the truth; "no record imported" would not be. A send that
                // arrives here was refused, which is not the same thing as a page that is only read.
                $level = $this->kept->drawn() ? notification::NOTIFY_ERROR : notification::NOTIFY_INFO;
                foreach ($this->hidden as $message) {
                    $context['notices'][] = ['noticehtml' => $output->notification($message, $level)];
                }
                if (!$this->hidden) {
                    $context['notices'][] = ['noticehtml' => $output->notification(
                        get_string('noframeworkimported', 'tool_vae'),
                        notification::NOTIFY_INFO
                    )];
                }
                $context['kepthtml'] = $output->render($this->kept);
                break;
            default:
                $context = $this->form_parts($output) + $context;
        }

        return $context;
    }

    /**
     * What the page with the form draws.
     *
     * @param renderer_base $output
     * @return array
     */
    private function form_parts(renderer_base $output): array {
        $notices = [];
        $keptnote = null;
        if ($this->refusal !== null) {
            $notices[] = ['noticehtml' => $output->notification($this->refusal, notification::NOTIFY_ERROR)];
            $keptnote = ['text' => get_string('declarehiddenkept', 'tool_vae')];
        }
        $others = [];
        foreach ($this->hidden as $message) {
            // The record the form was refused for has just been named.
            if ($message !== $this->refusal) {
                $others[] = ['noticehtml' => $output->notification($message, notification::NOTIFY_INFO)];
            }
        }
        // With no declaration yet, one way to position oneself, for whoever may.
        $selfpos = null;
        if (!$this->declaredcodes && selfpos_manager::can_write($this->candidateid)) {
            $selfpos = [
                'url' => selfpos_screen::url($this->candidateid)->out(false),
                'text' => get_string('selfposlink', 'tool_vae'),
            ];
        }
        // Where the vaeiste stands on each record they declared on, folded, in reading (lot 1b, spec 1a § 6.6).
        $panels = [];
        foreach ($this->declaredcodes as $declaredcode) {
            $panel = new whereami_tree($this->candidateid, (string) $declaredcode, ['link' => true, 'title' => '']);
            if ($panel->drawn()) {
                $panels[] = [
                    'summary' => get_string('whereamideclare', 'tool_vae', $declaredcode),
                    'panelhtml' => $output->render($panel),
                ];
            }
        }

        return [
            'notices' => $notices,
            'keptnote' => $keptnote,
            'others' => $others,
            'selfpos' => $selfpos,
            'panels' => $panels,
            'formhtml' => $this->form->render(),
        ];
    }
}
