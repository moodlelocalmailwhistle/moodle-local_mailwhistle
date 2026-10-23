@local @local_mailwhistle
Feature: Prepare and revise campaigns safely as a Manager
  In order to send a valid campaign to the right people
  As a Manager
  I need access to Mail Whistle and clear checks before completing a campaign

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | suspended |
      | manager1 | Campaign  | Manager  | manager@example.test | 0         |
      | alice    | Alice     | Learner  | alice@example.test   | 0         |
      | paused   | Paused    | Learner  | paused@example.test  | 1         |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |
    And the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
      | Paused     |
    And the following "local_mailwhistle > tag assignments" exist:
      | tag        | user   |
      | Newsletter | alice  |
      | Paused     | paused |
    And I log in as "manager1"

  Scenario: A default Manager can create, complete and reopen a campaign
    When I am on the Mail Whistle "send" tab
    And I press "Create a campaign"
    And I set the field "Internal email name" to "Manager newsletter"
    And I press "Save and continue"
    And I set the field "Subject" to "Original subject"
    And I set the field "Email body" to "<p>Hello everyone</p>"
    And I press "Save and continue"
    And I set the audience tag "Newsletter" for campaign "Manager newsletter"
    And I visit the review step for campaign "Manager newsletter"
    Then I should see "Newsletter" in the "Audience tags" "table_row"
    And I should see "1" in the "Eligible recipients" "table_row"
    When I press "Mark campaign complete"
    Then campaign "Manager newsletter" should have status "ready"
    When I follow "Manager newsletter"
    And I press "Return to draft"
    Then campaign "Manager newsletter" should have status "draft"
    When I follow "2. Content"
    Then the field "Subject" matches value "Original subject"
    When I set the field "Subject" to "Corrected subject"
    And I press "Save and continue"
    And I visit the review step for campaign "Manager newsletter"
    And I press "Mark campaign complete"
    Then campaign "Manager newsletter" should have status "ready"

  Scenario: A tag containing only suspended people cannot complete a campaign
    Given the following "local_mailwhistle > campaigns" exist:
      | name             | subject | bodyhtml     | status |
      | No eligible mail | News    | <p>Hello</p> | draft  |
    And I set the audience tag "Paused" for campaign "No eligible mail"
    When I visit the review step for campaign "No eligible mail"
    Then I should see "Paused" in the "Audience tags" "table_row"
    And I should see "0" in the "Eligible recipients" "table_row"
    And I should see "Choose audience groups containing at least one eligible person."
    And "Mark campaign complete" "button" should not exist
    And campaign "No eligible mail" should have status "draft"

  Scenario: Empty rich text is rejected and an image-only message is accepted
    Given the following "local_mailwhistle > campaigns" exist:
      | name         | subject | bodyhtml     | status |
      | Content test | News    | <p>Hello</p> | draft  |
    When I visit the "content" step for campaign "Content test"
    And I set the field "Email body" to "<p>&nbsp;<br></p>"
    And I press "Save and continue"
    Then I should see "Add text or an image to the email body in Content."
    When I set the field "Email body" to "<p><img src='https://example.test/banner.png' alt='News'></p>"
    And I press "Save and continue"
    Then I should see "Choose the groups of people for this campaign."

  Scenario: Delivery states never offer reopening
    Given the following "local_mailwhistle > campaigns" exist:
      | name           | subject | bodyhtml     | status  |
      | Sending locked | News    | <p>Hello</p> | sending |
      | Sent locked    | News    | <p>Hello</p> | sent    |
    When I visit the "details" step for campaign "Sending locked"
    Then "Return to draft" "button" should not exist
    And "Internal email name" "field" should not exist
    When I visit the "details" step for campaign "Sent locked"
    Then "Return to draft" "button" should not exist
    And "Internal email name" "field" should not exist
