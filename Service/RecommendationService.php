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

namespace NavinDBhudiya\ProductRecommendation\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use NavinDBhudiya\ProductRecommendation\Api\Data\LlmRankingInterfaceFactory;
use NavinDBhudiya\ProductRecommendation\Api\Data\RecommendationResultInterface;
use NavinDBhudiya\ProductRecommendation\Api\Data\RecommendationResultInterfaceFactory;
use NavinDBhudiya\ProductRecommendation\Api\EmbeddingProviderInterface;
use NavinDBhudiya\ProductRecommendation\Api\LlmRankingRepositoryInterface;
use NavinDBhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use NavinDBhudiya\ProductRecommendation\Api\VectorStoreInterface;
use NavinDBhudiya\ProductRecommendation\Helper\Config;
use NavinDBhudiya\ProductRecommendation\Model\Cache\Type\Recommendation as RecommendationCache;
use NavinDBhudiya\ProductRecommendation\Service\Fallback\FallbackProvider;
use NavinDBhudiya\ProductRecommendation\Service\Fallback\FallbackSelector;
use NavinDBhudiya\ProductRecommendation\Service\VectorStore\ColumnarConverter;
use Psr\Log\LoggerInterface;

/**
 * Service for getting AI-powered product recommendations
 */
class RecommendationService implements RecommendationServiceInterface
{
    private const CACHE_PREFIX = 'ai_rec_';

    /**
     * Static flag to prevent recursion
     *
     * @var bool
     */
    private static bool $isProcessing = false;

    /**
     * In-memory cache to avoid repeated database calls
     *
     * @var array
     */
    private static array $memoryCache = [];

    /**
     * @var ChromaClient
     */
    private ChromaClient $chromaClient;

    /**
     * @var EmbeddingProviderInterface
     */
    private EmbeddingProviderInterface $embeddingProvider;

    /**
     * @var ProductTextBuilder
     */
    private ProductTextBuilder $textBuilder;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var RecommendationResultInterfaceFactory
     */
    private RecommendationResultInterfaceFactory $resultFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var StockHelper
     */
    private StockHelper $stockHelper;

    /**
     * @var LlmReRanker|null
     */
    private ?LlmReRanker $llmReRanker;

    /**
     * @var LlmRankingRepositoryInterface|null
     */
    private ?LlmRankingRepositoryInterface $llmRankingRepository;

    /**
     * @var LlmRankingInterfaceFactory|null
     */
    private ?LlmRankingInterfaceFactory $llmRankingFactory;

    /**
     * @var CustomerSession|null
     */
    private ?CustomerSession $customerSession;

    /**
     * @var CheckoutSession|null
     */
    private ?CheckoutSession $checkoutSession;

    /**
     * @var DateTime|null
     */
    private ?DateTime $dateTime;

    /**
     * @var VectorStoreInterface|null
     */
    private ?VectorStoreInterface $vectorStore;

    /**
     * @var ColumnarConverter|null
     */
    private ?ColumnarConverter $columnarConverter;

    /**
     * @var FallbackSelector|null
     */
    private ?FallbackSelector $fallbackSelector;

    /**
     * @var FallbackProvider|null
     */
    private ?FallbackProvider $fallbackProvider;

    /**
     * @param ChromaClient $chromaClient
     * @param EmbeddingProviderInterface $embeddingProvider
     * @param ProductTextBuilder $textBuilder
     * @param ProductRepositoryInterface $productRepository
     * @param CollectionFactory $productCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param RecommendationResultInterfaceFactory $resultFactory
     * @param LoggerInterface $logger
     * @param StockHelper $stockHelper
     * @param LlmReRanker|null $llmReRanker
     * @param LlmRankingRepositoryInterface $llmRankingRepository
     * @param LlmRankingInterfaceFactory $llmRankingFactory
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param DateTime $dateTime
     * @param VectorStoreInterface|null $vectorStore
     * @param ColumnarConverter|null $columnarConverter
     * @param FallbackSelector|null $fallbackSelector
     * @param FallbackProvider|null $fallbackProvider
     */
    public function __construct(
        ChromaClient $chromaClient,
        EmbeddingProviderInterface $embeddingProvider,
        ProductTextBuilder $textBuilder,
        ProductRepositoryInterface $productRepository,
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        Config $config,
        CacheInterface $cache,
        SerializerInterface $serializer,
        RecommendationResultInterfaceFactory $resultFactory,
        LoggerInterface $logger,
        StockHelper $stockHelper,
        ?LlmReRanker $llmReRanker = null,
        ?LlmRankingRepositoryInterface $llmRankingRepository = null,
        ?LlmRankingInterfaceFactory $llmRankingFactory = null,
        ?CustomerSession $customerSession = null,
        ?CheckoutSession $checkoutSession = null,
        ?DateTime $dateTime = null,
        ?VectorStoreInterface $vectorStore = null,
        ?ColumnarConverter $columnarConverter = null,
        ?FallbackSelector $fallbackSelector = null,
        ?FallbackProvider $fallbackProvider = null
    ) {
        $this->chromaClient = $chromaClient;
        $this->embeddingProvider = $embeddingProvider;
        $this->textBuilder = $textBuilder;
        $this->productRepository = $productRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->resultFactory = $resultFactory;
        $this->logger = $logger;
        $this->stockHelper = $stockHelper;
        $this->llmReRanker = $llmReRanker;
        $this->llmRankingRepository = $llmRankingRepository;
        $this->llmRankingFactory = $llmRankingFactory;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->dateTime = $dateTime;
        $this->vectorStore = $vectorStore;
        $this->columnarConverter = $columnarConverter;
        $this->fallbackSelector = $fallbackSelector;
        $this->fallbackProvider = $fallbackProvider;
    }

    /**
     * @inheritDoc
     */
    public function getRelatedProducts($product, ?int $limit = null, ?int $storeId = null): array
    {
        // Prevent infinite recursion
        if (self::$isProcessing) {
            return [];
        }

        if (!$this->config->isEnabled($storeId) || !$this->config->isRelatedEnabled($storeId)) {
            return [];
        }

        try {
            self::$isProcessing = true;
            
            $limit = $limit ?? $this->config->getRelatedCount($storeId);
            $results = $this->getRecommendationsWithScores($product, self::TYPE_RELATED, $limit, $storeId);

            $products = array_map(fn($result) => $result->getProduct(), $results);
            $products = $this->applyFallback($product, $products, self::TYPE_RELATED, $limit, $storeId);

            self::$isProcessing = false;
            return $products;
        } catch (\Exception $e) {
            self::$isProcessing = false;
            $this->log('getRelatedProducts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getCrossSellProducts($product, ?int $limit = null, ?int $storeId = null): array
    {
        // Prevent infinite recursion
        if (self::$isProcessing) {
            return [];
        }

        if (!$this->config->isEnabled($storeId) || !$this->config->isCrossSellEnabled($storeId)) {
            return [];
        }

        try {
            self::$isProcessing = true;
            
            $limit = $limit ?? $this->config->getCrossSellCount($storeId);
            $results = $this->getRecommendationsWithScores($product, self::TYPE_CROSSSELL, $limit, $storeId);

            $products = array_map(fn($result) => $result->getProduct(), $results);
            $products = $this->applyFallback($product, $products, self::TYPE_CROSSSELL, $limit, $storeId);

            self::$isProcessing = false;
            return $products;
        } catch (\Exception $e) {
            self::$isProcessing = false;
            $this->log('getCrossSellProducts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getUpSellProducts($product, ?int $limit = null, ?int $storeId = null): array
    {
        // Prevent infinite recursion
        if (self::$isProcessing) {
            return [];
        }

        if (!$this->config->isEnabled($storeId) || !$this->config->isUpSellEnabled($storeId)) {
            return [];
        }

        try {
            self::$isProcessing = true;
            
            $limit = $limit ?? $this->config->getUpSellCount($storeId);
            $results = $this->getRecommendationsWithScores($product, self::TYPE_UPSELL, $limit, $storeId);

            $products = array_map(fn($result) => $result->getProduct(), $results);
            $products = $this->applyFallback($product, $products, self::TYPE_UPSELL, $limit, $storeId);

            self::$isProcessing = false;
            return $products;
        } catch (\Exception $e) {
            self::$isProcessing = false;
            $this->log('getUpSellProducts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getSimilarProductsByQuery(string $query, int $limit = 10, ?int $storeId = null): array
    {
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }

        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            
            // Generate embedding for query
            $queryEmbedding = $this->embeddingProvider->generateEmbedding($query);
            
            if (empty($queryEmbedding)) {
                $this->log('Failed to generate embedding for query');
                return [];
            }
            
            $collectionName = $this->config->getCollectionName();

            // Query the configured vector store with embeddings
            $queryResult = $this->queryVector($collectionName, $queryEmbedding, $limit + 5, []);

            return $this->processQueryResults($queryResult, $limit, $storeId);
        } catch (\Exception $e) {
            $this->log('Query by text failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getRecommendationsWithScores(
        $product,
        string $type = self::TYPE_RELATED,
        ?int $limit = null,
        ?int $storeId = null
    ): array {
        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            $product = $this->resolveProduct($product, $storeId);

            if (!$product) {
                return [];
            }

            $productId = (int) $product->getId();
            $limit = $limit ?? $this->getDefaultLimit($type, $storeId);

            // Get customer ID if logged in
            $customerId = null;
            if ($this->customerSession && $this->customerSession->isLoggedIn()) {
                $customerId = (int) $this->customerSession->getCustomerId();
            }

            // LAYER 1: Check Database (for logged-in customers only)
            if ($customerId && $this->llmRankingRepository) {
                try {
                    $dbRanking = $this->llmRankingRepository->getByProductAndCustomer(
                        $productId,
                        $type,
                        $customerId,
                        $storeId
                    );

                    if ($dbRanking && !$dbRanking->isExpired()) {
                        $this->log('🗄️ [DATABASE HIT] Returning stored LLM rankings', [
                            'product_id' => $productId,
                            'customer_id' => $customerId,
                            'type' => $type,
                            'created_at' => $dbRanking->getCreatedAt(),
                            'expires_at' => $dbRanking->getExpiresAt(),
                            'cost_saved' => '$0.018 (no API call)'
                        ]);

                        $rankedIds = $dbRanking->getRankedProductIds();
                        return $this->hydrateFromProductIds($rankedIds, $type, $limit);
                    }

                    $this->log('🔍 [DATABASE MISS] No valid ranking in database for customer', [
                        'customer_id' => $customerId,
                        'product_id' => $productId
                    ]);
                } catch (\Exception $e) {
                    $this->log('❌ Database check error: ' . $e->getMessage());
                }
            }

            // LAYER 2: Check Cache
            $cacheKey = $this->getCacheKey($productId, $type, $storeId);
            if ($this->config->isCacheEnabled()) {
                $cached = $this->cache->load($cacheKey);
                if ($cached) {
                    $this->log('💾 [CACHE HIT] Returning cached rankings', [
                        'product_id' => $productId,
                        'type' => $type,
                        'cache_key' => $cacheKey,
                        'cost_saved' => '$0.018 (no API call)'
                    ]);
                    $cachedData = $this->serializer->unserialize($cached);
                    return $this->hydrateResults($cachedData, $type, $limit);
                }
                $this->log('🔍 [CACHE MISS] Will generate new recommendations', [
                    'product_id' => $productId,
                    'type' => $type
                ]);
            }

            // Build query text from product
            $queryText = $this->textBuilder->buildText($product, $storeId);

            if (empty($queryText)) {
                $this->log('Empty query text for product ' . $productId);
                return [];
            }

            // Generate embedding for query
            $this->log('Generating embedding for product ' . $productId);
            $queryEmbedding = $this->embeddingProvider->generateEmbedding($queryText);

            if (empty($queryEmbedding)) {
                $this->log('Failed to generate embedding for product ' . $productId . '. Check embedding service!');
                return [];
            }

            $this->log('Embedding generated successfully, dimension: ' . count($queryEmbedding));

            // Get collection
            $collectionName = $this->config->getCollectionName();

            // Build filter
            $where = $this->buildWhereFilter($product, $type, $storeId);

            // Query the configured vector store (NOT query_texts!)
            $nResults = $limit + 10; // Get extra to account for filtering
            $queryResult = $this->queryVector($collectionName, $queryEmbedding, $nResults, $where);

            // Process results
            $results = $this->processResults($queryResult, $product, $type, $limit, $storeId);

            // SAVE RESULTS (Hybrid Storage: Database + Cache)
            if (!empty($results)) {
                $rankedProductIds = array_map(fn($r) => (int)$r->getProduct()->getId(), $results);

                // LAYER 1: Save to DATABASE for logged-in customers
                if ($customerId && $this->llmRankingRepository && $this->llmRankingFactory && $this->dateTime) {
                    try {
                        $timestamp = $this->dateTime->gmtTimestamp() + $this->config->getCacheLifetime();
                        $expiresAt = gmdate('Y-m-d H:i:s', $timestamp);

                        /** @var \NavinDBhudiya\ProductRecommendation\Api\Data\LlmRankingInterface $ranking */
                        $ranking = $this->llmRankingFactory->create();
                        $ranking->setCustomerId($customerId)
                            ->setProductId($productId)
                            ->setRecommendationType($type)
                            ->setStoreId($storeId)
                            ->setRankedProductIds($rankedProductIds)
                            ->setRankingMetadata([
                                'generated_at' => gmdate('Y-m-d H:i:s'),
                                'result_count' => count($results),
                                'llm_enabled' => $this->config->isLlmRerankingEnabled($storeId)
                            ])
                            ->setExpiresAt($expiresAt);

                        // Extract LLM metadata from results if available
                        $metadata = $results[0]->getMetadata() ?? [];
                        if (isset($metadata['llm_model'])) {
                            $ranking->setModelUsed($metadata['llm_model']);
                        }
                        if (isset($metadata['llm_cost'])) {
                            $ranking->setEstimatedCost((float)$metadata['llm_cost']);
                        }

                        $this->llmRankingRepository->save($ranking);

                        $this->log('🗄️ [DATABASE SAVED] Persisted customer rankings', [
                            'customer_id' => $customerId,
                            'product_id' => $productId,
                            'type' => $type,
                            'ranking_count' => count($rankedProductIds),
                            'expires_at' => $expiresAt,
                            'model_used' => $ranking->getModelUsed(),
                            'estimated_cost' => $ranking->getEstimatedCost(),
                            'note' => 'Future visits will use database (no API cost!)'
                        ]);
                    } catch (\Exception $e) {
                        $this->log('❌ Failed to save to database: ' . $e->getMessage());
                    }
                }

                // LAYER 2: Save to CACHE (benefits both guests and logged-in users)
                if ($this->config->isCacheEnabled()) {
                    $cacheData = $this->dehydrateResults($results);
                    $this->cache->save(
                        $this->serializer->serialize($cacheData),
                        $cacheKey,
                        [RecommendationCache::CACHE_TAG],
                        $this->config->getCacheLifetime()
                    );
                    $this->log('💾 [CACHE SAVED] Stored in cache', [
                        'product_id' => $productId,
                        'type' => $type,
                        'cache_key' => $cacheKey,
                        'result_count' => count($results),
                        'cache_lifetime' => $this->config->getCacheLifetime() . ' seconds',
                        'note' => $customerId ? 'Cache serves as fallback for database' : 'Cache serves guest users'
                    ]);
                }
            }

            return $results;
        } catch (\Exception $e) {
            $this->log('Get recommendations failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function hasAiRecommendations(int $productId): bool
    {
        try {
            $collectionName = $this->config->getCollectionName();
            $collectionId = $this->chromaClient->getCollectionId($collectionName);

            $documents = $this->chromaClient->getDocuments($collectionId, ['product_' . $productId]);

            return !empty($documents['ids'] ?? []);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function clearCache(int $productId): void
    {
        foreach ([self::TYPE_RELATED, self::TYPE_CROSSSELL, self::TYPE_UPSELL] as $type) {
            $pattern = self::CACHE_PREFIX . $productId . '_' . $type . '_*';
            // Clear all store variations
            for ($storeId = 0; $storeId <= 100; $storeId++) {
                $cacheKey = $this->getCacheKey($productId, $type, $storeId);
                $this->cache->remove($cacheKey);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function clearAllCache(): void
    {
        $this->cache->clean([RecommendationCache::CACHE_TAG]);
    }

    /**
     * Resolve product from ID or instance
     *
     * @param ProductInterface|int $product
     * @param int $storeId
     * @return ProductInterface|null
     */
    private function resolveProduct($product, int $storeId): ?ProductInterface
    {
        if ($product instanceof ProductInterface) {
            return $product;
        }

        try {
            return $this->productRepository->getById((int) $product, false, $storeId);
        } catch (\Exception $e) {
            $this->log('Failed to load product: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get default limit for type
     *
     * @param string $type
     * @param int|null $storeId
     * @return int
     */
    private function getDefaultLimit(string $type, ?int $storeId): int
    {
        return match ($type) {
            self::TYPE_CROSSSELL => $this->config->getCrossSellCount($storeId),
            self::TYPE_UPSELL => $this->config->getUpSellCount($storeId),
            default => $this->config->getRelatedCount($storeId),
        };
    }

    /**
     * Build where filter for ChromaDB query
     *
     * @param ProductInterface $product
     * @param string $type
     * @param int $storeId
     * @return array
     */
    private function buildWhereFilter(ProductInterface $product, string $type, int $storeId): array
    {
        $conditions = [];

        // Exclude current product
        $conditions[] = ['product_id' => ['$ne' => (int) $product->getId()]];

        // Add store filter
        $conditions[] = ['store_id' => $storeId];

        // ChromaDB requires $and operator for multiple conditions
        if (count($conditions) > 1) {
            return ['$and' => $conditions];
        }

        // Single condition doesn't need $and
        return $conditions[0] ?? [];
    }

    /**
     * Process ChromaDB query results
     *
     * @param array $queryResult
     * @param ProductInterface $sourceProduct
     * @param string $type
     * @param int $limit
     * @param int $storeId
     * @return RecommendationResultInterface[]
     */
    private function processResults(
        array $queryResult,
        ProductInterface $sourceProduct,
        string $type,
        int $limit,
        int $storeId
    ): array {
        $results = [];

        if (empty($queryResult['ids'][0] ?? [])) {
            return $results;
        }

        $ids = $queryResult['ids'][0];
        $distances = $queryResult['distances'][0] ?? [];
        $metadatas = $queryResult['metadatas'][0] ?? [];

        // Get cart product IDs to exclude
        $cartProductIds = $this->getCartProductIds();

        $productIds = [];
        $idDistanceMap = [];

        foreach ($ids as $index => $id) {
            // Extract product ID from document ID (format: product_{id})
            $productId = $this->extractProductId($id);
            // Exclude current product AND cart products
            if ($productId
                && $productId !== (int) $sourceProduct->getId()
                && !in_array($productId, $cartProductIds, true)
            ) {
                $productIds[] = $productId;
                $idDistanceMap[$productId] = $distances[$index] ?? 0;
            }
        }

        if (empty($productIds)) {
            return $results;
        }

        // Load products
        $products = $this->loadProducts($productIds, $storeId, $type, $sourceProduct);

        // Build results with scores
        $threshold = $this->config->getSimilarityThreshold($storeId);

        foreach ($products as $product) {
            $productId = (int) $product->getId();
            $distance = $idDistanceMap[$productId] ?? 1;
            $score = $this->distanceToScore($distance);

            if ($score < $threshold) {
                continue;
            }

            /** @var RecommendationResultInterface $result */
            $result = $this->resultFactory->create();
            $result->setProduct($product)
                ->setScore($score)
                ->setDistance($distance)
                ->setType($type)
                ->setMetadata([
                    'source_product_id' => $sourceProduct->getId(),
                    'store_id' => $storeId,
                ]);

            $results[] = $result;

            if (count($results) >= $limit) {
                break;
            }
        }

        // DIAGNOSTIC: Log before LLM re-ranking check
        $this->log('🔍 [DIAGNOSTIC] Checking LLM re-ranking conditions', [
            'has_llmReRanker' => $this->llmReRanker !== null,
            'llmReRanker_class' => $this->llmReRanker ? get_class($this->llmReRanker) : 'NULL',
            'is_llm_enabled' => $this->config->isLlmRerankingEnabled($storeId),
            'has_results' => !empty($results),
            'result_count' => count($results),
            'store_id' => $storeId,
            'recommendation_type' => $type
        ]);

        // Apply LLM re-ranking if enabled
        if ($this->llmReRanker && $this->config->isLlmRerankingEnabled($storeId) && !empty($results)) {
            $this->log('✅ [DIAGNOSTIC] All conditions met - Calling LLM re-ranking!');
            try {
                $results = $this->llmReRanker->rerank(
                    $sourceProduct,
                    $results,
                    $type,
                    null, // customerId - could be passed in future
                    $limit,
                    $storeId
                );
            } catch (\Exception $e) {
                $this->log('❌ LLM re-ranking failed, using vector similarity results: ' . $e->getMessage());
                // Continue with original results if re-ranking fails
            }
        } else {
            $this->log('⚠️  [DIAGNOSTIC] LLM re-ranking skipped - condition failed', [
                'llmReRanker_is_null' => $this->llmReRanker === null,
                'llm_enabled' => $this->config->isLlmRerankingEnabled($storeId),
                'results_empty' => empty($results)
            ]);
        }

        return $results;
    }

    /**
     * Process query results for text-based search
     *
     * @param array $queryResult
     * @param int $limit
     * @param int $storeId
     * @return ProductInterface[]
     */
    private function processQueryResults(array $queryResult, int $limit, int $storeId): array
    {
        if (empty($queryResult['ids'][0] ?? [])) {
            return [];
        }

        $productIds = [];
        foreach ($queryResult['ids'][0] as $id) {
            $productId = $this->extractProductId($id);
            if ($productId) {
                $productIds[] = $productId;
            }
        }

        if (empty($productIds)) {
            return [];
        }

        return $this->loadProducts($productIds, $storeId);
    }

    /**
     * Load products with filters
     *
     * @param array $productIds
     * @param int $storeId
     * @param string|null $type
     * @param ProductInterface|null $sourceProduct
     * @return ProductInterface[]
     */
    private function loadProducts(
        array $productIds,
        int $storeId,
        ?string $type = null,
        ?ProductInterface $sourceProduct = null
    ): array {
        /** @var Collection $collection */
        $collection = $this->productCollectionFactory->create();
        $collection->addIdFilter($productIds)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect(['name', 'price', 'small_image', 'url_key'])
            ->addFieldToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addFieldToFilter('visibility', ['in' => [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_BOTH,
            ]]);

        // Add stock filter
        $this->stockHelper->addInStockFilterToCollection($collection);

        // Apply type-specific filters
        if ($type && $sourceProduct) {
            $this->applyTypeFilters($collection, $type, $sourceProduct, $storeId);
        }

        // Maintain original order
        $collection->getSelect()->order(
            new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $productIds) . ')')
        );

        return $collection->getItems();
    }

    /**
     * Apply type-specific filters to collection
     *
     * @param Collection $collection
     * @param string $type
     * @param ProductInterface $sourceProduct
     * @param int $storeId
     * @return void
     */
    private function applyTypeFilters(
        Collection $collection,
        string $type,
        ProductInterface $sourceProduct,
        int $storeId
    ): void {
        switch ($type) {
            case self::TYPE_CROSSSELL:
                if ($this->config->excludeSameCategoryForCrossSell($storeId)) {
                    // Exclude products from same categories
                    if ($sourceProduct instanceof Product) {
                        $categoryIds = $sourceProduct->getCategoryIds();
                        if (!empty($categoryIds)) {
                            // This is a simplified approach - for better performance,
                            // you might want to use a more sophisticated category filter
                        }
                    }
                }
                break;

            case self::TYPE_UPSELL:
                $threshold = $this->config->getUpSellPriceThreshold($storeId);
                if ($threshold > 0 && $sourceProduct->getPrice()) {
                    $minPrice = $sourceProduct->getPrice() * (1 + $threshold / 100);
                    $collection->addFieldToFilter('price', ['gteq' => $minPrice]);
                }
                break;
        }
    }

    /**
     * Extract product ID from ChromaDB document ID
     *
     * @param string $documentId
     * @return int|null
     */
    private function extractProductId(string $documentId): ?int
    {
        if (preg_match('/^product_(\d+)(?:_\d+)?$/', $documentId, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * Convert distance to similarity score
     *
     * @param float $distance
     * @return float Score between 0 and 1
     */
    private function distanceToScore(float $distance): float
    {
        // ChromaDB uses L2 distance by default
        // Convert to similarity score: score = 1 / (1 + distance)
        return 1 / (1 + $distance);
    }

    /**
     * Get cache key
     *
     * @param int $productId
     * @param string $type
     * @param int $storeId
     * @return string
     */
    private function getCacheKey(int $productId, string $type, int $storeId): string
    {
        return self::CACHE_PREFIX . $productId . '_' . $type . '_' . $storeId;
    }

    /**
     * Dehydrate results for caching
     *
     * @param RecommendationResultInterface[] $results
     * @return array
     */
    private function dehydrateResults(array $results): array
    {
        $data = [];
        foreach ($results as $result) {
            $data[] = [
                'product_id' => $result->getProduct()->getId(),
                'score' => $result->getScore(),
                'distance' => $result->getDistance(),
                'type' => $result->getType(),
                'metadata' => $result->getMetadata(),
            ];
        }
        return $data;
    }

    /**
     * Hydrate results from cache
     *
     * @param array $cachedData
     * @param string $type
     * @param int $limit
     * @return RecommendationResultInterface[]
     */
    private function hydrateResults(array $cachedData, string $type, int $limit): array
    {
        if (empty($cachedData)) {
            return [];
        }

        // Collect product IDs
        $productIds = [];
        $dataByProductId = [];
        
        foreach ($cachedData as $item) {
            if (count($productIds) >= $limit) {
                break;
            }
            $productId = (int) $item['product_id'];
            $productIds[] = $productId;
            $dataByProductId[$productId] = $item;
        }

        if (empty($productIds)) {
            return [];
        }

        // Load all products in one query using collection
        try {
            $collection = $this->productCollectionFactory->create();
            $collection->addIdFilter($productIds)
                ->addAttributeToSelect(['name', 'sku', 'price', 'small_image', 'url_key'])
                ->addAttributeToFilter(
                    'status',
                    \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
                );
            
            // Maintain order
            $collection->getSelect()->order(
                new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $productIds) . ')')
            );

            $results = [];
            foreach ($collection->getItems() as $product) {
                $productId = (int) $product->getId();
                if (!isset($dataByProductId[$productId])) {
                    continue;
                }
                
                $item = $dataByProductId[$productId];
                
                /** @var RecommendationResultInterface $result */
                $result = $this->resultFactory->create();
                $result->setProduct($product)
                    ->setScore((float) ($item['score'] ?? 0))
                    ->setDistance((float) ($item['distance'] ?? 0))
                    ->setType($type)
                    ->setMetadata($item['metadata'] ?? []);

                $results[] = $result;
            }

            return $results;
        } catch (\Exception $e) {
            $this->log('hydrateResults error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Hydrate results from product IDs (database storage)
     *
     * @param array $productIds
     * @param string $type
     * @param int $limit
     * @return RecommendationResultInterface[]
     */
    private function hydrateFromProductIds(array $productIds, string $type, int $limit): array
    {
        if (empty($productIds)) {
            return [];
        }

        // Limit to requested count
        $productIds = array_slice($productIds, 0, $limit);

        // Convert to cache format for reuse
        $cachedData = [];
        foreach ($productIds as $productId) {
            $cachedData[] = [
                'product_id' => $productId,
                'score' => 0.0, // Score not stored in DB, only ranking order
                'distance' => 0.0,
                'type' => $type,
                'metadata' => []
            ];
        }

        return $this->hydrateResults($cachedData, $type, $limit);
    }

    /**
     * Get cart product IDs to exclude from recommendations
     *
     * @return array
     */
    private function getCartProductIds(): array
    {
        $cartProductIds = [];

        if (!$this->checkoutSession) {
            return $cartProductIds;
        }

        try {
            $quote = $this->checkoutSession->getQuote();
            if ($quote && $quote->getId()) {
                $cartItems = $quote->getAllVisibleItems();
                foreach ($cartItems as $item) {
                    $product = $item->getProduct();
                    if ($product && $product->getId()) {
                        $cartProductIds[] = (int) $product->getId();
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail if cart is unavailable
            if ($this->config->isDebugMode()) {
                $this->logger->debug('[ProductRecommendation] Error getting cart products: ' . $e->getMessage());
            }
        }

        return $cartProductIds;
    }

    /**
     * Query the configured vector store, returning ChromaDB-columnar results.
     *
     * Uses VectorStoreInterface when wired (so the search-engine backend works while ChromaDB
     * behaviour is preserved through its adapter); otherwise the legacy direct ChromaClient call.
     *
     * @param string $collection
     * @param array $embedding
     * @param int $nResults
     * @param array $where
     * @return array
     */
    private function queryVector(string $collection, array $embedding, int $nResults, array $where): array
    {
        if ($this->vectorStore !== null && $this->columnarConverter !== null) {
            $matches = $this->vectorStore->query($collection, $embedding, $nResults, $where);
            return $this->columnarConverter->toColumnar($matches);
        }
        $collectionId = $this->chromaClient->getCollectionId($collection);
        return $this->chromaClient->query($collectionId, [], $nResults, $where, [], [$embedding]);
    }

    /**
     * Top up a result set so the recommendation block never renders empty.
     *
     * Chooses the tier via FallbackSelector and logs served_by. No-op (returns input unchanged)
     * when the fallback services are not wired.
     *
     * @param ProductInterface|int $product
     * @param ProductInterface[] $products
     * @param string $type
     * @param int $limit
     * @param int|null $storeId
     * @return ProductInterface[]
     */
    private function applyFallback($product, array $products, string $type, int $limit, ?int $storeId): array
    {
        if ($this->fallbackSelector === null || $this->fallbackProvider === null) {
            return $products;
        }

        $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
        $count = count($products);
        $nativeEnabled = $this->config->isFallbackToNativeEnabled($storeId);

        $decision = $this->fallbackSelector->select(
            true,
            $count === 0,
            $count,
            max(1, $limit),
            true,
            $nativeEnabled
        );

        if ($decision['served_by'] === FallbackSelector::SERVED_BY_PRIMARY) {
            return $products;
        }

        $source = $this->resolveProduct($product, $storeId);
        if ($source === null) {
            return $products;
        }

        $excludeIds = array_map(static fn ($p) => (int) $p->getId(), $products);
        $allowNative = $nativeEnabled || $decision['served_by'] === FallbackSelector::SERVED_BY_NATIVE;
        $fill = $this->fallbackProvider->fill($source, $limit - $count, $excludeIds, $allowNative, $storeId);

        $this->log('🛟 [FALLBACK] Block topped up to stay populated', [
            'served_by' => $decision['served_by'],
            'reason' => $decision['reason'],
            'type' => $type,
            'primary_count' => $count,
            'filled' => count($fill),
        ]);

        return array_slice(array_merge($products, $fill), 0, $limit);
    }

    /**
     * Log message if debug mode is enabled
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    private function log(string $message, array $context = []): void
    {
        if ($this->config->isDebugMode()) {
            $this->logger->debug('[ProductRecommendation] ' . $message, $context);
        }
    }
}
