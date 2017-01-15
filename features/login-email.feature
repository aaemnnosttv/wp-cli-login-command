Feature: Magic sign-in links can be emailed to the user.

  Scenario: It can email the user with a magic sign-in link.
    Given a WP install
    And a user john john@example.dev
    And the login plugin is installed and active
    When I run `wp login email john --debug`
    Then STDOUT should contain:
      """
      Success: Email sent.
      """
    And STDERR should contain:
      """
      Debug (aaemnnosttv/wp-cli-login-command): Sending email to john@example.dev
      """
