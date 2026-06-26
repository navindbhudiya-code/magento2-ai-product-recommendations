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

namespace NavinDBhudiya\ProductRecommendation\Service\VectorStore;

/**
 * Resolves the per-store-view (and per-locale) collection/index name (pure).
 *
 * Each store view — and each locale — gets its own vector space so languages never share an
 * index (the NL/PL cross-locale-bleed problem). Names are lowercased and sanitised so they are
 * valid OpenSearch index names and ChromaDB collection names.
 */
class CollectionResolver
{
    /**
     * Resolve a collection/index name for a store view and optional locale.
     *
     * @param string $base Base name / prefix (e.g. "navin_reco" or "magento_products").
     * @param int $storeId
     * @param string|null $locale e.g. "en_US"; when given, appended so locales never share space.
     * @return string
     */
    public function resolve(string $base, int $storeId, ?string $locale = null): string
    {
        $parts = [$this->sanitize($base), (string) max(0, $storeId)];
        if ($locale !== null && $locale !== '') {
            $parts[] = $this->sanitize($locale);
        }
        return implode('_', array_filter($parts, static fn ($p) => $p !== ''));
    }

    /**
     * Whether two (store, locale) combinations map to the same vector space.
     *
     * @param string $base
     * @param int $storeIdA
     * @param string|null $localeA
     * @param int $storeIdB
     * @param string|null $localeB
     * @return bool
     */
    public function isSameSpace(
        string $base,
        int $storeIdA,
        ?string $localeA,
        int $storeIdB,
        ?string $localeB
    ): bool {
        return $this->resolve($base, $storeIdA, $localeA) === $this->resolve($base, $storeIdB, $localeB);
    }

    /**
     * Lowercase and replace any non-alphanumeric run with a single underscore.
     *
     * @param string $value
     * @return string
     */
    private function sanitize(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        return trim($value, '_');
    }
}
