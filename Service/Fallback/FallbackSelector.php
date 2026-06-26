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

/**
 * Pure decision logic for the graceful fallback chain (Phase 1.5).
 *
 * Given the state of a recommendation request it picks which tier should serve the slot:
 *   primary (vector store) -> same_category -> native (Magento related) -> none.
 * Keeping this dependency-free makes the "never renders empty" guarantee fully unit-testable;
 * the chosen tier is logged as `served_by` so the resilience test can assert it.
 */
class FallbackSelector
{
    public const SERVED_BY_PRIMARY = 'primary';
    public const SERVED_BY_SAME_CATEGORY = 'same_category';
    public const SERVED_BY_NATIVE = 'native';
    public const SERVED_BY_NONE = 'none';

    public const REASON_OK = 'primary_ok';
    public const REASON_STORE_DOWN = 'vector_store_unavailable';
    public const REASON_COLD_START = 'cold_start';
    public const REASON_BELOW_THRESHOLD = 'below_threshold';

    /**
     * Decide which tier should serve the slot.
     *
     * @param bool $storeAvailable Whether the vector store responded.
     * @param bool $coldStart Whether the anchor product is brand-new / not indexed.
     * @param int $primaryCount Number of results the vector store returned.
     * @param int $minResults Minimum acceptable results before falling back.
     * @param bool $sameCategoryEnabled Whether the same-category fallback may be used.
     * @param bool $nativeEnabled Whether Magento-native related is allowed as last resort.
     * @return array{served_by: string, reason: string} The chosen tier and why.
     */
    public function select(
        bool $storeAvailable,
        bool $coldStart,
        int $primaryCount,
        int $minResults,
        bool $sameCategoryEnabled,
        bool $nativeEnabled
    ): array {
        $reason = $this->fallbackReason($storeAvailable, $coldStart, $primaryCount, $minResults);

        if ($reason === self::REASON_OK) {
            return ['served_by' => self::SERVED_BY_PRIMARY, 'reason' => self::REASON_OK];
        }

        if ($sameCategoryEnabled) {
            return ['served_by' => self::SERVED_BY_SAME_CATEGORY, 'reason' => $reason];
        }
        if ($nativeEnabled) {
            return ['served_by' => self::SERVED_BY_NATIVE, 'reason' => $reason];
        }
        return ['served_by' => self::SERVED_BY_NONE, 'reason' => $reason];
    }

    /**
     * Why a fallback is (or is not) needed. REASON_OK means the primary tier is sufficient.
     *
     * @param bool $storeAvailable
     * @param bool $coldStart
     * @param int $primaryCount
     * @param int $minResults
     * @return string
     */
    private function fallbackReason(
        bool $storeAvailable,
        bool $coldStart,
        int $primaryCount,
        int $minResults
    ): string {
        if (!$storeAvailable) {
            return self::REASON_STORE_DOWN;
        }
        if ($coldStart) {
            return self::REASON_COLD_START;
        }
        if ($primaryCount < max(1, $minResults)) {
            return self::REASON_BELOW_THRESHOLD;
        }
        return self::REASON_OK;
    }

    /**
     * Convenience: did the slot end up served by a fallback tier (not the primary store)?
     *
     * @param string $servedBy
     * @return bool
     */
    public function isFallback(string $servedBy): bool
    {
        return $servedBy !== self::SERVED_BY_PRIMARY;
    }
}
