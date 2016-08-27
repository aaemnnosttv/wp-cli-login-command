<?php

namespace WP_CLI_Login;

use Composer\Semver\Semver;

class ServerPlugin
{
    /**
     * Plugin file path, relative to plugins directory.
     */
    const PLUGIN_FILE = 'wp-cli-login-server/wp-cli-login-server.php';

    /**
     * Absolute path to primary plugin file.
     * @var
     */
    private $file;

    /**
     * ServerPlugin constructor.
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Check if the plugin is currently active.
     *
     * @return bool
     */
    public static function isActive()
    {
        return is_plugin_active(self::PLUGIN_FILE);
    }

    /**
     * Get a new instance for the installed plugin.
     *
     * @return static
     */
    public static function installed()
    {
        return new static(WP_PLUGIN_DIR . '/' . static::PLUGIN_FILE);
    }

    /**
     * Check if the main plugin file exists.
     *
     * @return bool
     */
    public function exists()
    {
        return file_exists($this->file);
    }

    /**
     * Get the absolute path to the main plugin file.
     *
     * @return mixed
     */
    public function fullPath()
    {
        return $this->file;
    }

    /**
     * Get the plugin name, as defined in the header.
     *
     * @return mixed
     */
    public function name()
    {
        return $this->data()['Name'];
    }

    /**
     * Get the plugin version, as defined in the header.
     *
     * @return mixed
     */
    public function version()
    {
        return $this->data()['Version'];
    }

    /**
     * Check if the plugin's version satisfies the given constraints.
     *
     * @param string $constraints  Composer Semver-style constraints
     *
     * @return bool
     */
    public function versionSatisfies($constraints)
    {
        return Semver::satisfies($this->version(), $constraints);
    }

    /**
     * Get the plugin file header data.
     *
     * @return array
     */
    public function data()
    {
        static $loaded;

        if (! $loaded) {
            $loaded = get_plugin_data($this->file);
        }

        return $loaded;
    }
}
