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

/**
 * Converts a VectorStoreInterface flat match list into ChromaDB's columnar query shape (pure).
 *
 * This lets RecommendationService consume any backend (ChromaDB or search engine) through the
 * VectorStoreInterface while reusing the existing ChromaDB-shaped result processing unchanged.
 */
class ColumnarConverter
{
    /**
     * Convert matches ([{id, distance, metadata}, ...]) to ['ids'=>[[..]], 'distances'=>[[..]],
     * 'metadatas'=>[[..]]] — i.e. the single-query columnar response ChromaDB returns.
     *
     * @param array $matches
     * @return array
     */
    public function toColumnar(array $matches): array
    {
        $ids = [];
        $distances = [];
        $metadatas = [];
        foreach ($matches as $match) {
            $ids[] = (string) ($match['id'] ?? '');
            $distances[] = (float) ($match['distance'] ?? 0.0);
            $metadatas[] = (array) ($match['metadata'] ?? []);
        }
        return [
            'ids' => [$ids],
            'distances' => [$distances],
            'metadatas' => [$metadatas],
        ];
    }
}
