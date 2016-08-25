Feature: It can control the activation of its companion plugin

  Scenario: It can activate the companion plugin after it is installed.
    Given a WP install
    When I run `wp login install`
    And I run `wp login toggle on`
    Then STDOUT should be:
      """
      Success: Plugin 'wp-cli-login-server' activated.
      """
    And I run `wp login toggle off`
    Then STDOUT should be:
      """
      Success: Plugin 'wp-cli-login-server' deactivated.
      """
    And I run `wp login toggle`
    Then STDOUT should be:
      """
      Success: Plugin 'wp-cli-login-server' activated.
      """
