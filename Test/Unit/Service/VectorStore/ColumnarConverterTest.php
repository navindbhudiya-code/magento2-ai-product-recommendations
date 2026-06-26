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

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Service\VectorStore;

use NavinDBhudiya\ProductRecommendation\Service\VectorStore\ColumnarConverter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the match-list -> columnar converter.
 */
class ColumnarConverterTest extends TestCase
{
    /**
     * @var ColumnarConverter
     */
    private ColumnarConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ColumnarConverter();
    }

    public function testConvertsMatchesToColumnarShape(): void
    {
        $columnar = $this->converter->toColumnar([
            ['id' => 'product_10', 'distance' => 0.1, 'metadata' => ['product_id' => 10]],
            ['id' => 'product_11', 'distance' => 0.3, 'metadata' => ['product_id' => 11]],
        ]);

        $this->assertSame([['product_10', 'product_11']], $columnar['ids']);
        $this->assertSame([[0.1, 0.3]], $columnar['distances']);
        $this->assertSame([[['product_id' => 10], ['product_id' => 11]]], $columnar['metadatas']);
    }

    public function testEmptyMatchesProduceEmptyColumns(): void
    {
        $columnar = $this->converter->toColumnar([]);
        $this->assertSame([[]], $columnar['ids']);
        $this->assertSame([[]], $columnar['distances']);
        $this->assertSame([[]], $columnar['metadatas']);
    }

    public function testToleratesMissingKeys(): void
    {
        $columnar = $this->converter->toColumnar([['id' => 'product_5']]);
        $this->assertSame([['product_5']], $columnar['ids']);
        $this->assertSame([[0.0]], $columnar['distances']);
        $this->assertSame([[[]]], $columnar['metadatas']);
    }
}
