# Installation and first setup

*English page of the plugin "VAE - pathway" (`tool_vae`). The complete step-by-step guide, for beginners, is in French:
[INSTALLATION-PAS-A-PAS.md](../INSTALLATION-PAS-A-PAS.md). Back to the [README](../../README.md).*

## Before you start

- **Moodle 5.3 LTS** (5.3.0 or later of the 5.3 branch). The plugin declares `requires` 5.3.0 and `incompatible` 5.4:
  Moodle refuses it on 5.4 and later.
- **PHP**: what Moodle 5.3 requires (8.3.0 or later). Tested on PHP 8.3.
- **Database**: run locally on PostgreSQL 17; PostgreSQL 17 and MariaDB 11.4 in the continuous integration; MySQL 8.4
  added to the continuous integration on 22 November 2026, **not run yet** there (the package installed on 22 November
  2026 on new local sites with MySQL 8.4 and MariaDB 11.4: PHPUnit passes without any notice, Behat without JavaScript
  passes; see "Status" in the README).
- **No other plugin**, nothing to install with Composer.
- Take a backup of the site first.

## Install

1. **From a ZIP file**: *Site administration > Plugins > Install plugins*, then choose the ZIP. The root folder of the
   ZIP must be named `vae`. A ZIP made by GitHub opens on `moodle-tool_vae-<branch or tag>`: type `vae` in the advanced
   field "Rename the root directory" of the installer. The installer needs the folder `public/admin/tool/` to be
   writable by the web server; otherwise unzip by hand.
2. **By hand**: put the files in `<moodle>/public/admin/tool/vae` (the folder must be named `vae`), for example
   `git clone https://github.com/jeanlouismartinez/moodle-tool_vae.git <moodle>/public/admin/tool/vae`.
3. Visit *Site administration > Notifications*, or run `php admin/cli/upgrade.php` from the root of Moodle.

Nothing is created at installation: no role, no cohort, no record. Every function that writes something personal and
was not asked for is **closed by default** (supporting pieces, reflective journal, "outside my activity", AI help,
addresses of the rules of validation, preparation of the validation dossier, weekly copy).

## First setup, in the order of the page "Getting started"

All the pages of the plugin are under *Site administration > Competencies > VAE*. The page **Getting started**
(`setup.php`) shows the state of each step below ("Done", "To do", "For you to check") and changes nothing.

1. **Competencies enabled**: *Site administration > Advanced features > Enable competencies*. Without it the plugin shows
   an explanation and stops.
2. **Cron running**: messages, the purge after a deleted account and the scheduled tasks run from cron. "Getting
   started" reads the last start of cron (more than a day ago is flagged).
3. **Base roles created**: on the page **VAE roles** (`roles_install.php`, for whoever holds `moodle/role:manage`), create
   the three base roles from the presets shipped in `presets/roles/` (`vaeiste`, `vae_responsable`, `vae_direction`; in
   English "Vaeiste", "VAE manager", "VAE direction"). Nothing is created without a confirmation that lists the rights;
   an existing role is never changed. The two optional roles (`vae_responsable_evaluateur`, `vae_direction_certificatrice`)
   are created only if the site adopts the internal positioning cycle.
4. **Expected rights given by a role**: the grid "Actors and powers" of the tab "Organisation" says, right by right,
   which roles give it and what is "Missing".
5. **Roles assigned and cohorts attached** (for you to check): assign the roles "Vaeiste" (to the candidates) and "VAE
   direction" at site level, the only level their presets allow; attach each manager to their candidates with the core tool
   *Cohort roles management* (one cohort per establishment; the role "VAE manager" is assignable in the user context
   only). **Never assign the manager role at site level**: it opens no dossier there.
6. **At least one record imported**: *Competencies > VAE > VAE - pathway*, "Import records from France compétences":
   upload the ZIP of the export downloaded from data.gouv.fr, or let the server fetch it. A CSV file of four columns is a
   special case; `cli/convert_fiche.php` is a fallback run on the server. Details in French:
   [IMPORT-FICHES-FRANCE-COMPETENCES.md](../IMPORT-FICHES-FRANCE-COMPETENCES.md).
7. **Settings**: review *VAE: settings* (20 settings in seven groups) without opening what is closed until the site has
   written what goes with it (retention, legal basis, texts read by a VAE practitioner).
8. **Terms of use and privacy policy published** (for you to check): the plugin ships a model of terms of use in French
   ([MODELE-CONTRAT-USAGE-PLATEFORME.md](../MODELE-CONTRAT-USAGE-PLATEFORME.md)); see [personal data](privacy.md).
9. **Antivirus**: needed only if the site opens the supporting pieces; without an antivirus, files are refused unless
   the administrator accepts it in writing (`piecenoantivirus`).

Then a rehearsal with three test accounts (a candidate, a manager who follows them, a direction), deleted afterwards.

## Capabilities to know

No capability touching the data has a default archetype (only `linkcourse` goes to the Manager archetype). The
candidate also needs the core capability `moodle/competency:competencyview` to save a declaration (by default it comes
from the "Authenticated user" archetype). The table of the 21 capabilities is in the [README](../../README.md), section
"Roles and capabilities".

## Upgrading

Replace the folder `vae` by the new version, then *Site administration > Notifications* or `php admin/cli/upgrade.php`.
Each new build carries a step of `db/upgrade.php` that records the number; the core then rebuilds its caches.

## Uninstalling

Uninstall through *Site administration > Plugins > Plugins overview* or `admin/cli/uninstall_plugins.php`: every dossier
is then backed up into the private files of the administrator before the tables are dropped. **Never delete the folder
of the plugin first**: nothing would be backed up and the tables would be lost. A candidate with no declaration has no
backup. The roles created by the page "VAE roles" stay, with their assignments, but emptied of their `tool/vae:*` rights,
and a reinstallation does not fill them again (the page never changes an existing role): delete them (*Define roles*),
then, the plugin installed again, create them from the page "VAE roles" and assign them again; or give their rights back
by hand.
