Feature: It can install its companion plugin.

  Scenario: The command needs to install a companion plugin to work.
    Given a WP install
    When I run `wp login install`
    Then the wp-content/plugins/wp-cli-login-server/wp-cli-login-server.php file should exist

  @flag:mu
  Scenario: The command can be installed as an Must Use plugin.
    Given a WP install
    When I run `wp login install --mu`
    Then the wp-content/mu-plugins/wp-cli-login-server.php file should exist
    And the wp-content/plugins/wp-cli-login-server/wp-cli-login-server.php file should not exist
