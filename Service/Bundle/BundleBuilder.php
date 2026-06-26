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

namespace NavinDBhudiya\ProductRecommendation\Service\Bundle;

/**
 * Builds a "complete the look" bundle from candidates scored by co-purchase frequency and
 * semantic proximity (pure, deterministic).
 *
 * Candidates are arrays: ['id' => mixed, 'co_purchase' => int, 'proximity' => float(0..1),
 * 'category' => string]. The anchor is excluded, low-proximity items are dropped for coherence,
 * and at most one item per category is kept so the bundle reads as a complementary set.
 */
class BundleBuilder
{
    private const WEIGHT_COPURCHASE = 0.5;
    private const WEIGHT_PROXIMITY = 0.5;
    private const MIN_PROXIMITY = 0.2;

    /**
     * Build a bundle of up to $size coherent items for an anchor.
     *
     * @param mixed $anchorId
     * @param array $candidates
     * @param int $size
     * @return array Ordered bundle items, each with an added 'bundle_score'.
     */
    public function build($anchorId, array $candidates, int $size = 3): array
    {
        $maxCoPurchase = $this->maxCoPurchase($candidates);

        $scored = [];
        foreach ($candidates as $candidate) {
            if (($candidate['id'] ?? null) === $anchorId) {
                continue;
            }
            $proximity = (float) ($candidate['proximity'] ?? 0);
            if ($proximity < self::MIN_PROXIMITY) {
                continue; // not coherent enough to belong in the look
            }
            $coNorm = $maxCoPurchase > 0 ? (int) ($candidate['co_purchase'] ?? 0) / $maxCoPurchase : 0.0;
            $candidate['bundle_score'] = round(
                self::WEIGHT_COPURCHASE * $coNorm + self::WEIGHT_PROXIMITY * $proximity,
                4
            );
            $scored[] = $candidate;
        }

        usort($scored, static function (array $a, array $b): int {
            return $b['bundle_score'] <=> $a['bundle_score'];
        });

        return $this->takeDistinctCategories($scored, max(1, $size));
    }

    /**
     * Keep the highest-scoring item per category, up to $size items.
     *
     * @param array $scored
     * @param int $size
     * @return array
     */
    private function takeDistinctCategories(array $scored, int $size): array
    {
        $bundle = [];
        $seenCategories = [];
        foreach ($scored as $item) {
            $category = (string) ($item['category'] ?? '');
            if ($category !== '' && isset($seenCategories[$category])) {
                continue;
            }
            $seenCategories[$category] = true;
            $bundle[] = $item;
            if (count($bundle) >= $size) {
                break;
            }
        }
        return $bundle;
    }

    /**
     * Max co-purchase count across candidates (for normalisation).
     *
     * @param array $candidates
     * @return int
     */
    private function maxCoPurchase(array $candidates): int
    {
        $max = 0;
        foreach ($candidates as $candidate) {
            $max = max($max, (int) ($candidate['co_purchase'] ?? 0));
        }
        return $max;
    }
}
