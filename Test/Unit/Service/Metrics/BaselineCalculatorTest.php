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

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Service\Metrics;

use NavinDBhudiya\ProductRecommendation\Service\Metrics\BaselineCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the pure baseline metric math.
 */
class BaselineCalculatorTest extends TestCase
{
    /**
     * @var BaselineCalculator
     */
    private BaselineCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new BaselineCalculator();
    }

    public function testPercentileEmptyListIsZero(): void
    {
        $this->assertSame(0.0, $this->calculator->percentile([], 50.0));
    }

    public function testPercentileSingleValue(): void
    {
        $this->assertSame(42.0, $this->calculator->percentile([42], 50.0));
    }

    public function testMedianInterpolatesBetweenRanks(): void
    {
        // values 10,20,30,40 -> rank 1.5 -> halfway between 20 and 30 -> 25
        $this->assertSame(25.0, $this->calculator->percentile([40, 10, 30, 20], 50.0));
    }

    public function testMedianOddCountIsMiddle(): void
    {
        $this->assertSame(30.0, $this->calculator->percentile([10, 50, 30], 50.0));
    }

    public function testPercentileBounds(): void
    {
        $values = [5, 1, 9, 3, 7];
        $this->assertSame(1.0, $this->calculator->percentile($values, 0.0));
        $this->assertSame(9.0, $this->calculator->percentile($values, 100.0));
    }

    public function testPercentileIgnoresNonNumeric(): void
    {
        $this->assertSame(20.0, $this->calculator->percentile([10, 'x', 30, null], 50.0));
    }

    public function testSummarizeComputesHitRateAndExcludesUnresolved(): void
    {
        $rows = [
            ['resolved' => true,  'hit' => true,  'latency_ms' => 100],
            ['resolved' => true,  'hit' => false, 'latency_ms' => 200],
            ['resolved' => true,  'hit' => true,  'latency_ms' => 300],
            ['resolved' => false],                              // unresolved -> excluded
        ];

        $summary = $this->calculator->summarize($rows, 10);

        $this->assertSame(4, $summary['pairs_total']);
        $this->assertSame(3, $summary['pairs_evaluated']);
        $this->assertSame(1, $summary['pairs_unresolved']);
        $this->assertSame(2, $summary['hits']);
        $this->assertSame(1, $summary['misses']);
        // 2 hits / 3 evaluated
        $this->assertSame(0.6667, $summary['hit_rate_at_10']);
        $this->assertSame(200.0, $summary['p50_latency_ms']);
    }

    public function testSummarizeHonoursKInKeyName(): void
    {
        $summary = $this->calculator->summarize([['resolved' => true, 'hit' => true]], 5);
        $this->assertArrayHasKey('hit_rate_at_5', $summary);
        $this->assertSame(5, $summary['k']);
    }

    public function testSummarizeAllUnresolvedYieldsZeroHitRate(): void
    {
        $summary = $this->calculator->summarize([['resolved' => false], ['resolved' => false]], 10);
        $this->assertSame(0, $summary['pairs_evaluated']);
        $this->assertSame(0.0, $summary['hit_rate_at_10']);
        $this->assertSame(0.0, $summary['p50_latency_ms']);
    }

    public function testSummarizeEmptyInput(): void
    {
        $summary = $this->calculator->summarize([], 10);
        $this->assertSame(0, $summary['pairs_total']);
        $this->assertSame(0.0, $summary['hit_rate_at_10']);
    }
}
