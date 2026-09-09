@local @local_mailwhistle
Feature: Email templates
  In order to reuse newsletter layouts
  As a manager
  I need to list, create, preview, and archive templates

  Background:
    Given I log in as "admin"

  Scenario: The default template is listed
    When I am on the Mail Whistle "templates" tab
    Then I should see "Email templates"
    And I should see "Default whistle"
    And I should see "Create template"

  Scenario: Create a template in HTML mode
    When I am on the Mail Whistle "templates" tab
    And I follow "Create template"
    And I set the field "Template name" to "Plain HTML"
    And I set the field "Preview text" to "A simple layout"
    And I set the field "Editor mode" to "HTML mode"
    And I set the field "HTML body" to "<p>Hello from a template</p>"
    And I press "Save template"
    Then I should see "Template saved."
    And I should see "Plain HTML"

  @javascript
  Scenario: Create a template in builder mode
    When I am on the Mail Whistle "templates" tab
    And I follow "Create template"
    And I set the field "Template name" to "Visual layout"
    And I set the field "Preview text" to "Built visually"
    And I click on "Header" "button" in the ".local-mailwhistle-builder-toolbar" "css_element"
    And I click on "Button" "button" in the ".local-mailwhistle-builder-toolbar" "css_element"
    And I press "Save template"
    Then I should see "Template saved."
    And I should see "Visual layout"
    And I should see "Newsletter title"
    And I should see "Learn more"

  Scenario: Archive a template
    Given the following "local_mailwhistle > templates" exist:
      | name            | bodyhtml                 |
      | Seasonal header | <p>Seasonal wrapper</p>  |
    When I am on the Mail Whistle "templates" tab
    Then I should see "Seasonal header"
    When I click on "Archive" "button" in the ".local-mailwhistle-template-card[data-template-name='Seasonal header']" "css_element"
    Then I should see "Template archived."
    And I should not see "Seasonal header"
    When I follow "Archived"
    Then I should see "Seasonal header"
