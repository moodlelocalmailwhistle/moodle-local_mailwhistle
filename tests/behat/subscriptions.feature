@local @local_mailwhistle
Feature: Recipients control their campaign email preference
  In order to stop unwanted newsletters
  As a recipient
  I need to confirm an unsubscribe link or change my own preference

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | alice    | Alice     | Reader   | alice@example.com |
    And the following "local_mailwhistle > campaigns" exist:
      | name        | subject | status | bodyhtml    |
      | Autumn news | News    | sent   | <p>News</p> |
    And the following "local_mailwhistle > recipients" exist:
      | campaign    | user  | email             | status |
      | Autumn news | alice | alice@example.com | sent   |

  Scenario: An anonymous unsubscribe link requires confirmation
    When I open the unsubscribe link for "alice" in campaign "Autumn news"
    Then I should see "alice@example.com"
    And user "alice" should not be opted out of campaign emails
    When I press "Unsubscribe from campaign emails"
    Then I should see "You have opted out of Mail Whistle campaign emails"
    And user "alice" should be opted out of campaign emails
    When I open the unsubscribe link for "alice" in campaign "Autumn news"
    And I press "Unsubscribe from campaign emails"
    Then I should see "You have opted out of Mail Whistle campaign emails"

  Scenario: A recipient can opt back in from their own preferences
    Given I open the unsubscribe link for "alice" in campaign "Autumn news"
    And I press "Unsubscribe from campaign emails"
    And I log in as "alice"
    When I am on "/local/mailwhistle/preferences.php"
    Then I should see "You have opted out"
    When I press "Allow campaign emails"
    Then I should see "You have not opted out"
    And user "alice" should not be opted out of campaign emails

  Scenario: Audience shows and filters people who opted out
    Given I open the unsubscribe link for "alice" in campaign "Autumn news"
    And I press "Unsubscribe from campaign emails"
    And I log in as "admin"
    When I am on "/local/mailwhistle/index.php?tab=audience"
    And I set the field "Campaign email preference" to "Opted out"
    And I press "Filter users"
    Then I should see "alice@example.com"
    When I set the field "Campaign email preference" to "Not opted out"
    And I press "Filter users"
    Then I should not see "alice@example.com"

  Scenario: Test unsubscribe links never change preferences
    When I am on "/local/mailwhistle/unsubscribe.php?preview=1"
    Then I should see "This is a preview unsubscribe link"
    And user "alice" should not be opted out of campaign emails
