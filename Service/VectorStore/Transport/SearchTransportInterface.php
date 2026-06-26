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

/**
 * Thin transport over an OpenSearch/Elasticsearch HTTP API.
 *
 * Kept minimal and separate from SearchEngineVectorStore so the store's request-building
 * logic is unit-testable against a mock without a live cluster.
 */
interface SearchTransportInterface
{
    /**
     * Perform a JSON request and return the decoded response body.
     *
     * @param string $method HTTP method (GET/PUT/POST/DELETE).
     * @param string $path Path beginning with "/".
     * @param array $body JSON body (omitted when empty).
     * @return array Decoded response (may contain an "error" key on 4xx/5xx).
     */
    public function request(string $method, string $path, array $body = []): array;

    /**
     * Perform a `_bulk` request with newline-delimited JSON.
     *
     * @param string $ndjson
     * @return array
     */
    public function bulk(string $ndjson): array;

    /**
     * Whether the cluster is reachable.
     *
     * @return bool
     */
    public function ping(): bool;
}
