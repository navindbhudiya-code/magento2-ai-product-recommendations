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
 * Rolls raw interaction events up into daily buckets keyed by date/slot/store/variant (pure).
 *
 * The same grouping and counting used by the roll-up cron, kept dependency-free so the numbers
 * are reproducible and unit-testable (same input -> identical output).
 */
class StatsAggregator
{
    private const TYPE_MAP = [
        'impression' => 'impressions',
        'click' => 'clicks',
        'add_to_cart' => 'add_to_carts',
        'purchase' => 'purchases',
    ];

    /**
     * Aggregate raw events into daily stat rows.
     *
     * @param array $events Each: ['stat_date'=>string, 'slot'=>string, 'store_id'=>int,
     *                      'variant'=>string, 'event_type'=>string, 'revenue'=>float, 'is_demo'=>bool].
     * @return array List of stat rows, one per (date, slot, store, variant) group.
     */
    public function aggregate(array $events): array
    {
        $buckets = [];
        foreach ($events as $event) {
            $date = (string) ($event['stat_date'] ?? '');
            $slot = (string) ($event['slot'] ?? 'related');
            $storeId = (int) ($event['store_id'] ?? 0);
            $variant = (string) ($event['variant'] ?? 'ai');
            $key = $date . '|' . $slot . '|' . $storeId . '|' . $variant;

            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'stat_date' => $date,
                    'slot' => $slot,
                    'store_id' => $storeId,
                    'variant' => $variant,
                    'impressions' => 0,
                    'clicks' => 0,
                    'add_to_carts' => 0,
                    'purchases' => 0,
                    'revenue' => 0.0,
                    'is_demo' => false,
                ];
            }

            $type = (string) ($event['event_type'] ?? '');
            if (isset(self::TYPE_MAP[$type])) {
                $buckets[$key][self::TYPE_MAP[$type]]++;
            }
            $buckets[$key]['revenue'] += (float) ($event['revenue'] ?? 0);
            $buckets[$key]['is_demo'] = $buckets[$key]['is_demo'] || !empty($event['is_demo']);
        }

        // Normalise revenue precision and return a list (drop the keys).
        return array_map(static function (array $row): array {
            $row['revenue'] = round($row['revenue'], 4);
            return $row;
        }, array_values($buckets));
    }
}
