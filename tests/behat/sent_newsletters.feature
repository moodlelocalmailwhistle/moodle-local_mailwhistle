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
    # Two report tables render on this page (draft campaigns then sent
    # campaigns), both sharing the "reportbuilder-table" class, so the sent
    # table cannot be targeted by that class alone. The sent report is wrapped
    # in a ".local-mailwhistle-sent-campaigns" container; scope assertions to it.
    Then I should see "First campaign" in the ".local-mailwhistle-sent-campaigns" "css_element"
    And I should see "Read our exciting news" in the ".local-mailwhistle-sent-campaigns" "css_element"
    And I should see "Second campaign" in the ".local-mailwhistle-sent-campaigns" "css_element"
    And I should see "Third campaign" in the ".local-mailwhistle-sent-campaigns" "css_element"
    And I should not see "Draft campaign" in the ".local-mailwhistle-sent-campaigns" "css_element"
