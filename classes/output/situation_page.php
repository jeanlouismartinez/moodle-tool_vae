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
use tool_vae\local\note_manager;
use tool_vae\local\selfpos_tree;

/**
 * The page of one work situation (situation.php), under its title, out of the confirmation of a deletion (a screen of the
 * core the page draws itself) (lot 7, MMR-520 ticket 4, wave 4a): the code of the record, the sentences of every screen, the
 * error of a send, the conflict of a version, the situation that may not be written now with the text given back; then
 * either the two forms of the vaeiste and the live region of the autosave, or the reading (details, latest version, draft
 * in progress); the way to the versions, the pieces and their forms, the annotations, the button "delete" and the way back.
 *
 * Was the screens situation_screen::details(), text(), closed() and refused(), and the HTML the page wrote around them. The
 * forms are drawn here, once each, in the order the page drew them (details, text, file, annotation).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class situation_page implements named_templatable, renderable {
    /** @var array What the page gives, by name (see situation()). */
    private array $data;

    /**
     * The page.
     *
     * @param array $data
     */
    private function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * One situation.
     *
     * @param \stdClass $situation The row, with tags.
     * @param int $candidateid The vaeiste.
     * @param \context_user $context The vaeiste's context.
     * @param string|null $error The message of a send that failed, or null.
     * @param bool $conflict Whether a version was just refused for a conflict.
     * @param \moodle_url $url The address of the page.
     * @param string|null $hiddenmessage The message of the record when it is hidden (already escaped by
     *        hidden_frameworks::message()), or null.
     * @param bool $canwrite Whether the reader may write the situations of the vaeiste (situation_manager::can_write()).
     * @param bool $open Whether the preparation is open.
     * @param string[] $kept What the vaeiste was sending, by field (situation_screen::kept_text()).
     * @param bool $editing Whether the vaeiste writes the situation now (the forms are drawn).
     * @param \moodleform|null $detailsform The form of the details, when editing.
     * @param \moodleform|null $textform The form of the text, when editing.
     * @param \stdClass|null $latest The latest version, or null.
     * @param \stdClass $draft The draft that holds the text.
     * @param \moodle_url $history The history of the text.
     * @param situation_pieces $pieces The pieces.
     * @param bool $mayact Whether the vaeiste may act on the situation (not hidden).
     * @param situation_piece_forms|null $pieceforms The forms to add a piece, while adding is open; null otherwise.
     * @param string|null $noteerror The message of a send of an annotation that failed, or null.
     * @param \stdClass[] $noterows The annotations of the situation (note_manager::for_target()).
     * @param bool $canannotate Whether the reader may annotate (note_manager::can_write()).
     * @param \moodleform|null $noteform The form of an annotation, for whoever may annotate.
     * @param \moodle_url $listurl The list of the situations of the record.
     * @return self
     */
    public static function situation(
        \stdClass $situation,
        int $candidateid,
        \context_user $context,
        ?string $error,
        bool $conflict,
        \moodle_url $url,
        ?string $hiddenmessage,
        bool $canwrite,
        bool $open,
        array $kept,
        bool $editing,
        ?\moodleform $detailsform,
        ?\moodleform $textform,
        ?\stdClass $latest,
        \stdClass $draft,
        \moodle_url $history,
        situation_pieces $pieces,
        bool $mayact,
        ?situation_piece_forms $pieceforms,
        ?string $noteerror,
        array $noterows,
        bool $canannotate,
        ?\moodleform $noteform,
        \moodle_url $listurl
    ): self {
        return new self(get_defined_vars());
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/situation_page';
    }

    /**
     * The context of the template. The parts are drawn in the order of the page, so that its forms are drawn in the order
     * they were (their scripts follow).
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $d = $this->data;
        $situation = $d['situation'];
        $context = [
            'code' => (string) $situation->fichecode,
            'disclaimerhtml' => $output->render(new situation_disclaimer($d['candidateid'])),
            // The message of an exception, escaped before the notification of the core, as before.
            'errorhtml' => $d['error'] !== null ? $output->notification(s($d['error']), notification::NOTIFY_ERROR) : '',
            'conflict' => $d['conflict'] ? [
                'noticehtml' => $output->notification(get_string('errordraftconflict', 'tool_vae'), notification::NOTIFY_ERROR),
                'kept' => get_string('draftconflicttextkept', 'tool_vae'),
                'reload' => ['url' => $d['url']->out(false), 'text' => get_string('draftconflictreload', 'tool_vae')],
            ] : null,
            'refused' => $this->refused(),
            'editing' => null,
            'reading' => null,
        ];
        if ($d['editing']) {
            $draft = $d['draft'];
            $context['editing'] = [
                'detailsformhtml' => $d['detailsform']->render(),
                'heading' => get_string('situationtext', 'tool_vae'),
                'status' => $d['latest'] !== null
                    ? get_string('sectioncurrentversion', 'tool_vae', (int) $d['latest']->number)
                    : get_string('sectionnoversion', 'tool_vae'),
                'textformhtml' => $d['textform']->render(),
                // A live region, not a toast: a screen reader announces it (section.php).
                'autosaved' => $draft->workingat && !$d['conflict']
                    ? get_string('draftautosaved', 'tool_vae', userdate((int) $draft->workingat)) : '',
            ];
        } else {
            $context['reading'] = $this->reading($output);
        }
        $context['versions'] = ['url' => $d['history']->out(false), 'text' => get_string('situationversions', 'tool_vae')];
        $context['pieceshtml'] = $output->render($d['pieces']);
        $context['pieceformshtml'] = $d['pieceforms'] !== null ? $output->render($d['pieceforms']) : '';
        $context['piececlosedhtml'] = $d['pieceforms'] === null && $d['mayact']
            ? $output->notification(get_string('piececlosed', 'tool_vae'), notification::NOTIFY_INFO) : '';
        $context['noteerrorhtml'] = $d['noteerror'] !== null
            ? $output->notification(s($d['noteerror']), notification::NOTIFY_ERROR) : '';
        $context['noteshtml'] = $output->render(new notes_block(
            $d['candidateid'],
            note_manager::TARGET_SITUATION,
            (string) $situation->fichecode,
            (int) $situation->slot,
            '',
            $d['noterows'],
            $d['canannotate'],
            $d['noteform'] ? $d['noteform']->render() : ''
        ));
        $context['deletehtml'] = $d['editing']
            ? $output->single_button(
                new \moodle_url($d['url'], ['action' => 'delete']),
                get_string('situationdelete', 'tool_vae'),
                'post'
            )
            : '';
        $context['back'] = ['url' => $d['listurl']->out(false), 'text' => get_string('situationbacklist', 'tool_vae')];

        return $context;
    }

    /**
     * A situation that may not be written now - the record hidden (said to whoever reads), the preparation closed (said to
     * the vaeiste) -: the message, and what the vaeiste was sending, given back, never lost. Was situation_screen::refused()
     * and closed().
     *
     * @return array|null colour, messagehtml and kept (object: intro, fields) or null; null when the situation may be written.
     */
    private function refused(): ?array {
        $d = $this->data;
        if ($d['hiddenmessage'] !== null) {
            // Already escaped by hidden_frameworks::message().
            $message = $d['hiddenmessage'];
            $kept = $d['canwrite'] ? $d['kept'] : [];
        } else if ($d['canwrite'] && !$d['open']) {
            // A sentence of the language file, escaped here as before: the key holds HTML.
            $message = s(get_string('errorvalidationclosed', 'tool_vae'));
            $kept = $d['kept'];
        } else {
            return null;
        }
        $labels = ['title' => 'situationtitle', 'setting' => 'situationsetting', 'content' => 'situationtext'];
        $fields = [];
        foreach ($kept as $field => $text) {
            $fields[] = [
                'rows' => $field === 'content' ? 8 : 1,
                'label' => get_string($labels[$field], 'tool_vae'),
                'text' => $text,
            ];
        }

        return [
            'colour' => $kept ? 'danger' : 'info',
            'messagehtml' => $message,
            'kept' => $kept ? ['intro' => get_string('selfposhiddenkept', 'tool_vae'), 'fields' => $fields] : null,
        ];
    }

    /**
     * The reading of a situation: its CURRENT title, period, setting and tags (D-1b-I); the latest version saved, then,
     * apart, the draft in progress said to be one (D-1b-H). Was situation_screen::details() and text().
     *
     * @param renderer_base $output
     * @return array
     */
    private function reading(renderer_base $output): array {
        $d = $this->data;
        $situation = $d['situation'];
        $latest = $d['latest'];
        $draft = $d['draft'];
        $options = ['context' => $d['context'], 'filter' => false];

        return [
            'detailshtml' => $output->render(new situation_facts(
                $situation,
                selfpos_tree::for_record((string) $situation->fichecode),
                [],
                'tool-vae-situation-details mb-3'
            )),
            'noversion' => $latest === null ? ['text' => get_string('situationnoversion', 'tool_vae')] : null,
            'latest' => $latest !== null ? [
                'status' => get_string('sectioncurrentversion', 'tool_vae', (int) $latest->number),
                // The text, as plain text through format_text(), as before.
                'texthtml' => format_text((string) $latest->content, FORMAT_PLAIN, $options),
            ] : null,
            'working' => $draft->working !== null && trim((string) $draft->working) !== '' ? [
                'notice' => get_string('situationworkingnotice', 'tool_vae'),
                'texthtml' => format_text((string) $draft->working, FORMAT_PLAIN, $options),
            ] : null,
        ];
    }
}
