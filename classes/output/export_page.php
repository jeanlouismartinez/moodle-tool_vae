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
use tool_vae\local\exporter;

/**
 * The page "Exporter le dossier" (export.php), between its header or heading and the footer (lot 8; lot 7, MMR-520).
 *
 * Four states, which exclude one another: the sub-system of competencies is off (a notification, below the header); the
 * dossier holds no declaration (a notification and the way back to the dossier); the archive is produced (the digest this
 * platform registered, the lines the schema refused, the link to download it and the way back to the dossier); or, above the
 * confirmation of the core that the page draws itself, what an export is, the archive the vaeiste keeps in place, and the
 * notice that a withdrawal does not reach an archive. The page checks the rights, reads, produces the archive and registers
 * it before; this class words the state for the template tool_vae/export_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class export_page implements named_templatable, renderable {
    /** @var string The state: disabled, nodeclaration, done or intro. */
    private string $state;

    /** @var bool Whether the person who exports is the vaeiste. */
    private bool $own;

    /** @var \stdClass|null exporter::registered() of the archive produced: archiveuuid, sha256. */
    private ?\stdClass $registered;

    /** @var array[] exporter::warnings() of the archive produced. */
    private array $warnings;

    /** @var \stored_file|null The archive produced, or the one the vaeiste keeps in place. */
    private ?\stored_file $file;

    /** @var \moodle_url|null The dossier, when the page offers the way back to it. */
    private ?\moodle_url $dossier;

    /**
     * Use disabled(), nodeclaration(), done() or intro().
     *
     * @param string $state
     * @param bool $own
     * @param \stdClass|null $registered
     * @param array $warnings
     * @param \stored_file|null $file
     * @param \moodle_url|null $dossier
     */
    private function __construct(
        string $state,
        bool $own = false,
        ?\stdClass $registered = null,
        array $warnings = [],
        ?\stored_file $file = null,
        ?\moodle_url $dossier = null
    ) {
        $this->state = $state;
        $this->own = $own;
        $this->registered = $registered;
        $this->warnings = $warnings;
        $this->file = $file;
        $this->dossier = $dossier;
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
     * The page of a dossier that holds no declaration: there is nothing to export.
     *
     * @param \moodle_url $dossier The dossier of the vaeiste, to go back to.
     * @return self
     */
    public static function nodeclaration(\moodle_url $dossier): self {
        return new self('nodeclaration', false, null, [], null, $dossier);
    }

    /**
     * The page that answers the export: the archive is produced.
     *
     * @param bool $own Whether the person who exports is the vaeiste (their archive is kept; another person's lives a day).
     * @param \stdClass $registered exporter::registered() of the archive: archiveuuid, sha256.
     * @param array[] $warnings exporter::warnings(): the lines the schema refused, which did not refuse the archive.
     * @param \stored_file $file The archive.
     * @param \moodle_url $dossier The dossier of the vaeiste, to go back to.
     * @return self
     */
    public static function done(bool $own, \stdClass $registered, array $warnings, \stored_file $file, \moodle_url $dossier): self {
        return new self('done', $own, $registered, $warnings, $file, $dossier);
    }

    /**
     * What an export is, above the confirmation of the core.
     *
     * @param bool $own Whether the person who exports is the vaeiste: the text speaks to them, or of them.
     * @param \stored_file|null $kept exporter::kept(): the archive the vaeiste keeps in place, read for the vaeiste only.
     * @return self
     */
    public static function intro(bool $own, ?\stored_file $kept): self {
        return new self('intro', $own, null, [], $kept);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/export_page';
    }

    /**
     * The context of the template: plain texts and addresses, not escaped; HTML of the core only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = [
            'noticehtml' => false,
            'warninghtml' => false,
            'intro' => false,
            'keptnoticehtml' => false,
            'withdrawalhtml' => false,
            'download' => false,
            'continuehtml' => $this->dossier === null ? false : $output->continue_button($this->dossier),
        ];
        if ($this->state === 'disabled') {
            $context['noticehtml'] = $output->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                notification::NOTIFY_ERROR
            );
        } else if ($this->state === 'nodeclaration') {
            $context['noticehtml'] = $output->notification(get_string('errorexportempty', 'tool_vae'), notification::NOTIFY_INFO);
        } else if ($this->state === 'done') {
            // The vaeiste's own archive is kept, one per vaeiste; the one another person exports lives a day.
            $context['noticehtml'] = $output->notification(
                get_string($this->own ? 'exportdonekept' : 'exportdone', 'tool_vae', (object) [
                    'archive' => $this->registered->archiveuuid,
                    'sha256' => $this->registered->sha256,
                ]),
                notification::NOTIFY_SUCCESS
            );
            // The lines are escaped by the piece export_warnings: the value is whatever an administrator typed.
            if ($this->warnings) {
                $context['warninghtml'] = $output->notification(
                    $output->render(new export_warnings($this->warnings, get_string('exportwarning', 'tool_vae'))),
                    notification::NOTIFY_WARNING
                );
            }
            $context['download'] = [
                'url' => self::download($this->file)->out(false),
                'label' => get_string('exportdownload', 'tool_vae'),
            ];
        } else {
            $context['intro'] = $this->own ? get_string('exportintro', 'tool_vae') : get_string('exportintroother', 'tool_vae');
            if ($this->file) {
                // The sentence and the link, side by side, drawn by the template of a cell: the notification of the core
                // places its message as HTML.
                $context['keptnoticehtml'] = $output->notification(
                    $output->render(new cell_content([
                        cell_content::text(get_string('exportkeptcurrent', 'tool_vae', (object) [
                            'date' => userdate((int) $this->file->get_timecreated()),
                            'id' => substr($this->file->get_filename(), -30, 26),
                        ]) . ' '),
                        cell_content::link(self::download($this->file), get_string('exportdownload', 'tool_vae')),
                    ])),
                    notification::NOTIFY_INFO
                );
            }
            $context['withdrawalhtml'] = $output->notification(
                get_string('exportwithdrawalnotice', 'tool_vae'),
                notification::NOTIFY_WARNING
            );
        }

        return $context;
    }

    /**
     * The address that serves a file of the archive, as a download.
     *
     * @param \stored_file $file
     * @return \moodle_url
     */
    private static function download(\stored_file $file): \moodle_url {
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'tool_vae',
            exporter::FILEAREA,
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            true
        );
    }
}
