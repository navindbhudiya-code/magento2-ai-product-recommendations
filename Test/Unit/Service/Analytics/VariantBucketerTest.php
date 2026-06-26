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

use NavinDBhudiya\ProductRecommendation\Service\Analytics\VariantBucketer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for deterministic A/B bucketing.
 */
class VariantBucketerTest extends TestCase
{
    /**
     * @var VariantBucketer
     */
    private VariantBucketer $bucketer;

    protected function setUp(): void
    {
        $this->bucketer = new VariantBucketer();
    }

    public function testDeterministicForSameSession(): void
    {
        $a = $this->bucketer->bucket('session-abc');
        $b = $this->bucketer->bucket('session-abc');
        $this->assertSame($a, $b);
        $this->assertContains($a, [VariantBucketer::VARIANT_AI, VariantBucketer::VARIANT_CONTROL]);
    }

    public function testSaltChangesAssignmentSpace(): void
    {
        // Across many sessions, salting should change at least some assignments.
        $changed = 0;
        for ($i = 0; $i < 200; $i++) {
            if ($this->bucketer->bucket("s$i") !== $this->bucketer->bucket("s$i", 'exp2')) {
                $changed++;
            }
        }
        $this->assertGreaterThan(0, $changed);
    }

    public function testRoughlyFiftyFiftyOverManySessions(): void
    {
        $ai = 0;
        $total = 2000;
        for ($i = 0; $i < $total; $i++) {
            if ($this->bucketer->isAi("visitor-$i")) {
                $ai++;
            }
        }
        $share = $ai / $total;
        // Allow a generous band; the hash split should be near 50/50.
        $this->assertGreaterThan(0.40, $share);
        $this->assertLessThan(0.60, $share);
    }

    public function testIsAiMatchesBucket(): void
    {
        $isAi = $this->bucketer->isAi('xyz');
        $this->assertSame($isAi, $this->bucketer->bucket('xyz') === VariantBucketer::VARIANT_AI);
    }
}
