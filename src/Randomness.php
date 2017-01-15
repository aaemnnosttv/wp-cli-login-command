<?php

namespace WP_CLI_Login;

trait Randomness
{
    /**
     * @param $min
     * @param $max
     *
     * @return string
     */
    private function randomness($min, $max = null)
    {
        $min = absint($min);
        $max = absint($max ? $max : $min);
        return bin2hex(random_bytes(random_int($min, $max)));
    }
}
