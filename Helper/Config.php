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

namespace NavinDBhudiya\ProductRecommendation\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Configuration helper
 */
class Config extends AbstractHelper
{
    private const XML_PATH_GENERAL_ENABLED = 'product_recommendation/general/enabled';
    private const XML_PATH_GENERAL_DEBUG = 'product_recommendation/general/debug_mode';

    private const XML_PATH_CHROMADB_HOST = 'product_recommendation/chromadb/host';
    private const XML_PATH_CHROMADB_PORT = 'product_recommendation/chromadb/port';
    private const XML_PATH_CHROMADB_COLLECTION = 'product_recommendation/chromadb/collection_name';

    private const XML_PATH_EMBEDDING_PROVIDER = 'product_recommendation/embedding/provider';
    private const XML_PATH_EMBEDDING_ATTRIBUTES = 'product_recommendation/embedding/product_attributes';
    private const XML_PATH_EMBEDDING_CATEGORIES = 'product_recommendation/embedding/include_categories';

    private const XML_PATH_RELATED_ENABLED = 'product_recommendation/recommendation/related_enabled';
    private const XML_PATH_RELATED_COUNT = 'product_recommendation/recommendation/related_count';
    private const XML_PATH_CROSSSELL_ENABLED = 'product_recommendation/recommendation/crosssell_enabled';
    private const XML_PATH_CROSSSELL_COUNT = 'product_recommendation/recommendation/crosssell_count';
    private const XML_PATH_UPSELL_ENABLED = 'product_recommendation/recommendation/upsell_enabled';
    private const XML_PATH_UPSELL_COUNT = 'product_recommendation/recommendation/upsell_count';
    private const XML_PATH_SIMILARITY_THRESHOLD = 'product_recommendation/recommendation/similarity_threshold';
    private const XML_PATH_EXCLUDE_SAME_CATEGORY = 'product_recommendation/recommendation/exclude_same_category';
    private const XML_PATH_UPSELL_PRICE_THRESHOLD = 'product_recommendation/recommendation/upsell_price_threshold';
    private const XML_PATH_FALLBACK_NATIVE = 'product_recommendation/recommendation/fallback_to_native';

    private const XML_PATH_CACHE_ENABLED = 'product_recommendation/cache/enabled';
    private const XML_PATH_CACHE_LIFETIME = 'product_recommendation/cache/lifetime';

    // Personalized Recommendations
    private const XML_PATH_PERSONALIZED_BROWSING_ENABLED = 'product_recommendation/personalized/browsing_enabled';
    private const XML_PATH_PERSONALIZED_BROWSING_LIMIT = 'product_recommendation/personalized/browsing_limit';
    private const XML_PATH_PERSONALIZED_PURCHASE_ENABLED = 'product_recommendation/personalized/purchase_enabled';
    private const XML_PATH_PERSONALIZED_PURCHASE_LIMIT = 'product_recommendation/personalized/purchase_limit';
    private const XML_PATH_PERSONALIZED_WISHLIST_ENABLED = 'product_recommendation/personalized/wishlist_enabled';
    private const XML_PATH_PERSONALIZED_WISHLIST_LIMIT = 'product_recommendation/personalized/wishlist_limit';
    private const XML_PATH_PERSONALIZED_JUSTFORYOU_ENABLED = 'product_recommendation/personalized/justforyou_enabled';
    private const XML_PATH_PERSONALIZED_JUSTFORYOU_LIMIT = 'product_recommendation/personalized/justforyou_limit';
    private const XML_PATH_PERSONALIZED_WEIGHT_WISHLIST = 'product_recommendation/personalized/weight_wishlist';
    private const XML_PATH_PERSONALIZED_WEIGHT_PURCHASE = 'product_recommendation/personalized/weight_purchase';
    private const XML_PATH_PERSONALIZED_WEIGHT_BROWSING = 'product_recommendation/personalized/weight_browsing';
    private const XML_PATH_PERSONALIZED_MIN_HISTORY = 'product_recommendation/personalized/min_history_items';
    private const XML_PATH_PERSONALIZED_SHOW_HOMEPAGE = 'product_recommendation/personalized/show_on_homepage';
    private const XML_PATH_PERSONALIZED_SHOW_CATEGORY = 'product_recommendation/personalized/show_on_category';
    private const XML_PATH_PERSONALIZED_SHOW_PRODUCT = 'product_recommendation/personalized/show_on_product';

    // LLM Re-ranking Configuration
    private const XML_PATH_LLM_ENABLED = 'product_recommendation/llm_reranking/enabled';
    private const XML_PATH_LLM_PROVIDER = 'product_recommendation/llm_reranking/provider';
    private const XML_PATH_LLM_API_KEY = 'product_recommendation/llm_reranking/api_key';
    private const XML_PATH_LLM_MODEL = 'product_recommendation/llm_reranking/model';
    private const XML_PATH_LLM_TEMPERATURE = 'product_recommendation/llm_reranking/temperature';
    private const XML_PATH_LLM_CANDIDATE_COUNT = 'product_recommendation/llm_reranking/candidate_count';

    // Vector store backend (Phase 1)
    private const XML_PATH_VECTOR_BACKEND = 'product_recommendation/vector_store/backend';
    private const XML_PATH_SEARCH_HOST = 'product_recommendation/vector_store/search_host';
    private const XML_PATH_SEARCH_PORT = 'product_recommendation/vector_store/search_port';
    private const XML_PATH_SEARCH_SCHEME = 'product_recommendation/vector_store/search_scheme';
    private const XML_PATH_SEARCH_INDEX_PREFIX = 'product_recommendation/vector_store/index_prefix';

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @param Context $context
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
    }

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GENERAL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_GENERAL_DEBUG);
    }

    /**
     * Get ChromaDB host
     *
     * @return string
     */
    public function getChromaDbHost(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_CHROMADB_HOST) ?: 'chromadb';
    }

    /**
     * Get ChromaDB port
     *
     * @return int
     */
    public function getChromaDbPort(): int
    {
        return (int) ($this->scopeConfig->getValue(self::XML_PATH_CHROMADB_PORT) ?: 8000);
    }

    /**
     * Get ChromaDB collection name
     *
     * @return string
     */
    public function getCollectionName(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_CHROMADB_COLLECTION) ?: 'magento_products';
    }

    /**
     * Get ChromaDB base URL
     *
     * @return string
     */
    public function getChromaDbUrl(): string
    {
        return sprintf('http://%s:%d', $this->getChromaDbHost(), $this->getChromaDbPort());
    }

    /**
     * Get embedding provider
     *
     * @return string
     */
    public function getEmbeddingProvider(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_EMBEDDING_PROVIDER) ?: 'chromadb';
    }

    /**
     * Get the configured vector-store backend (chromadb | search_engine).
     *
     * @return string
     */
    public function getVectorStoreBackend(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_VECTOR_BACKEND) ?: 'chromadb';
    }

    /**
     * Get the OpenSearch/Elasticsearch host for the search-engine vector store.
     *
     * @return string
     */
    public function getSearchHost(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_SEARCH_HOST) ?: 'opensearch';
    }

    /**
     * Get the search-engine port.
     *
     * @return int
     */
    public function getSearchPort(): int
    {
        return (int) ($this->scopeConfig->getValue(self::XML_PATH_SEARCH_PORT) ?: 9200);
    }

    /**
     * Get the search-engine scheme (http | https).
     *
     * @return string
     */
    public function getSearchScheme(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_SEARCH_SCHEME) ?: 'http';
    }

    /**
     * Get the index-name prefix for per-store-view vector indexes.
     *
     * @return string
     */
    public function getSearchIndexPrefix(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_SEARCH_INDEX_PREFIX) ?: 'navin_reco';
    }

    /**
     * Get product attributes for embedding
     *
     * @return array
     */
    public function getProductAttributes(): array
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_EMBEDDING_ATTRIBUTES);
        if (empty($value)) {
            return ['name', 'short_description', 'description', 'meta_keywords'];
        }
        return is_string($value) ? explode(',', $value) : (array) $value;
    }

    /**
     * Check if categories should be included in embedding
     *
     * @return bool
     */
    public function includeCategories(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_EMBEDDING_CATEGORIES);
    }

    /**
     * Check if related products are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isRelatedEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_RELATED_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get related products count
     *
     * @param int|null $storeId
     * @return int
     */
    public function getRelatedCount(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_RELATED_COUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 4;
    }

    /**
     * Check if cross-sell products are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCrossSellEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CROSSSELL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get cross-sell products count
     *
     * @param int|null $storeId
     * @return int
     */
    public function getCrossSellCount(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_CROSSSELL_COUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 4;
    }

    /**
     * Check if up-sell products are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isUpSellEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_UPSELL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get up-sell products count
     *
     * @param int|null $storeId
     * @return int
     */
    public function getUpSellCount(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_UPSELL_COUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 4;
    }

    /**
     * Get similarity threshold
     *
     * @param int|null $storeId
     * @return float
     */
    public function getSimilarityThreshold(?int $storeId = null): float
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_SIMILARITY_THRESHOLD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value !== null ? (float) $value : 0.5;
    }

    /**
     * Check if same category should be excluded for cross-sell
     *
     * @param int|null $storeId
     * @return bool
     */
    public function excludeSameCategoryForCrossSell(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EXCLUDE_SAME_CATEGORY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get up-sell price threshold percentage
     *
     * @param int|null $storeId
     * @return int
     */
    public function getUpSellPriceThreshold(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_UPSELL_PRICE_THRESHOLD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 10;
    }

    /**
     * Check if fallback to native is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isFallbackToNativeEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FALLBACK_NATIVE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if cache is enabled
     *
     * @return bool
     */
    public function isCacheEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_CACHE_ENABLED);
    }

    /**
     * Get cache lifetime in seconds
     *
     * @return int
     */
    public function getCacheLifetime(): int
    {
        return (int) ($this->scopeConfig->getValue(self::XML_PATH_CACHE_LIFETIME) ?: 3600);
    }

    // ==========================================
    // Personalized Recommendations Configuration
    // ==========================================

    /**
     * Check if browsing-based recommendations are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isBrowsingRecommendationsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PERSONALIZED_BROWSING_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get browsing recommendations limit
     *
     * @param int|null $storeId
     * @return int
     */
    public function getBrowsingRecommendationsLimit(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_PERSONALIZED_BROWSING_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 8;
    }

    /**
     * Check if purchase-based recommendations are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isPurchaseRecommendationsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PERSONALIZED_PURCHASE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get purchase recommendations limit
     *
     * @param int|null $storeId
     * @return int
     */
    public function getPurchaseRecommendationsLimit(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_PERSONALIZED_PURCHASE_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 8;
    }

    /**
     * Check if wishlist-based recommendations are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isWishlistRecommendationsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PERSONALIZED_WISHLIST_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get wishlist recommendations limit
     *
     * @param int|null $storeId
     * @return int
     */
    public function getWishlistRecommendationsLimit(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_PERSONALIZED_WISHLIST_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 8;
    }

    /**
     * Check if "Just For You" recommendations are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isJustForYouEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PERSONALIZED_JUSTFORYOU_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get "Just For You" recommendations limit
     *
     * @param int|null $storeId
     * @return int
     */
    public function getJustForYouLimit(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_PERSONALIZED_JUSTFORYOU_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 12;
    }

    /**
     * Get wishlist weight for combined recommendations
     *
     * @param int|null $storeId
     * @return float
     */
    public function getWishlistWeight(?int $storeId = null): float
    {
        $weight = (int) $this->scopeConfig->getValue(
            self::XML_PATH_PERSONALIZED_WEIGHT_WISHLIST,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 40;
        return $weight / 100;
    }

    /**
     * Get purchase weight for combined recommendations
     *
     * @param int|null $storeId
     * @return float
     */
    public function getPurchaseWeight(?int $storeId = null): float
    {
        $weight = (int) $this->scopeConfig->getValue(
            self::XML_PATH_PERSONALIZED_WEIGHT_PURCHASE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 35;
        return $weight / 100;
    }

    /**
     * Get browsing weight for combined recommendations
     *
     * @param int|null $storeId
     * @return float
     */
    public function getBrowsingWeight(?int $storeId = null): float
    {
        $weight = (int) $this->scopeConfig->getValue(
            self::XML_PATH_PERSONALIZED_WEIGHT_BROWSING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 25;
        return $weight / 100;
    }

    /**
     * Get minimum history items required for personalization
     *
     * @param int|null $storeId
     * @return int
     */
    public function getMinHistoryItems(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_PERSONALIZED_MIN_HISTORY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 2;
    }

    /**
     * Check if personalized recommendations should show on homepage
     *
     * @param int|null $storeId
     * @return bool
     */
    public function showOnHomepage(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PERSONALIZED_SHOW_HOMEPAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if personalized recommendations should show on category pages
     *
     * @param int|null $storeId
     * @return bool
     */
    public function showOnCategory(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PERSONALIZED_SHOW_CATEGORY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if personalized recommendations should show on product pages
     *
     * @param int|null $storeId
     * @return bool
     */
    public function showOnProduct(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PERSONALIZED_SHOW_PRODUCT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    // ==========================================
    // LLM Re-ranking Configuration
    // ==========================================

    /**
     * Check if LLM re-ranking is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isLlmRerankingEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_LLM_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get LLM provider (claude or openai)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLlmProvider(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_LLM_PROVIDER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 'claude';
    }

    /**
     * Get LLM API key (encrypted)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getLlmApiKey(?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_LLM_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (empty($value)) {
            return '';
        }

        return $this->encryptor->decrypt($value);
    }

    /**
     * Get LLM model
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getLlmModel(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_LLM_MODEL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get LLM temperature (0-1)
     *
     * @param int|null $storeId
     * @return float
     */
    public function getLlmTemperature(?int $storeId = null): float
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_LLM_TEMPERATURE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value !== null ? (float) $value : 0.7;
    }

    /**
     * Get number of candidates to send to LLM for re-ranking
     *
     * @param int|null $storeId
     * @return int
     */
    public function getLlmCandidateCount(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_LLM_CANDIDATE_COUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 20;
    }
}
