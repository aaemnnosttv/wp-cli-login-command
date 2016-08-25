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

if (
    is_admin()
    || defined('WP_CLI')
    || defined('DOING_AJAX')
    || 'GET' != strtoupper($_SERVER['REQUEST_METHOD'])
    || (! $magic_pass = filter_input(INPUT_GET, 'magic_login'))
) {
    return;
}

add_action('plugins_loaded', function () use ($magic_pass) {
    WP_CLI_Login_Server::handle($magic_pass);
});

unset($magic_pass);

/**
 * Manage magic passwordless logins.
 */
class WP_CLI_Login_Server
{
    /**
     * Public key passed to identify the unique magic login.
     * @var string
     */
    private $publicKey;

    /**
     * Endpoint to listen for magic login requests.
     * @var string
     */
    private $endpoint;

    /**
     * Option key for the current magic login endpoint.
     */
    const ENDPOINT_OPTION = 'wp_cli_login_endpoint';

    /**
     * WP_CLI_Login_Server constructor.
     *
     * @param $publicKey
     * @param $endpoint
     */
    public function __construct($publicKey, $endpoint)
    {
        $this->publicKey = $publicKey;
        $this->endpoint  = $endpoint;
    }

    /**
     * Handle a new magic login request.
     *
     * @param $publicKey
     */
    public static function handle($publicKey)
    {
        $endpoint = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $server = new static($publicKey, $endpoint);

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
     * @return WP_User
     *
     * @throws AuthenticationFailure
     * @throws InvalidUser
     */
    private function validate(stdClass $magic)
    {
        if (empty($magic->user) || (! $user = new WP_User($magic->user)) || ! $user->exists()) {
            throw new InvalidUser("No user found or no longer exists.");
        }

        if (empty($magic->private) || ! wp_check_password("$this->publicKey|$this->endpoint|{$user->ID}", $magic->private)) {
            throw new AuthenticationFailure("Magic login authentication failed.");
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
        add_action('template_redirect', function () use ($user) {
            delete_transient($this->magicKey());
            wp_set_auth_cookie($user->ID);
            wp_redirect(admin_url());
            exit;
        }, 1);
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
}

class BadMagic extends Exception
{}

class AuthenticationFailure extends Exception
{}

class InvalidUser extends Exception
{}
