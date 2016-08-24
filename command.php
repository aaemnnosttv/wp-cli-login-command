<?php

namespace WP_CLI_Login;

use WP_CLI;
use WP_User;

if (! class_exists('WP_CLI')) {
    return;
}

WP_CLI::add_command('login', LoginCommand::class);

class LoginCommand
{
    /**
     * Option key for the current magic login endpoint.
     */
    const ENDPOINT_OPTION = 'wp_cli_login_endpoint';

    /**
     * Companion plugin file path, relative to plugins directory.
     */
    const PLUGIN_FILE = 'wp-cli-login-server/wp-cli-login-server.php';

    /**
     * Get a magic login URL for the given user.
     *
     * ## OPTIONS
     *
     * <user-locator>
     * : A string which identifies the user to be logged in as.
     * Possible values are: User ID, User Login, or User Email.
     *
     * [--url-only]
     * : Output the magic link URL only.
     *
     * [--launch]
     * : Launch the magic url immediately in your web browser.
     *
     * @param $_
     * @param $assoc
     *
     * @subcommand as
     */
    public function as_($_, $assoc)
    {
        list($user_locator) = $_;

        $this->requirePluginActivation();

        /**
        * WP_User does not accept a email in the constructor,
        * however an ID or user_login works just fine.
        * If the locator is a valid email address, use that,
        * otherwise, fallback to the constructor.
        */
        if (filter_var($user_locator, FILTER_VALIDATE_EMAIL)) {
            $user = get_user_by('email', $user_locator);
        }
        if (empty($user) || ! $user->exists()) {
            $user = new WP_User($user_locator);
        }

        if (! $user->exists()) {
            WP_CLI::error("No user found by: $user_locator");
        }

        $endpoint   = $this->endpoint();
        $public     = md5(uniqid()) . md5(uniqid());
        $private    = wp_hash_password("$public|$endpoint|{$user->ID}");
        $magic_link = add_query_arg(['magic_login' => urlencode($public)], home_url($endpoint));
        $magic      = [
            'user'    => $user->ID,
            'private' => $private,
            'time'    => time(),
        ];

        set_transient("wp-cli-login/$endpoint/$public", json_encode($magic), MINUTE_IN_SECONDS * 5);

        if (WP_CLI\Utils\get_flag_value($assoc, 'url-only')) {
            WP_CLI::line($magic_link);
            exit;
        }

        WP_CLI::success('Magic login link created!');
        WP_CLI::line($magic_link);
        WP_CLI::line('This link will self-destruct in 5 minutes, or as soon as it is used; whichever comes first.');

        if (WP_CLI\Utils\get_flag_value($assoc, 'launch')) {
            $this->launch($magic_link);
        }
    }

    /**
    * Invalidate any existing magic links.
    */
    public function invalidate()
    {
        update_option(static::ENDPOINT_OPTION, uniqid());

        WP_CLI::success("Magic links invalidated.");
    }

    /**
     * Deactivate the companion plugin.
     *
     * @alias disable
     */
    public function deactivate()
    {
        static::debug('Deactivating companion plugin.');

        WP_CLI::run_command(['plugin', 'deactivate', 'wp-cli-login-server']);
    }

    /**
     * Launch the magic link URL in the default browser.
     *
     * @param $url
     */
    protected function launch($url)
    {
        static::debug('Attempting to launch magic login with system browser...');

        $launch  = preg_match('/^WIN/', PHP_OS) ? 'start' : 'open';
        $process = WP_CLI\Process::create(sprintf('%s "%s"', $launch, $url));
        $result  = $process->run();

        if ($result->return_code > 0) {
            WP_CLI::error($result->stderr);
        }

        WP_CLI::success("Magic link launched!");
    }

    /**
     * Create the endpoint if it does not exist, and return the current value.
     *
     * @return string
     */
    protected function endpoint()
    {
        /**
         * Create the endpoint if it does not exist yet.
         */
        add_option(static::ENDPOINT_OPTION, uniqid());

        return get_option(static::ENDPOINT_OPTION);
    }

    /**
     * Install/update the server plugin.
     *
     * ## OPTIONS
     *
     * Overwrites existing installed plugin, if any.
     *
     * [--activate]
     * : Activate the plugin after installing.
     *
     * @todo Update this to use versioning.
     */
    public function install($_, $assoc)
    {
        static::debug('Installing/refreshing companion plugin.');

        wp_mkdir_p(WP_PLUGIN_DIR . '/' . dirname(static::PLUGIN_FILE));

        // update / overwrite / refresh installed plugin file
        copy(
            __DIR__ . '/plugin/wp-cli-login-server.php',
            WP_PLUGIN_DIR . '/' . static::PLUGIN_FILE
        );

        if (file_exists(WP_PLUGIN_DIR . '/' . static::PLUGIN_FILE)) {
            WP_CLI::success('Companion plugin installed.');
        }

        if (WP_CLI\Utils\get_flag_value($assoc, 'activate')) {
            $this->activate();
        }
    }

    /**
     * Activate the companion plugin.
     */
    public function activate()
    {
        static::debug('Activating companion plugin.');

        WP_CLI::run_command(['plugin', 'activate', 'wp-cli-login-server']);
    }

    /**
    * Check active status of the companion plugin, and stop execution if it is not,
    * with instructions as to how to proceed.
    */
    protected function requirePluginActivation()
    {
        if (! is_plugin_active(static::PLUGIN_FILE)) {
            WP_CLI::error('This command requires the companion plugin to be installed and active. Run `wp login install --activate` and try again.');
        }
    }

    /**
     * Log to debug.
     *
     * @param $message
     */
    public static function debug($message)
    {
        WP_CLI::debug("[login] $message");
    }
}
