<?php

namespace WP_CLI_Login;

class Package
{
    /**
     * Absolute path to package root directory.
     * @var string
     */
    private $root;

    /**
     * Package constructor.
     *
     * @param $root
     */
    public function __construct($root)
    {
        $this->root = $root;
    }

    /**
     * Get an absolute file path, from the given path relative to the package root.
     *
     * @param $relative
     *
     * @return string
     */
    public function fullPath($relative)
    {
        return $this->root . '/' . $relative;
    }
}
