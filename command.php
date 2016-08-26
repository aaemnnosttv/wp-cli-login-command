<?php

namespace WP_CLI_Login;

if (! class_exists('WP_CLI')) {
    return;
}

LoginCommand::setRoot(__DIR__);

\WP_CLI::add_command('login', LoginCommand::class);

