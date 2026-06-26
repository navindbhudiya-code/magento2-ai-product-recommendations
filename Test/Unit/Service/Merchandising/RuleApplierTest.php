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

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Service\Merchandising;

use NavinDBhudiya\ProductRecommendation\Service\Merchandising\RuleApplier;
use NavinDBhudiya\ProductRecommendation\Service\Merchandising\RuleCompiler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for applying compiled merchandising rules to a ranked result set.
 */
class RuleApplierTest extends TestCase
{
    /**
     * @var RuleApplier
     */
    private RuleApplier $applier;

    /**
     * @var RuleCompiler
     */
    private RuleCompiler $compiler;

    protected function setUp(): void
    {
        $this->applier = new RuleApplier();
        $this->compiler = new RuleCompiler();
    }

    /**
     * @return array
     */
    private function items(): array
    {
        // Low-margin item currently ranks first by score.
        return [
            ['id' => 'A', 'score' => 1.0, 'attributes' => ['margin' => 5, 'price' => 20, 'stock' => 3]],
            ['id' => 'B', 'score' => 0.9, 'attributes' => ['margin' => 50, 'price' => 200, 'stock' => 0]],
            ['id' => 'C', 'score' => 0.8, 'attributes' => ['margin' => 30, 'price' => 120, 'stock' => 7]],
        ];
    }

    public function testBoostHighMarginReordersWhenBasketQualifies(): void
    {
        $rule = $this->compiler->compile('boost high-margin items when basket > 5000');
        $result = $this->applier->apply($this->items(), $rule, ['basket_total' => 6000]);

        // High-margin B should now lead despite its lower base score.
        $this->assertSame('B', $result[0]['id']);
        $this->assertSame(['B', 'C', 'A'], array_column($result, 'id'));
    }

    public function testRuleDormantWhenContextNotSatisfied(): void
    {
        $rule = $this->compiler->compile('boost high-margin items when basket > 5000');
        $result = $this->applier->apply($this->items(), $rule, ['basket_total' => 1000]);

        // Unchanged order: original scores preserved.
        $this->assertSame(['A', 'B', 'C'], array_column($result, 'id'));
    }

    public function testFilterRemovesMatchingItems(): void
    {
        $rule = $this->compiler->compile('filter items under 100');
        $result = $this->applier->apply($this->items(), $rule);

        // Item A (price 20) is removed; B (200) and C (120) remain.
        $this->assertSame(['B', 'C'], array_column($result, 'id'));
    }

    public function testBuryOutOfStockSinksItem(): void
    {
        $rule = $this->compiler->compile('bury out-of-stock items');
        $result = $this->applier->apply($this->items(), $rule);

        // B has stock 0 -> buried to the bottom.
        $this->assertSame('B', $result[count($result) - 1]['id']);
    }

    public function testInvalidRuleLeavesItemsUntouched(): void
    {
        $rule = $this->compiler->compile('do something clever');
        $result = $this->applier->apply($this->items(), $rule, ['basket_total' => 9999]);
        $this->assertSame(['A', 'B', 'C'], array_column($result, 'id'));
    }
}
