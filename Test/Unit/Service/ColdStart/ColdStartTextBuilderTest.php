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

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Service\ColdStart;

use NavinDBhudiya\ProductRecommendation\Service\ColdStart\ColdStartTextBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for cold-start attribute-only embedding text.
 */
class ColdStartTextBuilderTest extends TestCase
{
    /**
     * @var ColdStartTextBuilder
     */
    private ColdStartTextBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ColdStartTextBuilder();
    }

    public function testBuildsFromAttributes(): void
    {
        $text = $this->builder->build([
            'name' => 'Karmen Yoga Pant',
            'categories' => ['Women', 'Pants', 'Pants'],
            'attributes' => ['color' => 'Black', 'material' => 'Cotton'],
            'description' => '<p>Soft and stretchy.</p>',
        ]);

        $this->assertStringContainsString('Karmen Yoga Pant', $text);
        $this->assertStringContainsString('Categories: Women, Pants', $text);
        $this->assertStringContainsString('color: Black', $text);
        $this->assertStringContainsString('Soft and stretchy.', $text);
        $this->assertStringNotContainsString('<p>', $text);
    }

    public function testDeterministicAttributeOrdering(): void
    {
        $a = $this->builder->build(['name' => 'X', 'attributes' => ['b' => '2', 'a' => '1']]);
        $b = $this->builder->build(['name' => 'X', 'attributes' => ['a' => '1', 'b' => '2']]);
        $this->assertSame($a, $b);
    }

    public function testSkipsEmptyPiecesAndCollapsesWhitespace(): void
    {
        $text = $this->builder->build([
            'name' => '  Spaced   Name  ',
            'categories' => ['', '  '],
            'attributes' => ['size' => '', 'fit' => 'slim'],
            'description' => '',
        ]);

        $this->assertSame('Spaced Name. fit: slim', $text);
    }

    public function testEmptyProductYieldsEmptyString(): void
    {
        $this->assertSame('', $this->builder->build([]));
    }
}
