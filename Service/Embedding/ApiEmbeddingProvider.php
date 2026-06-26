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

namespace NavinDBhudiya\ProductRecommendation\Service\Embedding;

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use NavinDBhudiya\ProductRecommendation\Api\EmbeddingProviderInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Hosted-embedding provider (OpenAI-compatible `/v1/embeddings` API).
 *
 * The documented default for real merchants: no Python embedding-service container required.
 * Model, dimension, endpoint, and the encrypted API key all come from admin config. Compatible
 * with any OpenAI-style endpoint (OpenAI, Voyage, Azure OpenAI, local proxies).
 */
class ApiEmbeddingProvider implements EmbeddingProviderInterface
{
    public const NAME = 'api';

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
    public function generateEmbeddings(array $texts): array
    {
        if ($texts === []) {
            return [];
        }
        if (!$this->isAvailable()) {
            $this->logger->error('[ProductRecommendation] Hosted embedding API key is not configured.');
            return [];
        }

        try {
            $payload = [
                'model' => $this->config->getApiEmbeddingModel(),
                'input' => array_values($texts),
            ];
            // OpenAI text-embedding-3-* accept a "dimensions" parameter to truncate output.
            $dimension = $this->config->getApiEmbeddingDimension();
            if ($dimension > 0) {
                $payload['dimensions'] = $dimension;
            }

            $response = $this->getClient()->post('', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config->getApiEmbeddingKey(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            return $this->extractEmbeddings($response->getBody()->getContents());
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] Hosted embedding API error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function generateEmbedding(string $text): array
    {
        $embeddings = $this->generateEmbeddings([$text]);
        return $embeddings[0] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getDimension(): int
    {
        return $this->config->getApiEmbeddingDimension();
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return $this->config->getApiEmbeddingKey() !== '';
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Parse the `data[].embedding` vectors from an OpenAI-style response, preserving order.
     *
     * @param string $body
     * @return array
     */
    private function extractEmbeddings(string $body): array
    {
        $decoded = json_decode($body, true);
        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            $this->logger->error('[ProductRecommendation] Unexpected embedding API response.');
            return [];
        }

        // Sort by index when present so vectors line up with the input order.
        usort($data, static function ($a, $b) {
            return ($a['index'] ?? 0) <=> ($b['index'] ?? 0);
        });

        $vectors = [];
        foreach ($data as $row) {
            if (isset($row['embedding']) && is_array($row['embedding'])) {
                $vectors[] = $row['embedding'];
            }
        }
        return $vectors;
    }

    /**
     * Lazily build the HTTP client pointed at the configured embeddings endpoint.
     *
     * @return Client
     */
    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = $this->clientFactory->create([
                'config' => [
                    'base_uri' => $this->config->getApiEmbeddingEndpoint(),
                    'timeout' => 60,
                    'connect_timeout' => 10,
                ],
            ]);
        }
        return $this->client;
    }
}
