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
use tool_vae\local\structure_archive;

/**
 * The page "Archive de la structure" (structure.php), below its heading (lot 7, MMR-520 ticket 4).
 *
 * Four states, which exclude one another: the sub-system of competencies is off, or the production is refused (a
 * notification and the way back to the dossier); the archive is produced (the digest this platform registered, the link to
 * download it and the way back to the dossier); or, above the confirmation of the core that the page draws itself, what the
 * archive is, who may read it, and whether it replaces one in place. The page checks the right, produces the archive and
 * registers it before; this class words the state for the template tool_vae/structure_page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class structure_page implements named_templatable, renderable {
    /** @var string The state: disabled, failed, done or intro. */
    private string $state;

    /** @var string The message of the refusal, in words. */
    private string $message;

    /** @var \stdClass|null exporter::registered() of the archive produced: archiveuuid, sha256. */
    private ?\stdClass $registered;

    /** @var \stored_file|null The archive produced. */
    private ?\stored_file $file;

    /** @var bool Whether the archive to produce replaces one in place. */
    private bool $replaces;

    /** @var \moodle_url|null The dossier, when the page offers the way back to it. */
    private ?\moodle_url $dossier;

    /**
     * Use disabled(), failed(), done() or intro().
     *
     * @param string $state
     * @param \moodle_url|null $dossier
     * @param string $message
     * @param \stdClass|null $registered
     * @param \stored_file|null $file
     * @param bool $replaces
     */
    private function __construct(
        string $state,
        ?\moodle_url $dossier = null,
        string $message = '',
        ?\stdClass $registered = null,
        ?\stored_file $file = null,
        bool $replaces = false
    ) {
        $this->state = $state;
        $this->dossier = $dossier;
        $this->message = $message;
        $this->registered = $registered;
        $this->file = $file;
        $this->replaces = $replaces;
    }

    /**
     * The page when the sub-system of competencies is off.
     *
     * @param \moodle_url $dossier The dossier of the vaeiste, to go back to.
     * @return self
     */
    public static function disabled(\moodle_url $dossier): self {
        return new self('disabled', $dossier);
    }

    /**
     * The page when the production is refused.
     *
     * @param string $message The message of the exception, as text.
     * @param \moodle_url $dossier The dossier of the vaeiste, to go back to.
     * @return self
     */
    public static function failed(string $message, \moodle_url $dossier): self {
        return new self('failed', $dossier, $message);
    }

    /**
     * The page that answers the production: the archive is produced.
     *
     * @param \stdClass $registered exporter::registered() of the archive: archiveuuid, sha256.
     * @param \stored_file $file The archive.
     * @param \moodle_url $dossier The dossier of the vaeiste, to go back to.
     * @return self
     */
    public static function done(\stdClass $registered, \stored_file $file, \moodle_url $dossier): self {
        return new self('done', $dossier, '', $registered, $file);
    }

    /**
     * What the archive is, above the confirmation of the core.
     *
     * @param bool $replaces Whether an archive of the structure is in place (structure_archive::current()).
     * @return self
     */
    public static function intro(bool $replaces): self {
        return new self('intro', null, '', null, null, $replaces);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/structure_page';
    }

    /**
     * The context of the template: plain texts and addresses, not escaped; HTML of the core only in the keys ...html.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = [
            'intro' => false,
            'noticehtml' => false,
            'replaceshtml' => false,
            'download' => false,
            'continuehtml' => $this->dossier === null ? false : $output->continue_button($this->dossier),
        ];
        if ($this->state === 'disabled') {
            $context['noticehtml'] = $output->notification(
                get_string('errorcompetenciesdisabled', 'tool_vae'),
                notification::NOTIFY_ERROR
            );
        } else if ($this->state === 'failed') {
            // The notification of the core places its message as HTML: the message is text.
            $context['noticehtml'] = $output->notification(s($this->message), notification::NOTIFY_ERROR);
        } else if ($this->state === 'done') {
            $context['noticehtml'] = $output->notification(
                get_string('structuredone', 'tool_vae', (object) [
                    'archive' => $this->registered->archiveuuid,
                    'sha256' => $this->registered->sha256,
                ]),
                notification::NOTIFY_SUCCESS
            );
            $context['download'] = [
                'url' => \moodle_url::make_pluginfile_url(
                    $this->file->get_contextid(),
                    'tool_vae',
                    structure_archive::FILEAREA,
                    $this->file->get_itemid(),
                    $this->file->get_filepath(),
                    $this->file->get_filename(),
                    true
                )->out(false),
                'label' => get_string('structuredownload', 'tool_vae'),
            ];
        } else {
            $context['intro'] = get_string('structureintro', 'tool_vae');
            $context['noticehtml'] = $output->notification(
                get_string('structurenotice', 'tool_vae'),
                notification::NOTIFY_INFO
            );
            if ($this->replaces) {
                $context['replaceshtml'] = $output->notification(
                    get_string('structurereplaces', 'tool_vae'),
                    notification::NOTIFY_WARNING
                );
            }
        }

        return $context;
    }
}
