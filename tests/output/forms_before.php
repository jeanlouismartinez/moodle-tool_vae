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

/**
 * The HTML the forms, the notifier, the exporter, the importer of a dossier and the comparison of two versions wrote before
 * lot 7 took it into templates (MMR-520 ticket 4, wave 4c).
 *
 * Kept here as the expected value of the tests that prove the new pieces draw the same DOM. Copied by script from the files
 * as they were; the only changes: the wrapper an expression stood in ($mform->addElement(), the field of the message) is
 * dropped and the expression returned; a whole method is copied with its body, protected and named with the prefix
 * "before_"; a constant of a form or a variable of the code around (the id of a reminder, the token, the id of the
 * account, the body of the message) is given as a parameter; the classes the files imported are named in full; the two
 * private constants of the importer (10 competencies listed, labels of 100 characters) are written as figures; a long line
 * is folded; the calls between copied
 * methods call the copies. Nothing else is touched.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait forms_before {
    /**
     * Was classes/form/attest_form.php:172-176 (ret).
     *
     * @param string $id
     * @return string HTML.
     */
    protected static function before_attest_form_reminder(string $id): string {
        return \html_writer::div(
            get_string('anonymisereminder', 'tool_vae'),
            'alert alert-info tool-vae-anonymise',
            ['id' => $id]
        );
    }

    /**
     * Was classes/form/certification_form.php:72-72 (add).
     *
     * @return string HTML.
     */
    protected static function before_certification_form_l72(): string {
        return \html_writer::div(s(get_string('anonymiseshort', 'tool_vae')), 'small text-muted mb-2');
    }

    /**
     * Was classes/form/certification_form.php:78-78 (add).
     *
     * @return string HTML.
     */
    protected static function before_certification_form_l78(): string {
        return \html_writer::div(s(get_string('anonymiseshort', 'tool_vae')), 'small text-muted mb-2');
    }

    /**
     * Was classes/form/declare_form.php:276-280 (ret).
     *
     * @param string $id
     * @return string HTML.
     */
    protected static function before_declare_form_full_reminder(string $id): string {
        return \html_writer::div(
            get_string('anonymisereminder', 'tool_vae'),
            'alert alert-info tool-vae-anonymise',
            ['id' => $id]
        );
    }

    /**
     * Was classes/form/declare_form.php:293-297 (ret).
     *
     * @param string $id
     * @return string HTML.
     */
    protected static function before_declare_form_reminder(string $id): string {
        return \html_writer::div(
            get_string('anonymiseshort', 'tool_vae'),
            'alert alert-info tool-vae-anonymise tool-vae-anonymise-short',
            ['id' => $id]
        );
    }

    /**
     * Was classes/form/evaluation_form.php:47-47 (add).
     *
     * @return string HTML.
     */
    protected static function before_evaluation_form_l47(): string {
        return \html_writer::div(s(get_string('anonymiseshort', 'tool_vae')), 'small text-muted mb-2');
    }

    /**
     * Was classes/form/fiche_confirm_form.php:73-76 (add).
     *
     * @param string $token
     * @return string HTML.
     */
    protected static function before_fiche_confirm_form_l73(string $token): string {
        return \html_writer::tag('p', \html_writer::link(
            new \moodle_url('/admin/tool/vae/import_fiches.php', ['redo' => $token]),
            get_string('fcimportedit', 'tool_vae')
        ));
    }

    /**
     * Was classes/form/fiche_import_form.php:59-62 (add).
     *
     * @return string HTML.
     */
    protected static function before_fiche_import_form_l59(): string {
        return \html_writer::empty_tag('input', [
            'type' => 'submit', 'name' => 'defaultbutton', 'value' => '', 'class' => 'd-none',
            'tabindex' => '-1', 'aria-hidden' => 'true',
        ]);
    }

    /**
     * Was classes/form/fiche_import_form.php:75-79 (static).
     *
     * @return string HTML.
     */
    protected static function before_fiche_import_form_l75(): string {
        return get_string(
            'fcimportziptext',
            'tool_vae',
            \html_writer::link(\tool_vae\local\fiche_import::SOURCE_URL, 'data.gouv.fr')
        );
    }

    /**
     * Was classes/form/fields_form.php:62-65 (expr).
     *
     * @param int $userid
     * @return string HTML.
     */
    protected static function before_fields_form_l62(int $userid): string {
        return \html_writer::link(
            new \moodle_url('/user/edit.php', ['id' => $userid]),
            get_string('faisabeditprofile', 'tool_vae')
        );
    }

    /**
     * Was classes/form/note_form.php:46-50 (add).
     *
     * @param string $id
     * @return string HTML.
     */
    protected static function before_note_form_l46(string $id): string {
        return \html_writer::div(
            s(get_string('anonymiseshort', 'tool_vae')),
            'small text-muted mb-2 tool-vae-anonymise',
            ['id' => $id]
        );
    }

    /**
     * Was classes/form/piece_file_form.php:63-67 (add).
     *
     * @param string $id
     * @return string HTML.
     */
    protected static function before_piece_file_form_l63(string $id): string {
        return \html_writer::div(
            get_string('anonymisepiece', 'tool_vae'),
            'alert alert-info tool-vae-anonymise',
            ['id' => $id]
        );
    }

    /**
     * Was classes/form/piece_link_form.php:50-54 (add).
     *
     * @param string $id
     * @return string HTML.
     */
    protected static function before_piece_link_form_l50(string $id): string {
        return \html_writer::div(
            get_string('anonymisepiece', 'tool_vae'),
            'alert alert-info tool-vae-anonymise',
            ['id' => $id]
        );
    }

    /**
     * Was classes/form/precision_form.php:50-54 (add).
     *
     * @param string $id
     * @return string HTML.
     */
    protected static function before_precision_form_l50(string $id): string {
        return \html_writer::div(
            get_string('anonymisereminder', 'tool_vae'),
            'alert alert-info tool-vae-anonymise',
            ['id' => $id]
        );
    }

    /**
     * Was classes/form/remove_record_form.php:49-49 (add).
     *
     * @return string HTML.
     */
    protected static function before_remove_record_form_l49(): string {
        return \html_writer::div(s(get_string('removerecordintro', 'tool_vae')), 'alert alert-warning');
    }

    /**
     * Was classes/form/remove_record_form.php:64-107, copied whole.
     *
     * @param \stdClass $inventory
     * @return string
     */
    protected static function before_remove_record_form_leaves(\stdClass $inventory): string {
        $source = $inventory->source;
        $none = get_string('removerecordnone', 'tool_vae');
        $nodate = get_string('removerecordnodate', 'tool_vae');
        $dateformat = get_string('strftimedate', 'langconfig');
        $date = fn($time): string => empty($time) ? $nodate : \tool_vae\local\display_date::format((int) $time, $dateformat);

        $addresses = '';
        foreach ($inventory->refs as $ref) {
            $addresses .= \html_writer::tag('li', s($ref->title) . ' (' . s($ref->url) . ')');
        }
        $refs = s(get_string('removerecordrefs', 'tool_vae', count($inventory->refs)))
            . ($addresses === '' ? '' : \html_writer::tag('ul', $addresses));

        $items = [
            s(get_string('removerecordlicence', 'tool_vae', (object) [
                'licence' => $source->licence,
                'attribution' => $source->attribution,
            ])),
            s(get_string('removerecorddates', 'tool_vae', (object) [
                'updated' => $date($source->sourceupdated),
                'lastimport' => $date($source->lastimport),
            ])),
            $refs,
            s(get_string('removerecordreferents', 'tool_vae', (int) $inventory->referents)),
            s(get_string('removerecordpassages', 'tool_vae', count($inventory->passageids))),
            s(get_string('removerecordmarks', 'tool_vae', (int) $inventory->marks)),
            s(get_string(
                'removerecordsuccessor',
                'tool_vae',
                $inventory->successor === '' ? get_string('supersessionnosuccessor', 'tool_vae') : $inventory->successor
            )),
            s(get_string(
                'removerecordreplaced',
                'tool_vae',
                $inventory->replaced ? implode(', ', $inventory->replaced) : $none
            )),
        ];

        return \html_writer::tag('p', s(get_string('removerecordleaves', 'tool_vae')), ['class' => 'mb-1'])
            . \html_writer::tag('ul', implode('', array_map(fn($item) => \html_writer::tag('li', $item), $items)), [
                'class' => 'tool-vae-removal',
            ]);
    }

    /**
     * Was classes/form/scope_form.php:50-50 (add).
     *
     * @return string HTML.
     */
    protected static function before_scope_form_l50(): string {
        return \html_writer::div(s(get_string('anonymiseshort', 'tool_vae')), 'small text-muted mb-2');
    }

    /**
     * Was classes/form/section_form.php:52-56 (add).
     *
     * @param string $id
     * @return string HTML.
     */
    protected static function before_section_form_l52(string $id): string {
        return \html_writer::div(
            get_string('anonymisereminder', 'tool_vae'),
            'alert alert-info tool-vae-anonymise',
            ['id' => $id]
        );
    }

    /**
     * Was classes/form/selfpos_form.php:52-52 (add).
     *
     * @return string HTML.
     */
    protected static function before_selfpos_form_l52(): string {
        return \html_writer::div(s(get_string('anonymiseshort', 'tool_vae')), 'small text-muted mb-2');
    }

    /**
     * Was classes/form/situation_details_form.php:50-54 (add).
     *
     * @param string $id
     * @return string HTML.
     */
    protected static function before_situation_details_form_l50(string $id): string {
        return \html_writer::div(
            s(get_string('anonymiseshort', 'tool_vae')),
            'small text-muted mb-2',
            ['id' => $id]
        );
    }

    /**
     * Was classes/form/situation_details_form.php:69-69 (add).
     *
     * @return string HTML.
     */
    protected static function before_situation_details_form_l69(): string {
        return \html_writer::tag('h3', s(get_string('situationtags', 'tool_vae')), ['class' => 'h5 mt-3']);
    }

    /**
     * Was classes/form/situation_form.php:50-54 (add).
     *
     * @param string $id
     * @return string HTML.
     */
    protected static function before_situation_form_l50(string $id): string {
        return \html_writer::div(
            get_string('anonymisereminder', 'tool_vae'),
            'alert alert-info tool-vae-anonymise',
            ['id' => $id]
        );
    }

    /**
     * Was classes/form/situation_form.php:56-60 (add).
     *
     * @return string HTML.
     */
    protected static function before_situation_form_l56(): string {
        return \html_writer::tag(
            'p',
            s(get_string('situationmemo', 'tool_vae')),
            ['class' => 'tool-vae-section-help', 'id' => 'tool_vae_situationmemo']
        );
    }

    /**
     * Was classes/form/withdraw_form.php:60-63 (add).
     *
     * @return string HTML.
     */
    protected static function before_withdraw_form_l60(): string {
        return \html_writer::div(
            get_string('withdrawwarning', 'tool_vae'),
            'alert alert-warning'
        );
    }

    /**
     * Was classes/notifier.php:1614-1615 (assign).
     *
     * @param string $body
     * @param \moodle_url $url
     * @param string $linkname
     * @return string HTML.
     */
    protected static function before_notifier_send_html(string $body, \moodle_url $url, string $linkname): string {
        return \html_writer::tag('p', s($body))
            . \html_writer::tag('p', \html_writer::link($url, s($linkname)));
    }

    /**
     * Was classes/local/exporter.php:218-220, copied whole.
     *
     * @param array $warnings
     * @param string $lead
     * @return string
     */
    protected static function before_exporter_warnings_html(array $warnings, string $lead): string {
        return \html_writer::tag('p', s($lead))
            . \html_writer::alist(array_map('s', \tool_vae\local\exporter::describe_warnings($warnings)));
    }

    /**
     * Was classes/local/dossier_importer.php:494-514, copied whole.
     *
     * @param array $held
     * @return \stdClass
     */
    protected static function before_dossier_importer_describe_held(array $held): \stdClass {
        usort($held, fn($first, $second) => strnatcmp($first->name, $second->name));

        $items = [];
        foreach (array_slice($held, 0, 10) as $competency) {
            $label = self::before_dossier_importer_tidy_label($competency->label);
            if ($label === '') {
                $items[] = s($competency->name);
                continue;
            }
            $items[] = get_string('errorarchivecompetencyheldone', 'tool_vae', (object) [
                'name' => s($competency->name),
                'label' => s($label),
            ]);
        }
        if (count($held) > 10) {
            $items[] = get_string('errorarchivecompetencyheldmore', 'tool_vae', count($held) - 10);
        }

        return (object) ['count' => count($held), 'list' => \html_writer::alist($items)];
    }

    /**
     * Was classes/local/dossier_importer.php:522-529, copied whole.
     *
     * @param string $label
     * @return string
     */
    protected static function before_dossier_importer_tidy_label(string $label): string {
        $label = trim((string) preg_replace('/\s+/u', ' ', $label));
        if (\core_text::strlen($label) > 100) {
            $label = \core_text::substr($label, 0, 100 - 3) . '...';
        }

        return $label;
    }

    /**
     * Was classes/local/text_diff.php:123-140, copied whole.
     *
     * @param array $ops
     * @return string
     */
    protected static function before_text_diff_html(array $ops): string {
        $html = '';
        foreach ($ops as $op) {
            $text = s($op['text']);
            if ($op['op'] === \tool_vae\local\text_diff::EQUAL) {
                $html .= $text;
            } else if ($op['op'] === \tool_vae\local\text_diff::INSERT) {
                $html .= '<ins class="tool-vae-ins"><span class="sr-only">'
                    . s(get_string('diffadded', 'tool_vae')) . ' </span>' . $text . '</ins>';
            } else {
                // The space after the words of the screen reader is the code's, not the string's.
                $html .= '<del class="tool-vae-del"><span class="sr-only">'
                    . s(get_string('diffremoved', 'tool_vae')) . ' </span>' . $text . '</del>';
            }
        }

        return $html;
    }
}
