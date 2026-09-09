@local @local_mailwhistle
Feature: Send a test copy of a draft campaign
  In order to check how a newsletter looks
  As a manager
  I need to send a test copy to myself from the review step

  Background:
    Given the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
    And the following "local_mailwhistle > campaigns" exist:
      | name        | subject      | bodyhtml           | status |
      | Autumn news | Hello Autumn | <p>Body content</p> | draft  |
    And I set the audience tag "Newsletter" for campaign "Autumn news"
    And I log in as "admin"

  Scenario: Review step can send a test email to the current user
    When I visit the review step for campaign "Autumn news"
    And I press "Send test email to me"
    Then I should see "A test email has been sent"
