@local @local_mailwhistle @javascript @_file_upload
# @javascript is only used for the background but cannot be added selectively to it.
Feature: Using files as resources in mailwhistle
  In order to be able to use the mail whistle mailing list plugin
  As responsible for the mailing list
  I need to be able to upload files and use them as resources in the mailwhistle plugin

  Scenario: Upload files to mailwhistle
    When I log in as "admin"
    And I navigate to "General > Mail Whistle > Campaigns" in site administration
    And I follow "Resources"
    And I upload "local/mailwhistle/tests/fixtures/image_640x480px.jpg" file to "Resources" filemanager
