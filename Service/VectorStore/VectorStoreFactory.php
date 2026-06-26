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

use Magento\Framework\ObjectManagerInterface;
use NavinDBhudiya\ProductRecommendation\Api\VectorStoreInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;

/**
 * Selects the active vector-store backend by admin config and proxies the contract to it.
 *
 * Mirrors EmbeddingProviderFactory: it implements VectorStoreInterface so the rest of the
 * module can depend on the interface while the concrete backend (chromadb | search_engine)
 * is chosen at runtime. Defaults to ChromaDB for full backward compatibility.
 */
class VectorStoreFactory implements VectorStoreInterface
{
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var array
     */
    private array $backends;

    /**
     * @var VectorStoreInterface|null
     */
    private ?VectorStoreInterface $current = null;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param Config $config
     * @param array $backends Map of backend code => class name.
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Config $config,
        array $backends = []
    ) {
        $this->objectManager = $objectManager;
        $this->config = $config;
        $this->backends = $backends;
    }

    /**
     * Resolve the configured backend instance (defaults to chromadb).
     *
     * @return VectorStoreInterface
     */
    public function getBackend(): VectorStoreInterface
    {
        if ($this->current === null) {
            $code = $this->config->getVectorStoreBackend();
            if (!isset($this->backends[$code])) {
                $code = 'chromadb';
            }
            if (!isset($this->backends[$code])) {
                throw new \InvalidArgumentException(
                    sprintf('Vector store backend "%s" is not configured', $code)
                );
            }
            $this->current = $this->objectManager->get($this->backends[$code]);
        }
        return $this->current;
    }

    /**
     * @inheritDoc
     */
    public function upsert(string $collection, array $records): bool
    {
        return $this->getBackend()->upsert($collection, $records);
    }

    /**
     * @inheritDoc
     */
    public function query(string $collection, array $vector, int $k = 10, array $filters = []): array
    {
        return $this->getBackend()->query($collection, $vector, $k, $filters);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $collection, array $ids): bool
    {
        return $this->getBackend()->delete($collection, $ids);
    }

    /**
     * @inheritDoc
     */
    public function count(string $collection): int
    {
        return $this->getBackend()->count($collection);
    }

    /**
     * @inheritDoc
     */
    public function ping(): bool
    {
        return $this->getBackend()->ping();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->getBackend()->getName();
    }

    /**
     * Available backend codes.
     *
     * @return string[]
     */
    public function getAvailableBackends(): array
    {
        return array_keys($this->backends);
    }
}
