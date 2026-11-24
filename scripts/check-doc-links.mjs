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
 * Checks that no published file points to a document that is missing or not published.
 *
 * The public repository is produced from the working repository: of docs/, it only keeps docs/schema/ and the documents
 * of the constant PUBLISHED below. A reference to another document of docs/ would lead, in the public repository, to a
 * missing file. This script reads the files that leave with the plugin, notes every reference of the form
 * docs/<path>.md and every relative Markdown link of a published document, and fails (code 1, one line per wrong
 * reference: file, line, target) when the target is not published or does not exist under the given root.
 *
 * Usage: node scripts/check-doc-links.mjs [root]
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {existsSync, readFileSync, readdirSync, statSync} from 'node:fs';
import {dirname, join, posix, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

// Documents of docs/ that the publication procedure keeps (decision of the project owner of 9 November 2026), on top of
// the whole folder docs/schema/. This list must stay equal to the one of the publication procedure: a document added
// here and not there (or the reverse) makes this check wrong.
const PUBLISHED = [
    'docs/AIPD-VAE.md',
    'docs/BRANCHES.md',
    'docs/FAQ.md',
    'docs/IMPORT-FICHES-FRANCE-COMPETENCES.md',
    'docs/INSTALLATION-PAS-A-PAS.md',
    'docs/MODELE-CONTRAT-USAGE-PLATEFORME.md',
    'docs/T-84-conformite-rgpd.md',
    // The three English pages of the README (review MMR-520, ticket 7, 22 November 2026): to be added to the publication
    // procedure as well.
    'docs/en/external-services.md',
    'docs/en/installation.md',
    'docs/en/privacy.md',
];
const PUBLISHED_FOLDER = 'docs/schema/';

// Files read: the README files and the change log, the licence, the Makefile, and the code by extension.
const ROOT_FILES = ['README.md', 'README.fr.md', 'CHANGELOG.md', 'LICENSE', 'Makefile'];
const EXTENSIONS = ['.php', '.mjs', '.sh', '.feature', '.xml', '.yml', '.mustache', '.js'];
// Folders that do not leave with the plugin (local tools, continuous integration of the working repository,
// deployment, built modules); docs/ is handled apart.
const EXCLUDED = new Set(['.git', '.claude', '.superpowers', '.unlazy', '.forgejo', 'node_modules', 'vendor', 'deploy']);

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(process.argv[2] ?? join(here, '..'));

const isPublished = (target) => PUBLISHED.includes(target) || target.startsWith(PUBLISHED_FOLDER);

/**
 * Lists the published files under the root, as relative paths with forward slashes.
 *
 * @param {string} folder relative path of the folder read ('' for the root)
 * @returns {string[]} relative paths of the files to read
 */
function files(folder) {
    const list = [];
    for (const name of readdirSync(join(root, folder)).sort()) {
        const relative = folder ? `${folder}/${name}` : name;
        const full = join(root, relative);
        if (statSync(full).isDirectory()) {
            if (EXCLUDED.has(name) || relative === 'amd/build') {
                continue;
            }
            if (relative === 'docs') {
                list.push(...PUBLISHED.filter((d) => existsSync(join(root, d))));
                if (existsSync(join(root, PUBLISHED_FOLDER))) {
                    list.push(...files('docs/schema').filter((f) => f.endsWith('.md')));
                }
                continue;
            }
            list.push(...files(relative));
        } else if (folder.startsWith('docs/') || (folder === '' && ROOT_FILES.includes(name))
            || EXTENSIONS.some((ext) => name.endsWith(ext))) {
            list.push(relative);
        }
    }
    return list;
}

const faults = [];
const read = files('');
for (const file of read) {
    const lines = readFileSync(join(root, file), 'utf8').split('\n');
    const markdown = file.endsWith('.md');
    lines.forEach((line, i) => {
        const targets = new Set();
        for (const m of line.matchAll(/(?<![A-Za-z0-9_.-])docs\/[A-Za-z0-9_.\/-]+?\.md\b/g)) {
            targets.add(m[0]);
        }
        if (markdown) {
            // Relative Markdown links: the target is resolved from the folder of the document.
            for (const m of line.matchAll(/\]\(([^)\s#]+)(#[^)\s]*)?\)/g)) {
                const link = m[1];
                if (/^[a-z][a-z0-9+.-]*:/i.test(link)) {
                    continue;
                }
                const target = posix.normalize(posix.join(posix.dirname(file), link));
                if (target.startsWith('../')) {
                    faults.push(`${file}:${i + 1}: ${link} (outside the repository)`);
                    continue;
                }
                if (target.startsWith('docs/')) {
                    targets.add(target);
                } else if (!existsSync(join(root, target))) {
                    faults.push(`${file}:${i + 1}: ${link} (missing)`);
                }
            }
        }
        for (const target of targets) {
            if (!isPublished(target)) {
                faults.push(`${file}:${i + 1}: ${target} (not published)`);
            } else if (!existsSync(join(root, target))) {
                faults.push(`${file}:${i + 1}: ${target} (missing)`);
            }
        }
    });
}

if (faults.length) {
    console.log(`✗ doc links: ${faults.length} reference(s) to a missing or unpublished document:`);
    for (const fault of faults) {
        console.log(`  ${fault}`);
    }
    process.exit(1);
}
console.log(`✓ doc links: ${read.length} files read, no reference to a missing or unpublished document`);
