@local @local_mailwhistle
Feature: Reports tab
  In order to see who a campaign was sent to
  As a manager
  I need to open the Reports tab and drill into a campaign

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | alice    | Alice     | Rowe     | alice@example.com |
    And the following "local_mailwhistle > campaigns" exist:
      | name   | subject     | bodyhtml     | status |
      | Spring | Spring news | <p>Hello</p> | sent   |
    And the following "local_mailwhistle > recipients" exist:
      | campaign | user  | email             | firstname | lastname | status |
      | Spring   | alice | alice@example.com | Alice     | Rowe     | sent   |

  Scenario: Reports list shows sent campaigns
    Given I log in as "admin"
    When I am on the Mail Whistle "reports" tab
    Then I should see "Campaign reports"
    And I should see "Spring" in the ".local-mailwhistle-reports" "css_element"
    And I should not see "Reporting and analytics are coming soon."

  Scenario: Campaign report shows who was sent
    Given I log in as "admin"
    When I am on the Mail Whistle "reports" tab
    And I follow "Spring"
    Then I should see "Spring news"
    And I should see "Alice Rowe"
    And I should see "alice@example.com"
    And I should see "Who was sent this campaign"
