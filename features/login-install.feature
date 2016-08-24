Feature: It can install its companion plugin.

  Scenario: The command needs to install a companion plugin to work.
    Given a WP install
    When I run `wp login install`
    Then the wp-content/plugins/wp-cli-login-server/wp-cli-login-server.php file should exist
