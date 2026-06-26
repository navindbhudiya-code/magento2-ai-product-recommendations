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

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Service\Bundle;

use NavinDBhudiya\ProductRecommendation\Service\Bundle\BundleBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for "complete the look" bundle generation.
 */
class BundleBuilderTest extends TestCase
{
    /**
     * @var BundleBuilder
     */
    private BundleBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new BundleBuilder();
    }

    /**
     * @return array
     */
    private function candidates(): array
    {
        return [
            ['id' => 'anchor', 'co_purchase' => 99, 'proximity' => 1.0, 'category' => 'pants'],
            ['id' => 'top', 'co_purchase' => 40, 'proximity' => 0.9, 'category' => 'tops'],
            ['id' => 'belt', 'co_purchase' => 30, 'proximity' => 0.7, 'category' => 'accessories'],
            ['id' => 'shoes', 'co_purchase' => 20, 'proximity' => 0.6, 'category' => 'shoes'],
            ['id' => 'random', 'co_purchase' => 1, 'proximity' => 0.05, 'category' => 'electronics'],
        ];
    }

    public function testExcludesAnchorAndIncoherentItems(): void
    {
        $bundle = $this->builder->build('anchor', $this->candidates(), 4);
        $ids = array_column($bundle, 'id');

        $this->assertNotContains('anchor', $ids);
        $this->assertNotContains('random', $ids); // proximity 0.05 < 0.2 threshold
    }

    public function testOrdersByCombinedScore(): void
    {
        $bundle = $this->builder->build('anchor', $this->candidates(), 3);
        $this->assertSame(['top', 'belt', 'shoes'], array_column($bundle, 'id'));
        $this->assertGreaterThan($bundle[1]['bundle_score'], $bundle[0]['bundle_score']);
    }

    public function testRespectsSizeAndDistinctCategories(): void
    {
        $bundle = $this->builder->build('anchor', $this->candidates(), 2);
        $this->assertCount(2, $bundle);

        $categories = array_column($bundle, 'category');
        $this->assertSame($categories, array_unique($categories));
    }

    public function testDeduplicatesByCategoryKeepingHigherScore(): void
    {
        $candidates = [
            ['id' => 'top-a', 'co_purchase' => 10, 'proximity' => 0.5, 'category' => 'tops'],
            ['id' => 'top-b', 'co_purchase' => 50, 'proximity' => 0.9, 'category' => 'tops'],
            ['id' => 'belt', 'co_purchase' => 5, 'proximity' => 0.6, 'category' => 'accessories'],
        ];
        $bundle = $this->builder->build('anchor', $candidates, 3);
        $ids = array_column($bundle, 'id');

        $this->assertContains('top-b', $ids);
        $this->assertNotContains('top-a', $ids); // lower-scoring duplicate category dropped
        $this->assertContains('belt', $ids);
    }

    public function testEmptyCandidates(): void
    {
        $this->assertSame([], $this->builder->build('anchor', [], 3));
    }
}
