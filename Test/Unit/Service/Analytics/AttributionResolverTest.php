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

use NavinDBhudiya\ProductRecommendation\Service\Analytics\AttributionResolver;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for last-click attribution.
 */
class AttributionResolverTest extends TestCase
{
    /**
     * @var AttributionResolver
     */
    private AttributionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new AttributionResolver();
    }

    public function testAttributesToEarlierSameSessionClick(): void
    {
        $clicks = [
            ['recommended_id' => 42, 'created_at' => 1000, 'variant' => 'ai'],
        ];
        $winner = $this->resolver->attribute($clicks, 42, 1500);
        $this->assertNotNull($winner);
        $this->assertSame('ai', $winner['variant']);
        $this->assertTrue($this->resolver->isAttributed($clicks, 42, 1500));
    }

    public function testPicksMostRecentClickBeforePurchase(): void
    {
        $clicks = [
            ['recommended_id' => 42, 'created_at' => 1000, 'variant' => 'ai'],
            ['recommended_id' => 42, 'created_at' => 1400, 'variant' => 'control'],
        ];
        $winner = $this->resolver->attribute($clicks, 42, 1500);
        $this->assertSame(1400, $winner['created_at']);
        $this->assertSame('control', $winner['variant']);
    }

    public function testNotAttributedWhenProductNeverClicked(): void
    {
        $clicks = [['recommended_id' => 7, 'created_at' => 1000]];
        $this->assertNull($this->resolver->attribute($clicks, 42, 1500));
        $this->assertFalse($this->resolver->isAttributed($clicks, 42, 1500));
    }

    public function testClickAfterPurchaseIsIgnored(): void
    {
        $clicks = [['recommended_id' => 42, 'created_at' => 2000]];
        $this->assertNull($this->resolver->attribute($clicks, 42, 1500));
    }

    public function testCrossSessionNotAttributedWhenCallerPassesEmptyClicks(): void
    {
        // The caller only passes same-session clicks; an empty set => no attribution.
        $this->assertNull($this->resolver->attribute([], 42, 1500));
    }
}
