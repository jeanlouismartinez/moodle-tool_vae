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
 * Whether the tables a piece of work writes to are installed.
 *
 * Observers are notified during installation and upgrade, before the schema exists, and the tasks they queue can be picked up
 * by a cron that runs alongside an upgrade. Each follow-up task asks here first, for EVERY table it writes to, not just the
 * first one it reads: a half-built schema is exactly the situation this guard is for, and a guard that clears the way on the
 * strength of one table out of three would let the work fail on the second. The check costs a few cached lookups on tasks
 * that run a few times a year.
 *
 * It was the private tables_exist() of the observer until ticket 3 of review MMR-520 moved every database call out of it.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plugin_schema {
    /** @var string[] The three tables a purge of declarations writes to. */
    public const DECLARATION_TABLES = ['tool_vae_evidence', 'tool_vae_attest', 'tool_vae_identity'];

    /** @var string[] The two tables the follow-up of a deleted framework writes to: it touches none of the three above. */
    public const RECORD_TABLES = ['tool_vae_source', 'tool_vae_obsolete'];

    /**
     * Whether every one of these tables is installed.
     *
     * @param string[] $tables Names without the prefix.
     * @return bool
     */
    public static function has_tables(array $tables): bool {
        global $DB;

        $dbman = $DB->get_manager();
        foreach ($tables as $table) {
            if (!$dbman->table_exists($table)) {
                return false;
            }
        }

        return true;
    }
}
