#!/usr/bin/env bash
#
# MC-2: no direct write into the tables of the core.
#
# Compensating measure of DER-referentiel-001: everything goes through \core_competency\api, for every table prefixed
# competency_ (and user_evidence, its former name). This check did not exist until 12 October 2026; the full review of
# that day found MC-2 broken once without anything saying so.
#
# ONE EXCEPTION ONLY, NAMED IN THE DEROGATION AND LOCATED HERE TO THE FUNCTION:
#   classes/local/competency_gateway.php, function relabel_evidence(): the renaming of the evidence of the core at
#   uninstallation (see the derogation, "Named exception to MC-2").
# The exemption is on the pair (path, function), not on the file: a second direct write added to the gateway fails this
# check as it would anywhere else.
#
# Tests are out of scope, as for MC-5: a test that prepares or checks the core must be able to touch it.
#
# Usage: scripts/check-core-writes.sh [root]

set -uo pipefail

ROOT="${1:-.}"

# Perl reads each file whole: the call and the table name are often on two lines, which a grep line by line misses.
FOUND=$(find "$ROOT" -name '*.php' -not -path '*/.git/*' -not -path '*/tests/*' \
  -not -path '*/vendor/*' -not -path '*/node_modules/*' -print0 \
  | xargs -0 perl -0777 -ne '
      my $f = $ARGV;
      my $write = qr/\$DB->(?:insert_records?|update_record|set_fields?|set_field_select|delete_records(?:_select|_list)?)\(\s*[\x27"](?:competency|user_evidence)[a-z_]*[\x27"]/;
      my $sql = qr/(?:UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+\{(?:competency|user_evidence)/i;
      while (/$write|$sql/g) {
          my $pos = $-[0];
          my $before = substr($_, 0, $pos);
          my $line = ($before =~ tr/\n//) + 1;
          my ($function) = $before =~ /.*function\s+(\w+)/s;
          $function //= "(outside any function)";
          next if $f =~ m{(^|/)classes/local/competency_gateway\.php$} && $function eq "relabel_evidence";
          print "$f:$line: $function()\n";
      }' || true)

if [ -n "$FOUND" ]; then
  echo "✗ MC-2 broken: direct write into a table of the core"
  echo
  echo "$FOUND"
  echo
  echo "  Go through \\core_competency\\api, by \\tool_vae\\local\\competency_gateway."
  echo "  See DER-referentiel-001, compensating measure MC-2."
  exit 1
fi

echo "✓ MC-2: no direct write into the core outside the named exception"
exit 0
