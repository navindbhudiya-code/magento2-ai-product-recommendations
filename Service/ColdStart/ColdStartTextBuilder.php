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

namespace NavinDBhudiya\ProductRecommendation\Service\ColdStart;

/**
 * Builds attribute-only embedding text for brand-new products that have no behaviour yet (pure).
 *
 * Cold-start products cannot be embedded from interactions, so we synthesise a stable text
 * representation from catalog attributes (name, categories, key attributes, description). The
 * output is deterministic and order-stable so re-embedding the same product yields the same text.
 */
class ColdStartTextBuilder
{
    /**
     * Build embedding text from a product's structured attributes.
     *
     * @param array $product ['name'=>string, 'categories'=>string[], 'attributes'=>[label=>value],
     *                        'description'=>string].
     * @return string
     */
    public function build(array $product): string
    {
        $parts = [];

        $name = trim((string) ($product['name'] ?? ''));
        if ($name !== '') {
            $parts[] = $name;
        }

        $categories = $this->cleanList($product['categories'] ?? []);
        if ($categories !== []) {
            $parts[] = 'Categories: ' . implode(', ', $categories);
        }

        foreach ($this->cleanAttributes($product['attributes'] ?? []) as $label => $value) {
            $parts[] = $label . ': ' . $value;
        }

        $description = trim(strip_tags((string) ($product['description'] ?? '')));
        if ($description !== '') {
            $parts[] = $description;
        }

        return $this->normalizeWhitespace(implode('. ', $parts));
    }

    /**
     * Normalise a list of strings (trim, drop empties, de-dupe, preserve order).
     *
     * @param mixed $list
     * @return string[]
     */
    private function cleanList($list): array
    {
        if (!is_array($list)) {
            return [];
        }
        $clean = [];
        foreach ($list as $value) {
            $value = trim((string) $value);
            if ($value !== '' && !in_array($value, $clean, true)) {
                $clean[] = $value;
            }
        }
        return $clean;
    }

    /**
     * Sort attributes by label for deterministic output; drop empty values.
     *
     * @param mixed $attributes
     * @return array
     */
    private function cleanAttributes($attributes): array
    {
        if (!is_array($attributes)) {
            return [];
        }
        $clean = [];
        foreach ($attributes as $label => $value) {
            $label = trim((string) $label);
            $value = trim((string) $value);
            if ($label !== '' && $value !== '') {
                $clean[$label] = $value;
            }
        }
        ksort($clean);
        return $clean;
    }

    /**
     * Collapse runs of whitespace into single spaces.
     *
     * @param string $text
     * @return string
     */
    private function normalizeWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}
