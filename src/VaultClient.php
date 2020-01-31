<?php

namespace Vault;

use function array_key_exists;
use Guzzle\Http\ClientInterface;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Exception\RequestException;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use function json_decode;
use function json_encode;
use function json_last_error;
use Psr\Log\LoggerInterface;
use function sprintf;
use Vault\Exception\VaultClientException;
use Vault\Exception\VaultClientKeyNotFoundException;
use Vault\Exception\VaultClientResponseParsingException;

class VaultClient
{
    protected const ERROR_CODE_TRANSPORT = 800;
    private const READ_BUFFER = 1048576;

    protected ClientInterface $client;

    protected VaultConfigModel $vaultConfig;

    protected ?LoggerInterface $logger;

    public function __construct(ClientInterface $client, VaultConfigModel $vaultConfig, ?LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->vaultConfig = $vaultConfig;
        $this->logger = $logger;
    }

    /**
     * @return mixed
     */
    public function executeGetValueRequest(string $key)
    {
        $response = $this->request(RequestInterface::GET, $key);

        if (404 === $response->getStatusCode()) {
            throw new VaultClientKeyNotFoundException(sprintf('Value with key "%s" is not found', $key));
        }

        $data = $this->transformResponseToArray($response);

        if (!array_key_exists('data', $data) || !array_key_exists('data', $data['data']) || !array_key_exists('value', $data['data']['data'])) {
            throw new VaultClientResponseParsingException('Could not parse response');
        }

        return $data['data']['data']['value'];
    }

    public function executeSetValueRequest(string $key, string $value): void
    {
        $data = json_encode([
            'data' => [
                'value' => $value,
            ],
        ]);

        if (false === $data) {
            throw new VaultClientException('Request body encoding error');
        }

        $this->request(RequestInterface::POST, $key, $data);
    }

    protected function request(string $method, ?string $route = null, ?string $data = null): Response
    {
        $url = sprintf('%s/%s', $this->vaultConfig->getBaseUrl(), $route);

        $headers = [
            'X-Vault-Token' => $this->vaultConfig->getToken(),
            'Content-Type' => 'application/json',
        ];

        $request = $this->client->createRequest($method, $url, $headers, $data);

        $this->writeRequestLog($request);

        try {
            $response = $request->send();
        } catch (RequestException $exception) {
            throw new VaultClientException(sprintf('Transport exception: %s', $exception->getMessage()), static::ERROR_CODE_TRANSPORT, $exception);
        }

        $this->writeResponseLog($response);

        return $response;
    }

    protected function writeRequestLog(RequestInterface $request): void
    {
        if (null !== $this->logger) {
            $requestData = json_encode([$request->getUrl(), (string) $request]);
            $message = sprintf('VAULT-REQUEST: %s', $requestData);
            $this->logger->info($message);
        }
    }

    protected function writeResponseLog(Response $response): void
    {
        if (null !== $this->logger) {
            $responseDataJson = json_encode([$response->getLocation(), $response->getStatusCode(), $response->getMessage(), $response->getBody(true)]);
            $message = sprintf('VAULT-RESPONSE: %s', $responseDataJson);
            $this->logger->info($message);
        }
    }

    /**
     * @return array|mixed[]
     */
    protected function transformResponseToArray(Response $response): array
    {
        $rawResponse = '';
        $responseBody = $response->getBody();

        if ($responseBody instanceof EntityBody) {
            while ($responseBody->isReadable() && $responseBody->isSeekable() && !$responseBody->isConsumed()) {
                $rawResponse .= $responseBody->read(static::READ_BUFFER);
            }
        }

        $result = json_decode($rawResponse, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $result = [];
        }

        return $result;
    }
}
