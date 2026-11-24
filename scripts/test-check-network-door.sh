#!/usr/bin/env bash
#
# Self-test of scripts/check-network-door.sh (MC-30): it passes on a clean tree, fails on every kind of violation and
# refuses a directory that is not the root of a plugin.
#
# A GUARD THAT FINDS NOTHING PROVES NOTHING. Until 19 November 2026 a PHPUnit test ran the shell script on these trees;
# a test of the plugin no longer runs a shell (the package installs no development script), so the proof of the shell
# script lives here, with the script. tests/fiche_no_background_test.php plays the same trees against its PHP twin.
#
# Usage: scripts/test-check-network-door.sh

set -uo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
GUARD="$HERE/check-network-door.sh"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
FAILURES=0
COUNT=0

# make_tree <name> [<relative path> <content>]...: a minimal plugin tree, with the given files over the base ones.
make_tree() {
  local root="$WORK/$1"
  shift
  mkdir -p "$root/classes/local" "$root/classes/event"
  printf '<?php\n' > "$root/version.php"
  printf "<?php\n'verify' => true; 'allow_redirects' => false;\n" > "$root/classes/local/fiche_fetcher.php"
  printf '<?php\n' > "$root/classes/local/fiche_import.php"
  printf '<?php\n' > "$root/import_fiches.php"
  printf '<?php\n' > "$root/classes/event/export_fetched.php"
  while [ "$#" -ge 2 ]; do
    mkdir -p "$(dirname "$root/$1")"
    printf '%s' "$2" > "$root/$1"
    shift 2
  done
  echo "$root"
}

# expect <code> <label> <root>: the guard ends with this exit code on this tree.
expect() {
  local code="$1" label="$2" root="$3" got
  bash "$GUARD" "$root" > "$WORK/out.txt" 2>&1
  got=$?
  COUNT=$((COUNT + 1))
  if [ "$got" -ne "$code" ]; then
    echo "  ✗ $label: exit $got, expected $code"
    sed 's/^/      /' "$WORK/out.txt"
    FAILURES=$((FAILURES + 1))
  fi
}

expect 0 "a clean tree" "$(make_tree clean)"
expect 1 "a client named elsewhere" "$(make_tree t1 classes/local/other.php $'<?php\n// the HTTP_CLIENT of the core\n')"
expect 1 "a socket in a page" "$(make_tree t2 index.php $'<?php\nfsockopen(\'x\', 80);\n')"
expect 1 "a url opened in a script of the amd directory" \
  "$(make_tree t3 amd/src/x.js $'file_get_contents(\'https://x.example/\')\n')"
expect 1 "the door named by an observer" "$(make_tree t4 classes/observer.php $'<?php\nnew \\tool_vae\\local\\fiche_fetcher();\n')"
expect 1 "the door called with another case" "$(make_tree t5 classes/observer.php $'<?php\n(new X())->From_Data_Gouv([]);\n')"
expect 1 "the class that calls the door, named by an observer" \
  "$(make_tree t6 classes/observer.php $'<?php\n$import = new \\tool_vae\\local\\Fiche_Import();\n')"
expect 1 "the door named by the back office" "$(make_tree t7 classes/task/job.php $'<?php\nfiche_import::from_data_gouv();\n')"
expect 1 "the door named by a command line script" "$(make_tree t8 cli/fetch.php $'<?php\n$x->from_data_gouv();\n')"
expect 1 "a loosened door" "$(make_tree t9 classes/local/fiche_fetcher.php $'<?php\n\'verify\' => false;\n')"
expect 1 "a door that follows redirections" \
  "$(make_tree t10 classes/local/fiche_fetcher.php $'<?php\n\'verify\' => true; \'allow_redirects\' => true;\n')"
expect 1 "a door that follows them with options" \
  "$(make_tree t11 classes/local/fiche_fetcher.php $'<?php\n\'verify\' => true; \'allow_redirects\' => [\'max\' => 3];\n')"
# The exemption is a whole path: another file with the name of the door is not the door.
expect 1 "another file with the name of the door" \
  "$(make_tree t12 classes/other/fiche_fetcher.php $'<?php\n\\core\\http_client::class;\n')"
# A root that is not the plugin's reads nothing, and says so.
mkdir -p "$WORK/empty"
expect 2 "a directory that is not a plugin" "$WORK/empty"

if [ "$FAILURES" -ne 0 ]; then
  echo "✗ MC-30 self-test: $FAILURES of $COUNT cases wrong"
  exit 1
fi
echo "✓ MC-30 self-test: $COUNT cases, the guard passes, fails and refuses where it must"
exit 0
