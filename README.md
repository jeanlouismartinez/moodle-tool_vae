# VAE - pathway (`tool_vae`)

*Version française : [README.fr.md](README.fr.md) (the same sections and the same facts, in French).*

A Moodle **admin tool** for the French VAE (*validation des acquis de l'expérience*, the accreditation of prior
experiential learning). It lets professionals engaged in a VAE build, as their work goes on, a dated, attributed and
verifiable record of the competencies they have acquired in work situations. The VAE is not an exam: the plugin helps
to build and shape the dossier; **the jury decides**.

- **Source code**: https://github.com/jeanlouismartinez/moodle-tool_vae
- **Bug tracker (public)**: https://github.com/jeanlouismartinez/moodle-tool_vae/issues
- **Documentation**: this file, and three pages in English: [installation and first setup](docs/en/installation.md),
  [personal data](docs/en/privacy.md), [external services](docs/en/external-services.md). The other documents are in
  French (see "Documentation").

Displayed name "VAE - pathway" ("VAE - cheminement" in French); component `tool_vae`; repository `moodle-tool_vae`
(Moodle's convention `moodle-{plugintype}_{pluginname}`). Up to version 0.10.1 the component was called
`tool_referentiel`, a name that referred to the plugin of Jean Fruitet (https://github.com/jfruitet/moodle_referentiel):
older entries of the [changelog](CHANGELOG.md) keep that name.

In this file, the person who prepares a VAE is the **candidate** (*vaeiste*, the word of the French screens); the
**manager** (*responsable VAE*) follows candidates; the **direction** reads every dossier; the **administrator** sets
the plugin up. The words of the VAE are explained in the "Glossary" at the end.

**Why "pathway".** The VAE turns a proven competency into a qualification through reflection on one's practice: training
goes from knowledge to experience, validation goes from experience to knowledge. The plugin accompanies that
**pathway**; the dossier is only its end product. The conception of the VAE that guides the project is described by the
author at https://martinez.pro/mod/page/view.php?id=84 and https://martinez.pro/mod/page/view.php?id=85. The user
guides (candidate, manager, direction, administrator) are published on the author's website, not in this repository.

## Status

- **Version 2.5.03 (Moodle 5.3), build 2026112200, maturity beta** (`version.php`). The release reads
  "generation . Moodle branch": 01.5.3 was the first release sent to the plugins directory; 2.5.03 is the second
  generation, for Moodle 5.3 only. What Moodle compares to decide an upgrade is the build number, not the release.
- **Never used by real candidates to date**; its behaviour under a real load has never been verified.
- **Checked by**: the PHPUnit and Behat suites of the plugin, run locally on Moodle 5.3.0 with PostgreSQL 17 before each
  build (the changelog says what each build verified and what it did not); the continuous integration on GitHub
  (`.github/workflows/moodle-ci.yml`, `moodle-plugin-ci`) on PostgreSQL and MariaDB; **MySQL was added to it on
  22 November 2026 and has not run yet**. On 23 November 2026 the package of this build was installed on new local
  sites with MySQL 8.4 and MariaDB 11.4: the PHPUnit suite passes on both without any notice; the tests skipped there
  need PostgreSQL (a trigger) or what the package leaves out (the development files, the French strings). Behat ran there on
  22 November 2026 with an earlier package of this build (without JavaScript after the corrections of that day, with
  JavaScript before them), not with this one. The two unexpected `debugging()` notices that an earlier local run on
  MySQL showed came from two tests of `tests/observer_test.php`, which ran the queue of messages in the PHP process that
  had just deleted an account, with rights read before the deletion still in cache; the two tests now run the queue as a
  new cron process would, and the plugin no longer tries to send a pending declaration about a deleted account, whatever
  the rights in cache (see the changelog).
- Behat runs axe-core accessibility checks in a real browser (five scenarios of `tests/behat/accessibility.feature`):
  the three tabs of the organisation page, "Getting started" and the guide for candidates. A PHPUnit test counts the
  database reads of the three tabs as the data grows (`tests/organisation_load_test.php`); the hot spots it found are
  described in the changelog, none is fixed.
- **Developer debugging**: the Behat test site of Moodle runs at the developer debug level (`lib/behat/lib.php`), and
  PHPUnit reports every unexpected `debugging()` call (`lib/phpunit/classes/advanced_testcase.php`). On 22 November
  2026, before the last corrections of this build, a new local site at the developer debug level was walked through
  over HTTP: the 45 pages of the plugin for four roles, and 62 forms, uploads and downloads sent, without any warning,
  notice or `debugging()` call. A session in a browser with developer debugging on a real site is **not verified**.

## What it does, role by role

Moodle already has a full competency subsystem. This plugin **feeds and extends** `core_competency`; it never
reimplements it.

- **The candidate** declares an activity on a competency of a certification record, with a period of practice and a
  setting (a core user evidence is created); writes the sections of the *dossier de faisabilité* (drafts saved on their
  own while typing, versions compared and restored), deposits it as a dated milestone, and answers the questions a
  manager asks before attesting; attaches supporting pieces, positions themself on each element of the record,
  describes work situations, and exports the dossier as an archive for the jury, or downloads an editable Word or
  OpenDocument copy. The candidate reads the dossier on "My pathway" and in a one-page guide.
- **The manager** follows candidates through a role given in each candidate's user context (by cohort); reads their
  dossiers, attests a declaration, asks for a precision before attesting, reads the deposits and writes an opinion on
  one; may evaluate a competency (optional role) and annotate situations with the candidate's consent; asks a colleague
  to withdraw an attestation. The tab "Followed candidates" lists the candidates with numbers, never a verdict.
- **The direction** reads every dossier (`viewany`), exports it, lists the declarations that await an attestation, may
  certify a block as the positioning of the structure (optional role) and list the certifications to renew, produces
  the archive of the structure, and reads the configuration of the scheme on the tab "Organisation".
- **The administrator** imports the certification records (France compétences export uploaded or fetched by the
  server, or a CSV file), creates the roles from the presets, sets the settings, backs up a dossier, reimports an
  archive, declares which record replaces which, names the referents of the blocks and the addresses of the rules of
  validation.
- Each person is notified of what concerns them, through Moodle messaging; **no message reproduces the narrative**.
- The dossier is **exported as a frozen archive for the jury**, with a printable PDF and a manifest of SHA-256 hashes,
  that another site can import back after checking the hashes, under the conditions said below.

## What it does not do

- **Numbers, never a verdict.** No screen says that a dossier is complete, ready or admissible; no rate, no
  percentage. The coverage table shows the candidate what has been declared, without concluding.
- **It does not replace the certifier.** No admissibility check, no pre-assessment, no opinion on the likely outcome.
  The positionings the structure records (evaluating a competency, certifying a block) are its own internal acts:
  they are neither a validation nor a forecast. **Only the jury validates, outside the platform.**
- It does not edit the certification framework: imported, never changed by the plugin.
- It embeds no AI model. The optional rewording help is closed by default and goes through the AI providers of Moodle
  core, with a confirmation at each sending.
- Its only outgoing request of its own is fetching the France compétences export, on an administrator's click, from
  two hosts written in the code. It never visits an address typed by a user (see
  [external services](docs/en/external-services.md)).

## Requirements

- **Moodle 5.3 LTS**: `requires` is 5.3.0 and `incompatible` is 5.4, so Moodle refuses the plugin on 5.4 and later.
  Only LTS releases are supported, one per package (reason under "Compatibility").
- **PHP**: what Moodle 5.3 requires (8.3.0 or later, `admin/environment.xml` of Moodle 5.3); tested on PHP 8.3.
- **Database**: through Moodle's database API only. Run locally on PostgreSQL 17; in the continuous integration on
  PostgreSQL 17 and MariaDB 11.4; **MySQL 8.4 added to the continuous integration, not run yet**. The lowest versions
  are those of Moodle 5.3 (PostgreSQL 17, MariaDB 11.4, MySQL 8.4).
- The **competency subsystem enabled** (*Site administration > Advanced features > Enable competencies*). Without it
  the plugin shows an explanation and stops.
- **Cron** running: messages, the purge after a deleted account and the scheduled tasks run from Moodle's cron.
- **No other plugin** is required (`version.php` declares no dependency); the plugin uses core subsystems only
  (competencies, cohort roles, messaging, files, data privacy, and AI when the site opens the rewording help). Nothing
  is to be installed with Composer.

## Installation

1. **From a ZIP file**, the ordinary way: *Site administration > Plugins > Install plugins*. The root folder of the
   ZIP must be named `vae`. A ZIP made by GitHub ("Download ZIP" or the archive of a release) opens on a folder named
   `moodle-tool_vae-<branch or tag>`: type `vae` in the advanced field "Rename the root directory" of the installer, or
   unzip by hand into `<moodle>/public/admin/tool/vae` (the folder must be named `vae`).
2. **Or with git**:
   `git clone https://github.com/jeanlouismartinez/moodle-tool_vae.git <moodle>/public/admin/tool/vae`.
3. Visit *Site administration > Notifications* to install (or run `php admin/cli/upgrade.php`).
4. **Steps after the installation** (they are not standard, nothing is created without the administrator):
   open *Site administration > Competencies > VAE > Getting started*, which lists the next steps in order, each with its
   state, and changes nothing; create the roles from the page *VAE roles*; attach managers to their candidates by
   cohort (core *Cohort roles management*); import the certification records; review *VAE: settings*; publish the site's terms of
   use and data protection information.

Details: [docs/en/installation.md](docs/en/installation.md) (English) and
[docs/INSTALLATION-PAS-A-PAS.md](docs/INSTALLATION-PAS-A-PAS.md) (French, step by step, for beginners).

The package is made from the repository by `git archive`: the development files (`scripts/`, `Makefile`,
`phpcs.xml.dist`, the continuous integration files) and the working documents of `docs/` stay in the repository and
are left out of the package by `.gitattributes` (`export-ignore`). Of `docs/`, the package keeps `docs/schema` (read
at run time) and the documents this README links to. **The package ships the English strings only** (`lang/en`): the
French strings (`lang/fr/tool_vae.php`) stay in the GitHub repository, for the sites that want them, and the project
owner intends the French of the plugin to come from AMOS, the translation tool of Moodle (not done to date).

## Roles and capabilities

**No capability touching the data has a default archetype**, except `linkcourse`, given to the Manager archetype
(`db/access.php`). This is deliberate: a capability given to the `user` archetype at user-context level would be held
by every user in the context of all the others. The plugin proves who follows whom in PHP (`\tool_vae\local\access`),
never by an inherited capability. **The plugin assigns no role**: who holds a role, and where, is decided in Moodle.

| Capability (`tool/vae:…`) | Level | What it opens |
|---|---|---|
| `declare` | user | declare an activity, on oneself |
| `viewown` | user | read one's own dossier |
| `draftown` | user | write the sections of one's own dossier and their versions |
| `positionself` | user | position oneself on the elements of one's record |
| `export` | user | export a dossier as an archive: with `viewown` on one's own, with `viewany` on another's; never alone |
| `useai` | user | ask for a rewording of a text one wrote (only once the setting `aiopen` is open) |
| `viewassigned` | user | read the dossier of a candidate one follows (role in the candidate's user context) |
| `attest` | user | attest the declaration of a candidate one may read |
| `readdeposit` | user | read the deposits of a dossier one may consult, never its drafts or versions |
| `writeopinion` | user | write an opinion on a deposit of a candidate one follows; needs `readdeposit` too |
| `evaluate` | user | evaluate one competency of a candidate one follows; never on oneself |
| `annotate` | user | annotate the situations and self-positioning of a candidate one follows, with their consent |
| `draftview` | user | read the sections and versions of a dossier one may consult |
| `identityview` | user | read the identity and status of a dossier de faisabilité, on top of reading it |
| `withdrawany` | user | withdraw any attestation on a dossier one may read |
| `viewany` | system | read every dossier, and the configuration of the scheme |
| `certify` | system | certify one block as the positioning of the structure; needs `viewany`; never on one's own dossier |
| `issuestructure` | system | produce the archive of the structure, and authorize a manager to produce it; needs `viewany` |
| `import` | system | import certification records, and remove the row of a record imported by mistake |
| `importdossier` | system | reimport an archive, and back up any candidate's dossier into one's own private files |
| `linkcourse` | course | attach an imported record to the competencies of a course (Manager archetype by default) |

Rules that the table does not show:

- **`attest` is not enough on its own**: attesting needs reading the dossier, by following the candidate or holding
  `viewany`. **`export` is never enough on its own**: a manager who follows a candidate reads the dossier, does not
  export it. **`certify` needs `viewany`; `evaluate` needs following the candidate.**
- **Never assign the manager role at site level.** `viewassigned` held at site level opens no dossier
  (`access::follows()`), and `readdeposit`, `draftview` and `identityview` held without `viewany` or following open
  nothing either. Reading every dossier has its own capability, `viewany`, for the direction.
- **The core capabilities.** The plugin reads the imported records without `moodle/competency:competencyview`, but the
  core asks for it when a declaration is saved, so the candidate must hold it (by default it comes from the
  "Authenticated user" archetype); the declaration page says so instead of opening the form. Importing asks
  `moodle/competency:competencymanage`, attaching a record to a course `moodle/competency:coursecompetencymanage`, and a
  backup or a reimport `moodle/competency:userevidencemanage` on the candidate.

**The page "VAE roles"** (*Site administration > Competencies > VAE > VAE roles*, `roles_install.php`, for whoever
holds `moodle/role:manage`) creates the roles from the presets shipped in `presets/roles/`: three base roles (`vaeiste`,
`vae_responsable`, `vae_direction`) and two optional ones (`vae_responsable_evaluateur`, the manager plus `evaluate`;
`vae_direction_certificatrice`, the direction plus `certify`). Their names come from the language strings: in English
"Vaeiste", "VAE manager", "VAE direction", "VAE evaluating manager", "VAE certifying direction". Nothing is created when
the page opens nor at installation: a role is created after a confirmation that lists its rights; an existing role
(same short name) is never changed; `import`, `importdossier`, `identityview`, `useai`, `withdrawany` and `linkcourse`
are refused even if a preset file were edited; `evaluate`, `certify` and `issuestructure` are only in the optional
presets. The roles can also be imported by hand (*Define roles > Import*) from the files of `presets/roles/`. The role
of the candidate is created with the core role archetype "Student" chosen in the role form, not by copying a student
role, which would copy rights such as "View user profiles" on the whole site. Its short name `vaeiste`, without the
`vae_` of the four others, is kept on purpose (decision of the project owner, 22 November 2026): the role exists only on a
site where an administrator asked for it on this page and confirmed, and the page shows a role that already has this
short name as existing, never creates it again and never changes it; renamed, the roles already created would be out of
reach of the page and of the upgrades of the plugin, which look a role up by its short name.

**Attaching managers to candidates.** A manager reads only the dossiers of the candidates they follow. The core tool
*Cohort roles management* (`tool_cohortroles`) gives a role to a person in the user context of each member of a cohort and keeps it
up to date from cron: one cohort per establishment, a role "VAE manager" (assignable in the user context only), then
the manager, the role and the cohort on *Cohort roles management*.

## Features in detail

### Getting started, the "VAE" link, the guides

- **Getting started** (`setup.php`) lists the steps of putting the plugin into service in the order of the
  installation guide, with their state ("Done", "To do", "For you to check"). **It writes nothing.** What cannot be
  read (roles assigned, cohorts attached, terms of use published) stays "For you to check".
- **"What you can do now"**: at the top of "My pathway" (the candidate) and "Followed candidates" (the manager), a box
  says what is possible now, with a link: declare an activity, answer a precision, take up the dossier de faisabilité,
  position oneself; for the manager, a number of declarations to attest, of answers to read, of deposits without an
  opinion. Never a progress rate or a verdict; it writes nothing and names nobody.
- **The "VAE" link in the top navigation bar** leads to `organisation.php`. It is shown only to whoever may open a tab
  of that page (the candidate, the manager who follows at least one candidate, the direction that holds `viewany`), never
  while "logged in as" another user.
- **A "VAE" category** under *Site administration > Competencies*, "Getting started" first; the 20 settings of
  *VAE: settings* are grouped under seven headings (attestations and reminders; dossiers; supporting pieces; AI
  help; editable copies; positioning; import of the records).
- **A one-page guide per role** (`guide.php`): under the title of each tab, a "Help" link opens the guide for
  candidates, managers or the direction, served by the plugin from its language strings (English only in the package); a
  role's guide opens for whoever may open that role's tab. It describes what is open on a site as installed, and gives
  no verdict.
- **Not verified**: the "VAE" category seen by an administrator or a direction on a real site (proved by the tests); the
  guide for the direction read over HTTP; the texts of the guides, to be read again by a VAE practitioner.

### The dossier de faisabilité, deposits, opinions, precisions

- **Sections with fields** from annex II of the French order of 3 July 2025: the plugin keeps the birth name, date of
  birth, nationality, highest level of education, level and titles of certifications obtained, status and collective
  agreement identifier; it reads the first names, usual name, email, address, city, country and phone from the Moodle
  account without copying them; the gender, optional in the annex, is not taken. Every save is a version, comparable like
  those of the texts. Reading by a third party needs `identityview`, separate from `draftview` and given to no
  archetype: reading a candidate's texts does not open their date of birth. **No completeness check**: the asterisk says
  what the official form requires, and a section is saved even incomplete. **Limit**: the lists of the annex were read
  by a screen reader, not copied from the Journal officiel; to be compared with the text before any use in production.
- **Text sections** have drafts saved automatically while typing (the external function `tool_vae_save_draft`, called
  through `core/ajax`) and versions saved on demand, compared and restored.
- **Deposit**: a dated milestone, hashed (SHA-256), of what the candidate has **saved**, never of a draft. It is not a
  lock: the candidate keeps writing, and a new deposit is a new numbered milestone. The managers who follow the
  candidate **and** hold `readdeposit` are notified (name and number, nothing of the sections) and read that milestone,
  never the drafts or the versions.
- **Opinion**: a manager who follows the candidate and holds `writeopinion` (with `readdeposit`) writes free text on one
  deposit, dated, hashed, never rewritten; the candidate reads it with its author's name and is notified without the
  text. The opinion stays attached to the deposit it comments. **The plugin does not say whether an opinion is
  favourable**, and it is not the signature of the official form.
- **Precision**: a manager who follows the candidate and may attest asks a question on a declaration before attesting;
  the candidate answers once, with words. Both texts are dated, hashed and never rewritten; the candidate is notified
  that a question waits, without the text. Neither a lock nor a verdict: the attestation does not wait for the answer.
- **Reminder**: the administrator may set a number of days (`overduedays`, zero = off) after which the managers who
  follow the candidate and may attest are reminded, once, that a declaration still waits; the message names the
  candidate and a number, never the narrative. The page "Declarations awaiting an attestation" (`viewany`) lists them
  by candidate, the oldest first, with the **number** of managers who would be notified (they follow the candidate, may
  attest, and their account is neither suspended nor deleted), never their names; a zero says nobody would be notified,
  and a box keeps only those candidates. To make sure someone is always notified, attach each candidate to a manager by
  cohort.
- Deposits, opinions, questions and answers are not in the export archive; they are in the export of personal data of
  Moodle, which can be read but not reimported.

### Archive, copies, backups

- **The archive for the jury** (`export.php`, capability `export`): the declarations, the attestations, the record, a
  `dossier.pdf`, the text sections of the dossier de faisabilité (`faisabilite.json`, latest saved version) and the
  supporting pieces, under a manifest of SHA-256 hashes. **Never the identity, the status or the contact details**: the
  archive keeps opaque identifiers. The sections go into the archive only for whoever may read them on screen. At
  reimport, a section absent from the site comes back as version 1 ("Restored from the archive …"); **a section already
  written on the site is never overwritten**; an archive without `faisabilite.json` reimports as before. The format is
  described by the JSON schemas of `docs/schema/` (keys and file names in French, on purpose).
- **An archive from another site.** Each site numbers its competencies the first time a dossier leaves it (archive,
  copy, backup) or an archive is taken in, and the candidate's account receives its identifier at its first
  declaration, export, copy or backup. An archive from another site is taken only if the receiving site has not already
  numbered differently the competencies it names, and if the candidate's account has no identifier there yet; otherwise
  it is refused whole, nothing is written, and the message gives the number of competencies in question and names the
  first ten, sorted by code. The export and backup screens say these reserves. The plugin never matches two competencies
  of two sites by itself: that is a judgement; a screen of correspondences validated by a person, and a complete archive
  for oneself, are decided, **not built**.
- **A line out of format** (a competency rank "c7" instead of "C7", left by a version before 0.46.0 or written in the
  database; a new declaration on such a rank is refused) does not block the export nor the backup made from the screen:
  the archive is produced with a warning that names the declaration and a mention in its `LISEZMOI.txt`. It **cannot be
  corrected from Moodle** (the archive takes the rank written in the declaration, not the idnumber the competency bears
  today); the warning comes back at each export while the declaration exists, and such an archive cannot be reimported
  as it is (the editable copy remains possible). The log of each export keeps the number of lines in question. A piece
  whose file is lost or altered, and a dossier too big to be read back, stay refused.
- **Editable copies** (`document.php`): the candidate (and the direction) downloads the dossier as `.docx` or `.odt` to
  read and correct it, with a sentence at the top that says it is **not** the archive delivered by the platform: the
  sections of the dossier de faisabilité first (latest saved version), then the declarations and the attestations. Same
  capability as the archive; the request is a POST with the session key; each download leaves an event (number of
  declarations, format, hash of the data, never the text). Identity, contact details and status are in it only for the
  candidate and whoever holds `identityview`; the sections only for whoever may read them on screen (the direction that
  holds only `viewany` and `export` receives the copy without them); the titles of the pieces only for whoever may open
  the pieces. The dates are in the candidate's time zone, whoever reads.
- **Weekly copy** (setting `copyenabled`, off by default): on Monday night, a task writes a dated copy into the
  candidate's own private files (folder `/vae/copies/`), only if the dossier changed, and keeps the last two. A deletion
  request erases them with the dossier. Its legal basis and retention are to be set by the site before it is switched on
  ([T-84](docs/T-84-conformite-rgpd.md), § 6).
- **Back up on Moodle** (`importdossier`, and `moodle/competency:userevidencemanage` on the candidate) puts a dossier into
  the administrator's private files. Every dossier is backed up automatically when the plugin is uninstalled **through
  the administration page or `admin/cli/uninstall_plugins.php`**, never when the folder of the plugin is deleted first
  (then nothing is saved and the tables are lost). It is reimported after reinstallation, once the records are imported
  again, except when it carries a line out of format, when a dossier left the site since on the same competencies, or
  when the candidate's account received a new identifier since. The backup is the normative archive: it does not hold
  the deposits, opinions, precisions, earlier versions of the sections, the reflective journal nor the preparation of the
  validation dossier; it holds the positionings of the structure, for a candidate who still has a declaration. A
  candidate with no declaration has neither archive nor backup (`db/uninstall.php` says so). **The roles created by the
  page "VAE roles" stay after the uninstallation, with their assignments, but emptied of their `tool/vae:*` rights** (the
  core removes the rights of the capabilities that disappear), and a reinstallation does not fill them again (the page
  never changes an existing role). To restore them: delete them (*Site administration > Users > Permissions > Define
  roles*), then, the plugin installed again, create them from the page "VAE roles" and assign them again; or give their
  rights back by hand.

### Supporting pieces

A candidate may attach to a declaration a **file** (PDF, ODT, DOCX, JPEG, PNG) or a **link**, with a nature from a
closed list (payslip, employment contract, employer's certificate, diploma or training certificate, job description,
other) and an optional title. **The plugin does not read a piece, does not judge it and does not say it is enough.**
**Closed by default** (`piecesopen`): to be opened only once the site has written how long such files are kept.

- The file is chosen in **Moodle's file picker**. Its type is read **from its content**, never from its name; **the
  original name is never kept** by the plugin (the file is stored, served and archived under an identifier), but the
  file picker keeps it in the person's draft area until the form is sent, and the core antivirus reads it.
- Limits: 5 MB per file, 5 active pieces per declaration, 30 MB per candidate (settings below these ceilings); macros,
  scripts, embedded objects and outside relations are refused in a DOCX or an ODT, and actions that start on opening in
  a PDF when they are readable. **A list of refusals, not a guarantee.**
- The core antivirus is called; **with no antivirus configured, files are refused**, unless the administrator accepts it
  in writing (`piecenoantivirus`). A SHA-256 hash is taken on reception and compared at each export. A link has no
  hash (what is at an address can change); it is flagged when it carries a query or names an address that looks
  private.
- **Who reads**: the candidate, whoever may attest the candidate, the site administrator. Whoever may only read the
  dossier sees the list (nature, type, size, hash, dates), not the title, and opens nothing. Download is always forced.
- **Attestation**: the signature is refused if the pieces changed since the screen was shown; the signed list is kept
  with the hash of each file and a box "I took note of the supporting pieces attached to this declaration." (a
  declaration, not a fact). The screen says how many files the signatory opened, and that **opening is not reading**. A
  piece added after the signature is marked so, and the managers who follow the candidate are notified (nothing of the
  piece).
- **Removal** is always possible for the candidate, before or after the attestation: the file, title and address are
  erased; nature, type, size, hash and dates stay. The screen says what removal cannot reach: a copy already
  downloaded, the recycle bin of the platform until it is emptied, an archive already given to a jury.
- **In the archives** (`pieces.json`, schema `docs/schema/pieces.schema.json`): the archive the candidate exports and
  the backup hold the files and the addresses; the export made by a third party, the PDF and the editable copy hold the
  list only (the title of a piece follows the files). At reimport, the pieces come back through the same checks as a
  person's upload; an archive that fails them is refused whole, by the name of the piece; **if pieces are closed on the
  receiving site and the archive has pieces to restore, it is refused** (open, import again, close). A plugin older than
  0.39.0 reimports the declarations and loses the pieces without saying so.
- **What the plugin cannot do**: check that a document names nobody; see what a document keeps inside it (author,
  dates, position of a photograph: metadata are not removed); know whether a manager read a piece.

### Rule of validation, "outside my activity", referents

- **Addresses of the rules of validation** (*Competencies > VAE > Rules of validation: addresses*): up to five per
  record (the page of the national register, the order of the diploma), each with a short title and, if the
  administrator says so, the day the page was checked. The plugin copies, reads and applies **no rule** (the national
  export does not give the rule of validation in VAE). Shown on the dossier in one box above the whole coverage, never on
  the line of a block, with a fixed sentence (the plugin does not apply the rule, does not say whether the dossier meets
  it, does not check the addresses; the jury decides). The PDF and the editable copy write the addresses as text with
  the same sentence; `referentiel.json` carries them (`regle_validation`). **Closed by default** (`refopen`): to be
  opened only after a VAE practitioner has read the sentence and its place.
- **"Outside my activity"**: a candidate may say that a competency or a block does not belong to their activity, with an
  optional one-line note. A declaration, not an exemption: nobody signs it, it is counted nowhere, triggers no message
  and changes nothing in the coverage; the jury decides whether a competency not practised counts. Shown in a box apart
  from the coverage, read by the candidate, whoever follows them and whoever reads every dossier; only the candidate
  sets and removes it. In the archive as `hors_activite.json`, reimported without overwriting. **Closed by default**
  (`scopeopen`): what was said stays readable and can be removed.
- **Referents of the blocks** (*Competencies > VAE > Referents of the blocs*): a rule of the site, written once, that
  decides who is notified first of a declaration on a block (and of a piece added after a signature), among the managers
  who follow the candidate: the referents of the block and of the whole record if they are among them, **all the
  managers otherwise**. It gives and takes away no right. The reminder and the other messages are not routed. On *My
  candidates*, a referent may count the declarations of one of their blocks; the list of candidates does not change.

### AI rewording help

A candidate may ask for **another wording of a text they wrote themselves** (a text section of the dossier de
faisabilité, the preparation of the validation dossier, the situation of a declaration); never the reflective journal,
never the identity. **Closed by default** (setting `aiopen`): to be opened only once the legal basis, the register, the
retention period and the contract with the provider are written, and once a VAE practitioner and the keeper of the
register have read the instruction sent and the text of the policy.

- **For the button to show**: the setting open; the capability `tool/vae:useai`, given by hand (no archetype; **a site
  administrator passes every capability check, so the real lock is the setting**); **exactly one AI provider of Moodle
  enabled for generating text** (with two, the core would try them one after the other).
- **Three screens, always by POST** (`ai.php`): the **site's AI policy**, the core's text, accepted once for the whole
  site; the **confirmation, at each sending, which cannot be switched off**, showing the exact text, the provider's
  name, the number of characters and what is kept, and reminds to name nobody; the **result**, next to the original, to
  be read again. **Nothing is saved by the plugin**: the candidate copies the proposal, or not, and saves it themself.
  The button opens `ai.php` in a new tab, so the text being written is not lost; each confirmation carries a one-time
  token and the mark of the provider named: reloading, a double click or a provider changed in between send nothing.
- **Limits**: 3,000 characters by default (setting from 500 to 6,000), 25 calls a day by default (1 to 100), a pause of
  30 seconds between two calls (fixed in the code). The plugin counts the calls in `tool_vae_aicall` (who and when,
  never the text), kept two days and removed by the daily task.
- **What the provider receives**: the text between two random markers, after the plugin's fixed instruction (the English
  string `aiprompt` of the plugin, which asks for a rewording in clear French; it has not been approved again since it
  became a language string); the system instruction the administrator set in the AI provider settings of Moodle, if
  any; and, for the providers of the core that send one, an identifier the core derives from the account
  (`hash('sha256', siteidentifier . userid)`).
- **What stays in the core**: the text sent and the answer are kept by Moodle in its own tables, in the site's context,
  in the candidate's name. **The plugin erases none of it.** Moodle empties them when a person approves a data deletion
  request, provided the site creates those requests by itself (`automaticdeletionrequests` of `tool_dataprivacy`) and its
  data registry does not define the purpose and category of the site; otherwise they must be erased by hand. The
  confirmation screen says which case applies. Details: [personal data](docs/en/privacy.md).

### Importing the certification records

- **One screen, two ways** (*Competencies > VAE > VAE - pathway > Import records from France compétences*, capability
  `import`): type up to twenty record numbers (`RNCP12345`, `RS1234`), then **upload** the ZIP of the export downloaded
  from data.gouv.fr (Moodle's file picker), or click **"Fetch the latest export"**: the server fetches it, at that click
  only. The plugin reads the ZIP without extracting it, shows each record's full published title, what Moodle already
  holds and what the import will do, and **writes nothing before the confirmation**. A refused record (number absent,
  record without a block, unexpected block code) is refused by name without blocking the others. Source, licence,
  attribution and date are not typed again: the date is that of the export, read from the file name; an export older
  than what Moodle holds is flagged and imported only if a box is ticked. The full title of a record longer than 100
  characters is kept nowhere: the framework carries the number as its name. The authorship and the date of the record
  are shown wherever the framework is presented; no competency title is reworded.
- **A CSV file** of four columns (special case), and the command-line script `cli/convert_fiche.php` (a Moodle CLI
  script, run on the server of the site, absolute paths) as a fallback.
- **Not verified**: the "Fetch" way has never been run against the real data.gouv.fr (its tests use a simulated
  response); the screen has never been clicked by a person on a real site.
- **A record imported by mistake**: hide it in Moodle, delete the framework in Moodle if Moodle accepts it, then remove
  the row of the record in the plugin ("Remove this row", capability `import`), offered only when Moodle no longer holds
  the framework, no framework carries the code, and no candidate left any trace on it (declaration, "outside my
  activity" mark, section of the validation dossier) nor the structure any positioning; the page says why when a trace
  or a framework of the same code stops it. A confirmation lists what goes with the row (licence and attribution,
  dates, addresses of rules, referents, marks, correspondences, successor); nothing is written into Moodle's frameworks,
  and the row cannot be recovered (importing the record again creates a new one).
- **Replaced records**: the administrator declares which record replaces which, sees what still holds the old one, and
  decides the correspondences competency by competency; the candidate carries a declaration over by copy, and it must
  be attested again. Nothing is moved by the plugin.

### The organisation page: three tabs, numbers, never a verdict

`organisation.php` has three tabs, **each under its own right**; the same test decides what is shown and what the
address accepts: a tab one may not open is refused, never replaced by another. The page reads no person's identifier
from the address (besides the tab, its only parameter is the block chosen on "Followed candidates", checked against the
reader's list); without a tab it opens on the first tab the reader may open, except for a holder of `viewany` who
declared nothing, who lands on "Organisation". Numbers, dates and names, never a rate, a grand total or a colour that
qualifies a person: only the line of a block adds up the declarations, pieces and attestations of its own line and of
its competencies, and the screen says so; the only coloured badge is the word "Missing" of the grid of rights.

- **"My pathway"** (the candidate, never while logged in as another user): for each competency of the records the
  candidate declared on, the declarations, pieces, attestations in force, the date of the last act, the courses, the
  course activities and the learning plans of the core that carry it. A third party reads a plan only under
  `moodle/competency:planview` or `planmanage` in the candidate's context (drafts under `planviewdraft` or
  `planmanagedraft`), never a core rating.
- **"Followed candidates"** (a manager who follows at least one candidate): per candidate, the records declared on, the
  number of declarations and of those awaiting an attestation, and the learning plans where the core's right allows.
- **"Organisation"** (`viewany`): the configuration of the scheme, **which shows no candidate**: per record, blocks,
  competencies, courses, activities, plan templates, the **number of learning plans that cite it** (exact, no
  threshold, drafts included: a small number can point to a person for whoever knows the site; risk accepted by the
  project owner on 4 November 2026), referents and addresses of rules; then a grid "Actors and powers", the roles that
  grant, remove or prohibit each right, the cohorts followed by a manager, and ten settings. **It shows, it sets
  nothing.**
- **Course activities**: "My pathway" says in which activity of which course each competency is worked on, and
  "Organisation" gives the number of activities per record and their list in a `<details>` element. The attachment of an
  activity to a competency is read (core table `competency_modulecomp`), never the rule the activity applies
  (`ruleoutcome`, `overridegrade`), no person, no grade. An activity hidden or restricted for the reader (`uservisible`
  of the core) stays listed without its name, type or link ("An activity that is not visible"); in a hidden course, the
  names are given only to whoever holds `moodle/course:viewhiddencourses`. The plugin does not test enrolment: whether a
  reader without a role in the course sees a name depends on the module and the roles of the site (decision of the
  project owner, 4 November 2026).
- **Actors and powers**: for each actor, the rights of the plugin they must hold and the roles of the site that give
  them: four actors with an account (candidate, manager, direction, administrator of the scheme) and three without
  (referent of a block, jury, certifier); three states per right (given, with the names of the roles; not given on
  purpose, "No role"; **"Missing"**, a right expected that no role gives). `evaluate`, optional, never shows a lack. The
  grid reads neither who holds a role nor overrides below the site: a right "given" is no proof that the right person
  exercises it.
- **Measured** (`tests/organisation_load_test.php`, 16 November 2026, PostgreSQL): "My pathway" grows by 3 database
  reads per record declared on (114 reads for 30 records), "Followed candidates" by 1 read per candidate followed (122
  reads for 100), "Organisation" by 1 read per distinct course and 2 per course carrying an activity (113 reads for 30
  such courses); the "VAE" link costs 1 read. The hot spots are described in the changelog; **none is fixed**.
- **Not verified**: the introductions and labels, to be read again by a VAE practitioner; accessibility in French and
  with another theme than Boost; the reading cost on MariaDB or MySQL, on the whole page and on a real large site.

### Evaluate a competency, certify a block: the positioning of the structure

The cycle **declare, evaluate, certify** happens inside the structure. **Only the jury validates, outside the
platform.** No setting opens the cycle: the two rights, given by hand, do.

- **Evaluate** (`evaluate.php`, `evaluate`): a manager who follows the candidate gives **one competency** a status (*to
  build*, *in the course of proof*, *sufficient for certification*, *withdrawn*) and a **written reason**, required
  (4,000 characters at most). Never on oneself; the administrator, who follows nobody, does not evaluate. The record
  must carry at least one declaration of the candidate.
- **Certify** (`certify.php`, `viewany` and `certify`): the direction gives **one block** (or the whole record when it has
  no block) the status *certified by the structure* or *withdrawn*, with the date of the decision (from 2000-01-01 to
  today) and a written reason; an optional "valid until" date (from the day of the decision to 2100-01-01; without it the
  certification never expires). **An alert, not a lock**: a competency of the block that is not "sufficient" is named,
  and a reason for the alert is required; the server computes it again when it saves. Never on one's own dossier, the
  administrator included.
- **Added, never changed**: correcting or withdrawing adds a line; the last line holds; a line identical to the last is
  refused. Each line carries the SHA-256 hash of what it says (it detects an accident; it is not tamper-proof); two
  sends at once on the same candidate and record go through a lock. The dossier shows the line that holds, a "History"
  link leads to the earlier ones, and the dossier flags competencies positioned after their block was certified.
- **Who reads**: whoever reads the dossier, the candidate included. The section "Positioning of the structure" opens on
  a warning that it is not a validation. A language test (`tests/positioning_test.php`) refuses the French words
  "valid-", "acquis", "candidat", "taux", "note" and "score" in the texts of these screens ("valid-" and "score" in
  English). The forms remind not to name any person accompanied
  (`anonymiseshort`); it is a written reminder, not a check.
- **The message to the candidate** (`positioningrecorded`): at each line, a message says only that a line was added,
  recalls that it is not a validation, links to the dossier and names the site's data protection officer for a
  rectification; **never the status, the reason or the author**. It leaves through the asynchronous task, only if the
  candidate may still open the dossier and a line still exists; it can be switched off in the notification preferences.
- **"To renew"** is computed when the page is read, at the reader's day, never written; renewing is adding a
  certification line. The list "Certifications to renew" (`renewals.php`, `viewany` and `certify`) shows each
  certification that holds and whose last day has passed, the oldest first; no total, no percentage, no colour.
- **"Positioned on the old record"**: when a record replaces another and the administrator decided the equivalent of a
  competency or a block, the new record shows, **read only**, the line that holds on the old one; nothing is copied.
- **Archives**: `positionnement.json` (schema version 0.1.0, `officiel` always false: current state and full history)
  is in the **archive of the structure** (`structure.php`, `issuestructure`, or a manager the direction authorized) and in
  the backup, **never in the candidate's archive, PDF or copies** (decision of the project owner: the word of the
  structure has its own booklet). At reimport, lines come back key by key (record, rank), all or nothing; a key that
  already has a line on the site is left; at most 5,000 lines.
- **Decisions of the project owner, who is also the data protection officer of the site the author runs, 5 November
  2026**: legal basis, the terms of use; information, a clause of the terms of use and the message above; retention,
  that of the dossier; a challenge goes to the site's data protection officer, with no screen
  ([T-84](docs/T-84-conformite-rgpd.md), § 8).
- **Not verified**: the words of the statuses, "to renew" and "valid until", to be read again by a VAE practitioner; the
  message sent by the mail server of a real site; the accessibility of these screens (no axe-core scenario).

### Self-positioning and work situations

- **Self-positioning** (`selfposition.php`, `positionself`, given to the role Vaeiste): the candidate says where they
  stand on each element of the record, at every level, by **one of four steps whose words the site chooses**
  (`selfposlabel1` to `selfposlabel4`; by default "I am discovering", "I practise with help", "I act on my own", "I master
  it and I pass it on"), with an optional justification (10,000 characters at most). Added, never changed; 200 lines at
  most per element; a send identical to the last line is refused; nothing is computed from it (no total, rate,
  threshold, sorting or gap). The page shows the word of the structure next to it, without a computed gap, and the
  counts of declarations and pieces. Only the candidate writes, on their own dossier; managers who follow them and the
  direction read. In `autopositionnement.json`: in the candidate's archive, the export by a third party and the backup,
  **never in the archive of the structure**. **Not verified**: the effect on real candidates (self-censorship).
- **Work situations** (`situations.php`, `situation.php`, `consent.php`): a title, a period, a setting, a text in
  versions, tags on the elements of the record, pieces. Only the candidate writes them, when the preparation of the
  validation dossier is open (`validationopen`). Bounds: 30 situations per record, 200 versions per situation, 5 pieces
  per situation, 200 annotations per target, 400 consents given per candidate.
- **"Where I stand"**: a panel says, for each element of the record, the number of situations and declarations that
  carry it, with a link to the reverse view; counts, never a total or a percentage.
- **Consent and annotations**: the candidate gives a manager who follows them a dated consent, by name, that can be
  withdrawn; the manager may then annotate (`annotate`, following and consent checked at each writing); only the
  author removes an annotation. Two messages without free text: `noteadded` to the candidate, `consentgiven` to the
  manager. The site administrator is refused on the situations and on the drafts, versions and history of the
  preparation of the validation dossier.
- **Archives**: `situations.json` and `pieces-situations/` in the candidate's archive and the backup, and in the export
  of a third party only when that third party may read the preparation of the validation dossier. Seven tables,
  declared column by column to the privacy provider. **Not verified**: the pieces of situations, the withdrawal of
  consent and the two messages on a real site.

### Two acts, and withdrawing an attestation

| Act | Who | Capability | Core API |
|---|---|---|---|
| **Declaration** | the candidate, on themself | `tool/vae:declare` | `create_user_evidence()` and attachment |
| **Attestation** | the manager | `tool/vae:attest` | **none**: a row of the plugin |

- The attestation, a row of the plugin, can be **withdrawn without destroying the candidate's evidence**: the dossier
  then says that an attestation existed and was withdrawn on such a date for such a reason, and that it no longer holds;
  **only if no other attestation holds on the declaration**, it adds once that the declaration was not changed and that
  the candidate may ask a manager to attest it again. Same rule on screen, in the PDF and in the copies.
- The narrative typed in the form is **plain text**: tags are removed. An archive reimported may carry a narrative in
  Markdown; the HTML format is refused everywhere.
- **Withdrawing**: by default only the site administrator withdraws. Three more ways: the signatory withdraws their own
  (setting `managerwithdrawown`, off by default); a coordinator withdraws any (`withdrawany`, given in the context of the
  candidates they follow; it does not dispense from reading the dossier); a manager who follows the candidate asks the
  signatory, who accepts (an ordinary withdrawal) or refuses; one request at a time, not kept once answered. The reason
  is required and taken from a closed list (signature error, content disputed, data deletion request); the candidate
  never withdraws an attestation on their own dossier; an attestation restored from an archive has no signatory on the
  site, so only the administrator and a holder of `withdrawany` withdraw it.
- **A record hidden in Moodle** (*Visible: No*) accepts no new declaration and no carry-over; the candidate reads a
  message written for them (title and code of the record, that it is hidden, to ask their manager), with no
  administration path. A form opened before the hiding gives back the text already written. Declarations already made
  do not change; restoring an archive and "outside my activity" are not concerned.

### Who is notified

Fifteen message types (`db/messages.php`), through Moodle messaging (notification and email by default; each person
sets them in their notification preferences), all sent from the site's "no-reply" user, so the candidate's email
address never travels in the headers:

- to the **manager** who follows the candidate and may attest: a declaration awaits their attestation (again after a
  withdrawal); a reminder after the number of days set (`overduedays`); a piece was added after a signature; to the
  manager who may read the deposits: a deposit was made;
- to the **candidate**: a declaration was attested; an attestation was withdrawn; a competency of the dossier left its
  record; an opinion was written on a deposit; a precision is asked; a line of positioning was added; an annotation was
  added;
- to the **signatory**: a colleague asks them to withdraw their attestation; the declaration they attested was destroyed
  (the candidate deleted the core evidence); to the **manager who asked**: the signatory refused;
- to the **manager** to whom the candidate gave consent: the consent was given.

**No message reproduces the narrative**: the candidate's name (for their manager), the reference of the competency, a
fixed sentence that says where to act, and a link to the dossier. **The candidate's name is never in the subject.**
Messages leave **from cron**, a few minutes after the act; the departure of a competency from its record waits one day
and is announced only if it still holds; the two messages of an erasure wait about fifteen minutes, so that a series of
approvals gives one per recipient. The direction (`viewany`) is not notified of each declaration. The notifications are
stored and erased by Moodle messaging, on its own schedule.

## Personal data

The Moodle Privacy API is implemented: the provider (`classes/privacy/provider.php`) declares **all 31 tables** of
`db/install.xml`, column by column, and links the core subsystems the plugin uses (competencies, messaging, files, AI).
Retention follows the existence of the candidate's Moodle account: the dossier is erased after the account is deleted
(at the first cron run that follows the deletion), or when a data deletion request is approved. **GDPR compliance is the
responsibility of the site that installs the plugin**: the site is the data controller, and the author of the plugin
receives no data. English summary: [docs/en/privacy.md](docs/en/privacy.md).

- **Two persons are in every attestation, and their rights go opposite ways.** Erasing the candidate erases their
  dossier, backups and copies. Erasing a manager does **not** erase the dossier: their identity is taken off the
  attestation, which is marked withdrawn and dated, so that the candidate keeps "attested, then withdrawn"; the candidate
  and the other managers are notified. Deleting a manager's account does not anonymise their attestations at once:
  Moodle, left to its settings, files a data deletion request that waits for approval; the attestations an archive
  attributes to that account (by an opaque identifier) are withdrawn at the first cron run after the deletion.
- **A positioning** is a third party's text about the candidate: erasing the candidate erases its lines; erasing its
  author empties the author's identifier but keeps the line and its text.
- **No field asks for a name**: the descriptions of work situations describe the professional's activity, never a
  person accompanied. A reminder, which cannot be hidden, sits on the free texts, and the manager reads before attesting;
  the plugin cannot know whether a first name is fictitious.
- For the candidate to take the dossier away before the account is deleted, the site must give them `export` (no role
  has it by default).

Three documents are provided as drafts, to be validated by the site's data protection officer. They **stay in French**
because they are documents of French law, written for French sites (the VAE is a French scheme): a summary in English
heads each of the first two.
[docs/AIPD-VAE.md](docs/AIPD-VAE.md) (data protection impact assessment),
[docs/MODELE-CONTRAT-USAGE-PLATEFORME.md](docs/MODELE-CONTRAT-USAGE-PLATEFORME.md) (model terms of use of the
platform), [docs/T-84-conformite-rgpd.md](docs/T-84-conformite-rgpd.md) (legal bases, retention, rights).

## External services

- **France compétences open data** (data.gouv.fr): fetched only when an administrator clicks "Fetch the latest export",
  from two hosts fixed in the code (`www.data.gouv.fr`, `static.data.gouv.fr`, `classes/local/fiche_fetcher.php`), over
  https; no personal data leaves; nothing scheduled calls it. Uploading the ZIP works with no network at all.
- **AI providers of Moodle core**, only if the site opens the rewording help and configures a provider.
- Details, and how a reviewer can test without network: [docs/en/external-services.md](docs/en/external-services.md).

## Architecture

- `classes/local/competency_gateway.php` is the **only** file of the plugin allowed to call the competency subsystem
  (`scripts/check-gateway.sh`, in the repository, fails otherwise); `classes/local/fiche_fetcher.php` is the only one
  allowed to open a network connection (`scripts/check-network-door.sh`, in the repository).
- **Output**: the pages are drawn by output classes (`classes/output/`, 104 classes) and Mustache templates
  (`templates/`, 104 templates), through the plugin's renderer. The HTML that the PDF library reads (the PDF of the
  archive, `classes/local/pdf_renderer.php`) is drawn by one template, `templates/pdf_blocks.mustache`, through
  `$OUTPUT->render_from_template()`; `classes/local/document_writer.php` writes the Word and OpenDocument files, which
  are not HTML, and has no template.
- JavaScript: three AMD modules (`amd/src/`). The autosave of a draft calls the external function
  `tool_vae_save_draft` (`db/services.php`), the only web service of the plugin, for AJAX only.
- No PHP superglobal is read; the three uploads use the file picker of Moodle.
- Observers only queue an ad hoc task (`db/events.php`, `classes/observer.php`, `classes/notification_observer.php`): the
  purge after a deleted account or evidence runs from cron, and the scheduled task `reconcile_orphans` catches up with
  what a task that never ran would have removed; a message is queued by its observer when the event names its one
  recipient, and otherwise its recipients are found at the first cron run after the event.

## Development and tests

The commands below need the GitHub repository: `scripts/`, the `Makefile` and `phpcs.xml.dist` are in it, not in the ZIP
package installed on a site.

```bash
git clone https://github.com/jeanlouismartinez/moodle-tool_vae.git
cd moodle-tool_vae
make doctor      # what is missing on this computer, and what it prevents
make validate    # every check that runs offline
```

- Checks of the project (`scripts/`): `check-gateway.sh`, `check-core-writes.sh`, `check-network-door.sh`,
  `check-secrets.sh`, `check-schema.mjs`, `check-doc-links.mjs`, `check-english-comments.mjs`, `codestyle.sh`.
- Tests, from the root of a Moodle 5.3 site where the plugin is installed:
  `vendor/bin/phpunit --testsuite tool_vae_testsuite` and
  `php public/admin/tool/behat/cli/run.php --tags=@tool_vae` (after the usual initialisation of the PHPUnit and Behat
  sites of Moodle). With the plugin installed from the package, the tests that need the development files or that read
  only French are skipped, and the French checks of the other tests are not made: `lang/fr` is not in the package.
- Code and comments are in English (`make english` fails on a comment written in French). Identifiers are in English
  (the classes of the feasibility dossier are `feasibility_*`, those of the correspondences between a replaced record and
  the record that replaces it `correspondence_*`), except French words of the domain that some names keep on purpose:
  `fiche` and `bloc` (the RNCP record and its blocs: `fiche_converter`, `for_fiches()`, `bloc_of()`), `vaeiste`,
  `reflexion` and `regard` (`reflexion_overview`, `selfpos_regard`); and the names that stored data or links point to:
  the database columns (`fichecode`, `ficherang`…), the values stored in them (the document type `faisabilite`), the
  table `tool_vae_passage` and the event `passage_changed` (named in the logs), the pages and their addresses
  (`faisabilite.php`, `passage.php`), and the export format, whose schemas keep French keys and file names
  (`faisabilite.json`). The text of
  the pages, forms and messages comes from the language files, with one exception: the title "Bloc " followed by the
  rank, which `classes/local/fiche_converter.php` (`SHORT_TITLE`) gives to a bloc whose published label is too long or
  carries markup, as the name of the competency it creates; it stays in French on purpose, "Bloc" being the official
  term of the RNCP data of France compétences, and `competency_gateway::label_of()` recognises that name to show the
  published label in its place. Every PHP, JavaScript, CSS and Mustache file carries the GPL v3 header; the shell scripts
  of `scripts/` do not.
  Comment the why, not the what.
- Test data: invented records only (`tests/fixtures/README-fixtures.md`).

## Documentation

- In English: this file; [installation and first setup](docs/en/installation.md); [personal data](docs/en/privacy.md);
  [external services](docs/en/external-services.md).
- In French: [README.fr.md](README.fr.md); [docs/INSTALLATION-PAS-A-PAS.md](docs/INSTALLATION-PAS-A-PAS.md) (first
  installation, step by step); [docs/FAQ.md](docs/FAQ.md) (questions and answers by role);
  [docs/IMPORT-FICHES-FRANCE-COMPETENCES.md](docs/IMPORT-FICHES-FRANCE-COMPETENCES.md) (importing the records);
  [docs/T-84-conformite-rgpd.md](docs/T-84-conformite-rgpd.md), [docs/AIPD-VAE.md](docs/AIPD-VAE.md),
  [docs/MODELE-CONTRAT-USAGE-PLATEFORME.md](docs/MODELE-CONTRAT-USAGE-PLATEFORME.md) (personal data);
  [docs/BRANCHES.md](docs/BRANCHES.md) (branches and supported Moodle versions);
  [docs/schema/LISEZMOI.md](docs/schema/LISEZMOI.md) (the export format).
- [CHANGELOG.md](CHANGELOG.md): what changed in each version (older entries in French).
- Screenshots: none in the repository; they are on the page of the plugin in the plugins directory.

## Known limits

- **Date picker**: three drop-down lists (day, month, year), fifty years back.
- **An archive from another site** is refused whole when the receiving site numbered a competency differently or
  already knows the account; a screen of correspondences validated by a person is decided, not built.
- **Texts to be read again by a VAE practitioner**: the words of the statuses, "to renew", "valid until", the
  introductions of the organisation page and of the guides.
- **Accessibility**: axe-core checks the three tabs of the organisation page, "Getting started" and the guide for
  candidates, not the other screens (dossier, declaration, evaluation, certification, import).
- **Supporting pieces** are closed by default until the site writes how long such files are kept.
- **Without JavaScript**, the file picker of Moodle falls back on the core's file manager; not tried.

## Compatibility: LTS releases only

A VAE dossier is built over months and read by a jury years later: the platform that holds it must still receive
security fixes that day. An LTS release has three years of them, an intermediate release eighteen months.

| Moodle | Status | Branch |
|---|---|---|
| 4.5 LTS | **package no longer maintained**, not to be installed on a real site | `MOODLE_405` |
| 5.0, 5.1, 5.2 | **never**: intermediate releases | none |
| 5.3 LTS | **supported** | `main`, followed by `MOODLE_503` |

`version.php` declares `supported = [503, 503]`, `requires` 5.3.0 and `incompatible = 504`. In Moodle, `supported` is only
a declaration (outside it the plugins page shows a warning); `requires` and `incompatible` refuse: an administrator who
upgrades the core to an intermediate release finds the plugin blocked rather than running where nobody tested it.
Neither package widens its range to swallow the other: announcing a compatibility nobody tests is worse than announcing
none. The Moodle 4.5 package is no longer maintained (decision of 27 October 2026); its branch stays readable. See
[docs/BRANCHES.md](docs/BRANCHES.md).

## Licence

**GNU GPL v3 or later**: see [LICENSE](LICENSE) and the header of each source file; the licence of Moodle itself.
Copyright holder: Jean-Louis Martinez, jeanlouis@martinez.pro (decision of the project owner of 28 October 2026: the
plugin stays under this licence).

The certification records are **data** published by France compétences under the *Licence Ouverte 2.0*. **None of it is
in this package**: the administrator uploads the export or has the server fetch it, and the data lives in the site's
database. The test files of `tests/fixtures/` are invented (codes `RS00000` and `RNCP99001`, labels written for the
tests), as `tests/fixtures/README-fixtures.md` explains.

## Glossary

| Word | What it means here |
|---|---|
| **Declaration** | What the candidate says they did in their work, attached to a competency of a certification record. It is written, then a manager **attests** it. |
| **Attestation** | The act of a manager who, having read the narrative, confirms the declaration. It is **withdrawn**, never changed; a new attestation is a new act. |
| **Certification record** (*fiche*) | The competency framework of a diploma or title (RNCP, RS), imported from the France compétences export (uploaded or fetched) or from a CSV file. |
| **Dossier de faisabilité** | Formerly "livret 1": the request for admissibility. The plugin helps to write it; it never says whether it is admissible. |
| **Validation dossier** | Formerly "livret 2": the dossier of evidence. Its preparation (sections, block by block) is in the plugin; the rest of it is not yet. |
| **Section** | A part of the dossier de faisabilité (aims, path, experiences…), of text or with fields (identity, status). |
| **Draft / version** | The draft is saved on its own and is not a version; a version is saved on demand, compared and restored. |
| **Deposit** | A dated and hashed milestone of the dossier de faisabilité as saved. The candidate keeps writing; a new deposit is a new milestone. |
| **Opinion** | A manager's free text on one deposit; never rewritten; the plugin does not say whether it is favourable. |
| **Precision** | A manager's question on a declaration not yet attested, and the candidate's answer; neither a lock nor a verdict. |
| **Positioning** | What the structure says, in writing, dated and attributed: where a competency stands (**evaluation**, by a manager who follows the candidate) or a block (**certification**, by the direction). Added, never changed; the last line holds. **Not a validation**: only the jury validates, outside the platform. |
| **To renew** | A certification whose optional "valid until" date has passed on the day one reads. Computed when read, never written. |
| **Supporting piece** | A file or a link attached to a declaration, with a nature from a closed list. The plugin neither reads nor judges it; it keeps the hash of a file and the list of those a signature covered. |
| **Hash** | Here, always the **SHA-256** of a content: it proves that a text or an archive has not changed. |
| **Archive** | The frozen export of the dossier, with its manifest of hashes and a printable PDF, for the jury; checked by its hashes; taken by another site under conditions; without the deposits, the opinions or the precisions. |
| **Follow** | Holding `viewassigned` in the candidate's user context, directly or by cohort: the only way to read another person's dossier without `viewany`. |

*Fil d'Argent — Jean-Louis Martinez, jeanlouis@martinez.pro*
