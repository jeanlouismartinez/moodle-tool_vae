@tool @tool_vae @javascript
Feature: The scripts of the plugin run as modules in a real browser, as they did when they were written into the page
  In order to keep a draft I am writing, have each reminder read with its field, and send a confirmed text once
  As a vaeiste
  I need the autosave, the reminders and the confirmation of the aid to work in my browser

  # Lot Q4. The four scripts that were written into the page became three AMD modules: tool_vae/autosave (a section of the
  # feasibility dossier and a work situation), tool_vae/reminder_pointers (the reminders of the free fields) and
  # tool_vae/submit_once (the confirmation of the aid). The autosave sends every twenty seconds, which is why two scenarios
  # wait that long: it is the real delay of the module.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | candidat | Camille   | Durand   | camille@example.com |
    And the following "permission overrides" exist:
      | capability            | permission | role | contextlevel | reference |
      | tool/vae:declare      | Allow      | user | System       |           |
      | tool/vae:viewown      | Allow      | user | System       |           |
      | tool/vae:positionself | Allow      | user | System       |           |
      | tool/vae:draftown     | Allow      | user | System       |           |
      | tool/vae:useai        | Allow      | user | System       |           |
    And the following config values are set as admin:
      | validationopen | 1 | tool_vae |
    And the following "tool_vae > sources" exist:
      | fichecode | ficherang | name          | shortname            |
      | RNCP400   | D1        | Aide-soignant | Accompany the person |
    And the following "tool_vae > competencies" exist:
      | fichecode | ficherang | shortname            | parent |
      | RNCP400   | C11       | Help with daily care | D1     |
    And the following "tool_vae > declarations" exist:
      | candidate | fichecode | ficherang | situation    |
      | candidat  | RNCP400   | C11       | Night rounds |

  Scenario: A section of the feasibility dossier keeps the text being written, without a click
    Given I log in as "candidat"
    When I visit "/admin/tool/vae/section.php?section=objectifs"
    And I set the field "Text of the section" to "Je veux obtenir le diplome pour evoluer."
    Then I should not see "Draft saved at" in the "#tool-vae-autosave" "css_element"
    And I wait "22" seconds
    Then I should see "Draft saved at" in the "#tool-vae-autosave" "css_element"
    When I reload the page
    Then the field "Text of the section" matches value "Je veux obtenir le diplome pour evoluer."

  # Ticket 5 of MMR-520: the autosave calls the external function tool_vae_save_draft through core/ajax. A conflict between two
  # tabs is an answer of the function, said in the live region in the words of the plugin, with no dialog of the core; the
  # draft the other tab saved is not overwritten.
  Scenario: On a section, a draft saved by another tab is not overwritten and the page says so without a dialog
    Given I log in as "candidat"
    When I visit "/admin/tool/vae/section.php?section=objectifs"
    And another tab of "candidat" saves the draft in progress "Le texte de l'autre onglet." of the section "objectifs"
    And I set the field "Text of the section" to "Le texte de cette page."
    And I wait "22" seconds
    Then I should see "This text was changed somewhere else since you opened it." in the "#tool-vae-autosave" "css_element"
    And I should not see "Draft saved at" in the "#tool-vae-autosave" "css_element"
    And ".modal-dialog" "css_element" should not exist
    When I reload the page
    Then the field "Text of the section" matches value "Le texte de l'autre onglet."

  # The one difference the autosave of a section has: the button of the aid sends the form to a new tab, and the page being
  # written goes on saving. The new tab is closed, which leaves the page as the aid left it.
  Scenario: On a section, asking for the aid in a new tab does not stop the autosave of the page being written
    Given the following "tool_vae > aiproviders" exist:
      | name           |
      | Ollama d'essai |
    And the following config values are set as admin:
      | aiopen | 1 | tool_vae |
    And I log in as "candidat"
    When I visit "/admin/tool/vae/section.php?section=objectifs"
    And I set the field "Text of the section" to "Un texte qui reste sur la page."
    And I press "Propose another wording"
    And I switch to a second window
    Then I should see "AI usage policy"
    When I close all opened windows
    Then I should see "Objectives of the approach"
    And I should not see "Draft saved at" in the "#tool-vae-autosave" "css_element"
    And I wait "22" seconds
    Then I should see "Draft saved at" in the "#tool-vae-autosave" "css_element"

  Scenario: A work situation keeps the text being written, without a click
    Given the following "tool_vae > situations" exist:
      | candidate | fichecode | title       | text                   | tags |
      | candidat  | RNCP400   | Night round | I led the night round. | C11  |
    And I log in as "candidat"
    When I am on the "candidat RNCP400-1" "tool_vae > Situation" page
    And I set the field "id_content" to "I led the night round with a new colleague."
    Then I should not see "Draft saved at" in the "#tool-vae-autosave" "css_element"
    And I wait "22" seconds
    Then I should see "Draft saved at" in the "#tool-vae-autosave" "css_element"
    When I reload the page
    Then the field "id_content" matches value "I led the night round with a new colleague."

  # A refusal of the function is an exception of the plugin: the page says its sentence, with no dialog of the core, and the text
  # stays on the page. Here the preparation is closed while the page is open.
  Scenario: A work situation whose preparation is closed while it is written says the refusal without a dialog
    Given the following "tool_vae > situations" exist:
      | candidate | fichecode | title       | text                   | tags |
      | candidat  | RNCP400   | Night round | I led the night round. | C11  |
    And I log in as "candidat"
    When I am on the "candidat RNCP400-1" "tool_vae > Situation" page
    And the following config values are set as admin:
      | validationopen | 0 | tool_vae |
    And I set the field "id_content" to "I led the night round with a new colleague."
    And I wait "22" seconds
    Then I should see "The preparation of the validation dossier is not open" in the "#tool-vae-autosave" "css_element"
    And I should not see "Draft saved at" in the "#tool-vae-autosave" "css_element"
    And ".modal-dialog" "css_element" should not exist
    And the field "id_content" matches value "I led the night round with a new colleague."

  Scenario: Each free field of the declaration is described by its reminders, each once
    Given I log in as "candidat"
    When I am on the "tool_vae > Declare" page
    Then "textarea#id_situation[aria-describedby='tool_vae_anonymise_situation tool_vae_anonymise']" "css_element" should exist
    And "input#id_settingdetail[aria-describedby='tool_vae_anonymise_settingdetail tool_vae_anonymise']" "css_element" should exist

  # The send reaches ai.php, which checks the confirmation and answers. Nothing leaves the site: once the confirmation is shown,
  # the site lowers the length that may be sent under the length of the text, so the send step refuses it before any provider
  # is asked, in the plugin's own words.
  Scenario: The confirmation of the aid is sent as it was without the module
    Given the following "tool_vae > aiproviders" exist:
      | name           |
      | Ollama d'essai |
    And the following config values are set as admin:
      | aiopen                    | 1 | tool_vae         |
      | automaticdeletionrequests | 1 | tool_dataprivacy |
    And I log in as "candidat"
    When I am on the "tool_vae > Declare" page
    And I set the field "Work situation" to "Le matin, j'aide les residents a se lever, a se laver et a s'habiller, en respectant leur rythme et leurs habitudes. Le matin, j'aide les residents a se lever, a se laver et a s'habiller, en respectant leur rythme et leurs habitudes. Le matin, j'aide les residents a se lever, a se laver et a s'habiller, en respectant leur rythme et leurs habitudes. Le matin, j'aide les residents a se lever, a se laver et a s'habiller, en respectant leur rythme et leurs habitudes. Le matin, j'aide les residents a se lever, a se laver et a s'habiller."
    And I press "Propose another wording"
    And I switch to a second window
    And I press "I have read the policy and I accept it"
    Then I should see "Check what will be sent"
    And the following config values are set as admin:
      | aimaxchars | 500 | tool_vae |
    When I press "Send this text to Ollama d'essai"
    Then I should see "The text is too long: at most 500 characters can be sent."
    And I should not see "Check what will be sent"
    And I should see "Le matin, j'aide les residents a se lever"
