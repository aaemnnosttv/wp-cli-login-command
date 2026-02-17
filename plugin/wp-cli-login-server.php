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

/**
 * Handle the second leg of the magic login on wp-login.php.
 *
 * CDN/proxy layers (e.g. Cloudflare with "Cache Everything") strip Set-Cookie
 * headers from responses on arbitrary URLs. wp-login.php is universally excluded
 * from CDN caching, so setting cookies here guarantees they reach the browser.
 */
function handle_login_handoff()
{
    if (empty($_GET['wp_cli_login_token'])) {
        return;
    }

    $token = sanitize_text_field($_GET['wp_cli_login_token']);
    $data  = get_transient('wp_cli_login/handoff/' . $token);
    delete_transient('wp_cli_login/handoff/' . $token);

    if (! $data || ! ($user = new \WP_User($data['user_id'])) || ! $user->exists()) {
        wp_die('The magic login handoff has expired or is invalid.', '', ['response' => 410]);
    }

    wp_set_auth_cookie($user->ID);
    do_action('wp_cli_login/login', $user->user_login, $user);
    do_action('wp_login', $user->user_login, $user);

    $redirect_to = $data['redirect_url'] ?: admin_url();
    $redirect_to = apply_filters('login_redirect', $redirect_to, '', $user);
    $redirect_to = apply_filters('wp_cli_login/login_redirect', $redirect_to, '', $user);

    if ((empty($redirect_to) || $redirect_to == 'wp-admin/' || $redirect_to == admin_url())) {
        if (is_multisite() && ! get_active_blog_for_user($user->ID) && ! is_super_admin($user->ID)) {
            $redirect_to = user_admin_url();
        } elseif (is_multisite() && ! $user->has_cap('read')) {
            $redirect_to = get_dashboard_url($user->ID);
        } elseif (! $user->has_cap('edit_posts')) {
            $redirect_to = $user->has_cap('read') ? admin_url('profile.php') : home_url();
        }
    }

    wp_safe_redirect($redirect_to);
    exit;
}
add_action('login_init', __NAMESPACE__ . '\\handle_login_handoff');



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
     *
     * Rather than setting auth cookies directly (which CDN/proxy layers like
     * Cloudflare may strip), hand off to wp-login.php via a short-lived token.
     */
    public function run()
    {
        try {
            $magic = $this->loadMagic();
            $user  = $this->validate($magic);
            $this->deleteMagic();

            $token = bin2hex(random_bytes(16));
            set_transient('wp_cli_login/handoff/' . $token, [
                'user_id'      => $user->ID,
                'redirect_url' => $magic->redirect_url,
            ], 30);

            wp_redirect(add_query_arg('wp_cli_login_token', $token, wp_login_url()));
            exit;
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
