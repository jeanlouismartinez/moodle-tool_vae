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
use tool_vae\local\draft_manager;
use tool_vae\local\feasibility_catalog;
use tool_vae\local\feasibility_fields;
use tool_vae\local\validation_catalog;

/**
 * The page that writes one section of one's own document (section.php), under its title, in its three states that exclude
 * each other (lot 7, MMR-520 ticket 4, wave 3b): the contact details read from the account; a section of separate fields
 * and its form; a section of free text - the conflict of a version refused, the notices of the document, the help, where
 * the section stands, its ways out, the situations tagged on the bloc for the part "lier", the form and the live region of
 * the autosave.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class section_page implements named_templatable, renderable {
    /** @var string account, fields or text. */
    private string $state;

    /** @var string The code of the section. */
    private string $sectioncode;

    /** @var array The values of the account, by key (account). */
    private array $shown = [];

    /** @var int The writer. */
    private int $userid = 0;

    /** @var string The document. */
    private string $doctype = draft_manager::DOCTYPE_FAISABILITE;

    /** @var string The record, for the preparation of the validation dossier. */
    private string $fichecode = '';

    /** @var \stdClass|null The draft of the section. */
    private ?\stdClass $draft = null;

    /** @var bool Whether a version was refused for a conflict. */
    private bool $conflict = false;

    /** @var \moodle_url|null The address of the page. */
    private ?\moodle_url $url = null;

    /** @var \moodle_url|null The history of the section. */
    private ?\moodle_url $history = null;

    /** @var \moodle_url|null Where "back" goes. */
    private ?\moodle_url $backurl = null;

    /** @var string The words of "back". */
    private string $backwords = '';

    /** @var \moodleform|null The form. */
    private ?\moodleform $form = null;

    /**
     * A state of the page.
     *
     * @param string $state
     * @param string $sectioncode
     */
    private function __construct(string $state, string $sectioncode) {
        $this->state = $state;
        $this->sectioncode = $sectioncode;
    }

    /**
     * The contact details, read from the account, never kept here (T-10, lot 3, MC-10), and where to change them.
     *
     * @param string $sectioncode
     * @param array $shown The values of the account, by key, as feasibility_fields::account_values() gives them.
     * @param int $userid The writer, whose profile is edited.
     * @return self
     */
    public static function account(string $sectioncode, array $shown, int $userid): self {
        $page = new self('account', $sectioncode);
        $page->shown = $shown;
        $page->userid = $userid;

        return $page;
    }

    /**
     * A section of separate fields of the dossier de faisabilité, and its form.
     *
     * @param string $sectioncode
     * @param \stdClass $draft The draft of the section.
     * @param \moodle_url $history The history of the section.
     * @param \moodleform $form The form of the fields.
     * @return self
     */
    public static function fields(string $sectioncode, \stdClass $draft, \moodle_url $history, \moodleform $form): self {
        $page = new self('fields', $sectioncode);
        $page->draft = $draft;
        $page->history = $history;
        $page->form = $form;

        return $page;
    }

    /**
     * A section of free text, and its form.
     *
     * @param string $doctype The document.
     * @param string $sectioncode
     * @param string $fichecode The record, for the preparation of the validation dossier; '' otherwise.
     * @param int $userid The writer.
     * @param \stdClass $draft The draft of the section.
     * @param bool $conflict Whether a version was just refused for a conflict.
     * @param \moodle_url $url The address of the page (to reload it).
     * @param \moodle_url $history The history of the section.
     * @param \moodle_url $backurl Where "back" goes.
     * @param string $backwords The words of "back".
     * @param \moodleform $form The form of the text.
     * @return self
     */
    public static function text(
        string $doctype,
        string $sectioncode,
        string $fichecode,
        int $userid,
        \stdClass $draft,
        bool $conflict,
        \moodle_url $url,
        \moodle_url $history,
        \moodle_url $backurl,
        string $backwords,
        \moodleform $form
    ): self {
        $page = new self('text', $sectioncode);
        $page->doctype = $doctype;
        $page->fichecode = $fichecode;
        $page->userid = $userid;
        $page->draft = $draft;
        $page->conflict = $conflict;
        $page->url = $url;
        $page->history = $history;
        $page->backurl = $backurl;
        $page->backwords = $backwords;
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
        return 'tool_vae/section_page';
    }

    /**
     * The context of the template.
     *
     * @param renderer_base $output
     * @return array account, fields and text, each an object or null.
     */
    public function export_for_template(renderer_base $output): array {
        return [
            'account' => $this->state === 'account' ? $this->account_parts() : null,
            'fields' => $this->state === 'fields' ? $this->fields_parts() : null,
            'text' => $this->state === 'text' ? $this->text_parts($output) : null,
        ];
    }

    /**
     * A help, as an object for a section.
     *
     * @param string|null $help
     * @return array|null
     */
    private static function help(?string $help): ?array {
        return $help === null ? null : ['text' => $help];
    }

    /**
     * A way out.
     *
     * @param \moodle_url $url
     * @param string $text
     * @return array
     */
    private static function link(\moodle_url $url, string $text): array {
        return ['url' => $url->out(false), 'text' => $text];
    }

    /**
     * The contact details.
     *
     * @return array
     */
    private function account_parts(): array {
        $rows = [];
        foreach (feasibility_fields::account_keys()[$this->sectioncode] as $key) {
            $rows[] = [
                'term' => get_string('faisabaccount:' . $key, 'tool_vae'),
                // The account's values are text the user typed into their profile: escaped like everything else drawn here.
                'value' => (string) ($this->shown[$key] ?? get_string('faisabnotfilled', 'tool_vae')),
            ];
        }

        return [
            'help' => self::help(feasibility_catalog::help($this->sectioncode)),
            'rows' => $rows,
            'profile' => self::link(
                new \moodle_url('/user/edit.php', ['id' => $this->userid]),
                get_string('faisabeditprofile', 'tool_vae')
            ),
            'back' => self::link(new \moodle_url('/admin/tool/vae/faisabilite.php'), get_string('faisabbacktolist', 'tool_vae')),
        ];
    }

    /**
     * A section of separate fields.
     *
     * @return array
     */
    private function fields_parts(): array {
        $current = (int) $this->draft->currentversion;

        return [
            'help' => self::help(feasibility_catalog::help($this->sectioncode)),
            'status' => $current > 0
                ? get_string('sectioncurrentversion', 'tool_vae', $current)
                : get_string('sectionnoversionfields', 'tool_vae'),
            'history' => self::link($this->history, get_string('historylink', 'tool_vae')),
            'back' => self::link(new \moodle_url('/admin/tool/vae/faisabilite.php'), get_string('faisabbacktolist', 'tool_vae')),
            'formhtml' => $this->form->render(),
        ];
    }

    /**
     * A section of free text.
     *
     * @param renderer_base $output
     * @return array
     */
    private function text_parts(renderer_base $output): array {
        $isvalidation = $this->doctype === draft_manager::DOCTYPE_VALIDATION;
        $isreflexion = $this->doctype === draft_manager::DOCTYPE_REFLEXION;
        $conflict = null;
        if ($this->conflict) {
            $conflict = [
                'noticehtml' => $output->notification(get_string('errordraftconflict', 'tool_vae'), notification::NOTIFY_ERROR),
                'kept' => get_string('draftconflicttextkept', 'tool_vae'),
                'reload' => self::link($this->url, get_string('draftconflictreload', 'tool_vae')),
                'history' => self::link($this->history, get_string('historylink', 'tool_vae')),
            ];
        }
        $help = $this->doctype === draft_manager::DOCTYPE_FAISABILITE
            ? feasibility_catalog::help($this->sectioncode)
            : ($isvalidation ? validation_catalog::help($this->sectioncode) : null);
        $current = (int) $this->draft->currentversion;
        // The part "lier": the situations the vaeiste tagged on this bloc or under it, in reading, to link from (lot 1b).
        $taggedhtml = '';
        $parsed = $isvalidation ? validation_catalog::parse($this->sectioncode) : null;
        if ($parsed !== null && $parsed[1] === 'lier') {
            $tagged = new tagged_situations($this->userid, $this->fichecode, $parsed[0]);
            $taggedhtml = $tagged->drawn() ? $output->render($tagged) : '';
        }
        $formhtml = $this->form->render();

        return [
            'conflict' => $conflict,
            // The preparation is not the official dossier, and says so on every screen (MC-17).
            'labelling' => $isvalidation ? ['text' => get_string('validationlabelling', 'tool_vae')] : null,
            // Said on every entry, and not to be closed: nobody else reads the journal (T-14, MC-14).
            'reflexion' => $isreflexion ? [
                'private' => get_string('reflexionprivate', 'tool_vae'),
                'consigne' => get_string('reflexionconsigne', 'tool_vae'),
                'prompts' => get_string('reflexionprompts', 'tool_vae'),
            ] : null,
            'help' => self::help($help),
            // The situations told here can identify a person without naming them (MC-14).
            'consigne' => $isvalidation && validation_catalog::describes_situations($this->sectioncode)
                ? ['text' => get_string('validationconsigne', 'tool_vae')] : null,
            'status' => $current > 0
                ? get_string('sectioncurrentversion', 'tool_vae', $current)
                : get_string('sectionnoversion', 'tool_vae'),
            'history' => self::link($this->history, get_string('historylink', 'tool_vae')),
            'back' => self::link($this->backurl, $this->backwords),
            'taggedhtml' => $taggedhtml,
            'formhtml' => $formhtml,
            // A live region, not a toast. Empty after a conflict: the draft saved at that time is another tab's.
            'autosaved' => $this->draft->workingat && !$this->conflict
                ? get_string('draftautosaved', 'tool_vae', userdate((int) $this->draft->workingat)) : '',
        ];
    }
}
