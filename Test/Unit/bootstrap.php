<?php
/**
 * NavinDBhudiya ProductRecommendation
 *
 * Unit-suite bootstrap. Provides a minimal stub for GuzzleHttp\ClientFactory — a
 * Magento-generated factory that is absent in the isolated unit environment — so HTTP
 * clients can be mocked. No-op when Magento has generated the real factory.
 *
 * @license MIT License
 */

declare(strict_types=1);

namespace GuzzleHttp;

if (!class_exists(ClientFactory::class)) {
    /**
     * Test-only stand-in for the generated GuzzleHttp\ClientFactory.
     */
    class ClientFactory
    {
        /**
         * @param array $config
         * @return Client
         */
        public function create(array $config = []): Client
        {
            return new Client();
        }
    }
}
