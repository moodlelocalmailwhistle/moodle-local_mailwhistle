@local @local_mailwhistle
Feature: Reuse a campaign for a follow-up mailing
  In order to follow up with the same audience selection
  As a manager
  I need a new editable draft without reusing the original delivery history

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email              |
      | reader   | News      | Reader   | reader@example.com |
    And the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
    And the following "local_mailwhistle > tag assignments" exist:
      | tag        | user   |
      | Newsletter | reader |
    And the following "local_mailwhistle > campaigns" exist:
      | name        | subject       | bodyhtml             | sendername  | senderemail      | status |
      | Autumn news | Autumn update | <p>Saved welcome</p> | Campus news | news@example.com | sent   |
    And I set the audience tag "Newsletter" for campaign "Autumn news"
    And I log in as "admin"

  Scenario: Duplicate a sent campaign and edit its saved content and audience
    When I am on the Mail Whistle "send" tab
    And I click on "Duplicate" "button" in the "Autumn news" "table_row"
    Then I should see "Campaign duplicated as a new draft."
    And I should see "Current members and opt-outs are applied when sending starts"
    And the field "Internal email name" matches value "Copy of Autumn news"
    And the field "Sender display name" matches value "Campus news"
    And the field "Reply-to email address" matches value "news@example.com"
    And campaign "Autumn news" should have status "sent"
    And campaign "Copy of Autumn news" should have status "draft"
    When I press "Save and continue"
    Then the field "Subject" matches value "Autumn update"
    And the field "Email body" matches value "<p>Saved welcome</p>"
    When I set the field "Subject" to "Autumn follow-up"
    And I press "Save and continue"
    Then "//select[@name='tagids[]']/option[@selected and normalize-space(.)='Newsletter']" "xpath_element" should exist
    When I visit the review step for campaign "Copy of Autumn news"
    Then I should see "Autumn follow-up"
    And I should see "Newsletter"
    And there should be 2 Mail Whistle campaigns

  Scenario: Duplicate a campaign from Reports without changing its original status
    When I am on the Mail Whistle "reports" tab
    And I click on "Duplicate" "button" in the "Autumn news" "table_row"
    Then the field "Internal email name" matches value "Copy of Autumn news"
    And campaign "Autumn news" should have status "sent"
    And campaign "Copy of Autumn news" should have status "draft"
    And there should be 2 Mail Whistle campaigns

  Scenario: Duplicate a scheduled campaign without cancelling its planned send
    Given the following "local_mailwhistle > campaigns" exist:
      | name         | subject      | bodyhtml     | status    | timescheduled |
      | Planned news | Planned news | <p>Hello</p> | scheduled | 1893456000    |
    And I set the audience tag "Newsletter" for campaign "Planned news"
    When I am on the Mail Whistle "send" tab
    And I click on "Duplicate" "button" in the "Planned news" "table_row"
    Then campaign "Planned news" should have status "scheduled"
    And campaign "Copy of Planned news" should have status "draft"
    And the field "Internal email name" matches value "Copy of Planned news"
