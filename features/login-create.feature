Feature: Users can generate single-use magic links that will log them in automatically for a given user.

  Scenario: It requires the server plugin to be installed and active.
    Given a WP install
    When I try `wp login create admin`
    Then STDERR should contain:
      """
      Error: This command requires the companion plugin to be installed and active.
      """
    When I run `wp login install --activate`
    And I run `wp login create admin`
    Then STDOUT should contain:
      """
      Success: Magic login link created!
      """

  Scenario: It can generate magic login URLs using a user ID, login, or email address.
    Given a WP install
    And the login plugin is installed and active
    And I run `wp login create 1`
    Then STDOUT should contain:
      """
      Success: Magic login link created!
      """

    Given a user john john@example.com
    And I run `wp login as john`
    Then STDOUT should contain:
      """
      Success: Magic login link created!
      """

    When I run `wp login as john@example.com`
    Then STDOUT should contain:
      """
      Success: Magic login link created!
      """

    When I try `wp login as nobody@nowhere.com`
    Then STDERR should contain:
      """
      Error: No user found by: nobody@nowhere.com
      """

  Scenario: It can output the magic link URL only if desired.
    Given a WP install
    And a user jane jane@example.com
    And the login plugin is installed and active
    And I run `wp login as jane --url-only`
    Then STDOUT should contain:
      """
      http://localhost:8888/
      """
    And STDOUT should not contain:
      """
      Success: Magic login link created!
      """

  Scenario: It can log the user in using the magic link, but only once.
    Given a WP install
    And a running web server
    And a user evan evan@example.com
    And the login plugin is installed and active
    And I run `echo $(wp login as evan --url-only) > magic_link`
    And I run `ITERATION=1 curl -I -X GET --location $(cat magic_link)`
    Then STDOUT should contain:
      """
      302 Found
      """
    Then STDOUT should contain:
      """
      200 OK
      """
    And STDOUT should contain:
      """
      Set-Cookie: wordpress_logged_in_
      """
    And STDOUT should contain:
      """
      Set-Cookie: wordpressuser_
      """
    And STDOUT should contain:
      """
      Set-Cookie: wordpresspass_
      """
    And I run `ITERATION=2 curl -I -X GET --location $(cat magic_link)`
    Then STDOUT should contain:
      """
      410 Gone
      """

  @flag:expires
  Scenario: The expiration time can be set in seconds
    Given a WP install
    And a running web server
    And a user monalisa leo@vinci.it
    And the login plugin is installed and active

    When I run `wp login as monalisa --url-only --expires=1 > magic_link`
    And I run `sleep 1`
    And I run `curl -I -X GET $(cat magic_link)`

    Then STDOUT should contain:
      """
      410 Gone
      """

  @issue-7
  Scenario: It works for subdirectory installs too.
    Given a WP install in 'subdir'
    And a running web server
    And a user evan evan@example.com
    And the login plugin is installed and active
    And I run `wp login as evan --url-only --path=subdir > magic_link`
    And I run `ITERATION=1 curl -I -X GET --location $(cat magic_link)`
    Then STDOUT should contain:
      """
      302 Found
      """
    Then STDOUT should contain:
      """
      200 OK
      """
    And STDOUT should contain:
      """
      Set-Cookie: wordpress_logged_in_
      """
    And STDOUT should contain:
      """
      Set-Cookie: wordpressuser_
      """
    And STDOUT should contain:
      """
      Set-Cookie: wordpresspass_
      """
    And I run `ITERATION=2 curl -I -X GET --location $(cat magic_link)`
    Then STDOUT should contain:
      """
      410 Gone
      """

  Scenario: It can launch the magic url for the user automatically in their browser.
    Given a WP install
    And the login plugin is installed and active
    And I run `WP_CLI_LOGIN_LAUNCH_WITH=echo wp login as admin --launch --debug`
    Then STDERR should contain:
      """
      Debug (aaemnnosttv/wp-cli-login-command): Launching browser with: echo
      """
    Then STDERR should contain:
      """
      Debug (aaemnnosttv/wp-cli-login-command): http://localhost:8888/
      """
    And STDOUT should contain:
      """
      Magic link launched!
      """
