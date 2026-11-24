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
use core\output\single_button;

/**
 * The administration overview (index.php), below its heading (lot 7, MMR-520 ticket 4).
 *
 * The link to the setup, first, so that it stays when the competencies are disabled (lot S1); then, the competencies
 * disabled, an explanation the administrator can act on (acceptance criterion 10) and nothing else. Otherwise: no record
 * imported, said (the empty state is the one most often forgotten); or the records, after what is said of those whose
 * framework is gone from the core - kept for a framework of Moodle that carries the code, kept for a trace of a vaeiste
 * (each cause said for the rows it applies to: told one for the other, the manager looks for what is not there), or
 * removable, with one row and one button per record; then, for whoever may import, the two ways in, the first one the one
 * to use. The page reads the records and sorts the missing ones (record_remover) before; this class words them for the
 * template tool_vae/index_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class index_page implements named_templatable, renderable {
    /** @var bool Whether the competencies are enabled. */
    private bool $enabled;

    /** @var \stdClass[] Rows of tool_vae_source. */
    private array $sources;

    /** @var \stdClass|null The records whose framework is gone: kept, framework, traces, removable (codes); or none. */
    private ?\stdClass $missing;

    /** @var bool Whether the reader may import. */
    private bool $canimport;

    /**
     * Use disabled() or records().
     *
     * @param bool $enabled
     * @param \stdClass[] $sources
     * @param \stdClass|null $missing
     * @param bool $canimport
     */
    private function __construct(bool $enabled, array $sources, ?\stdClass $missing, bool $canimport) {
        $this->enabled = $enabled;
        $this->sources = $sources;
        $this->missing = $missing;
        $this->canimport = $canimport;
    }

    /**
     * The competencies are disabled: the link to the setup and the explanation, nothing else.
     *
     * @return self
     */
    public static function disabled(): self {
        return new self(false, [], null, false);
    }

    /**
     * The records of the site, or the sentence that there is none.
     *
     * @param \stdClass[] $sources Rows of tool_vae_source, in the order of their codes.
     * @param \stdClass|null $missing The records whose framework is gone, as the page sorted them: kept (the codes kept),
     *        framework and traces (the codes kept for each cause), removable (the codes that can be removed); null when none
     *        is missing.
     * @param bool $canimport Whether the reader may import (tool/vae:import).
     * @return self
     */
    public static function records(array $sources, ?\stdClass $missing, bool $canimport): self {
        return new self(true, $sources, $missing, $canimport);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/index_page';
    }

    /**
     * The context of the template: the link in clear; HTML only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = [
            'setup' => [
                'url' => (new \moodle_url('/admin/tool/vae/setup.php'))->out(false),
                'label' => get_string('setupheading', 'tool_vae'),
            ],
            'errorhtml' => false,
            'nonehtml' => false,
            'warninghtml' => false,
            'removablehtml' => false,
            'tablehtml' => false,
            'sourceshtml' => false,
            'buttons' => false,
        ];
        if (!$this->enabled) {
            $context['errorhtml'] = $output->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                notification::NOTIFY_ERROR
            );

            return $context;
        }

        if (!$this->sources) {
            $context['nonehtml'] = $output->notification(get_string('noframeworkimported', 'tool_vae'), notification::NOTIFY_INFO);
        } else {
            if ($this->missing) {
                $context = array_merge($context, $this->missing($output));
            }
            $context['sourceshtml'] = $output->render(new sources($this->sources));
        }

        if ($this->canimport) {
            $context['buttons'] = [
                'primaryhtml' => $output->render(new single_button(
                    new \moodle_url('/admin/tool/vae/import_fiches.php'),
                    get_string('fcimportheading', 'tool_vae'),
                    'get',
                    single_button::BUTTON_PRIMARY
                )),
                'buttonhtml' => $output->single_button(
                    new \moodle_url('/admin/tool/vae/import.php'),
                    get_string('import', 'tool_vae'),
                    'get'
                ),
            ];
        }

        return $context;
    }

    /**
     * What is said of the records whose framework is gone: the warning for those kept, and for those removable the warning
     * and the table of their buttons.
     *
     * @param renderer_base $output
     * @return array warninghtml, removablehtml, tablehtml: each false or HTML of the core or of tool_vae/table.
     */
    private function missing(renderer_base $output): array {
        $missing = $this->missing;
        $out = [];
        if ($missing->kept) {
            // The notification of the core does not escape its message: the codes are escaped here, as before.
            $warning = get_string('sourcenoframework', 'tool_vae', s(implode(', ', $missing->kept)));
            if ($missing->framework) {
                $warning .= ' ' . get_string('sourcenoframeworkheld', 'tool_vae', s(implode(', ', $missing->framework)));
            }
            if ($missing->traces) {
                $warning .= ' ' . get_string('sourcenoframeworktraced', 'tool_vae', s(implode(', ', $missing->traces)));
            }
            $out['warninghtml'] = $output->notification($warning, notification::NOTIFY_WARNING);
        }

        if ($missing->removable) {
            $out['removablehtml'] = $output->notification(
                get_string('sourcenoframeworkremovable', 'tool_vae', s(implode(', ', $missing->removable))),
                notification::NOTIFY_WARNING
            );

            // One row and one button per record, above the table of the records. A GET: the screen it opens asks the
            // confirmation, with the key of the session.
            $table = new \html_table();
            $table->attributes['class'] = 'generaltable tool-vae-removable';
            $table->head = [get_string('sourcecode', 'tool_vae'), get_string('actions')];
            foreach ($missing->removable as $code) {
                $table->data[] = [
                    new source_code((string) $code),
                    $output->single_button(
                        new \moodle_url('/admin/tool/vae/remove_record.php', ['fichecode' => $code]),
                        get_string('removerecordbutton', 'tool_vae'),
                        'get'
                    ),
                ];
            }
            $out['tablehtml'] = $output->render(new table($table));
        }

        return $out;
    }
}
