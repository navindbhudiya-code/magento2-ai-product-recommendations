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

use NavinDBhudiya\ProductRecommendation\Api\ChromaClientInterface;
use NavinDBhudiya\ProductRecommendation\Service\VectorStore\ChromaVectorStore;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the ChromaDB VectorStore adapter.
 */
class ChromaVectorStoreTest extends TestCase
{
    /**
     * @var ChromaClientInterface|MockObject
     */
    private $client;

    /**
     * @var ChromaVectorStore
     */
    private ChromaVectorStore $store;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ChromaClientInterface::class);
        $this->store = new ChromaVectorStore($this->client, $this->createMock(LoggerInterface::class));
    }

    public function testGetNameAndPing(): void
    {
        $this->client->method('testConnection')->willReturn(true);
        $this->assertSame('chromadb', $this->store->getName());
        $this->assertTrue($this->store->ping());
    }

    public function testUpsertMapsRecordsToColumnarArguments(): void
    {
        $this->client->method('getOrCreateCollection')->with('products')->willReturn(['id' => 'col-1']);
        $this->client->expects($this->once())
            ->method('upsertDocuments')
            ->with(
                'col-1',
                ['1', '2'],
                ['doc one', 'doc two'],
                [['sku' => 'A'], ['sku' => 'B']],
                [[0.1, 0.2], [0.3, 0.4]]
            )
            ->willReturn(true);

        $records = [
            ['id' => '1', 'document' => 'doc one', 'metadata' => ['sku' => 'A'], 'vector' => [0.1, 0.2]],
            ['id' => '2', 'document' => 'doc two', 'metadata' => ['sku' => 'B'], 'vector' => [0.3, 0.4]],
        ];
        $this->assertTrue($this->store->upsert('products', $records));
    }

    public function testUpsertEmptyShortCircuits(): void
    {
        $this->client->expects($this->never())->method('upsertDocuments');
        $this->assertTrue($this->store->upsert('products', []));
    }

    public function testQueryNormalizesColumnarResponseAndScores(): void
    {
        $this->client->method('getOrCreateCollection')->willReturn(['id' => 'col-1']);
        $this->client->expects($this->once())
            ->method('query')
            ->with('col-1', [], 5, ['category' => 'bags'], [], [[0.5, 0.5]])
            ->willReturn([
                'ids' => [['10', '11']],
                'distances' => [[0.0, 0.25]],
                'metadatas' => [[['sku' => 'X'], ['sku' => 'Y']]],
            ]);

        $matches = $this->store->query('products', [0.5, 0.5], 5, ['category' => 'bags']);

        $this->assertCount(2, $matches);
        $this->assertSame('10', $matches[0]['id']);
        $this->assertSame(1.0, $matches[0]['score']);     // distance 0 -> score 1
        $this->assertSame(0.75, $matches[1]['score']);    // distance 0.25 -> score 0.75
        $this->assertSame('Y', $matches[1]['metadata']['sku']);
    }

    public function testQueryEmptyVectorReturnsNoMatches(): void
    {
        $this->client->expects($this->never())->method('query');
        $this->assertSame([], $this->store->query('products', [], 5));
    }

    public function testQueryClampsNegativeScoreToZero(): void
    {
        $this->client->method('getOrCreateCollection')->willReturn(['id' => 'c']);
        $this->client->method('query')->willReturn([
            'ids' => [['1']],
            'distances' => [[1.8]], // distance > 1 -> score would be negative -> clamp 0
            'metadatas' => [[[]]],
        ]);
        $matches = $this->store->query('products', [0.1], 1);
        $this->assertSame(0.0, $matches[0]['score']);
    }

    public function testCountReturnsClientCount(): void
    {
        $this->client->method('getOrCreateCollection')->willReturn(['id' => 'c']);
        $this->client->method('count')->with('c')->willReturn(42);
        $this->assertSame(42, $this->store->count('products'));
    }

    public function testDeleteForwardsIds(): void
    {
        $this->client->method('getOrCreateCollection')->willReturn(['id' => 'c']);
        $this->client->expects($this->once())->method('deleteDocuments')->with('c', ['1', '2'])->willReturn(true);
        $this->assertTrue($this->store->delete('products', ['1', '2']));
    }

    public function testCollectionResolutionFailureIsHandled(): void
    {
        $this->client->method('getOrCreateCollection')->willThrowException(new \RuntimeException('down'));
        $this->assertSame(0, $this->store->count('products'));
        $this->assertSame([], $this->store->query('products', [0.1], 5));
        $this->assertFalse($this->store->delete('products', ['1']));
    }
}
