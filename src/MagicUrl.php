<?php

namespace WP_CLI_Login;

use WP_User;

class MagicUrl
{
    use Randomness;

    /**
     * @var string Public key.
     */
    private $key;

    /**
     * @var WP_User User the magic url is for.
     */
    private $user;

    /**
     * @var string Domain name this url is bound to.
     */
    private $domain;

    /**
     * @var int Timestamp that the magic url is valid until.
     */
    private $expires_at;

    /**
     * @var string URL to redirect to upon successful login.
     */
    private $redirect_url;

    /**
     * MagicUrl constructor.
     *
     * @param WP_User $user
     * @param string  $domain
     * @param int $expires_at
     * @param string  $redirect_url
     */
    public function __construct(WP_User $user, $domain, $expires_at, $redirect_url = null)
    {
        $this->user = $user;
        $this->domain = $domain;
        $this->key = $this->newPublicKey();
        $this->expires_at = ceil($expires_at);
        $this->redirect_url = $redirect_url;
    }

    /**
     * Get the public key.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Generate a new magic URL for the given endpoint.
     *
     * @param string $endpoint
     *
     * @return array
     */
    public function generate($endpoint)
    {
        // We need to hash the salt to produce a key that won't exceed the maximum of 64 bytes.
        $key = sodium_crypto_generichash(wp_salt('auth'));
        $private_bin = sodium_crypto_generichash($this->signature($endpoint), $key);

        return [
            'user'         => $this->user->ID,
            'private'      => sodium_bin2base64($private_bin, SODIUM_BASE64_VARIANT_URLSAFE),
            'redirect_url' => $this->redirect_url,
            'expires_at'   => $this->expires_at,
        ];
    }

    /**
     * Build the signature for the given endpoint.
     *
     * @param $endpoint
     *
     * @return string
     */
    private function signature($endpoint)
    {
        return join('|', [
            $this->key,
            $endpoint,
            $this->domain,
            $this->user->ID,
            $this->expires_at,
            $this->redirect_url,
        ]);
    }

    /**
     * Generate a new cryptographically sound public key.
     *
     * @return string
     */
    private function newPublicKey()
    {
        return implode('-', [
            $this->randomness(3, 5),
            $this->randomness(3, 5),
            $this->randomness(3, 5),
        ]);
    }
}
