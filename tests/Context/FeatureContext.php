<?php

namespace WP_CLI_Login\Tests\Context;

use function WP_CLI\Utils\esc_cmd;
use function WP_CLI\Utils\get_php_binary;

class FeatureContext extends \WP_CLI\Tests\Context\FeatureContext
{
    /**
     * @Given /a user ([a-z]+) ([\w\d\.@-]+)/
     */
    public function aUser($username, $email)
    {
        $this->proc(
            "wp user create '$username' '$email'",
            ['path' => $this->variables['WP_DIR']]
        )->run_check();
    }

    /**
     * @Given /the login plugin is installed( and active)?$/
     */
    public function theLoginPluginIsInstalled($activate = '')
    {
        $activate = $activate ? '--activate' : '';
        $this->proc(
            "wp login install --yes $activate",
            ['path' => $this->variables['WP_DIR']]
        )->run_check();
    }

    /**
     * @Given the login plugin is installed as an mu plugin
     */
    public function theLoginPluginIsInstalledAsAnMuPlugin()
    {
        $this->proc(
            'wp login install --mu',
            ['path' => $this->variables['WP_DIR']]
        )->run_check();
    }

    public function install_wp($subdir = '') {
        $this->variables['SUBDIR'] = $this->replace_variables($subdir);

        parent::install_wp($subdir);

        // Set a new variable for the WP path as RUN_DIR does not include a subdir.
        // Set after install where RUN_DIR is set.
        $this->variables['WP_DIR'] = rtrim(
            $this->variables['RUN_DIR'] . '/' . $this->variables['SUBDIR'],
            '/'
        );
    }

    public function start_php_server( $subdir = '' ) {
        $dir = $this->variables['RUN_DIR'] . '/';
        if ( $subdir ) {
            $dir .= trim( $subdir, '/' ) . '/';
        }
        $cmd = esc_cmd(
            '%s -S %s -t %s -c %s %s',
            get_php_binary(),
            'localhost:8080',
            $dir,
            get_cfg_var( 'cfg_file_path' ),
            $this->variables['PROJECT_DIR'] . '/vendor/wp-cli/server-command/router.php'
        );
        $this->background_proc( $cmd );
    }

    public function proc($command, $assoc_args = [], $path = '')
    {
        // Hack around changing the default site URL to match
        // the one used by the "a PHP built-in web server"
        if ('wp core install' === $command && isset($assoc_args['url'])) {
            if ($this->variables['SUBDIR']) {
                $assoc_args["url"] = "http://localhost:8080/{$this->variables['SUBDIR']}";
            } else {
                $assoc_args['url'] = 'http://localhost:8080';
            }
        }
        return parent::proc($command, $assoc_args, $path);
    }
}
