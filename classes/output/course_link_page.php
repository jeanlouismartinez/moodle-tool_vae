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
use tool_vae\local\display_date;

/**
 * The page "Rattacher une fiche au cours" (course_link.php), between its header or heading and the footer (lot 8 bis; lot 7,
 * MMR-520 ticket 4).
 *
 * Three states, which exclude one another: the sub-system of competencies is off (a notification, below the header); no
 * record is imported (a notification, below the heading); or the records, each with its date, how many of its competencies
 * the course holds, and what the manager can do: attach it (a button of the core), nothing (all in the course), or the reason
 * (a framework hidden in the core). The page checks the right, attaches and redirects, reads the records before; this class
 * words them for the template tool_vae/course_link_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_link_page implements named_templatable, renderable {
    /** @var string The state: disabled, none or records. */
    private string $state;

    /** @var \stdClass[] course_linker::records(). */
    private array $records;

    /** @var \moodle_url|null The address of the page, to which a record is sent. */
    private ?\moodle_url $url;

    /**
     * Use disabled(), none() or records().
     *
     * @param string $state
     * @param \stdClass[] $records
     * @param \moodle_url|null $url
     */
    private function __construct(string $state, array $records = [], ?\moodle_url $url = null) {
        $this->state = $state;
        $this->records = $records;
        $this->url = $url;
    }

    /**
     * The page when the sub-system of competencies is off.
     *
     * @return self
     */
    public static function disabled(): self {
        return new self('disabled');
    }

    /**
     * The page when no record is imported on the site.
     *
     * @return self
     */
    public static function none(): self {
        return new self('none');
    }

    /**
     * The page of the records.
     *
     * @param \stdClass[] $records course_linker::records(), not empty.
     * @param \moodle_url $url The address of the page.
     * @return self
     */
    public static function records(array $records, \moodle_url $url): self {
        return new self('records', $records, $url);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/course_link_page';
    }

    /**
     * The context of the template: plain texts, not escaped; HTML of the core or of the table only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = ['noticehtml' => false, 'intro' => false, 'tablehtml' => false];
        if ($this->state === 'disabled') {
            $context['noticehtml'] = $output->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                notification::NOTIFY_ERROR
            );
        } else if ($this->state === 'none') {
            $context['noticehtml'] = $output->notification(get_string('linkrecordnone', 'tool_vae'), notification::NOTIFY_INFO);
        } else {
            $context['intro'] = get_string('linkrecordintro', 'tool_vae');
            $context['tablehtml'] = $output->render(new table($this->table($output)));
        }

        return $context;
    }

    /**
     * The table of the records: code and type, date, competencies in the course, the action.
     *
     * @param renderer_base $output
     * @return \html_table
     */
    private function table(renderer_base $output): \html_table {
        $table = new \html_table();
        $table->head = [
            get_string('linkrecordcode', 'tool_vae'),
            get_string('linkrecordupdated', 'tool_vae'),
            get_string('linkrecordincourse', 'tool_vae'),
            '',
        ];
        foreach ($this->records as $record) {
            $complete = $record->total > 0 && $record->incourse >= $record->total;
            if ($record->hidden) {
                // A framework hidden in tool_lp is one the core will not attach: the reason, instead of a button that ends on
                // an error.
                $action = new cell_content([cell_content::span('small text-muted', get_string('linkrecordhidden', 'tool_vae'))]);
            } else {
                $action = $complete ? '' : $output->single_button(
                    new \moodle_url($this->url, ['fichecode' => $record->fichecode]),
                    get_string('linkrecordbutton', 'tool_vae'),
                    'post'
                );
            }
            $table->data[] = [
                new cell_content([cell_content::text(
                    $record->fichecode . ' (' . \core_text::strtoupper($record->fichetype) . ')'
                )]),
                new cell_content([cell_content::text($record->sourceupdated ? display_date::format(
                    (int) $record->sourceupdated,
                    get_string('strftimedate', 'langconfig')
                ) : '-')]),
                new cell_content([cell_content::text(get_string('linkrecordcount', 'tool_vae', $record))]),
                $action,
            ];
        }

        return $table;
    }
}
