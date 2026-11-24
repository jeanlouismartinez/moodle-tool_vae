@tool @tool_vae
Feature: A guide of one page for each role, from the tab of that role
  In order to use the plugin without knowing Moodle
  As a vaeiste, a manager who follows vaeistes, or the direction
  I need a link "Help" on my tab of the organisation page that leads to the guide of my role, and to nobody else's

  # Lot S5. The guide of a role is opened by whoever may open the tab of that role, by the same predicates and with the same
  # refusals. The guides are language strings of the plugin: these scenarios read the English ones.

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname  | email               |
      | candidat  | Camille   | Durand    | camille@example.com |
      | suivi     | Sam       | Leroy     | sam@example.com     |
      | direction | Dana      | Direction | dana@example.com    |
      | etranger  | Eli       | Roux      | eli@example.com     |
    And the following "roles" exist:
      | shortname | name                |
      | vaeiste   | Vaeiste             |
      | follower  | Follows a candidate |
      | direction | Direction VAE       |
    And the following "permission overrides" exist:
      | capability            | permission | role      | contextlevel | reference |
      | tool/vae:declare      | Allow      | vaeiste   | System       |           |
      | tool/vae:viewown      | Allow      | vaeiste   | System       |           |
      | tool/vae:viewassigned | Allow      | follower  | System       |           |
      | tool/vae:viewany      | Allow      | direction | System       |           |
    And the following "role assigns" exist:
      | user      | role      | contextlevel | reference |
      | candidat  | vaeiste   | User         | candidat  |
      | suivi     | follower  | User         | candidat  |
      | direction | direction | System       |           |

  Scenario: The vaeiste follows "Help" from "My pathway" and reads the guide for candidates
    Given I log in as "candidat"
    And I am on the "tool_vae > Organisation" page
    When I follow "Help: the guide for candidates"
    Then I should see "Guide for candidates" in the "page-header" "region"
    And I should see "Where to find your dossier"
    And I should see "Export your dossier"
    And I should see "Words to know"
    And I should see "VAE" is active in navigation
    And "Help: the guide for managers" "link" should not exist

  Scenario: The manager follows "Help" from "Followed candidates" and reads the guide for managers
    Given I log in as "suivi"
    And I am on the "tool_vae > Organisation followed" page
    When I follow "Help: the guide for managers"
    Then I should see "Guide for managers" in the "page-header" "region"
    And I should see "Attest a declaration"
    And I should see "Give an opinion on a deposit"

  Scenario: The direction follows "Help" from "Organisation", reads its guide and opens the declarations awaiting an attestation
    Given I log in as "direction"
    And I am on the "tool_vae > Organisation site" page
    When I follow "Help: the guide for the direction"
    Then I should see "Guide for the direction" in the "page-header" "region"
    And I should see "What the direction does not do"
    And I follow "Declarations awaiting an attestation"
    And I should see "Declarations awaiting an attestation" in the "region-main" "region"
    And I should not see "What the direction does not do"

  Scenario: A person without a right of the plugin is refused on every guide
    Given I log in as "etranger"
    Then the "any" "Guide vaeiste" page should be refused with "Sorry, but you do not currently have permissions to do that"
    And the "any" "Guide manager" page should be refused with "Sorry, but you do not currently have permissions to do that"
    And the "any" "Guide direction" page should be refused with "Sorry, but you do not currently have permissions to do that"

  Scenario: A vaeiste is refused on the guide of the direction
    Given I log in as "candidat"
    Then the "any" "Guide direction" page should be refused with "Sorry, but you do not currently have permissions to do that (View any vaeiste's dossier"
