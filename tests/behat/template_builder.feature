@local @local_mailwhistle @javascript
Feature: Safe and accessible template editing
  In order to edit reusable newsletters without losing content
  As a manager
  I need safe editor choices and keyboard controls for blocks

  Background:
    Given I log in as "admin"

  Scenario: Move blocks using keyboard buttons and retain focus
    When I am on the Mail Whistle "templates" tab
    And I follow "Create template"
    And I set the field "Template name" to "Keyboard layout"
    And I click on "Header" "button" in the ".local-mailwhistle-builder-toolbar" "css_element"
    And I set the field "Title" to "First block"
    And I click on "Text" "button" in the ".local-mailwhistle-builder-toolbar" "css_element"
    And I set the field "Content" to "Second block"
    Then the focused element is "Content" "field"
    When I click on "Move up" "button" in the ".local-mailwhistle-builder-block[data-index='1']" "css_element"
    Then the field "Content" in the ".local-mailwhistle-builder-block[data-index='0']" "css_element" matches value "Second block"
    And the focused element is "Move up" "button" in the ".local-mailwhistle-builder-block[data-index='0']" "css_element"
    When I press the tab key
    And I press the enter key
    Then the field "Content" in the ".local-mailwhistle-builder-block[data-index='1']" "css_element" matches value "Second block"
    And the focused element is "Move down" "button" in the ".local-mailwhistle-builder-block[data-index='1']" "css_element"
    When I press "Save template"
    Then I should see "Template saved."
    And "First block" "text" should appear before "Second block" "text" in the ".local-mailwhistle-email-preview" "css_element"

  Scenario: Unsaved blocks survive a mode change and server validation error
    When I am on the Mail Whistle "templates" tab
    And I follow "Create template"
    And I set the field "Template name" to "Keep my blocks"
    And I click on "Header" "button" in the ".local-mailwhistle-builder-toolbar" "css_element"
    And I set the field "Title" to "My unsaved headline"
    And I set the field "Editor mode" to "HTML mode"
    Then I should see "Your content is still in the current editor."
    And the field "Editor mode" matches value "Builder mode"
    And the field "Title" matches value "My unsaved headline"
    When I click on "Button" "button" in the ".local-mailwhistle-builder-toolbar" "css_element"
    And I set the field "URL" to "not a URL"
    And I press "Save template"
    Then I should see "Builder block 2 has an invalid URL."
    And the field "Title" matches value "My unsaved headline"
    And the field "URL" matches value "not a URL"
    When I set the field "URL" to "https://example.com/news"
    And I press "Save template"
    Then I should see "Template saved."
    And I should see "My unsaved headline"

  Scenario: Unsaved HTML cannot be replaced by empty builder blocks
    When I am on the Mail Whistle "templates" tab
    And I follow "Create template"
    And I set the field "Template name" to "Keep my HTML"
    And I set the field "Editor mode" to "HTML mode"
    And I set the field "HTML body" to "HTML that must survive"
    And I set the field "Editor mode" to "Builder mode"
    Then I should see "Your content is still in the current editor."
    And the field "Editor mode" matches value "HTML mode"
    When I press "Save template"
    Then I should see "Template saved."
    And I should see "HTML that must survive"

  Scenario: Create an editable HTML copy while retaining the builder original
    When I am on the Mail Whistle "templates" tab
    And I follow "Create template"
    And I set the field "Template name" to "Builder original"
    And I click on "Header" "button" in the ".local-mailwhistle-builder-toolbar" "css_element"
    And I set the field "Title" to "Original headline"
    And I press "Save template"
    And I follow "Edit"
    And I set the field "Title" to "Current unsaved headline"
    And I press "Save as HTML copy"
    Then I should see "HTML copy created. Your original Builder template is unchanged."
    And the field "Template name" matches value "HTML copy of Builder original"
    And the field "Editor mode" matches value "HTML mode"
    And "#id_editormode option[value='builder']" "css_element" should not exist
    When I press "Save template"
    Then I should see "Current unsaved headline"
    When I follow "Back to templates"
    And I click on "Preview" "link" in the ".local-mailwhistle-template-card[data-template-name='Builder original']" "css_element"
    And I follow "Edit"
    Then the field "Title" matches value "Original headline"
    And the field "Editor mode" matches value "Builder mode"
