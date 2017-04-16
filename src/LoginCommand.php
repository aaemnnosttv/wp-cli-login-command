<?php

namespace WP_CLI_Login;

use WP_CLI;
use WP_CLI\Process;
use WP_User;

/**
 * Manage magic passwordless log-in.
 */
class LoginCommand
{
    use Randomness;

    /**
     * Option key for the persisted-data.
     */
    const OPTION = 'wp_cli_login';

    /**
     * Required version constraint of the wp-cli-login-server companion plugin.
     */
    const REQUIRED_PLUGIN_VERSION = '^1.2';

    /**
     * Package instance
     * @var Package
     */
    private static $package;

    /**
     * Create a magic log-in link for the given user.
     *
     * ## OPTIONS
     *
     * <user-locator>
     * : A string which identifies the user to be logged in as.
     * Possible values are: User ID, User Login, or User Email.
     *
     * [--expires=<seconds-from-now>]
     * : The number of seconds from now until the magic link expires. Default: 15 minutes
     * ---
     * default: 900
     * ---
     *
     * [--url-only]
     * : Output the magic link URL only.
     *
     * [--launch]
     * : Launch the magic url immediately in your web browser.
     *
     * @param array $_
     * @param array $assoc
     *
     * @alias as
     */
    public function create($_, $assoc)
    {
        $this->ensurePluginRequirementsMet();

        list($user_locator) = $_;

        $user      = $this->lookupUser($user_locator);
        $expires   = human_time_diff(time(), time() + absint($assoc['expires']));
        $magic_url = $this->makeMagicUrl($user, $assoc['expires']);

        if (WP_CLI\Utils\get_flag_value($assoc, 'url-only')) {
            WP_CLI::line($magic_url);
            exit;
        }

        WP_CLI::success('Magic login link created!');
        WP_CLI::line(str_repeat('-', strlen($magic_url)));
        WP_CLI::line($magic_url);
        WP_CLI::line(str_repeat('-', strlen($magic_url)));
        WP_CLI::line("This link will self-destruct in $expires, or as soon as it is used; whichever comes first.");

        if (WP_CLI\Utils\get_flag_value($assoc, 'launch')) {
            $this->launch($magic_url);
        }
    }

    /**
     * Email a magic log-in link to the given user.
     *
     * ## OPTIONS
     *
     * <user-locator>
     * : A string which identifies the user to be logged in as.
     * Possible values are: User ID, User Login, or User Email.
     *
     * [--expires=<seconds-from-now>]
     * : The number of seconds from now until the magic link expires. Default: 15 minutes
     * ---
     * default: 900
     * ---
     *
     * [--template=<path-to-template-file>]
     * : The path to a file to use for a custom email template.
     * Uses Mustache templating for dynamic html.
     * ---
     * default:
     * ---
     *
     * @param array $_
     * @param array $assoc
     */
    public function email($_, $assoc)
    {
        $this->ensurePluginRequirementsMet();

        list($user_locator) = $_;

        $user          = $this->lookupUser($user_locator);
        $expires       = human_time_diff(time(), time() + absint($assoc['expires']));
        $magic_url     = $this->makeMagicUrl($user, $assoc['expires']);
        $domain        = $this->domain();
        $html_rendered = $this->renderEmailTemplate(
            compact('magic_url','domain','expires'),
            $assoc['template']
        );

        if (! $this->sendEmail($user, $domain, $html_rendered)) {
            WP_CLI::error('Email failed to send.');
        }

        WP_CLI::success('Email sent.');
    }

    /**
     * Render the given email template, for the given user.
     *
     * @param      $template_data
     * @param null $template_file
     *
     * @return string
     */
    private function renderEmailTemplate($template_data, $template_file = null)
    {
        return \WP_CLI\Utils\mustache_render(
            $template_file ?: $this->packagePath('template/email-default.mustache'),
            $template_data
        );
    }

    /**
     * Invalidate any existing magic links.
     */
    public function invalidate()
    {
        $this->resetOption();

        WP_CLI::success('Magic links invalidated.');
    }

    /**
     * Launch the magic link URL in the default browser.
     *
     * @param $url
     */
    private function launch($url)
    {
        static::debug('Attempting to launch magic login with system browser...');

        if ($cmd = getenv('WP_CLI_LOGIN_LAUNCH_WITH')) {
            // system/environment override
        }
        elseif (preg_match('/^darwin/i', PHP_OS)) {
            $cmd = 'open';
        } elseif (preg_match('/^win/i', PHP_OS)) {
            $cmd = 'start';
        } elseif (preg_match('/^linux/i', PHP_OS)) {
            $cmd = 'xdg-open';
        } else {
            WP_CLI::error('Your operating system does not seem to support launching from the command line.  Please open an issue (https://github.com/aaemnnosttv/wp-cli-login-command/issues) and be sure to include the output from this command: `php -r \'echo PHP_OS;\'`');
            exit; // make IDE happy.
        }

        static::debug("Launching browser with: $cmd");

        $process = Process::create("$cmd '$url'");
        $result  = $process->run();

        if ($result->return_code > 0) {
            WP_CLI::error($result->stderr);
        }

        self::debug($result->stdout);

        WP_CLI::success("Magic link launched!");
    }

    /**
     * Create the endpoint if it does not exist, and return the current value.
     *
     * @return string
     */
    private function endpoint()
    {
        $saved   = json_decode(get_option(static::OPTION));
        $version = isset($saved->version) ? $saved->version : false;

        if (! $saved) {
            static::debug('Creating endpoint');
            $saved = $this->resetOption();
        } elseif (! $this->installedPlugin()->versionSatisfies($version) && $this->promptForReset($version)) {
            static::debug("Updating endpoint for version $version");
            $saved = $this->resetOption();
        }

        return $saved->endpoint;
    }

    /**
     * Prompt the user about resetting log-ins.
     *
     * @param null $version
     *
     * @return string
     */
    private function promptForReset($version = null)
    {
        if ($version) {
            WP_CLI::line("Version $version requires an update for compatibility with the current version of the login command.");
            WP_CLI::line('Your site will not be able to respond to newly created magic log-in links until updating.');
        }
        WP_CLI::warning('This will invalidate any existing magic links.');

        return $this->confirm('Are you sure?');
    }

    /**
     * Prompt the user for a yes/no answer.
     *
     * @param $question
     *
     * @return bool
     */
    private function confirm($question)
    {
        fwrite(STDOUT, $question . ' [Y/n] ');
        $response = trim(fgets(STDIN));

        return ('y' == strtolower($response));
    }

    /**
     * Reset the saved option with fresh data.
     *
     * @return \stdClass
     */
    private function resetOption()
    {
        static::debug('Resetting option...');

        $option = [
            'endpoint' => $this->randomness(4),
            'version'  => static::REQUIRED_PLUGIN_VERSION,
        ];

        update_option(static::OPTION, json_encode($option));

        return (object) $option;
    }

    /**
     * Install/update the companion server plugin.
     *
     * ## OPTIONS
     *
     * [--activate]
     * : Activate the plugin after installing.
     *
     * [--yes]
     * : Suppress confirmation to overwrite the installed plugin if it exists.
     *
     * @param array $_
     * @param array $assoc
     */
    public function install($_, $assoc)
    {
        static::debug('Installing plugin.');

        $installed = $this->installedPlugin();
        $suppress_prompt = \WP_CLI\Utils\get_flag_value($assoc, 'yes');

        if ($installed->exists() && ! $suppress_prompt && ! $this->confirmOverwrite($installed)) {
            WP_CLI::line('Update aborted by user.');
            exit;
        }

        wp_mkdir_p(dirname($installed->fullPath()));

        // update / overwrite / refresh installed plugin file
        copy(
            $this->bundledPlugin()->fullPath(),
            $installed->fullPath()
        );

        if (! $installed->exists()) {
            WP_CLI::error('Plugin install failed.');
        }

        WP_CLI::success('Companion plugin installed.');

        if (WP_CLI\Utils\get_flag_value($assoc, 'activate')) {
            $this->toggle(['on']);
        }
    }

    /**
     * Confirm the overwrite of the given server plugin with the user.
     *
     * @param ServerPlugin $plugin
     *
     * @return bool
     */
    private function confirmOverwrite(ServerPlugin $plugin)
    {
        return $plugin->isComposerInstalled()
            ? $this->confirm('This plugin appears to be installed by Composer. Overwrite anyway?')
            : $this->confirm('Overwrite existing plugin?');
    }

    /**
     * Toggle the active state of the companion server plugin.
     *
     * [<on|off>]
     * : Toggle the companion plugin on or off.
     * Default: toggles active status.
     * ---
     * options:
     *   - on
     *   - off
     * ---
     *
     * @param array $_
     */
    public function toggle($_)
    {
        if (($setState = reset($_)) && ! in_array($setState, ['on','off'])) {
            WP_CLI::error('Invalid toggle value. Possible options are "on" and "off".');
        }

        if (! $setState) {
            $setState = ServerPlugin::isActive() ? 'off' : 'on';
        }

        self::debug("Toggling companion plugin: $setState");

        $command = $setState == 'on' ? 'activate' : 'deactivate';

        WP_CLI::run_command(['plugin', $command, 'wp-cli-login-server']);
    }

    /**
     * Create a magic login URL
     *
     * @param  WP_User $user User to create login URL for
     *
     * @param          $expires
     *
     * @return string URL
     */
    private function makeMagicUrl(WP_User $user, $expires)
    {
        static::debug("Generating a new magic login for User $user->ID expiring in {$expires} seconds.");

        $endpoint = $this->endpoint();
        $magic    = new MagicUrl($user, $this->domain());

        $this->persistMagicUrl($magic, $endpoint, $expires);

        return $this->homeUrl($endpoint . '/' . $magic->getKey());
    }

    /**
     * Store the Magic Url to be used later.
     *
     * @param $magic
     * @param $endpoint
     * @param $expires
     */
    private function persistMagicUrl(MagicUrl $magic, $endpoint, $expires)
    {
        set_transient(
            self::OPTION . '/' . $magic->getKey(),
            json_encode($magic->generate($endpoint)),
            ceil($expires)
        );
    }

    /**
     * Get the target user by the given locator.
     *
     * @param  mixed $locator User login, ID, or email address
     *
     * @return WP_User
     */
    private function lookupUser($locator)
    {
        static::debug("Looking up user by '$locator'");

        $fetcher = new WP_CLI\Fetchers\User();
        $user    = $fetcher->get($locator);

        if (! $user instanceof WP_User) {
            WP_CLI::error("No user found by: $locator");
        }

        return $user;
    }

    /**
     * Check that the login server plugin meets all necessary requirements.
     * If any criteria is not met, abort with instructions as to how to proceed.
     */
    private function ensurePluginRequirementsMet()
    {
        $plugin = $this->installedPlugin();

        if (! ServerPlugin::isActive() || ! $plugin->exists()) {
            WP_CLI::error('This command requires the companion plugin to be installed and active. Run `wp login install --activate` and try again.');
        }

        if (! $plugin->versionSatisfies(self::REQUIRED_PLUGIN_VERSION)) {
            WP_CLI::error(
                sprintf('The current version of the login command requires version %s of %s, but version %s is installed. Run `wp login install` to install it.',
                    static::REQUIRED_PLUGIN_VERSION,
                    $plugin->name(),
                    $plugin->version()
                )
            );
        }
    }

    /**
     * Get a ServerPlugin instance for the installed plugin.
     *
     * @return ServerPlugin
     */
    private function installedPlugin()
    {
        static $plugin;

        if (! $plugin) {
            $plugin = ServerPlugin::installed();
        }

        return $plugin;
    }

    /**
     * Get a ServerPlugin instance for the bundled plugin.
     *
     * @return ServerPlugin
     */
    private function bundledPlugin()
    {
        static $plugin;

        if (! $plugin) {
            $plugin = new ServerPlugin($this->packagePath('plugin/wp-cli-login-server.php'));
        }

        return $plugin;
    }

    /**
     * Get the domain of the current site.
     *
     * @return mixed
     */
    private function domain()
    {
        return parse_url(home_url(), PHP_URL_HOST);
    }

    /**
     * Get the home URL.
     *
     * @param string $path
     *
     * @return string
     */
    private function homeUrl($path = '')
    {
        $url = home_url($path);

        /**
         * If the global --url is passed it will set the HTTP_HOST.
         * Here we replace the hostname in the default home URL with the one set by the command.
         * This preserves compatibility with sites installed as a subdirectory.
         */
        if (! empty($_SERVER['HTTP_HOST'])) {
            return preg_replace('#//[^/]+#', '//'. $_SERVER['HTTP_HOST'], $url, 1);
        }

        return $url;
    }

    /**
     * Get an absolute file path, from the given path relative to the package root.
     *
     * @param $relative
     *
     * @return string
     */
    private function packagePath($relative)
    {
        return self::$package->fullPath($relative);
    }

    /**
     * Set the package instance.
     *
     * @param Package $package
     */
    public static function setPackage(Package $package)
    {
        self::$package = $package;
    }

    /**
     * Log to debug.
     *
     * @param $message
     */
    private static function debug($message)
    {
        WP_CLI::debug($message, 'aaemnnosttv/wp-cli-login-command');
    }

    /**
     * Send an email to the given user.
     *
     * @param $user
     * @param $domain
     * @param $html_rendered
     *
     * @return bool|void
     */
    private function sendEmail($user, $domain, $html_rendered)
    {
        static::debug("Sending email to $user->user_email");

        return wp_mail($user->user_email,
            "Magic log-in link for $domain",
            $html_rendered,
            [
                'Content-Type: text/html',
                "From: WordPress <no-reply@{$domain}>",
            ]
        );
    }
}
