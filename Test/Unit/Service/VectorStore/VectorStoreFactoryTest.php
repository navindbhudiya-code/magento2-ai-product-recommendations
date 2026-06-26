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

use Magento\Framework\ObjectManagerInterface;
use NavinDBhudiya\ProductRecommendation\Api\VectorStoreInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use NavinDBhudiya\ProductRecommendation\Service\VectorStore\VectorStoreFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the vector-store backend selector.
 */
class VectorStoreFactoryTest extends TestCase
{
    /**
     * @var ObjectManagerInterface|MockObject
     */
    private $objectManager;

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var VectorStoreInterface|MockObject
     */
    private $chroma;

    /**
     * @var VectorStoreInterface|MockObject
     */
    private $search;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->chroma = $this->createMock(VectorStoreInterface::class);
        $this->chroma->method('getName')->willReturn('chromadb');
        $this->search = $this->createMock(VectorStoreInterface::class);
        $this->search->method('getName')->willReturn('search_engine');

        $this->objectManager->method('get')->willReturnMap([
            ['ChromaStore', $this->chroma],
            ['SearchStore', $this->search],
        ]);
    }

    private function factory(): VectorStoreFactory
    {
        return new VectorStoreFactory($this->objectManager, $this->config, [
            'chromadb' => 'ChromaStore',
            'search_engine' => 'SearchStore',
        ]);
    }

    public function testSelectsSearchEngineWhenConfigured(): void
    {
        $this->config->method('getVectorStoreBackend')->willReturn('search_engine');
        $this->assertSame('search_engine', $this->factory()->getName());
    }

    public function testDefaultsToChromaForUnknownBackend(): void
    {
        $this->config->method('getVectorStoreBackend')->willReturn('totally-unknown');
        $this->assertSame('chromadb', $this->factory()->getName());
    }

    public function testProxiesQueryToSelectedBackend(): void
    {
        $this->config->method('getVectorStoreBackend')->willReturn('search_engine');
        $this->search->expects($this->once())
            ->method('query')
            ->with('idx', [0.1, 0.2], 5, ['c' => 'x'])
            ->willReturn([['id' => '1']]);

        $this->assertSame([['id' => '1']], $this->factory()->query('idx', [0.1, 0.2], 5, ['c' => 'x']));
    }

    public function testBackendResolvedOnlyOnce(): void
    {
        $this->config->method('getVectorStoreBackend')->willReturn('chromadb');
        $this->objectManager->expects($this->once())->method('get')->with('ChromaStore')->willReturn($this->chroma);

        $factory = new VectorStoreFactory($this->objectManager, $this->config, ['chromadb' => 'ChromaStore']);
        $factory->ping();
        $factory->count('idx');
        $factory->getName();
    }

    public function testAvailableBackends(): void
    {
        $this->assertSame(['chromadb', 'search_engine'], $this->factory()->getAvailableBackends());
    }
}
