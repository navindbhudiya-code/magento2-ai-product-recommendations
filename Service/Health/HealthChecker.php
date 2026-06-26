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

namespace NavinDBhudiya\ProductRecommendation\Service\Health;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use NavinDBhudiya\ProductRecommendation\Api\EmbeddingProviderInterface;
use NavinDBhudiya\ProductRecommendation\Api\VectorStoreInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;

/**
 * Aggregates a health report: embedding provider + vector store reachability and index
 * coverage (X / Y products indexed). Backs the recommendation:health CLI and admin widget.
 */
class HealthChecker
{
    public const STATUS_GREEN = 'green';
    public const STATUS_YELLOW = 'yellow';
    public const STATUS_RED = 'red';

    /**
     * Coverage at or above this percent is considered healthy.
     */
    public const HEALTHY_COVERAGE = 90.0;

    /**
     * @var EmbeddingProviderInterface
     */
    private EmbeddingProviderInterface $embeddingProvider;

    /**
     * @var VectorStoreInterface
     */
    private VectorStoreInterface $vectorStore;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productCollectionFactory;

    /**
     * @param EmbeddingProviderInterface $embeddingProvider
     * @param VectorStoreInterface $vectorStore
     * @param Config $config
     * @param CollectionFactory $productCollectionFactory
     */
    public function __construct(
        EmbeddingProviderInterface $embeddingProvider,
        VectorStoreInterface $vectorStore,
        Config $config,
        CollectionFactory $productCollectionFactory
    ) {
        $this->embeddingProvider = $embeddingProvider;
        $this->vectorStore = $vectorStore;
        $this->config = $config;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * Build the full health report.
     *
     * @return array
     */
    public function gather(): array
    {
        $providerAvailable = $this->safeBool(fn () => $this->embeddingProvider->isAvailable());
        $storeReachable = $this->safeBool(fn () => $this->vectorStore->ping());

        $indexed = $storeReachable
            ? $this->safeInt(fn () => $this->vectorStore->count($this->config->getCollectionName()))
            : 0;
        $total = $this->safeInt(fn () => (int) $this->productCollectionFactory->create()->getSize());

        return $this->buildReport(
            $this->embeddingProvider->getName(),
            $providerAvailable,
            $this->vectorStore->getName(),
            $storeReachable,
            $indexed,
            $total
        );
    }

    /**
     * Assemble the report and overall status from collected facts (pure).
     *
     * @param string $providerName
     * @param bool $providerAvailable
     * @param string $storeName
     * @param bool $storeReachable
     * @param int $indexed
     * @param int $total
     * @return array
     */
    public function buildReport(
        string $providerName,
        bool $providerAvailable,
        string $storeName,
        bool $storeReachable,
        int $indexed,
        int $total
    ): array {
        $percent = $this->coveragePercent($indexed, $total);
        return [
            'status' => $this->overallStatus($providerAvailable, $storeReachable, $percent),
            'embedding_provider' => ['name' => $providerName, 'available' => $providerAvailable],
            'vector_store' => ['name' => $storeName, 'reachable' => $storeReachable],
            'coverage' => [
                'indexed' => $indexed,
                'total' => $total,
                'percent' => $percent,
            ],
        ];
    }

    /**
     * Coverage percentage (0.0 when there are no products).
     *
     * @param int $indexed
     * @param int $total
     * @return float
     */
    public function coveragePercent(int $indexed, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }
        return round(min($indexed, $total) / $total * 100, 1);
    }

    /**
     * Overall status: red if a backend is down, yellow if under-indexed, else green.
     *
     * @param bool $providerAvailable
     * @param bool $storeReachable
     * @param float $coveragePercent
     * @return string
     */
    public function overallStatus(bool $providerAvailable, bool $storeReachable, float $coveragePercent): string
    {
        if (!$providerAvailable || !$storeReachable) {
            return self::STATUS_RED;
        }
        if ($coveragePercent < self::HEALTHY_COVERAGE) {
            return self::STATUS_YELLOW;
        }
        return self::STATUS_GREEN;
    }

    /**
     * Run a callback returning bool, swallowing backend errors as false.
     *
     * @param callable $callback
     * @return bool
     */
    private function safeBool(callable $callback): bool
    {
        try {
            return (bool) $callback();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Run a callback returning int, swallowing backend errors as 0.
     *
     * @param callable $callback
     * @return int
     */
    private function safeInt(callable $callback): int
    {
        try {
            return (int) $callback();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
