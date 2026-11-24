# Personal data

*English page of the plugin "VAE - pathway" (`tool_vae`). It summarises what the code does; the documents of French law
that a site needs (impact assessment, model terms of use, legal bases and retention) are in French:
[AIPD-VAE.md](../AIPD-VAE.md), [MODELE-CONTRAT-USAGE-PLATEFORME.md](../MODELE-CONTRAT-USAGE-PLATEFORME.md),
[T-84-conformite-rgpd.md](../T-84-conformite-rgpd.md). Back to the [README](../../README.md).*

**The site that installs the plugin is the data controller.** The author of the plugin receives no data: the plugin
sends nothing to its author, and its only outgoing request of its own carries no personal data (see
[external services](external-services.md)).

## What the plugin declares to Moodle

The privacy provider (`classes/privacy/provider.php`) implements the Moodle Privacy API: metadata, export and deletion.
It declares **all 31 tables** of `db/install.xml`, each column that holds data (a PHPUnit test,
`tests/privacy_coverage_test.php`, fails if a table or a column is not declared), and links the core subsystems that
hold data for the plugin:

- `core_competency`: the plugin makes the competency subsystem write the candidate's evidence (a declaration creates a
  core user evidence);
- `core_message`: the notifications, stored and erased by Moodle messaging on its own schedule;
- `core_files`: the supporting pieces, the archive that waits a day in the candidate's user context after an export,
  the weekly copies in the candidate's private files and the backups in the administrator's private files;
- `core_ai`: the text sent to the rewording help and the answer, kept by the core (below).

## What is kept, by category

| Data | Tables | Whose |
|---|---|---|
| Declarations, carry-overs, the candidate's opaque identifier | `tool_vae_evidence`, `tool_vae_carry`, `tool_vae_identity` | the candidate |
| Attestations and the pieces they covered | `tool_vae_attest`, `tool_vae_attest_piece` | the candidate and the signatory |
| Sections of the dossiers, drafts, versions, deposits | `tool_vae_draft`, `tool_vae_version`, `tool_vae_deposit` | the candidate |
| Opinions on a deposit, questions and answers | `tool_vae_opinion`, `tool_vae_request` | the candidate and the manager |
| Supporting pieces and who opened them | `tool_vae_piece`, `tool_vae_piece_open` | the candidate; the reader |
| "Outside my activity" marks | `tool_vae_scope` | the candidate |
| Positionings of the structure | `tool_vae_eval`, `tool_vae_certif` | the candidate and the author |
| Self-positioning | `tool_vae_selfpos` | the candidate |
| Work situations, versions, tags, pieces, links | `tool_vae_situation`, `tool_vae_sitversion`, `tool_vae_sitetag`, `tool_vae_spiece`, `tool_vae_slink` | the candidate |
| Consents and annotations | `tool_vae_consent`, `tool_vae_note` | the candidate and the manager |
| Calls to the rewording help (who and when, never the text) | `tool_vae_aicall` | the candidate |
| Register of the archives issued | `tool_vae_archive` | the candidate |
| Configuration rows that name the account who wrote them | `tool_vae_source`, `tool_vae_passage`, `tool_vae_referent`, `tool_vae_booklet_auth`, `tool_vae_obsolete`, `tool_vae_ref` | the administrator, the referent, the manager authorized |

The free texts are the candidate's narrative of their own work. **No field asks for the name of a person accompanied**;
a reminder, which cannot be hidden, asks not to name anyone, and the manager reads the text before attesting. The plugin
cannot know whether a first name is fictitious.

## How long

**The retention follows the existence of the candidate's Moodle account**: the plugin has no retention period of its
own for the dossier. Two short-lived records are removed by the plugin itself: the calls to the rewording help, kept two
days (`ai_assistant::KEEP`, removed by the daily task `reconcile_orphans`), and an exported archive, kept a day in the
candidate's user context (`exporter::LIFETIME`, removed by the hourly task `purge_exports`).

**After an account is deleted**, the observer of the event only queues an ad hoc task (`purge_deleted_account`); the
dossier is purged **at the first cron run that follows the deletion**. A task that fails is shown in the task logs and run
again by the task manager; the scheduled task `reconcile_orphans` (daily) removes what a task that never ran would have
removed. Until the task has run, the dossier is still in the database.

## Rights

- **Export**: through the data requests of Moodle (`tool_dataprivacy`). The candidate receives their dossier, including
  what is not in the archive (deposits, opinions, questions and answers); a signatory receives their attestations and
  the reference of the declarations signed, never the narrative; a manager who asked a question receives the question,
  the date and the hash of the answer.
- **Erasure**: erasing the candidate erases their dossier, backups and copies. Erasing a manager does **not** erase the
  dossier: their identity is taken off the attestation, which is marked withdrawn and dated, and the candidate and the
  other managers are notified. Erasing the author of a positioning empties the author's identifier and keeps the line
  and its text.
- **Deleting a manager's account** does not anonymise their attestations at once: Moodle, left to its settings, files a
  data deletion request (`automaticdeletionrequests`) that waits for approval (`automaticdatadeletionapproval` off by
  default); its approval anonymises the attestations. The attestations an archive attributes to that account are
  withdrawn at the first cron run after the deletion, without a request.
- For the candidate to take the dossier away before the account is deleted, the site gives them `tool/vae:export` (no
  role has it by default).

## What the plugin leaves to the core

- **The rewording help** (closed by default, setting `aiopen`): the text sent and the answer are kept by Moodle in its
  own tables, in the site's context, in the candidate's name. **The plugin erases none of it.** Moodle empties them when a
  person approves a data deletion request, provided the site creates those requests by itself and its data registry does
  not define the purpose and the category of the site; otherwise they stay and must be erased by hand. The core keeps a
  line of its register in every case (account number, date, provider, model, size of the exchange, success or failure).
  The confirmation screen says which case applies on the site.
- **The file picker** keeps the original name of a file in the person's draft area until the form is sent, or until the
  core removes draft files older than four days (`lib/filestorage/file_storage.php`), and the core antivirus reads it. The
  plugin itself stores the file under an identifier and never keeps the original name.
- **The events** of the plugin go into Moodle's logs with identifiers, never the narrative; their retention is the log
  setting of the site.

## Not verified

The complete path of an access request and of a deletion request on a real site; the effect of a site data registry that
defines the purpose and category of the site, read in the code of the core but not played on a real site.
