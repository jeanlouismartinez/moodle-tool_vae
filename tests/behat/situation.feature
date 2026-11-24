@tool @tool_vae
Feature: The vaeiste writes their work situations, tags them on the record, and a manager they consented to annotates them
  In order to tell my work in my own words, next to the record, and to read what a manager I chose says of it
  As a vaeiste, and as the manager who follows them
  I need situations I alone write, tagged on the record, and annotations only by the managers I consented to

  # Lot 1b. The vaeiste alone writes the title, the text, the tags, the pieces and the consent. A manager who follows them
  # reads; with their consent, they annotate, under the text, never in it. Nothing is computed; nothing is a validation.

  Background:
    Given the following "users" exist:
      | username    | firstname | lastname  | email                |
      | candidat    | Camille   | Durand    | camille@example.com  |
      | responsable | Nadia     | Benali    | nadia@example.com    |
      | autre       | Omar      | Ailleurs  | omar@example.com     |
      | intrus      | Ines      | Voisin    | ines@example.com     |
    And the following "roles" exist:
      | shortname | name                |
      | vaeiste   | Vaeiste role        |
      | follower  | Follows a candidate |
    And the following "permission overrides" exist:
      | capability             | permission | role     | contextlevel | reference |
      | tool/vae:declare       | Allow      | vaeiste  | System       |           |
      | tool/vae:viewown       | Allow      | vaeiste  | System       |           |
      | tool/vae:positionself  | Allow      | vaeiste  | System       |           |
      | tool/vae:draftown      | Allow      | vaeiste  | System       |           |
      | tool/vae:viewassigned  | Allow      | follower | System       |           |
      | tool/vae:evaluate      | Allow      | follower | System       |           |
      | tool/vae:annotate      | Allow      | follower | System       |           |
    And the following "role assigns" exist:
      | user        | role     | contextlevel | reference   |
      | candidat    | vaeiste  | System       |             |
      | intrus      | vaeiste  | System       |             |
      | responsable | follower | User         | candidat    |
      | autre       | follower | User         | responsable |
    And the following config values are set as admin:
      | validationopen   | 1 | tool_vae |
      | piecesopen       | 1 | tool_vae |
      | piecenoantivirus | 1 | tool_vae |
    And the following "tool_vae > sources" exist:
      | fichecode | ficherang | name          | shortname            |
      | RNCP400   | D1        | Aide-soignant | Accompany the person |
    And the following "tool_vae > competencies" exist:
      | fichecode | ficherang | shortname                | parent |
      | RNCP400   | C11       | Help with daily care     | D1     |
      | RNCP400   | H111      | Help the person to wash  | C11    |
      | RNCP400   | H112      | Help the person to dress | C11    |
    And the following "tool_vae > declarations" exist:
      | candidate | fichecode | ficherang | situation    |
      | candidat  | RNCP400   | C11       | Night rounds |

  Scenario: The vaeiste writes a situation, tags it, saves a version, attaches a link and finds the counts where they stand
    Given I log in as "candidat"
    When I am on the "candidat RNCP400" "tool_vae > Situations" page
    Then I should see "No work situation is written here yet."
    And I should see "0 situation(s), 1 declaration(s)" in the ".tool-vae-whereami [data-rank='C11']" "css_element"
    When I press "New situation"
    And I set the following fields to these values:
      | id_title   | Night round with a new colleague |
      | id_setting | Night service                    |
    And I set the field "tool-vae-whereami-tag-C11" to "1"
    And I set the field "tool-vae-whereami-tag-H111" to "1"
    And I press "Save the title, the period, the setting and the tags"
    Then I should see "The title, the period, the setting and the tags are saved."
    And I should see "No version has been saved yet."
    When I set the field "id_content" to "I led the night round with a colleague who had just arrived."
    And I press "Save a version of the text"
    Then I should see "Version 1 saved."
    And I should see "Latest saved version: 1."
    When I set the following fields to these values:
      | id_linknature | Professional writing      |
      | id_linktitle  | Round sheet               |
      | id_url        | https://example.com/round |
      | id_linkpart   | The handover on page two  |
    And I press "Attach the link"
    Then I should see "The link was attached."
    And I should see "Professional writing" in the "#region-main" "css_element"
    And I should see "The handover on page two" in the "#region-main" "css_element"
    When I am on the "candidat RNCP400" "tool_vae > Situations" page
    Then I should see "Night round with a new colleague" in the ".tool-vae-situations" "css_element"
    And I should see "Night service" in the ".tool-vae-situations" "css_element"
    And I should see "1 piece(s)" in the ".tool-vae-situations" "css_element"
    And I should see "1 situation(s), 1 declaration(s)" in the ".tool-vae-whereami [data-rank='C11']" "css_element"
    And I should see "1 situation(s), 0 declaration(s)" in the ".tool-vae-whereami [data-rank='H111']" "css_element"
    And I should see "0 situation(s), 0 declaration(s)" in the ".tool-vae-whereami [data-rank='H112']" "css_element"
    And I should not see "%" in the ".tool-vae-whereami" "css_element"

  Scenario: The vaeiste consents, the manager who follows annotates, and the vaeiste reads the annotation signed and dated
    Given the following "tool_vae > situations" exist:
      | candidate | fichecode | title       | text                    | tags |
      | candidat  | RNCP400   | Night round | I led the night round.  | C11  |
    And I log in as "candidat"
    When I am on the "candidat" "tool_vae > Consent" page
    Then I should see "No consent"
    When I press "Give my consent"
    Then I should see "Consent given"
    And I should see "Take back my consent"
    And I log out
    And I log in as "responsable"
    When I am on the "candidat RNCP400-1" "tool_vae > Situation" page
    Then I should see "I led the night round." in the ".tool-vae-situation-text" "css_element"
    And I should not see "Save a version of the text"
    When I set the field "Your annotation" to "A clear account; say what you checked first."
    And I press "Save the annotation"
    Then I should see "A clear account; say what you checked first." in the ".tool-vae-notes" "css_element"
    And I log out
    And I log in as "candidat"
    When I am on the "candidat RNCP400-1" "tool_vae > Situation" page
    Then the field "id_content" matches value "I led the night round."
    And I should see "A clear account; say what you checked first." in the ".tool-vae-note" "css_element"
    And I should see "Nadia Benali, " in the ".tool-vae-note" "css_element"
    And I should see "##today##%d %B %Y##" in the ".tool-vae-note" "css_element"
    And I should see "it has no value of validation" in the ".tool-vae-notes" "css_element"
    And I should not see "Save the annotation"

  Scenario: Without the consent of the vaeiste, the manager who follows reads the situation and gets no form to annotate
    Given the following "tool_vae > situations" exist:
      | candidate | fichecode | title       | text                    | tags |
      | candidat  | RNCP400   | Night round | I led the night round.  | C11  |
    And I log in as "responsable"
    When I am on the "candidat RNCP400-1" "tool_vae > Situation" page
    Then I should see "I led the night round." in the ".tool-vae-situation-text" "css_element"
    And I should see "No annotation." in the ".tool-vae-notes" "css_element"
    And I should not see "Your annotation"
    And I should not see "Save the annotation"
    And the following "tool_vae > consents" exist:
      | candidate | manager     | state     |
      | candidat  | responsable | given     |
      | candidat  | responsable | withdrawn |
    When I am on the "candidat RNCP400-1" "tool_vae > Situation" page
    Then I should see "No annotation." in the ".tool-vae-notes" "css_element"
    And I should not see "Save the annotation"
    When I am on the "candidat" "tool_vae > Consent" page
    Then I should see "Consent taken back"
    And I should not see "Give my consent"

  Scenario: The manager who does not follow the vaeiste is refused their situations
    Given the following "tool_vae > situations" exist:
      | candidate | fichecode | title       | text                   |
      | candidat  | RNCP400   | Night round | I led the night round. |
    And I log in as "autre"
    Then the "candidat RNCP400-1" "Situation" page should be refused with "Sorry, but you do not currently have permissions to do that"
    And the "candidat RNCP400" "Situations" page should be refused with "Sorry, but you do not currently have permissions to do that"
    And the "candidat" "Consent" page should be refused with "Sorry, but you do not currently have permissions to do that"

  Scenario: The declaration page shows the vaeiste where they stand on the record they declared on
    Given the following "tool_vae > situations" exist:
      | candidate | fichecode | title       | text                   | tags |
      | candidat  | RNCP400   | Night round | I led the night round. | C11  |
    And I log in as "candidat"
    When I am on the "tool_vae > Declare" page
    Then I should see "Where I stand on RNCP400"
    And I should see "1 situation(s), 1 declaration(s)" in the ".tool-vae-whereami [data-rank='C11']" "css_element"
    And I should see "0 situation(s), 0 declaration(s)" in the ".tool-vae-whereami [data-rank='H111']" "css_element"

  Scenario: Another vaeiste is refused the situations of the vaeiste
    Given the following "tool_vae > situations" exist:
      | candidate | fichecode | title       | text                   |
      | candidat  | RNCP400   | Night round | I led the night round. |
    And I log in as "intrus"
    Then the "candidat RNCP400-1" "Situation" page should be refused with "Sorry, but you do not currently have permissions to do that"
    And the "candidat RNCP400" "Situations" page should be refused with "Sorry, but you do not currently have permissions to do that"
    And the "candidat" "Consent" page should be refused with "Sorry, but you do not currently have permissions to do that"

  # Lot 7 (MMR-520, wave 4a): the states of situations.php and situation.php that the tool of equivalence does not serve -
  # the competencies switched off, a record hidden, the preparation closed with the text given back, adding a piece closed,
  # an annotation refused - are drawn by classes of output now; only a request for the pages proves what the pages give them.
  Scenario: With the competencies switched off, the work situations say so and nothing else
    Given the following config values are set as admin:
      | enabled | 0 | core_competency |
    And I log in as "candidat"
    When I am on the "candidat" "tool_vae > Situations" page
    Then I should see "My work situations"
    And I should see "The competency subsystem is switched off on this site."
    And I should not see "Choose a certification record"
    And I should not see "Back to my pathway"

  Scenario: A hidden record is said on the list of the records and on its situations, which stay readable and closed to writing
    Given the following "tool_vae > situations" exist:
      | candidate | fichecode | title       | text                   | tags |
      | candidat  | RNCP400   | Night round | I led the night round. | C11  |
    And the framework of the record "RNCP400" is hidden
    And I log in as "candidat"
    When I am on the "candidat" "tool_vae > Situations" page
    Then I should see "The record Aide-soignant (RNCP400) is hidden" in the ".alert-info" "css_element"
    And I should see "RNCP400" in the "#region-main li" "css_element"
    When I am on the "candidat RNCP400" "tool_vae > Situations" page
    Then I should see "The record Aide-soignant (RNCP400) is hidden" in the ".alert-info" "css_element"
    And I should see "Night round" in the ".tool-vae-situations" "css_element"
    And I should not see "New situation"
    When I am on the "candidat RNCP400-1" "tool_vae > Situation" page
    Then I should see "The record Aide-soignant (RNCP400) is hidden" in the ".alert-info" "css_element"
    And I should see "I led the night round." in the ".tool-vae-situation-text" "css_element"
    And I should not see "Save a version of the text"

  Scenario: The preparation closed under the vaeiste gives back the text typed, and attaching a piece closed is said
    Given the following "tool_vae > situations" exist:
      | candidate | fichecode | title       | text                   | tags |
      | candidat  | RNCP400   | Night round | I led the night round. | C11  |
    And I log in as "candidat"
    And I am on the "candidat RNCP400-1" "tool_vae > Situation" page
    And I set the field "id_content" to "Typed while the preparation closed."
    And the following config values are set as admin:
      | validationopen | 0 | tool_vae |
    When I press "Save a version of the text"
    Then I should see "The preparation of the validation dossier is not open on this site yet." in the ".alert-danger" "css_element"
    And I should see "Your text was not recorded"
    And I should see "Typed while the preparation closed." in the "textarea[readonly]" "css_element"
    When I am on the "candidat RNCP400-1" "tool_vae > Situation" page
    Then I should see "The preparation of the validation dossier is not open on this site yet." in the ".alert-info" "css_element"
    And "textarea[readonly]" "css_element" should not exist
    And I should see "I led the night round." in the ".tool-vae-situation-text" "css_element"
    And I should not see "Attaching new pieces is closed."
    When I am on the "candidat RNCP400" "tool_vae > Situations" page
    Then I should see "The preparation of the validation dossier is not open on this site yet." in the ".alert-info" "css_element"
    And I should not see "New situation"
    And the following config values are set as admin:
      | piecesopen | 0 | tool_vae |
    When I am on the "candidat RNCP400-1" "tool_vae > Situation" page
    Then I should see "Attaching new pieces is closed."
    And I should not see "Attach the link"

  Scenario: The same annotation twice is refused, and said under the text
    Given the following "tool_vae > situations" exist:
      | candidate | fichecode | title       | text                   | tags |
      | candidat  | RNCP400   | Night round | I led the night round. | C11  |
    And the following "tool_vae > consents" exist:
      | candidate | manager     | state |
      | candidat  | responsable | given |
    And I log in as "responsable"
    And I am on the "candidat RNCP400-1" "tool_vae > Situation" page
    And I set the field "Your annotation" to "Say what you checked first."
    And I press "Save the annotation"
    When I set the field "Your annotation" to "Say what you checked first."
    And I press "Save the annotation"
    Then I should see "This annotation is the same as your latest annotation on the same item."
    And I should see "Say what you checked first." in the ".tool-vae-note" "css_element"

  # Lot Q3, then review MMR-520. The file of a piece reaches situation.php through the file picker of the core (a draft area
  # of the vaeiste, which needs JavaScript here); the page reads it there and empties the area.
  @javascript @_file_upload
  Scenario: The vaeiste attaches a file to a situation and reads that it was received
    Given I log in as "candidat"
    And I am on the "candidat RNCP400" "tool_vae > Situations" page
    And I press "New situation"
    And I set the following fields to these values:
      | id_title   | Night round with a sheet on paper |
      | id_setting | Night service                     |
    And I set the field "tool-vae-whereami-tag-C11" to "1"
    And I press "Save the title, the period, the setting and the tags"
    And I set the field "id_content" to "I filled the round sheet on paper that night."
    And I press "Save a version of the text"
    When I set the following fields to these values:
      | id_filenature | Professional writing |
      | id_filetitle  | Round sheet on paper |
      | id_filepart   | The first page       |
    And I upload "admin/tool/vae/tests/fixtures/piece-sample.pdf" file to "File" filemanager
    And I press "Attach the file"
    Then I should see "The file was attached."
    And I should see "Round sheet on paper" in the "#region-main" "css_element"
    And I should see "The first page" in the "#region-main" "css_element"
