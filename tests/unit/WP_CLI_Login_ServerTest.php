<?php

use WP_CLI_Login\WP_CLI_Login_Server;

if (! class_exists('PHPUnit_Framework_TestCase')) {
    class_alias('PHPUnit\Framework\TestCase', 'PHPUnit_Framework_TestCase');
}

class WP_CLI_Login_ServerTest extends PHPUnit_Framework_TestCase
{
    /** @test */
    function parses_endpoint_and_key_from_uri()
    {
        list($endpoint, $public) = WP_CLI_Login_Server::parseUri('/end/key');

        $this->assertSame('end', $endpoint);
        $this->assertSame('key', $public);
    }

    /** @test */
    function parses_endpoint_and_key_from_uri_for_subdirectory_site()
    {
        list($endpoint, $public) = WP_CLI_Login_Server::parseUri('/abc/end/key');

        $this->assertSame('end', $endpoint);
        $this->assertSame('key', $public);
    }

}
