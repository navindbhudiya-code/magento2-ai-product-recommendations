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

use NavinDBhudiya\ProductRecommendation\Api\ChromaClientInterface;
use NavinDBhudiya\ProductRecommendation\Api\VectorStoreInterface;
use Psr\Log\LoggerInterface;

/**
 * VectorStoreInterface adapter over the existing ChromaClient (no behaviour change for users).
 */
class ChromaVectorStore implements VectorStoreInterface
{
    public const NAME = 'chromadb';

    /**
     * @var ChromaClientInterface
     */
    private ChromaClientInterface $client;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ChromaClientInterface $client
     * @param LoggerInterface $logger
     */
    public function __construct(ChromaClientInterface $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function upsert(string $collection, array $records): bool
    {
        if ($records === []) {
            return true;
        }
        $collectionId = $this->resolveCollectionId($collection);
        if ($collectionId === null) {
            return false;
        }

        $ids = [];
        $documents = [];
        $metadatas = [];
        $embeddings = [];
        foreach ($records as $record) {
            $ids[] = (string) ($record['id'] ?? '');
            $documents[] = (string) ($record['document'] ?? '');
            $metadatas[] = (array) ($record['metadata'] ?? []);
            $embeddings[] = (array) ($record['vector'] ?? []);
        }

        return $this->client->upsertDocuments($collectionId, $ids, $documents, $metadatas, $embeddings);
    }

    /**
     * @inheritDoc
     */
    public function query(string $collection, array $vector, int $k = 10, array $filters = []): array
    {
        if ($vector === []) {
            return [];
        }
        $collectionId = $this->resolveCollectionId($collection);
        if ($collectionId === null) {
            return [];
        }

        $raw = $this->client->query($collectionId, [], $k, $filters, [], [$vector]);

        return $this->normalizeMatches($raw);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $collection, array $ids): bool
    {
        if ($ids === []) {
            return true;
        }
        $collectionId = $this->resolveCollectionId($collection);
        if ($collectionId === null) {
            return false;
        }
        return $this->client->deleteDocuments($collectionId, $ids);
    }

    /**
     * @inheritDoc
     */
    public function count(string $collection): int
    {
        $collectionId = $this->resolveCollectionId($collection);
        if ($collectionId === null) {
            return 0;
        }
        return $this->client->count($collectionId);
    }

    /**
     * @inheritDoc
     */
    public function ping(): bool
    {
        return $this->client->testConnection();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Resolve a collection name to ChromaDB's internal collection id.
     *
     * @param string $collection
     * @return string|null
     */
    private function resolveCollectionId(string $collection): ?string
    {
        try {
            $result = $this->client->getOrCreateCollection($collection);
            $id = $result['id'] ?? null;
            return $id !== null ? (string) $id : null;
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] ChromaVectorStore collection error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert ChromaDB's columnar query response into a flat list of match arrays.
     *
     * Chroma returns parallel arrays nested one level per query vector:
     *   ['ids' => [[...]], 'distances' => [[...]], 'metadatas' => [[...]]]
     * We always send a single vector, so we read index 0 of each.
     *
     * @param array $raw
     * @return array
     */
    private function normalizeMatches(array $raw): array
    {
        $ids = $raw['ids'][0] ?? [];
        $distances = $raw['distances'][0] ?? [];
        $metadatas = $raw['metadatas'][0] ?? [];

        $matches = [];
        foreach ($ids as $i => $id) {
            $distance = isset($distances[$i]) ? (float) $distances[$i] : 0.0;
            $matches[] = [
                'id' => (string) $id,
                'distance' => $distance,
                'score' => $this->distanceToScore($distance),
                'metadata' => (array) ($metadatas[$i] ?? []),
            ];
        }
        return $matches;
    }

    /**
     * Map a cosine distance (0 = identical) to a 0..1 similarity score.
     *
     * @param float $distance
     * @return float
     */
    private function distanceToScore(float $distance): float
    {
        $score = 1.0 - $distance;
        if ($score < 0.0) {
            return 0.0;
        }
        if ($score > 1.0) {
            return 1.0;
        }
        return $score;
    }
}
