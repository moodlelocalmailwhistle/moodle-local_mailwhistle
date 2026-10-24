@local @local_mailwhistle
Feature: Understand recipient eligibility and delivery progress
  In order to send to the right audience and recognise stalled sending
  As a manager
  I need a recipient preview and clear background task information

  Background:
    Given campaign email delivery is enabled
    And the following "users" exist:
      | username | firstname | lastname | email                 | suspended | emailstop |
      | reader   | Active    | Reader   | reader@example.test   | 0         | 0         |
      | paused   | Paused    | Reader   | paused@example.test   | 1         | 0         |
      | optedout | Optedout  | Reader   | optedout@example.test | 0         | 1         |
    And the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
    And the following "local_mailwhistle > tag assignments" exist:
      | tag        | user     |
      | Newsletter | reader   |
      | Newsletter | paused   |
      | Newsletter | optedout |
    And I log in as "admin"

  Scenario: Review explains which audience members cannot receive the campaign
    Given the following "local_mailwhistle > campaigns" exist:
      | name          | subject | bodyhtml     | status |
      | Audience test | News    | <p>Hello</p> | draft  |
    And I set the audience tag "Newsletter" for campaign "Audience test"
    When I visit the review step for campaign "Audience test"
    Then I should see "1" in the "Eligible recipients" "table_row"
    And I should see "2" in the "Excluded recipients" "table_row"
    And I should see "Eligible to receive email" in the "Active Reader" "table_row"
    And I should see "account is unavailable or inactive" in the "Paused Reader" "table_row"
    And I should see "notification settings do not permit campaign email" in the "Optedout Reader" "table_row"
    And "Mark campaign complete" "button" should exist

  Scenario: Suspended people are clearly marked in audience management
    When I am on the Mail Whistle "audience" tab
    Then I should see "Suspended — will not receive mail" in the "Paused Reader" "table_row"
    And I should not see "Suspended — will not receive mail" in the "Active Reader" "table_row"

  @javascript
  Scenario: A sending report explains a missing worker on a narrow screen
    Given the following "local_mailwhistle > campaigns" exist:
      | name        | subject | bodyhtml     | status  |
      | Waiting job | News    | <p>Hello</p> | sending |
    And the following "local_mailwhistle > recipients" exist:
      | campaign    | user   | email               | status  |
      | Waiting job | reader | reader@example.test | pending |
    And I change viewport size to "390x844"
    When I am on the Mail Whistle "reports" tab
    And I follow "Waiting job"
    Then I should see "Sending progress"
    And I should see "No delivery task is currently queued"
    And I should see "Time since queued"
    And I should see "No delivery attempt recorded yet"
    When I click on "Background task status" "text"
    Then I should see "Last run"
    And I should see "Manage scheduled tasks"
    And I should see "View task logs"
