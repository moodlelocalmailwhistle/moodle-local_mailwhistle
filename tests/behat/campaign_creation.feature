@local @local_mailwhistle
Feature: Campaign creation requires a valid name
  In order to keep the campaign list free of abandoned unnamed drafts
  As a manager
  I need campaigns to be created only when I save valid details

  Background:
    Given I log in as "admin"

  Scenario: Cancelling the initial details form does not create a campaign
    When I am on the Mail Whistle "send" tab
    And I press "Create a campaign"
    Then "Internal email name" "field" should exist
    And "2. Content" "link" should not exist
    And there should be 0 Mail Whistle campaigns
    When I press "Cancel"
    Then I should see "No draft or scheduled campaigns yet."
    And there should be 0 Mail Whistle campaigns

  Scenario: Empty and whitespace-only names do not create a campaign
    When I am on the Mail Whistle "send" tab
    And I press "Create a campaign"
    And I press "Save and continue"
    Then I should see "Required"
    And "Internal email name" "field" should exist
    And there should be 0 Mail Whistle campaigns
    When I set the field "Internal email name" to "   "
    And I press "Save and continue"
    Then I should see "Required"
    And "Internal email name" "field" should exist
    And there should be 0 Mail Whistle campaigns

  Scenario: Saving valid details creates one draft that can be reopened
    When I am on the Mail Whistle "send" tab
    And I press "Create a campaign"
    And I set the field "Internal email name" to "Spring digest"
    And I set the field "Sender display name" to "Campus News"
    And I set the field "Reply-to email address" to "news@example.com"
    And I press "Save and continue"
    Then "Subject" "field" should exist
    And there should be 1 Mail Whistle campaigns
    And campaign "Spring digest" should have status "draft"
    When I press "Cancel"
    And I follow "Spring digest"
    Then the field "Internal email name" matches value "Spring digest"
    And the field "Sender display name" matches value "Campus News"
    And the field "Reply-to email address" matches value "news@example.com"
    And "2. Content" "link" should exist
    And there should be 1 Mail Whistle campaigns

  Scenario: An existing unnamed draft has a link and can be renamed
    Given the following "local_mailwhistle > campaigns" exist:
      | name | subject        | status |
      |      | Legacy subject | draft  |
    When I am on the Mail Whistle "send" tab
    And I follow "Untitled campaign"
    Then the field "Internal email name" matches value ""
    When I set the field "Internal email name" to "Recovered campaign"
    And I press "Save and continue"
    Then the field "Subject" matches value "Legacy subject"
    And there should be 1 Mail Whistle campaigns
    When I press "Cancel"
    Then "Recovered campaign" "link" should exist
    And there should be 1 Mail Whistle campaigns
