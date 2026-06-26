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

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Service\Fallback;

use NavinDBhudiya\ProductRecommendation\Service\Fallback\FallbackSelector;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the graceful fallback decision logic.
 */
class FallbackSelectorTest extends TestCase
{
    /**
     * @var FallbackSelector
     */
    private FallbackSelector $selector;

    protected function setUp(): void
    {
        $this->selector = new FallbackSelector();
    }

    public function testPrimaryWhenStoreHealthyAndEnoughResults(): void
    {
        $result = $this->selector->select(true, false, 8, 4, true, true);
        $this->assertSame(FallbackSelector::SERVED_BY_PRIMARY, $result['served_by']);
        $this->assertSame(FallbackSelector::REASON_OK, $result['reason']);
        $this->assertFalse($this->selector->isFallback($result['served_by']));
    }

    public function testStoreDownFallsBackToSameCategory(): void
    {
        $result = $this->selector->select(false, false, 0, 4, true, true);
        $this->assertSame(FallbackSelector::SERVED_BY_SAME_CATEGORY, $result['served_by']);
        $this->assertSame(FallbackSelector::REASON_STORE_DOWN, $result['reason']);
        $this->assertTrue($this->selector->isFallback($result['served_by']));
    }

    public function testStoreDownWithoutSameCategoryUsesNative(): void
    {
        $result = $this->selector->select(false, false, 0, 4, false, true);
        $this->assertSame(FallbackSelector::SERVED_BY_NATIVE, $result['served_by']);
        $this->assertSame(FallbackSelector::REASON_STORE_DOWN, $result['reason']);
    }

    public function testColdStartFallsBack(): void
    {
        $result = $this->selector->select(true, true, 0, 4, true, true);
        $this->assertSame(FallbackSelector::SERVED_BY_SAME_CATEGORY, $result['served_by']);
        $this->assertSame(FallbackSelector::REASON_COLD_START, $result['reason']);
    }

    public function testBelowThresholdFallsBack(): void
    {
        $result = $this->selector->select(true, false, 2, 4, true, true);
        $this->assertSame(FallbackSelector::SERVED_BY_SAME_CATEGORY, $result['served_by']);
        $this->assertSame(FallbackSelector::REASON_BELOW_THRESHOLD, $result['reason']);
    }

    public function testExactlyAtThresholdIsPrimary(): void
    {
        $result = $this->selector->select(true, false, 4, 4, true, true);
        $this->assertSame(FallbackSelector::SERVED_BY_PRIMARY, $result['served_by']);
    }

    public function testNoFallbackAvailableYieldsNone(): void
    {
        $result = $this->selector->select(false, false, 0, 4, false, false);
        $this->assertSame(FallbackSelector::SERVED_BY_NONE, $result['served_by']);
        $this->assertSame(FallbackSelector::REASON_STORE_DOWN, $result['reason']);
    }

    public function testMinResultsTreatedAsAtLeastOne(): void
    {
        // Even with minResults=0, zero primary results must trigger a fallback so the block
        // never renders empty.
        $result = $this->selector->select(true, false, 0, 0, true, true);
        $this->assertSame(FallbackSelector::SERVED_BY_SAME_CATEGORY, $result['served_by']);
        $this->assertSame(FallbackSelector::REASON_BELOW_THRESHOLD, $result['reason']);
    }
}
