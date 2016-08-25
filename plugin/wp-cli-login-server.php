<?php
/**
 * Plugin Name: WP CLI Login Command Server
 * Description: Companion plugin to the WP-CLI Login Command
 * Author: Evan Mattson
 * Author URI: https://aaemnnost.tv
 * Plugin URI: https://aaemnnost.tv/wp-cli-commands/login/
 *
 * Version: 1.0
 */

namespace WP_CLI_Login;

use stdClass;
use WP_User;
use Exception;

/**
 * Rule out the easy non-requests.
 */
if (defined('WP_CLI')                                   // ignore cli requests
    || defined('DOING_AJAX')                            // ignore ajax requests
    || is_admin()                                       // ignore admin requests
    || count($_GET)                                     // if there is any query string
    || 'GET' != strtoupper($_SERVER['REQUEST_METHOD'])  // GET requests only
) {
    return;
}

/**
 * Fire up the server
 */
add_action('plugins_loaded', function () {
    $request = trim($_SERVER['REQUEST_URI'], '/');
    $fragments = explode('/', $request);

    if (2 !== count($fragments)) {
        return;
    }

    list($endpoint, $public) = $fragments;

    WP_CLI_Login_Server::handle($endpoint, $public);
});



class WP_CLI_Login_Server
{
    /**
     * The http endpoint triggering the request.
     */
    private $endpoint;

    /**
     * Public key passed to identify the unique magic login.
     * @var string
     */
    private $publicKey;

    /**
     * Option key for the current magic login endpoint.
     */
    const ENDPOINT_OPTION = 'wp_cli_login_endpoint';

    /**
     * WP_CLI_Login_Server constructor.
     *
     * @param $endpoint
     * @param $publicKey
     */
    public function __construct($endpoint, $publicKey)
    {
        $this->endpoint = $endpoint;
        $this->publicKey = $publicKey;
    }

    /**
     * Handle a new magic login request.
     *
     * @param $endpoint
     * @param $publicKey
     */
    public static function handle($endpoint, $publicKey)
    {
        $server = new static($endpoint, $publicKey);

        if ($server->checkEndpoint()) {
            $server->run();
        }
    }

    /**
     * Test if endpoints match.
     *
     * @return bool
     */
    public function checkEndpoint()
    {
        return ($this->endpoint === get_option(static::ENDPOINT_OPTION));
    }

    /**
     * Attempt the magic login.
     */
    public function run()
    {
        try {
            $magic = $this->loadMagic();
            $user  = $this->validate($magic);
            $this->loginUser($user);
        } catch (Exception $e) {
            $this->abort($e);
        }
    }

    /**
     * Validate the magic login, and return the user to login if successful.
     *
     * @param stdClass $magic
     *
     * @throws AuthenticationFailure
     * @throws InvalidUser
     *
     * @return WP_User
     */
    private function validate(stdClass $magic)
    {
        if (empty($magic->user) || (! $user = new WP_User($magic->user)) || ! $user->exists()) {
            throw new InvalidUser('No user found or no longer exists.');
        }

        if (empty($magic->private) || ! wp_check_password($this->signature($user), $magic->private)) {
            throw new AuthenticationFailure('Magic login authentication failed.');
        }

        return $user;
    }

    /**
     * Load the saved data for the current magic login.
     *
     * @throws BadMagic
     *
     * @return stdClass
     */
    private function loadMagic()
    {
        $magic = json_decode(
            get_transient($this->magicKey())
        );

        if (is_null($magic)) {
            throw new BadMagic('The attempted magic login has expired or already been used.');
        }

        return $magic;
    }

    /**
     * Login the given user and redirect them to wp-admin.
     *
     * @param WP_User $user
     */
    private function loginUser(WP_User $user)
    {
        delete_transient($this->magicKey());
        wp_set_auth_cookie($user->ID);
        wp_redirect(admin_url());
        exit;
    }

    /**
     * Abort the process; Explode with terrifying message.
     *
     * @param Exception $e
     */
    private function abort(Exception $e)
    {
        $exception        = get_class($e);
        $exceptionMessage = $e->getMessage();
        $common           = sprintf("Try again perhaps? or <a href='%s'>Go Home &rarr;</a>", esc_url(home_url()));
        $message          = "<strong>$exceptionMessage</strong><p>$common</p>";

        wp_die($message, $exception);
    }

    /**
     * Get the key for retrieving magic data for the current request.
     *
     * @return string
     */
    private function magicKey()
    {
        return "wp-cli-login/$this->publicKey";
    }

    /**
     * Build the signature to check against the private key for this request.
     *
     * @param WP_User $user
     *
     * @return string
     */
    private function signature(WP_User $user)
    {
        $domain = parse_url(home_url(), PHP_URL_HOST);

        return "$this->publicKey|$this->endpoint|$domain|$user->ID";
    }
}

class BadMagic extends Exception
{}

class AuthenticationFailure extends Exception
{}

class InvalidUser extends Exception
{}
