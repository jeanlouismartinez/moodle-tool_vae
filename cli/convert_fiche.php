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
 * Convert one record of the export of France compétences into the CSV file the import screen reads (T-31).
 *
 * A FALLBACK. Since 0.47.0 the screen "Import records from France compétences" reads the archive of the export itself.
 * This script remains for a file one wants to read before importing it through the CSV import screen. It needs an
 * installed Moodle, like every Moodle command line script; the conversion itself is fiche_converter, the class the screen
 * uses, and the script only reads the options, the two files, and writes the result where it is told.
 *
 * NO DATA IN THE PLUGIN. The script holds no data of France compétences (Licence Ouverte 2.0) and writes none into the
 * tree of the plugin: the output goes where the administrator says.
 *
 * EVERY SENTENCE IS A LANGUAGE STRING (cli*), in the language of the site; a refusal is said in the words of the import
 * screen (fiche_messages). Only the names of the options, the example command and the values to type stay as they are.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use tool_vae\local\fiche_converter;
use tool_vae\local\fiche_messages;
use tool_vae\local\fiche_refusal;

// The provenance to type, as the Licence Ouverte 2.0 asks: data, not sentences. Written here, not read from the class of the
// import from data.gouv.fr: no command line script names the class that opens the network door (MC-30).
$sourceurl = 'https://www.data.gouv.fr/datasets/repertoire-national-des-certifications-professionnelles-et-repertoire-specifique';
$licence = 'Licence Ouverte 2.0';
$attribution = 'France compétences';

$usage = implode("\n", [
    get_string('clihelp_intro', 'tool_vae'),
    '',
    get_string('clihelp_export', 'tool_vae', $sourceurl),
    get_string('clihelp_output', 'tool_vae', fiche_converter::LABEL_MAXLENGTH),
    '',
    get_string('clihelp_options', 'tool_vae'),
    '--blocs=PATH      ' . get_string('clihelp_blocs', 'tool_vae'),
    '--standard=PATH   ' . get_string('clihelp_standard', 'tool_vae'),
    '--number=CODE     ' . get_string('clihelp_number', 'tool_vae'),
    '--output=PATH     ' . get_string('clihelp_outputpath', 'tool_vae'),
    '-h, --help        ' . get_string('clihelp_help', 'tool_vae'),
    '',
    get_string('clihelp_absolute', 'tool_vae'),
    '',
    get_string('clihelp_example', 'tool_vae'),
    '$ sudo -u www-data /usr/bin/php admin/tool/vae/cli/convert_fiche.php --blocs=/tmp/export/blocs.csv \\',
    '    --standard=/tmp/export/standard.csv --number=RNCP40692 --output=/tmp/export/deas.csv',
    '',
]);

[$options, $unrecognized] = cli_get_params(
    ['blocs' => '', 'standard' => '', 'number' => '', 'output' => '', 'help' => false],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}
if ($options['help']) {
    echo $usage;
    exit(0);
}
foreach (['blocs', 'standard', 'number', 'output'] as $name) {
    if (trim((string) $options[$name]) === '') {
        cli_error(get_string('climissingoption', 'tool_vae', $name) . "\n\n" . $usage, 2);
    }
}
foreach (['blocs', 'standard', 'output'] as $name) {
    // Moodle has moved to the folder of this script: a relative path would be read, or written, inside the plugin.
    if (!preg_match('~^(/|[A-Za-z]:[\\\\/])~', $options[$name])) {
        cli_error(get_string('clipathnotabsolute', 'tool_vae', (object) ['option' => $name, 'path' => $options[$name]]), 2);
    }
}
foreach (['blocs', 'standard'] as $name) {
    if (!is_readable($options[$name])) {
        cli_error(get_string('clifileunreadable', 'tool_vae', $options[$name]), 2);
    }
}

try {
    $result = fiche_converter::convert(
        (string) file_get_contents($options['blocs']),
        (string) file_get_contents($options['standard']),
        $options['number']
    );
} catch (\RuntimeException $e) {
    // The sentence of the import screen, as plain text; any other exception keeps its own message. The screen names the day
    // of the export a number is missing from; here the day is not known, so the sentence of the script says "the given export".
    if (!$e instanceof fiche_refusal) {
        $why = $e->getMessage();
    } else if ($e->reason() === fiche_refusal::NOT_FOUND) {
        $why = get_string('clinotfound', 'tool_vae');
    } else {
        $why = html_to_text(fiche_messages::html($e), 0, false);
    }
    cli_error(get_string('clirefused', 'tool_vae', trim($why)), 1);
}

if (!fiche_converter::write_csv($options['output'], $result->rows)) {
    cli_error(get_string('clinotwritten', 'tool_vae', $options['output']), 2);
}

cli_writeln(get_string('cliwritten', 'tool_vae', (object) ['count' => count($result->rows), 'path' => $options['output']]));
if ($result->long) {
    cli_writeln(get_string('clilonglabels', 'tool_vae', (object) [
        'max' => fiche_converter::LABEL_MAXLENGTH, 'ranks' => implode(', ', $result->long),
    ]));
}
if ($result->markup) {
    cli_writeln(get_string('climarkuplabels', 'tool_vae', (object) ['ranks' => implode(', ', $result->markup)]));
}
cli_writeln('');
cli_writeln(get_string('clitotype', 'tool_vae'));
cli_writeln('  ' . get_string('importfichecode', 'tool_vae') . ': ' . $result->meta->fichecode);
cli_writeln('  ' . get_string('importfichetype', 'tool_vae') . ': ' . strtoupper($result->meta->fichetype));
cli_writeln('  ' . get_string('importname', 'tool_vae') . ': ' . $result->meta->name);
if ($result->meta->name !== $result->meta->title) {
    cli_writeln('    ' . get_string('clititlenotname', 'tool_vae', (object) [
        'title' => $result->meta->title, 'max' => fiche_converter::LABEL_MAXLENGTH,
    ]));
}
cli_writeln('  ' . get_string('importsourceurl', 'tool_vae') . ': ' . $sourceurl);
cli_writeln('  ' . get_string('importlicence', 'tool_vae') . ': ' . $licence);
cli_writeln('  ' . get_string('importattribution', 'tool_vae') . ': ' . $attribution);
cli_writeln('  ' . get_string('importsourceupdated', 'tool_vae') . ': ' . get_string('cliexportdate', 'tool_vae'));
