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

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Service\Privacy;

use NavinDBhudiya\ProductRecommendation\Service\Privacy\RetentionPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for retention-window math.
 */
class RetentionPolicyTest extends TestCase
{
    /**
     * @var RetentionPolicy
     */
    private RetentionPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new RetentionPolicy();
    }

    public function testCutoffDate(): void
    {
        $this->assertSame('2026-06-01', $this->policy->cutoffDate('2026-06-31', 30));
        $this->assertSame('2026-05-01', $this->policy->cutoffDate('2026-05-31', 30));
    }

    public function testIsExpired(): void
    {
        $this->assertTrue($this->policy->isExpired('2026-01-01', '2026-06-30', 90));
        $this->assertFalse($this->policy->isExpired('2026-06-15', '2026-06-30', 90));
    }

    public function testZeroRetentionExpiresEverythingBeforeToday(): void
    {
        $this->assertTrue($this->policy->isExpired('2026-06-29', '2026-06-30', 0));
        $this->assertFalse($this->policy->isExpired('2026-06-30', '2026-06-30', 0));
    }
}
