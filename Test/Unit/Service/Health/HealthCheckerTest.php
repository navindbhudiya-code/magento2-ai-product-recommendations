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

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Service\Health;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use NavinDBhudiya\ProductRecommendation\Api\EmbeddingProviderInterface;
use NavinDBhudiya\ProductRecommendation\Api\VectorStoreInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use NavinDBhudiya\ProductRecommendation\Service\Health\HealthChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the health checker (mocked backends + catalog count).
 */
class HealthCheckerTest extends TestCase
{
    /**
     * @var EmbeddingProviderInterface|MockObject
     */
    private $embedding;

    /**
     * @var VectorStoreInterface|MockObject
     */
    private $vectorStore;

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var CollectionFactory|MockObject
     */
    private $collectionFactory;

    protected function setUp(): void
    {
        $this->embedding = $this->createMock(EmbeddingProviderInterface::class);
        $this->vectorStore = $this->createMock(VectorStoreInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->config->method('getCollectionName')->willReturn('magento_products');
    }

    private function checker(): HealthChecker
    {
        return new HealthChecker($this->embedding, $this->vectorStore, $this->config, $this->collectionFactory);
    }

    private function stubCatalogSize(int $size): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getSize')->willReturn($size);
        $this->collectionFactory->method('create')->willReturn($collection);
    }

    public function testCoveragePercent(): void
    {
        $checker = $this->checker();
        $this->assertSame(0.0, $checker->coveragePercent(0, 0));
        $this->assertSame(50.0, $checker->coveragePercent(50, 100));
        $this->assertSame(33.3, $checker->coveragePercent(1, 3));
        // Indexed can never exceed total in the ratio.
        $this->assertSame(100.0, $checker->coveragePercent(120, 100));
    }

    public function testOverallStatusTransitions(): void
    {
        $checker = $this->checker();
        $this->assertSame(HealthChecker::STATUS_RED, $checker->overallStatus(false, true, 100.0));
        $this->assertSame(HealthChecker::STATUS_RED, $checker->overallStatus(true, false, 100.0));
        $this->assertSame(HealthChecker::STATUS_YELLOW, $checker->overallStatus(true, true, 50.0));
        $this->assertSame(HealthChecker::STATUS_GREEN, $checker->overallStatus(true, true, 95.0));
    }

    public function testGatherGreenWhenAllHealthyAndIndexed(): void
    {
        $this->embedding->method('isAvailable')->willReturn(true);
        $this->embedding->method('getName')->willReturn('api');
        $this->vectorStore->method('ping')->willReturn(true);
        $this->vectorStore->method('getName')->willReturn('search_engine');
        $this->vectorStore->method('count')->with('magento_products')->willReturn(100);
        $this->stubCatalogSize(100);

        $report = $this->checker()->gather();

        $this->assertSame(HealthChecker::STATUS_GREEN, $report['status']);
        $this->assertSame('api', $report['embedding_provider']['name']);
        $this->assertTrue($report['embedding_provider']['available']);
        $this->assertSame('search_engine', $report['vector_store']['name']);
        $this->assertSame(100, $report['coverage']['indexed']);
        $this->assertSame(100.0, $report['coverage']['percent']);
    }

    public function testGatherYellowWhenUnderIndexed(): void
    {
        $this->embedding->method('isAvailable')->willReturn(true);
        $this->embedding->method('getName')->willReturn('api');
        $this->vectorStore->method('ping')->willReturn(true);
        $this->vectorStore->method('getName')->willReturn('chromadb');
        $this->vectorStore->method('count')->willReturn(40);
        $this->stubCatalogSize(100);

        $this->assertSame(HealthChecker::STATUS_YELLOW, $this->checker()->gather()['status']);
    }

    public function testGatherRedWhenStoreUnreachableAndIndexedForcedZero(): void
    {
        $this->embedding->method('isAvailable')->willReturn(true);
        $this->embedding->method('getName')->willReturn('api');
        $this->vectorStore->method('ping')->willReturn(false);
        $this->vectorStore->method('getName')->willReturn('chromadb');
        // count() must not even be consulted when the store is unreachable.
        $this->vectorStore->expects($this->never())->method('count');
        $this->stubCatalogSize(100);

        $report = $this->checker()->gather();
        $this->assertSame(HealthChecker::STATUS_RED, $report['status']);
        $this->assertSame(0, $report['coverage']['indexed']);
    }

    public function testGatherSwallowsBackendExceptions(): void
    {
        $this->embedding->method('isAvailable')->willThrowException(new \RuntimeException('boom'));
        $this->embedding->method('getName')->willReturn('api');
        $this->vectorStore->method('ping')->willThrowException(new \RuntimeException('down'));
        $this->vectorStore->method('getName')->willReturn('chromadb');
        $this->stubCatalogSize(10);

        $report = $this->checker()->gather();
        $this->assertSame(HealthChecker::STATUS_RED, $report['status']);
        $this->assertFalse($report['embedding_provider']['available']);
        $this->assertFalse($report['vector_store']['reachable']);
    }
}
