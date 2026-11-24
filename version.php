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

/**
 * Plugin version and metadata.
 *
 * This file keeps only what explains its values. The history of the versions is in CHANGELOG.md.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'tool_vae';

// THE MIGRATION FROM 4.5. A site moving from Moodle 4.5 to 5.3 replaces the MOODLE_405 package with this one, and Moodle
// only runs a step whose number is above the installed one. So the 4.5 number stays below the lowest step that only this
// package has - 2026101403, the idempotent copy of step 2026101311 (T-80) - and this number is always above the 4.5 one.
// A step of the 4.5 branch is copied here under another number, and every step checks before it acts, so that a site
// which ran it on 4.5 runs it again here to no effect. See docs/BRANCHES.md. The `migration` job of the continuous
// integration, which proved the passage against the tip of MOODLE_405, was removed on 17 November 2026.
// NUMBERING: the date of the build and a two-digit counter (YYYYMMDDNN), always rising; db/upgrade.php ends on this number.
$plugin->version   = 2026112200; // 22 November 2026: new classes, templates and strings since 2026111901 (review MMR-520).

// 2026100500.00 is Moodle 5.3.0, released on 5 October 2026.
$plugin->requires  = 2026100500.00;

// MOODLE LTS RELEASES ONLY, ONE AT A TIME (decision of 28 August 2026). A dossier of prior learning is built over months and
// read by a panel years later: the platform under it must still receive security fixes that day. An LTS carries three
// years of them, an intermediate release eighteen months. `supported` DECLARES that policy, [503, 503]; it is not a lock
// (on another branch Moodle only flags the plugin on the plugins page). What refuses is `requires` (5.3.0, above) and
// `incompatible` (5.4, below): an administrator who upgrades the core to an intermediate release finds the plugin blocked
// rather than running where nobody tested it. Nothing in Moodle says "LTS only"; this is the closest thing to it (decided
// with the project owner on 13 October 2026). When the next LTS is tested, raise `incompatible` to the branch after it.
// TWO PACKAGES were opened on 12 October 2026; this is MOODLE_503. MOODLE_405, supported = [405, 405], is no longer
// maintained (decision of the project owner, 27 October 2026, see docs/BRANCHES.md): no fix is promised, even until
// 4 October 2027, when Moodle 4.5 LTS itself stops receiving security fixes. Neither widens its range to swallow the
// other: announcing a compatibility nobody tests is worse than announcing none.
$plugin->supported = [503, 503];

// The first Moodle branch that REFUSES this package: 5.4. core: branch >= incompatible is refused.
$plugin->incompatible = 504;

// LICENCE: GNU GPL v3 or later, as every file header and the LICENSE file say (decision of the project owner, 28 October
// 2026) - the licence of Moodle itself. Holder: Jean-Louis Martinez <jeanlouis@martinez.pro>. The GNU AGPL v3 was
// considered the same day and not retained. PO-2 stays open: it asks whether the data of France competences (Licence
// Ouverte 2.0) may be used with GPL code; the plugin never ships that data, the administrator loads it at run time, and
// a legal opinion must settle it (README, "Licence").

// MATURITY_BETA (decision of the project owner, 22 November 2026): complete for what it announces, still under test. Not
// MATURITY_STABLE: the tests are green on PostgreSQL, MariaDB and MySQL, but nobody has used the plugin with real vaeistes
// yet and PO-2 is not settled. Maturity describes the plugin, not the test report.
$plugin->maturity  = MATURITY_BETA;
// RELEASE: "generation . Moodle branch" since 01.5.3 (28 October 2026). 01 was the first release sent to the plugins directory;
// 2 is the second generation of the plugin. The next one for the same branch raises it.
$plugin->release   = '2.5.03 (Moodle 5.3)';
