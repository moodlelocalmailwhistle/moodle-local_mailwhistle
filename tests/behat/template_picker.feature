@local @local_mailwhistle @javascript
Feature: Preview and apply a template while editing campaign content
  In order to choose a newsletter design without losing my work
  As a manager
  I need to preview templates and explicitly load one into the email body

  Background:
    Given the following "local_mailwhistle > templates" exist:
      | name            | previewtext           | bodyhtml                              |
      | Bold newsletter | A bold heading style  | <p><strong>Bold news</strong></p>      |
      | Quiet update    | A softer update style | <p><em>Quiet news</em></p>             |
    And the following "local_mailwhistle > campaigns" exist:
      | name         | subject          | bodyhtml              | status |
      | Spring draft | Original subject | <p>Original body</p> | draft  |
    And I log in as "admin"

  Scenario: Choosing a preview does not replace the email body
    When I visit the "content" step for campaign "Spring draft"
    Then the "Load into body" "button" should be disabled
    When I set the field "Email body" to "<p>Unsaved body</p>"
    And I set the field "Use a template" to "Bold newsletter"
    Then the "Load into body" "button" should be enabled
    And I should see "A bold heading style"
    When I switch to "local-mailwhistle-template-preview" iframe
    Then I should see "Bold news"
    When I switch to the main frame
    Then the field "Email body" matches value "<p>Unsaved body</p>"
    When I press "Save and continue"
    Then campaign "Spring draft" should use template ""
    When I visit the "content" step for campaign "Spring draft"
    Then the field "Email body" matches value "<p>Unsaved body</p>"

  @_file_upload
  Scenario: Loading a template preserves other fields and later previews do not change the applied template
    Given I am on the Mail Whistle "resources" tab
    And I upload "local/mailwhistle/tests/fixtures/handbook.pdf" file to "Resources" filemanager
    And I press "Save changes"
    When I visit the "content" step for campaign "Spring draft"
    And I set the field "Subject" to "Unsaved spring subject"
    And I set the field "Attachments" to "handbook.pdf"
    And I set the field "Use a template" to "Bold newsletter"
    And I press "Load into body"
    Then the field "Email body" matches value "<p><strong>Bold news</strong></p>"
    And the field "Subject" matches value "Unsaved spring subject"
    And "#fitem_id_attachments .form-autocomplete-selection [role='option'][data-value='handbook.pdf']" "css_element" should exist
    When I set the field "Use a template" to "Quiet update"
    And I switch to "local-mailwhistle-template-preview" iframe
    Then I should see "Quiet news"
    And I should not see "Bold news"
    When I switch to the main frame
    Then the field "Email body" matches value "<p><strong>Bold news</strong></p>"
    When I press "Save and continue"
    Then campaign "Spring draft" should use template "Bold newsletter"
    When I visit the "content" step for campaign "Spring draft"
    Then the field "Email body" matches value "<p><strong>Bold news</strong></p>"
    And the field "Subject" matches value "Unsaved spring subject"
    And "#fitem_id_attachments .form-autocomplete-selection [role='option'][data-value='handbook.pdf']" "css_element" should exist
