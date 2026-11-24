@tool @tool_vae
Feature: The banner "What you can do now" on the tabs "My pathway" and "Followed candidates"
  In order to know what I can do now, without a progress nor a verdict
  As a vaeiste, or a manager who follows vaeistes
  I need a banner on my tab of the organisation page that says what is possible, with a link

  # Lot S2. The banner is read in itself (".tool-vae-next"), never in the body of the page: "Declare an activity" is also the
  # label of the button of the page, under the banner. A person who is both a vaeiste and a manager reads, on each tab, the
  # banner of that tab and not the other one.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | candidat | Camille   | Durand   | camille@example.com |
      | novice   | Noa       | Perrin   | noa@example.com     |
      | calme    | Lou       | Petit    | lou@example.com     |
      | suivi    | Sam       | Leroy    | sam@example.com     |
      | double   | Dominique | Blanc    | dom@example.com     |
      | etranger | Eli       | Roux     | eli@example.com     |
    And the following "roles" exist:
      | shortname | name                |
      | follower  | Follows a candidate |
    And the following "permission overrides" exist:
      | capability            | permission | role     | contextlevel | reference |
      | tool/vae:declare      | Allow      | user     | System       |           |
      | tool/vae:viewown      | Allow      | user     | System       |           |
      | tool/vae:viewassigned | Allow      | follower | System       |           |
      | tool/vae:attest       | Allow      | follower | System       |           |
    And the following "role assigns" exist:
      | user   | role     | contextlevel | reference |
      | calme  | follower | User         | novice    |
      | suivi  | follower | User         | candidat  |
      | double | follower | User         | candidat  |
    And the following "tool_vae > sources" exist:
      | fichecode | ficherang | name   | shortname            |
      | RNCP100   | BC01      | Record | Accompany the person |
    And the following "tool_vae > competencies" exist:
      | fichecode | ficherang | shortname    | bloc |
      | RNCP100   | C01       | Help to wash | BC01 |
    And the following "tool_vae > declarations" exist:
      | candidate | fichecode | ficherang | situation    |
      | candidat  | RNCP100   | C01       | Night rounds |

  Scenario: A vaeiste without a declaration reads "Declare an activity" in the banner of "My pathway"
    Given I log in as "novice"
    When I am on the "tool_vae > Organisation" page
    Then "What you can do now" "text" should exist in the ".tool-vae-next" "css_element"
    And "Declare an activity" "link" should exist in the ".tool-vae-next" "css_element"
    And "Nothing is waiting for your answer at the moment." "text" should not exist in the ".tool-vae-next" "css_element"

  Scenario: A manager whose followed vaeiste has nothing waiting reads the plain sentence
    Given I log in as "calme"
    When I am on the "tool_vae > Organisation followed" page
    Then "Nothing is waiting for your answer at the moment." "text" should exist in the ".tool-vae-next" "css_element"
    And "Declare an activity" "link" should not exist in the ".tool-vae-next" "css_element"
    And "declaration to attest" "text" should not exist in the ".tool-vae-next" "css_element"

  Scenario: A manager with one declaration to attest reads it in the banner, with its link
    Given I log in as "suivi"
    When I am on the "tool_vae > Organisation followed" page
    Then "1 declaration to attest" "link" should exist in the ".tool-vae-next" "css_element"
    And "Nothing is waiting for your answer at the moment." "text" should not exist in the ".tool-vae-next" "css_element"
    And I click on "1 declaration to attest" "link" in the ".tool-vae-next" "css_element"
    And "Followed candidates, with their records and learning plans" "link" should exist

  Scenario: A person who is both a vaeiste and a manager reads, on each tab, the banner of that tab
    Given I log in as "double"
    When I am on the "tool_vae > Organisation" page
    Then "Declare an activity" "link" should exist in the ".tool-vae-next" "css_element"
    And "1 declaration to attest" "link" should not exist in the ".tool-vae-next" "css_element"
    And I click on "Followed candidates" "link" in the ".nav-tabs" "css_element"
    And "1 declaration to attest" "link" should exist in the ".tool-vae-next" "css_element"
    And "Declare an activity" "link" should not exist in the ".tool-vae-next" "css_element"

  Scenario: A person who follows nobody is still refused the tab "Followed candidates", banner or not
    Given I log in as "etranger"
    Then the "etranger" "Organisation followed" page should be refused with "Sorry, but you do not currently have permissions to do that (View the dossier of a vaeiste one follows)."
