Feature: It can control the activation of its companion plugin

  Scenario: It can activate the companion plugin after it is installed.
    Given a WP install
    When I run `wp login install`
    And I run `wp login activate`
    Then STDOUT should be:
      """
      Success: Plugin 'wp-cli-login-server' activated.
      """
    And I run `wp login deactivate`
    Then STDOUT should be:
      """
      Success: Plugin 'wp-cli-login-server' deactivated.
      """
    And I run `wp login activate`
    And I run `wp login disable`
    Then STDOUT should be:
      """
      Success: Plugin 'wp-cli-login-server' deactivated.
      """
