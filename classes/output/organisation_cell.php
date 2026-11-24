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
use core\output\renderable;
use core\output\renderer_base;

/**
 * What a cell of the tables of the page organisation.php holds, drawn by the template tool_vae/organisation_cell (lot 7,
 * MMR-520 ticket 4, wave 4b).
 *
 * One named constructor per kind of cell, each with the parameters of the private method of organisation_screen it replaces:
 * the records of a followed vaeiste, their plans, the templates, the referents and the rules of a record, a list of role
 * names, the roles that give a right, the courses, the activities and their folded count, the plans of a competency, and the
 * label of a competency line. A cell is a row of inline parts (a word, a link, a name made by the core), a list of items made
 * of such parts, the fold of a count over its list, and the sentence that says a list may be incomplete.
 *
 * COUNTS, NEVER A VERDICT, A HIDDEN COURSE HAS NO NAME TO DRAW: the words are those organisation_screen wrote. Names of
 * courses, templates, cohorts and activities come formatted by the core (format_string(), or already HTML in the data, as
 * organisation::for_candidate() and organisation::site() give them), and the names of roles from role_get_names(): HTML,
 * placed as it is. Every other text is given plain and escaped once by the template.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class organisation_cell implements named_templatable, renderable {
    /** @var string[] The kinds of inline part, in the order the context lists them. */
    private const KINDS = ['text', 'html', 'link', 'linkhtml', 'span', 'strong', 'code'];

    /** @var string The kind of cell. */
    private string $kind;

    /** @var array The parameters of the kind, as the old method took them. */
    private array $args;

    /**
     * A cell of one kind.
     *
     * @param string $kind
     * @param array $args
     */
    private function __construct(string $kind, array $args) {
        $this->kind = $kind;
        $this->args = $args;
    }

    /**
     * The records a candidate declared on, code and name. Was organisation_screen::fiches().
     *
     * @param \stdClass[] $fiches fichecode, name.
     * @return self
     */
    public static function records(array $fiches): self {
        return new self('records', [$fiches]);
    }

    /**
     * The plans cell of a followed candidate. Was organisation_screen::followed_plans().
     *
     * Neither right: "not readable", never "none". One of the two: the plans of that kind, followed by the sentence that
     * says the list may be incomplete - an empty list is then the sentence alone. Both: the list, or "none".
     *
     * @param \stdClass $candidate As organisation::followed() gives it.
     * @return self
     */
    public static function followed_plans(\stdClass $candidate): self {
        return new self('followed_plans', [$candidate]);
    }

    /**
     * The templates of the core that carry a competency of a record. Was organisation_screen::models().
     *
     * @param \stdClass[] $models templateid, name (null when the core keeps it), visible.
     * @return self
     */
    public static function models(array $models): self {
        return new self('models', [$models]);
    }

    /**
     * The referents of a record, by bloc. Was organisation_screen::referents().
     *
     * @param \stdClass[] $referents ficherang (empty for the whole record), names.
     * @return self
     */
    public static function referents(array $referents): self {
        return new self('referents', [$referents]);
    }

    /**
     * The addresses where the certifier publishes its rule, as links. Was organisation_screen::rules().
     *
     * @param \stdClass[]|null $rules title, url; null where the setting that opens the addresses is closed.
     * @return self
     */
    public static function rules(?array $rules): self {
        return new self('rules', [$rules]);
    }

    /**
     * A list of role names, or the word that says there is none. Was organisation_screen::role_names().
     *
     * @param string[] $names Names ready to be written as HTML (role_get_names() has put them through format_string()).
     * @param string $none The key of the word.
     * @return self
     */
    public static function role_names(array $names, string $none): self {
        return new self('role_names', [$names, $none]);
    }

    /**
     * What the third cell of a right says: the roles that give it, or the word for none. Was organisation_screen::actor_roles().
     *
     * @param \stdClass $right As actors::grid() gives it.
     * @return self
     */
    public static function actor_roles(\stdClass $right): self {
        return new self('actor_roles', [$right]);
    }

    /**
     * The courses that carry a competency. Was organisation_screen::courses().
     *
     * @param \stdClass[] $courses courseid, fullname (null when the core keeps the name), visible.
     * @param bool $unreadable Whether courses carry the competency and the reader may see none: not "none".
     * @return self
     */
    public static function courses(array $courses, bool $unreadable): self {
        return new self('courses', [$courses, $unreadable]);
    }

    /**
     * The activities that carry a competency: "course > activity (type)". Was organisation_screen::activities().
     *
     * @param \stdClass[] $activities course, name, type, url - null where the reader may not be told.
     * @param bool $unreadable Whether activities carry the competency and the reader may see none: not "none".
     * @return self
     */
    public static function activities(array $activities, bool $unreadable): self {
        return new self('activities', [$activities, $unreadable]);
    }

    /**
     * The number of activities of a record, and the list behind it, folded away. Was organisation_screen::activity_count().
     *
     * @param \stdClass[] $activities As in activities().
     * @param bool $unreadable Whether activities carry the competencies of the record and the reader may see none.
     * @param string $fichecode The record, said to a screen reader after the count: many folds share one table.
     * @return self
     */
    public static function activity_count(array $activities, bool $unreadable, string $fichecode): self {
        return new self('activity_count', [$activities, $unreadable, $fichecode]);
    }

    /**
     * The plans that contain a competency, each with the core's word for its status. Was organisation_screen::plans().
     *
     * @param \stdClass[] $plans name, statuslabel.
     * @return self
     */
    public static function plans(array $plans): self {
        return new self('plans', [$plans]);
    }

    /**
     * The label of a competency line of a declared record: its number and name, the word of a competency that left the
     * record, and a bloc line set in bold with its tag. Was the start of the loop of organisation_screen::record().
     *
     * @param \stdClass $row As organisation::for_candidate() gives it: idnumber, shortname, gone, isbloc.
     * @return self
     */
    public static function competency(\stdClass $row): self {
        return new self('competency', [$row]);
    }

    /**
     * The title of a record of the site: its code in bold and the name of its framework, shown as the core shows it. Was
     * the head cell of organisation_screen::site_fiches().
     *
     * @param \stdClass $fiche As organisation::site() gives it: fichecode, name.
     * @return self
     */
    public static function record_title(\stdClass $fiche): self {
        return new self('record_title', [$fiche]);
    }

    /**
     * A right: its label and its name in the code. Was a cell of organisation_screen::site_actors() and the head cell of
     * site_capabilities().
     *
     * @param string $label The label of the right, plain text.
     * @param string $name The name of the capability.
     * @return self
     */
    public static function right(string $label, string $name): self {
        return new self('right', [$label, $name]);
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/organisation_cell';
    }

    /**
     * The context of the template: the inline parts, the fold, the list, the sentence of a partial list, the label of a
     * competency; each absent one null.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $cell = ['parts' => [], 'details' => null, 'list' => null, 'partial' => null, 'competency' => null];
        $method = 'draw_' . $this->kind;

        return array_merge($cell, $this->$method(...$this->args));
    }

    /**
     * A cell of one word.
     *
     * @param string $key The key of the word.
     * @return array
     */
    private static function word(string $key): array {
        return ['parts' => [self::part('text', ['label' => get_string($key, 'tool_vae')])]];
    }

    /**
     * An inline part: every kind named, the one it is set to an object, the others false.
     *
     * @param string $kind
     * @param array $value
     * @return array
     */
    private static function part(string $kind, array $value): array {
        return array_merge(array_fill_keys(self::KINDS, false), [$kind => $value]);
    }

    /**
     * A list of items, each a row of parts.
     *
     * @param array[] $items Each a list of parts.
     * @return array
     */
    private static function items(array $items): array {
        return [
            'class' => 'list-unstyled mb-0',
            'items' => array_map(fn(array $parts): array => ['parts' => $parts], $items),
        ];
    }

    /**
     * The records a candidate declared on.
     *
     * @param \stdClass[] $fiches
     * @return array
     */
    protected function draw_records(array $fiches): array {
        if (!$fiches) {
            return self::word('orgnone');
        }

        $items = [];
        foreach ($fiches as $fiche) {
            $items[] = [self::part('text', ['label' => $fiche->name === $fiche->fichecode
                ? $fiche->fichecode
                : $fiche->fichecode . ' — ' . $fiche->name])];
        }

        return ['list' => self::items($items)];
    }

    /**
     * The plans cell of a followed candidate.
     *
     * @param \stdClass $candidate
     * @return array
     */
    protected function draw_followed_plans(\stdClass $candidate): array {
        if (!$candidate->plansreadable && !$candidate->draftsreadable) {
            return self::word('orgplansunreadable');
        }
        if ($candidate->plansreadable && $candidate->draftsreadable) {
            return $this->draw_plans($candidate->plans);
        }

        return array_merge(
            $candidate->plans ? $this->draw_plans($candidate->plans) : [],
            ['partial' => ['label' => get_string('orgplanspartial', 'tool_vae')]]
        );
    }

    /**
     * The templates of the core that carry a competency of a record.
     *
     * @param \stdClass[] $models
     * @return array
     */
    protected function draw_models(array $models): array {
        if (!$models) {
            return self::word('orgnone');
        }

        $items = [];
        foreach ($models as $model) {
            if ($model->name === null) {
                $items[] = [self::part('text', ['label' => get_string('orgmodelhidden', 'tool_vae')])];
                continue;
            }
            $item = [self::part('html', [
                'texthtml' => format_string($model->name, true, ['context' => \context_system::instance()]),
            ])];
            if (!$model->visible) {
                $item[] = self::part('text', ['label' => ' ']);
                $item[] = self::part('span', [
                    'class' => 'small text-muted',
                    'label' => get_string('orgcoursehiddenmark', 'tool_vae'),
                ]);
            }
            $items[] = $item;
        }

        return ['list' => self::items($items)];
    }

    /**
     * The referents of a record, by bloc.
     *
     * @param \stdClass[] $referents
     * @return array
     */
    protected function draw_referents(array $referents): array {
        if (!$referents) {
            return self::word('orgnone');
        }

        $items = [];
        foreach ($referents as $referent) {
            $where = $referent->ficherang === ''
                ? get_string('referentwhole', 'tool_vae')
                : get_string('referentblocrank', 'tool_vae', $referent->ficherang);
            $who = $referent->names ? implode(', ', $referent->names) : get_string('orgnoreferent', 'tool_vae');
            // The sentence is escaped whole by the template: the old code escaped its two parameters, and the sentence of the
            // language file holds neither "<" nor "&" (the same DOM).
            $items[] = [self::part('text', ['label' => get_string('orgreferentline', 'tool_vae', (object) [
                'where' => $where,
                'who' => $who,
            ])])];
        }

        return ['list' => self::items($items)];
    }

    /**
     * The addresses where the certifier publishes its rule.
     *
     * @param \stdClass[]|null $rules
     * @return array
     */
    protected function draw_rules(?array $rules): array {
        if ($rules === null) {
            return self::word('orgrulesclosed');
        }
        if (!$rules) {
            return self::word('orgnone');
        }

        $items = [];
        foreach ($rules as $rule) {
            $items[] = [self::part('link', ['url' => (new \moodle_url($rule->url))->out(false), 'label' => $rule->title])];
        }

        return ['list' => self::items($items)];
    }

    /**
     * A list of role names, or the word for none.
     *
     * @param string[] $names
     * @param string $none
     * @return array
     */
    protected function draw_role_names(array $names, string $none): array {
        if (!$names) {
            return self::word($none);
        }

        $items = [];
        foreach ($names as $name) {
            $items[] = [self::part('html', ['texthtml' => $name])];
        }

        return ['list' => self::items($items)];
    }

    /**
     * The roles that give a right, or the word for none, and the roles that forbid it.
     *
     * @param \stdClass $right
     * @return array
     */
    protected function draw_actor_roles(\stdClass $right): array {
        if ($right->state === \tool_vae\local\actors::GIVEN) {
            // The names are HTML already (role_get_names): carried as they are, not escaped again.
            $parts = [self::part('html', ['texthtml' => implode(', ', $right->roles)])];
        } else if ($right->state === \tool_vae\local\actors::MISSING) {
            $key = $right->adminmay ? 'orgactormissingadmin' : 'orgactormissingnobody';
            $parts = [
                self::part('span', ['class' => 'badge bg-warning text-dark', 'label' => get_string('orgactormissing', 'tool_vae')]),
                self::part('text', ['label' => ' ' . get_string($key, 'tool_vae')]),
            ];
        } else {
            $key = $right->status === \tool_vae\local\actors::ADMIN ? 'orgactorfreeadmin' : 'orgactorfreeoptional';
            $parts = [self::part('text', ['label' => get_string($key, 'tool_vae')])];
        }
        if ($right->forbidden) {
            // A sentence of the language file whose parameter is HTML of the core (the names of role_get_names()): placed
            // as it is, as before.
            $parts[] = self::part('text', ['label' => ' (']);
            $parts[] = self::part('html', [
                'texthtml' => get_string('orgactorforbiddenby', 'tool_vae', implode(', ', $right->forbidden)),
            ]);
            $parts[] = self::part('text', ['label' => ')']);
        }

        return ['parts' => $parts];
    }

    /**
     * The courses that carry a competency.
     *
     * @param \stdClass[] $courses
     * @param bool $unreadable
     * @return array
     */
    protected function draw_courses(array $courses, bool $unreadable): array {
        if (!$courses) {
            return self::word($unreadable ? 'orgcoursesunreadable' : 'orgnone');
        }

        $items = [];
        foreach ($courses as $course) {
            if ($course->fullname === null) {
                $items[] = [self::part('text', ['label' => get_string('orgcoursehidden', 'tool_vae')])];
                continue;
            }
            $name = format_string($course->fullname, true, ['context' => \context_course::instance((int) $course->courseid)]);
            $item = [self::part('linkhtml', [
                'url' => (new \moodle_url('/course/view.php', ['id' => $course->courseid]))->out(false),
                'labelhtml' => $name,
            ])];
            // A reader who may see hidden courses is told this one is: others do not see it.
            if (!$course->visible) {
                $item[] = self::part('text', ['label' => ' ']);
                $item[] = self::part('span', [
                    'class' => 'small text-muted',
                    'label' => get_string('orgcoursehiddenmark', 'tool_vae'),
                ]);
            }
            $items[] = $item;
        }

        return ['list' => self::items($items)];
    }

    /**
     * The activities that carry a competency.
     *
     * @param \stdClass[] $activities
     * @param bool $unreadable
     * @return array
     */
    private function draw_activities(array $activities, bool $unreadable): array {
        if (!$activities) {
            return self::word($unreadable ? 'orgcoursesunreadable' : 'orgnone');
        }

        $items = [];
        foreach ($activities as $activity) {
            // The names of the course and of the activity come formatted by the core (see the class): HTML.
            $item = [$activity->course === null
                ? self::part('text', ['label' => get_string('orgcoursehidden', 'tool_vae')])
                : self::part('html', ['texthtml' => $activity->course])];
            $item[] = self::part('text', ['label' => ' › ']);
            if ($activity->name === null) {
                $item[] = self::part('text', ['label' => get_string('orgactivityhidden', 'tool_vae')]);
            } else {
                $item[] = $activity->url !== null
                    ? self::part('linkhtml', [
                        'url' => (new \moodle_url($activity->url))->out(false),
                        'labelhtml' => $activity->name,
                    ])
                    : self::part('html', ['texthtml' => $activity->name]);
                $item[] = self::part('text', ['label' => ' (' . $activity->type . ')']);
            }
            $items[] = $item;
        }

        return ['list' => self::items($items)];
    }

    /**
     * The number of activities of a record, and the list behind it, folded away.
     *
     * @param \stdClass[] $activities
     * @param bool $unreadable
     * @param string $fichecode
     * @return array
     */
    protected function draw_activity_count(array $activities, bool $unreadable, string $fichecode): array {
        if (!$activities) {
            return self::word($unreadable ? 'orgcoursesunreadable' : 'orgnone');
        }
        $count = count($activities);
        $label = $count === 1
            ? get_string('orgactivitiesone', 'tool_vae')
            : get_string('orgactivitiesmany', 'tool_vae', $count);

        return array_merge($this->draw_activities($activities, false), ['details' => [
            'title' => get_string('orgactivitieslist', 'tool_vae'),
            'label' => $label,
            'fichecode' => $fichecode,
        ]]);
    }

    /**
     * The plans that contain a competency.
     *
     * @param \stdClass[] $plans
     * @return array
     */
    private function draw_plans(array $plans): array {
        if (!$plans) {
            return self::word('orgnone');
        }

        $items = [];
        foreach ($plans as $plan) {
            $items[] = [self::part('text', ['label' => $plan->name . ' (' . $plan->statuslabel . ')'])];
        }

        return ['list' => self::items($items)];
    }

    /**
     * The title of a record of the site.
     *
     * @param \stdClass $fiche
     * @return array
     */
    protected function draw_record_title(\stdClass $fiche): array {
        $parts = [self::part('strong', ['label' => $fiche->fichecode])];
        if ($fiche->name !== $fiche->fichecode) {
            // The name typed at the import is the framework's: shown as the core shows it, filters included.
            $parts[] = self::part('text', ['label' => ' — ']);
            $parts[] = self::part('html', [
                'texthtml' => format_string($fiche->name, true, ['context' => \context_system::instance()]),
            ]);
        }

        return ['parts' => $parts];
    }

    /**
     * A right: its label and its name in the code.
     *
     * @param string $label
     * @param string $name
     * @return array
     */
    protected function draw_right(string $label, string $name): array {
        return ['parts' => [self::part('text', ['label' => $label . ' ']), self::part('code', ['label' => $name])]];
    }

    /**
     * The label of a competency line.
     *
     * @param \stdClass $row
     * @return array
     */
    protected function draw_competency(\stdClass $row): array {
        $label = $row->idnumber;
        if ($row->shortname !== null) {
            $label .= ' — ' . $row->shortname;
        }

        return ['competency' => [
            'label' => $label,
            'gone' => $row->gone ? ['label' => get_string('coveragegone', 'tool_vae')] : null,
            'isbloc' => (bool) $row->isbloc,
            'tag' => get_string('coveragebloctag', 'tool_vae'),
        ]];
    }
}
