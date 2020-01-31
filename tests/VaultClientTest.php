<?php

declare(strict_types=1);

namespace Tests;

use function fopen;
use Guzzle\Http\ClientInterface;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Exception\RequestException;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Vault\Exception\VaultClientException;
use Vault\Exception\VaultClientKeyNotFoundException;
use Vault\Exception\VaultClientResponseParsingException;
use Vault\VaultClient;
use Vault\VaultConfigModel;

class VaultClientTest extends TestCase
{
    /**
     * @var MockObject|ClientInterface
     */
    protected $client;

    /**
     * @var MockObject|VaultConfigModel
     */
    protected $vaultConfigModel;

    /**
     * @var MockObject|LoggerInterface
     */
    protected $logger;

    /**
     * @var VaultClient
     */
    protected VaultClient $vaultClient;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(ClientInterface::class);
        $this->vaultConfigModel = $this->createMock(VaultConfigModel::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->vaultClient = new VaultClient($this->client, $this->vaultConfigModel, $this->logger);

        $this->vaultConfigModel->expects($this->once())->method('getBaseUrl')->willReturn('foo');
        $this->vaultConfigModel->expects($this->once())->method('getToken')->willReturn('bar');
        $this->vaultConfigModel->expects($this->never())->method('getNamespace');
    }

    public function testExecuteGetValueRequest(): void
    {
        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('getLocation')->willReturn('foo/some_key');
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(200);
        $response->expects($this->once())->method('getMessage')->willReturn('OK');
        $response->expects($this->at(3))->method('getBody')->with(true)->willReturn('response_body');
        $response->expects($this->at(5))->method('getBody')->willReturn(EntityBody::factory($this->getResponseBodyResource('get_value')));

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getUrl')->willReturn('foo/some_key');
        $request->expects($this->once())->method('send')->willReturn($response);

        $expectedHeaders = [
            'X-Vault-Token' => 'bar',
            'Content-Type' => 'application/json',
        ];
        $this->client->expects($this->once())->method('createRequest')->with('GET', 'foo/some_key', $expectedHeaders, null)->willReturn($request);

        $this->logger->expects($this->at(0))->method('info')->with('VAULT-REQUEST: ["foo\/some_key",""]');
        $this->logger->expects($this->at(1))->method('info')->with('VAULT-RESPONSE: ["foo\/some_key",200,"OK","response_body"]');

        $result = $this->vaultClient->executeGetValueRequest('some_key');

        self::assertSame('baz', $result);
    }

    public function testExecuteGetValueRequestNotFound(): void
    {
        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('getLocation')->willReturn('foo/some_key');
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(404);
        $response->expects($this->once())->method('getMessage')->willReturn('ERROR');
        $response->expects($this->at(3))->method('getBody')->with(true)->willReturn('response_body');

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getUrl')->willReturn('foo/some_key');
        $request->expects($this->once())->method('send')->willReturn($response);

        $expectedHeaders = [
            'X-Vault-Token' => 'bar',
            'Content-Type' => 'application/json',
        ];
        $this->client->expects($this->once())->method('createRequest')->with('GET', 'foo/some_key', $expectedHeaders, null)->willReturn($request);

        $this->logger->expects($this->at(0))->method('info')->with('VAULT-REQUEST: ["foo\/some_key",""]');
        $this->logger->expects($this->at(1))->method('info')->with('VAULT-RESPONSE: ["foo\/some_key",404,"ERROR","response_body"]');

        $this->expectException(VaultClientKeyNotFoundException::class);
        $this->expectExceptionMessage('Value with key "some_key" is not found');

        $this->vaultClient->executeGetValueRequest('some_key');
    }

    public function testExecuteGetValueRequestParseError(): void
    {
        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('getLocation')->willReturn('foo/some_key');
        $response->expects($this->exactly(2))->method('getStatusCode')->willReturn(200);
        $response->expects($this->once())->method('getMessage')->willReturn('OK');
        $response->expects($this->at(3))->method('getBody')->with(true)->willReturn('response_body');
        $response->expects($this->at(5))->method('getBody')->willReturn(EntityBody::factory($this->getResponseBodyResource('not_found')));

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getUrl')->willReturn('foo/some_key');
        $request->expects($this->once())->method('send')->willReturn($response);

        $expectedHeaders = [
            'X-Vault-Token' => 'bar',
            'Content-Type' => 'application/json',
        ];
        $this->client->expects($this->once())->method('createRequest')->with('GET', 'foo/some_key', $expectedHeaders, null)->willReturn($request);

        $this->logger->expects($this->at(0))->method('info')->with('VAULT-REQUEST: ["foo\/some_key",""]');
        $this->logger->expects($this->at(1))->method('info')->with('VAULT-RESPONSE: ["foo\/some_key",200,"OK","response_body"]');

        $this->expectException(VaultClientResponseParsingException::class);
        $this->expectExceptionMessage('Could not parse response');

        $this->vaultClient->executeGetValueRequest('some_key');
    }

    public function testExecuteSetValueRequest(): void
    {
        $response = $this->createMock(Response::class);
        $response->expects($this->once())->method('getLocation')->willReturn('foo/some_key');
        $response->expects($this->once())->method('getStatusCode')->willReturn(200);
        $response->expects($this->once())->method('getMessage')->willReturn('OK');
        $response->expects($this->at(3))->method('getBody')->with(true)->willReturn('response_body');

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getUrl')->willReturn('foo/some_key');
        $request->expects($this->once())->method('send')->willReturn($response);

        $expectedHeaders = [
            'X-Vault-Token' => 'bar',
            'Content-Type' => 'application/json',
        ];
        $this->client->expects($this->once())->method('createRequest')->with('POST', 'foo/some_key', $expectedHeaders, '{"data":{"value":"some_value"}}')->willReturn($request);

        $this->logger->expects($this->at(0))->method('info')->with('VAULT-REQUEST: ["foo\/some_key",""]');
        $this->logger->expects($this->at(1))->method('info')->with('VAULT-RESPONSE: ["foo\/some_key",200,"OK","response_body"]');

        $this->vaultClient->executeSetValueRequest('some_key', 'some_value');
    }

    public function testExecuteSetValueRequestException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getUrl')->willReturn('foo/some_key');
        $request->expects($this->once())->method('send')->willThrowException(new RequestException('exception_message'));

        $this->client->expects($this->once())->method('createRequest')->willReturn($request);

        $this->logger->expects($this->once())->method('info')->with('VAULT-REQUEST: ["foo\/some_key",""]');

        $this->expectException(VaultClientException::class);
        $this->expectExceptionMessage('Transport exception: exception_message');

        $this->vaultClient->executeSetValueRequest('some_key', 'some_value');
    }

    /**
     * @return resource
     */
    private function getResponseBodyResource(string $responseName)
    {
        $filename = sprintf('./tests/mock/%s_response_body.json', $responseName);
        $result = fopen($filename, 'r+');

        if (false === $result) {
            throw new \RuntimeException(sprintf('Could not open file "%s"', $filename));
        }

        return $result;
    }
}
