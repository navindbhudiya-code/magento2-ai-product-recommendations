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

use NavinDBhudiya\ProductRecommendation\Service\Privacy\ConsentGate;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the consent gate.
 */
class ConsentGateTest extends TestCase
{
    /**
     * @var ConsentGate
     */
    private ConsentGate $gate;

    protected function setUp(): void
    {
        $this->gate = new ConsentGate();
    }

    public function testRecordsOnlyWithFeatureAndConsent(): void
    {
        $this->assertTrue($this->gate->mayRecord(true, true));
    }

    public function testConsentOffWritesNothing(): void
    {
        $this->assertFalse($this->gate->mayRecord(true, false));
    }

    public function testFeatureDisabledBlocksEverythingIncludingDemo(): void
    {
        $this->assertFalse($this->gate->mayRecord(false, true));
        $this->assertFalse($this->gate->mayRecord(false, true, true));
    }

    public function testDemoBypassesVisitorConsentButNeedsFeatureOn(): void
    {
        $this->assertTrue($this->gate->mayRecord(true, false, true));
    }
}
