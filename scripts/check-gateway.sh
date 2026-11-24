#!/usr/bin/env bash
#
# MC-5: no call to the core outside the gateway.
#
# Compensating measure of DER-referentiel-001. The native model deprives the project of the buffer a bridge normally is;
# the gateway rebuilds it inside the plugin. A failure here is a failure of the derogation, not a style warning.
#
# THIS CHECK WAS ONCE WRITTEN WRONG. The first version was:
#     grep -rn 'core_competency' … | grep -v gateway && echo "✗" && exit 1
# On a clean tree the `grep -v` finds nothing, exits with 1, the `&&` short-circuits, and the status of the pipeline stays
# 1: the check failed on correct code as on faulty code. It would have been turned off on the first day, and MC-5 never
# checked. Hence `grep -q` and an explicit exit in both branches.
#
# Two exemptions, named AND LOCATED:
#   - classes/local/competency_gateway.php: it is the gateway.
#   - classes/privacy/provider.php: the privacy provider must declare add_subsystem_link() to the subsystem, or the
#     Privacy API is wrong.
# They are on a PATH, not on a file name. `grep --exclude` only compares the base name: written that way, the exemption
# covered any provider.php of the tree, including a future classes/local/provider.php, which has no reason to call the
# core. Reported in an adversarial review on 28 August 2026.
# The check is literal: it reads comments as it reads code, and offers no other way out. A guard with a list of
# exceptions ends up being nothing but a list of exceptions.
#
# Usage: scripts/check-gateway.sh [root]

set -uo pipefail

ROOT="${1:-.}"

# TWO PATTERNS, BECAUSE THE CORE IS CALLED IN TWO WAYS. The first catches the class, the second the TABLE:
# `$DB->count_records('competency', ...)` in a presenter is exactly the same breach as a call to the api, and the previous
# version of this script, which only looked for the class name, answered "green" on it. Reported in an adversarial review
# on 29 August 2026.
# The second pattern targets the position of the argument, not the word: `'competencyframeworkid' =>`, which is an array
# key, must not count as an access to a table.
PATTERN='core_competency'
TABLE_PATTERN="\\\$DB->[a-z_]+\\(\\s*'(competency|user_evidence)"

COMMON=(
  --include='*.php' --include='*.js'
  --exclude-dir=.git --exclude-dir=node_modules --exclude-dir=vendor
)

# The filter is applied AFTER the grep, on the whole path, and its result is put in a variable: a `grep | grep -v` in a
# pipeline gives the status of the LAST link, which is exactly the fault B9 that made this check useless in its first
# version.
EXEMPT='classes/local/competency_gateway\.php:|classes/privacy/provider\.php:'

# The CLASS name is forbidden everywhere, tests included: a test that calls the api of the core would escape the LTS
# migration like any other file.
FOUND=$(grep -rn "$PATTERN" "$ROOT" "${COMMON[@]}" 2>/dev/null | grep -Ev "$EXEMPT" || true)

# The TABLE name is forbidden outside the gateway, EXCEPT under tests/: a test whose role is to prove that the gateway
# did write into the core must look at the core, or it proves nothing. The exemption is on a path, never on a file name.
FOUND_TABLE=$(grep -rnE "$TABLE_PATTERN" "$ROOT" "${COMMON[@]}" 2>/dev/null \
  | grep -Ev "$EXEMPT|(^|/)tests/" || true)

FOUND=$(printf '%s\n%s' "$FOUND" "$FOUND_TABLE" | grep -v '^$' || true)

if [ -n "$FOUND" ]; then
  echo "✗ MC-5 broken: the core is called outside the gateway"
  echo
  echo "$FOUND"
  echo
  echo "  Fix it by going through \\tool_vae\\local\\competency_gateway."
  echo "  See DER-referentiel-001, compensating measure MC-5."
  exit 1
fi

echo "✓ MC-5: no call to the core outside the gateway"
exit 0
