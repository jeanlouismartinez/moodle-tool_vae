# tool_vae: validation loops
#
# Level 2. Without the jlm-socle repository, this Makefile carries the checks that run offline and without an
# installed Moodle. `make validate` tells three outcomes apart: ✓ passed, ✗ failed, ○ NOT RUN (missing prerequisite):
# a report that says "all green" when it skipped checks is worse than no report.
#
# Development files: none of them is in the package of the plugin (.gitattributes, export-ignore).

PLUGIN  := tool_vae
ROOT    := .
PHP     := php
SOURCES := $(shell find $(ROOT) -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' 2>/dev/null)
# Folder node_modules holding ajv 8 and ajv-formats 3, for `make schema`.
AJV_MODULES ?=

.DEFAULT_GOAL := help

.PHONY: help validate doctor lint codestyle gateway core-writes network-door secrets schema doc-links english ci

help:
	@echo "tool_vae: available targets"
	@echo
	@echo "  make validate     everything required at the declared level"
	@echo "  make doctor       what is missing, and what it prevents"
	@echo "  make lint         PHP syntax of every file"
	@echo "  make codestyle    Moodle coding standard (phpcs + moodlehq/moodle-cs)"
	@echo "  make gateway      MC-5: no call to the core outside the gateway"
	@echo "  make core-writes  MC-2: no direct write into the tables of the core"
	@echo "  make network-door MC-30: one outgoing door, never scheduled (and the self-test of the guard)"
	@echo "  make secrets      secrets in the staged diff"
	@echo "  make schema       pivot schema: examples accepted, counter-examples refused"
	@echo "  make doc-links    no published file points to a missing or unpublished document"
	@echo "  make english      no code comment written in French"
	@echo
	@echo "  Not covered here, required before merging: moodle-plugin-ci"
	@echo "  (codechecker, validate, savepoints, mustache, grunt, phpunit, behat)"

schema:
	@echo "── Pivot schema (docs/schema)"
	@if ! command -v node > /dev/null 2>&1; then \
	  echo "  ○ NOT RUN: node missing."; \
	elif ! AJV_MODULES="$(AJV_MODULES)" node -e "const m = process.env.AJV_MODULES; require(m ? require('path').join(m, 'ajv/dist/2020') : 'ajv/dist/2020')" > /dev/null 2>&1; then \
	  echo "  ○ NOT RUN: ajv 8 not found."; \
	  echo "     make schema AJV_MODULES=<node_modules folder holding ajv and ajv-formats>"; \
	else \
	  AJV_MODULES="$(AJV_MODULES)" node scripts/check-schema.mjs $(ROOT); \
	fi

validate: doctor lint codestyle gateway core-writes network-door secrets schema doc-links english
	@echo
	@echo "══════════════════════════════════════════════"
	@echo "  ✓ Offline checks: all green"
	@echo
	@echo "  ○ NOT RUN here: needs an installed Moodle:"
	@echo "     phpunit           tests of the plugin (tests/), including the language strings"
	@echo "                       (lang_strings_test) and the privacy coverage (privacy_coverage_test)"
	@echo "     privacy           tests of the Privacy API of the core"
	@echo "     behat             scenarios of the plugin (tests/behat/), including axe-core with JavaScript"
	@echo "     Last figures read: section Verified of the top entry of CHANGELOG.md"
	@echo
	@echo "  ○ NOT RUN: no tool for it:"
	@echo "     manual test with the keyboard and a screen reader"
	@echo "     third-party human review: rights, personal data, licence"
	@echo "══════════════════════════════════════════════"

doctor:
	@bash scripts/doctor.sh $(ROOT)

lint:
	@echo "── PHP syntax"
	@errors=0; \
	for f in $(SOURCES); do \
	  $(PHP) -l $$f > /dev/null 2>&1 || { echo "  ✗ $$f"; $(PHP) -l $$f; errors=1; }; \
	done; \
	if [ $$errors -eq 0 ]; then \
	  echo "  ✓ $(words $(SOURCES)) files without a syntax error"; \
	else exit 1; fi

codestyle:
	@echo "── Moodle coding standard"
	@PHPCS=$$(command -v phpcs || echo /opt/moodle-cs/vendor/bin/phpcs); \
	if [ -x "$$PHPCS" ]; then \
	  $$PHPCS --report=summary --no-colors $(ROOT) && echo "  ✓ no violation"; \
	else \
	  echo "  ○ NOT RUN: phpcs missing."; \
	  echo "     composer require moodlehq/moodle-cs, then phpcs --report=summary ."; \
	  echo "     The rules of the plugin are in phpcs.xml.dist."; \
	fi

gateway:
	@echo "── MC-5"
	@bash scripts/check-gateway.sh $(ROOT)

core-writes:
	@echo "── MC-2"
	@bash scripts/check-core-writes.sh $(ROOT)

network-door:
	@echo "── MC-30"
	@bash scripts/check-network-door.sh $(ROOT)
	@bash scripts/test-check-network-door.sh

secrets:
	@echo "── Secrets"
	@bash scripts/check-secrets.sh

doc-links:
	@echo "── References to docs/"
	@node scripts/check-doc-links.mjs $(ROOT)

english:
	@echo "── English comments"
	@node scripts/check-english-comments.mjs $(ROOT)

# Target of the continuous integration: adds what needs an installation.
ci: validate
	@echo "── moodle-plugin-ci"
	@command -v moodle-plugin-ci > /dev/null 2>&1 \
	  && moodle-plugin-ci validate \
	  || echo "  ○ NOT RUN: moodle-plugin-ci missing from this machine"
