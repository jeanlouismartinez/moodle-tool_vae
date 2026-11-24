@tool @tool_vae
Feature: The plugin installs and refuses to work without the competency subsystem
  In order to build a dossier of prior learning
  As a candidate or an administrator
  I need the plugin to be reachable, and to say plainly when it cannot work

  # EVERY STRING ASSERTED HERE IS THE ENGLISH ONE, AND THAT IS NOT A PREFERENCE.
  # The acceptance test site runs in English; it always has. This file used to assert the
  # French wording of an establishment's site,
  # "Aucune fiche de certification n'a encore été importée.",
  # which no page of the test site ever renders. All three scenarios were therefore
  # incapable of passing, and the file read as coverage while covering nothing.
  # Found on 28 August 2026, the first time Behat was actually run on this plugin.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | candidat | Camille   | Durand   | camille@example.com  |
      | autre    | Alex      | Martin   | alex@example.com     |

  @javascript
  Scenario: The administration page is reachable and reports the empty state
    Given I log in as "admin"
    When I navigate to "Competencies > VAE > VAE - pathway" in site administration
    Then I should see "No certification record has been imported yet."

  Scenario: The plugin explains itself when competencies are switched off
    Given the following config values are set as admin:
      | enabled | 0 | core_competency |
    And I log in as "admin"
    When I am on the "tool_vae > Index" page
    Then I should see "The competency subsystem is switched off on this site."

  # THE NEXT SCENARIO WAS BORN OF A DEFECT THAT 101 UNIT TESTS DID NOT SEE.
  #
  # declare.php died on `Class "moodleform" not found`: the form class extends moodleform, which
  # lives in lib/formslib.php and is NOT loaded by the autoloader. Every unit test loaded that
  # library itself in its setUp() - so none of them could see that the page did not load it.
  # Found on 29 August 2026 by using the plugin in a browser, not by testing it.
  #
  # A harness that loads what the page does not load hides a fatal error. Only a test that
  # ASKS FOR THE PAGE can see it: this one.
  Scenario: The declaration form opens for a candidate
    Given the following "tool_vae > sources" exist:
      | fichecode | ficherang | shortname                                |
      | RNCP36832 | C03       | Spot the risk factors for falls          |
    And the following "permission overrides" exist:
      | capability               | permission | role | contextlevel | reference |
      | tool/vae:declare | Allow      | user | User         | candidat  |
    And I log in as "candidat"
    When I am on the "tool_vae > Declare" page
    Then I should see "Competency claimed"
    And I should see "Describe what you did, not who you did it for"

  # THE SAME REASONING FOR THE IMPORT, AND FOR THE SAME REASON.
  #
  # Lot 6 adds an HTTP page and a second form, so it reopens exactly the setting of
  # 29 August 2026: a class that extends moodleform, unit tests that load formslib themselves,
  # and nobody to ask for the page. The adversarial review of 29 August 2026 noted it -
  # `grep import_form tests/` returned nothing.
  #
  # WHAT IT CATCHES, MEASURED BY MUTATION ON 30 AUGUST 2026, NOT ASSUMED:
  #   - admin_externalpage_setup on a page that is not declared → red ("Section error")
  #   - a form element or a language string missing → red
  #
  # WHAT IT DOES NOT CATCH, AND IT MUST BE SAID: removing the require_once of formslib.php from
  # import_form.php leaves this scenario GREEN. On that page, admin_externalpage_setup builds the
  # administration tree, which loads the settings.php of every plugin, several of which load
  # formslib; the parent class therefore arrives by accident. The require_once stays right - a
  # class must not depend on an accident - but it is guarded by declare.php, which does not go
  # through adminlib, and by it alone.
  # A scenario that claims more than it proves is exactly what this project holds against the
  # tests it had to rewrite.
  Scenario: The import form opens and refuses a file it cannot read
    Given I log in as "admin"
    When I am on the "tool_vae > Import" page
    Then I should see "CSV file"
    And I should see "File encoding"
    And I should see "Provenance"

  # AND THE LIST MUST SAY WHAT THE IMPORT RECORDED.
  #
  # A competency that left the record was faithfully noted in tool_vae_obsolete and shown
  # nowhere: decision 3 keeps the evidence, it does not allow silence.
  # Found in adversarial review on 29 August 2026.
  Scenario: The administration page states what an import recorded
    Given the following "tool_vae > sources" exist:
      | fichecode | ficherang | shortname                       |
      | RNCP36832 | C03       | Spot the risk factors for falls |
    And I log in as "admin"
    When I am on the "tool_vae > Index" page
    Then I should see "RNCP36832"
    And I should see "Import a CSV file (special case)"
    And I should see "Import records from France compétences"

  # THE THIRD SCENARIO WAS REMOVED, AND ITS ABSENCE IS DELIBERATE.
  #
  # It read: a candidate without an explicit grant cannot read anyone's dossier, and it was the
  # regression test for the privilege escalation found in adversarial review. Two things were
  # wrong with it, and only running Behat could show either.
  #
  # First, its step had its two arguments inverted, so the core resolved the page type as
  # "autre" and threw `Unrecognised core page type` before reaching this plugin at all.
  #
  # Second, and this one is not fixable: Behat FAILS ANY STEP THAT RENDERS A MOODLE EXCEPTION.
  # behat_hooks::look_for_exceptions() runs after every step, and a permission refusal is an
  # exception page. The plugin does refuse - verified, the run showed
  # `required_capability_exception` raised at access.php line 54 - but the refusal cannot be
  # expressed as a passing scenario. There is no such scenario anywhere in the Moodle core
  # either: grep for the refusal wording across every .feature file of 4.5 returns nothing.
  #
  # The guarantee is not lost, it lives where it can actually run: tests/access_test.php,
  # `test_a_plain_authenticated_user_holds_nothing_by_default` and
  # `test_viewown_is_not_enough_to_read_someone_elses_dossier`. A scenario that cannot pass is
  # worse than no scenario: it makes a file look like it covers something.
  #
  # REVISITED ON 12 OCTOBER 2026 (lot 8 ter). "Not fixable" was too strong: the hook only looks
  # at the page left open when a step ends. access_control.feature reads the refusal inside one
  # custom step and leaves the page before the step returns, so refusals by direct URL are now
  # scenarios - see that file. This scenario stays removed; that one replaces it.
