<?php

use PHPUnit\Framework\TestCase;
use WP_CLI_Login\WP_CLI_Login_Server;

class WP_CLI_Login_ServerTest extends TestCase
{
    /** @test */
    function parses_endpoint_and_key_from_uri()
    {
        list($endpoint, $public) = WP_CLI_Login_Server::parseUri('/end/key');

        $this->assertSame('end', $endpoint);
        $this->assertSame('key', $public);
    }

    /** @test */
    function parses_endpoint_and_key_from_uri_with_trailing_slash()
    {
        list($endpoint, $public) = WP_CLI_Login_Server::parseUri('/end/key/');

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

    /** @test */
    function parses_endpoint_and_key_from_uri_with_trailing_slash_for_subdirectory_site()
    {
        list($endpoint, $public) = WP_CLI_Login_Server::parseUri('/abc/end/key/');

        $this->assertSame('end', $endpoint);
        $this->assertSame('key', $public);
    }

    /** @test */
    function parses_an_endpoint_with_a_single_segment() {
        list($endpoint, $public) = WP_CLI_Login_Server::parseUri('/abc');

        $this->assertSame('', $endpoint);
        $this->assertSame('', $public);
    }

    /** @test */
    function parses_an_endpoint_with_no_segment() {
        list($endpoint, $public) = WP_CLI_Login_Server::parseUri('/');

        $this->assertSame('', $endpoint);
        $this->assertSame('', $public);
    }
}
