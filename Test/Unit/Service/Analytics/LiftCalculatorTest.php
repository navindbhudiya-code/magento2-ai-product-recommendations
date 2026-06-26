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

use NavinDBhudiya\ProductRecommendation\Service\Analytics\LiftCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for A/B lift + significance math.
 */
class LiftCalculatorTest extends TestCase
{
    /**
     * @var LiftCalculator
     */
    private LiftCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new LiftCalculator();
    }

    public function testRatesAndLiftPercent(): void
    {
        $ai = ['impressions' => 1000, 'clicks' => 100, 'add_to_carts' => 50, 'purchases' => 20, 'revenue' => 500.0];
        $control = ['impressions' => 1000, 'clicks' => 80, 'add_to_carts' => 40, 'purchases' => 16, 'revenue' => 400.0];

        $result = $this->calculator->compute($ai, $control);

        $this->assertSame(0.1, $result['ai']['ctr']);       // 100/1000
        $this->assertSame(0.08, $result['control']['ctr']); // 80/1000
        $this->assertSame(25.0, $result['lift']['ctr_pct']); // (0.1-0.08)/0.08 = 25%
        $this->assertSame(0.5, $result['ai']['revenue_per_impression']);
        $this->assertSame(25.0, $result['lift']['revenue_per_impression_pct']);
    }

    public function testZeroControlRateYieldsZeroLift(): void
    {
        $ai = ['impressions' => 100, 'clicks' => 10, 'add_to_carts' => 0, 'purchases' => 0, 'revenue' => 0];
        $control = ['impressions' => 100, 'clicks' => 0, 'add_to_carts' => 0, 'purchases' => 0, 'revenue' => 0];
        $result = $this->calculator->compute($ai, $control);
        $this->assertSame(0.0, $result['lift']['ctr_pct']);
    }

    public function testSignificantWithLargeSeparatedSamples(): void
    {
        // 12% vs 8% CTR over 5000 impressions each is comfortably significant.
        $ai = ['impressions' => 5000, 'clicks' => 600, 'add_to_carts' => 0, 'purchases' => 0, 'revenue' => 0];
        $control = ['impressions' => 5000, 'clicks' => 400, 'add_to_carts' => 0, 'purchases' => 0, 'revenue' => 0];
        $this->assertTrue($this->calculator->compute($ai, $control)['significant']);
    }

    public function testNotSignificantWithTinySamples(): void
    {
        $ai = ['impressions' => 10, 'clicks' => 2, 'add_to_carts' => 0, 'purchases' => 0, 'revenue' => 0];
        $control = ['impressions' => 10, 'clicks' => 1, 'add_to_carts' => 0, 'purchases' => 0, 'revenue' => 0];
        $this->assertFalse($this->calculator->compute($ai, $control)['significant']);
    }

    public function testNotSignificantWhenAnArmHasNoImpressions(): void
    {
        $ai = ['impressions' => 0, 'clicks' => 0, 'add_to_carts' => 0, 'purchases' => 0, 'revenue' => 0];
        $control = ['impressions' => 1000, 'clicks' => 100, 'add_to_carts' => 0, 'purchases' => 0, 'revenue' => 0];
        $result = $this->calculator->compute($ai, $control);
        $this->assertFalse($result['significant']);
        $this->assertSame(0.0, $result['ai']['ctr']);
    }
}
