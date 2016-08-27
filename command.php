<?php

namespace WP_CLI_Login;

if (! class_exists('WP_CLI')) {
    return;
}

LoginCommand::setPackage(new Package(__DIR__));

\WP_CLI::add_command('login', LoginCommand::class);

