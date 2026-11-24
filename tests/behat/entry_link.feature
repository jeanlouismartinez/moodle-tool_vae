@tool @tool_vae
Feature: The link "VAE" in the primary navigation of the site
  In order to find the plugin without going through my profile
  As a vaeiste, a manager who follows vaeistes, or the direction
  I need a link "VAE" in the bar at the top of every page, that leads to my tab of the organisation page

  # Lot S3. The link is shown to whoever may open a tab of the organisation page, and to nobody else. It is looked for in the
  # primary navigation and nowhere else in the page. On the page it leads to, "VAE" is the active entry of the bar. The manager
  # of this file may not read a dossier of their own: the role that makes a vaeiste is given to the vaeiste alone, in their own
  # user context, so the manager lands on "Followed candidates" and not on "My pathway".

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

  Scenario: The vaeiste follows the link "VAE" and lands on "My pathway"
    Given I log in as "candidat"
    When I select "VAE" from primary navigation
    Then I should see "My pathway" in the ".nav-tabs.mb-3 .nav-link.active" "css_element"
    And I should see "VAE" is active in navigation

  Scenario: A manager who may not read a dossier of their own follows the link "VAE" and lands on "Followed candidates"
    Given I log in as "suivi"
    When I select "VAE" from primary navigation
    Then I should see "Followed candidates" in the ".nav-tabs.mb-3 .nav-link.active" "css_element"
    And "My pathway" "link" should not exist in the ".nav-tabs.mb-3" "css_element"
    And I should see "VAE" is active in navigation

  Scenario: The direction follows the link "VAE" and lands on "Organisation"
    Given I log in as "direction"
    When I select "VAE" from primary navigation
    Then I should see "Organisation" in the ".nav-tabs.mb-3 .nav-link.active" "css_element"
    And I should see "VAE" is active in navigation

  Scenario: A person without a right of the plugin has no link "VAE" in the bar
    Given I log in as "etranger"
    When I am on homepage
    Then "Dashboard" "link" should exist in the ".primary-navigation" "css_element"
    And "VAE" "link" should not exist in the ".primary-navigation" "css_element"

  # The same link in a real browser: with JavaScript the bar is drawn by a component of the core, not by the template that the
  # scenarios above read.
  @javascript
  Scenario: With JavaScript, the vaeiste follows the link "VAE" from the bar and lands on "My pathway"
    Given I log in as "candidat"
    When I select "VAE" from primary navigation
    Then I should see "My pathway" in the ".nav-tabs.mb-3 .nav-link.active" "css_element"

  @javascript
  Scenario: With JavaScript, a person without a right of the plugin has no link "VAE" in the bar
    Given I log in as "etranger"
    When I am on homepage
    Then "Dashboard" "link" should exist in the ".primary-navigation" "css_element"
    And "VAE" "link" should not exist in the ".primary-navigation" "css_element"
