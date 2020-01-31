<?php

namespace Vault;

use Vault\Exception\ParameterStorageException;
use Vault\Exception\VaultClientException;

class VaultParameterProvider implements ParameterProviderInterface
{
    protected VaultClient $vaultClient;

    public function __construct(VaultClient $vaultClient)
    {
        $this->vaultClient = $vaultClient;
    }

    /**
     * @return mixed
     */
    public function get(string $key)
    {
        try {
            $result = $this->vaultClient->executeGetValueRequest($key);
        } catch (VaultClientException $exception) {
            throw new ParameterStorageException(sprintf('Could not get value for key "%s"', $key), 1, $exception);
        }

        return $result;
    }

    public function set(string $key, $value): void
    {
        try {
            $this->vaultClient->executeSetValueRequest($key, $value);
        } catch (VaultClientException $exception) {
            throw new ParameterStorageException(sprintf('Could not set value for key "%s"', $key), 1, $exception);
        }
    }
}
