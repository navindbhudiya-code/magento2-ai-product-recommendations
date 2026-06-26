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

namespace NavinDBhudiya\ProductRecommendation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Vector-store backend options (Phase 1).
 */
class VectorStoreBackend implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'chromadb',
                'label' => __('ChromaDB (existing)')
            ],
            [
                'value' => 'search_engine',
                'label' => __('Search Engine (OpenSearch/Elasticsearch k-NN — no extra infra)')
            ],
        ];
    }
}
