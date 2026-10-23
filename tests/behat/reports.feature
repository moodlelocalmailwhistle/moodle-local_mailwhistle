@local @local_mailwhistle
Feature: Reports tab
  In order to see who a campaign was sent to
  As a manager
  I need to open the Reports tab and drill into a campaign

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | alice    | Alice     | Rowe     | alice@example.com |
      | bob      | Bob       | Learner  | bob@example.com   |
    And the following "local_mailwhistle > campaigns" exist:
      | name   | subject                         | bodyhtml                                                      | status |
      | Spring | Spring news for {{firstname}}   | <p>Hello {{firstname}}</p><p>Your saved spring newsletter.</p> | sent   |
    And the following "local_mailwhistle > recipients" exist:
      | campaign | user  | email             | firstname | lastname | status |
      | Spring   | alice | alice@example.com | Alice     | Rowe     | sent   |
      | Spring   | bob   | bob@example.com   | Bob       | Learner  | sent   |

  Scenario: Reports list shows sent campaigns
    Given I log in as "admin"
    When I am on the Mail Whistle "reports" tab
    Then I should see "Campaign reports"
    And I should see "Spring" in the ".local-mailwhistle-reports" "css_element"
    And I should see "Spring news for {{firstname}}" in the ".local-mailwhistle-report-campaign" "css_element"
    And I should see "2 of 2 sent" in the ".local-mailwhistle-report-delivery" "css_element"
    And I should see "0.0% of sent" in the ".local-mailwhistle-report-opens" "css_element"
    And I should see "Not recorded" in the ".local-mailwhistle-report-date" "css_element"
    And I should see "How to read these figures"
    And I should not see "Reporting and analytics are coming soon."

  Scenario: Campaign report shows who was sent
    Given I log in as "admin"
    When I am on the Mail Whistle "reports" tab
    And I follow "Spring"
    Then I should see "Spring news"
    And I should see "Alice Rowe"
    And I should see "alice@example.com"
    And I should see "Who was sent this campaign"

  @javascript
  Scenario: Managers review and switch recipient emails on a narrow screen
    Given I log in as "admin"
    And I change viewport size to "390x844"
    When I am on the Mail Whistle "reports" tab
    And I follow "Spring"
    Then I should see "Spring news for Alice" in the "#local-mailwhistle-report-email" "css_element"
    And I should see "Alice Rowe <alice@example.com>" in the "#local-mailwhistle-report-email" "css_element"
    When I switch to "local-mailwhistle-report-email-frame" iframe
    Then I should see "Hello Alice"
    And I should see "Your saved spring newsletter."
    When I switch to the main frame
    And I click on "a[aria-label='View email for Bob Learner <bob@example.com>']" "css_element"
    Then I should see "Spring news for Bob" in the "#local-mailwhistle-report-email" "css_element"
    And I should see "Bob Learner <bob@example.com>" in the "#local-mailwhistle-report-email" "css_element"
    When I switch to "local-mailwhistle-report-email-frame" iframe
    Then I should see "Hello Bob"
    And I should not see "Hello Alice"
    When I switch to the main frame
    Then I should see "Who was sent this campaign"
