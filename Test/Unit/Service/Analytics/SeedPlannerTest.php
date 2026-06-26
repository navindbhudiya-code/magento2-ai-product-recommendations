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

use NavinDBhudiya\ProductRecommendation\Service\Analytics\SeedPlanner;
use NavinDBhudiya\ProductRecommendation\Service\Analytics\VariantBucketer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the deterministic demo-event planner.
 */
class SeedPlannerTest extends TestCase
{
    /**
     * @var SeedPlanner
     */
    private SeedPlanner $planner;

    /**
     * @var array
     */
    private array $catalog;

    protected function setUp(): void
    {
        $this->planner = new SeedPlanner(new VariantBucketer());
        $this->catalog = [];
        for ($id = 1; $id <= 12; $id++) {
            $this->catalog[$id] = "SKU-$id";
        }
    }

    public function testSameSeedProducesIdenticalEvents(): void
    {
        $a = $this->planner->generate(42, 7, $this->catalog, '2026-06-30');
        $b = $this->planner->generate(42, 7, $this->catalog, '2026-06-30');
        $this->assertEquals($a, $b);
        $this->assertNotEmpty($a);
    }

    public function testDifferentSeedProducesDifferentEvents(): void
    {
        $a = $this->planner->generate(42, 7, $this->catalog, '2026-06-30');
        $b = $this->planner->generate(7, 7, $this->catalog, '2026-06-30');
        $this->assertNotEquals($a, $b);
    }

    public function testEveryRowIsStampedDemo(): void
    {
        $events = $this->planner->generate(42, 5, $this->catalog, '2026-06-30');
        foreach ($events as $event) {
            $this->assertSame(1, $event['is_demo']);
        }
    }

    public function testContainsFunnelEventTypesAndBothVariants(): void
    {
        $events = $this->planner->generate(42, 30, $this->catalog, '2026-06-30');
        $types = array_unique(array_column($events, 'event_type'));
        $variants = array_unique(array_column($events, 'variant'));

        $this->assertContains('impression', $types);
        $this->assertContains('click', $types);
        $this->assertContains('purchase', $types);
        $this->assertContains(VariantBucketer::VARIANT_AI, $variants);
        $this->assertContains(VariantBucketer::VARIANT_CONTROL, $variants);
    }

    public function testDatesSpanRequestedWindow(): void
    {
        $events = $this->planner->generate(42, 3, $this->catalog, '2026-06-30');
        $dates = array_unique(array_column($events, 'stat_date'));
        sort($dates);
        $this->assertSame(['2026-06-28', '2026-06-29', '2026-06-30'], $dates);
    }

    public function testEmptyCatalogOrZeroDaysYieldsNothing(): void
    {
        $this->assertSame([], $this->planner->generate(42, 7, [], '2026-06-30'));
        $this->assertSame([], $this->planner->generate(42, 0, $this->catalog, '2026-06-30'));
    }
}
