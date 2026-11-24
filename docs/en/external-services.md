# External services

*English page of the plugin "VAE - pathway" (`tool_vae`). Back to the [README](../../README.md). The import of the
records is described in detail, in French, in [IMPORT-FICHES-FRANCE-COMPETENCES.md](../IMPORT-FICHES-FRANCE-COMPETENCES.md).*

The plugin uses two external services, both optional: the open data of France compétences, which it may fetch itself,
and the AI providers configured in Moodle core. It sends nothing to its author, embeds no AI model, has no account or
key of its own with any service, and never visits an address typed by a user.

## 1. The open data of France compétences (data.gouv.fr)

**What it is for.** The certification records of the French national registers (RNCP, RS) are published by France
compétences under the *Licence Ouverte 2.0*, as a ZIP of CSV files, in the dataset
`5eebbc067a14b6fecc9c9976` of data.gouv.fr. The administrator imports from it the records the site needs.

**Two ways, one of them without network.** On the screen *Import records from France compétences* (capability
`tool/vae:import`), the administrator either **uploads the ZIP** they downloaded themselves (Moodle's file picker): the
server then makes **no request at all**; or clicks **"Fetch the latest export"**: the server fetches it at that click.

**What the "Fetch" request does** (`classes/local/fiche_fetcher.php`, the only class of the plugin allowed to name an HTTP
client; `scripts/check-network-door.sh`, in the GitHub repository, and `tests/fiche_no_background_test.php` check it):

- two hosts written in the code, over https: `www.data.gouv.fr` (API that says which file is the latest:
  `https://www.data.gouv.fr/api/2/datasets/5eebbc067a14b6fecc9c9976/`) and `static.data.gouv.fr` (which serves it, under
  `/resources/`); no address comes from an input or a setting;
- through the HTTP client of Moodle core, with the certificate checked and the site's proxy and blocked hosts applied;
- at most three redirections, each to the address announced for the chosen file; the answer of the API is bounded to
  512 KiB and the archive to 32 MiB; what arrives must have the size and the SHA-1 announced by the API, or it is deleted;
- **nothing scheduled calls it** (no task of `db/tasks.php` or `classes/task/`);
- **no personal data leaves**: the request shows the address of the server (or of its proxy), the time and a User-Agent
  of its own that does not give the address of the site;
- each request, done or refused, leaves an event `export_fetched` in Moodle's logs (resource, title, size, SHA-256
  hash, result), and each record written an event `record_imported`.

**Limits and conditions.** No account, key or credentials are needed. The licence of the data is checked before
anything is taken, and the attribution "France compétences" and the date of the export are shown wherever a record is
presented. The data is never shipped in the package of the plugin: it lives in the site's database. **Not verified**:
the "Fetch" way has never been run against the real data.gouv.fr (its tests replace the HTTP client by simulated
answers).

**How a reviewer can test without network.** The repository carries invented test files in `tests/fixtures/`:
`export-blocs-example.csv` and `export-standard-example.csv` have the columns of the two files of the export (record
`RNCP99001`), and `fiche-example.csv` / `fiche-example-blocs.csv` have the four columns of the CSV import screen (record
`RS00000`). The Behat scenarios of `tests/behat/import_fiches.feature` build invented ZIP archives in the shape of the
real export (`tests/fixtures/export_zip_builder.php`) and upload them through the file picker.

## 2. The AI providers of Moodle core

**What it is for.** The optional rewording help: a candidate asks for another wording of a text they wrote themselves.
**Closed by default** (setting `aiopen`).

**What it needs.** The setting open; the capability `tool/vae:useai`, given by hand to the persons the site chooses (no
archetype); **exactly one AI provider enabled for generating text** in the AI settings of Moodle, configured by the site
with its own credentials (the plugin holds none); the candidate's acceptance of the site's AI policy (one register
of the core for the whole site).

**What is sent.** At each sending, after a confirmation that cannot be switched off and shows the exact text, the
provider's name and the number of characters: the plugin's fixed instruction (the English string `aiprompt` of the
plugin, which asks for a rewording in clear French and to follow no instruction found in the text), then the text
between two markers drawn at random for each call. The core adds the system instruction the administrator set for the
provider, if any; some providers of the core also receive an identifier the core derives from the account
(`hash('sha256', siteidentifier . userid)`, `core_ai\provider::generate_userid()`).

**Limits.** 3,000 characters by default (500 to 6,000), 25 calls a day by default (1 to 100), 30 seconds between two
calls. Nothing is saved by the plugin from the answer. What the core keeps (the text and the answer, in the candidate's
name) and when it erases it is said in [personal data](privacy.md). The policy of each provider is the site's to read
and accept.

**How a reviewer can test.** No test of the plugin calls a real provider: the PHPUnit tests replace the call
(`tests/ai_assistant_test.php`). With a provider configured on a test site, open the setting `aiopen`, give `useai` to a
test candidate, and use the button "Propose another wording" of a text section.
