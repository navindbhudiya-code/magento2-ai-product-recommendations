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

use NavinDBhudiya\ProductRecommendation\Service\Merchandising\RuleCompiler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the natural-language merchandising rule compiler.
 */
class RuleCompilerTest extends TestCase
{
    /**
     * @var RuleCompiler
     */
    private RuleCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new RuleCompiler();
    }

    public function testBoostHighMarginWhenBasketOverThreshold(): void
    {
        $rule = $this->compiler->compile('boost high-margin items when basket > 5000');

        $this->assertTrue($rule['valid']);
        $this->assertSame('boost', $rule['action']);
        $this->assertSame('margin', $rule['item']['attribute']);
        $this->assertSame('high', $rule['item']['direction']);
        $this->assertSame('basket_total', $rule['context']['field']);
        $this->assertSame('gt', $rule['context']['op']);
        $this->assertSame(5000.0, $rule['context']['value']);
    }

    public function testBuryOutOfStock(): void
    {
        $rule = $this->compiler->compile('bury out-of-stock items');
        $this->assertTrue($rule['valid']);
        $this->assertSame('bury', $rule['action']);
        $this->assertSame('stock', $rule['item']['attribute']);
        $this->assertSame('lte', $rule['item']['op']);
        $this->assertSame(0.0, $rule['item']['threshold']);
        $this->assertNull($rule['context']);
    }

    public function testThresholdRule(): void
    {
        $rule = $this->compiler->compile('filter items under 50');
        $this->assertTrue($rule['valid']);
        $this->assertSame('filter', $rule['action']);
        $this->assertSame('price', $rule['item']['attribute']);
        $this->assertSame('lt', $rule['item']['op']);
        $this->assertSame(50.0, $rule['item']['threshold']);
    }

    public function testExplicitAttributeThreshold(): void
    {
        $rule = $this->compiler->compile('boost price items over 100');
        $this->assertTrue($rule['valid']);
        $this->assertSame('price', $rule['item']['attribute']);
        $this->assertSame('gt', $rule['item']['op']);
        $this->assertSame(100.0, $rule['item']['threshold']);
    }

    public function testGibberishIsRejectedNotGuessed(): void
    {
        $this->assertFalse($this->compiler->compile('make it better somehow')['valid']);
        $this->assertFalse($this->compiler->compile('')['valid']);
        $this->assertFalse($this->compiler->compile('boost wobble items')['valid']);
    }
}
