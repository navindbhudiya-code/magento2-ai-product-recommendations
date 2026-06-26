<?php
/**
 * NavinDBhudiya ProductRecommendation
 *
 * Unit-suite bootstrap. Provides minimal stubs for Magento-generated factory classes that are
 * absent in the isolated unit environment, so collaborators can be mocked. Each stub is a
 * no-op when Magento has already generated the real class.
 *
 * @license MIT License
 */

declare(strict_types=1);

namespace GuzzleHttp {

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
}

namespace Magento\Catalog\Model\ResourceModel\Product {

    if (!class_exists(CollectionFactory::class)) {
        /**
         * Test-only stand-in for the generated catalog product CollectionFactory.
         */
        class CollectionFactory
        {
            /**
             * @param array $data
             * @return mixed
             */
            public function create(array $data = [])
            {
                return null;
            }
        }
    }
}
