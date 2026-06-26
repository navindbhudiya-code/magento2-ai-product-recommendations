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

namespace NavinDBhudiya\ProductRecommendation\Test\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Dependency-free smoke test for the integration suite.
 *
 * This intentionally does NOT bootstrap Magento (no DB, no app container) so the test
 * harness is green from Phase 0 on any host. DB-backed integration tests added in later
 * phases (indexing Luma, querying the vector store, attribution roll-ups) will run under
 * Magento's integration framework — see README.md.
 */
class SmokeTest extends TestCase
{
    /**
     * @var string Module root directory (three levels up from Test/Integration).
     */
    private string $moduleRoot;

    protected function setUp(): void
    {
        $this->moduleRoot = dirname(__DIR__, 2);
    }

    /**
     * registration.php must exist so the module can register with Magento.
     */
    public function testRegistrationFileExists(): void
    {
        $this->assertFileExists($this->moduleRoot . '/registration.php');
    }

    /**
     * module.xml must declare the canonical module name.
     */
    public function testModuleXmlDeclaresModuleName(): void
    {
        $moduleXml = $this->moduleRoot . '/etc/module.xml';
        $this->assertFileExists($moduleXml);
        $contents = (string) file_get_contents($moduleXml);
        $this->assertStringContainsString(
            'NavinDBhudiya_ProductRecommendation',
            $contents,
            'module.xml must declare the NavinDBhudiya_ProductRecommendation module.'
        );
    }

    /**
     * The canonical console namespace is recommendation:* (see dev/demo/AUDIT.md).
     */
    public function testCanonicalCommandNamespaceIsRegistered(): void
    {
        $diXml = (string) file_get_contents($this->moduleRoot . '/etc/di.xml');
        $this->assertStringContainsString(
            'Console\\Command\\IndexProducts',
            $diXml,
            'di.xml must register the recommendation:* console commands.'
        );
    }
}
