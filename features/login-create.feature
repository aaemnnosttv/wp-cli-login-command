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

  @install:mu @issue:14
  Scenario: It works without activation when installed as an mu plugin.
    Given a WP install
    And the login plugin is installed as an mu plugin
    When I try `wp login create admin`
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
      http://localhost:8080/
      """
    And STDOUT should not contain:
      """
      Success: Magic login link created!
      """

  Scenario: It can log the user in using the magic link, but only once.
    Given a WP install
    And a PHP built-in web server
    And a user evan evan@example.com
    And the login plugin is installed and active
    And I run `wp login as evan --url-only`
    And save STDOUT as {MAGIC_LINK}
    # Request the link the first time, following redirects.
    And I try `curl --request GET --head --location {MAGIC_LINK}`
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
    # Request the link the second time, following redirects.
    And I try `curl --request GET --head --location {MAGIC_LINK}`
    Then STDOUT should contain:
      """
      410 Gone
      """

  @option:expires
  Scenario: The expiration time can be set in seconds
    Given a WP install
    And a PHP built-in web server
    And a user monalisa leo@vinci.it
    And the login plugin is installed and active

    When I run `wp login as monalisa --url-only --expires=1`
    And save STDOUT as {MAGIC_LINK}
    And I run `sleep 2`
    And I try `curl --request GET --head {MAGIC_LINK}`

    Then STDOUT should contain:
      """
      410 Gone
      """

  @issue:7
  Scenario: It works for subdirectory installs too.
    Given a WP install in 'subdir'
    And a PHP built-in web server to serve 'subdir'
    And a user evan evan@example.com
    And the login plugin is installed and active
    And I run `wp login as evan --url-only --path=subdir`
    And save STDOUT as {MAGIC_LINK}
    # Request the link the first time, following redirects.
    And I try `curl --request GET --head --location {MAGIC_LINK}`
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
    # Request the link the second time, following redirects.
    And I try `curl --request GET --head --location {MAGIC_LINK}`
    Then STDOUT should contain:
      """
      410 Gone
      """

  Scenario: It can launch the magic url for the user automatically in their browser.
    Given a WP install
    And the login plugin is installed and active
    And I try `WP_CLI_LOGIN_LAUNCH_WITH=echo wp login as admin --launch --debug`
    Then STDERR should contain:
      """
      Debug (aaemnnosttv/wp-cli-login-command): Launching browser with: echo
      """
    Then STDERR should contain:
      """
      Debug (aaemnnosttv/wp-cli-login-command): http://localhost:8080/
      """
    And STDOUT should contain:
      """
      Magic link launched!
      """

  @option:redirect-url
  Scenario: It can redirect the user to an alternate URL on successful login.
    Given a WP install
    And a PHP built-in web server
    And the login plugin is installed and active
    And I run `wp login as admin --url-only --redirect-url=http://localhost:8080/custom-redirect`
    And save STDOUT as {MAGIC_LINK}
    And I try `curl --request GET --head {MAGIC_LINK}`
    Then STDOUT should contain:
      """
      Set-Cookie: wordpress_logged_in_
      """
    Then STDOUT should contain:
      """
      302 Found
      """
    Then STDOUT should contain:
      """
      Location: http://localhost:8080/custom-redirect
      """
