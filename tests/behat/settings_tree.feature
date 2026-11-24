@tool @tool_vae
Feature: The administrator reads the settings of the plugin by group, and finds its pages in one category
  In order to take in the settings and the pages of the plugin at a glance
  As the administrator
  I need the settings under titles, and the pages in a category "VAE", "Getting started" first

  Scenario: The settings page shows the seven group titles in order
    Given I log in as "admin"
    When I visit "/admin/settings.php?section=tool_vae_settings"
    Then I should see "VAE: settings"
    And "Attestations and reminders" "text" should appear before "Feasibility and validation dossiers" "text"
    And "Feasibility and validation dossiers" "text" should appear before "Supporting pieces" "text"
    And "Supporting pieces" "text" should appear before "AI aid" "text"
    And "AI aid" "text" should appear before "Editable copies" "text"
    And "Editable copies" "text" should appear before "Self-positioning" "text"
    And "Self-positioning" "text" should appear before "Import of records" "text"
    And I should see "A manager may withdraw an attestation they gave a vaeiste themselves"

  Scenario: The category VAE lists the pages of the plugin, Getting started first
    Given I log in as "admin"
    When I visit "/admin/category.php?category=toolvaefolder"
    Then "Getting started" "link" should appear before "VAE - pathway" "link"
    And "VAE - pathway" "link" should appear before "VAE roles" "link"
    And "VAE roles" "link" should appear before "VAE: settings" "link"
    And "VAE: settings" "link" should appear before "Organisation" "link"
    And "Organisation" "link" should appear before "Declarations awaiting an attestation" "link"
    And "Declarations awaiting an attestation" "link" should appear before "Certifications to renew" "link"
    And "Certifications to renew" "link" should appear before "Managers authorized to produce the archive of the structure" "link"
    And "Managers authorized to produce the archive of the structure" "link" should appear before "Replaced certification records" "link"
    And "Replaced certification records" "link" should appear before "Referents of the blocs" "link"
    And "Referents of the blocs" "link" should appear before "Rules of validation: addresses" "link"
