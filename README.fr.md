# VAE - cheminement (`tool_vae`)

*English version: [README.md](README.md). Ce fichier en est la version française fidèle : mêmes sections, mêmes faits.*

Plugin Moodle de type **admin tool** pour la VAE (validation des acquis de l'expérience). Il permet à des professionnels
engagés dans une démarche de VAE de constituer, au fil de leur activité, une trace datée, attribuée et vérifiable des
compétences acquises en situation de travail. La VAE n'est pas un examen : le plugin aide à constituer et à mettre en
forme le dossier ; **le jury décide**.

- **Code source** : https://github.com/jeanlouismartinez/moodle-tool_vae
- **Suivi des bogues (public)** : https://github.com/jeanlouismartinez/moodle-tool_vae/issues
- **Documentation** : ce fichier, et trois pages en anglais : [installation et première mise en service](docs/en/installation.md),
  [données personnelles](docs/en/privacy.md), [services externes](docs/en/external-services.md). Les autres documents
  sont en français (voir « Documentation »).

Nom affiché « VAE - cheminement » (« VAE - pathway » en anglais) ; composant `tool_vae` ; dépôt `moodle-tool_vae`
(convention de Moodle `moodle-{type}_{nom}`). Jusqu'à la version 0.10.1, le composant s'appelait `tool_referentiel`, nom
qui renvoyait au plugin de M. Jean Fruitet (https://github.com/jfruitet/moodle_referentiel) : les entrées anciennes du
[journal des versions](CHANGELOG.md) gardent ce nom.

Dans ce fichier, la personne qui prépare une VAE est le **vaeiste** ; le **responsable VAE** suit des vaeistes ; la
**direction** lit tous les dossiers ; l'**administrateur** met le plugin en service. Les mots de la VAE sont expliqués
dans le « Glossaire », à la fin.

**Pourquoi « cheminement ».** La VAE transforme une compétence éprouvée en qualification, par un travail de réflexion
sur la pratique : la formation va du savoir vers l'expérience, la validation va de l'expérience vers le savoir. Le plugin
accompagne ce **cheminement** ; le dossier n'en est que le produit final. La conception de la VAE qui guide le projet est
décrite par l'auteur sur https://martinez.pro/mod/page/view.php?id=84 et https://martinez.pro/mod/page/view.php?id=85.
Les guides d'usage (vaeiste, responsable, direction, administrateur) sont publiés par l'auteur sur son site, pas dans ce
dépôt.

## État

- **Version 2.5.03 (Moodle 5.3), build 2026112200, maturité bêta** (`version.php`). Le numéro de publication se lit
  « génération . branche de Moodle » : 01.5.3 a été la première version envoyée au répertoire des plugins ; 2.5.03 est la
  deuxième génération, pour Moodle 5.3 seulement. Ce que Moodle compare pour décider d'une mise à jour est le numéro de
  build, pas cette étiquette.
- **Jamais utilisé par de vrais vaeistes à ce jour** ; le comportement sous une charge réelle n'a jamais été vérifié.
- **Vérifié par** : les suites PHPUnit et Behat du plugin, jouées en local sur Moodle 5.3.0 et PostgreSQL 17 avant chaque
  build (le journal des versions dit ce que chaque build a vérifié et ce qu'il n'a pas vérifié) ; l'intégration continue
  sur GitHub (`.github/workflows/moodle-ci.yml`, `moodle-plugin-ci`) sur PostgreSQL et MariaDB ; **MySQL y a été ajouté le
  22 novembre 2026 et n'a pas encore tourné**. Le 23 novembre 2026, le paquet de ce build a été installé sur des sites locaux
  neufs avec MySQL 8.4 et MariaDB 11.4 : la suite PHPUnit y passe sans aucune notice ; les essais qui y sont sautés demandent
  PostgreSQL (un déclencheur) ou ce que le paquet ne contient pas (les fichiers de développement, les chaînes françaises).
  Behat y a été joué le 22 novembre 2026 avec un paquet antérieur de ce build (sans JavaScript après les corrections de ce
  jour-là, avec JavaScript avant elles), pas avec celui-ci. Les deux appels `debugging()` inattendus qu'une première exécution
  locale sur MySQL avait montrés venaient de deux essais de `tests/observer_test.php`, qui jouaient la file des messages dans
  le processus PHP qui venait de supprimer un compte, avec des droits lus avant la suppression encore en cache ; ces deux
  essais jouent maintenant la file comme un nouveau processus de cron, et le plugin ne tente plus d'envoyer une déclaration
  en attente qui concerne un compte supprimé, quels que soient les droits en cache (voir le journal des versions).
- Behat joue des contrôles d'accessibilité axe-core dans un vrai navigateur (cinq scénarios de
  `tests/behat/accessibility.feature`) : les trois onglets de la page Organisation, « Mise en route » et le guide du
  vaeiste. Un essai PHPUnit compte les lectures de la base des trois onglets quand les données croissent
  (`tests/organisation_load_test.php`) ; les points chauds trouvés sont décrits dans le journal des versions, aucun n'est
  corrigé.
- **Mode de débogage développeur** : le site d'essai Behat de Moodle tourne au niveau de débogage développeur
  (`lib/behat/lib.php`), et PHPUnit signale tout appel `debugging()` inattendu
  (`lib/phpunit/classes/advanced_testcase.php`). Le 22 novembre 2026, avant les dernières corrections de ce build, un site
  local neuf en débogage développeur a été parcouru en HTTP : les 45 pages du plugin pour quatre rôles, et 62 formulaires,
  dépôts et téléchargements envoyés, sans aucun avertissement, aucune notice ni aucun appel `debugging()`. Une séance dans un
  navigateur en débogage développeur sur un vrai site **n'est pas vérifiée**.

## Ce qu'il fait, rôle par rôle

Moodle possède déjà un sous-système de compétences complet. Ce plugin **alimente** et **étend** `core_competency` ; il
ne le réimplémente jamais.

- **Le vaeiste** déclare une activité sur une compétence d'une fiche de certification, avec une période d'exercice et un
  cadre (une preuve d'acquis du cœur est créée) ; rédige les rubriques du dossier de faisabilité (brouillons enregistrés
  tout seuls pendant la frappe, versions comparées et restaurées), le dépose comme un jalon daté, et répond aux questions
  qu'un responsable pose avant d'attester ; joint des pièces justificatives, se positionne sur chaque élément de la fiche,
  décrit des situations de travail, et exporte le dossier en archive pour le jury, ou télécharge une copie modifiable
  Word ou OpenDocument. Il lit son dossier sur « Mon parcours » et dans un guide d'une page.
- **Le responsable** suit des vaeistes par un rôle donné dans le contexte utilisateur de chacun (par cohorte) ; lit leurs
  dossiers, atteste une déclaration, demande une précision avant d'attester, lit les dépôts et écrit un avis sur l'un
  d'eux ; peut évaluer une compétence (rôle facultatif) et annoter des situations avec l'accord du vaeiste ; demande à un
  collègue de retirer une attestation. L'onglet « Vaeistes suivis » les liste avec des nombres, jamais un verdict.
- **La direction** lit tous les dossiers (`viewany`), les exporte, liste les déclarations en attente d'attestation, peut
  certifier un bloc comme positionnement de la structure (rôle facultatif) et lister les certifications à renouveler,
  produit l'archive de la structure, et lit la configuration du dispositif sur l'onglet « Organisation ».
- **L'administrateur** importe les fiches de certification (export de France compétences déposé ou récupéré par le
  serveur, ou fichier CSV), crée les rôles à partir des préréglages, règle les réglages, sauvegarde un dossier, réimporte
  une archive, déclare quelle fiche en remplace une autre, nomme les référents des blocs et les adresses des règles de
  validation.
- Chacun est prévenu de ce qui le concerne, par la messagerie de Moodle ; **aucun message ne reproduit le récit**.
- Le dossier **s'exporte en une archive figée pour le jury**, avec un PDF imprimable et un manifeste d'empreintes SHA-256,
  qu'un autre site peut réimporter après vérification des empreintes, aux conditions dites plus bas.

## Ce qu'il ne fait pas

- **Des nombres, jamais un verdict.** Aucun écran ne dit qu'un dossier est complet, prêt ou recevable ; aucun taux, aucun
  pourcentage. Le tableau de couverture montre au vaeiste ce qu'il a déclaré, sans conclure.
- **Il ne remplace pas le certificateur.** Aucune recevabilité, aucune pré-évaluation, aucun avis sur l'issue probable. Les
  positionnements que la structure inscrit (évaluer une compétence, certifier un bloc) sont ses propres actes, internes :
  ni une validation ni une prévision. **Seul le jury valide, hors de la plateforme.**
- Il n'édite pas le référentiel de certification : importé, jamais modifié par le plugin.
- Il n'embarque aucun modèle d'IA. L'aide facultative à la reformulation est fermée par défaut et passe par les
  fournisseurs d'IA du cœur de Moodle, avec une confirmation à chaque envoi.
- Sa seule requête sortante propre est la récupération de l'export de France compétences, sur clic d'un administrateur,
  vers deux hôtes écrits dans le code. Il ne visite jamais une adresse saisie par un utilisateur (voir
  [services externes](docs/en/external-services.md)).

## Prérequis

- **Moodle 5.3 LTS** : `requires` vaut 5.3.0 et `incompatible` 5.4, donc Moodle refuse le plugin en 5.4 et au-delà. Seules
  les versions LTS sont prises en charge, une par paquet (raison dans « Compatibilité »).
- **PHP** : ce que Moodle 5.3 exige (8.3.0 ou plus, `admin/environment.xml` de Moodle 5.3) ; essayé sur PHP 8.3.
- **Base de données** : par l'API de base de données de Moodle seulement. Jouée en local sur PostgreSQL 17 ; dans
  l'intégration continue sur PostgreSQL 17 et MariaDB 11.4 ; **MySQL 8.4 ajouté à l'intégration continue, pas encore
  joué**. Les versions minimales sont celles de Moodle 5.3 (PostgreSQL 17, MariaDB 11.4, MySQL 8.4).
- Le **sous-système de compétences activé** (*Administration du site → Fonctions avancées → Activer les compétences*).
  Sans lui, le plugin affiche une explication et s'arrête.
- Le **cron** de Moodle doit tourner : les messages, la purge qui suit la suppression d'un compte et les tâches planifiées
  en dépendent.
- **Aucun autre plugin** n'est requis (`version.php` ne déclare aucune dépendance) ; le plugin n'utilise que des
  sous-systèmes du cœur (compétences, rôles par cohorte, messagerie, fichiers, protection des données, et IA quand le site
  ouvre l'aide à la reformulation). Rien n'est à installer avec Composer.

## Installation

1. **Par un fichier ZIP**, la voie ordinaire : *Administration du site → Plugins → Installer des plugins*. Le dossier
   principal du ZIP doit s'appeler `vae`. Un ZIP produit par GitHub (« Download ZIP » ou l'archive d'une version) s'ouvre
   sur un dossier `moodle-tool_vae-<branche ou étiquette>` : tapez `vae` dans le champ avancé « Renommer le dossier
   principal » de l'installateur, ou décompressez à la main dans `<moodle>/public/admin/tool/vae` (le dossier doit
   s'appeler `vae`).
2. **Ou avec git** :
   `git clone https://github.com/jeanlouismartinez/moodle-tool_vae.git <moodle>/public/admin/tool/vae`.
3. Ouvrez *Administration du site → Notifications* pour installer (ou lancez `php admin/cli/upgrade.php`).
4. **Étapes après l'installation** (elles ne sont pas standard ; rien n'est créé sans l'administrateur) : ouvrez
   *Administration du site → Compétences → VAE → Mise en route*, qui liste les étapes suivantes dans l'ordre, chacune avec
   son état, et ne modifie rien ; créez les rôles depuis la page *Rôles VAE* ; rattachez les responsables à leurs vaeistes
   par cohorte (outil du cœur *Gestion des rôles par cohorte*) ; importez les fiches de certification ; relisez
   *VAE : réglages* ; publiez le contrat d'usage du site et l'information sur la protection des données.

Détail : [docs/en/installation.md](docs/en/installation.md) (en anglais) et
[docs/INSTALLATION-PAS-A-PAS.md](docs/INSTALLATION-PAS-A-PAS.md) (en français, pas à pas, pour débutants).

Le paquet est produit depuis le dépôt par `git archive` : les fichiers de développement (`scripts/`, `Makefile`,
`phpcs.xml.dist`, les fichiers d'intégration continue) et les documents de travail de `docs/` restent dans le dépôt
et sont écartés du paquet par `.gitattributes` (`export-ignore`). De `docs/`, le paquet garde `docs/schema` (lu à
l'exécution) et les documents vers lesquels ce README renvoie. **Le paquet ne livre que les chaînes anglaises**
(`lang/en`) : les chaînes françaises (`lang/fr/tool_vae.php`) restent dans le dépôt GitHub, pour les sites qui les
veulent, et le porteur entend que le français du plugin vienne d'AMOS, l'outil de traduction de Moodle (pas fait à ce
jour).

## Rôles et capacités

**Aucune capacité touchant à la donnée ne porte d'archétype par défaut**, sauf `linkcourse`, donnée à l'archétype
Gestionnaire (`db/access.php`). Ce n'est pas un oubli : une capacité donnée à l'archétype `user` au niveau du contexte
utilisateur serait détenue par chaque utilisateur dans le contexte de tous les autres. Le plugin prouve qui suit qui en PHP
(`\tool_vae\local\access`), jamais par une capacité héritée. **Le plugin n'attribue aucun rôle** : qui détient un rôle, et
où, se décide dans Moodle.

| Capacité (`tool/vae:…`) | Niveau | Ce qu'elle ouvre |
|---|---|---|
| `declare` | utilisateur | déclarer une activité, sur soi |
| `viewown` | utilisateur | lire son propre dossier |
| `draftown` | utilisateur | rédiger les rubriques de son propre dossier et leurs versions |
| `positionself` | utilisateur | se positionner sur les éléments de sa fiche |
| `export` | utilisateur | exporter un dossier en archive : avec `viewown` sur le sien, avec `viewany` sur celui d'un autre ; jamais seule |
| `useai` | utilisateur | demander une reformulation d'un texte que l'on a écrit (seulement une fois le réglage `aiopen` ouvert) |
| `viewassigned` | utilisateur | lire le dossier d'un vaeiste que l'on suit (rôle dans le contexte utilisateur du vaeiste) |
| `attest` | utilisateur | attester la déclaration d'un vaeiste dont on peut lire le dossier |
| `readdeposit` | utilisateur | lire les dépôts d'un dossier que l'on peut consulter, jamais ses brouillons ni ses versions |
| `writeopinion` | utilisateur | écrire un avis sur un dépôt d'un vaeiste que l'on suit ; exige aussi `readdeposit` |
| `evaluate` | utilisateur | évaluer une compétence d'un vaeiste que l'on suit ; jamais sur soi |
| `annotate` | utilisateur | annoter les situations et l'auto-positionnement d'un vaeiste que l'on suit, avec son accord |
| `draftview` | utilisateur | lire les rubriques et les versions d'un dossier que l'on peut consulter |
| `identityview` | utilisateur | lire l'identité et le statut d'un dossier de faisabilité, en plus de sa lecture |
| `withdrawany` | utilisateur | retirer n'importe quelle attestation d'un dossier que l'on peut lire |
| `viewany` | système | lire tous les dossiers, et la configuration du dispositif |
| `certify` | système | certifier un bloc comme positionnement de la structure ; exige `viewany` ; jamais sur son propre dossier |
| `issuestructure` | système | produire l'archive de la structure, et autoriser un responsable à la produire ; exige `viewany` |
| `import` | système | importer des fiches de certification, et retirer la ligne d'une fiche importée par erreur |
| `importdossier` | système | réimporter une archive, et sauvegarder le dossier de n'importe quel vaeiste dans ses fichiers personnels |
| `linkcourse` | cours | rattacher une fiche importée aux compétences d'un cours (archétype Gestionnaire par défaut) |

Règles que le tableau ne montre pas :

- **`attest` ne suffit pas seule** : attester exige de lire le dossier, en suivant le vaeiste ou en détenant `viewany`.
  **`export` ne suffit jamais seule** : un responsable qui suit un vaeiste lit son dossier, il ne l'exporte pas.
  **`certify` exige `viewany` ; `evaluate` exige de suivre le vaeiste.**
- **Ne jamais attribuer le rôle de responsable au niveau du site.** `viewassigned` détenue au niveau du site n'ouvre aucun
  dossier (`access::follows()`), et `readdeposit`, `draftview` et `identityview` détenues sans `viewany` ni suivi n'ouvrent
  rien non plus. Lire tous les dossiers a sa propre capacité, `viewany`, pour la direction.
- **Les droits du cœur.** Le plugin lit les fiches importées sans `moodle/competency:competencyview`, mais le cœur le
  redemande à l'enregistrement d'une déclaration : le vaeiste doit donc le détenir (par défaut, il lui vient de l'archétype
  « Utilisateur authentifié ») ; la page de déclaration le dit au lieu d'ouvrir le formulaire. L'import demande
  `moodle/competency:competencymanage`, le rattachement d'une fiche à un cours `moodle/competency:coursecompetencymanage`,
  et une sauvegarde ou un réimport `moodle/competency:userevidencemanage` sur le vaeiste.

**La page « Rôles VAE »** (*Administration du site → Compétences → VAE → Rôles VAE*, `roles_install.php`, pour qui détient
`moodle/role:manage`) crée les rôles à partir des préréglages livrés dans `presets/roles/` : trois rôles de base
(`vaeiste`, `vae_responsable`, `vae_direction`) et deux rôles facultatifs (`vae_responsable_evaluateur`, le responsable
plus `evaluate` ; `vae_direction_certificatrice`, la direction plus `certify`). Leurs noms viennent des chaînes de langue :
en anglais, seule langue du paquet, « Vaeiste », « VAE manager », « VAE direction », « VAE evaluating manager », « VAE
certifying direction » ; les chaînes françaises du dépôt les nomment « Vaeiste », « Responsable VAE », « Direction VAE »,
« Responsable VAE évaluateur », « Direction VAE certificatrice ». Rien n'est créé à l'ouverture de la page ni à
l'installation : un rôle est créé après une confirmation
qui liste ses droits ; un rôle existant (même nom court) n'est jamais modifié ; `import`, `importdossier`, `identityview`,
`useai`, `withdrawany` et `linkcourse` sont refusées même si un fichier de préréglage avait été modifié ; `evaluate`,
`certify` et `issuestructure` ne sont que dans les préréglages facultatifs. Les rôles peuvent aussi s'importer à la main
(*Définition des rôles → Importer*) avec les fichiers de `presets/roles/`. Le rôle du vaeiste se crée avec l'archétype
« Étudiant » choisi dans le formulaire du rôle, et non en recopiant un rôle d'étudiant, ce qui recopierait des droits
comme « Voir les profils des utilisateurs » sur tout le site. Son nom court `vaeiste`, sans le `vae_` des quatre autres,
est gardé à dessein (décision du porteur, 22 novembre 2026) : le rôle n'existe que sur un site où un administrateur l'a
demandé sur cette page et l'a confirmé, et la page montre comme existant un rôle qui porte déjà ce nom court, ne le crée
jamais une seconde fois et ne le modifie jamais ; renommé, les rôles déjà créés échapperaient à la page et aux mises à jour
du plugin, qui retrouvent un rôle par son nom court.

**Rattacher les responsables à leurs vaeistes.** Un responsable ne lit que les dossiers des vaeistes qu'il suit. L'outil
du cœur *Gestion des rôles par cohorte* (`tool_cohortroles`) donne un rôle à une personne dans le contexte utilisateur de
chaque membre d'une cohorte et le tient à jour par le cron : une cohorte par établissement, un rôle « Responsable VAE »
(attribuable dans le seul contexte utilisateur), puis le responsable, le rôle et la cohorte dans *Gestion des rôles par
cohorte*.

## Les fonctions en détail

### Mise en route, le lien « VAE », les guides

- **Mise en route** (`setup.php`) liste les étapes de la mise en service dans l'ordre du guide d'installation, avec leur
  état (« Fait », « À faire », « À vérifier par vous »). **Elle n'écrit rien.** Ce qui ne se constate pas (rôles attribués,
  cohortes rattachées, contrat d'usage publié) reste « À vérifier par vous ».
- **« Ce que vous pouvez faire maintenant »** : en tête de « Mon parcours » (le vaeiste) et de « Vaeistes suivis » (le
  responsable), un encadré dit ce qui est possible maintenant, avec un lien : déclarer une activité, répondre à une
  précision, reprendre son dossier de faisabilité, se positionner ; pour le responsable, un nombre de déclarations à
  attester, de réponses à lire, de dépôts sans avis. Jamais une progression ni un verdict ; il n'écrit rien et ne nomme
  personne.
- **Le lien « VAE » de la barre du haut** mène à `organisation.php`. Il n'est montré qu'à qui peut ouvrir un onglet de
  cette page (le vaeiste, le responsable qui suit au moins un vaeiste, la direction qui détient `viewany`), jamais en
  session « Se connecter en tant que ».
- **Une catégorie « VAE »** sous *Administration du site → Compétences*, « Mise en route » en premier ; les 20 réglages de
  *VAE : réglages* sont groupés sous sept titres (attestations et rappels ; dossiers ; pièces justificatives ; aide de
  l'IA ; copies modifiables ; positionnement ; import des fiches).
- **Un guide d'une page par rôle** (`guide.php`) : sous le titre de chaque onglet, un lien « Aide » ouvre le guide du
  vaeiste, du responsable ou de la direction, servi par le plugin à partir de ses chaînes de langue (l'anglais seul dans
  le paquet) ; le guide d'un rôle s'ouvre pour qui peut ouvrir l'onglet de ce rôle. Il décrit ce qui est ouvert sur un site
  tel qu'il est installé, et ne donne aucun verdict.
- **Non vérifié** : la catégorie « VAE » vue par un administrateur ou une direction sur un vrai site (prouvée par les
  essais) ; le guide de la direction lu par HTTP ; les textes des guides, à relire par un accompagnateur VAE.

### Le dossier de faisabilité, les dépôts, les avis, les précisions

- **Rubriques à champs** de l'annexe II de l'arrêté du 3 juillet 2025 : le plugin garde le nom de naissance, la date de
  naissance, la nationalité, le niveau de formation le plus élevé, le niveau et les intitulés des certifications obtenues,
  le statut et l'identifiant de la convention collective ; il lit les prénoms, le nom d'usage, le courriel, l'adresse, la
  ville, le pays et le téléphone dans le compte Moodle sans les copier ; le genre, facultatif dans l'annexe, n'est pas
  repris. Chaque enregistrement est une version, comparable comme celles des textes. La lecture par un tiers exige
  `identityview`, distincte de `draftview` et donnée à aucun archétype : lire les textes d'un vaeiste n'ouvre pas sa date
  de naissance. **Aucun contrôle de complétude** : l'astérisque dit ce que le formulaire officiel exige, et une rubrique
  s'enregistre même incomplète. **Limite** : les listes de l'annexe ont été lues par un lecteur de page, non copiées du
  Journal officiel ; à comparer avec le texte avant tout usage en production.
- **Les rubriques de texte** ont un brouillon enregistré tout seul pendant la frappe (la fonction externe
  `tool_vae_save_draft`, appelée par `core/ajax`) et des versions enregistrées à la demande, comparées et restaurées.
- **Dépôt** : un jalon daté et empreinté (SHA-256) de ce que le vaeiste a **enregistré**, jamais d'un brouillon. Ce n'est
  pas un verrou : le vaeiste continue d'écrire, et un nouveau dépôt est un nouveau jalon numéroté. Les responsables qui le
  suivent **et** détiennent `readdeposit` sont prévenus (le nom et le numéro, rien des rubriques) et lisent ce jalon,
  jamais les brouillons ni les versions.
- **Avis** : un responsable qui suit le vaeiste et détient `writeopinion` (avec `readdeposit`) écrit un texte libre sur un
  dépôt, daté, empreinté, jamais réécrit ; le vaeiste le lit avec le nom de l'auteur et en est prévenu sans le texte. L'avis
  reste attaché au dépôt qu'il commente. **Le plugin ne dit pas si l'avis est favorable**, et ce n'est pas la signature du
  formulaire officiel.
- **Précision** : un responsable qui suit le vaeiste et peut attester lui pose une question sur une déclaration avant de
  l'attester ; le vaeiste y répond une fois, avec des mots. Les deux textes sont datés, empreintés et jamais réécrits ; le
  vaeiste est prévenu qu'une question l'attend, sans le texte. Ni verrou ni verdict : l'attestation n'attend pas la
  réponse.
- **Rappel** : l'administrateur peut fixer un nombre de jours (`overduedays`, zéro = désactivé) après lequel les
  responsables qui suivent le vaeiste et peuvent attester sont rappelés, une fois, qu'une déclaration attend encore ; le
  message nomme le vaeiste et un nombre, jamais le récit. La page « Déclarations en attente d'attestation » (`viewany`) les
  liste par vaeiste, la plus ancienne d'abord, avec le **nombre** de responsables qui seraient prévenus (ils suivent le
  vaeiste, peuvent attester, et leur compte n'est ni suspendu ni supprimé), jamais leurs noms ; un zéro dit que personne ne
  serait prévenu, et une case ne garde que ces vaeistes. Pour qu'un vaeiste ait toujours quelqu'un à prévenir, rattachez-le
  à un responsable par une cohorte.
- Les dépôts, les avis, les questions et les réponses ne sont pas dans l'archive d'export ; ils sont dans l'export des
  données personnelles de Moodle, qui se lit mais ne se réimporte pas.

### Archive, copies, sauvegardes

- **L'archive pour le jury** (`export.php`, capacité `export`) : les déclarations, les attestations, la fiche, un
  `dossier.pdf`, les rubriques de texte du dossier de faisabilité (`faisabilite.json`, dernière version enregistrée) et les
  pièces justificatives, sous un manifeste d'empreintes SHA-256. **Jamais l'identité, le statut ni les coordonnées** :
  l'archive garde des identifiants opaques. Les rubriques n'y vont que pour qui peut les lire à l'écran. Au réimport, une
  rubrique absente du site revient en version 1 (« Restauré depuis l'archive … ») ; **une rubrique déjà écrite sur le site
  n'est jamais écrasée** ; une archive sans `faisabilite.json` se réimporte comme avant. Le format est décrit par les
  schémas JSON de `docs/schema/` (clés et noms de fichiers en français, à dessein).
- **Une archive venue d'un autre site.** Chaque site numérote ses compétences la première fois qu'un dossier en sort
  (archive, copie, sauvegarde) ou qu'une archive y est reprise, et le compte du vaeiste reçoit son identifiant à sa
  première déclaration, à son premier export, à sa première copie ou à sa première sauvegarde. Une archive d'un autre site
  n'est reprise que si le site qui la reçoit n'a pas déjà numéroté autrement les compétences qu'elle nomme, et si le compte
  du vaeiste n'y a pas encore d'identifiant ; sinon elle est refusée en entier, rien n'est écrit, et le message donne le
  nombre de compétences en cause et nomme les dix premières, triées par code. Les écrans d'export et de sauvegarde disent
  ces réserves. Le plugin ne rapproche jamais de lui-même deux compétences de deux sites : c'est un jugement ; un écran de
  correspondance validée par une personne, et une archive complète pour soi, sont décidés, **pas construits**.
- **Une ligne hors format** (un rang de compétence « c7 » au lieu de « C7 », laissé par une version antérieure à la
  0.46.0 ou écrit dans la base ; une déclaration nouvelle sur un tel rang est refusée) ne bloque ni l'export ni la
  sauvegarde faite depuis l'écran : l'archive est produite avec un avertissement qui nomme la déclaration et une mention
  dans sa notice `LISEZMOI.txt`. Elle **ne se corrige pas depuis Moodle** (l'archive reprend le rang écrit dans la
  déclaration, non le numéro d'identification que la compétence porte aujourd'hui) ; l'avertissement revient à chaque
  export tant que la déclaration existe, et une telle archive ne se réimporte pas telle quelle (la copie modifiable reste
  possible). Le journal de chaque export garde le nombre de lignes en cause. Une pièce dont le fichier est perdu ou altéré,
  et un dossier trop volumineux pour être relu, restent refusés.
- **Copies modifiables** (`document.php`) : le vaeiste (et la direction) télécharge le dossier en `.docx` ou en `.odt`
  pour le relire et le corriger, avec une phrase en tête qui dit que ce n'est **pas** l'archive délivrée par la
  plateforme : d'abord les rubriques du dossier de faisabilité (dernière version enregistrée), puis les déclarations et les
  attestations. Même capacité que l'archive ; la demande est un envoi POST avec la clé de session ; chaque téléchargement
  laisse un événement (nombre de déclarations, format, empreinte des données, jamais le texte). Identité, coordonnées et
  statut n'y figurent que pour le vaeiste et pour qui détient `identityview` ; les rubriques seulement pour qui peut les
  lire à l'écran (la direction qui n'a que `viewany` et `export` reçoit la copie sans elles) ; les intitulés des pièces
  seulement pour qui peut ouvrir les pièces. Les dates sont celles du fuseau horaire du vaeiste, quel que soit le lecteur.
- **Copie hebdomadaire** (réglage `copyenabled`, désactivé par défaut) : la nuit du dimanche au lundi, une tâche écrit une
  copie datée dans les fichiers personnels du vaeiste (dossier `/vae/copies/`), seulement si le dossier a changé, et ne
  garde que les deux dernières. Une demande d'effacement les supprime avec le dossier. Sa base légale et sa durée de
  conservation sont à fixer par le site avant activation ([T-84](docs/T-84-conformite-rgpd.md), § 6).
- **Sauvegarder sur Moodle** (`importdossier`, et `moodle/competency:userevidencemanage` sur le vaeiste) place un dossier
  dans les fichiers personnels de l'administrateur. Chaque dossier est sauvegardé automatiquement à la désinstallation du
  plugin **si elle passe par la page d'administration ou par `admin/cli/uninstall_plugins.php`**, jamais en supprimant
  d'abord le dossier du plugin (rien n'est alors sauvegardé et les tables sont perdues). On le réimporte après
  réinstallation, une fois les fiches réimportées, sauf s'il porte une ligne hors format, si un dossier est sorti du site
  depuis sur les mêmes compétences, ou si le compte du vaeiste a reçu depuis un nouvel identifiant. La sauvegarde est
  l'archive normative : elle ne contient ni les dépôts, ni les avis, ni les précisions, ni les versions antérieures des
  rubriques, ni le journal réflexif, ni la préparation du dossier de validation ; elle contient les positionnements de la
  structure, pour un vaeiste qui a encore une déclaration. Un vaeiste sans aucune déclaration n'a ni archive ni sauvegarde
  (`db/uninstall.php` le dit). **Les rôles créés par la page « Rôles VAE » restent après la désinstallation, avec leurs
  affectations, mais vidés de leurs droits `tool/vae:*`** (le cœur retire les droits des capacités qui disparaissent), et
  une réinstallation ne les remplit pas (la page ne modifie jamais un rôle existant). Pour les rétablir : les supprimer
  (*Administration du site → Utilisateurs → Permissions → Définition des rôles*), puis, le plugin réinstallé, les recréer
  depuis la page « Rôles VAE » et les réaffecter ; ou leur rendre leurs droits à la main.

### Pièces justificatives

Un vaeiste peut joindre à une déclaration un **fichier** (PDF, ODT, DOCX, JPEG, PNG) ou un **lien**, avec une nature
prise dans une liste fermée (bulletin de paie, contrat de travail, attestation de l'employeur, diplôme ou attestation de
formation, fiche de poste, autre) et un intitulé facultatif. **Le plugin ne lit pas une pièce, ne la juge pas et ne dit
pas qu'elle suffit.** **Fermé par défaut** (`piecesopen`) : à n'ouvrir qu'une fois écrite la durée de conservation de
tels fichiers par le site.

- Le fichier se choisit dans le **sélecteur de fichiers de Moodle**. Son type est lu **sur son contenu**, jamais sur son
  nom ; **le nom d'origine n'est jamais gardé** par le plugin (le fichier est enregistré, servi et archivé sous un
  identifiant), mais le sélecteur le garde dans la zone de brouillon de la personne jusqu'à l'envoi du formulaire, et
  l'antivirus du cœur le lit.
- Bornes : 5 Mo par fichier, 5 pièces actives par déclaration, 30 Mo par vaeiste (réglables sous ces plafonds) ; macros,
  scripts, objets incorporés et relations vers l'extérieur sont refusés dans un DOCX ou un ODT, et les actions qui
  démarrent à l'ouverture dans un PDF quand elles sont lisibles. **Une liste d'interdits, pas une garantie.**
- L'antivirus du cœur est appelé ; **sans antivirus configuré, les fichiers sont refusés**, sauf si l'administrateur
  l'accepte par écrit (`piecenoantivirus`). Une empreinte SHA-256 est calculée à la réception et recomparée à chaque
  export. Un lien n'a pas d'empreinte (ce qui est à une adresse peut changer) ; il est signalé quand il porte une requête ou
  désigne une adresse qui semble privée.
- **Qui lit** : le vaeiste, qui peut attester ce vaeiste, l'administrateur du site. Qui peut seulement lire le dossier voit
  la liste (nature, type, taille, empreinte, dates), pas l'intitulé, et n'ouvre rien. Le téléchargement est toujours forcé.
- **L'attestation** : la signature est refusée si les pièces ont changé depuis l'affichage ; la liste signée est gardée
  avec l'empreinte de chaque fichier et une case « J'ai pris connaissance des pièces justificatives jointes à cette
  déclaration. » (une déclaration, pas un fait). L'écran dit combien de fichiers le signataire a ouverts, et qu'**ouvrir
  n'est pas lire**. Une pièce jointe après la signature est marquée comme telle, et les responsables qui suivent le vaeiste
  en sont prévenus (rien de la pièce).
- **Le retrait** est toujours possible pour le vaeiste, avant ou après l'attestation : le fichier, l'intitulé et l'adresse
  sont effacés ; la nature, le type, la taille, l'empreinte et les dates restent. L'écran dit ce que le retrait ne peut pas
  atteindre : une copie déjà téléchargée, la corbeille de la plateforme jusqu'à son vidage, une archive déjà remise à un
  jury.
- **Dans les archives** (`pieces.json`, schéma `docs/schema/pieces.schema.json`) : l'archive que le vaeiste exporte et la
  sauvegarde portent les fichiers et les adresses ; l'export fait par un tiers, le PDF et la copie modifiable ne portent
  que la liste (l'intitulé d'une pièce suit les fichiers). Au réimport, les pièces reviennent par les mêmes contrôles que le
  dépôt d'une personne ; une archive qui ne les passe pas est refusée en entier, par le nom de la pièce ; **si la jonction
  de pièces est fermée sur le site qui reçoit et que l'archive a des pièces à restaurer, elle est refusée** (ouvrir,
  importer de nouveau, refermer). Un plugin antérieur à la 0.39.0 réimporte les déclarations et perd les pièces sans le
  dire.
- **Ce que le plugin ne peut pas faire** : vérifier qu'un document ne nomme personne ; voir ce qu'un document garde en lui
  (auteur, dates, position d'une photographie : les métadonnées ne sont pas retirées) ; savoir si un responsable a lu une
  pièce.

### Règle de validation, « Hors de mon activité », référents

- **Adresses des règles de validation** (*Compétences → VAE → Règles de validation : adresses*) : jusqu'à cinq par fiche
  (la fiche du répertoire national, l'arrêté du diplôme), chacune avec un intitulé court et, si l'administrateur le dit, le
  jour où la page a été vérifiée. Le plugin ne copie, ne lit et n'applique **aucune règle** (l'export national ne donne pas
  la règle de validation en VAE). Sur le dossier, un seul encadré au-dessus de toute la couverture, jamais sur la ligne d'un
  bloc, avec une phrase fixe (le plugin n'applique pas cette règle, ne dit pas si le dossier la satisfait, ne vérifie pas les
  adresses ; la décision revient au jury). Le PDF et la copie modifiable écrivent les adresses en texte, avec la même
  phrase ; `referentiel.json` les porte (`regle_validation`). **Fermé par défaut** (`refopen`) : à n'ouvrir qu'après
  relecture de la phrase et de sa place par un accompagnateur VAE.
- **« Hors de mon activité »** : un vaeiste peut dire qu'une compétence ou un bloc ne relève pas de son activité, avec une
  note facultative d'une ligne. Une déclaration, pas une dispense : personne ne la signe, elle n'est comptée nulle part, ne
  déclenche aucun message et ne change rien à la couverture ; le jury décide si une compétence non exercée compte. Montrée
  dans un encadré à part de la couverture, lue par le vaeiste, par qui le suit et par qui lit tous les dossiers ; seul le
  vaeiste la pose et la retire. Dans l'archive : `hors_activite.json`, réimporté sans écraser. **Fermé par défaut**
  (`scopeopen`) : ce qui a été dit reste lisible et peut être retiré.
- **Référents des blocs** (*Compétences → VAE → Référents des blocs*) : une règle du site, écrite une fois, qui décide qui est
  prévenu en premier d'une déclaration sur un bloc (et d'une pièce jointe après une signature), parmi les responsables qui
  suivent le vaeiste : les référents du bloc et de la fiche entière s'ils sont parmi eux, **tous les responsables sinon**.
  Elle ne donne ni n'ôte aucun droit. Le rappel et les autres messages ne sont pas aiguillés. Sur *Mes vaeistes*, un
  référent peut compter les déclarations d'un de ses blocs ; la liste des vaeistes ne change pas.

### Aide à la reformulation par IA

Un vaeiste peut demander **une autre formulation d'un texte qu'il a écrit lui-même** (une rubrique de texte du dossier de
faisabilité, la préparation du dossier de validation, la situation d'une déclaration) ; jamais le journal réflexif, jamais
l'identité. **Fermée par défaut** (réglage `aiopen`) : à n'ouvrir qu'une fois écrits la base légale, le registre, la durée
de conservation et le contrat avec le fournisseur, et une fois relus par un accompagnateur VAE et par la personne qui tient
le registre la consigne envoyée et le texte de la politique.

- **Pour que le bouton paraisse** : le réglage ouvert ; la capacité `tool/vae:useai`, donnée à la main (aucun archétype ;
  **un administrateur du site passe tous les contrôles de droit, le vrai verrou est le réglage**) ; **un seul fournisseur
  d'IA de Moodle actif pour la génération de texte** (avec deux, le cœur les essaierait l'un après l'autre).
- **Trois écrans, toujours en POST** (`ai.php`) : la **politique d'IA du site**, texte du cœur, acceptée une fois pour tout
  le site ; la **confirmation, à chaque envoi, qu'on ne peut pas désactiver**, qui montre le texte exact, le nom du
  fournisseur, le nombre de caractères et ce qui est conservé, et rappelle de ne nommer personne ; le **résultat**, à côté
  de l'original, à relire. **Rien n'est enregistré par le plugin** : le vaeiste copie la proposition, ou non, et enregistre
  lui-même. Le bouton ouvre `ai.php` dans un nouvel onglet, pour que le texte en cours ne soit pas perdu ; chaque
  confirmation porte un jeton à usage unique et la marque du fournisseur nommé : recharger la page, un double clic ou un
  fournisseur changé entre-temps n'envoient rien.
- **Plafonds** : 3 000 caractères par défaut (réglable de 500 à 6 000), 25 appels par jour par défaut (de 1 à 100), une
  pause de 30 secondes entre deux appels (fixée par le code). Le plugin compte les appels dans `tool_vae_aicall` (qui et
  quand, jamais le texte), gardés deux jours et retirés par la tâche quotidienne.
- **Ce que le fournisseur reçoit** : le texte entre deux marqueurs tirés au hasard, après la consigne fixe du plugin (la
  chaîne anglaise `aiprompt` du plugin, qui demande une reformulation en français clair ; elle n'a pas été approuvée de
  nouveau depuis qu'elle est devenue une chaîne de langue) ; la consigne système que l'administrateur a réglée dans les
  réglages du fournisseur d'IA de Moodle, s'il y en a une ; et, pour les fournisseurs du cœur qui en envoient un, un
  identifiant que le cœur dérive du compte (`hash('sha256', siteidentifier . userid)`).
- **Ce qui reste dans le cœur** : le texte envoyé et la réponse sont conservés par Moodle dans ses propres tables, dans le
  contexte du site, au nom du vaeiste. **Le plugin n'en efface rien.** Moodle les vide quand une personne approuve une
  demande de suppression de données, à condition que le site crée lui-même ces demandes (`automaticdeletionrequests` de
  `tool_dataprivacy`) et que son registre des données ne définisse pas la finalité et la catégorie du site ; sinon il faut
  les effacer à la main. L'écran de confirmation dit lequel des deux cas s'applique. Détail : [données personnelles](docs/en/privacy.md).

### Importer les fiches de certification

- **Un écran, deux voies** (*Compétences → VAE → VAE - cheminement → Importer des fiches depuis France compétences*,
  capacité `import`) : tapez jusqu'à vingt numéros de fiche (`RNCP12345`, `RS1234`), puis **déposez** le fichier zip de
  l'export téléchargé sur data.gouv.fr (sélecteur de fichiers de Moodle), ou cliquez sur **« Aller chercher le dernier
  export »** : le serveur le récupère, à ce clic seulement. Le plugin lit le zip sans rien extraire, montre pour chaque fiche
  l'intitulé publié en entier, ce que Moodle détient déjà et ce que l'import fera, et **n'écrit rien avant la
  confirmation**. Une fiche refusée (numéro absent, fiche sans bloc, code de bloc inattendu) l'est par son nom, sans bloquer
  les autres. Source, licence, paternité et date ne se retapent pas : la date est celle de l'export, lue dans le nom du
  fichier ; un export plus ancien que ce que Moodle détient est signalé et n'est importé que si une case est cochée.
  L'intitulé complet d'une fiche de plus de 100 caractères n'est conservé nulle part : le référentiel porte le numéro pour
  nom. La paternité et la date de la fiche sont affichées partout où le référentiel est présenté ; aucun intitulé de
  compétence n'est reformulé.
- **Un fichier CSV** à quatre colonnes (cas particulier), et le script en ligne de commande `cli/convert_fiche.php` (script
  de Moodle, lancé sur le serveur du site, chemins absolus) comme voie de secours.
- **Non vérifié** : la voie « Aller chercher » n'a jamais été jouée contre le vrai data.gouv.fr (ses essais simulent la
  réponse) ; l'écran n'a jamais été cliqué par une personne sur un vrai site.
- **Une fiche importée par erreur** : la masquer dans Moodle, supprimer le référentiel dans Moodle si Moodle l'accepte,
  puis retirer la ligne de la fiche dans le plugin (« Retirer cette ligne », capacité `import`), offert seulement quand
  Moodle ne détient plus le référentiel, qu'aucun référentiel ne porte ce code et qu'aucun vaeiste n'y a laissé de trace
  (déclaration, marque « Hors de mon activité », rubrique du dossier de validation) ni la structure aucun positionnement ;
  la page dit pourquoi quand une trace ou un référentiel du même code l'en empêche. Une confirmation liste ce qui part avec
  la ligne (licence et paternité, dates, adresses de règles, référents, marques, correspondances, fiche remplaçante) ; rien
  n'est écrit dans les référentiels de Moodle, et la ligne ne se récupère pas (importer de nouveau la fiche en crée une
  neuve).
- **Fiches remplacées** : l'administrateur déclare quelle fiche en remplace une autre, voit ce qui tient encore l'ancienne,
  et décide les correspondances compétence par compétence ; le vaeiste reporte une déclaration par copie, qui doit être
  attestée de nouveau. Le plugin ne déplace rien.

### La page Organisation : trois onglets, des nombres, jamais un verdict

`organisation.php` réunit trois onglets, **chacun sous son droit** ; le même test décide de ce qui s'affiche et de ce que
l'adresse accepte : un onglet qu'on ne peut pas ouvrir est refusé, jamais remplacé par un autre. La page ne lit aucun
identifiant de personne dans l'adresse (hors l'onglet, son seul paramètre est le bloc choisi sur « Vaeistes suivis »,
contrôlé contre la liste du lecteur) ; sans onglet, elle s'ouvre sur le premier onglet que le lecteur peut ouvrir, sauf pour
qui détient `viewany` et n'a rien déclaré, qui arrive sur « Organisation ». Des nombres, des dates et des noms, jamais un
taux, un total général ni une couleur qui qualifie une personne : seule la ligne d'un bloc additionne les déclarations,
pièces et attestations de sa ligne et de ses compétences, et l'écran le dit ; le seul badge de couleur est le mot
« Manque » de la grille des droits.

- **« Mon parcours »** (le vaeiste, jamais en session « Se connecter en tant que ») : pour chaque compétence des fiches où
  le vaeiste a déclaré, les déclarations, les pièces, les attestations en vigueur, la date du dernier acte, les cours, les
  activités de cours et les plans de formation du cœur qui la portent. Un tiers ne lit un plan que sous
  `moodle/competency:planview` ou `planmanage` dans le contexte du vaeiste (les brouillons sous `planviewdraft` ou
  `planmanagedraft`), jamais une évaluation du cœur.
- **« Vaeistes suivis »** (un responsable qui suit au moins un vaeiste) : par vaeiste, les fiches sur lesquelles il a
  déclaré, le nombre de déclarations et de celles qui attendent une attestation, et les plans de formation là où le droit
  du cœur le permet.
- **« Organisation »** (`viewany`) : la configuration du dispositif, **qui ne montre aucun vaeiste** : par fiche, blocs,
  compétences, cours, activités, modèles de plans, **nombre de plans de formation qui la citent** (exact, sans seuil,
  brouillons compris : un petit nombre peut désigner une personne pour qui connaît le site ; risque accepté par le porteur
  le 4 novembre 2026), référents et adresses de règles ; puis une grille « Acteurs et pouvoirs », les rôles qui accordent,
  retirent ou interdisent chaque droit, les cohortes suivies par un responsable, et dix réglages. **Cet écran montre, il ne
  règle rien.**
- **Activités de cours** : « Mon parcours » dit dans quelle activité de quel cours chaque compétence se travaille, et
  « Organisation » donne le nombre d'activités par fiche et leur liste dans un élément `<details>`. Le rattachement d'une
  activité à une compétence est lu (table du cœur `competency_modulecomp`), jamais la règle que l'activité applique
  (`ruleoutcome`, `overridegrade`), aucune personne, aucune note. Une activité cachée ou restreinte pour le lecteur
  (`uservisible` du cœur) reste listée sans nom, type ni lien (« Une activité non visible ») ; dans un cours caché, les noms
  ne sont donnés qu'à qui détient `moodle/course:viewhiddencourses`. Le plugin ne teste pas l'inscription : qu'un lecteur
  sans rôle dans le cours voie un nom dépend du module et des rôles du site (décision du porteur du 4 novembre 2026).
- **Acteurs et pouvoirs** : pour chaque acteur, les droits du plugin qu'il doit détenir et les rôles du site qui les
  donnent : quatre acteurs à compte (vaeiste, responsable, direction, administrateur du dispositif) et trois sans compte
  (référent de bloc, jury, certificateur) ; trois états par droit (donné, avec les noms des rôles ; non donné et voulu
  ainsi, « Aucun rôle » ; **« Manque »**, un droit attendu qu'aucun rôle ne donne). `evaluate`, facultatif, ne signale
  jamais de manque. La grille ne lit ni qui détient un rôle ni les surcharges faites plus bas que le site : un droit
  « donné » n'est pas la preuve qu'il est exercé par la bonne personne.
- **Mesuré** (`tests/organisation_load_test.php`, 16 novembre 2026, PostgreSQL) : « Mon parcours » croît de 3 lectures de
  la base par fiche déclarée (114 lectures pour 30 fiches), « Vaeistes suivis » de 1 lecture par vaeiste suivi (122
  lectures pour 100), « Organisation » de 1 lecture par cours distinct et de 2 par cours qui porte une activité (113
  lectures pour 30 tels cours) ; le lien « VAE » coûte 1 lecture. Les points chauds sont décrits dans le journal des
  versions ; **aucun n'est corrigé**.
- **Non vérifié** : les introductions et les libellés, à relire par un accompagnateur VAE ; l'accessibilité en français et
  avec un autre thème que Boost ; le coût de lecture sur MariaDB ou MySQL, sur la page entière et sur un vrai grand site.

### Évaluer une compétence, certifier un bloc : le positionnement de la structure

Le cycle **déclarer, évaluer, certifier** se joue à l'intérieur de la structure. **Seul le jury valide, hors de la
plateforme.** Aucun réglage n'ouvre le cycle : ce sont les deux droits, donnés à la main, qui le font.

- **Évaluer** (`evaluate.php`, `evaluate`) : un responsable qui suit le vaeiste donne à **une compétence** un statut
  (*à construire*, *en cours de preuve*, *suffisante pour certification*, *retirée*) et un **motif écrit**, obligatoire
  (4 000 caractères au plus). Jamais sur soi-même ; l'administrateur, qui ne suit personne, n'évalue pas. La fiche doit
  porter au moins une déclaration du vaeiste.
- **Certifier** (`certify.php`, `viewany` et `certify`) : la direction donne à **un bloc** (ou à la fiche entière quand elle
  n'a pas de bloc) le statut *certifié par la structure* ou *retiré*, avec la date de la décision (du 2000-01-01 à
  aujourd'hui) et un motif écrit ; une date facultative « valable jusqu'au » (du jour de la décision au 2100-01-01 ; sans
  elle, la certification n'expire jamais). **Une alerte, pas un verrou** : une compétence du bloc qui n'est pas
  « suffisante » est nommée, et un motif de l'alerte est obligatoire ; le serveur recalcule à l'enregistrement. Jamais sur
  son propre dossier, l'administrateur compris.
- **Ajout seul** : corriger ou retirer, c'est ajouter une ligne ; la dernière ligne fait foi ; une ligne identique à la
  dernière est refusée. Chaque ligne porte l'empreinte SHA-256 de ce qu'elle dit (elle détecte l'accident, elle n'est pas
  inviolable) ; deux envois simultanés sur un même vaeiste et une même fiche passent par un verrou. Le dossier montre la
  ligne qui fait foi, un lien « Historique » mène aux précédentes, et le dossier signale les compétences positionnées après
  la certification de leur bloc.
- **Qui lit** : quiconque lit le dossier, le vaeiste compris. La section « Positionnement de la structure » s'ouvre sur un
  avertissement : ce n'est pas une validation. Un essai de langue (`tests/positioning_test.php`) refuse les mots français
  « valid- », « acquis », « candidat », « taux », « note » et « score » dans les textes de ces écrans (« valid- » et
  « score » en anglais). Les formulaires rappellent de ne nommer aucune personne accompagnée (`anonymiseshort`) : un rappel
  écrit, pas un contrôle.
- **Le message au vaeiste** (`positioningrecorded`) : à chaque ligne, un message dit seulement qu'une ligne a été ajoutée,
  rappelle que ce n'est pas une validation, renvoie au dossier et indique le délégué à la protection des données du site
  pour une rectification ; **jamais le statut, le motif ni l'auteur**. Il part par la tâche asynchrone, seulement si le
  vaeiste peut encore ouvrir son dossier et si une ligne subsiste ; il se désactive dans les préférences de notification.
- **« À renouveler »** se calcule à la lecture, au jour du lecteur, et ne s'écrit jamais ; renouveler, c'est ajouter une
  ligne de certification. La liste « Certifications à renouveler » (`renewals.php`, `viewany` et `certify`) montre chaque
  certification qui fait foi et dont le dernier jour est passé, la plus ancienne d'abord ; pas de total, pas de pourcentage,
  pas de couleur.
- **« Positionné sur l'ancienne fiche »** : quand une fiche en remplace une autre et que l'administrateur a décidé
  l'équivalent d'une compétence ou d'un bloc, la nouvelle fiche montre, **en lecture seule**, la ligne qui fait foi sur
  l'ancienne ; rien n'est copié.
- **Archives** : `positionnement.json` (schéma en version 0.1.0, `officiel` toujours faux : état courant et historique
  complet) est dans l'**archive de la structure** (`structure.php`, `issuestructure`, ou un responsable que la direction a
  autorisé) et dans la sauvegarde, **jamais dans l'archive du vaeiste, ni dans son PDF, ni dans ses copies** (décision du
  porteur : la parole de la structure a son propre livret). Au réimport, les lignes reviennent clé par clé (fiche, rang),
  tout ou rien ; une clé qui a déjà une ligne sur le site est laissée ; 5 000 lignes au plus.
- **Décisions du porteur, qui est aussi délégué à la protection des données de son site, le 5 novembre 2026** : base
  légale, le contrat d'usage ; information, une clause du contrat d'usage et le message ci-dessus ; durée, celle du
  dossier ; une contestation s'adresse au délégué du site, sans écran ([T-84](docs/T-84-conformite-rgpd.md), § 8).
- **Non vérifié** : les mots des statuts, « à renouveler » et « valable jusqu'au », à relire par un accompagnateur VAE ;
  le message envoyé par le serveur de courriel d'un vrai site ; l'accessibilité de ces écrans (aucun scénario axe-core).

### Auto-positionnement et situations de travail

- **Auto-positionnement** (`selfposition.php`, `positionself`, donnée au rôle Vaeiste) : le vaeiste dit où il en est sur
  chaque élément de sa fiche, à tous les niveaux, par **un palier parmi quatre dont le site choisit les mots**
  (`selfposlabel1` à `selfposlabel4` ; par défaut « Je découvre », « Je pratique avec aide », « J'agis seul », « Je
  maîtrise et je transmets »), avec une justification facultative (10 000 caractères au plus). Ajout seul ; 200 lignes au
  plus par élément ; un envoi identique à la dernière ligne est refusé ; rien n'en est calculé (aucun total, taux, seuil,
  tri ni écart). La page montre en regard la parole de la structure, sans écart calculé, et les nombres de déclarations et
  de pièces. Seul le vaeiste écrit, sur son propre dossier ; les responsables qui le suivent et la direction lisent. Dans
  `autopositionnement.json` : dans l'archive du vaeiste, l'export fait par un tiers et la sauvegarde, **jamais dans
  l'archive de la structure**. **Non vérifié** : l'effet sur de vrais vaeistes (autocensure).
- **Situations de travail** (`situations.php`, `situation.php`, `consent.php`) : un titre, une période, un cadre, un texte
  en versions, des étiquettes sur les éléments de la fiche, des pièces. Seul le vaeiste les écrit, quand la préparation du
  dossier de validation est ouverte (`validationopen`). Bornes : 30 situations par fiche, 200 versions par situation,
  5 pièces par situation, 200 annotations par cible, 400 accords donnés par vaeiste.
- **« Où j'en suis »** : un panneau dit, pour chaque élément de la fiche, le nombre de situations et de déclarations qui le
  portent, avec un lien vers la vue inverse ; des comptes, jamais un total ni un pourcentage.
- **Accord et annotations** : le vaeiste donne à un responsable qui le suit un accord daté, nominatif, qu'il peut retirer ;
  le responsable peut alors annoter (`annotate`, suivi et accord revérifiés à chaque écriture) ; seul l'auteur retire son
  annotation. Deux messages sans texte libre : `noteadded` au vaeiste, `consentgiven` au responsable. L'administrateur du
  site est refusé sur les situations et sur les brouillons, versions et historique de la préparation du dossier de
  validation.
- **Archives** : `situations.json` et `pieces-situations/` dans l'archive du vaeiste et dans la sauvegarde, et dans l'export
  d'un tiers seulement quand ce tiers peut lire la préparation du dossier de validation. Sept tables, déclarées colonne par
  colonne au fournisseur de vie privée. **Non vérifié** : les pièces de situation, le retrait de l'accord et les deux
  messages sur un vrai site.

### Deux actes, et le retrait d'une attestation

| Acte | Qui | Capacité | API du cœur |
|---|---|---|---|
| **Déclaration** | le vaeiste, sur lui-même | `tool/vae:declare` | `create_user_evidence()` et rattachement |
| **Attestation** | le responsable | `tool/vae:attest` | **aucune** : une ligne du plugin |

- L'attestation, ligne du plugin, peut être **retirée sans détruire la preuve du vaeiste** : le dossier dit alors qu'une
  attestation a existé et a été retirée à telle date, pour tel motif, et qu'elle n'est plus valable ; **seulement si aucune
  autre attestation n'est en vigueur sur la déclaration**, il ajoute une fois que la déclaration n'a pas été modifiée et que
  le vaeiste peut demander à un responsable de l'attester de nouveau. Même règle à l'écran, dans le PDF et dans les copies.
- Le récit saisi dans le formulaire est du **texte brut** : les balises sont retirées. Une archive réimportée peut porter un
  récit en Markdown ; le format HTML est refusé partout.
- **Retirer** : par défaut, seul l'administrateur du site retire. Trois voies s'y ajoutent : le signataire retire la sienne
  (réglage `managerwithdrawown`, désactivé par défaut) ; un coordinateur retire n'importe laquelle (`withdrawany`, donnée
  dans le contexte des vaeistes qu'il suit ; elle ne dispense pas de lire le dossier) ; un responsable qui suit le vaeiste
  demande au signataire, qui accepte (un retrait ordinaire) ou refuse ; une demande à la fois, non conservée une fois la
  réponse donnée. Le motif est obligatoire et pris dans une liste fermée (erreur de signature, contenu contesté, demande de
  suppression de données) ; le vaeiste ne retire jamais une attestation sur son propre dossier ; une attestation reprise
  d'une archive n'a pas de signataire sur le site, donc seuls l'administrateur et un détenteur de `withdrawany` la
  retirent.
- **Une fiche cachée dans Moodle** (*Visible : Non*) n'accepte plus de déclaration nouvelle ni de report ; le vaeiste lit un
  message écrit pour lui (intitulé et code de la fiche, qu'elle est cachée, s'adresser à son responsable), sans chemin
  d'administration. Un formulaire ouvert avant le masquage rend le texte déjà écrit. Les déclarations déjà faites ne
  changent pas ; la restauration d'une archive et « Hors de mon activité » ne sont pas concernées.

### Qui est prévenu

Quinze types de message (`db/messages.php`), par la messagerie de Moodle (notification et courriel par défaut ; chacun les
règle dans ses préférences de notification), tous envoyés par l'utilisateur « ne pas répondre » du site, si bien que
l'adresse de courriel du vaeiste ne voyage jamais dans les en-têtes :

- au **responsable** qui suit le vaeiste et peut attester : une déclaration attend son attestation (de nouveau après un
  retrait) ; un rappel après le nombre de jours fixé (`overduedays`) ; une pièce a été jointe après une signature ; au
  responsable qui peut lire les dépôts : un dépôt a été fait ;
- au **vaeiste** : une déclaration a été attestée ; une attestation a été retirée ; une compétence du dossier a quitté sa
  fiche ; un avis a été écrit sur un dépôt ; une précision est demandée ; une ligne de positionnement a été ajoutée ; une
  annotation a été ajoutée ;
- au **signataire** : un collègue lui demande de retirer son attestation ; la déclaration qu'il avait attestée a été
  détruite (le vaeiste a supprimé la preuve d'acquis du cœur) ; au **responsable qui a demandé** : le signataire a refusé ;
- au **responsable** à qui le vaeiste a donné son accord : l'accord a été donné.

**Aucun message ne reproduit le récit** : le nom du vaeiste (pour son responsable), la référence de la compétence, une
phrase fixe qui dit où agir et un lien vers le dossier. **Le nom du vaeiste n'est jamais dans l'objet.** Les messages
partent **par le cron**, quelques minutes après l'acte ; le départ d'une compétence de sa fiche attend un jour et n'est
annoncé que s'il tient toujours ; les deux messages d'un effacement attendent environ quinze minutes, pour qu'une série
d'approbations n'en produise qu'un par destinataire. La direction (`viewany`) n'est pas prévenue de chaque déclaration. Les
notifications sont stockées et effacées par la messagerie de Moodle, selon sa propre échéance.

## Données personnelles

L'API de confidentialité de Moodle est implémentée : le fournisseur (`classes/privacy/provider.php`) déclare **les
31 tables** de `db/install.xml`, colonne par colonne, et relie les sous-systèmes du cœur que le plugin emploie
(compétences, messagerie, fichiers, IA). La conservation suit l'existence du compte Moodle du vaeiste : le dossier est
effacé après la suppression du compte (au premier passage du cron qui suit la suppression), ou quand une demande de
suppression de données est approuvée. **La conformité (RGPD) est à la charge du site qui installe le plugin** : le
responsable de traitement est le site, et l'auteur du plugin ne reçoit aucune donnée. Résumé en anglais :
[docs/en/privacy.md](docs/en/privacy.md).

- **Deux personnes figurent dans chaque attestation, et leurs droits vont en sens contraire.** Effacer le vaeiste efface son
  dossier, ses sauvegardes et ses copies. Effacer un responsable n'efface **pas** le dossier : son identité est retirée de
  l'attestation, marquée retirée et datée, pour que le vaeiste garde « attesté, puis retiré » ; le vaeiste et les autres
  responsables en sont prévenus. Supprimer le compte d'un responsable n'anonymise pas ses attestations sur le moment :
  Moodle, laissé à ses réglages, crée une demande de suppression de données qui attend d'être approuvée ; les attestations
  qu'une archive attribue à ce compte (par un identifiant opaque) sont retirées au premier passage du cron qui suit la
  suppression.
- **Un positionnement** est un texte d'un tiers sur le vaeiste : effacer le vaeiste efface ses lignes ; effacer leur auteur
  vide l'identifiant de l'auteur mais garde la ligne et son texte.
- **Aucun champ ne demande de nom** : les descriptions de situations de travail décrivent l'activité du professionnel,
  jamais une personne accompagnée. Un rappel, qu'on ne peut pas masquer, accompagne les textes libres, et le responsable
  relit avant d'attester ; le plugin ne peut pas savoir si un prénom est fictif.
- Pour que le vaeiste puisse emporter son dossier avant la suppression de son compte, le site doit lui donner `export`
  (aucun rôle ne l'a par défaut).

Trois documents sont fournis en projet, à valider par le délégué à la protection des données du site. Ils **restent en
français** parce que ce sont des documents de droit français, écrits pour des sites français (la VAE est un dispositif
français) : un résumé en anglais ouvre chacun des deux premiers.
[docs/AIPD-VAE.md](docs/AIPD-VAE.md) (analyse d'impact relative à la protection des données),
[docs/MODELE-CONTRAT-USAGE-PLATEFORME.md](docs/MODELE-CONTRAT-USAGE-PLATEFORME.md) (modèle de contrat d'usage de la
plateforme), [docs/T-84-conformite-rgpd.md](docs/T-84-conformite-rgpd.md) (bases légales, durées de conservation, droits).

## Services externes

- **Données ouvertes de France compétences** (data.gouv.fr) : récupérées seulement quand un administrateur clique sur
  « Aller chercher le dernier export », depuis deux hôtes écrits dans le code (`www.data.gouv.fr`, `static.data.gouv.fr`,
  `classes/local/fiche_fetcher.php`), en https ; aucune donnée personnelle ne part ; rien de planifié ne l'appelle. Le dépôt
  du fichier zip fonctionne sans aucun réseau.
- **Fournisseurs d'IA du cœur de Moodle**, seulement si le site ouvre l'aide à la reformulation et configure un
  fournisseur.
- Détail, et comment un relecteur peut essayer sans réseau : [docs/en/external-services.md](docs/en/external-services.md).

## Architecture

- `classes/local/competency_gateway.php` est le **seul** fichier du plugin autorisé à appeler le sous-système de
  compétences (`scripts/check-gateway.sh`, dans le dépôt, échoue sinon) ; `classes/local/fiche_fetcher.php` est le seul
  autorisé à ouvrir une connexion réseau (`scripts/check-network-door.sh`, dans le dépôt).
- **Affichage** : les pages sont dessinées par des classes de sortie (`classes/output/`, 104 classes) et des gabarits
  Mustache (`templates/`, 104 gabarits), par le renderer du plugin. Le HTML que lit la bibliothèque PDF (le PDF de
  l'archive, `classes/local/pdf_renderer.php`) est dessiné par un gabarit, `templates/pdf_blocks.mustache`, avec
  `$OUTPUT->render_from_template()` ; `classes/local/document_writer.php` écrit les fichiers Word et OpenDocument, qui
  ne sont pas du HTML, et n'a pas de gabarit.
- JavaScript : trois modules AMD (`amd/src/`). L'enregistrement automatique d'un brouillon appelle la fonction externe
  `tool_vae_save_draft` (`db/services.php`), le seul service web du plugin, pour AJAX seulement.
- Aucune superglobale de PHP n'est lue ; les trois téléversements passent par le sélecteur de fichiers de Moodle.
- Les observateurs ne font que mettre en file une tâche ad hoc (`db/events.php`, `classes/observer.php`,
  `classes/notification_observer.php`) : la purge qui suit la suppression d'un compte ou d'une preuve se fait par le cron, et
  la tâche planifiée `reconcile_orphans` rattrape ce qu'une tâche qui n'aurait jamais tourné aurait retiré ; un message est mis
  en file par son observateur quand l'événement nomme son seul destinataire, et sinon ses destinataires sont cherchés au
  premier passage du cron qui suit l'événement.

## Développement et essais

Les commandes ci-dessous demandent le dépôt GitHub : `scripts/`, le `Makefile` et `phpcs.xml.dist` y sont, pas dans le
paquet ZIP installé sur un site.

```bash
git clone https://github.com/jeanlouismartinez/moodle-tool_vae.git
cd moodle-tool_vae
make doctor      # ce qui manque sur ce poste, et ce que ça empêche
make validate    # tous les contrôles exécutables hors ligne
```

- Contrôles du projet (`scripts/`) : `check-gateway.sh`, `check-core-writes.sh`, `check-network-door.sh`,
  `check-secrets.sh`, `check-schema.mjs`, `check-doc-links.mjs`, `check-english-comments.mjs`, `codestyle.sh`.
- Essais, depuis la racine d'un site Moodle 5.3 où le plugin est installé :
  `vendor/bin/phpunit --testsuite tool_vae_testsuite` et
  `php public/admin/tool/behat/cli/run.php --tags=@tool_vae` (après l'initialisation habituelle des sites PHPUnit et Behat
  de Moodle). Avec le plugin installé depuis le paquet, les essais qui demandent les fichiers de développement ou qui ne
  lisent que le français sont sautés, et les contrôles en français des autres essais ne sont pas faits : `lang/fr` n'est pas
  dans le paquet.
- Code et commentaires en anglais (`make english` échoue sur un commentaire rédigé en français). Les identifiants sont en
  anglais (les classes du dossier de faisabilité sont `feasibility_*`, celles des correspondances entre une fiche remplacée
  et celle qui la remplace `correspondence_*`), sauf des mots français du domaine que certains noms gardent à dessein :
  `fiche` et `bloc` (la fiche RNCP et ses blocs : `fiche_converter`, `for_fiches()`, `bloc_of()`), `vaeiste`, `reflexion`
  et `regard` (`reflexion_overview`, `selfpos_regard`) ; et les noms vers lesquels pointent des données enregistrées ou des
  liens : les colonnes de la base (`fichecode`, `ficherang`…), les valeurs qui y sont enregistrées (le type de document
  `faisabilite`), la table `tool_vae_passage` et l'événement `passage_changed` (nommé dans les journaux), les pages et leurs
  adresses (`faisabilite.php`, `passage.php`), et le format d'export, dont les schémas gardent des clés et des noms de
  fichiers en français (`faisabilite.json`). Le texte des pages, des formulaires et des messages vient des fichiers de
  langue, à une exception près : le titre « Bloc » suivi du rang, que `classes/local/fiche_converter.php` (`SHORT_TITLE`)
  donne à un bloc dont l'intitulé publié est trop long ou porte du balisage, comme nom de la compétence qu'il crée ; il
  reste en français à dessein, « Bloc » étant le terme officiel des données RNCP de France compétences, et
  `competency_gateway::label_of()` reconnaît ce nom pour montrer l'intitulé publié à sa place. Chaque fichier PHP,
  JavaScript, CSS et Mustache porte l'en-tête GPL v3 ; les scripts shell de `scripts/` n'en portent pas. Commenter le
  pourquoi, pas le quoi.
- Données d'essai : des fiches inventées seulement (`tests/fixtures/README-fixtures.md`).

## Documentation

- En anglais : [README.md](README.md) ; [installation et première mise en service](docs/en/installation.md) ;
  [données personnelles](docs/en/privacy.md) ; [services externes](docs/en/external-services.md).
- En français : ce fichier ; [docs/INSTALLATION-PAS-A-PAS.md](docs/INSTALLATION-PAS-A-PAS.md) (première installation, pas à
  pas) ; [docs/FAQ.md](docs/FAQ.md) (questions et réponses par rôle) ;
  [docs/IMPORT-FICHES-FRANCE-COMPETENCES.md](docs/IMPORT-FICHES-FRANCE-COMPETENCES.md) (import des fiches) ;
  [docs/T-84-conformite-rgpd.md](docs/T-84-conformite-rgpd.md), [docs/AIPD-VAE.md](docs/AIPD-VAE.md),
  [docs/MODELE-CONTRAT-USAGE-PLATEFORME.md](docs/MODELE-CONTRAT-USAGE-PLATEFORME.md) (données personnelles) ;
  [docs/BRANCHES.md](docs/BRANCHES.md) (branches et versions de Moodle prises en charge) ;
  [docs/schema/LISEZMOI.md](docs/schema/LISEZMOI.md) (le format d'export).
- [CHANGELOG.md](CHANGELOG.md) : ce qui a changé à chaque version (entrées anciennes en français).
- Captures d'écran : aucune dans le dépôt ; elles sont sur la page du plugin dans le répertoire des plugins.

## Limites connues

- **Sélecteur de dates** : trois listes déroulantes (jour, mois, année), sur cinquante ans en arrière.
- **Une archive d'un autre site** est refusée en entier quand le site qui la reçoit a numéroté autrement une compétence ou
  connaît déjà le compte ; un écran de correspondance validée par une personne est décidé, pas construit.
- **Textes à relire par un accompagnateur VAE** : les mots des statuts, « à renouveler », « valable jusqu'au », les
  introductions de la page Organisation et des guides.
- **Accessibilité** : axe-core contrôle les trois onglets de la page Organisation, « Mise en route » et le guide du
  vaeiste, pas les autres écrans (dossier, déclaration, évaluation, certification, import).
- **Les pièces justificatives** restent fermées par défaut tant que le site n'a pas écrit la durée de conservation de tels
  fichiers.
- **Sans JavaScript**, le sélecteur de fichiers de Moodle se replie sur le gestionnaire de fichiers du cœur ; non essayé.

## Compatibilité : versions LTS seulement

Un dossier de VAE se construit sur des mois et se lit devant un jury des années plus tard : la plateforme qui le détient
doit encore recevoir des correctifs de sécurité ce jour-là. Une version LTS en apporte trois ans, une version
intermédiaire dix-huit mois.

| Moodle | Statut | Branche |
|---|---|---|
| 4.5 LTS | **paquet non maintenu**, à ne pas installer sur un site réel | `MOODLE_405` |
| 5.0, 5.1, 5.2 | **jamais** : versions intermédiaires | aucune |
| 5.3 LTS | **prise en charge** | `main`, suivie par `MOODLE_503` |

`version.php` déclare `supported = [503, 503]`, `requires` 5.3.0 et `incompatible = 504`. Dans Moodle, `supported` n'est
qu'une déclaration (hors de cette plage, la page des plugins affiche un avertissement) ; `requires` et `incompatible`
refusent : un administrateur qui met à jour le cœur vers une version intermédiaire trouve le plugin bloqué, au lieu de le
voir tourner là où personne ne l'a essayé. Aucun des deux paquets n'élargit sa plage pour absorber l'autre : annoncer une
compatibilité que personne n'essaie est pire que ne rien annoncer. Le paquet pour Moodle 4.5 n'est plus maintenu (décision
du 27 octobre 2026) ; sa branche reste consultable. Voir [docs/BRANCHES.md](docs/BRANCHES.md).

## Licence

**GNU GPL v3 ou ultérieure** : voir [LICENSE](LICENSE) et l'en-tête de chaque fichier source ; c'est la licence de Moodle
lui-même. Titulaire des droits : Jean-Louis Martinez, jeanlouis@martinez.pro (décision du porteur du 28 octobre 2026 : le
plugin reste sous cette licence).

Les fiches de certification sont des **données** publiées par France compétences sous *Licence Ouverte 2.0*. **Aucune
n'est dans ce paquet** : l'administrateur dépose l'export ou le fait récupérer par le serveur, et les données vivent dans
la base du site. Les fichiers d'essai de `tests/fixtures/` sont inventés (codes `RS00000` et `RNCP99001`, intitulés écrits
pour les essais), comme l'explique `tests/fixtures/README-fixtures.md`.

## Glossaire

| Mot | Ce qu'il veut dire ici |
|---|---|
| **Déclaration** | Ce que le vaeiste dit avoir fait dans son activité, rattaché à une compétence d'une fiche de certification. Elle se rédige, puis un responsable l'**atteste**. |
| **Attestation** | L'acte d'un responsable qui, ayant relu le récit, confirme la déclaration. Elle se **retire**, elle ne se modifie pas ; une nouvelle attestation est un nouvel acte. |
| **Fiche de certification** | Le référentiel de compétences d'un diplôme ou d'un titre (RNCP, RS), importé depuis l'export de France compétences (déposé ou récupéré), ou par un fichier CSV. |
| **Dossier de faisabilité** | Ex-« livret 1 » : la demande de recevabilité. Le plugin aide à le rédiger ; il ne dit jamais s'il est recevable. |
| **Dossier de validation** | Ex-« livret 2 » : le dossier de preuves. Sa préparation (rubriques, bloc par bloc) est dans le plugin ; le reste du dossier n'y est pas encore. |
| **Rubrique** | Une partie du dossier de faisabilité (objectifs, parcours, expériences…), de texte ou à champs (identité, statut). |
| **Brouillon / version** | Le brouillon est enregistré tout seul et n'est pas une version ; une version est enregistrée à la demande, se compare et se restaure. |
| **Dépôt** | Un jalon daté et empreinté du dossier de faisabilité tel qu'enregistré. Le vaeiste continue d'écrire ; un nouveau dépôt est un nouveau jalon. |
| **Avis** | Texte libre d'un responsable sur un dépôt précis ; jamais réécrit ; le plugin ne dit pas s'il est favorable. |
| **Précision** | Question d'un responsable sur une déclaration non attestée, et réponse du vaeiste ; ni verrou ni verdict. |
| **Positionnement** | Ce que la structure dit, par écrit, daté et attribué : où en est une compétence (**évaluation**, par un responsable qui suit le vaeiste) ou un bloc (**certification**, par la direction). Ajouté et jamais modifié ; la dernière ligne fait foi. **Ce n'est pas une validation** : seul le jury valide, hors de la plateforme. |
| **À renouveler** | Une certification dont la date « valable jusqu'au », facultative, est passée au jour où l'on lit. Calculé à la lecture, jamais écrit. |
| **Pièce justificative** | Un fichier ou un lien joint à une déclaration, avec une nature d'une liste fermée. Le plugin ne la lit pas ni ne la juge ; il garde l'empreinte d'un fichier et la liste de celles qu'une signature couvrait. |
| **Empreinte** | Ici, toujours le **SHA-256** d'un contenu : elle prouve qu'un texte ou une archive n'a pas changé. |
| **Archive** | L'export figé du dossier, avec son manifeste d'empreintes et un PDF imprimable, pour le jury ; vérifiée par ses empreintes ; reprise par un autre site sous conditions ; sans les dépôts, les avis ni les précisions. |
| **Suivre** | Détenir `viewassigned` dans le contexte utilisateur du vaeiste, directement ou par cohorte : la seule façon de lire le dossier d'un autre sans `viewany`. |

*Fil d'Argent — Jean-Louis Martinez, jeanlouis@martinez.pro*
