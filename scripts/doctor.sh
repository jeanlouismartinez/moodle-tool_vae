#!/usr/bin/env bash
#
# doctor: what is missing, and what it prevents.
#
# The base set of rules tells three outcomes apart: ✓ passed, ✗ failed, ○ NOT RUN (missing prerequisite). A report that
# says "all green" when it skipped checks is worse than no report.
#
# Usage: scripts/doctor.sh [root]

set -uo pipefail

ROOT="${1:-.}"
MISSING=0
ERRORS=0

title()  { echo; echo "── $1"; }
ok()     { echo "  ✓ $1"; }
absent() { echo "  ○ NOT RUN: $1"; MISSING=$((MISSING+1)); }
wrong()  { echo "  ✗ $1"; ERRORS=$((ERRORS+1)); }

title "Tools"
command -v php  >/dev/null && ok "php $(php -r 'echo PHP_VERSION;')" || absent "php missing: lint impossible"
command -v git  >/dev/null && ok "git" || absent "git missing: the check of secrets cannot run"
command -v node >/dev/null && ok "node $(node --version)" \
  || absent "node missing: make schema, make doc-links and make english cannot run"

title "Files required by moodle-plugin-ci validate"
[ -f "$ROOT/version.php" ] && ok "version.php" || wrong "version.php missing: validate fails"
[ -f "$ROOT/lang/en/tool_vae.php" ] && ok "lang/en/tool_vae.php" || wrong "lang/en missing: validate fails"

title "Files required by the base set of rules"
[ -f "$ROOT/LICENSE" ]   && ok "LICENSE" || wrong "LICENSE missing: rule 7 of the irreducible base"
[ -f "$ROOT/README.md" ] && ok "README.md" || wrong "README missing: rule 8 of the irreducible base"
[ -f "$ROOT/CHANGELOG.md" ] && ok "CHANGELOG.md" || wrong "CHANGELOG missing"
# The derogation and the legal memo are documents of the working repository of the project owner: the publication
# procedure removes from docs/ everything that is not published (the folder of derogations included). Their two checks
# therefore apply where that folder exists (working repository) and say, elsewhere (public repository), that they do not
# apply. The file names are built here so that no published file names an unpublished document.
DEROGATIONS="$ROOT/docs/derogations"
DEROGATION="DER-referentiel-001-modele-natif"
MEMO="MEMO-JURIDIQUE"
if [ -d "$DEROGATIONS" ]; then
  [ -f "$DEROGATIONS/$DEROGATION.md" ] \
    && ok "DER-referentiel-001" || wrong "derogation missing: the native model is not covered"
else
  absent "DER-referentiel-001: a check of the working repository of the project owner, where the derogation is kept;"\
" not applicable here"
fi

title "Files of the base set of rules not produced yet"
[ -f "$ROOT/VERSIONS.md" ]  && ok "VERSIONS.md" || absent "VERSIONS.md: MC-4 (register of deprecations) cannot be checked"
[ -f "$ROOT/ARCHITECTURE.md" ] && ok "ARCHITECTURE.md" || absent "ARCHITECTURE.md: encapsulations and gaps not traced"
if [ -d "$DEROGATIONS" ]; then
  [ -f "$ROOT/docs/$MEMO.md" ] && ok "$MEMO.md" || absent "$MEMO.md: PO-2 (LO 2.0 / GPLv3) not examined"
else
  absent "legal memo: PO-2 (LO 2.0 / GPLv3), a check of the working repository of the project owner, not applicable here"
fi

title "Checks that need an installed Moodle"
echo "  · privacy coverage of every table: tests/privacy_coverage_test.php (PHPUnit)"
echo "  · strings of the capabilities and message providers, parity and order of the languages:"\
" tests/lang_strings_test.php (PHPUnit)"

title "Consistency of the current lot"

if [ -f "$ROOT/db/upgrade.php" ]; then
  grep -q 'function xmldb_tool_vae_upgrade' "$ROOT/db/upgrade.php" \
    && ok "db/upgrade.php: function name right" \
    || wrong "db/upgrade.php present with a wrong signature: validate fails"
else
  ok "db/upgrade.php absent: right as long as there is nothing to upgrade"
fi

title "Behat tests"
for f in "$ROOT"/tests/behat/*.feature; do
  [ -e "$f" ] || { absent "no .feature file"; break; }
  head -1 "$f" | grep -q '@tool ' && head -1 "$f" | grep -q '@tool_vae' \
    && ok "$(basename "$f"): tags @tool and @tool_vae" \
    || wrong "$(basename "$f"): validate requires both tags @tool and @tool_vae"
done

echo
echo "──────────────────────────────────────────────"
echo "  ✗ failures: $ERRORS      ○ not run: $MISSING"
echo
echo "  What no tool will check:"
echo "   - that no free field names a supported person"
echo "   - that no display prejudges the decision of the panel"
echo "   - that the authorship and the date of the framework are shown"
echo "   - that the exported archive can be read by a person in five years, without Moodle"
echo "──────────────────────────────────────────────"

[ "$ERRORS" -eq 0 ] || exit 1
exit 0
