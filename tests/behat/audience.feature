@local @local_mailwhistle
Feature: Audience tagging
  In order to target newsletters at groups of users
  As a manager
  I need to create tags, assign them to users, filter by tag, and remove them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email              |
      | alice    | Alice     | Student  | alice@example.com  |
      | bob      | Bob       | Learner  | bob@example.com    |
    And the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
    And I log in as "admin"

  @javascript
  Scenario: Apply a new tag to a selected user
    When I am on the Mail Whistle "audience" tab
    And I click on "input[name='userids[]']" "css_element" in the "Alice Student" "table_row"
    And I set the field "newtagname" to "VIP"
    And I click on ".mw-apply-tag-controls input[type='submit']" "css_element"
    Then I should see "1 user(s) tagged successfully."
    And user "alice" should have tag "VIP"
    And I should see "VIP" in the "Alice Student" "table_row"

  Scenario: Filter the audience list by tag
    Given the following "local_mailwhistle > tag assignments" exist:
      | tag        | user  |
      | Newsletter | alice |
    When I am on the Mail Whistle "audience" tab
    And I set the field "Tag" to "Newsletter"
    And I click on "#id_submitbutton" "css_element"
    Then I should see "Alice Student"
    And I should not see "Bob Learner"

  Scenario: Remove a tag from a user
    Given the following "local_mailwhistle > tag assignments" exist:
      | tag        | user  |
      | Newsletter | alice |
    When I am on the Mail Whistle "audience" tab
    And I click on "Remove" "link" in the "Alice Student" "table_row"
    And I press "Remove"
    Then I should see "Tag removed."
    And user "alice" should not have tag "Newsletter"
