@local @local_mailwhistle
Feature: Schedule and cancel future campaigns
  In order to send a campaign at a planned time
  As a manager
  I need a clear schedule and a safe way to cancel it before sending starts

  Background:
    Given campaign email delivery is enabled
    And the following "users" exist:
      | username | firstname | lastname | email                 |
      | reader   | Newsletter | Reader  | reader@example.test   |
    And the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
    And the following "local_mailwhistle > tag assignments" exist:
      | tag        | user   |
      | Newsletter | reader |
    And the following "local_mailwhistle > campaigns" exist:
      | name        | subject       | bodyhtml                 | status |
      | Future news | Planned news  | <p>Hello {{firstname}}</p> | ready  |
    And I set the audience tag "Newsletter" for campaign "Future news"
    And I log in as "admin"

  Scenario: Scheduling confirms the local time and does not start delivery early
    When I am on the Mail Whistle "send" tab
    And I click on "Schedule campaign" "link" in the "Future news" "table_row"
    Then I should see "Time zone"
    And I should see "Recipients are selected from the campaign’s audience when sending starts."
    When I press "Schedule campaign"
    Then I should see "Scheduled for"
    And campaign "Future news" should have status "scheduled"
    When I run the scheduled task "local_mailwhistle\task\dispatch_scheduled_campaigns"
    And I am on the Mail Whistle "send" tab
    Then campaign "Future news" should have status "scheduled"
    And "Send now" "button" should not exist in the "Future news" "table_row"
    And "View schedule" "link" should exist in the "Future news" "table_row"

  Scenario: Cancelling returns a campaign to ready so it can be reopened
    When I am on the Mail Whistle "send" tab
    And I click on "Schedule campaign" "link" in the "Future news" "table_row"
    And I press "Schedule campaign"
    And I press "Cancel schedule"
    Then I should see "Schedule cancelled."
    And campaign "Future news" should have status "ready"
    When I follow "Future news"
    And I press "Return to draft"
    Then campaign "Future news" should have status "draft"

  Scenario: Cron starts a due campaign and the regular worker delivers it
    Given the following "local_mailwhistle > campaigns" exist:
      | name     | subject | bodyhtml                  | status    | timescheduled |
      | Due news | Due now | <p>Hello {{firstname}}</p> | scheduled | 1             |
    And I set the audience tag "Newsletter" for campaign "Due news"
    When I run the scheduled task "local_mailwhistle\task\dispatch_scheduled_campaigns"
    Then campaign "Due news" should have status "sending"
    When I run all adhoc tasks
    Then campaign "Due news" should have status "sent"
    And campaign "Future news" should have status "ready"

  Scenario: An invalid due schedule stays visible for review without sending
    Given the following "local_mailwhistle > campaigns" exist:
      | name           | subject | bodyhtml     | status    | timescheduled | attachmentsjson |
      | Needs checking | News    | <p>Hello</p> | scheduled | 1             | ["missing.pdf"] |
    And I set the audience tag "Newsletter" for campaign "Needs checking"
    When I run the scheduled task "local_mailwhistle\task\dispatch_scheduled_campaigns"
    And I am on the Mail Whistle "send" tab
    Then campaign "Needs checking" should have status "schedulefailed"
    And I should see "Schedule needs review" in the "Needs checking" "table_row"
    When I follow "Needs checking"
    Then I should see "No messages were queued."
    And I should see "missing.pdf"
    When I press "Cancel schedule"
    Then campaign "Needs checking" should have status "ready"
