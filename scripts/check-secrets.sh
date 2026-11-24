#!/usr/bin/env bash
#
# Secrets in the staged diff.
#
# THIS CHECK ONCE CRIED WOLF. The first version looked for the words `secret`, `password`, `key`. It flagged the text of
# the GPL ("must require no special password or key"), the name of its own make target, and its own regular expression.
# A check that fails on correct code is turned off within the week, and then protects nothing.
#
# So it looks for VALUES, not WORDS: the assignment of a sensitive name to a string long enough to be a real secret, or
# the fingerprint of a recognisable token.
#
# ACCEPTED LIMIT: this check does not find a secret stored in a variable with an innocent name, nor a secret already in
# the history. It closes the most frequent door, not every door. The rule stays: no secret in the code NOR in the Git
# history.
#
# Usage: scripts/check-secrets.sh [--all]

set -uo pipefail

if ! command -v git > /dev/null 2>&1 || [ ! -d .git ]; then
  echo "  ○ NOT RUN: no git repository"
  exit 0
fi

# Assignment of a sensitive name to a value of at least 8 characters.
ASSIGNMENT='(api[_-]?key|apikey|secret[_-]?key|client[_-]?secret|auth[_-]?token|access[_-]?token|passwd|password)["'"'"']?\s*[:=]>?\s*["'"'"'][^"'"'"']{8,}'
# Fingerprints of recognisable tokens, whatever the name of the variable.
TOKENS='(-----BEGIN [A-Z ]*PRIVATE KEY-----|xox[baprs]-[A-Za-z0-9-]{10,}|ghp_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{20,}|AKIA[0-9A-Z]{16}|sk-[A-Za-z0-9]{24,}|AIza[0-9A-Za-z_-]{30,})'

if [ "${1:-}" = "--all" ]; then
  TARGET=$(git ls-files -- ':!LICENSE' ':!scripts/check-secrets.sh')
  # shellcheck disable=SC2086
  FOUND=$(grep -EIn "$ASSIGNMENT|$TOKENS" $TARGET 2>/dev/null)
else
  # The staged diff, added lines only. The line of this script itself and the text of the licence are left out: they
  # are not code and hold no value.
  FOUND=$(git diff --cached -U0 -- ':!LICENSE' ':!scripts/check-secrets.sh' \
    | grep -E '^\+' \
    | grep -Ei "$ASSIGNMENT|$TOKENS")
fi

if [ -n "$FOUND" ]; then
  echo "  ✗ value that looks like a secret:"
  echo "$FOUND" | sed 's/^/      /'
  echo
  echo "    A secret in the Git history stays there. Remove it BEFORE committing."
  exit 1
fi

echo "  ✓ no value that looks like a secret"
exit 0
