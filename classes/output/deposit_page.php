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
use tool_vae\local\deposit_manager;
use tool_vae\local\feasibility_catalog;
use tool_vae\local\feasibility_fields;
use tool_vae\local\validation_catalog;

/**
 * The page of the deposits of a dossier (deposit.php), below its heading (T-21, lot 2; lot 7, MMR-520 ticket 4).
 *
 * Three states, which exclude one another: one deposit of the preparation of the validation dossier, as it was fixed (read,
 * never evaluated); one deposit of the dossier de faisabilité, as it was fixed, with the opinions written on it and the
 * form of whoever may add one; the list of the deposits, with the form of the vaeiste who may deposit. The page checks the
 * rights, reads the forms, deposits and redirects before, and reads the deposit, its opinions and the list; this class only
 * words and places them. The texts deposited are shown the way the page showed them: format_text() of the core, plain text,
 * no filter. The titles of the sections go to a heading of the core, which does not escape: they are escaped here, as before.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deposit_page implements named_templatable, renderable {
    /** @var array What the state holds, by name. */
    private array $state;

    /**
     * Use validation(), deposit() or listing().
     *
     * @param array $state
     */
    private function __construct(array $state) {
        $this->state = $state + [
            'deposit' => null, 'validation' => false, 'withfields' => true, 'opinions' => null, 'opinionform' => null,
            'deposits' => null, 'form' => null, 'own' => false,
        ];
    }

    /**
     * One deposit of the preparation of the validation dossier, as it was fixed.
     *
     * @param \stdClass $deposit The row of the deposit.
     * @param \moodle_url $url The list of the deposits.
     * @param int $candidateid The vaeiste whose deposit it is.
     * @return self
     */
    public static function validation(\stdClass $deposit, \moodle_url $url, int $candidateid): self {
        return new self(['deposit' => $deposit, 'validation' => true, 'url' => $url, 'candidateid' => $candidateid]);
    }

    /**
     * One deposit of the dossier de faisabilité, as it was fixed, its opinions and the form of whoever may add one.
     *
     * @param \stdClass $deposit The row of the deposit.
     * @param bool $withfields Whether the reader may read the separate fields (identity, status).
     * @param \stdClass[] $opinions The opinions written on the deposit, as opinion_manager::for_deposit() gives them.
     * @param \moodleform|null $opinionform The form of an opinion, already read by the page; null for none.
     * @param \moodle_url $url The list of the deposits.
     * @param int $candidateid The vaeiste whose deposit it is.
     * @return self
     */
    public static function deposit(
        \stdClass $deposit,
        bool $withfields,
        array $opinions,
        ?\moodleform $opinionform,
        \moodle_url $url,
        int $candidateid
    ): self {
        return new self([
            'deposit' => $deposit, 'withfields' => $withfields, 'opinions' => $opinions, 'opinionform' => $opinionform,
            'url' => $url, 'candidateid' => $candidateid,
        ]);
    }

    /**
     * The list of the deposits, and the form of the vaeiste who may deposit.
     *
     * @param bool $validation Whether these are the deposits of the preparation of the validation dossier.
     * @param \moodleform|null $form The form of a deposit, already read by the page; null for none.
     * @param \stdClass[] $deposits The rows of the deposits, the latest first.
     * @param \moodle_url $url This page.
     * @param bool $own Whether the reader is the vaeiste.
     * @param int $candidateid The vaeiste whose deposits they are.
     * @return self
     */
    public static function listing(
        bool $validation,
        ?\moodleform $form,
        array $deposits,
        \moodle_url $url,
        bool $own,
        int $candidateid
    ): self {
        return new self([
            'validation' => $validation, 'form' => $form, 'deposits' => $deposits, 'url' => $url, 'own' => $own,
            'candidateid' => $candidateid,
        ]);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/deposit_page';
    }

    /**
     * The context of the template: the texts in clear; the headings, the texts deposited, the table and the forms as HTML.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $state = $this->state;
        $context = [
            'note' => false, 'digest' => false, 'labelling' => false, 'engagement' => false, 'intro' => false,
            'formhtml' => false, 'sections' => [], 'omittedhtml' => false, 'opinions' => false, 'none' => false,
            'tablehtml' => false, 'back' => false,
        ];
        // The sentence is not frozen in the deposit: it is shown, always, to whoever reads (MC-15).
        $labelling = $state['validation'] ? ['text' => get_string('validationlabelling', 'tool_vae')] : false;

        if ($state['deposit'] === null) {
            return $this->list_context($output, $labelling) + $context;
        }

        $deposit = $state['deposit'];
        if ($deposit->note !== null && $deposit->note !== '') {
            $context['note'] = ['label' => (string) $deposit->note];
        }
        $context['digest'] = ['text' => get_string('depositdigest', 'tool_vae', $deposit->contenthash)];
        $context['back'] = ['url' => $state['url']->out(false), 'label' => get_string('depositbacklist', 'tool_vae')];
        $usercontext = \context_user::instance($state['candidateid']);

        if ($state['validation']) {
            $context['labelling'] = $labelling;
            $fiche = null;
            foreach (deposit_manager::validation_sections($deposit) as $section) {
                $record = false;
                if ($section['fiche'] !== $fiche) {
                    $fiche = $section['fiche'];
                    $record = $output->heading(s(get_string('validationdepositrecord', 'tool_vae', $fiche)), 3);
                }
                $context['sections'][] = [
                    'recordhtml' => $record,
                    'headinghtml' => $output->heading(s(validation_catalog::label($fiche, (string) $section['code'])), 4),
                    'version' => get_string('depositsectionversion', 'tool_vae', (int) $section['version']),
                    'texthtml' => format_text((string) $section['texte'], FORMAT_PLAIN, [
                        'context' => $usercontext,
                        'filter' => false,
                    ]),
                ];
            }

            return $context;
        }

        if (deposit_manager::engagement_confirmed($deposit)) {
            $context['engagement'] = ['text' => get_string('depositengagementdone', 'tool_vae')];
        }
        foreach (deposit_manager::sections($deposit, $state['withfields']) as $section) {
            $text = $section['kind'] === feasibility_catalog::KIND_FIELDS
                ? feasibility_fields::readable($section['code'], $section['content'])
                : $section['content'];
            $context['sections'][] = [
                'recordhtml' => false,
                'headinghtml' => $output->heading(s(feasibility_catalog::label($section['code'], $section['slot'])), 3),
                'version' => get_string('depositsectionversion', 'tool_vae', $section['version']),
                'texthtml' => format_text($text, FORMAT_PLAIN, ['context' => $usercontext, 'filter' => false]),
            ];
        }
        if (!$state['withfields']) {
            $context['omittedhtml'] = $output->notification(
                get_string('documentidentityomitted', 'tool_vae'),
                notification::NOTIFY_INFO
            );
        }

        // The opinions written on this deposit, then the form of whoever may add one.
        $items = [];
        foreach ($state['opinions'] as $opinion) {
            $author = $opinion->authorname !== '' ? $opinion->authorname : get_string('opinionformerauthor', 'tool_vae');
            $items[] = [
                'by' => $author . ' - ' . userdate($opinion->timecreated, get_string('strftimedatetime', 'langconfig')),
                'texthtml' => format_text($opinion->content, FORMAT_PLAIN, ['context' => $usercontext, 'filter' => false]),
            ];
        }
        $context['opinions'] = [
            'headinghtml' => $output->heading(get_string('opinionheading', 'tool_vae'), 3),
            'none' => $state['opinions'] ? false : ['text' => get_string('opinionnone', 'tool_vae')],
            'items' => $items,
            'intro' => $state['opinionform'] === null ? false : ['text' => get_string('opinionintro', 'tool_vae')],
            // A form of the core is drawn once per page: its identifiers must be unique.
            'opinionformhtml' => $state['opinionform'] === null ? false : $state['opinionform']->render(),
        ];

        return $context;
    }

    /**
     * The list of the deposits: the sentence of the labelling, the form, the table, the way back.
     *
     * @param renderer_base $output
     * @param array|false $labelling
     * @return array
     */
    private function list_context(renderer_base $output, array|false $labelling): array {
        $state = $this->state;
        $validation = $state['validation'];
        $own = $state['own'];

        $context = ['labelling' => $labelling];
        if ($state['form'] !== null) {
            $context['intro'] = ['text' => get_string($validation ? 'validationdepositintro' : 'depositintro', 'tool_vae')];
            // A form of the core is drawn once per page: its identifiers must be unique.
            $context['formhtml'] = $state['form']->render();
        }

        if (!$state['deposits']) {
            $context['none'] = ['text' => get_string('depositnone', 'tool_vae')];
        } else {
            $table = new \html_table();
            $table->attributes['class'] = 'generaltable tool-vae-deposits';
            $table->head = [
                get_string('depositcolnumber', 'tool_vae'),
                get_string('depositcoldate', 'tool_vae'),
                get_string('depositcolnote', 'tool_vae'),
                get_string('depositcoldigest', 'tool_vae'),
                get_string('faisabcolactions', 'tool_vae'),
            ];
            foreach ($state['deposits'] as $deposit) {
                $table->data[] = [
                    (int) $deposit->number,
                    new cell_content([cell_content::text(
                        userdate($deposit->timecreated, get_string('strftimedatetime', 'langconfig'))
                    )]),
                    new cell_content([cell_content::text((string) $deposit->note)]),
                    new deposit_digest(substr($deposit->contenthash, 0, 12)),
                    new cell_content([cell_content::link(
                        new \moodle_url($state['url'], ['number' => (int) $deposit->number]),
                        get_string('depositread', 'tool_vae', (int) $deposit->number)
                    )]),
                ];
            }
            $context['tablehtml'] = $output->render(new table($table));
        }

        if ($validation && $own) {
            $back = [
                'url' => (new \moodle_url('/admin/tool/vae/validation.php'))->out(false),
                'label' => get_string('validationtitle', 'tool_vae'),
            ];
        } else {
            $back = [
                'url' => ($own
                    ? new \moodle_url('/admin/tool/vae/faisabilite.php')
                    : new \moodle_url('/admin/tool/vae/dossier.php', ['userid' => $state['candidateid']]))->out(false),
                'label' => get_string($own ? 'faisabheading' : 'faisabbacktodossier', 'tool_vae'),
            ];
        }
        $context['back'] = $back;

        return $context;
    }
}
