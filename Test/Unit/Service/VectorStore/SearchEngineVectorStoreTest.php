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

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Service\VectorStore;

use NavinDBhudiya\ProductRecommendation\Service\VectorStore\SearchEngineVectorStore;
use NavinDBhudiya\ProductRecommendation\Service\VectorStore\Transport\SearchTransportInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the OpenSearch/Elasticsearch k-NN vector store (mocked transport).
 */
class SearchEngineVectorStoreTest extends TestCase
{
    /**
     * @var SearchTransportInterface|MockObject
     */
    private $transport;

    /**
     * @var SearchEngineVectorStore
     */
    private SearchEngineVectorStore $store;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(SearchTransportInterface::class);
        $this->store = new SearchEngineVectorStore($this->transport, $this->createMock(LoggerInterface::class), 4);
    }

    public function testNameAndPing(): void
    {
        $this->transport->method('ping')->willReturn(true);
        $this->assertSame('search_engine', $this->store->getName());
        $this->assertTrue($this->store->ping());
    }

    public function testQueryBuildsPlainKnnBodyWhenNoFilters(): void
    {
        $this->transport->expects($this->once())
            ->method('request')
            ->with('POST', '/products/_search', [
                'size' => 3,
                'query' => ['knn' => ['vector' => ['vector' => [0.1, 0.2, 0.3, 0.4], 'k' => 3]]],
            ])
            ->willReturn(['hits' => ['hits' => [
                ['_id' => '7', '_score' => 1.0, '_source' => ['metadata' => ['sku' => 'S7']]],
                ['_id' => '8', '_score' => 0.4, '_source' => ['metadata' => ['sku' => 'S8']]],
            ]]]);

        $matches = $this->store->query('products', [0.1, 0.2, 0.3, 0.4], 3);

        $this->assertCount(2, $matches);
        $this->assertSame('7', $matches[0]['id']);
        $this->assertSame(1.0, $matches[0]['score']);
        $this->assertEqualsWithDelta(0.6, $matches[1]['distance'], 1e-9); // 1 - 0.4
        $this->assertSame('S8', $matches[1]['metadata']['sku']);
    }

    public function testQueryAddsTermFiltersInsideBool(): void
    {
        $this->transport->expects($this->once())
            ->method('request')
            ->with('POST', '/products/_search', [
                'size' => 5,
                'query' => [
                    'bool' => [
                        'must' => [['knn' => ['vector' => ['vector' => [1.0, 0.0, 0.0, 0.0], 'k' => 5]]]],
                        'filter' => [['term' => ['metadata.category' => 'bags']]],
                    ],
                ],
            ])
            ->willReturn(['hits' => ['hits' => []]]);

        $this->assertSame([], $this->store->query('products', [1.0, 0.0, 0.0, 0.0], 5, ['category' => 'bags']));
    }

    public function testUpsertEnsuresIndexThenBulks(): void
    {
        // First the existence probe returns "not found" (error) -> store creates the index.
        $this->transport->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $method, string $path, array $body = []) {
                if ($method === 'GET') {
                    $this->assertSame('/products/_count', $path);
                    return ['error' => 'index_not_found_exception'];
                }
                $this->assertSame('PUT', $method);
                $this->assertSame('/products', $path);
                $this->assertSame('knn_vector', $body['mappings']['properties']['vector']['type']);
                $this->assertSame(4, $body['mappings']['properties']['vector']['dimension']);
                $this->assertTrue($body['settings']['index']['knn']);
                return ['acknowledged' => true];
            });

        $captured = '';
        $this->transport->expects($this->once())
            ->method('bulk')
            ->willReturnCallback(function (string $ndjson) use (&$captured) {
                $captured = $ndjson;
                return ['errors' => false];
            });

        $ok = $this->store->upsert('products', [
            ['id' => '1', 'vector' => [0.1, 0.2, 0.3, 0.4], 'document' => 'A', 'metadata' => ['sku' => 'A']],
        ]);

        $this->assertTrue($ok);
        $this->assertStringContainsString('"_index":"products"', $captured);
        $this->assertStringContainsString('"_id":"1"', $captured);
        $this->assertStringContainsString('"sku":"A"', $captured);
    }

    public function testUpsertReturnsFalseWhenBulkReportsErrors(): void
    {
        $this->transport->method('request')->willReturn(['count' => 0]); // index exists
        $this->transport->method('bulk')->willReturn(['errors' => true]);

        $ok = $this->store->upsert('products', [
            ['id' => '1', 'vector' => [0.1, 0.2, 0.3, 0.4], 'document' => 'A', 'metadata' => []],
        ]);
        $this->assertFalse($ok);
    }

    public function testCountReadsCountField(): void
    {
        $this->transport->method('request')->with('GET', '/products/_count')->willReturn(['count' => 123]);
        $this->assertSame(123, $this->store->count('products'));
    }

    public function testDeleteBulksDeleteActions(): void
    {
        $captured = '';
        $this->transport->method('bulk')->willReturnCallback(function (string $ndjson) use (&$captured) {
            $captured = $ndjson;
            return ['errors' => false];
        });
        $this->assertTrue($this->store->delete('products', ['9', '10']));
        $this->assertStringContainsString('"delete":{"_index":"products","_id":"9"}', $captured);
    }

    public function testEmptyVectorAndEmptyRecordsShortCircuit(): void
    {
        $this->transport->expects($this->never())->method('request');
        $this->transport->expects($this->never())->method('bulk');
        $this->assertSame([], $this->store->query('products', [], 5));
        $this->assertTrue($this->store->upsert('products', []));
        $this->assertTrue($this->store->delete('products', []));
    }
}
