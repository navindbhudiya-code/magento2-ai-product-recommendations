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

namespace NavinDBhudiya\ProductRecommendation\Service\VectorStore;

use NavinDBhudiya\ProductRecommendation\Api\VectorStoreInterface;
use NavinDBhudiya\ProductRecommendation\Service\VectorStore\Transport\SearchTransportInterface;
use Psr\Log\LoggerInterface;

/**
 * VectorStoreInterface backed by the store's existing OpenSearch/Elasticsearch via k-NN.
 *
 * Stores embeddings in a `knn_vector` field so recommendations need zero extra infrastructure
 * for merchants already running OpenSearch (Magento 2.4.6+ default).
 */
class SearchEngineVectorStore implements VectorStoreInterface
{
    public const NAME = 'search_engine';

    /**
     * @var SearchTransportInterface
     */
    private SearchTransportInterface $transport;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var int
     */
    private int $dimension;

    /**
     * In-memory guard so the index mapping is only ensured once per request per collection.
     *
     * @var array<string, bool>
     */
    private array $ensured = [];

    /**
     * @param SearchTransportInterface $transport
     * @param LoggerInterface $logger
     * @param int $dimension Embedding dimension for the knn_vector mapping.
     */
    public function __construct(
        SearchTransportInterface $transport,
        LoggerInterface $logger,
        int $dimension = 1536
    ) {
        $this->transport = $transport;
        $this->logger = $logger;
        $this->dimension = $dimension;
    }

    /**
     * @inheritDoc
     */
    public function upsert(string $collection, array $records): bool
    {
        if ($records === []) {
            return true;
        }
        $this->ensureIndex($collection);

        $lines = [];
        foreach ($records as $record) {
            $id = (string) ($record['id'] ?? '');
            $lines[] = json_encode(['index' => ['_index' => $collection, '_id' => $id]]);
            $lines[] = json_encode([
                'vector' => array_values((array) ($record['vector'] ?? [])),
                'document' => (string) ($record['document'] ?? ''),
                'metadata' => (array) ($record['metadata'] ?? []),
            ]);
        }
        $ndjson = implode("\n", $lines) . "\n";

        $response = $this->transport->bulk($ndjson);
        if (!empty($response['errors'])) {
            $this->logger->error('[ProductRecommendation] SearchEngine bulk upsert reported errors.');
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function query(string $collection, array $vector, int $k = 10, array $filters = []): array
    {
        if ($vector === []) {
            return [];
        }
        $body = $this->buildKnnQuery(array_values($vector), $k, $filters);
        $response = $this->transport->request('POST', '/' . $collection . '/_search', $body);

        return $this->normalizeHits($response);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $collection, array $ids): bool
    {
        if ($ids === []) {
            return true;
        }
        $lines = [];
        foreach ($ids as $id) {
            $lines[] = json_encode(['delete' => ['_index' => $collection, '_id' => (string) $id]]);
        }
        $response = $this->transport->bulk(implode("\n", $lines) . "\n");
        return empty($response['errors']);
    }

    /**
     * @inheritDoc
     */
    public function count(string $collection): int
    {
        $response = $this->transport->request('GET', '/' . $collection . '/_count');
        return (int) ($response['count'] ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function ping(): bool
    {
        return $this->transport->ping();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Create the knn-enabled index if it does not already exist.
     *
     * @param string $collection
     * @return void
     */
    private function ensureIndex(string $collection): void
    {
        if (isset($this->ensured[$collection])) {
            return;
        }
        $this->ensured[$collection] = true;

        $existing = $this->transport->request('GET', '/' . $collection . '/_count');
        if (!isset($existing['error'])) {
            return;
        }
        $this->transport->request('PUT', '/' . $collection, $this->buildIndexBody());
    }

    /**
     * Index settings + mapping with a knn_vector field of the configured dimension.
     *
     * @return array
     */
    private function buildIndexBody(): array
    {
        return [
            'settings' => ['index' => ['knn' => true]],
            'mappings' => [
                'properties' => [
                    'vector' => ['type' => 'knn_vector', 'dimension' => $this->dimension],
                    'document' => ['type' => 'text'],
                    'metadata' => ['type' => 'object'],
                ],
            ],
        ];
    }

    /**
     * Build a k-NN search body, adding term filters when constraints are supplied.
     *
     * @param float[] $vector
     * @param int $k
     * @param array $filters
     * @return array
     */
    private function buildKnnQuery(array $vector, int $k, array $filters): array
    {
        $knn = ['knn' => ['vector' => ['vector' => $vector, 'k' => $k]]];
        if ($filters === []) {
            return ['size' => $k, 'query' => $knn];
        }

        $termFilters = [];
        foreach ($filters as $field => $value) {
            $termFilters[] = ['term' => ['metadata.' . $field => $value]];
        }
        return [
            'size' => $k,
            'query' => [
                'bool' => [
                    'must' => [$knn],
                    'filter' => $termFilters,
                ],
            ],
        ];
    }

    /**
     * Convert an OpenSearch search response into match arrays.
     *
     * @param array $response
     * @return array
     */
    private function normalizeHits(array $response): array
    {
        $hits = $response['hits']['hits'] ?? [];
        $matches = [];
        foreach ($hits as $hit) {
            $score = (float) ($hit['_score'] ?? 0.0);
            $matches[] = [
                'id' => (string) ($hit['_id'] ?? ''),
                'score' => $score,
                'distance' => $score > 0.0 ? 1.0 - $score : 1.0,
                'metadata' => (array) ($hit['_source']['metadata'] ?? []),
            ];
        }
        return $matches;
    }
}
