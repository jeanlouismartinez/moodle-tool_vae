#!/usr/bin/env bash
#
# Moodle coding standard (moodle-cs), run locally against the COMMITTED state of the plugin, as the continuous
# integration does (`moodle-plugin-ci phpcs --max-warnings 0`).
#
# WHY. On 15 October 2026 the continuous integration of lot T-81 failed on this check while PHPUnit and Behat passed: a
# variable `$byNumber`, a split `foreach`, badly sorted language strings. None of the offline checks of the repository
# ran it. It is a standing rule: it is run before pushing.
#
# WHAT THE SCRIPT DOES
#   1. installs moodlehq/moodle-cs in /tmp/mcs of the container moodle53-web, outside the repository, the first time
#      only (composer, from packagist.org);
#   2. copies the committed state (`git archive HEAD`, which leaves out what .gitattributes marks export-ignore, as the
#      package does) into /tmp/vae-src of the container, with the ruleset of the repository (phpcs.xml.dist);
#   3. normalises the line endings: on Windows, `git archive` may write CRLF, while the repository is in LF and the
#      standard requires it;
#   4. runs phpcs with that ruleset and prints the report.
#
# Exit code: 0 if the standard is met, non-zero otherwise. Warnings count as errors, as in the continuous integration
# (--max-warnings 0).
#
# Usage: scripts/codestyle.sh [container]

set -uo pipefail

CONTAINER="${1:-moodle53-web}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export MSYS_NO_PATHCONV=1

if ! docker exec "$CONTAINER" true 2>/dev/null; then
    echo "✗ the container $CONTAINER does not answer (Docker Desktop, then: docker start moodle53-db moodle53-web)"
    exit 2
fi

if ! docker exec -u www-data "$CONTAINER" test -x /tmp/mcs/vendor/bin/phpcs; then
    echo "· installing moodle-cs in /tmp/mcs (once)"
    docker exec -u www-data "$CONTAINER" sh -c '
        mkdir -p /tmp/mcs && cd /tmp/mcs &&
        php -d memory_limit=-1 /var/www/html/composer.phar require --dev moodlehq/moodle-cs \
            --no-interaction --no-progress --no-plugins >/dev/null 2>&1 ||
        php -d memory_limit=-1 /var/www/html/composer.phar install --no-plugins --no-interaction --no-progress >/dev/null 2>&1;
        vendor/bin/phpcs --config-set installed_paths \
/tmp/mcs/vendor/moodlehq/moodle-cs,/tmp/mcs/vendor/phpcsstandards/phpcsextra,/tmp/mcs/vendor/phpcsstandards/phpcsutils >/dev/null'
    if ! docker exec -u www-data "$CONTAINER" test -x /tmp/mcs/vendor/bin/phpcs; then
        echo "✗ moodle-cs cannot be installed (network? composer?)"
        exit 2
    fi
fi

cd "$ROOT" || exit 2
git archive HEAD | docker exec -i -u www-data "$CONTAINER" sh -c '
    rm -rf /tmp/vae-src && mkdir -p /tmp/vae-src && tar -x -C /tmp/vae-src &&
    find /tmp/vae-src -name "*.php" -exec sed -i "s/\r$//" {} +'
git show HEAD:phpcs.xml.dist | docker exec -i -u www-data "$CONTAINER" sh -c 'cat > /tmp/vae-src/phpcs.xml.dist'

docker exec -u www-data "$CONTAINER" sh -c '
    cd /tmp/mcs &&
    vendor/bin/phpcs --standard=/tmp/vae-src/phpcs.xml.dist --extensions=php -s --report=full /tmp/vae-src'
STATUS=$?

if [ "$STATUS" -eq 0 ]; then
    echo "✓ Moodle coding standard: no error, no warning (committed state HEAD)"
else
    echo "✗ Moodle coding standard: see the report above (phpcbf fixes the lines marked [x] by itself)"
fi
exit "$STATUS"
