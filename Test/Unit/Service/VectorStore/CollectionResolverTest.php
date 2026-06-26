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

use NavinDBhudiya\ProductRecommendation\Service\VectorStore\CollectionResolver;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for per-store/locale collection naming.
 */
class CollectionResolverTest extends TestCase
{
    /**
     * @var CollectionResolver
     */
    private CollectionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CollectionResolver();
    }

    public function testPerStoreNaming(): void
    {
        $this->assertSame('navin_reco_1', $this->resolver->resolve('navin_reco', 1));
        $this->assertSame('navin_reco_2', $this->resolver->resolve('navin_reco', 2));
    }

    public function testLocaleAppendedAndSanitised(): void
    {
        $this->assertSame('navin_reco_3_en_us', $this->resolver->resolve('navin_reco', 3, 'en_US'));
        $this->assertSame('navin_reco_3_nl_nl', $this->resolver->resolve('navin_reco', 3, 'nl-NL'));
    }

    public function testDifferentLocalesNeverShareSpace(): void
    {
        // The babyonline NL/PL case: same store, different locales => different indexes.
        $this->assertFalse($this->resolver->isSameSpace('navin_reco', 5, 'nl_NL', 5, 'pl_PL'));
        $this->assertTrue($this->resolver->isSameSpace('navin_reco', 5, 'nl_NL', 5, 'nl_NL'));
    }

    public function testDifferentStoresNeverShareSpace(): void
    {
        $this->assertFalse($this->resolver->isSameSpace('navin_reco', 1, 'en_US', 2, 'en_US'));
    }

    public function testBaseIsSanitised(): void
    {
        $this->assertSame('magento_products_0', $this->resolver->resolve('Magento Products!', 0));
    }
}
