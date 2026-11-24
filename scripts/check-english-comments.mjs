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
 * Fails when a code comment of the repository is written in French.
 *
 * WHY. The guidelines of the Moodle plugins directory ask for every code comment, variable and function name in English
 * (Use of English); the review of 12 November 2026 found about 305 lines of French comments. This check keeps them from
 * coming back. It reads the COMMENTS of the code (php, js, mjs, sh, yml, mustache, css, feature, Makefile, .gitignore,
 * .gitattributes), not the strings: test data in French (texts of vaeistes, CSV files) is data, not a comment. It does not
 * read lang/ (the French language pack is a translation), docs/ (the French documentation) nor the tools of the working
 * repository.
 *
 * HOW A LINE IS JUDGED. The French terms an English comment may name without quotation marks (names of the French law and
 * of France compétences, words of the screens, the vocabulary of the project) are listed in ALLOWED_TERMS below,
 * explicitly; they are removed from the line first, with what stands between quotation marks ("...", `...`, « ... »),
 * addresses, paths and identifiers. The line is then French when a word with a French accent is left, or when two
 * different French function words (`le`, `des`, `pour`, `avec`...) are left and make a third of its words. A false alarm is fixed
 * by quoting the French text or by adding the term to ALLOWED_TERMS, never by turning the check off.
 *
 * Usage: node scripts/check-english-comments.mjs [root]
 *        node scripts/check-english-comments.mjs --self-test
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {readFileSync, readdirSync, statSync} from 'node:fs';
import {basename, dirname, extname, join, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

// French terms that an English comment may quote. Each one is a term of the domain, a name, or the words of a screen,
// written as they are quoted. Matched without regard to case.
const ALLOWED_TERMS = [
    // Names and registers of the French law and of France compétences.
    'France compétences', 'Répertoire national des certifications professionnelles', 'Répertoire spécifique',
    'Blocs_De_Compétences', 'Blocs de compétences', 'Licence Ouverte', 'VoixdAccès', 'arrêté', 'décret',
    'Code du travail', 'dossier de faisabilité', 'dossier de validation', 'livret 1', 'livret 2', 'candidat accompagné',
    'Commission nationale de l\'informatique et des libertés', 'Délégation', 'Diplôme d\'État', 'aide-soignant',
    // Words of the screens and of the guides, quoted as the user reads them.
    'Ce que je peux faire maintenant', 'Mise en route', 'Mes vaeistes', 'Vaeistes suivis', 'Aller chercher le dernier export',
    'Importer des fiches depuis France compétences', 'Compétences', 'Préparation', 'Faisabilité', 'compétence',
    'compétences', 'préparation', 'faisabilité', 'référentiel', 'référent', 'référents', 'pièce', 'pièces', 'clôturé',
    'élément', 'éléments', 'Accompagnement au lever', 'résidents',
    // Quotations that run over two lines of a comment, so that no quotation mark closes them on the line.
    'Les droits à', 'Echelle d\'évaluation de compétence par', 'défault', 'accompagné', 'brouillon, actif, terminé',
    // Places and sources.
    'Légifrance', 'La Réunion',
    // Data of a test written in a comment (a cell of a table).
    'Activité',
];

// French function words: two of them left on a line make it French. Words that are also English (a, on, son, pour as a
// verb...) or language codes (en, fr) are not listed.
const FRENCH_WORDS = new Set([
    'le', 'la', 'les', 'des', 'du', 'une', 'est', 'sont', 'pour', 'dans', 'avec', 'sans', 'que', 'qui', 'pas', 'sur',
    'aux', 'au', 'ce', 'cette', 'ces', 'il', 'elle', 'ils', 'nous', 'vous', 'leur', 'leurs', 'mais', 'donc', 'puis',
    'ne', 'plus', 'tout', 'tous', 'toute', 'chaque', 'selon', 'lorsque', 'quand', 'comme', 'aussi', 'deja', 'encore',
    'fichier', 'fichiers', 'controle', 'verifie', 'chaine', 'chaines', 'ligne', 'lignes', 'ici', 'fait', 'faut', 'peut',
    'doit', 'avant', 'apres', 'entre', 'sous', 'vers', 'chez', 'sauf', 'meme', 'autre', 'autres', 'rien', 'jamais',
    'toujours', 'parce', 'ainsi', 'alors', 'dont', 'ou', 'et', 'un', 'de', 'par', 'en',
]);
const ACCENTED = /[àâäçéèêëîïôöûùüÿœæ]/i;

// Folders never read: tools of the working repository, generated code, data, translations and documentation.
const SKIPPED = new Set(['.git', '.claude', '.superpowers', '.unlazy', 'node_modules', 'vendor', 'docs', 'lang']);
const SKIPPED_PATHS = new Set(['amd/build', 'deploy']);
const EXTENSIONS = new Map([
    ['.php', 'c'], ['.js', 'js'], ['.mjs', 'js'], ['.css', 'css'], ['.mustache', 'mustache'],
    ['.sh', 'hash'], ['.yml', 'hash'], ['.yaml', 'hash'], ['.feature', 'feature'],
]);
const NAMES = new Map([['Makefile', 'hash'], ['.gitignore', 'hash'], ['.gitattributes', 'hash']]);

/**
 * The comments of a C-like source (PHP, JavaScript, CSS), with their line numbers.
 *
 * Strings are skipped, so that the text of a string is never read as a comment: '...', "...", `...` (JavaScript),
 * heredoc and nowdoc (PHP), and regular expression literals (JavaScript, by the character before them).
 *
 * @param {string} text the source
 * @param {string} kind 'c' (PHP), 'js' or 'css'
 * @returns {Array<[number, string]>} line number and text of each comment line
 */
function cComments(text, kind) {
    const out = [];
    let line = 1;
    let i = 0;
    let previous = '';
    const push = (start, body) => {
        body.split('\n').forEach((part, k) => out.push([start + k, part]));
    };
    while (i < text.length) {
        const c = text[i];
        const next = text[i + 1];
        if (c === '\n') {
            line++;
            i++;
            continue;
        }
        if (c === '/' && next === '*') {
            const end = text.indexOf('*/', i + 2);
            const stop = end < 0 ? text.length : end;
            const body = text.slice(i + 2, stop);
            push(line, body);
            line += (body.match(/\n/g) || []).length;
            i = stop + 2;
            previous = ' ';
            continue;
        }
        if (kind !== 'css' && ((c === '/' && next === '/') || (kind === 'c' && c === '#' && next !== '['))) {
            let stop = text.indexOf('\n', i);
            stop = stop < 0 ? text.length : stop;
            out.push([line, text.slice(i + (c === '#' ? 1 : 2), stop)]);
            i = stop;
            continue;
        }
        if (c === '\'' || c === '"' || (kind === 'js' && c === '`')) {
            let j = i + 1;
            while (j < text.length && text[j] !== c) {
                if (text[j] === '\\') {
                    j++;
                } else if (text[j] === '\n') {
                    line++;
                }
                j++;
            }
            i = j + 1;
            previous = c;
            continue;
        }
        if (kind === 'c' && text.startsWith('<<<', i)) {
            const head = /^<<<[ \t]*['"]?([A-Za-z_][A-Za-z0-9_]*)['"]?\r?\n/.exec(text.slice(i));
            if (head) {
                const close = new RegExp(`\\n[ \\t]*${head[1]}\\b`).exec(text.slice(i + head[0].length - 1));
                const stop = close ? i + head[0].length - 1 + close.index + close[0].length : text.length;
                line += (text.slice(i, stop).match(/\n/g) || []).length;
                i = stop;
                previous = ';';
                continue;
            }
        }
        if (kind === 'js' && c === '/' && /^$|[(,=:[!&|?{};+\-*%<>~^]$/.test(previous)) {
            let j = i + 1;
            let inClass = false;
            while (j < text.length && text[j] !== '\n' && (text[j] !== '/' || inClass)) {
                if (text[j] === '\\') {
                    j++;
                } else if (text[j] === '[') {
                    inClass = true;
                } else if (text[j] === ']') {
                    inClass = false;
                }
                j++;
            }
            i = j + 1;
            previous = 'a';
            continue;
        }
        if (!/\s/.test(c)) {
            previous = c;
        }
        i++;
    }
    return out;
}

/**
 * The comments of a source whose comments start with # (shell, YAML, Makefile, Git files): a # at the start of the line
 * or after a blank, outside quotes.
 *
 * @param {string} text the source
 * @returns {Array<[number, string]>} line number and text of each comment
 */
function hashComments(text) {
    const out = [];
    text.split('\n').forEach((source, index) => {
        let quote = null;
        for (let j = 0; j < source.length; j++) {
            const c = source[j];
            if (quote) {
                if (c === '\\' && quote === '"') {
                    j++;
                } else if (c === quote) {
                    quote = null;
                }
            } else if (c === '\'' || c === '"') {
                quote = c;
            } else if (c === '#' && (j === 0 || /\s/.test(source[j - 1]))) {
                out.push([index + 1, source.slice(j + 1)]);
                break;
            }
        }
    });
    return out;
}

/**
 * The comments of a source, by its kind.
 *
 * @param {string} text the source
 * @param {string} kind the kind of the file
 * @returns {Array<[number, string]>} line number and text of each comment
 */
function comments(text, kind) {
    if (kind === 'hash') {
        return hashComments(text);
    }
    if (kind === 'feature') {
        return text.split('\n').map((l, i) => [i + 1, l]).filter(([, l]) => /^\s*#/.test(l))
            .map(([n, l]) => [n, l.replace(/^\s*#/, '')]);
    }
    if (kind === 'mustache') {
        const out = [];
        for (const m of text.matchAll(/\{\{!([\s\S]*?)\}\}/g)) {
            const start = text.slice(0, m.index).split('\n').length;
            m[1].split('\n').forEach((part, k) => out.push([start + k, part]));
        }
        return out;
    }
    return cComments(text, kind);
}

/**
 * Whether one comment line is written in French.
 *
 * @param {string} line the text of the comment
 * @returns {boolean}
 */
function isFrench(line) {
    let text = line.normalize('NFC');
    // The longest terms first: a short one must not cut a long one that holds it.
    for (const term of [...ALLOWED_TERMS].sort((a, b) => b.length - a.length)) {
        text = text.split(new RegExp(term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi')).join(' ');
    }
    // A quotation is not the language of the comment: text between backquotes, double quotes or French quotation marks.
    text = text.replace(/`[^`]*`/g, ' ').replace(/"[^"]*"/g, ' ').replace(/«[^»]*»/g, ' ').replace(/“[^”]*”/g, ' ');
    // Addresses, paths and identifiers are not prose.
    text = text.replace(/\bhttps?:\/\/\S+/g, ' ').replace(/\S*[/\\_$@{}<>=:]\S*/g, ' ');
    const words = text.toLowerCase().split(/[^a-zàâäçéèêëîïôöûùüÿœæ]+/).filter(Boolean);
    if (words.some((w) => ACCENTED.test(w))) {
        return true;
    }
    return new Set(words.filter((w) => FRENCH_WORDS.has(w))).size >= 2
        && words.filter((w) => FRENCH_WORDS.has(w)).length * 3 >= Math.min(words.length, 9);
}

/**
 * The files read under the root, as relative paths with forward slashes.
 *
 * @param {string} root the root of the plugin
 * @param {string} folder the relative folder read
 * @returns {Array<[string, string]>} relative path and kind of each file
 */
function files(root, folder = '') {
    const list = [];
    for (const name of readdirSync(join(root, folder)).sort()) {
        const relative = folder ? `${folder}/${name}` : name;
        if (statSync(join(root, relative)).isDirectory()) {
            if (!SKIPPED.has(name) && !SKIPPED_PATHS.has(relative)) {
                list.push(...files(root, relative));
            }
            continue;
        }
        const kind = NAMES.get(name) ?? EXTENSIONS.get(extname(name));
        if (kind) {
            list.push([relative, kind]);
        }
    }
    return list;
}

/**
 * The check on a tree.
 *
 * @param {string} root the root of the plugin
 * @returns {{read: number, faults: string[]}} the number of files read and one line per French comment
 */
function check(root) {
    const faults = [];
    const read = files(root);
    for (const [relative, kind] of read) {
        for (const [number, text] of comments(readFileSync(join(root, relative), 'utf8'), kind)) {
            if (isFrench(text)) {
                faults.push(`${relative}:${number}: ${text.trim()}`);
            }
        }
    }
    return {read: read.length, faults};
}

/**
 * The self-test: what must be caught is caught, what must pass passes.
 *
 * @returns {string[]} one line per case that went wrong
 */
function selfTest() {
    const wrong = [];
    const french = [
        'Verifie que chaque capability declaree porte sa chaine de langue',
        'Le nom du sous-systeme vient de la passerelle',
        'L\'administrateur relance le même fichier',
        'Copie l\'arbre de travail dans le conteneur',
        'Ce controle a deja crie au loup une fois',
        'Réécrit le 11 octobre 2026',
    ];
    const english = [
        'The banner "Ce que je peux faire maintenant" (lot S2): its title, then the buttons',
        'Names and registers of France compétences, read on the exports of 19 and 27 October 2026',
        'A label of the shape `Installer un mât (<10m) dans une gaine` would be cut',
        'The record is converted as blocs, one line each, and a dossier de faisabilité is never read here',
        'tests/fixtures/README-fixtures.md: a fixture in lang/en, en and fr',
        'The value of sans-serif is the font of the theme, as is the one of le_mot_de_passe',
        '',
    ];
    for (const line of french) {
        if (!isFrench(line)) {
            wrong.push(`not caught: ${line}`);
        }
    }
    for (const line of english) {
        if (isFrench(line)) {
            wrong.push(`caught by mistake: ${line}`);
        }
    }
    const php = "<?php\n// Le fichier est lu ici.\n$x = 'Le fichier est lu ici, dans une chaine';\n/* Une ligne pour le test. */\n";
    const found = comments(php, 'c').filter(([, t]) => isFrench(t)).map(([n]) => n);
    if (found.join(',') !== '2,4') {
        wrong.push(`PHP comments and strings told apart wrongly: lines ${found.join(',')}`);
    }
    const shell = "echo \"# pas un commentaire, une chaine\" # Le commentaire est ici, pour le test\nx=$#\n";
    const lines = comments(shell, 'hash').filter(([, t]) => isFrench(t)).map(([n]) => n);
    if (lines.join(',') !== '1' || comments(shell, 'hash').length !== 1) {
        wrong.push('shell comments and strings told apart wrongly');
    }
    return wrong;
}

const here = dirname(fileURLToPath(import.meta.url));
if (process.argv[2] === '--self-test') {
    const wrong = selfTest();
    if (wrong.length) {
        console.log(`✗ English comments, self-test: ${wrong.length} case(s) wrong`);
        wrong.forEach((w) => console.log(`  ${w}`));
        process.exit(1);
    }
    console.log('✓ English comments, self-test: French caught, English and quoted terms let through');
    process.exit(0);
}

const root = resolve(process.argv[2] ?? join(here, '..'));
const wrong = selfTest();
if (wrong.length) {
    console.log(`✗ English comments: the check itself is wrong (${wrong.length} case(s)), run it with --self-test`);
    process.exit(2);
}
const {read, faults} = check(root);
if (read === 0) {
    console.log(`✗ English comments: no file read under ${root}`);
    process.exit(2);
}
if (faults.length) {
    console.log(`✗ English comments: ${faults.length} comment line(s) in French (${basename(root)}):`);
    faults.forEach((f) => console.log(`  ${f}`));
    process.exit(1);
}
console.log(`✓ English comments: ${read} files read, no comment in French`);
