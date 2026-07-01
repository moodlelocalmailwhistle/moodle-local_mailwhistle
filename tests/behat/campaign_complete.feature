@local @local_mailwhistle
Feature: Mark a draft campaign as complete
  In order to finish preparing a newsletter
  As a manager
  I need to mark a fully-prepared draft campaign as ready

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | manager1 | Man       | Ager     | manager1@test.com |
    And the following "local_mailwhistle > tags" exist:
      | name      |
      | Newsletter |
    And I log in as "admin"

  @javascript
  Scenario: A complete draft campaign can be marked complete from the review step
    Given the following "local_mailwhistle > campaigns" exist:
      | name        | subject      | bodyhtml           | status |
      | Autumn news | Hello Autumn | <p>Body content</p> | draft  |
    And I set the audience tag "Newsletter" for campaign "Autumn news"
    When I visit the review step for campaign "Autumn news"
    And I press "Mark campaign complete"
    Then I should see "Campaign marked as ready"
    And campaign "Autumn news" should have status "ready"

  @javascript
  Scenario: An incomplete draft cannot be completed
    Given the following "local_mailwhistle > campaigns" exist:
      | name       | subject | bodyhtml | status |
      | Empty one  |         |          | draft  |
    When I visit the review step for campaign "Empty one"
    Then I should not see "Mark campaign complete"
    And I should see "Add a name, subject, body and at least one audience tag"
