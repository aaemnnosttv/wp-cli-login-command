<?php

namespace WP_CLI_Login;

if (! class_exists('WP_CLI')) {
    return;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

LoginCommand::setPackage(new Package(__DIR__));

\WP_CLI::add_command('login', LoginCommand::class);

