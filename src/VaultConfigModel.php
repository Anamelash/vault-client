<?php

namespace Vault;

class VaultConfigModel
{
    private string $baseUrl;

    private string $token;

    private ?string $namespace;

    public function __construct(string $baseUrl, string $token, ?string $namespace = null)
    {
        $this->baseUrl = $baseUrl;
        $this->token = $token;
        $this->namespace = $namespace;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }
}
