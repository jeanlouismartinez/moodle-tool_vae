@tool @tool_vae @javascript @accessibility
Feature: The screens of the plugin that hold tables meet the accessibility standards the core checks
  In order to read my pathway, the vaeistes I follow, the organisation of the scheme and the setup with any reader
  As a vaeiste, a manager, the direction or the administrator
  I need those screens, filled with real data, to pass the axe-core checks of the core in a real browser

  # Lot Q7. The step "the page should meet accessibility standards" runs axe-core with the default set of the core (WCAG 2.2 A
  # and AA, section 508, ARIA); it runs only in a browser and only with the tag @accessibility. Each scenario first reads a
  # cell of a filled table, so that the check never passes on an empty screen. One scenario per screen: a scenario that
  # drives a browser is slow.

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname  | email               |
      | candidat  | Camille   | Durand    | camille@example.com |
      | unique    | Alex      | Martin    | alex@example.com    |
      | novice    | Noa       | Perrin    | noa@example.com     |
      | suivi     | Sam       | Leroy     | sam@example.com     |
      | direction | Dana      | Direction | dana@example.com    |
    And the following "roles" exist:
      | shortname | name                |
      | follower  | Follows a candidate |
      | direction | Direction VAE       |
    And the following "permission overrides" exist:
      | capability            | permission | role      | contextlevel | reference |
      | tool/vae:declare      | Allow      | user      | System       |           |
      | tool/vae:viewown      | Allow      | user      | System       |           |
      | tool/vae:viewassigned | Allow      | follower  | System       |           |
      | tool/vae:attest       | Allow      | follower  | System       |           |
      | tool/vae:viewany      | Allow      | direction | System       |           |
    And the following "role assigns" exist:
      | user      | role      | contextlevel | reference |
      | direction | direction | System       |           |
      | suivi     | follower  | User         | candidat  |
      | suivi     | follower  | User         | unique    |
      | suivi     | follower  | User         | novice    |
    And the following "tool_vae > sources" exist:
      | fichecode | ficherang | name   | shortname            |
      | RNCP100   | BC01      | Record | Accompany the person |
      | RS200     | C01       | Flat   | Use a lift           |
    And the following "tool_vae > competencies" exist:
      | fichecode | ficherang | shortname    | bloc |
      | RNCP100   | BC02      | Work in team |      |
      | RNCP100   | C01       | Help to wash | BC01 |
      | RNCP100   | C02       | Hand over    | BC02 |
    And the following "tool_vae > declarations" exist:
      | candidate | fichecode | ficherang | situation        |
      | candidat  | RNCP100   | C01       | Night rounds     |
      | candidat  | RNCP100   | C02       | Evening handover |
      | unique    | RNCP100   | C01       | Morning care     |
    And the following "tool_vae > attestations" exist:
      | declaration  | attestant |
      | Night rounds | suivi     |
    And the following "courses" exist:
      | fullname             | shortname | visible |
      | Care course          | CARE      | 1       |
      | Secret working title | SECRET    | 0       |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | candidat | CARE   | student |
    And the following "core_competency > course_competencies" exist:
      | course | competency  |
      | CARE   | RNCP100-C01 |
      | SECRET | RNCP100-C01 |
    And the following "activities" exist:
      | activity | course | name       |
      | quiz     | CARE   | Night quiz |
    And the "quiz" activity "Night quiz" carries the competency "RNCP100-C01"
    And the following "core_competency > templates" exist:
      | shortname    | visible |
      | Care pathway | 1       |
    And the following "core_competency > template_competencies" exist:
      | template     | competency  |
      | Care pathway | RNCP100-C01 |
    And the following "core_competency > plans" exist:
      | name       | user     | status |
      | Plan AES   | candidat | active |
      | Draft plan | candidat | draft  |
    And the following "core_competency > plan_competencies" exist:
      | plan       | competency  |
      | Plan AES   | RNCP100-C01 |
      | Draft plan | RNCP100-C01 |
    And the following config values are set as admin:
      | validationopen | 1 | tool_vae |

  Scenario: The tab "My pathway" of a vaeiste, with its table and its banner, meets the standards
    Given I log in as "candidat"
    When I am on the "tool_vae > Organisation" page
    Then the following should exist in the "Record RNCP100" table:
      | Competency   | Declarations | Courses     | Learning plans    |
      | Help to wash | 1            | Care course | Plan AES (Active) |
    And "What you can do now" "text" should exist in the ".tool-vae-next" "css_element"
    And I should see "Care course › Night quiz (Quiz)"
    And the page should meet accessibility standards

  Scenario: The tab "Followed candidates" of a manager, with its table and its banner, meets the standards
    Given I log in as "suivi"
    When I am on the "tool_vae > Organisation followed" page
    Then the following should exist in the "tool-vae-followed" table:
      | Candidate      | Records | Declarations | Awaiting attestation |
      | Camille Durand | RNCP100 | 2            | 1                    |
      | Alex Martin    | RNCP100 | 1            | 1                    |
      | Noa Perrin     |         | 0            | 0                    |
    And "What you can do now" "text" should exist in the ".tool-vae-next" "css_element"
    And the page should meet accessibility standards

  Scenario: The tab "Organisation" of the direction, with the records, the actors, the rights, the cohorts and the settings, meets the standards
    Given the following "cohorts" exist:
      | name       | idnumber |
      | Night team | NIGHT    |
    And I log in as "admin"
    And I visit "/admin/tool/cohortroles/index.php"
    And I set the field "Select users to assign role" to "Sam Leroy"
    And I set the field "Select role" to "Follows a candidate"
    And I set the field "Select cohorts" to "Night team"
    And I press "Assign"
    And I log out
    And I log in as "direction"
    When I am on the "tool_vae > Organisation site" page
    Then the following should exist in the "tool-vae-site-fiches" table:
      | Records | Courses     | Activities | Templates of the core | Learning plans (number) |
      | RNCP100 | Care course | 1 activity | Care pathway          | 2                       |
    And the following should exist in the "tool-vae-site-actors" table:
      | Actor         | Right            | Roles that give it |
      | VAE direction | tool/vae:viewany | Direction VAE      |
    And the following should exist in the "tool-vae-site-powers" table:
      | Right                                            | Roles that give it |
      | View any vaeiste's dossier and the configuration | Direction VAE      |
    And the following should exist in the "tool-vae-site-cohorts" table:
      | Cohort (its members are followed) | Role given to the managers |
      | Night team                        | Follows a candidate        |
    And the following should exist in the "tool-vae-site-settings" table:
      | Setting                                                      | State |
      | Open the preparation of the validation dossier to candidates | open  |
    And the page should meet accessibility standards

  Scenario: The page "Getting started" of the administrator, with its list of steps, meets the standards
    Given I log in as "admin"
    When I am on the "tool_vae > Setup" page
    Then I should see "Getting started"
    And I should see "To do" in the "#tool-vae-setup-roles .tool-vae-setup-state" "css_element"
    And the page should meet accessibility standards

  Scenario: The guide for candidates, with its numbered steps and its words to know, meets the standards
    Given I log in as "candidat"
    When I am on the "tool_vae > Guide vaeiste" page
    Then I should see "Guide for candidates" in the "page-header" "region"
    And I should see "Declare an activity" in the "#tool-vae-guide-declare" "css_element"
    And I should see "Words to know" in the "#tool-vae-guide-words" "css_element"
    And the page should meet accessibility standards
