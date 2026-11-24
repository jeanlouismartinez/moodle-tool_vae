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

use tool_vae\local\ai_assistant;
use tool_vae\local\display_date;
use tool_vae\local\fiche_import;
use tool_vae\local\fiche_messages;
use tool_vae\local\fiche_refusal;

/**
 * The HTML the screens fiche_screen, ai_screens and next_actions_screen wrote before lot 7 (MMR-520 ticket 4, wave 2b).
 *
 * Their code as it was in classes/local/ before its migration to pieces of classes/output/, kept here as the expected value of
 * the tests that prove each piece draws the same DOM. The only changes: the renderer of the core is self::core() (the one of
 * the web, given by the trait dom_canon) where the old code read $OUTPUT, and the private methods of a screen are called by
 * their name here (before_fiche_..., before_ai_...). Nothing else is touched: the escaping, the order, the classes and the
 * spaces are those of the old code.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait fiche_ai_before {
    /**
     * fiche_screen::preview() before.
     *
     * @param \stdClass $plan
     * @param int|null $now
     * @return string
     */
    protected static function before_fiche_preview(\stdClass $plan, ?int $now = null): string {
        $now ??= \core\di::get(\core\clock::class)->time();
        $out = '';

        $out .= \html_writer::tag('p', self::before_fiche_source($plan));
        $out .= \html_writer::tag('p', get_string('fcimportexportdate', 'tool_vae', self::before_fiche_day((int) $plan->exportts))
            . ' ' . get_string(
                'fcimportdatenote',
                'tool_vae',
                self::before_fiche_day(fiche_import::to_timestamp($plan->innerdates->blocs))
            ));
        foreach (self::before_fiche_warnings($plan, $now) as $warning) {
            $out .= \html_writer::div($warning, 'alert alert-warning');
        }

        $out .= \html_writer::div(get_string('fcimportcheck', 'tool_vae'), 'alert alert-info');

        $ready = 0;
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-fcimport';
        $table->caption = get_string('fcimportcaption', 'tool_vae');
        $table->head = [
            get_string('fcimportcolfiche', 'tool_vae'),
            get_string('fcimportcolexport', 'tool_vae'),
            get_string('fcimportcolmoodle', 'tool_vae'),
            get_string('fcimportcolaction', 'tool_vae'),
        ];
        $anyheld = false;
        foreach ($plan->records as $record) {
            $table->data[] = self::before_fiche_line($record, $now, (int) $plan->exportts);
            $ready += $record->status === 'ready' ? 1 : 0;
            $anyheld = $anyheld || ($record->status === 'ready' && $record->state !== 'new');
        }
        $out .= \html_writer::table($table);

        if (!$ready) {
            $out .= \html_writer::div(get_string('fcimportnone', 'tool_vae'), 'alert alert-danger');
        }

        $out .= \html_writer::tag('p', get_string('fcimportprovenance', 'tool_vae', (object) [
            'source' => \html_writer::link(fiche_import::SOURCE_URL, 'data.gouv.fr'),
            'attribution' => s(fiche_import::ATTRIBUTION),
            'licence' => s(fiche_import::LICENCE),
            'date' => self::before_fiche_day((int) $plan->exportts),
        ]), ['class' => 'small']);
        if ($anyheld) {
            $out .= \html_writer::tag('p', get_string('fcimportgonenote', 'tool_vae'), ['class' => 'small']);
        }
        $out .= \html_writer::tag('p', get_string('fcimportvisible', 'tool_vae'), ['class' => 'small']);

        return $out;
    }

    /**
     * fiche_screen::warnings() before.
     *
     * @param \stdClass $plan
     * @param int|null $now
     * @return string[]
     */
    protected static function before_fiche_warnings(\stdClass $plan, ?int $now = null): array {
        $now ??= \core\di::get(\core\clock::class)->time();
        $warnings = [];

        $age = intdiv($now - (int) $plan->exportts, DAYSECS);
        if ($age >= fiche_import::STALE_DAYS) {
            $warnings[] = get_string(
                $plan->origin === fiche_import::ORIGIN_FETCH ? 'fcimportwarnstalefetch' : 'fcimportwarnstale',
                'tool_vae',
                $age
            );
        }

        $first = fiche_import::to_timestamp($plan->innerdates->blocs);
        $second = fiche_import::to_timestamp($plan->innerdates->standard);
        if (abs((int) $plan->exportts - $first) > fiche_import::GAP_DAYS * DAYSECS) {
            $warnings[] = get_string('fcimportwarngap', 'tool_vae', (object) [
                'export' => self::before_fiche_day((int) $plan->exportts),
                'inner' => self::before_fiche_day($first),
            ]);
        }
        if ($first !== $second) {
            $warnings[] = get_string('fcimportwarndates', 'tool_vae', (object) [
                'first' => self::before_fiche_day($first),
                'second' => self::before_fiche_day($second),
            ]);
        }
        foreach ($plan->repeated as $number) {
            $warnings[] = get_string('fcimportwarnrepeated', 'tool_vae', s($number));
        }

        return $warnings;
    }

    /**
     * fiche_screen::results() before.
     *
     * @param \stdClass $plan
     * @return string
     */
    protected static function before_fiche_results(\stdClass $plan): string {
        $items = '';
        $imported = false;
        foreach ($plan->results as $result) {
            $code = s($result->number);
            switch ($result->status) {
                case 'imported':
                    $imported = true;
                    $text = get_string('fcimportresultline', 'tool_vae', (object) ['code' => $code, 'summary' => $result->html]);
                    break;
                case 'failed':
                    $text = get_string('fcimportresultfailed', 'tool_vae', (object) ['code' => $code, 'reason' => $result->html]);
                    break;
                case 'older':
                    $text = get_string('fcimportresultolder', 'tool_vae', $code);
                    break;
                case 'refused':
                    $text = get_string('fcimportresultrefused', 'tool_vae', (object) ['code' => $code, 'reason' => $result->html]);
                    break;
                default:
                    $text = get_string('fcimportresultnotrun', 'tool_vae', $code);
                    break;
            }
            $items .= \html_writer::tag('li', $text);
        }
        $out = \html_writer::tag('ul', $items, ['class' => 'tool-vae-fcimport-results']);

        if ($imported) {
            $link = \html_writer::link(
                new \moodle_url('/admin/tool/vae/supersession.php'),
                get_string('supersessionheading', 'tool_vae')
            );
            $out .= \html_writer::tag('p', get_string('fcimportnextreplace', 'tool_vae', $link));
        }

        return $out;
    }

    /**
     * fiche_screen::line() before.
     *
     * @param \stdClass $record
     * @param int $now
     * @param int $exportts
     * @return array
     */
    private static function before_fiche_line(\stdClass $record, int $now, int $exportts): array {
        $number = \html_writer::tag('strong', s($record->number));
        $title = $record->title === '' ? '' : \html_writer::div(s(trim((string) preg_replace('/\s+/u', ' ', $record->title))));

        $first = new \html_table_cell($number . $title);
        $first->header = true;
        $first->scope = 'row';

        if ($record->status !== 'ready') {
            $why = fiche_messages::html(fiche_refusal::from_array((array) $record->refusal), $exportts);

            $reason = new \html_table_cell(
                \html_writer::div($why) . \html_writer::div(get_string('fcimportrefusedfoot', 'tool_vae'), 'fw-bold')
            );
            $reason->colspan = 3;

            return [$first, $reason];
        }

        return [
            $first,
            self::before_fiche_export_cell($record, $now),
            self::before_fiche_moodle_cell($record),
            self::before_fiche_action_cell($record, $exportts),
        ];
    }

    /**
     * fiche_screen::export_cell() before.
     *
     * @param \stdClass $record
     * @param int $now
     * @return string
     */
    private static function before_fiche_export_cell(\stdClass $record, int $now): string {
        $blocs = '';
        foreach ($record->rows as $row) {
            $label = (string) $row->description !== '' ? $row->description : $row->intitule;
            $blocs .= \html_writer::tag('li', \html_writer::tag('strong', s($row->rang)) . ' ' . s($label));
        }
        $out = \html_writer::tag(
            'details',
            \html_writer::tag('summary', get_string('fcimportblocs', 'tool_vae', (int) $record->blocs))
                . \html_writer::tag('ul', $blocs)
        );

        if ($record->active !== null && $record->active !== '') {
            $state = $record->active === 'ACTIVE'
                ? get_string('fcimportstateactive', 'tool_vae')
                : ($record->active === 'INACTIVE'
                    ? get_string('fcimportstateinactive', 'tool_vae')
                    : get_string('fcimportstateother', 'tool_vae', s($record->active)));
            $out .= \html_writer::div($state, 'small');
        }
        if ($record->enddate !== null && $record->enddate !== '') {
            $end = s($record->enddate);
            $past = preg_match('~^(\d{2})/(\d{2})/(\d{4})$~', $record->enddate, $parts)
                && checkdate((int) $parts[2], (int) $parts[1], (int) $parts[3])
                && make_timestamp((int) $parts[3], (int) $parts[2], (int) $parts[1], 23, 59, 59, 99) < $now;
            $out .= \html_writer::div(
                get_string($past ? 'fcimportendregpast' : 'fcimportendreg', 'tool_vae', $end),
                'small'
            );
        }

        if ($record->long) {
            $out .= \html_writer::div(get_string('fcimportnotelong', 'tool_vae', (object) [
                'count' => count($record->long), 'ranks' => s(implode(', ', $record->long)),
            ]), 'small');
        }
        if ($record->markup) {
            $out .= \html_writer::div(get_string('fcimportnotemarkup', 'tool_vae', (object) [
                'count' => count($record->markup), 'ranks' => s(implode(', ', $record->markup)),
            ]), 'small');
        }
        if ($record->name !== $record->title && in_array($record->state, ['new', 'rebuild'], true)) {
            $out .= \html_writer::div(get_string('fcimportnotetitle', 'tool_vae'), 'small');
        }

        return $out;
    }

    /**
     * fiche_screen::moodle_cell() before.
     *
     * @param \stdClass $record
     * @return string
     */
    private static function before_fiche_moodle_cell(\stdClass $record): string {
        switch ($record->state) {
            case 'held':
                $out = \html_writer::div(
                    get_string('fcimportmoodleheld', 'tool_vae', self::before_fiche_day((int) $record->heldts))
                );
                if ((int) $record->declared > 0) {
                    $out .= \html_writer::div(get_string('fcimportdeclared', 'tool_vae', (int) $record->declared), 'small');
                }

                return $out;
            case 'rebuild':
                return \html_writer::div(get_string('fcimportmoodlerebuild', 'tool_vae'));
            case 'adopted':
                return \html_writer::div(get_string('fcimportmoodleadopt', 'tool_vae'));
            default:
                return \html_writer::div(get_string('fcimportmoodlenew', 'tool_vae'));
        }
    }

    /**
     * fiche_screen::action_cell() before.
     *
     * @param \stdClass $record
     * @param int $exportts
     * @return string
     */
    private static function before_fiche_action_cell(\stdClass $record, int $exportts): string {
        if (!empty($record->older)) {
            return \html_writer::div(get_string('fcimportolderskip', 'tool_vae'))
                . \html_writer::div(get_string('fcimportolder', 'tool_vae', (object) [
                    'held' => self::before_fiche_day((int) $record->heldts),
                    'export' => self::before_fiche_day($exportts),
                ]), 'small');
        }

        $out = \html_writer::div(get_string('fcimportready', 'tool_vae'));

        $forecast = $record->forecast;
        if ($forecast !== null) {
            if (in_array($record->state, ['new', 'rebuild'], true)) {
                $out .= \html_writer::div(get_string('fcimportdonenew', 'tool_vae', (int) $forecast->created), 'small');
            } else if (!$forecast->created && !$forecast->updated && !$forecast->gone && !$forecast->diverged) {
                $out .= \html_writer::div(get_string('fcimportdonesame', 'tool_vae'), 'small');
            } else {
                $out .= \html_writer::div(get_string('fcimportdoneheld', 'tool_vae', (object) [
                    'created' => (int) $forecast->created, 'updated' => (int) $forecast->updated,
                    'gone' => (int) $forecast->gone, 'diverged' => (int) $forecast->diverged,
                ]), 'small');
            }
        }

        return $out;
    }

    /**
     * fiche_screen::source() before.
     *
     * @param \stdClass $plan
     * @return string
     */
    private static function before_fiche_source(\stdClass $plan): string {
        return get_string(
            $plan->origin === fiche_import::ORIGIN_FETCH ? 'fcimportsourcefetch' : 'fcimportsourcezip',
            'tool_vae',
            (object) ['name' => s($plan->originname), 'size' => display_size((int) $plan->bytes)]
        );
    }

    /**
     * fiche_screen::day() before.
     *
     * @param int $timestamp
     * @return string
     */
    private static function before_fiche_day(int $timestamp): string {
        return display_date::format($timestamp, get_string('strftimedaydate', 'langconfig'));
    }

    /**
     * ai_screens::policy() before.
     *
     * @param array $params
     * @param string $text
     * @param \moodle_url $back
     * @param string $backwords
     * @return string
     */
    protected static function before_ai_policy(array $params, string $text, \moodle_url $back, string $backwords): string {
        $policy = format_text(get_string('userpolicy', 'core_ai'), FORMAT_HTML, ['context' => ai_assistant::core_context()]);

        return self::core()->heading(s(get_string('aiusagepolicy', 'core_ai')), 3)
            . \html_writer::div($policy, 'ai-policy-display')
            . self::before_ai_form($params, 'accept', $text, get_string('aipolicyaccept', 'tool_vae'), $back, $backwords);
    }

    /**
     * ai_screens::confirm() before.
     *
     * @param array $params
     * @param string $text
     * @param string $provider
     * @param \moodle_url $back
     * @param string $backwords
     * @param string[] $carried
     * @return string
     */
    protected static function before_ai_confirm(
        array $params,
        string $text,
        string $provider,
        \moodle_url $back,
        string $backwords,
        array $carried = []
    ): string {
        $output = self::core();

        $max = ai_assistant::max_chars();
        $count = \core_text::strlen($text);
        $logged = ai_assistant::moodle_erases_the_texts() ? 'aiconfirmlogged' : 'aiconfirmloggednoerase';
        $html = $output->heading(get_string('aiconfirmheading', 'tool_vae'), 3)
            . \html_writer::div(s(get_string('anonymisereminder', 'tool_vae')), 'alert alert-info tool-vae-anonymise', [
                'role' => 'note',
            ])
            . \html_writer::tag('p', s(get_string('aiconfirmwhere', 'tool_vae', $provider)))
            . \html_writer::tag('p', s(get_string('aiconfirmcount', 'tool_vae', ['count' => $count, 'max' => $max])))
            . \html_writer::tag('p', s(get_string($logged, 'tool_vae')));
        if ($text === '') {
            $html .= $output->notification(s(get_string('errorainotext', 'tool_vae')), \core\output\notification::NOTIFY_ERROR);
        } else if ($count > $max) {
            $html .= $output->notification(
                s(get_string('errorailength', 'tool_vae', $max)),
                \core\output\notification::NOTIFY_ERROR
            );
        }
        $sendable = $text !== '' && $count <= $max;

        return $html . self::before_ai_form(
            $params,
            'send',
            $text,
            $sendable ? get_string('aiconfirmsend', 'tool_vae', $provider) : '',
            $back,
            $backwords,
            $carried
        );
    }

    /**
     * ai_screens::result() before.
     *
     * @param string|null $proposal
     * @param string|null $error
     * @param string $text
     * @param \moodle_url $back
     * @param string $backwords
     * @return string
     */
    protected static function before_ai_result(
        ?string $proposal,
        ?string $error,
        string $text,
        \moodle_url $back,
        string $backwords
    ): string {
        $output = self::core();

        if ($proposal === null) {
            $html = $output->notification(s((string) $error), \core\output\notification::NOTIFY_ERROR);
        } else {
            $html = $output->heading(get_string('airesultheading', 'tool_vae'), 3)
                . \html_writer::div(s(get_string('airesultnotice', 'tool_vae')), 'alert alert-warning', ['role' => 'note'])
                . self::before_ai_text(get_string('airesultproposal', 'tool_vae'), $proposal, '', 'tool-vae-ai-proposal');
        }

        return $html
            . self::before_ai_text(get_string('aioriginal', 'tool_vae'), $text, '', 'tool-vae-ai-text')
            . \html_writer::tag('p', s(get_string('aibackhint', 'tool_vae')), ['class' => 'text-muted small'])
            . \html_writer::tag('p', \html_writer::link($back, s($backwords), ['class' => 'btn btn-secondary']));
    }

    /**
     * ai_screens::hidden() before.
     *
     * @param array $params
     * @param string $step
     * @return string
     */
    private static function before_ai_hidden(array $params, string $step): string {
        $html = \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        foreach ($params + ['step' => $step] as $name => $value) {
            $html .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => (string) $value]);
        }

        return $html;
    }

    /**
     * ai_screens::text() before.
     *
     * @param string $label
     * @param string $text
     * @param string $name
     * @param string $id
     * @return string
     */
    private static function before_ai_text(string $label, string $text, string $name, string $id): string {
        $attributes = ['id' => $id, 'readonly' => 'readonly', 'rows' => 12, 'class' => 'form-control mb-2'];
        if ($name !== '') {
            $attributes['name'] = $name;
        }

        return \html_writer::tag('label', s($label), ['for' => $id, 'class' => 'font-weight-bold'])
            . \html_writer::tag('textarea', s($text), $attributes);
    }

    /**
     * ai_screens::form() before.
     *
     * @param array $params
     * @param string $step
     * @param string $text
     * @param string $button
     * @param \moodle_url $back
     * @param string $backwords
     * @param string[] $carried
     * @return string
     */
    private static function before_ai_form(
        array $params,
        string $step,
        string $text,
        string $button,
        \moodle_url $back,
        string $backwords,
        array $carried = []
    ): string {
        $buttons = '';
        if ($button !== '') {
            $buttons .= \html_writer::empty_tag('input', [
                'type' => 'submit', 'class' => 'btn btn-primary mr-2', 'value' => $button,
            ]);
        }
        $buttons .= \html_writer::link($back, s($backwords), ['class' => 'btn btn-secondary']);

        return \html_writer::tag(
            'form',
            self::before_ai_hidden($params + $carried, $step)
                . self::before_ai_text(get_string('aioriginal', 'tool_vae'), $text, 'text', 'tool-vae-ai-text')
                . \html_writer::div($buttons, 'mt-2')
                . \html_writer::tag('p', s(get_string('aibackhint', 'tool_vae')), ['class' => 'text-muted small mt-2']),
            ['method' => 'post', 'action' => (new \moodle_url('/admin/tool/vae/ai.php'))->out(false)]
        );
    }
}
