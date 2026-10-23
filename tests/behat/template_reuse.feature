@local @local_mailwhistle @javascript
Feature: Reuse email templates
  In order to reuse saved layouts and exported templates
  As a manager
  I need independent copies that preserve their editor and content

  Background:
    Given I log in as "admin"

  Scenario: Duplicate an HTML template and edit the copy independently
    Given the following "local_mailwhistle > templates" exist:
      | name            | bodyhtml                   |
      | Original layout | <p>Original newsletter</p> |
    When I am on the Mail Whistle "templates" tab
    And I click on "Actions" "button" in the ".local-mailwhistle-template-card[data-template-name='Original layout']" "css_element"
    And I click on "Duplicate" "button" in the ".local-mailwhistle-template-card[data-template-name='Original layout']" "css_element"
    Then I should see "Template duplicated."
    And the field "Template name" matches value "Copy of Original layout"
    And the field "Editor mode" matches value "HTML mode"
    When I set the field "HTML body" to "Changed copy body"
    And I press "Save template"
    Then I should see "Changed copy body"
    When I follow "Back to templates"
    And I click on "Preview" "link" in the ".local-mailwhistle-template-card[data-template-name='Original layout']" "css_element"
    Then I should see "Original newsletter"
    And I should not see "Changed copy body"

  @_file_upload
  Scenario: Import an HTML export twice without overwriting the first template
    When I am on the Mail Whistle "templates" tab
    And I follow "Import template"
    Then I should see "Images and other linked files are not included in the JSON"
    When I upload "local/mailwhistle/tests/fixtures/template_export.json" file to "Template JSON file" filemanager
    And I press "Import template"
    Then I should see "Template imported as a new copy."
    And the field "Template name" matches value "Imported HTML layout"
    And the field "Editor mode" matches value "HTML mode"
    And the field "Preview text" matches value "Imported preview text"
    When I press "Save template"
    Then I should see "Imported HTML content for {{firstname}}"
    When I follow "Back to templates"
    And I follow "Import template"
    And I upload "local/mailwhistle/tests/fixtures/template_export.json" file to "Template JSON file" filemanager
    And I press "Import template"
    Then the field "Template name" matches value "Copy of Imported HTML layout"
    When I press "Cancel"
    Then I should see "Imported HTML layout"
    And I should see "Copy of Imported HTML layout"

  @_file_upload
  Scenario: Import an existing Builder export and duplicate it in Builder mode
    When I am on the Mail Whistle "templates" tab
    And I follow "Import template"
    And I upload "local/mailwhistle/db/defaulttemplates/default_whistle.json" file to "Template JSON file" filemanager
    And I press "Import template"
    Then the field "Template name" matches value "Copy of Default whistle"
    And the field "Editor mode" matches value "Builder mode"
    And the field "Title" matches value "welcome"
    When I press "Save template"
    And I press "Duplicate"
    Then the field "Template name" matches value "Copy of Copy of Default whistle"
    And the field "Editor mode" matches value "Builder mode"
    And the field "Title" matches value "welcome"

  @_file_upload
  Scenario: Invalid JSON displays a field error and creates no template
    When I am on the Mail Whistle "templates" tab
    And I follow "Import template"
    And I upload "local/mailwhistle/tests/fixtures/template_invalid.json" file to "Template JSON file" filemanager
    And I press "Import template"
    Then I should see "Choose a valid Mail Whistle template JSON export"
    And I should not see "Template imported as a new copy."
    When I press "Cancel"
    Then I should see "Default whistle"
    And I should not see "Copy of Default whistle"
