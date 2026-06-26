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

namespace NavinDBhudiya\ProductRecommendation\Service\Analytics;

/**
 * Last-click attribution within a session (pure).
 *
 * Credits a purchase to the most recent recommendation click on the purchased product that
 * happened in the same session before the purchase. The caller passes only same-session clicks,
 * so cross-session purchases are never attributed.
 */
class AttributionResolver
{
    /**
     * Resolve which recommendation click (if any) a purchase should be attributed to.
     *
     * @param array $clicks Same-session clicks: each ['recommended_id' => int, 'created_at' => int, ...].
     * @param int $purchasedProductId
     * @param int $purchaseTs Unix timestamp of the purchase.
     * @return array|null The winning click row, or null when the purchase is not attributable.
     */
    public function attribute(array $clicks, int $purchasedProductId, int $purchaseTs): ?array
    {
        $winner = null;
        $winnerTs = -1;
        foreach ($clicks as $click) {
            $recommendedId = (int) ($click['recommended_id'] ?? 0);
            $clickTs = (int) ($click['created_at'] ?? 0);
            if ($recommendedId !== $purchasedProductId) {
                continue;
            }
            if ($clickTs > $purchaseTs) {
                // A click after the purchase cannot have caused it.
                continue;
            }
            if ($clickTs > $winnerTs) {
                $winner = $click;
                $winnerTs = $clickTs;
            }
        }
        return $winner;
    }

    /**
     * Whether a purchase is attributable to a recommendation click in the session.
     *
     * @param array $clicks
     * @param int $purchasedProductId
     * @param int $purchaseTs
     * @return bool
     */
    public function isAttributed(array $clicks, int $purchasedProductId, int $purchaseTs): bool
    {
        return $this->attribute($clicks, $purchasedProductId, $purchaseTs) !== null;
    }
}
