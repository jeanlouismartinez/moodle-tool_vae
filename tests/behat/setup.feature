@tool @tool_vae
Feature: The administrator reads, on one page, the steps of putting the plugin into service
  In order to know what is done and what remains, in the order of the installation guide
  As the administrator
  I need a page that lists the steps with their state in words, and changes nothing

  # The page writes nothing: it only reads facts of the site (configuration, roles, rights, records).

  Scenario: On a new site the administrator reads the steps in order, the roles still to do
    Given I log in as "admin"
    When I am on the "tool_vae > Setup" page
    Then I should see "Getting started"
    And "#tool-vae-setup-competencies" "css_element" should appear before "#tool-vae-setup-cron" "css_element"
    And "#tool-vae-setup-cron" "css_element" should appear before "#tool-vae-setup-roles" "css_element"
    And "#tool-vae-setup-roles" "css_element" should appear before "#tool-vae-setup-rights" "css_element"
    And "#tool-vae-setup-rights" "css_element" should appear before "#tool-vae-setup-assignments" "css_element"
    And "#tool-vae-setup-assignments" "css_element" should appear before "#tool-vae-setup-record" "css_element"
    And "#tool-vae-setup-record" "css_element" should appear before "#tool-vae-setup-policy" "css_element"
    And I should see "To do" in the "#tool-vae-setup-roles .tool-vae-setup-state" "css_element"
    And I should see "For you to check" in the "#tool-vae-setup-assignments .tool-vae-setup-state" "css_element"
    And I should see "For you to check" in the "#tool-vae-setup-policy .tool-vae-setup-state" "css_element"
    And "#tool-vae-setup-antivirus" "css_element" should not exist

  Scenario: Once the three base roles are created, the roles step is done
    # Three creations through the "Roles installer" page, as in roles_install.feature.
    Given I log in as "admin"
    And I am on the "tool_vae > Roles installer" page
    And I press "Create \"Vaeiste\""
    And I press "Create the role"
    And I press "Create \"VAE manager\""
    And I press "Create the role"
    And I press "Create \"VAE direction\""
    And I press "Create the role"
    When I am on the "tool_vae > Setup" page
    Then I should see "Done" in the "#tool-vae-setup-roles .tool-vae-setup-state" "css_element"
    And I should see "Done" in the "#tool-vae-setup-rights .tool-vae-setup-state" "css_element"
    And I should see "For you to check" in the "#tool-vae-setup-assignments .tool-vae-setup-state" "css_element"
    And I should see "For you to check" in the "#tool-vae-setup-policy .tool-vae-setup-state" "css_element"

  Scenario: The plugin's own page leads to the getting started page
    Given I log in as "admin"
    And I am on the "tool_vae > Index" page
    When I follow "Getting started"
    Then "#tool-vae-setup-competencies" "css_element" should exist

  Scenario: Somebody who may not import records is refused
    Given the following "users" exist:
      | username | firstname | lastname | email           |
      | simple   | Sam       | Leroy    | sam@example.com |
    And I log in as "simple"
    Then the "any" "Setup" page should be refused with "Access denied"
