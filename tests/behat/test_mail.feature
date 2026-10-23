@local @local_mailwhistle @javascript
Feature: Send a test copy of a draft campaign
  In order to check how a newsletter looks
  As a manager
  I need to send untracked test copies to reviewer email addresses

  Background:
    Given campaign email delivery is enabled
    And the following "users" exist:
      | username | firstname | lastname | email               |
      | reviewer | Alice     | Reviewer | manager@example.com |
    And the following "system role assigns" exist:
      | user     | role    |
      | reviewer | manager |
    And the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
    And the following "local_mailwhistle > campaigns" exist:
      | name        | subject              | bodyhtml                    | status |
      | Autumn news | Hello {{firstname}}  | <p>Body for {{firstname}}</p> | draft  |
    And I set the audience tag "Newsletter" for campaign "Autumn news"
    And I log in as "reviewer"

  Scenario: The modal defaults to the current Manager and reports the transport result
    When I visit the review step for campaign "Autumn news"
    And I press "Send test email"
    Then the field "Email addresses" matches value "manager@example.com"
    And I should see "All copies use your current user details"
    When I press "Send test copies"
    Then I should see "Test email results"
    And I should see "manager@example.com" in the "[data-region='test-email-results']" "css_element"
    And I should see "Accepted by the email transport" in the "[data-region='test-email-results']" "css_element"

  Scenario: A list can send to two reviewer addresses with a result for each
    When I visit the review step for campaign "Autumn news"
    And I press "Send test email"
    And I set the field "Email addresses" to "first@example.com; second@example.com; FIRST@example.com"
    And I press "Send test copies"
    Then I should see "first@example.com" in the "[data-region='test-email-results']" "css_element"
    And I should see "second@example.com" in the "[data-region='test-email-results']" "css_element"
    And I should not see "FIRST@example.com" in the "[data-region='test-email-results']" "css_element"

  Scenario: An invalid address keeps the entire request in the modal for correction
    When I visit the review step for campaign "Autumn news"
    And I press "Send test email"
    And I set the field "Email addresses" to "valid@example.com; invalid-address"
    And I press "Send test copies"
    Then I should see "Enter valid email addresses"
    And I should not see "Test email results"
    When I set the field "Email addresses" to "corrected@example.com"
    And I press "Send test copies"
    Then I should see "corrected@example.com" in the "[data-region='test-email-results']" "css_element"
    And I should see "Accepted by the email transport" in the "[data-region='test-email-results']" "css_element"

  Scenario: A narrow screen shows distinct results for a blocked and an accepted destination
    When I change the viewport size to "390x844"
    And I visit the review step for campaign "Autumn news"
    And I press "Send test email"
    Then the field "Email addresses" matches value "manager@example.com"
    When I set the field "Email addresses" to "blocked@example.invalid; accepted@example.com"
    And I press "Send test copies"
    Then I should see "blocked@example.invalid" in the "[data-region='test-email-results']" "css_element"
    And I should see "reserved .invalid domain" in the "[data-region='test-email-results']" "css_element"
    And I should see "accepted@example.com" in the "[data-region='test-email-results']" "css_element"
    And I should see "Accepted by the email transport" in the "[data-region='test-email-results']" "css_element"
