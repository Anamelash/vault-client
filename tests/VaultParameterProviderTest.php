<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Vault\Exception\ParameterStorageException;
use Vault\Exception\VaultClientException;
use Vault\Exception\VaultClientKeyNotFoundException;
use Vault\VaultClient;
use Vault\VaultParameterProvider;

class VaultParameterProviderTest extends TestCase
{
    /**
     * @var MockObject|VaultClient
     */
    protected $vaultClient;

    protected VaultParameterProvider $vaultParameterProvider;

    public function setUp(): void
    {
        parent::setUp();

        $this->vaultClient = $this->createMock(VaultClient::class);

        $this->vaultParameterProvider = new VaultParameterProvider($this->vaultClient);
    }

    public function testGet(): void
    {
        $this->vaultClient->expects($this->once())->method('executeGetValueRequest')->with('foo')->willReturn('bar');

        $result = $this->vaultParameterProvider->get('foo');

        self::assertSame('bar', $result);
    }

    public function testGetNotFound(): void
    {
        $this->vaultClient->expects($this->once())->method('executeGetValueRequest')->with('foo')->willThrowException(new VaultClientKeyNotFoundException());

        $this->expectException(ParameterStorageException::class);

        $this->vaultParameterProvider->get('foo');
    }

    public function testSet(): void
    {
        $this->vaultClient->expects($this->once())->method('executeSetValueRequest')->with('foo', 'bar');

        $this->vaultParameterProvider->set('foo', 'bar');
    }

    public function testSetError(): void
    {
        $this->vaultClient->expects($this->once())->method('executeSetValueRequest')->with('foo', 'bar')->willThrowException(new VaultClientException());

        $this->expectException(ParameterStorageException::class);

        $this->vaultParameterProvider->set('foo', 'bar');
    }
}
