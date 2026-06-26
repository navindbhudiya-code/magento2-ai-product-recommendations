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

namespace NavinDBhudiya\ProductRecommendation\Service\Fallback;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Psr\Log\LoggerInterface;

/**
 * Supplies fallback products so the recommendation block never renders empty.
 *
 * Pulls same-category in-stock products first, then the source product's native related links,
 * excluding the source and anything already chosen. The tier is decided by FallbackSelector;
 * this class only fetches for the chosen tier(s).
 */
class FallbackProvider
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productCollectionFactory;

    /**
     * @var StockHelper
     */
    private StockHelper $stockHelper;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CollectionFactory $productCollectionFactory
     * @param StockHelper $stockHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $productCollectionFactory,
        StockHelper $stockHelper,
        LoggerInterface $logger
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockHelper = $stockHelper;
        $this->logger = $logger;
    }

    /**
     * Fetch up to $needed fallback products for a source product.
     *
     * @param ProductInterface $source
     * @param int $needed
     * @param int[] $excludeIds Product ids already chosen (and the source).
     * @param bool $allowNative Whether the native related-links tier may be used.
     * @param int $storeId
     * @return ProductInterface[]
     */
    public function fill(
        ProductInterface $source,
        int $needed,
        array $excludeIds,
        bool $allowNative,
        int $storeId
    ): array {
        if ($needed <= 0) {
            return [];
        }
        $exclude = $excludeIds;
        $exclude[] = (int) $source->getId();
        $exclude = array_values(array_unique(array_map('intval', $exclude)));

        $products = $this->sameCategory($source, $needed, $exclude, $storeId);

        if (count($products) < $needed && $allowNative) {
            foreach ($this->nativeRelated($source) as $related) {
                $id = (int) $related->getId();
                if (in_array($id, $exclude, true)) {
                    continue;
                }
                $products[] = $related;
                $exclude[] = $id;
                if (count($products) >= $needed) {
                    break;
                }
            }
        }

        return array_slice($products, 0, $needed);
    }

    /**
     * In-stock, visible products sharing a category with the source.
     *
     * @param ProductInterface $source
     * @param int $needed
     * @param int[] $excludeIds
     * @param int $storeId
     * @return ProductInterface[]
     */
    private function sameCategory(ProductInterface $source, int $needed, array $excludeIds, int $storeId): array
    {
        $categoryIds = $source instanceof Product ? $source->getCategoryIds() : [];
        if (empty($categoryIds)) {
            return [];
        }

        try {
            $collection = $this->productCollectionFactory->create();
            $collection->addStoreFilter($storeId)
                ->addAttributeToSelect(['name', 'price', 'small_image', 'url_key'])
                ->addFieldToFilter('status', Status::STATUS_ENABLED)
                ->addFieldToFilter('visibility', ['in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_BOTH,
                ]])
                ->addFieldToFilter('entity_id', ['nin' => $excludeIds]);
            $collection->joinField(
                'category_id',
                'catalog_category_product',
                'category_id',
                'product_id=entity_id',
                ['category_id' => ['in' => $categoryIds]],
                'inner'
            );
            $collection->getSelect()->group('e.entity_id');
            $this->stockHelper->addInStockFilterToCollection($collection);
            $collection->setPageSize($needed);

            return array_values($collection->getItems());
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] same-category fallback failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * The source product's native related-link products.
     *
     * @param ProductInterface $source
     * @return ProductInterface[]
     */
    private function nativeRelated(ProductInterface $source): array
    {
        try {
            if ($source instanceof Product) {
                return $source->getRelatedProducts();
            }
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] native related fallback failed: ' . $e->getMessage());
        }
        return [];
    }
}
