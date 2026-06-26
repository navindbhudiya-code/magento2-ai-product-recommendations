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

namespace NavinDBhudiya\ProductRecommendation\Api;

/**
 * Backend-agnostic vector store contract.
 *
 * Implementations store and query product embeddings. The same contract is satisfied by
 * ChromaDB (existing) and the search engine (OpenSearch/Elasticsearch) so the rest of the
 * module never hard-depends on a particular backend.
 *
 * Records passed to upsert() are associative arrays:
 *   ['id' => string, 'vector' => float[], 'document' => string, 'metadata' => array]
 * Matches returned by query() are associative arrays:
 *   ['id' => string, 'score' => float, 'distance' => float, 'metadata' => array]
 */
interface VectorStoreInterface
{
    /**
     * Insert or update records in a collection (index), creating it if needed.
     *
     * @param string $collection
     * @param array $records
     * @return bool True on success.
     */
    public function upsert(string $collection, array $records): bool;

    /**
     * Find the $k nearest records to $vector, optionally constrained by equality $filters.
     *
     * @param string $collection
     * @param float[] $vector
     * @param int $k
     * @param array $filters Field => value equality constraints applied server-side.
     * @return array List of match arrays, ordered most-similar first.
     */
    public function query(string $collection, array $vector, int $k = 10, array $filters = []): array;

    /**
     * Delete records by id.
     *
     * @param string $collection
     * @param string[] $ids
     * @return bool
     */
    public function delete(string $collection, array $ids): bool;

    /**
     * Number of records stored in a collection (0 if it does not exist).
     *
     * @param string $collection
     * @return int
     */
    public function count(string $collection): int;

    /**
     * Whether the backend is reachable.
     *
     * @return bool
     */
    public function ping(): bool;

    /**
     * Short identifier of the backend (e.g. "chromadb", "search_engine").
     *
     * @return string
     */
    public function getName(): string;
}
