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
use tool_vae\local\text_diff;

/**
 * The page "Comparer deux versions" (compare.php), below its heading (T-81, lot 3; lot 7, MMR-520 ticket 4).
 *
 * The section, the way to its history, one sentence that says which two versions and how much moved (a reader who cannot see
 * the marks still has the answer), a notification when a version is compared to itself, and the comparison, its white space
 * kept as written. The page checks the right to read and reads the two versions before; this class words them for the
 * template tool_vae/compare_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class compare_page implements named_templatable, renderable {
    /** @var string draft_manager::label() of the section. */
    private string $section;

    /** @var \moodle_url The history of the section. */
    private \moodle_url $history;

    /** @var int The number of the older version. */
    private int $from;

    /** @var int When the older version was kept. */
    private int $fromtime;

    /** @var int The number of the newer version. */
    private int $to;

    /** @var int When the newer version was kept. */
    private int $totime;

    /** @var array[] text_diff::diff() of the two versions. */
    private array $ops;

    /**
     * The page.
     *
     * @param string $section draft_manager::label() of the section, as text.
     * @param \moodle_url $history The history of the section.
     * @param int $from The number of the older version.
     * @param int $fromtime When it was kept.
     * @param int $to The number of the newer version.
     * @param int $totime When it was kept.
     * @param array[] $ops text_diff::diff() of the two versions.
     */
    public function __construct(string $section, \moodle_url $history, int $from, int $fromtime, int $to, int $totime, array $ops) {
        $this->section = $section;
        $this->history = $history;
        $this->from = $from;
        $this->fromtime = $fromtime;
        $this->to = $to;
        $this->totime = $totime;
        $this->ops = $ops;
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/compare_page';
    }

    /**
     * The context of the template: plain texts and addresses, not escaped; HTML only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'section' => get_string('faisabsectionline', 'tool_vae', $this->section),
            'history' => ['url' => $this->history->out(false), 'label' => get_string('historylink', 'tool_vae')],
            'summary' => get_string('comparesummary', 'tool_vae', (object) [
                'from' => $this->from,
                'fromdate' => userdate($this->fromtime),
                'to' => $this->to,
                'todate' => userdate($this->totime),
                'summary' => get_string('diffsummary', 'tool_vae', (object) text_diff::counts($this->ops)),
            ]),
            'samehtml' => $this->from === $this->to
                ? $output->notification(get_string('comparesame', 'tool_vae'), notification::NOTIFY_INFO)
                : false,
            // The block of the comparison, drawn by its own template (it was text_diff::html() until lot 7, wave 4c).
            'diffhtml' => $output->render(new compare_diff($this->ops)),
        ];
    }
}
