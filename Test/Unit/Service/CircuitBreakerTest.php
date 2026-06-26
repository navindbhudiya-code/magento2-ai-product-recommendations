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

namespace NavinDBhudiya\ProductRecommendation\Test\Unit\Service;

use Magento\Framework\App\CacheInterface;
use NavinDBhudiya\ProductRecommendation\Service\CircuitBreaker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the circuit breaker, backed by an in-memory cache double.
 */
class CircuitBreakerTest extends TestCase
{
    /**
     * @var CircuitBreaker
     */
    private CircuitBreaker $breaker;

    protected function setUp(): void
    {
        $store = [];
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturnCallback(
            static function ($key) use (&$store) {
                return $store[$key] ?? false;
            }
        );
        $cache->method('save')->willReturnCallback(
            static function ($data, $key) use (&$store) {
                $store[$key] = $data;
                return true;
            }
        );
        $cache->method('remove')->willReturnCallback(
            static function ($key) use (&$store) {
                unset($store[$key]);
                return true;
            }
        );

        $this->breaker = new CircuitBreaker($cache, $this->createMock(LoggerInterface::class));
    }

    public function testStartsClosed(): void
    {
        $this->assertFalse($this->breaker->isOpen('claude_api'));
        $this->assertSame(0, $this->breaker->getFailureCount('claude_api'));
    }

    public function testOpensAfterFiveFailures(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->breaker->recordFailure('claude_api');
        }
        $this->assertFalse($this->breaker->isOpen('claude_api'), 'Still closed at 4 failures');

        $this->breaker->recordFailure('claude_api');
        $this->assertTrue($this->breaker->isOpen('claude_api'), 'Open at the 5th failure');
        $this->assertSame(5, $this->breaker->getFailureCount('claude_api'));
    }

    public function testResetClosesCircuit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->breaker->recordFailure('claude_api');
        }
        $this->assertTrue($this->breaker->isOpen('claude_api'));

        $this->breaker->reset('claude_api');
        $this->assertFalse($this->breaker->isOpen('claude_api'));
        $this->assertSame(0, $this->breaker->getFailureCount('claude_api'));
    }

    public function testServicesAreIsolated(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure('claude_api');
        }
        $this->assertTrue($this->breaker->isOpen('claude_api'));
        $this->assertFalse($this->breaker->isOpen('openai_api'));
    }
}
