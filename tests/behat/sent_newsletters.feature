@local @local_mailwhistle
Feature: An admin can view a list of previously sent campaigns

  Background:
    Given the following "local_mailwhistle > campaigns" exist:
      | name            | subject                | bodyhtml                          | sendername     | status |
      | First campaign  | Read our exciting news | <p>Some important information</p> | Admin User     | sent   |
      | Second campaign | More useful info       | <p>Here is the campaign</p>       | Marketing User | sent   |
      | Third campaign  | Update for you         | <p>Read on for more info</p>      | Test User      | sent   |
      | Draft campaign  | Not yet sent           | <p>Will be really great</p>       | Test User      | draft  |

  Scenario: An admin can see previously sent campaigns
    When I log in as "admin"
    And I navigate to "General > Mail Whistle > Campaigns" in site administration
    Then the following should exist in the "reportbuilder-table" table:
      | Name            | Subject                | Audience | Recipients | Sent by        | Sent at | Status |
      | First campaign  | Read our exciting news | TODO     | 0          | Admin User     |         | Sent   |
      | Second campaign | More useful info       | TODO     | 0          | Marketing User |         | Sent   |
      | Third campaign  | Update for you         | TODO     | 0          | Test User      |         | Sent   |
    And I should not see "Draft campaign" in the "reportbuilder-table" "table"
