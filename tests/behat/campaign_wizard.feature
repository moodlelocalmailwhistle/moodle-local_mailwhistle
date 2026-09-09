@local @local_mailwhistle
Feature: Create a campaign through the edit wizard
  In order to prepare a newsletter
  As a manager
  I need to create a draft and fill in details, content, and audience

  Background:
    Given the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
    And I log in as "admin"

  Scenario: Create a campaign and save details
    When I am on the Mail Whistle "send" tab
    And I press "Create a campaign"
    Then I should see "1. Details"
    And I should see "2. Content"
    When I set the field "Internal email name" to "Spring digest"
    And I set the field "Sender name" to "Campus News"
    And I set the field "Sender email" to "news@example.com"
    And I press "Save and continue"
    Then I should see "2. Content"
    When I set the field "Subject" to "Spring is here"
    And I set the field "Email body" to "<p>Hello everyone</p>"
    And I press "Save and continue"
    Then I should see "3. Audience"
    And I should see "The campaign will be sent to everyone tagged with any of the selected tags."

  Scenario: Draft campaigns appear on the campaigns tab
    Given the following "local_mailwhistle > campaigns" exist:
      | name         | subject      | bodyhtml        | status |
      | Spring draft | Hello Spring | <p>Body</p>     | draft  |
    When I am on the Mail Whistle "send" tab
    Then I should see "Spring draft"
    And I should see "Draft"
