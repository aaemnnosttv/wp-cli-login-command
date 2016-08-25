Feature: Users can generate single-use magic links that will log them in automatically for a given user.

  Scenario: It can generate magic login URLs using a user ID, login, or email address.
    Given a WP install
    When I run `wp login install --activate`
    And I run `wp user create john john@example.com`
    And I run `wp login as john`
    Then STDOUT should contain:
      """
      Success: Magic login link created!
      """
    And I run `wp login as john@example.com`
    Then STDOUT should contain:
      """
      Success: Magic login link created!
      """

  Scenario: It can output the magic link URL only if desired.
    Given a WP install
    When I run `wp login install --activate`
    And I run `wp user create john john@example.com`
    And I run `wp login as john --url-only`
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
    When I run `wp user create evan evan@example.com`
    And I run `wp login install --activate`
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
      500 Internal Server Error
      """

  Scenario: It can launch the magic url for the user automatically in their browser.
    Given a WP install
    When I run `wp user create evan evan@example.com`
    And I run `wp login install --activate`
    And I run `wp login as evan --launch`
    Then STDOUT should contain:
      """
      Success: Magic link launched!
      """
