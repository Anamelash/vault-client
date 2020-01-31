<?php

namespace Vault;

interface ParameterProviderInterface
{
    /**
     * @return mixed
     */
    public function get(string $key);

    /**
     * @param mixed $value
     */
    public function set(string $key, $value): void;
}
