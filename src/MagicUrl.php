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
     * @var string URL to redirect to upon successful login.
     */
    private $redirect_url;

    /**
     * MagicUrl constructor.
     *
     * @param WP_User $user
     * @param string $domain
     * @param string $redirect_url
     */
    public function __construct(WP_User $user, $domain, $redirect_url = null)
    {
        $this->user = $user;
        $this->domain = $domain;
        $this->key = $this->newPublicKey();
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
        return [
            'user'         => $this->user->ID,
            'private'      => wp_hash($this->signature($endpoint)),
            'redirect_url' => $this->redirect_url,
            'time'         => time(),
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
