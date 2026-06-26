<?php
/**
 * NavinDBhudiya ProductRecommendation
 *
 * @category  NavinDBhudiya
 * @package   NavinDBhudiya_ProductRecommendation
 * @author    Navin Bhudiya
 * @license   MIT License
 */

declare(strict_types=1);

namespace NavinDBhudiya\ProductRecommendation\Service\VectorStore\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Guzzle-backed OpenSearch/Elasticsearch transport.
 *
 * Uses the host/port/scheme from module config. `http_errors` is disabled so 4xx responses
 * (e.g. "index already exists", "index not found") are returned as decoded bodies rather
 * than thrown, letting SearchEngineVectorStore make idempotent decisions.
 */
class GuzzleSearchTransport implements SearchTransportInterface
{
    /**
     * @var ClientFactory
     */
    private ClientFactory $clientFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Client|null
     */
    private ?Client $client = null;

    /**
     * @param ClientFactory $clientFactory
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(ClientFactory $clientFactory, Config $config, LoggerInterface $logger)
    {
        $this->clientFactory = $clientFactory;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function request(string $method, string $path, array $body = []): array
    {
        try {
            $options = ['headers' => ['Content-Type' => 'application/json']];
            if ($body !== []) {
                $options['json'] = $body;
            }
            $response = $this->getClient()->request($method, ltrim($path, '/'), $options);
            return $this->decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] SearchEngine transport error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @inheritDoc
     */
    public function bulk(string $ndjson): array
    {
        try {
            $response = $this->getClient()->post('_bulk', [
                'headers' => ['Content-Type' => 'application/x-ndjson'],
                'body' => $ndjson,
            ]);
            return $this->decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] SearchEngine bulk error: ' . $e->getMessage());
            return ['errors' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * @inheritDoc
     */
    public function ping(): bool
    {
        try {
            $response = $this->getClient()->get('');
            return $response->getStatusCode() < 400;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Lazily build the HTTP client pointed at the configured cluster.
     *
     * @return Client
     */
    private function getClient(): Client
    {
        if ($this->client === null) {
            $baseUri = sprintf(
                '%s://%s:%d/',
                $this->config->getSearchScheme(),
                $this->config->getSearchHost(),
                $this->config->getSearchPort()
            );
            $this->client = $this->clientFactory->create([
                'config' => [
                    'base_uri' => $baseUri,
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'http_errors' => false,
                ],
            ]);
        }
        return $this->client;
    }

    /**
     * Decode a JSON body to an array.
     *
     * @param string $body
     * @return array
     */
    private function decode(string $body): array
    {
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}
