<?php
/**
 * Plugin Name: WP CLI Login Command Server
 * Description: Companion plugin to the WP-CLI Login Command
 * Author: Evan Mattson
 * Author URI: https://aaemnnost.tv
 * Plugin URI: https://aaemnnost.tv/wp-cli-commands/login/
 *
 * Version: 1.5
 */

namespace WP_CLI_Login;

use WP_User;
use Exception;

/**
 * Fire up the server
 */
function init_server_from_request()
{
    list($endpoint, $public) = WP_CLI_Login_Server::parseUri(@$_SERVER['REQUEST_URI']);
    WP_CLI_Login_Server::handle($endpoint, $public);
}
if (is_eligible_request()) {
    add_action('plugins_loaded', __NAMESPACE__ . '\\init_server_from_request');
}

/**
 * @return bool
 */
function is_eligible_request()
{
    return ! (
        (defined('WP_CLI') && WP_CLI)                       // ignore cli requests
        || (defined('DOING_AJAX') && DOING_AJAX)            // ignore ajax requests
        || (defined('DOING_CRON') && DOING_CRON)            // ignore cron requests
        || (defined('WP_INSTALLING') && WP_INSTALLING)      // WP ain't ready
        || empty($_SERVER['REQUEST_METHOD'])               // Invalid request
        || 'GET' !== strtoupper($_SERVER['REQUEST_METHOD']) // GET requests only
        || count($_GET) > 0                                 // if there is any query string
        || is_admin()                                       // ignore admin requests
    );
}



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
     * Option key for the persisted-data.
     */
    const OPTION = 'wp_cli_login';

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
     * Parse the endpoint & key from the URI, if any.
     *
     * @param $uri string  Request URI
     *
     * @return array [endpoint, key]
     */
    public static function parseUri($uri)
    {
        $uri = trim($uri, '/');
        $segments = explode('/', $uri);

        // If there aren't at least 2 segments,
        // return empty values to always return an array with the same length.
        if (count($segments) < 2) {
            return ['', ''];
        }

        return array_slice($segments, -2);
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
        if ($saved = json_decode(get_option(static::OPTION))) {
            return $this->endpoint === $saved->endpoint;
        }

        return false;
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
            $this->loginRedirect($user, $magic->redirect_url);
        } catch (Exception $e) {
            $this->deleteMagic();
            $this->abort($e);
        }
    }

    /**
     * Validate the magic login, and return the user to login if successful.
     *
     * @param Magic $magic
     *
     * @throws InvalidUser
     * @throws AuthenticationFailure
     *
     * @return WP_User
     */
    private function validate(Magic $magic)
    {
        if (! $magic->user || (! $user = new WP_User($magic->user)) || ! $user->exists()) {
            throw new InvalidUser('No user found or no longer exists.');
        }

        // We need to hash the salt to produce a key that won't exceed the maximum of 64 bytes.
        $key = sodium_crypto_generichash(wp_salt('auth'));
        $private_bin = sodium_crypto_generichash($this->signature($magic), $key);
        if (! $magic->private
            || ! hash_equals($magic->private, sodium_bin2base64($private_bin, SODIUM_BASE64_VARIANT_URLSAFE))
        ) {
            throw new AuthenticationFailure('Magic login authentication failed.');
        }

        return $user;
    }

    /**
     * Load the saved data for the current magic login.
     *
     * @throws BadMagic
     *
     * @return Magic
     */
    private function loadMagic()
    {
        $magic = json_decode(
            get_transient($this->magicKey()),
            true
        );

        if (is_null($magic)) {
            throw new BadMagic('The attempted magic login has expired or already been used.');
        }

        return new Magic($magic);
    }

    /**
     * Delete saved magic.
     */
    private function deleteMagic()
    {
        delete_transient($this->magicKey());
    }

    /**
     * Login the given user and redirect them to wp-admin.
     *
     * @param WP_User $user
     */
    private function loginUser(WP_User $user)
    {
        $this->deleteMagic();

        wp_set_auth_cookie($user->ID);

        /**
         * Fires after the user has successfully logged in via the WP-CLI Login Server.
         *
         * @param string  $user_login Username.
         * @param WP_User $user       WP_User object of the logged-in user.
         */
        do_action('wp_cli_login/login', $user->user_login, $user);

        /**
         * Fires after the user has successfully logged in.
         *
         * @param string  $user_login Username.
         * @param WP_User $user       WP_User object of the logged-in user.
         */
        do_action('wp_login', $user->user_login, $user);
    }

    /**
     * Redirect the user after logging in.
     *
     * Mostly copied from wp-login.php
     *
     * @param WP_User $user
     * @param string  $redirect_url
     */
    private function loginRedirect(WP_User $user, $redirect_url)
    {
        $redirect_to = $redirect_url ?: admin_url();

        /**
         * Filters the login redirect URL.
         *
         * @param string           $redirect_to           The redirect destination URL.
         * @param string           $requested_redirect_to The requested redirect destination URL passed as a parameter.
         * @param WP_User          $user                  WP_User object.
         */
        $redirect_to = apply_filters('login_redirect', $redirect_to, '', $user);

        /**
         * Filters the login redirect URL for WP-CLI Login Server requests.
         *
         * @param string           $redirect_to           The redirect destination URL.
         * @param string           $requested_redirect_to The requested redirect destination URL passed as a parameter.
         * @param WP_User          $user                  WP_User object.
         */
        $redirect_to = apply_filters('wp_cli_login/login_redirect', $redirect_to, '', $user);

        /**
         * Figure out where to redirect the user for the default wp-admin URL based on the user's capabilities.
         */
        if ((empty($redirect_to) || $redirect_to == 'wp-admin/' || $redirect_to == admin_url())) {
            // If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
            if (is_multisite() && ! get_active_blog_for_user($user->ID) && ! is_super_admin($user->ID)) {
                $redirect_to = user_admin_url();
            } elseif (is_multisite() && ! $user->has_cap('read')) {
                $redirect_to = get_dashboard_url($user->ID);
            } elseif (! $user->has_cap('edit_posts')) {
                $redirect_to = $user->has_cap('read') ? admin_url('profile.php') : home_url();
            }

            wp_redirect($redirect_to);
            exit;
        }

        /**
         * Redirect safely to the URL provided.
         */
        wp_safe_redirect($redirect_to);
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
        $common           = sprintf('Try again perhaps? or <a href="%s">Go Home &rarr;</a>', esc_url(home_url()));
        $message          = "<strong>$exceptionMessage</strong><p>$common</p>";

        wp_die($message, $exception, ['response' => 410]);
    }

    /**
     * Get the key for retrieving magic data for the current request.
     *
     * @return string
     */
    private function magicKey()
    {
        // We need to hash the salt to produce a key that won't exceed the maximum of 64 bytes.
        $key = sodium_crypto_generichash(wp_salt('auth'));
        $bin_hash = sodium_crypto_generichash($this->publicKey, $key);

        return self::OPTION . '/' . sodium_bin2base64($bin_hash, SODIUM_BASE64_VARIANT_URLSAFE);
    }

    /**
     * Build the signature to check against the private key for this request.
     *
     * @param Magic Login data.
     *
     * @return string
     */
    private function signature(Magic $magic)
    {
        return join('|', [
            $this->publicKey,
            $this->endpoint,
            parse_url($this->homeUrl(), PHP_URL_HOST),
            $magic->user,
            $magic->expires_at,
            $magic->redirect_url,
        ]);
    }

    /**
     * Get the home URL.
     *
     * @return string
     */
    private function homeUrl()
    {
        /* wp-cli server-command filters home & siteurl to work and saves the original in a global. */
        return isset($GLOBALS['_wp_cli_original_url'])
            ? $GLOBALS['_wp_cli_original_url']
            : home_url();
    }
}

/**
 * @property-read int $user
 * @property-read string $private
 * @property-read int $expires_at
 * @property-read string $redirect_url
 */
class Magic {
    protected $data;
    public function __construct(array $data) {
        $this->data = $data;
    }
    public function __get($property) {
        return isset($this->data[$property]) ? $this->data[$property] : null;
    }
}

class BadMagic extends Exception
{}

class AuthenticationFailure extends Exception
{}

class InvalidUser extends Exception
{}
