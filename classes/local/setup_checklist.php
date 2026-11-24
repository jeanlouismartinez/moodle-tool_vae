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

namespace tool_vae\local;

/**
 * The steps of the setting up of the plugin, in the order of the installation guide (lot S1, page "Mise en route").
 *
 * WHAT THIS IS, AND WHAT IT IS NOT. It reads facts of the site - configuration, roles, imported records - and says, step by
 * step, what is in place, what is not, and what the plugin cannot know. It writes nothing, produces no HTML, reads no person and
 * no cohort, and draws no conclusion: no total, no count of the steps done. Three states only, and two steps are never "done":
 * who holds a role, which cohorts are attached and whether the policy of the site is published are not things the plugin can
 * see.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class setup_checklist {
    /** @var string The plugin reads that it is in place. */
    public const DONE = 'done';

    /** @var string The plugin reads that it is not in place. */
    public const TODO = 'todo';

    /** @var string The plugin cannot read it: the administrator checks it. */
    public const CHECK = 'check';

    /** @var int Age past which the last run of the cron is late: the value of the core (admin/index.php, $cronoverdue). */
    public const CRON_MAX_AGE = DAYSECS;

    /**
     * The steps, in the order they are shown.
     *
     * @param int|null $now The time to compare the last run of the cron with; the present time when null.
     * @return \stdClass[] Each: key, state, details (plain sentences, translated, not escaped), guide (parts of the guide),
     *         url (?\moodle_url), capability (?string, the right the url asks).
     */
    public static function steps(?int $now = null): array {
        $now = $now ?? time();

        $roles = self::roles();
        $steps = [
            self::competencies(),
            self::cron($now),
            $roles,
            self::rights($roles->state === self::TODO),
            self::assignments(),
            self::record(),
            self::policy(),
        ];
        if (piece_manager::is_open()) {
            $steps[] = self::antivirus();
        }

        return $steps;
    }

    /**
     * One step.
     *
     * @param string $key
     * @param string $state
     * @param string[] $details
     * @param int[] $guide
     * @param \moodle_url|null $url
     * @param string|null $capability
     * @return \stdClass
     */
    private static function step(
        string $key,
        string $state,
        array $details,
        array $guide,
        ?\moodle_url $url,
        ?string $capability
    ): \stdClass {
        return (object) [
            'key' => $key,
            'state' => $state,
            'details' => array_values($details),
            'guide' => $guide,
            'url' => $url,
            'capability' => $capability,
        ];
    }

    /**
     * Step 1: the competencies of the core are enabled (guide, part 4).
     *
     * @return \stdClass
     */
    private static function competencies(): \stdClass {
        return self::step(
            'competencies',
            competency_gateway::is_subsystem_enabled() ? self::DONE : self::TODO,
            [],
            [4],
            new \moodle_url('/admin/settings.php', ['section' => 'optionalsubsystems']),
            'moodle/site:config'
        );
    }

    /**
     * Step 2: the cron runs (guide, part 4). Its last start is read as the core reads it, against the same age.
     *
     * @param int $now
     * @return \stdClass
     */
    private static function cron(int $now): \stdClass {
        $last = get_config('tool_task', 'lastcronstart');
        $details = [];
        if ($last === false || $last === null || !is_numeric($last)) {
            $state = self::TODO;
            $details[] = get_string('setupcronnever', 'tool_vae');
        } else if ((int) $last > $now - self::CRON_MAX_AGE) {
            $state = self::DONE;
        } else {
            $state = self::TODO;
            $details[] = get_string('setupcronlate', 'tool_vae');
        }

        return self::step(
            'cron',
            $state,
            $details,
            [4],
            new \moodle_url('/admin/tool/task/scheduledtasks.php'),
            'moodle/site:config'
        );
    }

    /**
     * Step 3: the three base roles exist, read by their shortname (guide, part 6).
     *
     * @return \stdClass
     */
    private static function roles(): \stdClass {
        $presets = role_installer::presets();
        $missing = [];
        foreach (role_installer::BASE as $shortname) {
            if (!role_installer::exists($shortname)) {
                $name = isset($presets[$shortname]) ? $presets[$shortname]->name : $shortname;
                $missing[] = $name . ' (' . $shortname . ')';
            }
        }
        $details = [];
        if ($missing) {
            $details[] = get_string('setuprolesmissing', 'tool_vae', implode(', ', $missing));
        }
        $details[] = get_string('setuprolesnote', 'tool_vae');

        return self::step(
            'roles',
            $missing ? self::TODO : self::DONE,
            $details,
            [6],
            new \moodle_url('/admin/tool/vae/roles_install.php'),
            'moodle/role:manage'
        );
    }

    /**
     * Step 4: every expected right is given by some role of the site (guide, part 6), read on the grid of the actors.
     *
     * Only the rights the grid says MISSING are named, actor by actor, in the order of the grid: an optional right no role
     * gives is not a gap. The roles and settings are read by organisation::role_rights(), which reads no person and no
     * cohort and asks no right.
     *
     * @param bool $rolesmissing Whether the step of the base roles is to do: the gaps then come first from the absent roles.
     * @return \stdClass
     */
    private static function rights(bool $rolesmissing): \stdClass {
        $read = organisation::role_rights();
        $sentences = [];
        foreach (actors::grid($read->capabilities, $read->settings) as $actor) {
            $missing = [];
            foreach ($actor->rights as $right) {
                if ($right->state === actors::MISSING) {
                    $missing[] = $right->label;
                }
            }
            if ($missing) {
                $sentences[] = get_string('setuprightsactor', 'tool_vae', (object) [
                    'actor' => get_string('orgactor' . $actor->key, 'tool_vae'),
                    'rights' => implode('; ', $missing),
                ]);
            }
        }
        $details = [];
        if ($rolesmissing && $sentences) {
            $details[] = get_string('setuprightsfromroles', 'tool_vae');
        }
        $details = array_merge($details, $sentences);
        $details[] = get_string('setuprightsnote', 'tool_vae');

        return self::step(
            'rights',
            $sentences ? self::TODO : self::DONE,
            $details,
            [6],
            new \moodle_url('/admin/tool/vae/organisation.php', ['tab' => organisation::TAB_SITE]),
            'tool/vae:viewany'
        );
    }

    /**
     * Step 5: the roles are given and the cohorts attached (guide, parts 6 and 7). Never read: always for the administrator.
     *
     * @return \stdClass
     */
    private static function assignments(): \stdClass {
        return self::step('assignments', self::CHECK, [get_string('setupassignmentsnote', 'tool_vae')], [6, 7], null, null);
    }

    /**
     * Step 6: at least one imported record whose framework the core still holds (guide, part 8), read as index.php reads it.
     *
     * @return \stdClass
     */
    private static function record(): \stdClass {
        global $DB;

        $sources = $DB->get_records('tool_vae_source', null, 'fichecode ASC');
        $missing = source_frameworks::missing($sources);
        $details = [];
        if (!$sources) {
            $state = self::TODO;
            $details[] = get_string('setuprecordnone', 'tool_vae');
        } else if (count($missing) === count($sources)) {
            $state = self::TODO;
            $details[] = get_string('setuprecordgone', 'tool_vae', implode(', ', $missing));
        } else {
            $state = self::DONE;
        }

        return self::step(
            'record',
            $state,
            $details,
            [8],
            new \moodle_url('/admin/tool/vae/import_fiches.php'),
            'tool/vae:import'
        );
    }

    /**
     * Step 7: the policy and the privacy notice of the site are published (guide, part 10). Never read.
     *
     * @return \stdClass
     */
    private static function policy(): \stdClass {
        return self::step(
            'policy',
            self::CHECK,
            [get_string('setuppolicynote', 'tool_vae')],
            [10],
            new \moodle_url('/admin/settings.php', ['section' => 'policysettings']),
            'moodle/site:config'
        );
    }

    /**
     * Step 8: an antivirus scans the pieces (guide, part 11). Only listed while adding pieces is open.
     *
     * @return \stdClass
     */
    private static function antivirus(): \stdClass {
        $details = [];
        if (piece_manager::antivirus_configured()) {
            $state = self::DONE;
        } else if (piece_manager::settings()->noantivirus) {
            $state = self::CHECK;
            $details[] = get_string('setupantivirusaccepted', 'tool_vae');
        } else {
            $state = self::TODO;
            $details[] = get_string('setupantivirusrefused', 'tool_vae');
        }

        return self::step(
            'antivirus',
            $state,
            $details,
            [11],
            new \moodle_url('/admin/settings.php', ['section' => 'manageantiviruses']),
            'moodle/site:config'
        );
    }
}
