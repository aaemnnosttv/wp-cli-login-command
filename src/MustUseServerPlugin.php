<?php

namespace WP_CLI_Login;

class MustUseServerPlugin extends ServerPlugin
{
    const PLUGIN_FILE = 'wp-cli-login-server.php';

    public static function isActive()
    {
        return true;
    }
}
