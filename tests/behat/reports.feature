@local @local_mailwhistle
Feature: Reports tab
  In order to know what reporting is available
  As a manager
  I need to open the Reports tab

  Scenario: Reports tab currently shows a coming-soon notice
    Given I log in as "admin"
    When I am on the Mail Whistle "reports" tab
    Then I should see "Reporting and analytics are coming soon."
