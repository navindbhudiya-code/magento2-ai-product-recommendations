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
 * Deterministic generator of synthetic recommendation events (pure).
 *
 * Uses a self-contained LCG (not the global mt_rand state) so the SAME seed yields the SAME
 * events on any machine — backing the "reproducible: same seed, same numbers" claim. Every row
 * is stamped is_demo=1 with realistic distributions: popular products get more impressions,
 * clicks follow popularity, and add-to-cart/purchase funnel down from clicks.
 */
class SeedPlanner
{
    private const SESSIONS_PER_DAY = 20;
    private const RECS_PER_IMPRESSION = 4;
    private const LCG_A = 1664525;
    private const LCG_C = 1013904223;
    private const LCG_M = 4294967296; // 2^32

    /**
     * @var VariantBucketer
     */
    private VariantBucketer $bucketer;

    /**
     * Current LCG state.
     *
     * @var int
     */
    private int $state = 0;

    /**
     * @param VariantBucketer $bucketer
     */
    public function __construct(VariantBucketer $bucketer)
    {
        $this->bucketer = $bucketer;
    }

    /**
     * Generate a deterministic list of demo event rows.
     *
     * @param int $seed
     * @param int $days Number of days back from $endDate (inclusive).
     * @param string[] $skuByProductId Map of productId => sku for the catalog subset (ordered:
     *                 earlier entries are treated as more popular).
     * @param string $endDate End date (Y-m-d); the most recent day generated.
     * @param int $storeId
     * @return array List of event rows ready to insert.
     */
    public function generate(int $seed, int $days, array $skuByProductId, string $endDate, int $storeId = 1): array
    {
        $this->state = $seed % self::LCG_M;
        $productIds = array_keys($skuByProductId);
        $productCount = count($productIds);
        if ($productCount === 0 || $days < 1) {
            return [];
        }

        $events = [];
        for ($d = 0; $d < $days; $d++) {
            $date = $this->dateMinusDays($endDate, $days - 1 - $d);
            for ($s = 0; $s < self::SESSIONS_PER_DAY; $s++) {
                $sessionId = sprintf('demo-%d-%d-%d', $seed, $d, $s);
                $variant = $this->bucketer->bucket($sessionId, (string) $seed);
                $anchorIdx = $this->weightedIndex($productCount);
                $anchorId = $productIds[$anchorIdx];

                for ($r = 0; $r < self::RECS_PER_IMPRESSION; $r++) {
                    $recIdx = $this->weightedIndex($productCount);
                    $recId = $productIds[$recIdx];
                    $events[] = $this->row($date, $anchorId, $recId, 'impression', $sessionId, $storeId, $variant, 0.0);

                    // Click probability is higher for more popular (lower-index) recs, and the
                    // AI variant converts a little better than control.
                    $clickP = (1.0 - $recIdx / $productCount) * ($variant === VariantBucketer::VARIANT_AI ? 0.5 : 0.4);
                    if ($this->next() >= $clickP) {
                        continue;
                    }
                    $events[] = $this->row($date, $anchorId, $recId, 'click', $sessionId, $storeId, $variant, 0.0);

                    if ($this->next() < 0.4) {
                        $events[] = $this->row(
                            $date,
                            $anchorId,
                            $recId,
                            'add_to_cart',
                            $sessionId,
                            $storeId,
                            $variant,
                            0.0
                        );
                        if ($this->next() < 0.5) {
                            $revenue = round(10 + $this->next() * 90, 2);
                            $events[] = $this->row(
                                $date,
                                $anchorId,
                                $recId,
                                'purchase',
                                $sessionId,
                                $storeId,
                                $variant,
                                $revenue
                            );
                        }
                    }
                }
            }
        }
        return $events;
    }

    /**
     * Build one event row (always is_demo=1).
     *
     * @param string $date
     * @param int $anchorId
     * @param int $recId
     * @param string $type
     * @param string $sessionId
     * @param int $storeId
     * @param string $variant
     * @param float $revenue
     * @return array
     */
    private function row(
        string $date,
        int $anchorId,
        int $recId,
        string $type,
        string $sessionId,
        int $storeId,
        string $variant,
        float $revenue
    ): array {
        return [
            'slot' => 'related',
            'product_id' => $anchorId,
            'recommended_id' => $recId,
            'event_type' => $type,
            'session_id' => $sessionId,
            'store_id' => $storeId,
            'variant' => $variant,
            'revenue' => $revenue,
            'is_demo' => 1,
            'created_at' => $date . ' 12:00:00',
            'stat_date' => $date,
        ];
    }

    /**
     * Next pseudo-random float in [0, 1) from the internal LCG.
     *
     * @return float
     */
    private function next(): float
    {
        $this->state = (self::LCG_A * $this->state + self::LCG_C) % self::LCG_M;
        return $this->state / self::LCG_M;
    }

    /**
     * Pick an index with a bias toward lower indices (popularity / Zipf-like).
     *
     * @param int $count
     * @return int
     */
    private function weightedIndex(int $count): int
    {
        // Squaring a uniform in [0,1) concentrates mass near 0 -> lower indices chosen more.
        $r = $this->next();
        return (int) floor($r * $r * $count) % $count;
    }

    /**
     * Subtract whole days from a Y-m-d date without touching the system clock.
     *
     * @param string $endDate
     * @param int $daysBack
     * @return string
     */
    private function dateMinusDays(string $endDate, int $daysBack): string
    {
        $ts = strtotime($endDate . ' -' . $daysBack . ' days');
        return $ts === false ? $endDate : date('Y-m-d', $ts);
    }
}
