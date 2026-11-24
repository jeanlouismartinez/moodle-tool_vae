#!/usr/bin/env bash
#
# MC-30: one door onto the network, and never opened from the background.
#
# Up to 0.47.0 the plugin made NO request of its own. The button "Fetch the latest export" of the import screen opens one,
# and that promise only holds if it stays narrow: one class, and nothing that runs without a person clicking. This check
# reads the SOURCES, comments included (like MC-5: a guard with a list of exceptions is no more than a list of exceptions),
# whatever the case, and fails if:
#
#   1. a PHP, JS or inc file of the plugin - every one, not only classes/ and the root - OTHER than
#      classes/local/fiche_fetcher.php names an HTTP client, curl, a socket, a name resolution, a SOAP client, or opens an
#      http(s) address with file_get_contents, fopen, readfile or copy;
#   2. fiche_fetcher.php itself turns a security check off: ignoresecurity, CURLOPT_SSL, skipcertverify, `verify` other
#      than true, `allow_redirects` other than false (redirections are judged by hand, before they are followed);
#   3. db/tasks.php or a file of classes/task/ names the door (fiche_fetcher), what calls it (fiche_import) or the HTTP
#      client: the button is the only trigger;
#   4. the door or what calls it (fiche_fetcher, from_data_gouv) is named anywhere but in the four files allowed to: the
#      door itself, fiche_import.php, import_fiches.php and classes/event/export_fetched.php; or the class that calls it
#      (fiche_import) is named anywhere but in the seven files that name it (the case does not count, and the name of a
#      method can be built: it is the name of the class that is guarded). An observer, a web service, a command line
#      script or a new file does not open the door.
#
# THE EXEMPTION IS A WHOLE PATH, compared in full (the lesson of MC-5: `grep --exclude` only compares the base name).
# lang/, tests/, scripts/ and docs/ are not read: strings and tests may talk about the network.
# tests/fiche_no_background_test.php runs the same guard in PHPUnit, wherever the plugin is installed.
#
# EVERY BRANCH ENDS WITH AN EXPLICIT EXIT CODE (the fault that once made MC-5 useless: a `grep -v` at the end of a
# pipeline gives the status of the last link); run from a directory that is not the root of the plugin, it fails
# (code 2) instead of finding nothing. scripts/test-check-network-door.sh proves it on small trees.
#
# Usage: scripts/check-network-door.sh [root]

set -uo pipefail

ROOT="${1:-.}"
DOOR='classes/local/fiche_fetcher.php'

if [ ! -d "$ROOT/classes" ] || [ ! -f "$ROOT/version.php" ]; then
  echo "✗ MC-30: \"$ROOT\" is not the root of the plugin (classes/ or version.php is missing): nothing was checked"
  exit 2
fi

# What no other file may contain.
FORBIDDEN='http_client|GuzzleHttp|curl_init|curl_exec|curl_multi|curl_setopt|CURLOPT|FOLLOWLOCATION|new[[:space:]]+\\?curl([^_[:alnum:]]|$)|\\curl::|download_file_content|create_file_from_url|fsockopen|stream_socket_client|SoapClient|dns_get_record|gethostbyname|(^|[^_[:alnum:]>:$])get_headers[[:space:]]*\(|(file_get_contents|fopen|readfile|copy|file|simplexml_load_file)[[:space:]]*\([[:space:]]*['"'"'"]https?:'

# What the door itself must never contain.
LOOSENED="ignoresecurity|CURLOPT_SSL|skipcertverify|verify['\"]?[[:space:]]*=>[[:space:]]*[^t[:space:]]|allow_redirects['\"]?[[:space:]]*=>[[:space:]]*[^f[:space:]]"

# What runs without a click.
SCHEDULED='fiche_fetcher|fiche_import([^_[:alnum:]]|$)|from_data_gouv|http_client'

# What names the door or what calls it.
NAMED='fiche_fetcher|from_data_gouv'

# The four files allowed to name the door (whole paths, compared in full).
NAMERS=("$DOOR" "classes/local/fiche_import.php" "import_fiches.php" "classes/event/export_fetched.php")

# The class that calls the door (fiche_import): a PHP method has no case and its name can be built, so not only the name of
# the method is guarded, but also who names the class. The seven files that name it today.
IMPORTER='fiche_import([^_[:alnum:]]|$)'
IMPORTERS=(
  "classes/local/fiche_import.php" "classes/local/fiche_screen.php" "classes/local/fiche_plan_store.php"
  "classes/form/fiche_import_form.php" "classes/form/import_form.php" "classes/event/record_imported.php" "import_fiches.php"
)

# The sources of the plugin that run on the server or in the browser: everything, but what is not code of the plugin.
sources() {
  (
    cd "$ROOT" && find . \
      \( -path ./.git -o -path ./node_modules -o -path ./vendor -o -path ./tests -o -path ./docs -o -path ./lang -o -path ./scripts \) -prune \
      -o -type f \( -name '*.php' -o -name '*.js' -o -name '*.inc' \) -print \
      | sed 's#^\./##' | LC_ALL=C sort
  )
}

is_one_of() { # is_one_of <file> <path>...: the file is one of these whole paths
  local file="$1" allowed
  shift
  for allowed in "$@"; do
    [ "$file" = "$allowed" ] && return 0
  done
  return 1
}

ALL=$(sources)
if [ -z "$ALL" ]; then
  echo "✗ MC-30: no file to check under \"$ROOT\": the check read nothing"
  exit 2
fi

FOUND_ELSEWHERE=""
FOUND_LOOSENED=""
FOUND_SCHEDULED=""
FOUND_NAMED=""

while IFS= read -r file; do
  path="$ROOT/$file"

  if [ "$file" != "$DOOR" ]; then
    found=$(LC_ALL=C grep -naiE "$FORBIDDEN" "$path" | sed "s#^#$file:#" || true)
    [ -n "$found" ] && FOUND_ELSEWHERE+="$found"$'\n'
  else
    found=$(LC_ALL=C grep -naiE "$LOOSENED" "$path" | sed "s#^#$file:#" || true)
    [ -n "$found" ] && FOUND_LOOSENED+="$found"$'\n'
  fi

  case "$file" in
    db/tasks.php | classes/task/*.php)
      found=$(LC_ALL=C grep -naiE "$SCHEDULED" "$path" | sed "s#^#$file:#" || true)
      [ -n "$found" ] && FOUND_SCHEDULED+="$found"$'\n'
      ;;
  esac

  if ! is_one_of "$file" "${NAMERS[@]}"; then
    found=$(LC_ALL=C grep -naiE "$NAMED" "$path" | sed "s#^#$file:#" || true)
    [ -n "$found" ] && FOUND_NAMED+="$found"$'\n'
  fi
  if ! is_one_of "$file" "${IMPORTERS[@]}"; then
    found=$(LC_ALL=C grep -naiE "$IMPORTER" "$path" | sed "s#^#$file:#" || true)
    [ -n "$found" ] && FOUND_NAMED+="$found"$'\n'
  fi
done <<<"$ALL"

if [ ! -f "$ROOT/$DOOR" ]; then
  FOUND_LOOSENED+="$DOOR: the door does not exist"$'\n'
fi

FOUND=$(printf '%s%s%s%s' "$FOUND_ELSEWHERE" "$FOUND_LOOSENED" "$FOUND_SCHEDULED" "$FOUND_NAMED" | grep -v '^$' || true)

if [ -n "$FOUND" ]; then
  echo "✗ MC-30 broken: the outgoing door is not the only one, or it is loosened, or something schedules or names it"
  echo
  echo "$FOUND"
  echo
  echo "  Only classes/local/fiche_fetcher.php may name an HTTP client (verify, redirections judged by hand);"
  echo "  nothing in db/tasks.php or classes/task/ may call it; only fiche_import.php, import_fiches.php and"
  echo "  classes/event/export_fetched.php may name the door, and only seven files name the class fiche_import."
  exit 1
fi

echo "✓ MC-30: one outgoing door, never scheduled ($(printf '%s\n' "$ALL" | wc -l | tr -d ' ') files read)"
exit 0
