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

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Service\Analytics;

use NavinDBhudiya\ProductRecommendation\Service\Analytics\StatsAggregator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the daily roll-up aggregator.
 */
class StatsAggregatorTest extends TestCase
{
    /**
     * @var StatsAggregator
     */
    private StatsAggregator $aggregator;

    protected function setUp(): void
    {
        $this->aggregator = new StatsAggregator();
    }

    public function testGroupsByDateSlotStoreVariantAndCountsTypes(): void
    {
        $events = [
            $this->event('ai', 'impression'),
            $this->event('ai', 'click'),
            $this->event('ai', 'add_to_cart'),
            $this->event('ai', 'purchase', ['revenue' => 49.5]),
            // Different variant -> separate bucket.
            $this->event('control', 'impression'),
        ];

        $rows = $this->aggregator->aggregate($events);
        $this->assertCount(2, $rows);

        $ai = $this->findRow($rows, 'ai');
        $this->assertSame(1, $ai['impressions']);
        $this->assertSame(1, $ai['clicks']);
        $this->assertSame(1, $ai['add_to_carts']);
        $this->assertSame(1, $ai['purchases']);
        $this->assertSame(49.5, $ai['revenue']);

        $control = $this->findRow($rows, 'control');
        $this->assertSame(1, $control['impressions']);
        $this->assertSame(0, $control['clicks']);
    }

    public function testIsDemoIsStickyAcrossGroup(): void
    {
        $events = [
            $this->event('ai', 'impression', ['store_id' => 0, 'is_demo' => false]),
            $this->event('ai', 'click', ['store_id' => 0, 'is_demo' => true]),
        ];
        $rows = $this->aggregator->aggregate($events);
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['is_demo']);
    }

    public function testReproducibleAndIgnoresUnknownTypes(): void
    {
        $events = [
            $this->event('ai', 'weird', ['slot' => 'crosssell']),
            $this->event('ai', 'impression', ['slot' => 'crosssell']),
        ];
        $first = $this->aggregator->aggregate($events);
        $second = $this->aggregator->aggregate($events);
        $this->assertEquals($first, $second);
        $this->assertSame(1, $first[0]['impressions']);
    }

    public function testEmptyInput(): void
    {
        $this->assertSame([], $this->aggregator->aggregate([]));
    }

    /**
     * Build a compact event row with sensible defaults.
     *
     * @param string $variant
     * @param string $type
     * @param array $extra
     * @return array
     */
    private function event(string $variant, string $type, array $extra = []): array
    {
        return array_merge([
            'stat_date' => '2026-06-01',
            'slot' => 'related',
            'store_id' => 1,
            'variant' => $variant,
            'event_type' => $type,
        ], $extra);
    }

    /**
     * @param array $rows
     * @param string $variant
     * @return array
     */
    private function findRow(array $rows, string $variant): array
    {
        foreach ($rows as $row) {
            if ($row['variant'] === $variant) {
                return $row;
            }
        }
        $this->fail("No row for variant $variant");
    }
}
