@local @local_mailwhistle
Feature: Audience tagging
  In order to target newsletters at groups of users
  As a manager
  I need to find people, select them, and add an existing or new tag

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | alice    | Alice     | Student  | alice@audience.test  |
      | bob      | Bob       | Learner  | bob@audience.test    |
    And I log in as "admin"

  @javascript
  Scenario: Create the first tag for selected people
    When I am on the Mail Whistle "audience" tab
    Then I should see "No people selected"
    And the "Add tag to selected people" "button" should be disabled
    When I click on "input[name='userids[]']" "css_element" in the "Alice Student" "table_row"
    Then I should see "Selected people: 1"
    And the "Add tag to selected people" "button" should be disabled
    When I set the field "New tag name" to "VIP"
    Then the "Add tag to selected people" "button" should be enabled
    When I press "Add tag to selected people"
    Then I should see "Added tag"
    And user "alice" should have tag "VIP"
    And user "bob" should not have tag "VIP"
    And I should see "VIP" in the "Alice Student" "table_row"

  @javascript
  Scenario: Switching back to an existing tag ignores a typed new name
    Given the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
    When I am on the Mail Whistle "audience" tab
    And I click on "input[name='userids[]']" "css_element" in the "Alice Student" "table_row"
    And I click on "Create a new tag" "radio"
    And I set the field "New tag name" to "Unused new group"
    And I click on "Use an existing tag" "radio"
    And I set the field "applytagid" to "Newsletter"
    And I press "Add tag to selected people"
    Then user "alice" should have tag "Newsletter"
    And user "alice" should not have tag "Unused new group"
    And user "bob" should not have tag "Newsletter"

  @javascript
  Scenario: Select all filtered people then deselect one while retaining the filters
    Given the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
      | VIP        |
    And the following "local_mailwhistle > tag assignments" exist:
      | tag        | user  |
      | Newsletter | alice |
      | Newsletter | bob   |
    When I am on the Mail Whistle "audience" tab
    And I set the field "Search name / email" to "@audience.test"
    And I set the field "Tag" to "Newsletter"
    And I set the field "Status" to "Active only"
    And I set the field "Authentication" to "manual"
    And I press "Filter users"
    And I click on "[data-action='select-all'][aria-label='Select all people on this page']" "css_element"
    Then I should see "Selected people: 2"
    When I click on "input[name='userids[]']" "css_element" in the "Bob Learner" "table_row"
    Then I should see "Selected people: 1"
    When I set the field "applytagid" to "VIP"
    And I press "Add tag to selected people"
    Then user "alice" should have tag "VIP"
    And user "bob" should not have tag "VIP"
    And the field "Search name / email" matches value "@audience.test"
    And the field "Tag" matches value "Newsletter"
    And the field "Status" matches value "Active only"
    And the field "Authentication" matches value "manual"
    And I should see "Alice Student"
    And I should see "Bob Learner"
    And I should not see "Admin User" in the "#local-mailwhistle-audience-form table" "css_element"

  Scenario: Filter the audience list by tag
    Given the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
    And the following "local_mailwhistle > tag assignments" exist:
      | tag        | user  |
      | Newsletter | alice |
    When I am on the Mail Whistle "audience" tab
    And I set the field "Tag" to "Newsletter"
    And I press "Filter users"
    Then I should see "Alice Student"
    And I should not see "Bob Learner"
    When I follow "Clear filters"
    Then I should see "Alice Student"
    And I should see "Bob Learner"
    And the field "Tag" matches value "Any"

  Scenario: Remove a tag from a user
    Given the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
    And the following "local_mailwhistle > tag assignments" exist:
      | tag        | user  |
      | Newsletter | alice |
    When I am on the Mail Whistle "audience" tab
    And I click on "Remove" "link" in the "Alice Student" "table_row"
    And I press "Remove"
    Then I should see "Tag removed."
    And user "alice" should not have tag "Newsletter"
