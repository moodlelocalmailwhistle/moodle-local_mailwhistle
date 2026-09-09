@local @local_mailwhistle
Feature: Send a ready campaign
  In order to deliver a newsletter
  As a manager
  I need to queue a ready campaign and have it delivered to tagged users

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | alice    | Alice     | Student  | alice@example.com |
    And the following "local_mailwhistle > tags" exist:
      | name       |
      | Newsletter |
    And the following "local_mailwhistle > tag assignments" exist:
      | tag        | user  |
      | Newsletter | alice |
    And the following "local_mailwhistle > campaigns" exist:
      | name        | subject      | bodyhtml                         | status |
      | Autumn news | Hello Autumn | <p>Hello {{firstname}}</p>       | ready  |
    And I set the audience tag "Newsletter" for campaign "Autumn news"
    And I log in as "admin"

  Scenario: Send now delivers the campaign and moves it to the sent list
    When I am on the Mail Whistle "send" tab
    Then I should see "Autumn news"
    When I click on "Send now" "button" in the "Autumn news" "table_row"
    Then I should see "Campaign queued for sending."
    And campaign "Autumn news" should have status "sending"
    When I run all adhoc tasks
    And I am on the Mail Whistle "send" tab
    Then campaign "Autumn news" should have status "sent"
    And I should see "Autumn news" in the ".local-mailwhistle-sent-campaigns" "css_element"
    And I should see "Hello Autumn" in the ".local-mailwhistle-sent-campaigns" "css_element"

  Scenario: A draft campaign cannot be sent
    Given the following "local_mailwhistle > campaigns" exist:
      | name       | subject | bodyhtml | status |
      | Incomplete |         |          | draft  |
    When I am on the Mail Whistle "send" tab
    Then I should see "Incomplete"
    And "Send now" "button" should not exist in the "Incomplete" "table_row"
