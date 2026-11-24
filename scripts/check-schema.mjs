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
 * Checks the rebuilt pivot schema (docs/schema) against its examples and its counter-examples.
 *
 * Every example of docs/schema/exemples must be accepted. Every counter-example of docs/schema/tests/contre-exemples.json
 * is ONE change to the complete example: it must be refused, and it can only be refused for the reason it names. An
 * accepted counter-example means that a rule of the schema has gone. The eight other schemas of the archive (feasibility,
 * validation, reflection, pieces, outside the activity, positioning, self-positioning, situations) are checked the same
 * way, each against its own examples and counter-examples.
 *
 * The rules a JSON schema cannot express (end of period >= start) are checked here, after the schema.
 *
 * The folders, files and keys of docs/schema are in French on purpose: they are the normative format of the archive,
 * documented in docs/schema/LISEZMOI.md. Only the code that reads them is in English.
 *
 * Needs ajv 8 and ajv-formats 3. The node_modules folder that holds them is given by the variable AJV_MODULES; without
 * it, they are looked for next to this script.
 *
 * With --evidence <folder>, every .json file of the folder must also be accepted by the pivot schema, and the folder must
 * not be empty: that is how the continuous integration validates the evidence files the export really produced during
 * PHPUnit (see tests/exporter_test.php). An empty folder would let the check pass without checking anything.
 *
 * Usage: AJV_MODULES=/path/node_modules node scripts/check-schema.mjs [root] [--evidence <folder>]
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {createRequire} from 'node:module';
import {readFileSync, readdirSync} from 'node:fs';
import {dirname, join, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const args = process.argv.slice(2);
const option = args.indexOf('--evidence');
const producedFolder = option >= 0 ? args.splice(option, 2)[1] : null;
const root = resolve(args[0] ?? join(here, '..'));
const folder = join(root, 'docs', 'schema');
const readJson = (path) => JSON.parse(readFileSync(path, 'utf8'));

const modules = process.env.AJV_MODULES;
const require = createRequire(modules ? join(resolve(modules), 'resolver.js') : import.meta.url);
const Ajv2020 = require('ajv/dist/2020').default;
const addFormats = require('ajv-formats').default;

const ajv = new Ajv2020({allErrors: true, strict: true});
addFormats(ajv);
ajv.addSchema(readJson(join(folder, 'commun', 'identifiant.schema.json')));

/**
 * The rules outside the schema for one evidence file. They assume nothing of its shape: the schema may have refused it.
 *
 * @param {object} evidence an evidence file of the archive
 * @returns {string[]} the errors
 */
function rulesOutsideSchema(evidence) {
    const errors = [];
    const start = Date.parse(evidence?.periode?.debut);
    const end = Date.parse(evidence?.periode?.fin);
    if (!Number.isNaN(start) && !Number.isNaN(end) && end < start) {
        errors.push('/periode the end comes before the start');
    }
    if ((evidence?.reportee_depuis === undefined) !== (evidence?.reportee_le === undefined)) {
        errors.push('/ reportee_depuis and reportee_le go together');
    }
    return errors;
}

/**
 * The errors of one document against a compiled schema, as one line each.
 *
 * @param {Function} validate a schema compiled by ajv
 * @param {object} document the document
 * @returns {string[]} the errors
 */
function schemaErrors(validate, document) {
    return validate(document) ? [] : validate.errors.map((e) => `${e.instancePath || '/'} ${e.message}`);
}

/**
 * One counter-example: the base document with ONE change (a value set, a value repeated, or a key removed).
 *
 * @param {object} base the complete example
 * @param {object} counter the counter-example: nom, regle, chemin, and valeur, repeter and fois, or supprimer
 * @returns {object} the changed copy
 */
function apply(base, counter) {
    const copy = structuredClone(base);
    const path = [...counter.chemin];
    const key = path.pop();
    let parent = copy;
    for (const step of path) {
        if (parent[step] === undefined) {
            throw new Error(`counter-example ${counter.nom}: path missing from the base (${counter.chemin.join('.')})`);
        }
        parent = parent[step];
    }
    if (counter.supprimer) {
        if (!(key in parent)) {
            throw new Error(`counter-example ${counter.nom}: nothing to remove at ${counter.chemin.join('.')}`);
        }
        delete parent[key];
    } else {
        parent[key] = counter.repeter !== undefined ? counter.repeter.repeat(counter.fois) : counter.valeur;
    }
    return copy;
}

// The nine schemas of the archive: the name said in the report, the schema, the folder of its examples under
// docs/schema/exemples, and the file of its counter-examples under docs/schema/tests. The pivot schema (one evidence
// file) also has the rules outside the schema.
const pivotValidate = ajv.compile(readJson(join(folder, 'preuve.schema.json')));
const pivotErrors = (document) => schemaErrors(pivotValidate, document).concat(rulesOutsideSchema(document));
const SCHEMAS = [
    {name: 'pivot', errors: pivotErrors, examples: '', counters: 'contre-exemples.json'},
];
for (const [name, file, examples] of [
    ['faisabilite', 'faisabilite.schema.json', 'faisabilite'],
    ['validation', 'validation.schema.json', 'validation'],
    ['reflexion', 'reflexion.schema.json', 'reflexion'],
    ['pieces', 'pieces.schema.json', 'pieces'],
    ['hors_activite', 'hors_activite.schema.json', 'hors_activite'],
    ['positionnement', 'positionnement.schema.json', 'positionnement'],
    ['autopositionnement', 'autopositionnement.schema.json', 'autopositionnement'],
    ['situations', 'situations.schema.json', 'situations'],
]) {
    const validate = ajv.compile(readJson(join(folder, file)));
    SCHEMAS.push({
        name,
        errors: (document) => schemaErrors(validate, document),
        examples,
        counters: `contre-exemples-${name}.json`,
    });
}

let failures = 0;
const counts = [];
for (const schema of SCHEMAS) {
    const examplesFolder = join(folder, 'exemples', schema.examples);
    const examples = readdirSync(examplesFolder).filter((f) => f.endsWith('.json')).sort();
    for (const file of examples) {
        const errors = schema.errors(readJson(join(examplesFolder, file)));
        if (errors.length) {
            failures++;
            console.log(`  ✗ ${schema.name} example refused: ${file}\n      ${errors.join('\n      ')}`);
        }
    }

    const set = readJson(join(folder, 'tests', schema.counters));
    const base = readJson(join(folder, set.base));
    const names = new Set();
    for (const counter of set.cas) {
        if (names.has(counter.nom)) {
            failures++;
            console.log(`  ✗ ${schema.name} counter-example twice: ${counter.nom}`);
        }
        names.add(counter.nom);
        if (schema.errors(apply(base, counter)).length === 0) {
            failures++;
            console.log(`  ✗ ${schema.name} counter-example accepted: ${counter.nom} - ${counter.regle}`);
        }
    }
    counts.push(`  ✓ ${schema.name} schema: ${examples.length} examples accepted, ${set.cas.length} counter-examples refused`);
}

let produced = [];
if (producedFolder !== null) {
    try {
        produced = readdirSync(producedFolder).filter((f) => f.endsWith('.json')).sort();
    } catch (e) {
        produced = [];
    }
    if (produced.length === 0) {
        failures++;
        console.log(`  ✗ no evidence file produced in ${producedFolder}: nothing was checked`);
    }
    for (const file of produced) {
        const errors = pivotErrors(readJson(join(producedFolder, file)));
        if (errors.length) {
            failures++;
            console.log(`  ✗ exported evidence file refused: ${file}\n      ${errors.join('\n      ')}`);
        }
    }
}

if (failures) {
    console.log(`  ${failures} failure(s). See docs/schema/LISEZMOI.md.`);
    process.exit(1);
}
if (producedFolder !== null) {
    console.log(`  ✓ exported evidence files: ${produced.length} accepted`);
}
console.log(counts.join('\n'));
