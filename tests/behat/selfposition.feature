@tool @tool_vae
Feature: The vaeiste positions themselves on every level of their record, the manager who follows reads it
  In order to say where I stand on my record without anyone marking me
  As a vaeiste, and as the manager who follows them
  I need a self-positioning I write myself, by a step in words, kept line by line, next to the word of the structure

  # Lot 1a. The record has four levels (record, domain, competency, skill). The vaeiste alone writes; the manager who follows
  # and the direction read. A withdrawal is a line; nothing is computed; nothing is a validation.

  Background:
    Given the following "users" exist:
      | username    | firstname | lastname  | email                |
      | candidat    | Camille   | Durand    | camille@example.com  |
      | responsable | Nadia     | Benali    | nadia@example.com    |
      | autre       | Omar      | Ailleurs  | omar@example.com     |
    And the following "roles" exist:
      | shortname | name                |
      | vaeiste   | Vaeiste role        |
      | follower  | Follows a candidate |
    And the following "permission overrides" exist:
      | capability             | permission | role     | contextlevel | reference |
      | tool/vae:declare       | Allow      | vaeiste  | System       |           |
      | tool/vae:viewown       | Allow      | vaeiste  | System       |           |
      | tool/vae:positionself  | Allow      | vaeiste  | System       |           |
      | tool/vae:viewassigned  | Allow      | follower | System       |           |
      | tool/vae:evaluate      | Allow      | follower | System       |           |
    And the following "role assigns" exist:
      | user        | role     | contextlevel | reference |
      | candidat    | vaeiste  | System       |           |
      | responsable | follower | User         | candidat  |
      | autre       | follower | User         | responsable |
    And the following "tool_vae > sources" exist:
      | fichecode | ficherang | name          | shortname          |
      | RNCP400   | D1        | Aide-soignant | Accompany the person |
    And the following "tool_vae > competencies" exist:
      | fichecode | ficherang | shortname                 | parent |
      | RNCP400   | C11       | Help with daily care      | D1     |
      | RNCP400   | H111      | Help the person to wash   | C11    |
      | RNCP400   | H112      | Help the person to dress  | C11    |
      | RNCP400   | D2        | Work in a team            |        |
    And the following "tool_vae > declarations" exist:
      | candidate | fichecode | ficherang | situation    |
      | candidat  | RNCP400   | C11       | Night rounds |

  Scenario: The vaeiste positions themselves on a node of each level, withdraws one, and finds the lines in the history
    Given I log in as "candidat"
    When I am on the "candidat RNCP400" "tool_vae > Selfposition" page
    Then I should see "visible to the managers who follow you"
    And I should see "This is not a validation"
    And I should see "Record" in the ".tool-vae-selfpos-tree" "css_element"
    And I should see "Help the person to wash" in the ".tool-vae-selfpos-tree" "css_element"
    When I am on the "candidat RNCP400-" "tool_vae > Selfposition" page
    And I set the following fields to these values:
      | Step          | I am discovering              |
      | Justification | I start reading the record.   |
    And I press "Record"
    Then I should see "Your positioning is recorded."
    And I should see "I am discovering" in the ".tool-vae-history" "css_element"
    When I am on the "candidat RNCP400-D1" "tool_vae > Selfposition" page
    And I set the following fields to these values:
      | Step | I practise with help |
    And I press "Record"
    Then I should see "I practise with help" in the ".tool-vae-history" "css_element"
    When I am on the "candidat RNCP400-C11" "tool_vae > Selfposition" page
    And I set the following fields to these values:
      | Step | I act on my own |
    And I press "Record"
    Then I should see "I act on my own" in the ".tool-vae-history" "css_element"
    And I should see "1 declaration(s), including 0 attested, 0 supporting piece(s)"
    When I am on the "candidat RNCP400-H111" "tool_vae > Selfposition" page
    And I set the following fields to these values:
      | Step          | I master it and I pass it on |
      | Justification | Alone since the third week.  |
    And I press "Record"
    Then I should see "I master it and I pass it on" in the ".tool-vae-history" "css_element"
    When I set the following fields to these values:
      | Step          | Withdraw my level        |
      | Justification | I meant another element. |
    And I press "Record"
    Then I should see "Your positioning is recorded."
    And I should see "Level withdrawn" in the ".tool-vae-history" "css_element"
    And I should see "Earlier lines" in the ".tool-vae-history" "css_element"
    And I should see "Alone since the third week." in the ".tool-vae-history" "css_element"
    And I should see "I meant another element." in the ".tool-vae-history" "css_element"
    When I am on the "candidat RNCP400" "tool_vae > Selfposition" page
    Then I should see "I act on my own" in the ".tool-vae-selfpos-tree" "css_element"
    And I should see "No level given yet" in the ".tool-vae-selfpos-tree" "css_element"
    And I should not see "%" in the ".tool-vae-selfpos" "css_element"
    And I should not see "validated"

  Scenario: The same line twice is refused, and the vaeiste reaches the page from their pathway
    Given the following "tool_vae > selfpositions" exist:
      | candidate | fichecode | ficherang | level | justification |
      | candidat  | RNCP400   | C11       | 2     | With my colleague. |
    And I log in as "candidat"
    When I am on the "candidat" "tool_vae > Organisation" page
    And I press "Position myself"
    Then I should see "Choose a certification record"
    When I follow "RNCP400"
    Then I should see "Help with daily care" in the ".tool-vae-selfpos-tree" "css_element"
    And I should see "I practise with help" in the ".tool-vae-selfpos-tree" "css_element"
    When I am on the "candidat RNCP400-C11" "tool_vae > Selfposition" page
    And I set the following fields to these values:
      | Step          | I practise with help |
      | Justification | With my colleague.   |
    And I press "Record"
    Then I should see "This is what your latest line already says"

  Scenario: The manager who follows reads the self-positioning next to their evaluation, without a form
    Given the following "tool_vae > selfpositions" exist:
      | candidate | fichecode | ficherang | level | justification       |
      | candidat  | RNCP400   | C11       | 3     | Three weeks on my own. |
    And the following "tool_vae > evaluations" exist:
      | candidate | fichecode | ficherang | author      | status  | reason            |
      | candidat  | RNCP400   | C11       | responsable | inproof | Two situations seen. |
    And I log in as "responsable"
    When I am on the "candidat RNCP400-C11" "tool_vae > Selfposition" page
    Then I should see "Self-positioning of Camille Durand"
    And I should see "What the vaeiste writes here, they write alone"
    And I should not see "What you write here"
    And I should see "I act on my own" in the ".tool-vae-history" "css_element"
    And I should see "Three weeks on my own." in the ".tool-vae-history" "css_element"
    And I should see "The structure wrote: in the course of proof"
    And I should not see "Justification (optional"
    And I should not see "Record" in the "#region-main" "css_element"
    When I am on the "candidat RNCP400" "tool_vae > Selfposition" page
    Then I should not see "Say, element by element"
    And I should see "Back to the dossier"
    When I am on the "candidat" "tool_vae > Dossier" page
    Then I should see "Self-positioning of the vaeiste: I act on my own" in the ".tool-vae-positioning" "css_element"
    When I follow "Read the self-positioning of the vaeiste"
    Then I should see "Self-positioning of Camille Durand"
    And I should see "What the vaeiste writes here, they write alone"
    When I follow "RNCP400"
    Then I should see "Help the person to wash" in the ".tool-vae-selfpos-tree" "css_element"
    And I should not see "Position myself on this element"

  # Lot 7 (MMR-520, wave 3a): the page of a record hidden under the vaeiste, which no other scenario opened. With a send of the
  # vaeiste, the message is an alarm and the text typed is given back; without one, the message is an information.
  Scenario: A record hidden since the node was opened gives back its message and the text typed, never lost
    Given I log in as "candidat"
    And I am on the "candidat RNCP400-C11" "tool_vae > Selfposition" page
    And I set the following fields to these values:
      | Step          | I act on my own          |
      | Justification | Kept through the hiding. |
    And the framework of the record "RNCP400" is hidden
    When I press "Record"
    Then I should see "The record Aide-soignant (RNCP400) is hidden" in the ".alert-danger" "css_element"
    And I should see "Your text was not recorded"
    And I should see "Kept through the hiding." in the "textarea[readonly]" "css_element"
    When I am on the "candidat RNCP400-C11" "tool_vae > Selfposition" page
    Then I should see "The record Aide-soignant (RNCP400) is hidden" in the ".alert-info" "css_element"
    And I should not see "Your text was not recorded"
    And "textarea[readonly]" "css_element" should not exist

  Scenario: The manager who does not follow is refused
    Given I log in as "autre"
    Then the "candidat RNCP400" "Selfposition" page should be refused with "Sorry, but you do not currently have permissions to do that"

  Scenario: A node whose code does not follow the expected form is shown without a form
    Given the following "core_competency > competencies" exist:
      | competencyframework | shortname        | idnumber |
      | RNCP400             | Odd coded skill  | AS-1-1   |
    And I log in as "candidat"
    When I am on the "candidat RNCP400" "tool_vae > Selfposition" page
    Then I should see "Odd coded skill" in the ".tool-vae-selfpos-tree" "css_element"
    And I should see "cannot be positioned on" in the ".tool-vae-selfpos-tree" "css_element"

  # Lot 7 (MMR-520, wave 3a, pilot): the list of the records with a hidden record, which no scenario opened.
  Scenario: The list of the records says which record is hidden and offers the others only
    Given I log in as "candidat"
    And I am on the "candidat" "tool_vae > Organisation" page
    And I press "Position myself"
    And I should see "Choose a certification record"
    And the framework of the record "RNCP400" is hidden
    When I reload the page
    Then I should see "The record Aide-soignant (RNCP400) is hidden" in the ".alert-info" "css_element"
    And I should see "No certification record is offered on this site yet."
    And I should not see "Choose a certification record"

  # Lot 7 (MMR-520, wave 4a): two states of the page that the tool of equivalence does not serve - the competencies switched
  # off, an annotation of a node refused - drawn by a class of output now.
  Scenario: With the competencies switched off, the self-positioning says so and nothing else
    Given the following config values are set as admin:
      | enabled | 0 | core_competency |
    And I log in as "candidat"
    When I am on the "candidat RNCP400" "tool_vae > Selfposition" page
    Then I should see "Position myself"
    And I should see "The competency subsystem is switched off on this site."
    And I should not see "Help with daily care"
    And I should not see "Choose a certification record"

  Scenario: The same annotation of a node twice is refused, and said above the node
    Given the following "permission overrides" exist:
      | capability        | permission | role     | contextlevel | reference |
      | tool/vae:annotate | Allow      | follower | System       |           |
    And the following "tool_vae > consents" exist:
      | candidate | manager     | state |
      | candidat  | responsable | given |
    And I log in as "responsable"
    And I am on the "candidat RNCP400-C11" "tool_vae > Selfposition" page
    And I set the field "Your annotation" to "Say what you do alone."
    And I press "Save the annotation"
    When I set the field "Your annotation" to "Say what you do alone."
    And I press "Save the annotation"
    Then I should see "This annotation is the same as your latest annotation on the same item."
    And I should see "Say what you do alone." in the ".tool-vae-note" "css_element"

  # Lot 7 (MMR-520, wave 3a, pilot): the page of a node gives, in reading, the level the vaeiste gave on the equivalent node
  # of the record this one replaces, which no scenario read.
  Scenario: The node of a record that replaces another shows the level given on the old record
    Given the following "tool_vae > sources" exist:
      | fichecode | ficherang | name       | shortname  |
      | RNCP300   | D1        | Old record | Old domain |
    And the following "tool_vae > competencies" exist:
      | fichecode | ficherang | shortname      | parent |
      | RNCP300   | C11       | Old daily care | D1     |
    And the following "tool_vae > replacements" exist:
      | fichecode | successorcode |
      | RNCP300   | RNCP400       |
    And the following "tool_vae > correspondences" exist:
      | from        | to          |
      | RNCP300-C11 | RNCP400-C11 |
    And the following "tool_vae > selfpositions" exist:
      | candidate | fichecode | ficherang | level | justification      |
      | candidat  | RNCP300   | C11       | 2     | On the old record. |
    When I log in as "candidat"
    And I am on the "candidat RNCP400-C11" "tool_vae > Selfposition" page
    Then I should see "On the earlier record RNCP300 (C11):" in the ".tool-vae-selfpos" "css_element"
