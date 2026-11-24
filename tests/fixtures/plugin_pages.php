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

namespace tool_vae\fixtures;

/**
 * A page of the plugin, played as the site plays it, inside a test.
 *
 * The tests that read what a page prints set the request ($_GET, $_POST, $PAGE) and buffer the output; this class only
 * runs the page. EVERY PATH IS WRITTEN OUT: a page is included by a literal path, never by a path built from a variable,
 * and a page that is not listed here is refused by name, so that a test asking for it fails instead of including anything.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plugin_pages {
    /**
     * Run one page of the plugin, with the globals a page of the site reads.
     *
     * @param string $script The file name of the page, at the root of the plugin.
     */
    public static function run(string $script): void {
        // The page is a script of the site: it reads these as globals.
        global $CFG, $DB, $OUTPUT, $PAGE, $SITE, $USER;

        match ($script) {
            'ai.php' => include(__DIR__ . '/../../ai.php'),
            'attest.php' => include(__DIR__ . '/../../attest.php'),
            'consent.php' => include(__DIR__ . '/../../consent.php'),
            'declare.php' => include(__DIR__ . '/../../declare.php'),
            'document.php' => include(__DIR__ . '/../../document.php'),
            'dossier.php' => include(__DIR__ . '/../../dossier.php'),
            'history.php' => include(__DIR__ . '/../../history.php'),
            'import_fiches.php' => include(__DIR__ . '/../../import_fiches.php'),
            'organisation.php' => include(__DIR__ . '/../../organisation.php'),
            'piece.php' => include(__DIR__ . '/../../piece.php'),
            'section.php' => include(__DIR__ . '/../../section.php'),
            'selfposition.php' => include(__DIR__ . '/../../selfposition.php'),
            'situation.php' => include(__DIR__ . '/../../situation.php'),
            'situations.php' => include(__DIR__ . '/../../situations.php'),
            'validation.php' => include(__DIR__ . '/../../validation.php'),
            default => throw new \coding_exception("The page '$script' is not listed in tests/fixtures/plugin_pages.php"),
        };
    }
}
